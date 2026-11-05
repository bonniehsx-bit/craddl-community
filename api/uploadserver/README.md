# 小程序代码上传（miniprogram-ci CLI）

后台「上传代码」由 PHP 端 `app\forms\mall\platform\MpwxForm::upload()` 直接 `exec()` 调用官方
`miniprogram-ci` 命令行完成，**无需常驻服务、无端口**（旧的 8899 hapi 服务已废弃删除）。

## 部署（一次性）

服务器需有 Node 环境，然后在本目录安装依赖：

```bash
cd api/uploadserver
npm install
```

安装后 CLI 位于 `api/uploadserver/node_modules/.bin/miniprogram-ci`，PHP 默认就找这个路径。

## .env 可选配置

PHP-FPM 运行用户的 PATH 通常没有 node，若默认找不到，请在 `api/.env` 配置全路径：

```
# node 可执行文件路径（默认 node，找不到时填全路径，如 /usr/local/bin/node）
MINIPROGRAM_CI_NODE=/usr/local/bin/node
# miniprogram-ci CLI 路径（默认 api/uploadserver/node_modules/.bin/miniprogram-ci）
MINIPROGRAM_CI_BIN=
```

## 上传流程（PHP 自动完成）

1. 复制 `api/web/mp-weixin` 标准包 → `api/web/mini_path/{appid}`
2. 替换 `common/vendor.js` 占位符：`__JX_MALL_ID__` → 当前 mall_id，`__JX_API_ROOT__` → 后端域名
3. 覆写 `project.config.json` 的 appid，写入上传私钥
4. `exec` 调 `miniprogram-ci upload`，成功后到微信公众平台提交审核

> 标准包必须由 uni-app 用带占位符的 `config.json` / `siteinfo.js` 编译产出，否则上传会因缺占位符被拦截。
