/**
 * 首页主题注册中心
 *
 * 类似后端 GoodsListener 的事件挂载模式：
 *   - 主项目 pages/index/index.vue 不感知具体主题
 *   - 每个插件在 plugins1/{plugin}/home/HomeTheme.vue 独立维护自己的首页主题
 *   - 本文件用 webpack require.context 自动扫描 plugins1/ 下的所有 home/HomeTheme.vue
 *   - 后台返回 home_theme 字符串 → 前端从 HOME_THEMES 取对应组件
 *
 * 微信小程序 WXML 限制：模板里仍需用 v-if 显式列举各主题分支
 * （component :is 不支持），主项目 index.vue 末尾的注册区 2 行解决。
 *
 * 新增插件主题流程：
 *   1. 在 plugins1/{plugin}/home/ 加 HomeTheme.vue（必须接受 :pageData / :pageParams 两个 props）
 *   2. 在主项目 index.vue 末尾的"主题分发区"加 2 行 import + v-else-if 分支
 *   3. 后台 mall.home_theme = '{plugin}' 即可生效
 *
 * 主题模板规范（写主题时遵守）：
 *   - 只通过 props 接收数据，不要自发请求
 *   - 不操作 uni.setStorageSync('INDEX_DATA') 等壳层缓存
 *   - 不要监听 onLoad / onShow（component 里无效），需要的事件让壳层 emit
 *   - 内部弹窗自己管理；横切关注点（隐私、网络、订阅）由壳层统一处理
 */

import DiyTheme from './DiyTheme.vue'

// 默认主题（永远存在，回退兜底）
export const DEFAULT_THEME = 'diy'

// 主题注册表 —— 通过插件目录约定自动扫描
//
// 注意：
//   require.context 在编译时扫描，因此**新增插件主题文件后必须重启 dev server**
//   生产打包时所有命中的主题都会被打入主包（小程序限制下无法运行时远程加载）
const themeContext = require.context(
    '@/plugins1',                          // 扫描根目录
    true,                                  // 递归子目录
    /\/home\/HomeTheme\.vue$/              // 约定文件路径：plugins1/{plugin}/home/HomeTheme.vue
)

const HOME_THEMES = { [DEFAULT_THEME]: DiyTheme }

themeContext.keys().forEach((key) => {
    // key 形如：'./santa/home/HomeTheme.vue'
    const match = key.match(/^\.\/([^/]+)\//)
    if (!match) return
    const themeName = match[1]
    if (themeName === 'home' || themeName === 'common') return // 保留字
    HOME_THEMES[themeName] = themeContext(key).default
})

export { HOME_THEMES, DiyTheme }

/**
 * 已注册主题码 —— 主项目 index.vue 末尾的 v-if 分支必须**至少**包含这些主题
 * 缺一个就会运行时回退到 DiyTheme（不会报错，但定制丢失）
 */
export const KNOWN_THEMES = Object.keys(HOME_THEMES)

/**
 * 取主题组件，未注册时回退到默认
 */
export function resolveTheme(themeCode) {
    if (themeCode && HOME_THEMES[themeCode]) {
        return HOME_THEMES[themeCode]
    }
    return DiyTheme
}
