# Craddl 社区版第三方资源声明

社区版根目录的 CPAL-1.0 适用于由佛山今日鼎盛信息科技有限公司许可的 Craddl 自有代码。下列文件及随包的第三方依赖继续适用原许可证；仓库中的本地修改不改变上游文件已有的许可和署名。

| 资源及范围 | 上游证据与许可证 |
| --- | --- |
| Noto Sans SC Regular、Bold（`api/web/statics/fonts/`） | [Noto CJK Sans 2.004](https://github.com/notofonts/noto-cjk/tree/Sans2.004/Sans/SubsetOTF/SC) 原始文件。Copyright © 2014–2021 Adobe. Noto is a trademark of Google Inc. SIL OFL 1.1，全文见同目录 `OFL.txt`。 |
| ThorUI 的公开组件、历史样式、工具及其内嵌和独立图标字体（包括后台图标选择器） | [ThorUI 公开版本](https://github.com/dingyong0214/ThorUI-uniapp/tree/d3fb0efa8e76408c791a00fe27003a69a468d768)，MIT。声明见 `uniapp/static/licenses/THORUI-LICENSE.txt` 和 `api/resources/legacy-icon-fonts/THORUI-LICENSE`。Craddl 的业务扩展仍按自有代码许可。 |
| DCloud uni-ui（`uniapp/uni_modules/uni-*`）及保留的本地和服务端 uni-icons 字体 | 字体与 [公开快照 b2672313](https://github.com/dcloudio/uni-ui/tree/b2672313f9bf7d72bb5c4a3821dc408d31e9d864/uni_modules/uni-icons) 的字体一致，该历史快照为 Apache-2.0。许可证见 `api/resources/legacy-icon-fonts/UNI-UI-LICENSE`。 |
| DCloud UniApp WebView 桥（`api/web/js/uni.webview.1.5.2.js`） | 与 [DCloud 官方脚本](https://github.com/dcloudio/uni-app/blob/uni-app-vue2-dev/dist/uni.webview.1.5.2.js) 仅差末尾换行，Apache-2.0，见 `third-party-licenses/DCLOUD-UNI-APP-APACHE-2.0.txt`。 |
| Tabler Icons、uCharts | 各自的许可随资源保留：`api/resources/tabler/LICENSE`、`uniapp/uni_modules/qiun-data-charts/license.md`。 |
| yk-authpup（App 权限用途说明） | [发布者月上柳梢的 DCloud 插件页面](https://ext.dcloud.net.cn/plugin?id=15739) 明示 MIT；署名和正文见 `third-party-licenses/YK-AUTHPUP-MIT.txt`。 |
| wxParse 实体处理工具（`uniapp/components/feng-parse/libs/wxDiscode.js`，有本地修改） | [icindy/wxParse](https://github.com/icindy/wxParse) 的 MIT 声明见 `third-party-licenses/WXPARSE-MIT.txt`。 |
| js-base64 2.4.9（`uniapp/jssdk/base64.min.js`） | 与 [npm 上游发布](https://www.npmjs.com/package/js-base64/v/2.4.9) 逐字节一致，BSD-3-Clause，见 `third-party-licenses/JS-BASE64-2.4.9-BSD-3-CLAUSE.txt`。 |
| Vue 2.6.14、Muse UI 3.0.2（`api/web/js`、`api/web/css` 的公众号辅助页静态资源） | Vue 与 [npm 发布](https://www.npmjs.com/package/vue/v/2.6.14)仅有空白差异；Muse UI 和 [npm 发布](https://www.npmjs.com/package/muse-ui/v/3.0.2)仅有末尾换行差异，[上游署名](https://github.com/museui/muse-ui#licence)。MIT 声明见 `third-party-licenses/`。 |
| Yii2 应用模板、Vue 后台模板 | 取自主仓初始提交 `aa7fe3d39` 随附许可：`third-party-licenses/YII2-TEMPLATE-BSD-3-CLAUSE.txt`、`third-party-licenses/VUE-ADMIN-TEMPLATE-MIT.txt`；商户后台模板另见 `mch/LICENSE`。 |

微信辅助页面从腾讯官方地址加载 WeChat JS SDK 1.4.0，源码包不包含其二进制或副本。其使用遵循腾讯的条款。

源码发行物不包含 Composer、npm 或 Go 下载的依赖树，也不包含 HBuilderX 原生运行时；构建部署时按相应清单安装依赖。分发构建产物时应保留对应依赖的许可和声明。
