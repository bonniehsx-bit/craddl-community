package worker

import (
	"context"
	"encoding/json"
	"scheduler/common"
	"scheduler/config"
	"strconv"
	"time"
)

const upgradeStatusKey = "jxmall_scheduler:upgrade:status"
const ManualUpgradeMessage = "社区版请使用配套发布包手动升级"

// IsUpgradeCommand 判断信封是否为升级命令
func IsUpgradeCommand(env config.Envelope) bool {
	if env.Payload == nil {
		return false
	}
	t, _ := env.Payload["type"].(string)
	return t == "upgrade"
}

// IsCheckNowCommand 判断信封是否为「立即检查更新」命令
// (后台点"检查更新"时下发,scheduler 立即心跳一次刷新云端版本,不等下一轮)
func IsCheckNowCommand(env config.Envelope) bool {
	if env.Payload == nil {
		return false
	}
	t, _ := env.Payload["type"].(string)
	return t == "check_now"
}

// ResetStaleUpgrade 调度器启动时调用:清掉上一条命残留的"进行中"升级状态。
// 新进程刚起不可能有升级在跑,任何 queued/downloading/extracting/migrating 都是被重启(如手动更新)
// 中断的孤儿 → 标记为 failed,让后台不再永久卡"下载中",用户可直接重试(重试会重新完整下载覆盖)。
func ResetStaleUpgrade() {
	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
	defer cancel()
	raw, err := common.RDB.Get(ctx, upgradeStatusKey).Result()
	if err != nil || raw == "" {
		return
	}
	var st struct {
		State  string `json:"state"`
		Target int    `json:"target_version_code"`
	}
	if json.Unmarshal([]byte(raw), &st) != nil {
		return
	}
	switch st.State {
	case "queued", "downloading", "extracting", "migrating":
		message := "升级被中断(调度器已重启),请重试"
		if !OnlineUpgradeEnabled {
			message = ManualUpgradeMessage
		}
		setUpgradeStatus("failed", st.Target, message)
		common.Logger.Printf("⚠️ [Upgrade] 启动检测到残留进行中状态(%s),已标记为 failed", st.State)
	}
}

func setUpgradeStatus(state string, target int, message string) {
	payload := map[string]interface{}{
		"state":               state,
		"target_version_code": target,
		"message":             message,
		"updated_at":          time.Now().Unix(),
	}
	b, err := json.Marshal(payload)
	if err != nil {
		return
	}
	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Second)
	defer cancel()
	if err := common.RDB.Set(ctx, upgradeStatusKey, string(b), 0).Err(); err != nil {
		common.Logger.Printf("⚠️ [Upgrade] 写状态失败: %v", err)
	}
}

// payloadInt 从 payload 取整数(JSON 数字会被解析为 float64)
func payloadInt(payload map[string]interface{}, key string) int {
	switch v := payload[key].(type) {
	case float64:
		return int(v)
	case int:
		return v
	case string:
		n, _ := strconv.Atoi(v)
		return n
	}
	return 0
}
