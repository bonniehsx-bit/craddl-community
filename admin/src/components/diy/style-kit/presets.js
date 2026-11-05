/**
 * 风格组件的通用配色预设。
 *
 * 风格组件(主视觉卡 / 卖点条 / 分类宫格 / 区块标题 / 入口卡 / 视频卡 / 联系卡)统一用这一套字段名:
 *   bg_color / bg_color2(渐变终点)/ text_color / sub_color / accent_color / btn_bg / btn_color / glow_color
 * 选预设就是把这几个字段一次填好;运营之后照样能单独改某个颜色。
 * 预设名只描述风格,不绑定任何行业或店铺,所有商城都能用。
 */
export const STYLE_PRESETS = [
  {
    key: 'night', name: '深夜蓝金',
    bg_color: '#0e1a2b', bg_color2: '#16263d', text_color: '#ffffff', sub_color: 'rgba(255,255,255,0.75)',
    accent_color: '#e8b75a', btn_bg: '#c8102e', btn_color: '#ffffff', glow_color: 'rgba(111,227,184,0.35)'
  },
  {
    key: 'festive', name: '节日红',
    bg_color: '#b81d2a', bg_color2: '#7d0f18', text_color: '#ffffff', sub_color: 'rgba(255,255,255,0.85)',
    accent_color: '#ffe08a', btn_bg: '#ffffff', btn_color: '#a3162a', glow_color: 'rgba(255,214,120,0.25)'
  },
  {
    key: 'paper', name: '米白复古',
    bg_color: '#fbf6ec', bg_color2: '#f3ead8', text_color: '#2a2522', sub_color: '#6f655c',
    accent_color: '#c8102e', btn_bg: '#c8102e', btn_color: '#ffffff', glow_color: 'rgba(216,185,138,0.35)'
  },
  {
    key: 'fresh', name: '简约白',
    bg_color: '#ffffff', bg_color2: '#f5f7fa', text_color: '#1f2329', sub_color: '#86909c',
    accent_color: '#ff4a4a', btn_bg: '#ff4a4a', btn_color: '#ffffff', glow_color: 'rgba(0,0,0,0)'
  },
  {
    key: 'forest', name: '森林绿',
    bg_color: '#1f3d2b', bg_color2: '#2d5a3d', text_color: '#ffffff', sub_color: 'rgba(255,255,255,0.78)',
    accent_color: '#f2c94c', btn_bg: '#f2c94c', btn_color: '#1f3d2b', glow_color: 'rgba(242,201,76,0.25)'
  },
  {
    key: 'sakura', name: '樱花粉',
    bg_color: '#fff0f3', bg_color2: '#ffe0e8', text_color: '#3d2a30', sub_color: '#8a6b73',
    accent_color: '#e85a7a', btn_bg: '#e85a7a', btn_color: '#ffffff', glow_color: 'rgba(232,90,122,0.18)'
  }
]

export const COLOR_KEYS = ['bg_color', 'bg_color2', 'text_color', 'sub_color', 'accent_color', 'btn_bg', 'btn_color', 'glow_color']

/** 把预设的颜色写进组件配置(只写组件里本来就有的字段) */
export function applyPreset(form, key) {
  const p = STYLE_PRESETS.find(x => x.key === key)
  if (!p) return form
  const next = Object.assign({}, form, { preset: key })
  COLOR_KEYS.forEach(k => {
    if (k in form) next[k] = p[k]
  })
  return next
}

/** 衬线 / 无衬线标题字体。衬线在 iPhone 上是宋体,安卓多数没有中文衬线字体,会退回黑体 */
export const FONT_FAMILY = {
  serif: '"Songti SC", "STSong", "Noto Serif SC", "Source Han Serif SC", serif',
  sans: '"PingFang SC", "Microsoft YaHei", sans-serif'
}

/** 距某天(YYYY-MM-DD,当天 23:59:59 截止)还有几天;过了返回 -1,格式不对返回 null */
export function daysLeft(date) {
  if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) return null
  const [y, m, d] = date.split('-').map(Number)
  const end = new Date(y, m - 1, d, 23, 59, 59).getTime()
  const now = Date.now()
  if (end < now) return -1
  return Math.floor((end - now) / 86400000)
}

/**
 * 页面背景色预设(装修「页面设置」里用)。以浅色为主,深色只留一个给夜间风格首页;
 * 空串 = 不设,手机端保持默认底色。名称只描述颜色,不绑定行业。
 */
export const PAGE_BG_PRESETS = [
  { name: '默认', color: '' },
  { name: '浅灰', color: '#f5f6f8' },
  { name: '纯白', color: '#ffffff' },
  { name: '米白', color: '#f8f3ea' },
  { name: '浅杏', color: '#fbf0e4' },
  { name: '樱花粉', color: '#fff3f5' },
  { name: '薄荷绿', color: '#eef6f1' },
  { name: '雾蓝', color: '#eef3f9' },
  { name: '深夜蓝', color: '#0e1a2b' }
]

/**
 * 同一颜色的全透明版本(#rrggbb → rgba(r,g,b,0))。做「主色 → 透明」淡入时用它,
 * 直接写 transparent 会从主色往黑色过渡,中间发灰。认不出的颜色退回 transparent。
 */
export function clearOf(color) {
  const m = String(color || '').trim().match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i)
  if (!m) return 'transparent'
  const h = m[1].length === 3 ? m[1].replace(/./g, x => x + x) : m[1]
  return 'rgba(' + parseInt(h.slice(0, 2), 16) + ',' + parseInt(h.slice(2, 4), 16) + ',' + parseInt(h.slice(4, 6), 16) + ',0)'
}
