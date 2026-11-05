// store/modules/user.js

// 【关键修改】直接引入 request 工具，因为在 JS 文件中无法通过 this.$request 访问
import http from '@/utils/request.js';

const state = {
	// 1. 优先从本地缓存读取，实现“记住登录状态”
	token: uni.getStorageSync('ACCESS_TOKEN') || '',
	// 2. 缓存用户信息，避免每次打开APP都有一瞬间是未登录样式
	userInfo: uni.getStorageSync('USER_INFO') || {},
};

const getters = {
	// 【核心】页面中判断是否登录，统一使用 this.isLogin
	// 注意：这里使用 getters 而不是直接用 state，更安全
	isLogin: (state) => {
		// 确保 token 存在且不为空字符串
		return !!state.token;
	},
	// 安全获取用户信息，防止 userInfo 为 null 时报错
	userInfo: (state) => {
		return state.userInfo || {};
	},
	token: (state) => {
		return state.token;
	}
};

const mutations = {
	// 设置 Token 并持久化
	SET_TOKEN(state, token) {
		state.token = token;
		uni.setStorageSync('ACCESS_TOKEN', token);
	},
	// 设置用户信息 并持久化
	SET_USER_INFO(state, info) {
		state.userInfo = info;
		uni.setStorageSync('USER_INFO', info);
	},
	// 【清理状态】登出或 Token 失效时调用
	CLEAR_LOGIN_STATE(state) {
		state.token = '';
		state.userInfo = {};
		// 清除所有相关缓存
		uni.removeStorageSync('ACCESS_TOKEN');
		uni.removeStorageSync('USER_INFO');
		// 可选：清除个人中心页面的配置缓存
		uni.removeStorageSync('USER_CENTER_SETTING');
		console.log('[Vuex] 用户登录状态已清除');
	}
};

const actions = {
	/**
	 * 【登录成功动作】
	 * 登录页调用此方法，而不是简单的 setToken
	 * 它会先保存 Token，然后立即去服务器拉取最新的用户信息
	 */
	loginSuccess({ commit, dispatch }, { token }) {
		return new Promise((resolve, reject) => {
			// 1. 存 Token
			commit('SET_TOKEN', token);

			// 2. 立即拉取用户信息 (返回 Promise 以便登录页等待)
			dispatch('fetchUserInfo')
				.then(user => {
					// 【新增】广播登录成功事件，触发 App.vue 连接 IM
					uni.$emit('LOGIN_SUCCESS', user);
					resolve(user);
				})
				.catch(err => {
					// 即使获取用户信息失败，只要Token是对的，也算登录流程走完了
					console.warn('Token保存成功，但获取用户信息失败:', err);
					resolve();
				});
		});
	},

	/**
	 * 【获取用户信息】
	 * 使用 import 进来的 http.request 进行请求
	 */
	fetchUserInfo({ commit }) {
		return new Promise((resolve, reject) => {
			// 【关键修改】使用头部引入的 http.request
			http.request({
				url: "/api/user/index",
				method: 'GET'
			}).then(res => {
				if (res.code === 0) {
					// 成功：更新 Store 和 缓存
					commit('SET_USER_INFO', res.data.user);
					resolve(res.data.user);
				} else if (res.code === -1) {
					// 失败：Token 过期/无效 -> 自动清理登录态
					commit('CLEAR_LOGIN_STATE');
					// 这里的 reject(res) 很重要，让外层知道获取失败了
					reject(res);
				} else {
					// 其他错误
					reject(res);
				}
			}).catch(err => {
				// 网络错误等
				reject(err);
			});
		});
	},

	/**
	 * 【退出登录】
	 */
	logout({ commit }) {
		commit('CLEAR_LOGIN_STATE');
		// 【多商户】登出(含掉线自动登出 request.js code==-1)必须清商户上下文,
		// 否则 current_mch_id 残留 → 下一个用户的所有请求都带着上一个用户的 X-Mch-Id 串号。
		commit('mch/CLEAR_MCH_INFO', null, { root: true });
		// 【新增】广播登出事件，APP.vue 监听到后断开 IM
		uni.$emit('LOGOUT_SUCCESS');
	},

	/**
	 * 【兼容旧代码】
	 * 即使你通过 dispatch('user/setToken') 调用，也能正常工作
	 */
	setToken({ commit }, token) {
		commit('SET_TOKEN', token);
	}
};

export default {
	namespaced: true, // 必须开启命名空间
	state,
	getters,
	mutations,
	actions,
};