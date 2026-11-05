// 敏感权限申请前的「用途说明」助手 —— 同时满足两端应用市场审核:
//   · 华为 7.18:申请敏感权限前须同步、显式告知权限用途(名称+功能+目的),且说明不可自动消失;
//   · iOS 5.1.1(iv):说明弹窗不得带「取消」让用户跳过/延后权限,展示后须「直接弹系统权限、不允许跳过」。
// 故 App 端先弹一个「只有『继续』、不自动消失」的说明弹窗,用户点「继续」后由调用方直接触发系统权限
// (用户真正的拒绝权在随后的系统权限弹窗里行使);非 App 端(小程序/H5)无此审核约束,直接放行。

const TIPS = {
	album: {
		title: '相册权限使用说明',
		content: '用于选择本地图片并上传（如设置头像、上传凭证或图片等），仅在你主动选择图片时使用，不会用于其他用途。'
	},
	camera: {
		title: '相机权限使用说明',
		content: '用于拍照或扫码，仅在你主动使用相关功能时调用，不会用于其他用途。'
	},
	location: {
		title: '位置权限使用说明',
		content: '用于获取你的位置以推荐附近门店并按距离排序，仅在你主动使用相关功能时获取，不会用于其他用途。'
	},
	saveAlbum: {
		title: '存储权限使用说明',
		content: '用于将图片/海报保存到你的相册，仅在你主动点击保存时调用，不会用于其他用途。'
	},
	record: {
		title: '麦克风权限使用说明',
		content: '用于录制语音，仅在你主动使用录音功能时调用，不会用于其他用途。'
	},
	pickLocation: {
		title: '位置权限使用说明',
		content: '用于在地图上选择或定位地址（如收货地址、门店位置等），仅在你主动选择地址时使用，不会用于其他用途。'
	}
};

// 各权限对应的动作词,用于把通用说明拼成贴合当前场景的句子(见 permissionTip 的 purpose 参数)
const VERBS = {
	album: '从相册选择图片',
	camera: '拍照',
	location: '获取你的位置',
	saveAlbum: '保存图片到相册',
	record: '录制语音',
	pickLocation: '在地图上选择位置'
};
const SUFFIX = '，仅在你主动使用该功能时调用，不会用于其他用途。';

/** purpose 是本模块自用的,不要原样透给原生 API */
function omitPurpose(options) {
	const o = Object.assign({}, options);
	delete o.purpose;
	return o;
}

/**
 * 敏感权限申请前先向用户说明用途。
 * @param {string} scope album | camera | location | saveAlbum | record | pickLocation
 * @param {string} [purpose] 当前场景做什么用,如 '更换头像'。传了就把说明拼成
 *        「用于拍照以更换头像，仅在你主动使用该功能时调用…」这种贴合场景的句子。
 *        审核方会看说明与当前功能是否对应,通用文案("用于拍照或扫码")在具体场景下容易被挑,
 *        所以**调用方能说清用途时就传上**。
 *        ⚠ purpose 只写「目的」、别带动词 —— 模板是「用于{动作}以{purpose}」,
 *        传 '保存海报图片' 会拼出"用于保存图片到相册以保存海报图片"这种病句。
 *        本身动作已说清的场景(如保存到相册)直接用默认文案即可,不必传。
 * @returns {Promise<void>} 始终 resolve、不会 reject:App 端展示说明弹窗、用户点「继续」后 resolve
 *          (弹窗只有「继续」、不可跳过,符合 iOS 5.1.1(iv));非 App 端直接 resolve。
 *          调用方保留 .catch() 无妨(现不会触发),用途说明后应直接触发系统权限。
 *
 * 用法:
 *   permissionTip('album', '上传营业执照').then(() => { uni.chooseImage({...}) }).catch(() => {})
 */
export function permissionTip(scope, purpose = '') {
	return new Promise((resolve) => {
		// #ifdef APP-PLUS
		const base = TIPS[scope] || TIPS.album;
		const verb = VERBS[scope] || '';
		const t = (purpose && verb)
			? { title: base.title, content: '用于' + verb + '以' + purpose + SUFFIX }
			: base;
		// 说明弹窗只保留「继续」、不给「取消」——两端审核同时满足:
		//   · 华为 7.18:申请敏感权限前须显式、不自动消失地告知用途 → 单按钮说明弹窗即满足;
		//   · iOS 5.1.1(iv):说明弹窗不得让用户「取消」跳过/延后权限,展示后须「直接弹系统权限、不允许跳过」。
		// 用户真正的「拒绝权」在随后的系统权限弹窗里行使,此处只做用途告知、不再提供跳过入口。
		uni.showModal({
			title: t.title,
			content: t.content,
			showCancel: false,
			confirmText: '继续',
			// 无论点「继续」还是弹窗异常,都放行到系统权限(由系统弹窗把决定权交给用户),不因说明环节卡死功能
			success: () => resolve(),
			fail: () => resolve()
		});
		return;
		// #endif
		// #ifndef APP-PLUS
		resolve();
		// #endif
	});
}

/**
 * 带「用途说明」的定位:等价于 uni.getLocation(options),但 App 端会先弹权限用途说明,
 * 用户确认后再获取定位。完整透传 options 及其 success/fail/complete 回调,
 * 用户拒绝说明时回调原 fail,保持调用方的 Promise/流程语义不变。
 * 用法:把 `uni.getLocation(opt)` 直接替换为 `getLocationWithTip(opt)` 即可。
 * @param {object} options 与 uni.getLocation 完全一致的参数对象
 */
export function getLocationWithTip(options = {}) {
	permissionTip('location', options.purpose || '').then(() => {
		uni.getLocation(omitPurpose(options));
	}).catch(() => {
		if (typeof options.fail === 'function') {
			options.fail({ errMsg: 'getLocation:fail permission tip canceled' });
		}
		if (typeof options.complete === 'function') {
			options.complete({ errMsg: 'getLocation:fail permission tip canceled' });
		}
	});
}

/**
 * 带「用途说明」的地图选点:等价于 uni.chooseLocation(options),App 端先弹说明再打开地图选点。
 * 完整透传 options 及回调,用户拒绝说明时回调原 fail,保持调用方流程语义。
 * 用法:把 `uni.chooseLocation(opt)` 替换为 `chooseLocationWithTip(opt)`。
 * @param {object} options 与 uni.chooseLocation 完全一致的参数对象
 */
export function chooseLocationWithTip(options = {}) {
	permissionTip('pickLocation', options.purpose || '').then(() => {
		uni.chooseLocation(omitPurpose(options));
	}).catch(() => {
		if (typeof options.fail === 'function') {
			options.fail({ errMsg: 'chooseLocation:fail permission tip canceled' });
		}
		if (typeof options.complete === 'function') {
			options.complete({ errMsg: 'chooseLocation:fail permission tip canceled' });
		}
	});
}

/**
 * 带「用途说明」的选图:等价 uni.chooseImage(options),App 端在申请权限前说明用途。
 *
 * ⚠ 说明必须与**实际会申请的那个权限**对应,这是华为审核 7.18 的判定点。
 * uni.chooseImage 不传 sourceType 时默认 ['album','camera'] —— 用户点「拍摄」申请的是
 * 相机权限,此时若只弹了"相册权限说明",就属于「未同步告知权限申请的使用目的」。
 * 原实现固定弹 album,正是被驳回的原因(测试步骤:换头像 → 点拍摄 → 申请相机权限)。
 *
 * 因此当来源同时包含相册与相机时,先让用户自己选来源,再弹对应说明:
 *   选「拍照」   → 相机权限说明 → chooseImage({sourceType:['camera']})
 *   选「从相册」 → 相册权限说明 → chooseImage({sourceType:['album']})
 * 这样弹出的说明和随后申请的权限一一对应,功能也不必退化成"只能从相册选"。
 *
 * @param {object} options 与 uni.chooseImage 一致;额外支持 options.purpose ——
 *        当前场景用途(如 '更换头像'),会拼进权限说明让它贴合场景。
 */
export function chooseImageWithTip(options = {}) {
	const fail = (msg) => {
		if (typeof options.fail === 'function') {
			options.fail({ errMsg: 'chooseImage:fail ' + msg });
		}
		if (typeof options.complete === 'function') {
			options.complete({ errMsg: 'chooseImage:fail ' + msg });
		}
	};
	// 按来源弹对应说明后取图
	const go = (scope, sourceType) => {
		permissionTip(scope, options.purpose || '').then(() => {
			uni.chooseImage(Object.assign(omitPurpose(options), { sourceType: sourceType }));
		}).catch(() => fail('permission tip canceled'));
	};

	const src = Array.isArray(options.sourceType) && options.sourceType.length
		? options.sourceType
		: ['album', 'camera'];          // 与 uni.chooseImage 的默认值保持一致
	const hasAlbum = src.indexOf('album') !== -1;
	const hasCamera = src.indexOf('camera') !== -1;

	if (hasCamera && !hasAlbum) {
		return go('camera', ['camera']);
	}
	if (hasAlbum && !hasCamera) {
		return go('album', ['album']);
	}

	// 两者都可能 —— 非 App 端交给系统自带的来源选择(不涉及权限说明合规);
	// App 端必须先确定来源,否则无法给出与权限对应的说明。
	// #ifndef APP-PLUS
	uni.chooseImage(omitPurpose(options));   // purpose 是本模块自用的,别透给原生 API
	return;
	// #endif

	// #ifdef APP-PLUS
	uni.showActionSheet({
		itemList: ['拍照', '从相册选择'],
		success: (r) => {
			if (r.tapIndex === 0) {
				go('camera', ['camera']);
			} else {
				go('album', ['album']);
			}
		},
		fail: () => fail('source select canceled')
	});
	// #endif
}

/**
 * 带「用途说明」的保存到相册:等价 uni.saveImageToPhotosAlbum(options),App 端先弹存储权限说明。
 * @param {object} options 与 uni.saveImageToPhotosAlbum 一致
 */
export function saveImageWithTip(options = {}) {
	permissionTip('saveAlbum', options.purpose || '').then(() => {
		uni.saveImageToPhotosAlbum(omitPurpose(options));
	}).catch(() => {
		if (typeof options.fail === 'function') {
			options.fail({ errMsg: 'saveImageToPhotosAlbum:fail permission tip canceled' });
		}
		if (typeof options.complete === 'function') {
			options.complete({ errMsg: 'saveImageToPhotosAlbum:fail permission tip canceled' });
		}
	});
}

/**
 * 带「用途说明」的扫码:等价 uni.scanCode(options),App 端先弹相机权限说明。
 * @param {object} options 与 uni.scanCode 一致
 */
// scanCodeWithTip 已迁至 utils/scanCode.js。
//
// 原因:这里的旧实现无条件调用 uni.scanCode,而 **uni.scanCode 在微信 H5 不被支持**,
// 导致 card / hardware 等页面在微信里点扫码毫无反应。扫码必须按端分流,
// 而分流逻辑依赖 wechatJsSdk(H5),放在本文件会与 scanCode.js 形成循环引用。
// 现在扫码只有一个入口:
//     import { scanQR } from '@/utils/scanCode.js'          // 推荐,Promise
//     import { scanCodeWithTip } from '@/utils/scanCode.js'  // 回调风格兼容


export default permissionTip;
