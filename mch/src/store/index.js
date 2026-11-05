import Vue from 'vue'
import Vuex from 'vuex'
import getters from './getters'
import app from './modules/app'
import settings from './modules/settings'
import user from './modules/user'
import socket from './modules/socket'
import site from './modules/site'
import permission from './modules/permission'
Vue.use(Vuex)

const store = new Vuex.Store({
  modules: {
    app,
    settings,
    user,
    socket,
    site,
    permission
  },
  getters
})

export default store
