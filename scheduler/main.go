package main

import (
	"context"
	"os"
	"os/signal"
	"scheduler/common" // 基础组件
	"scheduler/worker" // 调度逻辑
	"syscall"
	"time"
)

func runScheduler(startServices, startAuthorization func()) {
	// 初始化日志
	common.InitLogger()

	// 初始化缓存助手 (依赖 Config 中的 DSN)
	common.InitCacheHelper()

	// 获取文件锁 & PID (防止重复启动)
	worker.TryLockAndWritePid()

	// 初始化 Redis 和 Database
	common.InitRedis()
	common.InitDB()

	// ==========================================
	// 2. 初始化 Worker 池
	// ==========================================
	if common.Cfg.Server.WorkerNum <= 0 {
		common.Cfg.Server.WorkerNum = 20
	}
	worker.WorkerPool = make(chan struct{}, common.Cfg.Server.WorkerNum)

	// 启动 Redis 事件监听 (异步)
	go worker.MonitorEvents()

	// ==========================================
	// 3. 启动版本对应的服务
	// ==========================================
	startServices()

	// 启动后台原生日志消费者
	go worker.StartAdminLogConsumer()
	go worker.StartUserLogConsumer()
	go worker.StartYiiLogConsumer()

	// ==========================================
	// 4. 启动 Cron 热重载监控 & License 检查
	// ==========================================
	worker.CheckAndReload()
	go worker.StartConfigWatcher(10 * time.Second)

	// 🟢 清掉被重启中断的残留升级状态(防后台永久卡"下载中")
	worker.ResetStaleUpgrade()

	if startAuthorization != nil {
		startAuthorization()
	}

	// ==========================================
	// 5. 优雅退出控制
	// ==========================================
	ctx, cancel := context.WithCancel(context.Background())
	quit := make(chan os.Signal, 1)
	// 监听 Ctrl+C (SIGINT) 和 kill (SIGTERM)
	signal.Notify(quit, os.Interrupt, syscall.SIGTERM)

	go func() {
		sig := <-quit
		common.Logger.Printf("🛑 接收到信号: %v，准备优雅退出...", sig)

		// 停止 Cron 调度器
		if worker.CronRunner != nil {
			worker.CronRunner.Stop()
		}

		// 通知主循环退出
		cancel()
	}()

	common.Logger.Printf("🚀 调度器已启动 (PID: %d)", os.Getpid())

	// 启动 Reaper：回收 processing 队列里认领超时的任务
	go worker.StartReaper(ctx)

	// 启动审计流水清理：定期删除过期的任务执行日志
	go worker.StartLogCleaner(ctx)

	// ==========================================
	// 6. 运行主循环 (阻塞在此，直到 ctx 被 cancel)
	// ==========================================
	worker.RunScheduler(ctx)

	// ==========================================
	// 7. 退出收尾
	// ==========================================
	common.Logger.Println("⏳ 等待所有正在执行的任务完成...")
	worker.Wg.Wait() // 等待所有 Worker 归还 token
	common.Logger.Println("👋 退出完成")
}
