import http from '@/utils/request.js';

// 与 API 端资源版本对应；整套 SVG 和目录均留在服务端。
export const TABLER_VERSION = '3.50.0';

export function tablerIconUrl(name, color, variant = 'outline') {
	if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(name) || name.length > 100) return '';
	if (!/^#[a-f0-9]{3}(?:[a-f0-9]{3})?$/i.test(color)) return '';
	if (variant !== 'outline' && variant !== 'filled') return '';
	return http.getApiRoot() + 'common/icon/svg&v=' + TABLER_VERSION + '&variant=' + variant +
		'&name=' + encodeURIComponent(name) + '&color=' + encodeURIComponent(color.toLowerCase());
}
