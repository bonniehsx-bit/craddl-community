import Vue from 'vue'
import Vuex from 'vuex'
import app from './modules/app'
import user from './modules/user'
import tagsView from './modules/tagsView'
import permission from './modules/permission'
import settings from './modules/settings'
import getters from './getters'
import site from './modules/site'
import shortcut from './modules/shortcut'
import im from './modules/im'
import noun from './modules/noun'

Vue.use(Vuex)

const store = new Vuex.Store({
	modules: {
		app,
		user,
		tagsView,
		permission,
		settings,
		site,
		shortcut,
		im,
		noun
	},
	getters
})

export default store
