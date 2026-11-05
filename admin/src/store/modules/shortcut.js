import { getShortcutList, updateShortcutList } from '@/api/admin/shortcut'
import { Message } from 'element-ui'

const state = {
  list: []
}

const mutations = {
  SET_SHORTCUTS: (state, shortcuts) => {
    state.list = shortcuts || []
  },
  ADD_SHORTCUT: (state, shortcut) => {
    const exists = state.list.find(item => item.path === shortcut.path)
    if (!exists) {
      state.list.push(shortcut)
    }
  },
  REMOVE_SHORTCUT: (state, path) => {
    for (const [i, v] of state.list.entries()) {
      if (v.path === path) {
        state.list.splice(i, 1)
        break
      }
    }
  }
}

const actions = {
  // 从服务器初始化
  initShortcuts({ commit }) {
    return new Promise((resolve, reject) => {
      getShortcutList().then(res => {
        if (res.code === 0 && res.data && res.data.list) {
          commit('SET_SHORTCUTS', res.data.list)
        }
        resolve(res)
      }).catch(error => {
        reject(error)
      })
    })
  },
  // 添加快捷菜单并同步服务器
  addShortcut({ commit, state }, view) {
    commit('ADD_SHORTCUT', {
      title: view.meta && view.meta.title ? view.meta.title : (view.title || '快捷菜单'),
      path: view.path,
      icon: view.meta && view.meta.icon ? view.meta.icon : 'star' // 原生 layout 的 svg-icon 通常是星标
    })
    updateShortcutList({ shortcuts: state.list }).then(res => {
      if (res.code === 0) {
        Message({ message: '已加入常用', type: 'success', duration: 1500 })
      }
    })
  },
  // 移除快捷菜单并同步服务器
  removeShortcut({ commit, state }, view) {
    commit('REMOVE_SHORTCUT', view.path)
    updateShortcutList({ shortcuts: state.list }).then(res => {
      if (res.code === 0) {
        Message({ message: '已移除常用', type: 'warning', duration: 1500 })
      }
    })
  }
}

export default {
  namespaced: true,
  state,
  mutations,
  actions
}
