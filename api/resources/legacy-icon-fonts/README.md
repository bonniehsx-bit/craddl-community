# 旧图标组件的服务端字体

这里只迁移字体二进制，不改变存量 `uni-icons`、`tui-icon` 的图标名称、颜色、尺寸和点击事件。

| 字体 | 来源 | 字节数 | SHA256 前缀 |
| --- | --- | ---: | --- |
| uniicons | 当前 `uni_modules/uni-icons` 1.3.5 的 `uniicons.ttf` 原文 | 35,760 | 89ed7d6d732e |
| thoruiFont | 当前 `components/thorui/tui-icon` 的 WOFF Base64 原样解码 | 26,032 | 40b12d37175c |

`common/icon/font` 只按这两个白名单名称和版本读取资源，返回对应 MIME、CORS、ETag、一年期缓存；不读取商城或用户数据。字体变化必须增加版本，不覆盖旧内容。

H5/微信小程序由 App 注册全局字体加载入口，首次使用相应旧组件时才下载，并发使用复用一个请求。无旧图标调用时不主动下载。失败不阻塞页面，自动重试一次；后续使用或切回应用可再次请求。首访和离线时仍受字体网络可用性影响，不能当作内置离线字体。

App、NVUE 及其他小程序保留原本地字体分支；原组件的字体源码未删除，以保证这些平台的既有行为。H5/微信的发行包不再包含这两份字体数据，轻量的名称映射 CSS 和组件逻辑继续保留。

字体按各自的上游许可分发。uni-icons 的上游为 DCloud uni-ui，该字体与 DCloud uni-ui 公开提交 b2672313 中的字体逐字节一致。对应快照的 Apache-2.0 许可见 `UNI-UI-LICENSE`。ThorUI WOFF 与公开版 `tui-icon.vue` 内嵌的字体逐字节一致，MIT 许可及原作者署名见 `THORUI-LICENSE`。 [DCloud uni-ui](https://github.com/dcloudio/uni-ui) · [ThorUI 公开版图标源码](https://github.com/dingyong0214/ThorUI-uniapp/blob/d3fb0efa8e76408c791a00fe27003a69a468d768/components/thorui/tui-icon/tui-icon.vue)
