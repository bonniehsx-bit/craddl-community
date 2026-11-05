# Craddl 社区版 v3 安装与手动升级

源码包含 API、平台后台、商户后台、UniApp 和社区调度器。`edition.json` 记录源码版本和随包插件。可以直接从源码构建调度器，也可以使用与源码配套的 Linux x86-64 附件。

## 准备环境

- PHP 8.2 CLI 和 PHP-FPM，启用 PDO MySQL、BCMath、cURL、mbstring、XML、ZIP、Imagick 等所需扩展。安装 Composer 2，执行 `composer check-platform-reqs` 确认扩展齐全。
- MySQL 8.4 和 Redis 8.0 是本地验收使用的版本。新装使用空数据库及独立 Redis 实例或逻辑库。API 和调度器必须连接同一个数据库和 Redis 逻辑库。
- 平台和商户后台从源码构建需要 Node.js 和 npm。H5 和 App 用 HBuilderX 构建。先配置自己的域名、API 地址和应用标识。

从 GitHub 克隆仓库或解压源码后，在项目根目录使用 Go 1.25 构建调度器。

```sh
cd scheduler
go build -tags=community -mod=readonly -trimpath -o ../api/scheduler .
cd ..
```

如果使用同时提供源码与预编译调度器的发行附件，在交付目录先核对完整性，再解压。以下示例在 Linux 使用 `sha256sum`，macOS 可使用 `shasum -a 256 -c SHA256SUMS`。

```sh
sha256sum -c SHA256SUMS
tar -xzf craddl-community-v3-source.tar.gz
tar -xzf craddl-scheduler-v3-linux-amd64.tar.gz -C craddl-community-v3/api
cd craddl-community-v3
chmod +x api/scheduler
sha256sum api/scheduler
```

最后一条命令的结果应与 `edition.json` 的 `scheduler.sha256` 一致。

## 新装

1. 在 `api` 目录执行 `composer install --prefer-dist --no-interaction`。按 `composer.lock` 安装完整依赖。当前运行时使用的 `yiisoft/yii2-redis` 列在开发依赖中，因此安装时需要包含开发依赖。安装完成后执行 `composer check-platform-reqs`。
2. 复制 `api/config/config.tpl.php` 为 `api/config/config.php`，替换占位符并填入部署环境的 MySQL、Redis 和所需业务配置。新建字符集为 `utf8mb4` 的空库。使用生产环境设置 `YII_ENV=prod`、`YII_DEBUG=false`。站点的公开根目录指向 `api/web`，将 API 请求交给 `index.php`，并给 PHP 运行用户提供 `api/runtime` 等实际运行目录的写权限。
3. 在 `api` 目录执行 `php yii migrate/auto`。它会先处理核心迁移，再扫描随包提供的插件迁移。逐段检查输出，确认核心与六个插件均成功或已经是最新。迁移后在受限网络中完成首次后台登录，并立即更换初始化管理员口令。初始账号由 `m991231_092209_adminDataInsert` 迁移建立。
4. 分别在 `admin` 和 `mch` 目录安装依赖并执行 `npm run build:prod`，产物分别进入 `api/web/admin` 和 `api/web/mch`。在 `uniapp` 目录运行 `npm install` 后，再用 HBuilderX 导入该目录。按部署路径发行 H5 并将产物放到对应的 Web 静态目录。App 使用自己的应用标识和签名，使用高德地图或定位时填入自己的 SDK Key。
5. 登录平台后台，通过应用管理安装 `article`、`clerk`、`distribution`、`marketing`、`mch` 和 `withdraw`。创建或编辑商城，勾选六个插件的授权，再进入该商城核对应用入口。充值赠券使用 `marketing` 提供的平台优惠券。
6. 复制 `api/scheduler.tpl.yaml` 为 `api/scheduler.yaml`，填入同一 MySQL 和 Redis 配置，令 `yii.entry_file` 指向实际的 `api/web/index.php`，FastCGI 地址指向 PHP-FPM。可参考 `api/supervisor.tpl.ini` 托管 `api/scheduler --config api/scheduler.yaml`。社区版调度器不连接商业授权服务，升级时也不要复制旧配置的 `license:` 段。在 `api` 执行 `php yii crontab/sync` 后启动调度器，再核对 `/health`、心跳和任务执行日志。

### 移动端配置

- H5 发行前，在 `uniapp/config.json` 配置自己的 API 根地址。本地开发时，`manifest.json` 的 `/api` 和 `/statics` 代理默认指向 `127.0.0.1:1024`，按本机后端地址修改。
- 首次导入后，在 HBuilderX 的 `manifest.json` 基础配置中获取自己的 DCloud AppID，再构建 H5 或 App。构建微信小程序时另填自己的小程序 AppID。使用 App 微信支付或分享时，填入自己的微信开放平台应用 AppID，并按平台要求配置 iOS Universal Links。
- 使用 App 高德地图、定位或 H5 腾讯地图时，填写对应平台自己的 SDK Key。

## 从社区版 v2 手动升级到 v3

1. 进入维护窗口，暂停新的业务写入，等待正在执行的任务结束，再停止旧调度器。备份旧源码、调度器、部署配置、持久化文件、MySQL 和任务队列所在的 Redis。记录升级前的队列和任务状态。
2. 将 v3 源码解压到新目录并安装依赖。将旧实例的配置和持久化文件迁入新目录，保留新包的 `api/community.json`，安装与该包匹配的 v3 调度器并复核校验值。
3. 在新目录执行 `php yii migrate/auto` 和 `php yii crontab/sync`，逐段检查迁移和任务同步结果。将站点根目录及调度器托管配置切换到 v3，再启动服务。保留原 Redis 队列供 v3 继续处理。
4. 开放写入前检查后台和 H5、插件授权、已有订单和优惠券状态，以及调度器心跳与执行日志。社区版后台和 CLI 会提示手动升级。

需要回滚时，先暂停写入并停止 v3 调度器。按升级前的同一恢复点恢复源码、调度器、部署配置及受升级影响的 MySQL、Redis 和持久化文件。若升级后已有新增业务记录，先保留当前快照并核对差异，再决定恢复范围，避免覆盖已确认的订单或资金记录。

第三方文件的许可见 `THIRD-PARTY-NOTICES.md`，预编译调度器附件的许可见 `api/scheduler-licenses/`。
