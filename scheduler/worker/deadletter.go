package worker

import (
	"encoding/json"
	"scheduler/common"
	"scheduler/config"
	"time"
)

// WriteDeadLetter 把重试耗尽的任务写入死信表 jxmall_schedule_dead_job
// 后台「失败任务」可人工重投 / 忽略。
func WriteDeadLetter(env config.Envelope, lastErr string) {
	payloadJSON, _ := json.Marshal(env.Payload)

	jobClass := ""
	if env.Payload != nil {
		if c, ok := env.Payload["class"].(string); ok {
			jobClass = c
		}
	}

	lastErr = truncateRunes(lastErr, 500)
	now := time.Now().Unix()

	row := map[string]interface{}{
		"job_class":  jobClass,
		"payload":    string(payloadJSON),
		"attempt":    env.Attempt,
		"last_error": lastErr,
		"status":     0, // 0 待处理
		"failed_at":  now,
		"created_at": now,
	}

	table := common.DBPrefix + "schedule_dead_job"
	if err := common.DB.Table(table).Create(row).Error; err != nil {
		common.Logger.Printf("❌ 死信落库失败 | Class: %s | Err: %v", jobClass, err)
		return
	}
	common.Logger.Printf("⚰️ 已写入死信表 | Class: %s | attempt=%d", jobClass, env.Attempt)
}

// truncateRunes 按 rune 安全截断，避免切坏多字节字符
func truncateRunes(s string, max int) string {
	r := []rune(s)
	if len(r) <= max {
		return s
	}
	return string(r[:max])
}
