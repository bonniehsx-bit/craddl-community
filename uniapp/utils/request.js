// 文件路径: utils/http.js (假设)

import config from "@/config.json";
import store from '@/store/index.js';
import util from "@/utils/util.js";

// H5 整页跳转(如微信静默授权 window.location.href)会中断在途请求，
// 此时 fail 回调不是真网络错误，不该弹"网络不给力"。监听 beforeunload 打标，请求失败时据此抑制误报。
let _navigating = false;
// 未登录弹窗是否已在屏上。并发请求会同时返回 -1，没这道闸会叠出多个弹窗
let _loginPrompting = false;
// #ifdef H5
if (typeof window !== 'undefined' && window.addEventListener) {
	window.addEventListener('beforeunload', () => { _navigating = true; });
	window.addEventListener('pagehide', () => { _navigating = true; });
}
// #endif

const http = {
	// 标记"即将整页跳转"：跳转前调用，使随后被中断的在途请求不弹"网络不给力"
	markNavigating: function() {
		_navigating = true;
	},

	/**
	 * 未登录引导弹窗。页面收到 code == -1(ApiCode::CODE_NOT_LOGIN)时调用。
	 *
	 * 直接 toast 一句"请先登录"是最差的处理：提示一闪而过，用户既不知道为什么失败，
	 * 也不知道下一步该干嘛 —— 尤其在下单这种走到一半的流程里。这里给两个明确出口。
	 *
	 * ⚠ 登录页成功后走的是 uni.reLaunch(login_before_url || '/pages/index/index')，
	 *   所以跳转前必须把来源页连同查询参数写进去，否则登录完会被丢回首页，
	 *   用户得从头再走一遍下单流程。
	 *
	 * @param {String|Object} options 字符串 = 弹窗正文；或 { content, pageUnusable }
	 *        pageUnusable: 当前页面是否已经不可用(数据没加载出来，是张空白页)。
	 *          传 true  —— 订单预览这类「页面还没渲染出来就被拦」的场景：
	 *                      点「返回」直接退出页面；点「去登录」用 redirectTo 顶掉这张废页，
	 *                      这样用户在登录页放弃返回时，回到的是上一个正常页面，
	 *                      而不是一张永远空白、也不会自己重新加载的结算页。
	 *          不传    —— 页面已完整渲染的场景(如提交失败)：
	 *                      点「返回」留在原地；点「去登录」用 navigateTo 保留页面栈。
	 */
	promptLogin: function(options) {
		if (typeof options === 'string') {
			options = { content: options };
		}
		options = options || {};

		// 一个页面常常并发发多个请求，不加这道闸会一口气叠出好几个弹窗
		if (_loginPrompting) return;

		const pages = getCurrentPages();
		const current = pages.length ? pages[pages.length - 1] : null;
		const route = current ? current.route : '';

		// 已经在登录页还弹，会形成「弹窗 → 登录页 → 弹窗」的死循环
		if (!route || route.indexOf('others/login/') === 0) return;

		_loginPrompting = true;

		// H5 端 options 挂在 $page 上，两处都取一下
		const query = current.options || (current.$page && current.$page.options) || {};
		const qs = Object.keys(query)
			.map(k => k + '=' + encodeURIComponent(query[k]))
			.join('&');
		const backUrl = '/' + route + (qs ? '?' + qs : '');

		uni.showModal({
			title: '还未登录',
			content: options.content || '登录后才能继续操作，现在去登录吗？',
			cancelText: '返回',
			confirmText: '去登录',
			success: function(e) {
				if (e.confirm) {
					// 点了「去登录」才写:点「返回」时写进去就会一直残留在缓存里
					uni.setStorageSync('login_before_url', backUrl);
					// 废页用 redirectTo 顶掉，正常页用 navigateTo 保留栈(见上方注释)
					const go = options.pageUnusable ? uni.redirectTo : uni.navigateTo;
					go({
						url: '/others/login/login'
					});
				} else if (options.pageUnusable) {
					// 页面栈可能只有这一页(扫码/分享直接进来)，navigateBack 会失败，兜底回首页
					uni.navigateBack({
						fail: function() {
							uni.switchTab({
								url: '/pages/index/index'
							});
						}
					});
				}
			},
			complete: function() {
				_loginPrompting = false;
			}
		});
	},
	toast: function(tips) {
		uni.showToast({
			title: tips || "出错啦~",
			icon: 'none',
			duration: 2000
		});
	},
	loading: function(tips) {
		uni.showLoading({
			title: tips || "加载中~",
		});
	},
	hideLoading: function() {
		uni.hideLoading();
	},
	showModal: function(content, title) {
		uni.showModal({
			title: title || '提示',
			content: content,
			showCancel: false, // 默认只显示确认按钮
		});
	},

	/**
	 * [核心优化] 从 Vuex store 获取 API 根路径
	 * 不再依赖 getApp() 或 globalData
	 */
	getApiRoot: function() {
		
		// H5环境特殊处理，区分开发和生产
		// #ifdef H5
		if (process.env.NODE_ENV === 'development') {
			return '/api?r='; // 用于本地开发跨域代理
		} else {
			// 在生产环境中，H5通常与后端部署在同一域名下
			return window.location.protocol + "//" + window.location.host + "/index.php?r=";
		}
		// #endif

		// #ifndef H5
		// 其他平台（小程序、App）从 Vuex 获取配置
		// 使用可选链(?.), 即使 setting 为 null 也不会报错
		const apiroot = store.state.mall.setting?.apiroot;
		if (apiroot) {
			return apiroot + '/index.php?r=';
		} else {
			// 在 Vuex 数据加载完成前提供一个默认/备用地址
			// 或者你可以在这里返回空字符串，并在 request 方法中处理
			console.warn("Vuex 'apiroot'尚未加载, 请求可能失败。");
			return config.apiroot + '/index.php?r='; // 从config.json兜底
		}
		// #endif
	},

	// [优化] 商城ID获取逻辑，去除了错误的 return 1;
	getMallId: function() {
		// 优先级: H5 URL参数 > 缓存 > 小程序配置 > 默认值
		// #ifdef H5
		const urlMallId = http.getUrlParam('mall_id');
		if (urlMallId) {
			http.setMallId(urlMallId);
			return urlMallId;
		}
		// #endif
		const storageMallId = uni.getStorageSync("X-MALL-ID");
		if (storageMallId) {
			return storageMallId;
		}
		// #ifdef MP
		if (config.mall_id) {
			return config.mall_id;
		}
		// #endif
		return 1; // 作为最终的默认值
	},

	setMallId: function(mall_id) {
		// 【多商户】切换商城时清掉上一个商城残留的商户上下文,防止 current_mch_id 跨商城串号
		// (X-Mch-Id 是全局注入的,不清会把 A 商城的店铺带进 B 商城的请求)。
		// 仅在 mall_id 真正变化时清理,避免 H5 每次请求都误清。
		const prev = uni.getStorageSync("X-MALL-ID");
		if (prev && String(prev) !== String(mall_id)) {
			uni.removeStorageSync('current_mch');
			uni.removeStorageSync('mch_list');
			uni.removeStorageSync('current_mch_id');
		}
		return uni.setStorageSync("X-MALL-ID", mall_id);
	},
	getPid: function() {
		return uni.getStorageSync("X-PARENT-ID") || -1;
	},
	setPid: function(pid) {
		return uni.setStorageSync("X-PARENT-ID", pid);
	},

	// [优化] 直接从 Vuex 获取 Token，统一数据源
	getToken() {
		return store.state.user.token || uni.getStorageSync("ACCESS_TOKEN");
	},
	isLogin: function() {
		return !!http.getToken();
	},
	logout: function() {
		store.dispatch('user/logout'); // 推荐通过 action 统一处理登出逻辑
		uni.reLaunch({
			url: '/pages/index/index'
		});
	},

	// 获取当前平台
	getPlatform() {
		let platform = 'WECHAT';
		// #ifdef H5
		platform = util.isWeChat() ? 'WECHAT' : 'H5';
		// #endif
		// #ifdef MP-WEIXIN
		platform = 'MPWX';
		// #endif
		// #ifdef APP-PLUS
		platform = 'APP';
		// #endif
		return platform;
	},


	getUrlParam: function(name) {
		// #ifdef H5
		// [修复1] 定义正则，匹配参数
		const reg = new RegExp('(^|&)' + name + '=([^&]*)(&|$)');

		// [修复2] 优先查找 window.location.search (域名后, #号前)
		// 场景: http://yoursite.com/?auth_code=123#/pages/index
		const searchResult = window.location.search.substr(1).match(reg);
		if (searchResult != null) return decodeURIComponent(searchResult[
		2]); // 使用 decodeURIComponent 替代 unescape

		// [修复3] 如果找不到，再查找 window.location.hash (路由后)
		// 场景: http://yoursite.com/#/pages/index?auth_code=123
		const hash = window.location.hash;
		if (hash.indexOf('?') > -1) {
			const hashSearch = hash.split('?')[1]; // 获取 hash 中 ? 后面的字符串
			const hashResult = hashSearch.match(reg);
			if (hashResult != null) return decodeURIComponent(hashResult[2]);
		}
		// #endif

		return null;
	},

	/**
	 * [核心优化] request 方法不再依赖 getApp()
	 */
	request: function(requestData) {
		const {
			url: reqUrl,
			data: postData,
			method: reqMethod = 'GET', // 默认GET
			showLoading = false
		} = requestData;

		const api_root = http.getApiRoot();
		// 如果api_root为空，可能配置还未加载，可以选择中断或提示
		if (!api_root && !reqUrl.startsWith('http')) {
			http.toast("应用配置加载中，请稍后重试");
			return Promise.reject("API Root not ready");
		}

		const fullUrl = reqUrl.startsWith('http') ? reqUrl : api_root + reqUrl;
		const method = reqMethod.toUpperCase();

		if (showLoading) {
			http.loading('请稍候...');
		}

		const version = uni.getSystemInfoSync().appVersionCode;
		 
		const header = {
			'content-type': 'application/json',
			'x-mall-id': http.getMallId(),
			'x-access-token': http.getToken(),
			'x-parent-id': http.getPid(),
			'x-platform': http.getPlatform(),
			'x-version': version,
			'lat': uni.getStorageSync('lat') || 0,
			'lon': uni.getStorageSync('lon') || 0
		};

		// 注入多商户ID
		const currentMchId = uni.getStorageSync('current_mch_id');
		if (currentMchId) {
			header['X-Mch-Id'] = currentMchId;
		}

		return new Promise((resolve, reject) => {
			uni.request({
				url: fullUrl,
				data: postData,
				header: header,
				method: method,
				dataType: 'json',
				success: (res) => {
					if (res.statusCode >= 400) {
						http.toast(`服务错误(${res.statusCode})`);
						reject(res);
						return;
					}
					// 登录失效/Token无效
					if (res.data.code == -1) {
						// 推荐通过action处理，可以做更多事情（如记录重定向页面）
						store.dispatch('user/logout');
					}
					if (res.data.code == -6) {
						uni.showModal({
							title: '提示',
							content: res.data.msg,
							success: (e) => {
								if (e.confirm) {
									uni.redirectTo({
										url: '/plugins1/mch/apply'
									})
								} else {
									uni.navigateBack();
								}
							}
						})
					}
					resolve(res.data);
				},
				fail: (err) => {
					// 整页跳转中断的请求不是真网络错误，不弹 toast
					if (!_navigating) {
						http.toast("网络不给力，请稍后再试");
					}
					reject(err);
				},
				complete: () => {
					if (showLoading) {
						http.hideLoading();
					}
				}
			});
		});
	},

	// 上传文件，逻辑保持不变，但依赖的方法已优化
	uploadFile: function(requestData) {
		const {
			url,
			file,
			fileKey
		} = requestData;
		const api_root = http.getApiRoot();
		const fullUrl = api_root + url;

		const header = {
			'x-mall-id': http.getMallId(),
			'x-access-token': http.getToken(),
			'x-parent-id': http.getPid(),
			'x-platform': http.getPlatform()
		};

		return new Promise((resolve, reject) => {
			uni.uploadFile({
				url: fullUrl,
				name: fileKey,
				filePath: file,
				header: header,
				success: (res) => {
					if (res.statusCode !== 200) {
						http.toast(`上传失败(${res.statusCode})`);
						return reject(res);
					}
					const data = JSON.parse(res.data);
					if (data.code == -1) {
						store.dispatch('user/logout');
						return reject(data);
					}
					resolve(data);
				},
				fail: (err) => {
					if (!_navigating) {
						http.toast("上传失败，请检查网络");
					}
					reject(err);
				}
			});
		});
	}
};
export const request = http.request;
export const getMallId = http.getMallId;
export const getUrlParam = http.getUrlParam;
export const getApiRoot = http.getApiRoot;
export const uploadFile = http.uploadFile;
export const toast = http.toast;
// 导出模块
export default http;