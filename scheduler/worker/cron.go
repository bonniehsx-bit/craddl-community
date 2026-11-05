package worker

import (
	"context"
	"crypto/md5"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"os/exec"
	"path/filepath"
	"scheduler/common"
	"scheduler/config"
	"sync"
	"time"

	"github.com/go-redis/redis/v8"
	"github.com/robfig/cron/v3"
)

var (
	CronRunner     *cron.Cron
	cronMutex      sync.Mutex
	lastConfigHash string
	// cronModes 记录 class → runMode('fpm'|'cli')。
	// 队列侧重投 cron 任务时需要据此沿用原调度方式,否则 cli 任务会被 FastCGI 派发、再撞 30s 超时。
	cronModes = make(map[string]string)
)

func StartConfigWatcher(interval time.Duration) {
	ticker := time.NewTicker(interval)
	defer ticker.Stop()
	for range ticker.C {
		CheckAndReload()
	}
}

func CheckAndReload() {
	if common.Cfg.Redis.CronKey == "" {
		common.Cfg.Redis.CronKey = "jxmall_cron_config"
	}

	ctx, cancel := context.WithTimeout(context.Background(), 3*time.Second)
	defer cancel()

	val, err := common.RDB.Get(ctx, common.Cfg.Redis.CronKey).Result()
	if err == redis.Nil {
		val = "[]" // 如果由于刷新缓存导致键名丢失，直接托底给个空值保证进程不崩
		common.Logger.Printf("⚠️ [Watcher] 侦测到 Redis [%s] 键已丢失！正在自动唤醒后台 PHP 触发全量同步补偿机制...", common.Cfg.Redis.CronKey)
		go autoSyncCrontab()
	} else if err != nil {
		common.Logger.Printf("⚠️ [Watcher] 读取 Redis 失败: %v", err)
		return
	}

	hasher := md5.New()
	hasher.Write([]byte(val))
	currentHash := hex.EncodeToString(hasher.Sum(nil))

	cronMutex.Lock()
	if currentHash == lastConfigHash {
		cronMutex.Unlock()
		return
	}

	common.Logger.Printf("🔄 [Watcher] 配置变更检测 (Hash: %s -> %s)", lastConfigHash, currentHash)
	lastConfigHash = currentHash
	cronMutex.Unlock()

	ReloadCron(val)
}

func ReloadCron(jsonConfig string) {
	cronMutex.Lock()
	defer cronMutex.Unlock()

	if CronRunner != nil {
		CronRunner.Stop()
	}

	CronRunner = cron.New()

	var tasks []config.CronConfig
	if err := json.Unmarshal([]byte(jsonConfig), &tasks); err != nil {
		common.Logger.Printf("❌ [Watcher] JSON 解析失败: %v", err)
	}

	uniqueTasks := make(map[string]bool)
	count := 0

	// 重建 class → runMode 映射(在 dedup 之前记录,保证每个类都有)
	modes := make(map[string]string, len(tasks))
	for _, task := range tasks {
		modes[task.Class] = task.Mode
	}
	cronModes = modes

	for _, task := range tasks {
		dedupKey := task.Class
		if uniqueTasks[dedupKey] {
			continue
		}
		uniqueTasks[dedupKey] = true

		t := task
		_, err := CronRunner.AddFunc(t.Expression, func() {
			triggerCronTask(t)
		})
		if err != nil {
			common.Logger.Printf("❌ [Watcher] 无效 Cron 规则 | Class: %s", t.Class)
		} else {
			count++
		}
	}

	CronRunner.Start()
	common.Logger.Printf("✅ [Watcher] 热重载完成 | 当前生效任务数: %d", count)
}

// CronModeOf 返回该任务类的调度方式('cli' 或 'fpm')。未知类/未声明按 'fpm'(与 PHP Task::runMode() 默认一致)。
func CronModeOf(class string) string {
	cronMutex.Lock()
	defer cronMutex.Unlock()
	if m, ok := cronModes[class]; ok && m == "cli" {
		return "cli"
	}
	return "fpm"
}

func triggerCronTask(task config.CronConfig) {
	// 🟢 License Check
	if !tasksEnabled() {
		common.Logger.Printf("🚫 [Cron] 授权失效，跳过定时任务: %s", task.Class)
		return
	}

	timeKey := time.Now().Format("200601021504")
	lockKey := fmt.Sprintf("scheduler:lock:%s:%s", task.Class, timeKey)

	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
	defer cancel()

	acquired, err := common.RDB.SetNX(ctx, lockKey, "1", 55*time.Second).Result()
	if err != nil || !acquired {
		return
	}

	payloadMap := map[string]interface{}{
		"class":  task.Class,
		"method": task.Method,
		"type":   "cron",
	}
	payloadBytes, _ := json.Marshal(payloadMap)

	WorkerPool <- struct{}{}
	Wg.Add(1)
	go func() {
		defer Wg.Done()
		defer func() { <-WorkerPool }()
		var ok bool
		var errMsg string
		var durMs int64
		if task.Mode == "cli" {
			// 重/长任务:走命令行 php yii,无 30s 派发超时、独立进程不占 fpm worker
			ok, errMsg, durMs = DispatchToCli(task.Class, task.Method)
		} else {
			ok, errMsg, durMs = DispatchToFpm(string(payloadBytes), "cron")
		}
		// cron 原始触发记为第 1 次尝试
		WriteJobLog(task.Class, "cron", ok, 1, int(durMs), errMsg)
		if !ok && task.Mode != "cli" {
			// FPM 任务失败 → 注入队列重试(队列也走 FPM,复用退避/死信/Reaper);
			// cli 任务不走 FPM 队列重试(会再撞 30s),失败等下一轮 cron 触发即可。
			enqueueCronRetry(payloadMap, errMsg)
		}
	}()
}

// DispatchToCli 用命令行 php yii 执行 cron 任务(mode=cli):无 FastCGI 30s 硬超时、独立进程不占 fpm worker。
// 复用 PHP 侧 scheduler/run-cron → Manager::run(逐商城 + flock 防重入)。退出码 0=成功、非 0=失败。
func DispatchToCli(class, method string) (bool, string, int64) {
	start := time.Now()
	php := common.Cfg.Yii.PhpBin
	if php == "" {
		php = "php"
	}
	if method == "" {
		method = "run"
	}
	// 由 EntryFile(.../api/web/index.php)反推 yii console 脚本(.../api/yii),与 autoSyncCrontab 一致
	yiiPath := filepath.Join(filepath.Dir(filepath.Dir(common.Cfg.Yii.EntryFile)), "yii")
	common.Logger.Printf("🖥️  [Cron/CLI] 开始 | Class: %s (php yii scheduler/run-cron)", class)

	// 给个宽松上限防跑飞(30min);正常全表对账远小于此
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Minute)
	defer cancel()

	cmd := exec.CommandContext(ctx, php, yiiPath, "scheduler/run-cron", class, method)
	out, err := cmd.CombinedOutput()
	durMs := time.Since(start).Milliseconds()

	if ctx.Err() == context.DeadlineExceeded {
		common.Logger.Printf("❌ [Cron/CLI] 超时(>30min) | Class: %s | 耗时: %dms", class, durMs)
		return false, "CLI 执行超时(>30min): " + class, durMs
	}
	if err != nil {
		msg := string(out)
		if len(msg) > 480 {
			msg = msg[:480]
		}
		common.Logger.Printf("❌ [Cron/CLI] 失败 | Class: %s | 耗时: %dms | Err: %v | 输出: %s", class, durMs, err, msg)
		return false, "CLI 执行失败: " + err.Error() + " | " + msg, durMs
	}
	common.Logger.Printf("✅ [Cron/CLI] 完成 | Class: %s | 耗时: %dms", class, durMs)
	return true, "", durMs
}

// enqueueCronRetry cron 派发失败后，把任务包成信封注入 normal 车道队列。
// envelope.Attempt=1 → 后续队列侧再重试 2 次（总计 max=3），仍失败进死信表。
func enqueueCronRetry(payload map[string]interface{}, errMsg string) {
	env := config.Envelope{
		V:          1,
		Attempt:    1,
		Max:        3,
		Lane:       "normal",
		EnqueuedAt: time.Now().Unix(),
		Payload:    payload,
	}
	member, _ := json.Marshal(env)

	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
	defer cancel()

	backoff := backoffSeconds(env.Attempt) // 第 1 次重试退避 60s
	common.RDB.ZAdd(ctx, laneKey("normal"), &redis.Z{
		Score:  float64(time.Now().Unix() + backoff),
		Member: string(member),
	})
	class, _ := payload["class"].(string)
	common.Logger.Printf("♻️ [Cron] 失败转入队列重试 | Class: %s | %ds 后 | 原因: %s", class, backoff, errMsg)
}

// 自动执行 yii crontab/sync
func autoSyncCrontab() {
	// 根据配置文件的 EntryFile (如: .../api/web/index.php) 反向推导出 yii console 脚本的绝对路径 (如: .../api/yii)
	yiiPath := filepath.Join(filepath.Dir(filepath.Dir(common.Cfg.Yii.EntryFile)), "yii")

	cmd := exec.Command("php", yiiPath, "crontab/sync")
	output, err := cmd.CombinedOutput()
	if err != nil {
		common.Logger.Printf("❌ [AutoSync] 自动拉起同步指令惨败 | Err: %v | 原始回显: %s", err, string(output))
	} else {
		common.Logger.Printf("🎉 [AutoSync] 已成功挽救定时任务环境树，下一次 Watcher Ticker 将自动加载这些恢复的 Cron 数据。")
	}
}
