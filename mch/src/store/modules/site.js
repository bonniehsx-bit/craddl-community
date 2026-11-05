import { getSetting } from '@/api/system/site'

// 配置留空时回到随系统交付的图标，避免清除自定义图标后仍残留旧品牌。
function applyDOMSetting(setting) {
  if (!setting) return
  let link = document.getElementById('dynamic-favicon')
  if (!link) {
    link = document.createElement('link')
    link.id = 'dynamic-favicon'
    link.rel = 'icon'
    document.head.appendChild(link)
  }
  link.href = setting.favicon || process.env.BASE_URL + 'favicon.ico?v=craddl-1'
}

// 1. 安全读取本地缓存
let localSetting = {}
try {
  const stored = localStorage.getItem('site-setting')
  if (stored) {
    localSetting = JSON.parse(stored)
    applyDOMSetting(localSetting)
  }
} catch (e) {
  console.error('Site setting parse error:', e)
}

const site = {
  state: {
    setting: localSetting || {}
  },

  mutations: {
    SET_SETTING: (state, setting) => {
      state.setting = setting
      localStorage.setItem('site-setting', JSON.stringify(setting))
      applyDOMSetting(setting)
    }
  },

  actions: {
    // 获取配置
    getSiteSetting({ commit }) {
      return new Promise((resolve, reject) => {
        getSetting().then(response => {
          const { data } = response
          if (!data) {
            return reject('Verification failed, please Login again.')
          }
          commit('SET_SETTING', data.setting)
          resolve(data)
        }).catch(error => {
          reject(error)
        })
      })
    }
  }
}

export default site
