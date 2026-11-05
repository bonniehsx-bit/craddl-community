import { login, logout, getInfo } from '@/api/login'
import { getToken, setToken, removeToken } from '@/utils/auth'

const user = {
  state: {
    token: getToken(),
    name: '',
    nickname: '', // 新增
    avatar: '',
    admin_type: null, // 新增：用于判断是否超管
    // 能进的商城是否多于一个 —— 决定要不要给「切换商城」入口。
    // 由后端 Admin::canSwitchMall() 算好下发:前端只知道 admin_type,算不出这个数。
    mall_switchable: false,
    roles: [],
    permissions: [],
    has_im: false // 新增：判断是否拥有 IM 插件的权限
  },

  mutations: {
    SET_TOKEN: (state, token) => {
      state.token = token
    },
    SET_NAME: (state, name) => {
      state.name = name
    },
    SET_NICKNAME: (state, nickname) => { // 新增
      state.nickname = nickname
    },
    SET_AVATAR: (state, avatar) => {
      state.avatar = avatar
    },
    SET_ADMIN_TYPE: (state, admin_type) => { // 新增
      state.admin_type = admin_type
    },
    SET_MALL_SWITCHABLE: (state, v) => {
      state.mall_switchable = !!v
    },
    SET_ROLES: (state, roles) => {
      state.roles = roles
    },
    SET_PERMISSIONS: (state, permissions) => {
      state.permissions = permissions
    },
    SET_HAS_IM: (state, has_im) => {
      state.has_im = has_im
    }
  },

  actions: {
    // 登录
    Login({ commit }, userInfo) {
      const username = userInfo.username.trim()
      const password = userInfo.password
      const captcha = userInfo.captcha
      const login_type = userInfo.login_type
      // [新增] 获取验证码 Key
      const captcha_key = userInfo.captcha_key

      return new Promise((resolve, reject) => {
        // [修改] 将 captcha_key 传递给 API
        login(username, password, captcha, login_type, captcha_key).then(res => {
          setToken(res.data.token)
          // 这里的 setMallId 已移除，逻辑正确
          commit('SET_TOKEN', res.data.token)
          resolve()
        }).catch(error => {
          reject(error)
        })
      })
    },

    // 获取用户信息
    GetInfo({ commit, state }) {
      return new Promise((resolve, reject) => {
        getInfo().then(res => {
          // 注意：请确保后端 getInfo 返回结构中包含 user.admin_type
          const user = res.user
          const avatar = user.avatar == "" ? require("@/assets/logo/logo.png") : user.avatar;

          if (res.roles && res.roles.length > 0) {
            commit('SET_ROLES', res.roles)
          } else {
            commit('SET_ROLES', ['ROLE_DEFAULT'])
          }

          commit('SET_PERMISSIONS', res.permissions)
          commit('SET_NAME', user.username)
          commit('SET_NICKNAME', user.nickname) // 存储昵称
          commit('SET_AVATAR', avatar)
          commit('SET_ADMIN_TYPE', user.admin_type) // 存储用户类型
          commit('SET_MALL_SWITCHABLE', user.mall_switchable)
          commit('SET_HAS_IM', !!res.has_im) // 存储 IM 插件状态

          resolve(res)
        }).catch(error => {
          reject(error)
        })
      })
    },

    // 退出系统
    LogOut({ commit, state }) {
      return new Promise((resolve, reject) => {
        logout(state.token).then(() => {
          commit('SET_TOKEN', '')
          commit('SET_ROLES', [])
          commit('SET_PERMISSIONS', [])
          removeToken()
          resolve()
        }).catch(error => {
          reject(error)
        })
      })
    },

    // 前端 登出
    FedLogOut({ commit }) {
      return new Promise(resolve => {
        commit('SET_TOKEN', '')
        removeToken()
        resolve()
      })
    }
  }
}

export default user
