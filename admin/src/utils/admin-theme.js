// 后台个人配色，不与商城前台 theme_color 共用配置。
export const ADMIN_THEMES = [
  { value: 'craddl', label: 'Craddl 默认', color: '#191B20', accent: '#F7931E' },
  { value: 'element', label: 'Element 蓝', color: '#409EFF', accent: '#409EFF' },
  { value: 'custom', label: '自定义', color: '#6959CD', accent: '#6959CD' }
]

export function normalizeColor(value) {
  return typeof value === 'string' && /^#[\da-f]{6}$/i.test(value) ? value.toUpperCase() : null
}

export function resolveAdminTheme(setting = {}) {
  const color = normalizeColor(setting.theme)
  // 旧版只有 theme 字段；保留已保存的个人颜色，不强制切换品牌方案。
  const mode = ADMIN_THEMES.some(item => item.value === setting.themeMode)
    ? setting.themeMode : (color ? (color === '#409EFF' ? 'element' : 'custom') : 'craddl')
  return {
    themeMode: mode,
    theme: mode === 'craddl' ? '#191B20' : mode === 'element' ? '#409EFF' : (color || '#409EFF')
  }
}

export function readLayoutSetting() {
  try {
    const value = JSON.parse(localStorage.getItem('layout-setting'))
    return value && typeof value === 'object' && !Array.isArray(value) ? value : {}
  } catch (error) {
    // 损坏或不可用的本地缓存不能阻断系统启动。
    return {}
  }
}

export function mixColor(color, target, amount) {
  const values = [1, 3, 5].map(index => {
    const from = parseInt(color.slice(index, index + 2), 16)
    const to = parseInt(target.slice(index, index + 2), 16)
    return Math.round(from + (to - from) * amount).toString(16).padStart(2, '0')
  })
  return '#' + values.join('')
}

function luminance(color) {
  return [1, 3, 5].reduce((sum, index, channel) => {
    const value = parseInt(color.slice(index, index + 2), 16) / 255
    const linear = value <= 0.04045 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4)
    return sum + linear * [0.2126, 0.7152, 0.0722][channel]
  }, 0)
}

export function contrastColor(color) {
  const light = luminance(color)
  return (light + 0.05) / 0.05 > 1.05 / (light + 0.05) ? '#000000' : '#FFFFFF'
}

// 深色菜单上的深色自定义主色需提亮，避免文字消失。
export function menuActiveColor(setting, dark = true) {
  const { themeMode, theme } = resolveAdminTheme(setting)
  if (themeMode === 'craddl') return dark ? '#F7931E' : '#A8540A'
  if (!dark) return theme
  let color = theme
  for (let i = 0; i < 10 && (luminance(color) + 0.05) / (luminance('#191B20') + 0.05) < 4.5; i++) {
    color = mixColor(color, '#FFFFFF', 0.15)
  }
  return color
}

export function themeCluster(color) {
  return [color, ...Array.from({ length: 9 }, (_, i) => mixColor(color, '#FFFFFF', (i + 1) / 10)), mixColor(color, '#000000', 0.1)]
}

// 品牌页面与挂到 body 的弹窗共用派生色，避免每页另写固定蓝色/橙色。
export function adminSurfaceTokens(setting) {
  const { themeMode, theme } = resolveAdminTheme(setting)
  const branded = themeMode === 'craddl'
  const accent = branded ? '#F7931E' : theme
  const soft = branded ? '#FFF2E2' : mixColor(accent, '#FFFFFF', 0.92)
  let text = branded ? '#A8540A' : accent
  for (let i = 0; themeMode !== 'element' && i < 30 && (luminance(soft) + 0.05) / (luminance(text) + 0.05) < 4.5; i++) {
    text = mixColor(text, '#000000', 0.1)
  }
  const darkAccent = menuActiveColor(setting)
  const rgb = value => [1, 3, 5].map(index => parseInt(value.slice(index, index + 2), 16)).join(', ')
  return {
    '--admin-accent-text': text,
    '--admin-accent-soft': soft,
    '--admin-accent-border': mixColor(accent, '#FFFFFF', 0.7),
    '--admin-accent-rgb': rgb(accent),
    '--admin-canvas': branded ? '#F7F6F2' : mixColor(accent, '#FFFFFF', 0.97),
    '--admin-decoration': mixColor(accent, '#FFFFFF', 0.88),
    '--admin-dark-accent': darkAccent,
    '--admin-dark-accent-rgb': rgb(darkAccent)
  }
}

// 动画和字体包含嵌套块，必须整块排除；逐层匹配会留下空 keyframes 覆盖原动画。
function withoutThemeAtRules(css) {
  const tokens = /\/\*[\s\S]*?\*\/|"(?:\\[\s\S]|[^"\\])*"|'(?:\\[\s\S]|[^'\\])*'|(@(?:-[\w]+-)?keyframes\b|@font-face\b)|[{}]/gi
  let result = ''
  let start = 0
  let excluded = -1
  let depth = 0
  let match
  while ((match = tokens.exec(css))) {
    if (excluded === -1) {
      if (match[1]) excluded = match.index
    } else if (match[0] === '{') {
      depth++
    } else if (match[0] === '}' && --depth === 0) {
      result += css.slice(start, excluded)
      start = tokens.lastIndex
      excluded = -1
    }
  }
  return result + css.slice(start)
}

export function recolorTheme(css, color) {
  const oldColors = themeCluster('#1890FF')
  const colors = themeCluster(color)
  const lookup = Object.fromEntries(oldColors.map((value, index) => [value.toLowerCase(), colors[index]]))
  const originalPattern = new RegExp(oldColors.join('|'), 'i')
  // 只覆盖包含主色色阶的声明，不重新覆盖组件尺寸、字体和业务状态色。
  const colorRules = withoutThemeAtRules(css)
    .replace(/:export\s*\{[^}]*\}/g, '')
    .replace(/([^{}]+)\{([^{}]*)\}/g, (rule, selector, body) => {
      const declarations = body.split(';').filter(value => originalPattern.test(value))
      return declarations.length ? `${selector}{${declarations.join(';')};}` : ''
    })
  // 单次替换，防止新颜色再次命中旧颜色而产生串色；不扫描/改写业务页面样式。
  return colorRules.replace(/#[\da-f]{6}\b/ig, value => lookup[value.toLowerCase()] || value)
}
