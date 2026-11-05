import Vue from 'vue'
import App from './App'
import http from './utils/request.js'
import { scan } from './utils/scanCode.js'
// [新增] 规范导入 store
import store from './store' 

// #ifdef H5
import wechatSdk from '@/utils/wechatJsSdk.js';
Vue.prototype.$wechatSdk = wechatSdk;
// #endif

Vue.config.productionTip = false
App.mpType = 'app'

// uview-ui 已移除 —— 替换为 thorui/uni-ui/native components
// 历史 $u 工具方法（addUnit/deepMerge 等）请用 lodash 或自行实现

// --- 【核心修复 START】 ---
// 1. 改用 import 导入 mixin，解决 export default 不生效的问题
// 注意：这会自动寻找 mixins/index.js，如果你文件名是 global.js，请写 '@/mixins/global.js'
import mixin from '@/mixins'; 
Vue.mixin(mixin)
// --- 【核心修复 END】 ---

// 2. 挂载 store
Vue.prototype.$store = store

import util from './utils/util.js'
Vue.prototype.$getScene = util.getScene;
Vue.prototype.$request = http.request;
Vue.prototype.$uploadFile = http.uploadFile;
Vue.prototype.$http = http;
Vue.prototype.$showModal = util.showModal
Vue.prototype.$util = util;

// 全局扫码:页面里直接 `await this.$scan()`,或 `this.$scan({success,fail})`,无需 import。
// 三端差异(小程序/App 原生、微信 H5 走公众号 JS-SDK、相机权限说明)全封在 utils/scanCode.js 内。
// 同时挂到 uni.$scan,方便在非组件上下文(工具函数、store)里调用。
Vue.prototype.$scan = scan;
uni.$scan = scan;
 
import t from './utils/go.js'
Vue.prototype.$T = t;
 
const app = new Vue({
    // 3. [建议] 显式注入 store，确保 mapState 在生命周期早期就能工作
    store,
	...App
})
app.$mount()