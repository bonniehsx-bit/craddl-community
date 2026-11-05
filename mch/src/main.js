import Vue from 'vue'


import ElementUI from 'element-ui'
import 'element-ui/lib/theme-chalk/index.css'
import locale from 'element-ui/lib/locale/lang/zh-CN' // lang i18n

import '@/styles/index.scss' // global css

import App from './App.vue'
import store from './store'
import router from './router'
import request from '@/utils/request'
Vue.prototype.$request = request

import {
  encode,
  decode
} from '@/utils/mcrypt'

Vue.prototype.$mcrypt_encode = encode
Vue.prototype.$mcrypt_decode = decode

import '@/icons' // icon

import '@/permission' // permission control
import plugins from './plugins' // plugins
Vue.use(plugins)




import Pagination from "@/components/Pagination/index.vue";
Vue.component('pagination', Pagination);
import ImagePreview from "@/components/ImagePreview/index.vue";
Vue.component('image-preview', ImagePreview);
import SingleImageSelector from "@/components/mall/SingleImageSelector.vue";
Vue.component('single-image-selector', SingleImageSelector);

import ImagesSelector from "@/components/mall/ImagesSelector.vue";
Vue.component('images-selector', ImagesSelector);
import FilePicker from "@/components/common/FilePicker.vue";
Vue.component('file-picker', FilePicker);
import RichText from '@/components/common/RichText.vue'

Vue.component('rich-text', RichText);
import exportExcel from '@/utils/ExportExcel';
Vue.prototype.$export = exportExcel;

import orderTriggerPlugin from '@/components/mall/order/index.js';
Vue.use(orderTriggerPlugin);

Vue.use(ElementUI, {
  locale
})
// 如果想要中文版 element-ui，按如下方式声明
// Vue.use(ElementUI)


Vue.config.productionTip = false

new Vue({
  el: '#app',
  router,
  store,
  render: h => h(App)
})
