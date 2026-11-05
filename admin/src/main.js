import Vue from 'vue'

import Cookies from 'js-cookie'
import Element from 'element-ui'
import './styles/element-variables.scss'

import './styles/index.scss' // global css
import './styles/jxmall.scss' // jxmall css
import App from './App'
import store from './store'
import { applyAdminTheme } from '@/utils/apply-admin-theme'
import './styles/admin-theme.scss'

store.watch(state => [state.settings.themeMode, state.settings.theme], () => {
  applyAdminTheme(store.state.settings)
}, { immediate: true })
import router from './router'
import directive from './directive' // directive
import plugins from './plugins' // plugins
import {download} from '@/utils/request'
import {
  encode, decode
} from '@/utils/mcrypt'

Vue.prototype.$mcrypt_encode = encode
Vue.prototype.$mcrypt_decode = decode

import request from '@/utils/request'

import './assets/icons' // icon
import './permission' // permission control
import {
  getDicts
} from "@/api/system/dict/data";
import {
  getConfigKey
} from "@/api/system/config";
import {
  parseTime, resetForm, addDateRange, selectDictLabel, selectDictLabels, handleTree
} from "@/utils/jxmall";
// 分页组件
//import Pagination from "@/components/Pagination";
// 自定义表格工具组件
import RightToolbar from "@/components/RightToolbar"
// 富文本组件
import Editor from "@/components/Editor"
// 文件上传组件
import FileUpload from "@/components/FileUpload"
// 图片上传组件
import ImageUpload from "@/components/ImageUpload"
// 图片预览组件
import ImagePreview from "@/components/ImagePreview"

import Avatar from "@/components/Avatar"
import ExportButton from "@/components/ExportButton/ExportButton"
import AsyncExportButton from "@/components/AsyncExportButton/AsyncExportButton"

Vue.component('export-button', ExportButton)
Vue.component('async-export-button', AsyncExportButton)

Vue.component('avatar', Avatar)
// 字典标签组件
import DictTag from '@/components/DictTag'
// 头部标签组件
import VueMeta from 'vue-meta'
// 字典数据组件
import DictData from '@/components/DictData'
import GoodsPicker from '@/components/common/GoodsPicker'

Vue.component('goods-picker', GoodsPicker)

import OrderPreview from '@/components/mall/order/index'

// 2. 使用插件
Vue.use(OrderPreview, { router })

import Pagination from "@/components/common/Pagination";
import Loading from '@/components/common/Loading'

Vue.component('loading', Loading)
import ScrollBar from '@/components/common/ScrollBar'

Vue.component('scroll-bar', ScrollBar)
import SingleImageUploader from '@/components/mall/SingleImageUploader'

Vue.component('single-image-uploader', SingleImageUploader)

import SingleImageSelector from '@/components/mall/SingleImageSelector'
import SingleFileSelector from '@/components/mall/SingleFileSelector'
import SingleVideoSelector from '@/components/mall/SingleVideoSelector'

Vue.component('single-image-selector', SingleImageSelector)
Vue.component('single-file-selector', SingleFileSelector)
Vue.component('single-video-selector', SingleVideoSelector)
// 视频选择(输入框 + 选择视频按钮,和 link-picker 同款)
import VideoPicker from '@/components/mall/VideoPicker'
Vue.component('video-picker', VideoPicker)
import ImagesSelector from '@/components/mall/ImagesSelector'

Vue.component('images-selector', ImagesSelector)
import PtGoodsPicker from '@/components/common/PtGoodsPicker'

Vue.component('pt-goods-picker', PtGoodsPicker)

import UserSelector from '@/components/mall/UserSelector'

Vue.component('user-selector', UserSelector)

import ImageView from '@/components/ImageView'

Vue.component('image-view', ImageView)

import RichText from '@/components/common/RichText'

Vue.component('rich-text', RichText);
import exportExcel from '@/utils/ExportExcel';

Vue.prototype.$export = exportExcel;
// 全局方法挂载
Vue.prototype.getDicts = getDicts
Vue.prototype.getConfigKey = getConfigKey
Vue.prototype.parseTime = parseTime
Vue.prototype.resetForm = resetForm
Vue.prototype.addDateRange = addDateRange
Vue.prototype.selectDictLabel = selectDictLabel
Vue.prototype.selectDictLabels = selectDictLabels
Vue.prototype.download = download
Vue.prototype.handleTree = handleTree
Vue.prototype.$request = request

// 全局词库 mixin：所有组件可直接用 this.$noun('balance') / {{ $noun('balance') }}
import nounMixin from '@/mixins/noun'
Vue.mixin(nounMixin)

import TuiIconPicker from '@/components/TuiIconPicker/index.vue'
import TuiIconView from '@/components/TuiIconPicker/TuiIconView.vue'

// 全局组件挂载
Vue.component('DictTag', DictTag)
Vue.component('Pagination', Pagination)
Vue.component('RightToolbar', RightToolbar)
Vue.component('Editor', Editor)
Vue.component('FileUpload', FileUpload)
Vue.component('ImageUpload', ImageUpload)
Vue.component('ImagePreview', ImagePreview)
// 手机端 tui-icon 图标:选择器(点开选,不用记英文名)+ 显示(后台预览画出图标本身)
Vue.component('tui-icon-picker', TuiIconPicker)
Vue.component('tui-icon-view', TuiIconView)

Vue.use(directive)
Vue.use(plugins)
Vue.use(VueMeta)
DictData.install()
import FilePicker from '@/components/common/FilePicker'

Vue.component('file-picker', FilePicker)
import LinkPicker from '@/components/mall/LinkPicker'

Vue.component('link-picker', LinkPicker)
/**
 * If you don't want to use mock-server
 * you want to use MockJs for mock api
 * you can execute: mockXHR()
 *
 * Currently MockJs will be used in the production environment,
 * please remove it before going online! ! !
 */

Vue.use(Element, {
  size: Cookies.get('size') || 'medium' // set element-ui default size
})

Vue.config.productionTip = false

new Vue({
  el: '#app', router, store, render: h => h(App)
})
