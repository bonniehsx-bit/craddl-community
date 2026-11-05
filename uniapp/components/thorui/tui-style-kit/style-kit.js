/**
 * 风格组件(tui-hero-card / tui-cat-grid / tui-promo-card …)手机端共用的小工具。
 * 和后台 admin/src/components/diy/style-kit/presets.js 同一套口径,改一边记得同步另一边。
 */

/** 衬线 / 无衬线标题字体。衬线 iPhone 上是宋体,多数安卓没有中文衬线字体,会退回黑体 */
export const FONT_FAMILY = {
	serif: '"Songti SC", "STSong", "Noto Serif SC", "Source Han Serif SC", serif',
	sans: '"PingFang SC", "Microsoft YaHei", sans-serif'
};

/** 距某天(YYYY-MM-DD,当天 23:59:59 截止)还有几天;过了返回 -1,格式不对返回 null */
export function daysLeft(date) {
	if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) return null;
	const parts = date.split('-').map(Number);
	const end = new Date(parts[0], parts[1] - 1, parts[2], 23, 59, 59).getTime();
	const now = Date.now();
	if (end < now) return -1;
	return Math.floor((end - now) / 86400000);
}

/** 文字按换行拆成数组(小程序 view 里不认 \n,逐行渲染) */
export function lines(text, max) {
	const arr = String(text || '').split('\n').map(s => s.trim()).filter(Boolean);
	return max ? arr.slice(0, max) : arr;
}

/** 装修里的跳转:空链接不动;运营选了 tabbar 页时 navigateTo 会失败,依次兜到 switchTab / reLaunch */
export function goLink(url) {
	if (!url) return;
	uni.navigateTo({
		url,
		fail: () => uni.switchTab({
			url: url.split('?')[0],
			fail: () => uni.reLaunch({ url })
		})
	});
}

/**
 * 沉浸式页面(没有原生标题栏、内容从屏幕最顶上开始)顶部的尺寸,和 tui-page 自绘导航栏同一套算法。单位 px。
 *   status:状态栏高度;bar:胶囊那一行的高度;right:右侧胶囊占的宽度(含边距);back:左侧返回按钮占的宽度
 * 只有首页 index / home(无返回按钮)和自定义页 diy(有返回按钮)是沉浸式;其它页面、以及 H5(浏览器自带顶栏)返回 null。
 */
export function immersiveTop() {
	// #ifdef H5
	return null;
	// #endif
	// #ifndef H5
	const pages = getCurrentPages();
	const route = pages.length ? pages[pages.length - 1].route : '';
	const NO_BACK = ['pages/index/index'];
	const WITH_BACK = ['pages/diy/diy'];
	if (NO_BACK.indexOf(route) < 0 && WITH_BACK.indexOf(route) < 0) return null;
	let status = 20;
	let bar = 44;
	let right = 0;
	let width = 375;
	try {
		const si = uni.getSystemInfoSync();
		if (si.statusBarHeight) status = si.statusBarHeight;
		if (si.windowWidth) width = si.windowWidth;
	} catch (e) {}
	// #ifdef MP-WEIXIN || MP-QQ || MP-BAIDU || MP-TOUTIAO
	try {
		const m = uni.getMenuButtonBoundingClientRect();
		if (m && m.top) {
			let margin = m.top - status;
			if (margin < 0) margin = 4;
			bar = margin * 2 + m.height;
			if (bar < 32) bar = 44;
			right = width - m.left;
		}
	} catch (e) {}
	// #endif
	// 返回按钮:tui-page 里左边 16px + 32px 圆按钮,再留 8px 间隙
	return { status, bar, right, back: WITH_BACK.indexOf(route) >= 0 ? 56 : 0 };
	// #endif
}

/**
 * 同一颜色的全透明版本(#rrggbb → rgba(r,g,b,0))。做「主色 → 透明」淡入时用它,
 * 直接写 transparent 会从主色往黑色过渡,中间发灰。认不出的颜色退回 transparent。
 */
export function clearOf(color) {
	const m = String(color || '').trim().match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
	if (!m) return 'transparent';
	const h = m[1].length === 3 ? m[1].replace(/./g, x => x + x) : m[1];
	return 'rgba(' + parseInt(h.slice(0, 2), 16) + ',' + parseInt(h.slice(2, 4), 16) + ',' + parseInt(h.slice(4, 6), 16) + ',0)';
}
