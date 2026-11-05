// store/modules/hardware.js
// 母婴硬件插件 - 当前宝宝状态管理
// 替代各页面散落的 babyId 参数传递 + storage 读写

const STORAGE_ID    = 'CURRENT_BABY_ID';
const STORAGE_NAME  = 'CURRENT_BABY_NAME';
// 插件主题色。缓存起来是为了避免每次进页面先闪一下商城主题色再切成插件色
const STORAGE_THEME = 'HW_THEME_COLOR';

const state = {
	// 进入页面前优先读 storage,避免初次渲染闪烁
	currentBabyId:   Number(uni.getStorageSync(STORAGE_ID))   || 0,
	currentBabyName: uni.getStorageSync(STORAGE_NAME) || '',
	currentBaby:     null,  // 完整宝宝对象,按需懒加载
	// 育儿插件自己的主题色。'' = 跟随商城主题色。
	// 育儿板块有自己的调性,而商城主题色是给主商城定的 —— 混在一起最扎眼的
	// 是快捷入口那排图标:结构色跟着商城走,图标却是另一套色。
	themeColor:      uni.getStorageSync(STORAGE_THEME) || '',
};

const getters = {
	currentBabyId:   state => state.currentBabyId,
	currentBabyName: state => state.currentBabyName,
	currentBaby:     state => state.currentBaby,
	hasBaby:         state => !!state.currentBabyId,
	themeColor:      state => state.themeColor,
};

const mutations = {
	// 设置完整宝宝(含 baby 对象,通常在 baby/list 或 baby-profile/detail 后调用)
	SET_CURRENT_BABY(state, baby) {
		state.currentBaby     = baby || null;
		state.currentBabyId   = baby ? Number(baby.id) : 0;
		state.currentBabyName = baby ? (baby.name || '') : '';
		if (state.currentBabyId) {
			uni.setStorageSync(STORAGE_ID, state.currentBabyId);
			uni.setStorageSync(STORAGE_NAME, state.currentBabyName);
		} else {
			uni.removeStorageSync(STORAGE_ID);
			uni.removeStorageSync(STORAGE_NAME);
		}
	},
	// 仅快速切换 ID(完整对象需后续 fetch)
	SET_CURRENT_BABY_ID(state, payload) {
		const id   = Number(payload?.id) || 0;
		const name = payload?.name || '';
		state.currentBabyId   = id;
		state.currentBabyName = name;
		state.currentBaby     = null;
		if (id) {
			uni.setStorageSync(STORAGE_ID, id);
			uni.setStorageSync(STORAGE_NAME, name);
		}
	},
	SET_THEME_COLOR(state, color) {
		const c = String(color || '').trim();
		// 只认 #RGB / #RRGGBB —— 脏值注进 CSS 变量会让整页样式失效,
		// 宁可当没配、回落商城主题色
		state.themeColor = /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(c) ? c : '';
		if (state.themeColor) {
			uni.setStorageSync(STORAGE_THEME, state.themeColor);
		} else {
			uni.removeStorageSync(STORAGE_THEME);
		}
	},
	CLEAR_CURRENT_BABY(state) {
		state.currentBabyId   = 0;
		state.currentBabyName = '';
		state.currentBaby     = null;
		uni.removeStorageSync(STORAGE_ID);
		uni.removeStorageSync(STORAGE_NAME);
	},
};

const actions = {
	setCurrentBaby({ commit }, baby) { commit('SET_CURRENT_BABY', baby); },
	setCurrentBabyId({ commit }, payload) { commit('SET_CURRENT_BABY_ID', payload); },
	clearCurrentBaby({ commit }) { commit('CLEAR_CURRENT_BABY'); },
	setThemeColor({ commit }, color) { commit('SET_THEME_COLOR', color); },
};

export default {
	namespaced: true,
	state,
	getters,
	mutations,
	actions,
};
