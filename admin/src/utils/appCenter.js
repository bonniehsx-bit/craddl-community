/**
 * 应用中心公共逻辑:顶栏应用面板(AppLauncher)和应用中心页共用。
 *
 * 应用列表来自 /mall/plugin/index —— 后端已按「操作员角色能看到的菜单」过滤,
 * route_path 是侧边栏同一套算法算出的第一个可见页面;has_entry=false 表示一个可进入的页面都没有。
 */
import request from '@/utils/request'
import store from '@/store'
import { getMallId } from '@/utils/auth'

const RECENT_MAX = 8
let cache = null // { mallId, at, groups }
let pending = null

/** 拉应用分组;同一商城 60 秒内复用,收藏变化时调 invalidateApps() */
export function fetchApps(force = false) {
  const mallId = String(getMallId() || '')
  if (!force && cache && cache.mallId === mallId && Date.now() - cache.at < 60000) {
    return Promise.resolve(cache.groups)
  }
  if (pending) return pending
  pending = request({ url: '/mall/plugin/index', method: 'get' }).then(res => {
    const groups = res.code === 0 ? ((res.data && res.data.list) || []) : []
    cache = { mallId, at: Date.now(), groups }
    return groups
  }).finally(() => {
    pending = null
  })
  return pending
}

export function invalidateApps() {
  cache = null
}

/** 分组打平成应用数组(带上分组名) */
export function flattenApps(groups) {
  const out = []
  ;(groups || []).forEach(g => (g.list || []).forEach(a => out.push({ ...a, group_name: g.group_name })))
  return out
}

/** 应用图标占位色与当前后台主题一致，图片和客户 Logo 不受影响。 */
export function colorOf() {
  return 'var(--admin-accent-text)'
}

/** 当前路由属于哪个应用:插件页面统一挂在 /plugins/{name}/ 下 */
export function appOfPath(apps, path) {
  if (!path) return null
  return (apps || []).find(a => path === a.route_path || path.indexOf('/plugins/' + a.name + '/') === 0) || null
}

// ---- 最近使用:只存在本机浏览器,按商城 + 账号区分 ----
function recentKey() {
  return 'jx_app_recent_' + (getMallId() || '0') + '_' + ((store.state.user && store.state.user.name) || '')
}

export function getRecent() {
  try {
    const v = JSON.parse(localStorage.getItem(recentKey()) || '[]')
    return Array.isArray(v) ? v : []
  } catch (e) {
    return []
  }
}

export function recordRecent(name) {
  if (!name) return
  try {
    const list = [name, ...getRecent().filter(n => n !== name)].slice(0, RECENT_MAX)
    localStorage.setItem(recentKey(), JSON.stringify(list))
  } catch (e) {
    // 隐私模式等写不进去,不影响使用
  }
}
