# Tabler 图标资源

- 来源：[@tabler/icons 3.50.0](https://www.npmjs.com/package/@tabler/icons/v/3.50.0)，上游仓库 [tabler/tabler-icons](https://github.com/tabler/tabler-icons/tree/v3.50.0)。
- 上游发布包 SHA256：`4c2eb678ed71cdcbf57a3e899c4e91f3445d680fbc1d57d27c7083a321c47303`。
- `3.50.0/icons.zip`：官方 SVG 原文，目录为 `outline/{name}.svg`、`filled/{name}.svg`；5,203 枚线性、1,054 枚实心，共 6,257 枚。仅重新归档，没有改写图形。
- `3.50.0/catalog.json`：从官方 `icons.json` 派生的名称、类别、搜索标签；分类补充中文名称。只在 API 服务端使用，不打入 UniApp。
- 第三方资源适用同目录 `LICENSE` 中的 MIT 许可，保留原作者声明；不适用系统自有代码许可。

资源保存在 Web 根目录之外，由 `common/icon/svg` 公开读取。PHP 依赖项目 Composer 已要求的 `ext-zip`。发布 API 时需要带上整个资源目录，不能因通用 ZIP 排除规则漏掉 `icons.zip`。本目录的 `.gitignore` 已为官方资源包单独放行。

升级需同时更新服务端及 UniApp 的版本声明，并兼容已发布客户端仍使用的旧版本。不要在同一个版本下替换 SVG 原文，避免一年期缓存中的图标不一致。
