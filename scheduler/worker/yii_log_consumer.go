package worker

import (
	"context"
	"crypto/md5"
	"encoding/json"
	"fmt"
	"scheduler/common"
	"time"

	"github.com/go-redis/redis/v8"
)

// StartYiiLogConsumer 消费 Yii2 error/warning 日志，带24小时内去重
func StartYiiLogConsumer() {
	if common.Cfg.Redis.YiiLogKey == "" {
		common.Logger.Println("ℹ️ 未配置 yii_log_key，Yii2错误监控服务跳过启动")
		return
	}

	queueKey := common.GetKeyName(common.Cfg.Redis.YiiLogKey)
	common.Logger.Printf("🔍 Yii2错误监控已启动，监听: %s", queueKey)

	bgCtx := context.Background()
	tableName := common.DBPrefix + "yii_error_log"

	for {
		result, err := common.RDB.BLPop(bgCtx, 2*time.Second, queueKey).Result()
		if err != nil {
			if err != redis.Nil {
				common.Logger.Printf("❌ 获取 YiiLog 异常: %v", err)
				time.Sleep(1 * time.Second)
			}
			continue
		}
		if len(result) < 2 {
			continue
		}

		var logData map[string]interface{}
		if err := json.Unmarshal([]byte(result[1]), &logData); err != nil {
			common.Logger.Printf("⚠️ YiiLog 解析失败: %v", err)
			continue
		}

		// 计算去重指纹：level + category + message 前200字符
		level := fmt.Sprintf("%v", logData["level"])
		category := fmt.Sprintf("%v", logData["category"])
		message := fmt.Sprintf("%v", logData["message"])
		msgShort := message
		if len([]rune(msgShort)) > 200 {
			msgShort = string([]rune(msgShort)[:200])
		}
		fingerprint := fmt.Sprintf("%x", md5.Sum([]byte(level+":"+category+":"+msgShort)))

		now := time.Now().Unix()
		since24h := now - 86400 // 24小时窗口去重

		// 查询24小时内是否有相同指纹的记录
		var existingID uint
		var existingCount int64
		row := common.DB.Table(tableName).
			Select("id, count").
			Where("fingerprint = ? AND created_at > ? AND is_delete = 0", fingerprint, since24h).
			Row()
		if row != nil {
			_ = row.Scan(&existingID, &existingCount)
		}

		if existingID > 0 {
			// 已存在：+1计数，更新最后出现时间
			common.DB.Exec(
				"UPDATE "+tableName+" SET `count`=`count`+1, last_seen_at=?, updated_at=?, updated_time=? WHERE id=?",
				now, now, time.Now().Format("2006-01-02 15:04:05"), existingID,
			)
		} else {
			// 新错误：完整插入
			logData["fingerprint"] = fingerprint
			logData["count"] = 1
			logData["last_seen_at"] = now
			logData["is_delete"] = 0
			if err := common.DB.Table(tableName).Create(&logData).Error; err != nil {
				common.Logger.Printf("❌ YiiLog 落盘失败: %v", err)
			}
		}
	}
}
