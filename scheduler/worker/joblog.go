package worker

import (
	"context"
	"scheduler/common"
	"time"
)

// jobLogRetentionDays 审计流水保留天数，过期由 StartLogCleaner 清理
const jobLogRetentionDays = 7

// WriteJobLog 写一条任务执行审计流水到 jxmall_schedule_job_log
// 在每次任务派发完成后调用（queue / cron 均覆盖）。失败只记日志，不影响主流程。
func WriteJobLog(jobClass, source string, success bool, attempt, durationMs int, errMsg string) {
	if common.DB == nil {
		return
	}
	succ := 0
	if success {
		succ = 1
	}
	row := map[string]interface{}{
		"job_class":   jobClass,
		"source":      source,
		"success":     succ,
		"attempt":     attempt,
		"duration_ms": durationMs,
		"error":       truncateRunes(errMsg, 500),
		"executed_at": time.Now().Unix(),
		"created_at":  time.Now().Unix(),
	}
	table := common.DBPrefix + "schedule_job_log"
	if err := common.DB.Table(table).Create(row).Error; err != nil {
		common.Logger.Printf("⚠️ 审计流水写入失败 | Class: %s | Err: %v", jobClass, err)
	}
}

// StartLogCleaner 定期清理过期审计流水（启动后立即跑一次，之后每 24h）
func StartLogCleaner(ctx context.Context) {
	cleanOldJobLogs()
	ticker := time.NewTicker(24 * time.Hour)
	defer ticker.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			cleanOldJobLogs()
		}
	}
}

func cleanOldJobLogs() {
	if common.DB == nil {
		return
	}
	cutoff := time.Now().Unix() - jobLogRetentionDays*24*3600
	table := common.DBPrefix + "schedule_job_log"
	res := common.DB.Exec("DELETE FROM "+table+" WHERE executed_at < ?", cutoff)
	if res.Error != nil {
		common.Logger.Printf("⚠️ 清理过期审计流水失败: %v", res.Error)
		return
	}
	if res.RowsAffected > 0 {
		common.Logger.Printf("🧹 已清理 %d 条过期审计流水(>%d天)", res.RowsAffected, jobLogRetentionDays)
	}
}
