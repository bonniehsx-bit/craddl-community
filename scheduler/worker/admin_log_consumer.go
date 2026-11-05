package worker

import (
	"context"
	"encoding/json"
	"scheduler/common"
	"time"

	"github.com/go-redis/redis/v8"
)

// StartAdminLogConsumer 独立线程消费操作日志
func StartAdminLogConsumer() {
	// 如果未配置队列键名，则不启动消费
	if common.Cfg.Redis.AdminLogKey == "" {
		common.Logger.Println("ℹ️ 未配置 admin_log_key，操作日志直写服务跳过启动")
		return
	}

	queueKey := common.GetKeyName(common.Cfg.Redis.AdminLogKey)
	common.Logger.Printf("📜 操作日志直写守护进程已启动，监听: %s", queueKey)
	bgCtx := context.Background()

	for {
		// BLPOP 阻塞拉取 (最多等待 2 秒)，彻底不消耗 CPU
		result, err := common.RDB.BLPop(bgCtx, 2*time.Second, queueKey).Result()

		if err != nil {
			if err != redis.Nil { // 非超时错误记录一下
				common.Logger.Printf("❌ 获取 AdminLog 异常: %v", err)
				time.Sleep(1 * time.Second)
			}
			continue
		}

		if len(result) < 2 {
			continue
		}

		// result[0] 是 key，result[1] 是 payload
		payloadStr := result[1]

		// 反序列化 JSON 到 map
		var logData map[string]interface{}
		if err := json.Unmarshal([]byte(payloadStr), &logData); err != nil {
			common.Logger.Printf("⚠️ AdminLog 解析失败: %v, 数据: %s", err, payloadStr)
			continue
		}

		// 开始将数据极速写入 MySQL
		tableName := common.DBPrefix + "admin_action_log"
		if err := common.DB.Table(tableName).Create(&logData).Error; err != nil {
			common.Logger.Printf("❌ AdminLog 落盘失败: %v", err)
			// (可选) 失败重试/进入死信队列，这里简单起见不做回插
		}
	}
}
