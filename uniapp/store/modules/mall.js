import http from "@/utils/request";

let fetchPromise = null;
let alertPromise = null; // 用于防并发的弹窗请求 Promise

const state = {
	// [新增] 1. 初始化主题色
	// 优先读取本地缓存，如果没有则使用默认值 #F7931E (与你 scss 里的 $uni-color-primary 一致)
	themeColor: uni.getStorageSync('theme_color') || '#F7931E',

	setting: uni.getStorageSync('MALL_SETTING') || null,
	tabbar: uni.getStorageSync('TABBAR') || null,
	checking_versions: uni.getStorageSync('CHECKING_VERSIONS') || null,
};

const mutations = {
	// [新增] 2. 设置主题色并持久化到本地 Storage
	SET_THEME_COLOR(state, color) {
		state.themeColor = color;
		uni.setStorageSync('theme_color', color);
	},

	SET_MALL_CONFIG(state, payload) {
		state.setting = payload.setting || null;
		state.tabbar = payload.tabbar || null;
		state.checking_versions = payload.checking_versions || null;

		uni.setStorageSync('MALL_SETTING', state.setting);
		uni.setStorageSync('TABBAR', state.tabbar);
		uni.setStorageSync('CHECKING_VERSIONS', state.checking_versions);
	},
	SET_TABBAR(state, tabbarPayload) {
		state.tabbar = tabbarPayload || null;
		uni.setStorageSync('TABBAR', state.tabbar);
	},
	// [修复] 移除这个导致闪烁的 mutation
	CLEAR_MALL_SETTING(state) {
		uni.clearStorageSync('MALL_SETTING');
	}
};

const actions = {
	// [新增] 3. 独立设置 APP 主题的 Action (含原生 API 调用)
	// 这个方法既修改数据，也修改原生界面
	setAppTheme({ commit }, color) {
		// A. 更新 State 和 Storage
		commit('SET_THEME_COLOR', color);
		// C. 更新原生 TabBar 选中态颜色 (如果需要的话)
		// 注意：这只改变 TabBar 的图标和文字选中色
		uni.setTabBarStyle({
			selectedColor: color,
			fail: (err) => console.log('setTabBarStyle failed', err)
		});
	},

	/**
	 * [已修复] 获取商城配置，支持强制刷新
	 * @param {boolean} force - 如果为 true，则忽略缓存，强制从网络获取
	 */
	fetchMallConfig({
		commit,
		state,
		dispatch // [新增] 需要解构出 dispatch 以调用 setAppTheme
	}, force = false) {

		// 1. [修复] 强制刷新不再清空数据
		// if (force) {
		// 	commit('CLEAR_MALL_CONFIG'); // <--- 已移除
		// }

		// 2. 如果不是强制刷新，且数据存在，且没有正在进行的请求，则返回缓存
		if (!force && state.setting && !fetchPromise) {
			console.log("Vuex [mall.js]: 配置已存在，直接返回 (非强制)。");
			return Promise.resolve(state);
		}

		// 3. 如果有一个请求正在进行中，则直接返回这个进行中的请求
		//    (这可以防止 onLaunch 和 onShow 同时触发两次请求)
		if (fetchPromise) {
			console.log("Vuex [mall.js]: 配置正在请求中，返回已存在的 Promise。");
			return fetchPromise;
		}

		// 4. 执行到这里，意味着：
		//    - A. 是强制刷新 (force = true)
		//    - B. 或者缓存不存在 (state.setting = null)
		//    此时，我们必须发起网络请求
		console.log(`Vuex [mall.js] Action: ${force ? '强制' : '首次'}请求商城配置...`);

		fetchPromise = new Promise(async (resolve, reject) => {
			try {
				const res = await http.request({
					url: '/api/mall/setting'
				});

				if (res.code === 0 && res.data) {
					console.log("Vuex [mall.js]: 成功获取商城配置。");

					// [新增] 4. 检查后台是否返回了主题色，如果有则立即应用
					// 假设后台返回结构为 setting.theme_color，请根据实际接口调整字段名
					if (res.data.setting && res.data.setting.theme_color) {
						console.log("Vuex [mall.js]: 检测到动态主题色，正在应用:", res.data.setting.theme_color);
						dispatch('setAppTheme', res.data.setting.theme_color);
					}

					commit('SET_MALL_CONFIG', res.data);

					// [修复] Bug B: 成功后必须重置 promise，否则下次无法刷新
					fetchPromise = null;
					resolve(res.data);
				} else {
					console.error("Vuex [mall.js] Error:", res.msg || '未知错误');
					fetchPromise = null; // 失败时重置
					reject(new Error(res.msg));
				}
			} catch (error) {
				console.error("Vuex [mall.js] Exception:", error);
				fetchPromise = null; // 异常时重置
				reject(error);
			}
		});
		return fetchPromise;
	},

	/**
	 * 获取全局弹窗数据
	 * 利用 Promise 防重发机制，解决各页面 tui-page 并发挂载时的竞态问题
	 */
	fetchAlertAdv({ commit }) {
		if (alertPromise) {
			return alertPromise;
		}

		alertPromise = new Promise(async (resolve, reject) => {
			try {
				const res = await http.request({
					url: '/api/mall/alert-adv'
				});

				if (res.code === 0 && res.data && res.data.alert_data) {
					const alertData = res.data.alert_data;
					if (alertData.status == 1) {
						uni.setStorageSync('ALERT_DATA', alertData);
					} else {
						uni.removeStorageSync('ALERT_DATA');
						uni.removeStorageSync('ALERT_LAUNCH_SHOW');
						uni.removeStorageSync('ALERT_DATE');
					}
					resolve(alertData);
				} else {
					resolve(null);
				}
			} catch (error) {
				console.error("获取弹窗配置失败:", error);
				resolve(null);
			} finally {
				alertPromise = null;
			}
		});

		return alertPromise;
	}
};

export default {
	namespaced: true,
	state,
	mutations,
	actions,
};