// 统一扫码入口:小程序 / App / 微信 H5 三端自动分流,业务页不再各自写 #ifdef。
//
// 为什么必须统一:uni.scanCode **在微信 H5 不被支持**,直接调它的页面在微信里点了毫无反应。
// 曾经 card(3处)、hardware(2处)就是这么坏掉的,而 mch 因为走了本模块所以正常。
// 现在本模块是**唯一**的扫码入口,permissionTip.js 里那个旧的 scanCodeWithTip 已迁到这里,
// 用哪个都能正确分流,杜绝"用错工具"。
//
// 推荐用法(Promise):
//     const code = await scanQR()
//   失败:reject({ msg, cancel }) —— cancel=true 表示用户主动取消,调用方应静默不提示。
//
// 兼容用法(回调,老代码用):
//     scanCodeWithTip({ success(res){ res.result }, fail(err){} })

// #ifdef H5
import wechatJsSdk from '@/utils/wechatJsSdk.js'
// #endif
import { permissionTip } from '@/utils/permissionTip.js'

/**
 * 扫码,返回 Promise<string>(已 trim)。
 * @param {Object} options
 *   - scanType  Array  默认 ['qrCode'];微信 H5 下透传给 wx.scanQRCode(支持 qrCode/barCode)
 *   - onlyFromCamera Boolean 仅原生端有效
 */
export function scanQR(options = {}) {
	const scanType = (Array.isArray(options.scanType) && options.scanType.length) ? options.scanType : ['qrCode'];

	// #ifdef H5
	// 微信 H5:公众号 JS-SDK。非微信环境 wechatJsSdk 内部会 reject 并提示手动输入。
	return wechatJsSdk.scanQRCode(scanType).then((code) => (code || '').trim()).catch((e) => {
		const msg = (e && e.msg) || '';
		return Promise.reject({ msg: msg || '扫码不可用,请手动输入', cancel: /cancel|取消/.test(msg) });
	});
	// #endif

	// #ifndef H5
	// App 端扫码需相机权限,先经 permissionTip 说明用途(华为审核 7.18),用户确认后再扫;
	// 小程序端 permissionTip 直接放行,行为不变。
	return new Promise((resolve, reject) => {
		permissionTip('camera').then(() => {
			uni.scanCode({
				scanType: scanType,
				onlyFromCamera: !!options.onlyFromCamera,
				success: (res) => {
					// 各端返回字段不一致,依次兜底
					const code = (res && (res.result || res.path || res.rawData) || '').trim();
					if (code) resolve(code);
					else reject({ msg: '未识别到二维码', cancel: false });
				},
				fail: (err) => {
					const isCancel = !!(err && /cancel/.test(err.errMsg || ''));
					reject({ msg: isCancel ? '已取消' : '扫码失败,请重试', cancel: isCancel });
				}
			});
		}).catch(() => {
			reject({ msg: '已取消', cancel: true });
		});
	});
	// #endif
}

/**
 * 回调风格的兼容包装(等价于 uni.scanCode 的用法),内部同样走 scanQR 的三端分流。
 * 老代码从 permissionTip.js 迁过来后无需改写法;新代码建议直接用 scanQR()。
 */
export function scanCodeWithTip(options = {}) {
	return scanQR(options).then((code) => {
		if (typeof options.success === 'function') {
			// 保持与 uni.scanCode 一致的入参形状
			options.success({ result: code, errMsg: 'scanCode:ok' });
		}
		return code;
	}).catch((e) => {
		if (typeof options.fail === 'function') {
			options.fail({ errMsg: (e && e.cancel) ? 'scanCode:fail cancel' : ('scanCode:fail ' + ((e && e.msg) || '')) });
		}
		return Promise.reject(e);
	}).finally(() => {
		if (typeof options.complete === 'function') {
			options.complete({});
		}
	});
}

/**
 * 全局扫码(main.js 已挂到 Vue.prototype.$scan 与 uni.$scan,**无需 import**)。
 *
 * 两种写法都支持,按习惯挑一种:
 *   ① Promise(推荐,代码最短)
 *        const code = await this.$scan()
 *        // 失败:catch 到 { msg, cancel };cancel=true 是用户主动取消,通常静默
 *
 *   ② 回调(与原生 uni.scanCode 写法一致,老代码迁过来零改动)
 *        this.$scan({
 *            scanType: ['qrCode'],
 *            success: (res) => { res.result },
 *            fail: (err) => { err.errMsg }
 *        })
 *
 * 传了 success/fail 就走回调,同时**仍然返回 Promise**,两种风格可混用。
 * 三端差异(小程序/App 原生、微信 H5 走公众号 JS-SDK、相机权限说明、各端返回字段)
 * 全部封在内部,页面不需要再写任何 #ifdef。
 */
export function scan(options = {}) {
	const hasCallback = typeof options.success === 'function'
		|| typeof options.fail === 'function'
		|| typeof options.complete === 'function';

	if (hasCallback) {
		// 回调风格下把 reject 吞掉:调用方已在 fail 里处理,
		// 再抛出去会变成没人接的 unhandled rejection。
		return scanCodeWithTip(options).catch(() => {});
	}
	return scanQR(options);
}

export default { scan, scanQR, scanCodeWithTip };
