// 文件路径: src/store/index.js
import Vue from 'vue'
import Vuex from 'vuex'

Vue.use(Vuex); // vue的插件机制

// 引入所有模块
import app from "@/store/modules/app"
import mall from "@/store/modules/mall"
import user from "@/store/modules/user"
import mch from "@/store/modules/mch"
import hardware from "@/store/modules/hardware"

// Vuex.Store 构造器选项
const store = new Vuex.Store({
	modules: {
		mall,
		app,
		user,
		mch,
		hardware
	}
})
export default store