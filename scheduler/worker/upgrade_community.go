//go:build community

package worker

import (
	"scheduler/common"
	"scheduler/config"
)

const OnlineUpgradeEnabled = false

func tasksEnabled() bool {
	return true
}

// 旧版队列里的检查更新命令只确认消费，社区版由部署者手动升级。
func refreshUpgradeStatus() {}

// HandleUpgrade 将历史或直接入队的升级命令结束为失败。
func HandleUpgrade(env config.Envelope) {
	target := payloadInt(env.Payload, "version_code")
	setUpgradeStatus("failed", target, ManualUpgradeMessage)
	common.Logger.Printf("🚫 [Upgrade] 拒绝社区版在线升级，目标版本码: %d", target)
}
