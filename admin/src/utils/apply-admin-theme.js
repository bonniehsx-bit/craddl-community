// 复用项目自身编译后的 Element 主题，避免运行时依赖外网 CSS。
import themeSource from '!!raw-loader!sass-loader!@/styles/element-variables.scss'
import { resolveAdminTheme, recolorTheme, contrastColor, adminSurfaceTokens, mixColor } from './admin-theme'

export function applyAdminTheme(setting) {
  const { themeMode, theme } = resolveAdminTheme(setting)
  const root = document.documentElement
  root.dataset.adminTheme = themeMode
  root.style.setProperty('--admin-primary', theme)
  const foreground = themeMode === 'custom' ? contrastColor(theme) : '#FFFFFF'
  root.style.setProperty('--admin-on-primary', foreground)
  root.style.setProperty('--admin-primary-hover', mixColor(theme, '#FFFFFF', 0.2))
  root.style.setProperty('--admin-primary-active', mixColor(theme, '#000000', 0.1))
  root.style.setProperty('--admin-accent', themeMode === 'craddl' ? '#F7931E' : theme)
  const tokens = adminSurfaceTokens(setting)
  Object.keys(tokens).forEach(key => root.style.setProperty(key, tokens[key]))
  let style = document.getElementById('admin-theme-style')
  if (!style) {
    style = document.createElement('style')
    style.id = 'admin-theme-style'
    document.head.appendChild(style)
  }
  style.textContent = recolorTheme(themeSource, theme)
}
