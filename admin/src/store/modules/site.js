import { getSetting, setSetting } from '@/api/system/site'

// --- 修复黑魔法：封装更新 DOM 方法 (拔插法强制浏览器刷新图标) ---
function applyDOMSetting(setting) {
  if (setting && setting.title) {
    document.title = setting.title
  }
  if (setting) {
    let oldLink = document.getElementById('dynamic-favicon')
    if (oldLink) {
      document.head.removeChild(oldLink)
    }
    let newLink = document.createElement('link')
    newLink.id = 'dynamic-favicon'
    newLink.rel = 'shortcut icon'
    newLink.href = setting.favicon || process.env.BASE_URL + 'favicon.ico?v=craddl-1'
    document.head.appendChild(newLink)
  }
}

// 1. 安全读取本地缓存
let localSetting = {}
try {
  const stored = localStorage.getItem('site-setting')
  if (stored) {
    localSetting = JSON.parse(stored)
    // 【关键修复】趁浏览器没反应过来，立即借用本地缓存抢载 Title 和 Favicon (解决首屏白板、时间差问题)
    applyDOMSetting(localSetting)
  }
} catch (e) {
  console.error('Site setting parse error:', e)
}

const site = {
  state: {
    // 平台全局配置
    setting: localSetting || {},
    // 当前进入的商城 logo（优先级高于平台 header_pic）
    mallLogo: localStorage.getItem('mall-logo') || '',
    // 当前进入的商城名称。平台管理员可以跨商城操作，"我现在在哪个商城"必须随时看得见，
    // 否则容易在 A 商城的后台做了本该给 B 商城做的事。侧边栏底部账号区常驻显示它。
    mallName: localStorage.getItem('mall-name') || ''
  },

  mutations: {
    SET_SETTING: (state, setting) => {
      state.setting = setting
      localStorage.setItem('site-setting', JSON.stringify(setting))
      applyDOMSetting(setting)
    },
    SET_MALL_LOGO: (state, logo) => {
      state.mallLogo = logo || ''
      localStorage.setItem('mall-logo', logo || '')
    },
    SET_MALL_NAME: (state, name) => {
      state.mallName = name || ''
      localStorage.setItem('mall-name', name || '')
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
          // 【修正】：不要在这里 stringify，直接传对象
          commit('SET_SETTING', data.setting)
          resolve(data)
        }).catch(error => {
          reject(error)
        })
      })
    },

    // 保存配置
    saveSiteSetting({ commit }, setting) {
      return new Promise((resolve, reject) => {
        setSetting(setting).then(response => {
          if (response.code !== 0) { // 注意这里通常用 code !== 0 判断失败
            return reject(response.msg)
          }
          // 【修正】：直接传对象
          // 假设后端返回结构是 response.data.setting，如果没返回最新配置，可以用传入的 setting
          const newSetting = response.data && response.data.setting ? response.data.setting : setting
          commit('SET_SETTING', newSetting)
          resolve(response)
        }).catch(error => {
          reject(error)
        })
      })
    }
  }
}

export default site
