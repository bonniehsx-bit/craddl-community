import http from '@/utils/request.js';

const versions = { uniicons: '89ed7d6d732e', thoruiFont: '40b12d37175c' };
const states = Object.create(null);
let appFontLoader = null;

// 微信的全局字体必须由 App 上下文注册加载器，组件只按需请求。
export function registerLegacyFontLoader(loader) {
	appFontLoader = loader;
}

export function legacyIconFontUrl(family) {
	if (!versions[family]) return '';
	return http.getApiRoot() + 'common/icon/font&family=' + family + '&v=' + versions[family];
}

export function ensureLegacyIconFont(family) {
	const url = legacyIconFontUrl(family);
	if (!url || !appFontLoader) return Promise.resolve(false);
	let state = states[family];
	if (!state || state.url !== url) state = states[family] = { url, loaded: false, pending: null, attempts: 0 };
	if (state.loaded) return Promise.resolve(true);
	if (state.pending) return state.pending;
	state.attempts++;
	// 字体失败不拦住业务页面。最多自动重试一次，后续页面使用或切回应用可再次请求。
	state.pending = new Promise(resolve => {
		let settled = false;
		const finish = success => {
			if (settled) return;
			settled = true;
			clearTimeout(timer);
			state.loaded = success;
			resolve(success);
			if (!success && state.attempts < 2) {
				setTimeout(() => { if (states[family] === state && !state.loaded) ensureLegacyIconFont(family); }, 1000);
			}
		};
		const timer = setTimeout(() => finish(false), 10000);
		try {
			appFontLoader({ global: true, family, source: 'url("' + url + '")', success: () => finish(true), fail: () => finish(false) });
		} catch (e) { finish(false); }
	}).then(success => { state.pending = null; return success; });
	return state.pending;
}

export function retryUsedLegacyFonts() {
	Object.keys(states).forEach(family => {
		if (!states[family].loaded && !states[family].pending) ensureLegacyIconFont(family);
	});
}
