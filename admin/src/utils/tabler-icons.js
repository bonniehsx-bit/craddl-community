// 装修字段保持 URL 字符串；图标尺寸由所在控件的 CSS 控制。
export function parseTablerAsset(value) {
  if (typeof value !== 'string' || !/^https?:\/\//i.test(value)) return null
  try {
    const url = new URL(value)
    if (url.searchParams.get('r') !== 'common/icon/svg') return null
    const name = url.searchParams.get('name') || ''
    const color = url.searchParams.get('color') || ''
    const variant = url.searchParams.get('variant') || 'outline'
    const scale = Number(url.searchParams.get('scale') || 100)
    if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(name) || name.length > 100) return null
    if (!/^#[a-f0-9]{3}(?:[a-f0-9]{3})?$/i.test(color)) return null
    if (!['outline', 'filled'].includes(variant) || scale < 50 || scale > 100 || scale % 5 !== 0) return null
    return { name, color, variant, scale }
  } catch (e) {
    return null
  }
}

export function tablerPreviewUrl(base, version, name, color, variant, scale) {
  if (!base || !name) return ''
  return base + '&' + Object.entries({ v: version, name, color, variant, scale })
    .map(([key, value]) => key + '=' + encodeURIComponent(value)).join('&')
}
