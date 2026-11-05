package worker

import (
	"context"
	"encoding/json"
	"scheduler/common"
	"scheduler/config"
	"strconv"
	"sync"
	"time"

	"github.com/go-redis/redis/v8"
)

var (
	WorkerPool    chan struct{}
	Wg            sync.WaitGroup
	lastHeartbeat time.Time
)

// HeartbeatKey 调度器存活心跳 key —— 后台据此显示状态灯
const HeartbeatKey = "jxmall_scheduler:heartbeat"

// visibilityTimeout 任务被 claim 到 processing 后的可见性超时（秒）
// 必须大于 FastCGI 派发超时(30s)，超时未 ack 的任务由 Reaper 回收。
const visibilityTimeout int64 = 90

// throttlePerTick throttle 车道每个 tick(200ms) 最多派发的任务数
// 4/tick × 5tick/s = 20/s，用于批量推送等削峰场景
const throttlePerTick = 4

// processingKey processing 队列 key（已认领待执行的任务）
func processingKey() string {
	return common.Cfg.Redis.TaskKey + ":processing"
}

// laneKey 车道 → ZSet key（与 PHP JobHelper::laneKey 保持一致）
func laneKey(lane string) string {
	base := common.Cfg.Redis.TaskKey
	switch lane {
	case "high":
		return base + ":high"
	case "low":
		return base + ":low"
	case "throttle":
		return base + ":throttle"
	default: // normal
		return base
	}
}

// uniqueRedisKey 唯一性投递占位 key
func uniqueRedisKey(uniqueKey string) string {
	return common.Cfg.Redis.TaskKey + ":uniq:" + uniqueKey
}

// releaseUniqueKey 任务进入终态(成功/死信)时释放唯一性占位
func releaseUniqueKey(env config.Envelope) {
	if env.UniqueKey == "" {
		return
	}
	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
	defer cancel()
	common.RDB.Del(ctx, uniqueRedisKey(env.UniqueKey))
}

// writeHeartbeat 向 Redis 写存活心跳（节流：每 5s 一次）
func writeHeartbeat() {
	if time.Since(lastHeartbeat) < 5*time.Second {
		return
	}
	lastHeartbeat = time.Now()
	rCtx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
	defer cancel()
	common.RDB.Set(rCtx, HeartbeatKey, time.Now().Unix(), 60*time.Second)
}

func RunScheduler(ctx context.Context) {
	ticker := time.NewTicker(200 * time.Millisecond)
	defer ticker.Stop()

	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			// 心跳在最前面写：即便授权失效 / 无空闲 worker，调度器本身仍是存活的
			writeHeartbeat()

			freeWorkers := common.Cfg.Server.WorkerNum - len(WorkerPool)
			if freeWorkers <= 0 {
				continue
			}

			// 🟢 License Check
			if !tasksEnabled() {
				continue
			}

			if freeWorkers > 50 {
				freeWorkers = 50
			}
			fetchAndDispatch(ctx, freeWorkers)
		}
	}
}

// fetchAndDispatch 按车道优先级 claim 到期任务并派发
// 顺序：high → normal → low 吃满 free；throttle 车道每 tick 限量
func fetchAndDispatch(ctx context.Context, limit int) {
	free := limit

	// 优先级车道：high → normal → low
	for _, lane := range []string{"high", "normal", "low"} {
		if free <= 0 {
			return
		}
		claimed := claimLane(laneKey(lane), free)
		dispatchAll(claimed)
		free -= len(claimed)
	}

	// throttle 车道：限量派发（削峰），最多 min(throttlePerTick, free)
	if free > 0 {
		n := throttlePerTick
		if n > free {
			n = free
		}
		dispatchAll(claimLane(laneKey("throttle"), n))
	}
}

// claimLane 从指定车道 ZSet 原子 claim 至多 limit 个到期任务到 processing
func claimLane(laneZSet string, limit int) []string {
	if limit <= 0 {
		return nil
	}
	rCtx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
	defer cancel()

	now := time.Now().Unix()
	deadline := now + visibilityTimeout

	result, err := common.PopScript.Run(rCtx, common.RDB,
		[]string{laneZSet, processingKey()},
		now, limit, deadline).StringSlice()

	if err != nil && err != redis.Nil {
		common.Logger.Printf("❌ claim 失败 [%s]: %v", laneZSet, err)
		return nil
	}
	return result
}

// dispatchAll 为每个 claim 到的任务起协程派发
func dispatchAll(members []string) {
	for _, member := range members {
		WorkerPool <- struct{}{}
		Wg.Add(1)
		go func(m string) {
			defer Wg.Done()
			defer func() { <-WorkerPool }()
			processQueueMember(m)
		}(member)
	}
}

// processQueueMember 解析信封 → 派发 → 成功 ack / 失败重试
func processQueueMember(member string) {
	env := parseEnvelope(member)

	if env.Payload == nil {
		common.Logger.Printf("❌ 任务 payload 为空，直接进死信")
		ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
		common.RDB.ZRem(ctx, processingKey(), member)
		cancel()
		WriteDeadLetter(env, "payload 为空或解析失败")
		releaseUniqueKey(env)
		return
	}

	// 升级命令:scheduler 原生执行(不转 PHP-FPM —— 代码正被替换)。
	// ★ 先 ack 移出 processing 再执行:升级耗时(下载大包+迁移)远超可见性超时(90s),
	//   若不先 ack,Reaper 会误判超时重投 → 重复升级。升级自身幂等(内存锁)+ 失败不重试。
	if IsUpgradeCommand(env) {
		resolveSuccess(member, env)
		HandleUpgrade(env)
		return
	}

	// 立即检查更新命令:马上心跳一次刷新云端版本(写 Redis available),ack 不重试
	if IsCheckNowCommand(env) {
		refreshUpgradeStatus()
		resolveSuccess(member, env)
		return
	}

	var ok bool
	var errMsg string
	var durMs int64
	// cron 任务经队列重投时必须沿用其 runMode:cli 任务若走 FastCGI 会再撞 30s 派发超时
	// (read unix .../php-cgi.sock: use of closed network connection)。
	// 新的 cli 失败已不入队(见 enqueueCronRetry),此处兜底历史信封与其它入队来源。
	if sourceOf(env) == "cron" && CronModeOf(classOf(env)) == "cli" {
		ok, errMsg, durMs = DispatchToCli(classOf(env), methodOf(env))
	} else {
		payloadJSON, _ := json.Marshal(env.Payload)
		ok, errMsg, durMs = DispatchToFpm(string(payloadJSON), "queue")
	}

	// 审计流水：source 按 payload.type 判定（cron 任务经队列重试仍记 cron）
	// attempt 以 1 起算（env.Attempt 是本次执行前已尝试次数）
	WriteJobLog(classOf(env), sourceOf(env), ok, env.Attempt+1, int(durMs), errMsg)

	if ok {
		resolveSuccess(member, env)
	} else {
		resolveFailure(member, env, errMsg)
	}
}

// parseEnvelope 解析 ZSet 成员为信封；兼容旧的"裸 payload"格式
func parseEnvelope(member string) config.Envelope {
	var env config.Envelope
	_ = json.Unmarshal([]byte(member), &env)
	if env.Payload == nil {
		// 旧格式：整个成员就是 payload，无信封
		var raw map[string]interface{}
		_ = json.Unmarshal([]byte(member), &raw)
		env = config.Envelope{V: 1, Attempt: 0, Payload: raw}
	}
	if env.Max <= 0 {
		env.Max = 3
	}
	if env.Lane == "" {
		env.Lane = "normal"
	}
	return env
}

// resolveSuccess 任务成功 → 从 processing 删除（ack）+ 释放唯一性占位
func resolveSuccess(member string, env config.Envelope) {
	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
	defer cancel()
	removed, _ := common.RDB.ZRem(ctx, processingKey(), member).Result()
	if removed == 0 {
		// 已被 Reaper 回收并重投，唯一性占位仍由那个副本持有，此处不释放
		common.Logger.Printf("⚠️ ack 时任务已被 Reaper 回收，可能重复执行（任务需保证幂等）")
		return
	}
	releaseUniqueKey(env)
}

// resolveFailure 任务失败 → 重试入队 或 写死信
func resolveFailure(member string, env config.Envelope, errMsg string) {
	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
	defer cancel()

	// 抢占处理权：ZRem 返回 1 才由本协程处理，避免与 Reaper 重复处理
	removed, _ := common.RDB.ZRem(ctx, processingKey(), member).Result()
	if removed == 0 {
		return
	}

	env.Attempt++
	jobClass := classOf(env)

	if env.Attempt < env.Max {
		backoff := backoffSeconds(env.Attempt)
		newMember, _ := json.Marshal(env)
		// 重试回到原车道（unique_key 占位继续保持，不释放）
		common.RDB.ZAdd(ctx, laneKey(env.Lane), &redis.Z{
			Score:  float64(time.Now().Unix() + backoff),
			Member: string(newMember),
		})
		common.Logger.Printf("♻️ 重试 | Class: %s | lane=%s | attempt=%d/%d | %ds 后重投 | 原因: %s",
			jobClass, env.Lane, env.Attempt, env.Max, backoff, errMsg)
	} else {
		WriteDeadLetter(env, errMsg)
		releaseUniqueKey(env) // 终态：释放唯一性占位
		common.Logger.Printf("☠️ 重试耗尽 | Class: %s | attempt=%d | 原因: %s",
			jobClass, env.Attempt, errMsg)
	}
}

// sourceOf 判定任务来源：payload.type == "cron" 即 cron，否则 queue
func sourceOf(env config.Envelope) string {
	if env.Payload != nil {
		if t, ok := env.Payload["type"].(string); ok && t == "cron" {
			return "cron"
		}
	}
	return "queue"
}

// classOf 取信封内的任务类名
func classOf(env config.Envelope) string {
	if env.Payload != nil {
		if c, ok := env.Payload["class"].(string); ok {
			return c
		}
	}
	return "Unknown"
}

// methodOf 取信封内的任务方法名，缺省 run（与 PHP Manager::run 一致）
func methodOf(env config.Envelope) string {
	if env.Payload != nil {
		if m, ok := env.Payload["method"].(string); ok && m != "" {
			return m
		}
	}
	return "run"
}

// backoffSeconds 指数退避：60s → 120s → 240s ... 封顶 1h
func backoffSeconds(attempt int) int64 {
	base := int64(60)
	for i := 1; i < attempt; i++ {
		base *= 2
		if base >= 3600 {
			return 3600
		}
	}
	return base
}

// StartReaper 回收 processing 队列里认领超时的任务（执行进程崩溃 / 卡死导致）
func StartReaper(ctx context.Context) {
	ticker := time.NewTicker(30 * time.Second)
	defer ticker.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			reapTimedOut()
		}
	}
}

func reapTimedOut() {
	ctx, cancel := context.WithTimeout(context.Background(), 3*time.Second)
	defer cancel()

	now := time.Now().Unix()
	timedOut, err := common.RDB.ZRangeByScore(ctx, processingKey(), &redis.ZRangeBy{
		Min: "-inf",
		Max: strconv.FormatInt(now, 10),
	}).Result()
	if err != nil || len(timedOut) == 0 {
		return
	}

	common.Logger.Printf("🔧 [Reaper] 发现 %d 个认领超时任务，回收处理", len(timedOut))
	for _, member := range timedOut {
		env := parseEnvelope(member)
		resolveFailure(member, env, "认领超时(执行进程可能崩溃或卡死)")
	}
}

func MonitorEvents() {
	if common.Cfg.Redis.EventKey == "" {
		return
	}
	pubsub := common.RDB.Subscribe(context.Background(), common.Cfg.Redis.EventKey)
	defer pubsub.Close()
	ch := pubsub.Channel()
	for msg := range ch {
		var event config.EnqueueEvent
		if err := json.Unmarshal([]byte(msg.Payload), &event); err == nil {
			common.Logger.Printf("📅 新增任务 | Class: %s", event.Class)
		}
	}
}
