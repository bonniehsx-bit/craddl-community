# Craddl 社区调度器

社区调度器执行订单队列、定时任务和后台日志落库。运行只需要连接同一套 Craddl API、PHP-FPM、MySQL 和 Redis，安装时无需激活。

在导出的 `scheduler` 目录使用 Go 1.25 构建。

```sh
go build -tags=community -mod=readonly -trimpath -o ../api/scheduler .
```

用 `api/scheduler.tpl.yaml` 生成 `api/scheduler.yaml`，填入本机服务地址、实际 API 入口及日志目录。沿用原实例的 Redis 队列键和逻辑库。在 API 目录运行 `php yii crontab/sync` 后，通过 `./scheduler --config scheduler.yaml` 启动。通过 `/health` 和后台任务日志检查运行状态。

从 v3 旧调度器切换时，先暂停旧调度器并等当前任务结束，再启动新调度器。保留 Redis 中等待执行的任务和原数据库。社区版使用发布包手动升级。
