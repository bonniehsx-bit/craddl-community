import http from '@/utils/request.js';

const state = {
	// 当前操作的店铺对象
	currentMch: uni.getStorageSync('current_mch') || null,
	// 用户可管理的店铺列表
	mchList: uni.getStorageSync('mch_list') || []
};

const mutations = {
	// 设置当前店铺
	SET_CURRENT_MCH(state, mch) {
		state.currentMch = mch;
		uni.setStorageSync('current_mch', mch);
		// [重要] 同步更新缓存key 'current_mch_id' 给 request.js 使用
		if (mch && mch.id) {
			uni.setStorageSync('current_mch_id', mch.id);
		} else {
			uni.removeStorageSync('current_mch_id');
		}
	},
	// 设置店铺列表
	SET_MCH_LIST(state, list) {
		state.mchList = list;
		uni.setStorageSync('mch_list', list);
	},
	// 退出登录或清理缓存
	CLEAR_MCH_INFO(state) {
		state.currentMch = null;
		state.mchList = [];
		uni.removeStorageSync('current_mch');
		uni.removeStorageSync('mch_list');
		uni.removeStorageSync('current_mch_id'); // 别忘了清除这个
	}
};

const actions = {
	/**
	 * 初始化商家信息 (核心动作)
	 * @param {Object} context
	 * @param {boolean} force - [核心修改] 新增 force 参数，默认为 false
	 */
	async initMchInfo({
		commit,
		state
	}, force = false) { // [核心修改] 接收 force 参数

		// [核心修改] 只有在不强制刷新，且缓存存在时，才直接返回
		// [多商户] 内存 currentMch 会在切商城/登出后残留(那两处清的是 storage),
		// 因此早退必须同时要求 storage 的 current_mch_id 仍在——它由 SET_CURRENT_MCH 写、
		// 被 CLEAR_MCH_INFO(登出) 和 setMallId(切商城) 清,天然作为上下文有效性判据。
		const hasStorageCtx = !!uni.getStorageSync('current_mch_id');
		if (state.currentMch && state.currentMch.id && hasStorageCtx && !force) {
			return Promise.resolve(state.currentMch);
		}

		try {
			const res = await http.request({
				url: '/plugin/mch/admin/mch/init'
			});
			if (res.code == -1) {
				uni.showModal({
					title: '提示',
					content: res.msg,
					confirmText: '去登录',
					cancelText: '先逛逛',
					success: (e) => {
						if (e.confirm) {
							uni.redirectTo({
								url: '/others/login/login'
							})
							return;
						}
						uni.navigateBack()
					}
				})
				return;
			}

			if (res.code === 0) {
				commit('SET_MCH_LIST', res.data.mch_list);
				// 切换时优先保持当前店铺 id，但 **必须用新接口返回的对象** —— 否则 localStorage
				// 里残留的旧 currentMch 没有 type 等新字段，导致 center.vue 显示错误类型/店铺ID
				const currentId = state.currentMch && state.currentMch.id;
				const matched = currentId ? res.data.mch_list.find(mch => mch.id === currentId) : null;
				if (matched) {
					commit('SET_CURRENT_MCH', matched);
					return Promise.resolve(matched);
				} else {
					commit('SET_CURRENT_MCH', res.data.default_mch);
					return Promise.resolve(res.data.default_mch);
				}
			} else {
				const mchStatus = res.data ? res.data.mch_status : null;

				if (mchStatus === 0) {
					// 待审核：只告知状态，不引导去申请
					uni.showModal({
						title: '审核中',
						content: '您的入驻申请正在审核中，请耐心等待平台审核。',
						showCancel: false,
						confirmText: '我知道了',
						success: () => uni.navigateBack()
					});
				} else if (mchStatus === 2) {
					// 已驳回：提示原因 + 引导重新申请
					uni.showModal({
						title: '申请被驳回',
						content: res.msg,
						confirmText: '重新申请',
						cancelText: '先逛逛',
						success: (e) => {
							if (e.confirm) {
								uni.redirectTo({ url: '/plugins1/mch/apply' });
							} else {
								uni.navigateBack();
							}
						}
					});
				} else {
					// 未申请
					uni.showModal({
						title: '提示',
						content: res.msg,
						confirmText: '现在申请',
						cancelText: '先逛逛',
						success: (e) => {
							if (e.confirm) {
								uni.redirectTo({ url: '/plugins1/mch/apply' });
							} else {
								uni.navigateBack();
							}
						}
					});
				}
				return Promise.reject(res);
			}
		} catch (error) {
			console.log(error);
			return Promise.reject(error);
		}
	},

	/**
	 * 切换店铺
	 */
	switchMch({
		commit,
		state
	}, mch) {
		// 切店只需更新上下文(currentMch + storage current_mch_id),后续请求靠 X-Mch-Id 头自动生效;
		// 页面刷新由调用方处理(如 center.vue onMchSelect → initialize(true)),无需事件广播。
		if (mch && mch.id !== (state.currentMch && state.currentMch.id)) {
			commit('SET_CURRENT_MCH', mch);
		}
	}
};
export default {
	namespaced: true,
	state,
	mutations,
	actions
};