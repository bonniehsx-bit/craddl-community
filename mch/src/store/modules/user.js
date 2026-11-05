import {
  mchLogin,
  mchLogout,
  getMchUserInfo
} from '@/api/mch'
import {
  getToken,
  setToken,
  removeToken,
  // [新增] 引入商户ID管理的 Cookie 操作方法
  getCurrentMchId,
  setCurrentMchId,
  removeCurrentMchId
} from '@/utils/auth'
import {
  resetRouter
} from '@/router'
// 假设 mall_id 的获取逻辑在 utils/mall 或 utils/auth，请根据实际文件位置调整
import {
  getMallId,
  setMallId
} from '@/utils/mall'

const getDefaultState = () => {
  return {
    token: getToken(),
    name: '',
    avatar: '',
    userInfo: null,     // 用户基础详细信息

    // [核心] 权限与商户信息
    roles: [],          // 角色数组 ['admin', 'mch_owner']
    mchList: [],        // 该用户关联的所有商户列表
    currentMch: null,   // 当前选中的商户对象 {id, name, logo, type...}

    mall_id: getMallId(),
  }
}

const state = getDefaultState()

const mutations = {
  RESET_STATE: (state) => {
    Object.assign(state, getDefaultState())
  },
  SET_TOKEN: (state, token) => {
    state.token = token
  },
  SET_MALL: (state, mall_id) => {
    state.mall_id = mall_id
  },
  SET_NAME: (state, name) => {
    state.name = name
  },
  SET_AVATAR: (state, avatar) => {
    state.avatar = avatar
  },
  SET_ROLES: (state, roles) => {
    state.roles = roles
  },
  SET_USERINFO: (state, userInfo) => {
    state.userInfo = userInfo
  },
  // [新增] 设置商户列表
  SET_MCH_LIST: (state, list) => {
    state.mchList = list
  },
  // [新增] 设置当前商户 (同时持久化到 Cookie)
  SET_CURRENT_MCH: (state, mch) => {
    state.currentMch = mch
    if (mch && mch.id) {
      // 将 ID 存入 Cookie，供 request.js 拦截器放入 Header
      setCurrentMchId(mch.id)
    }
  }
}

const actions = {
  // 1. 商家登录
  login({ commit }, userInfo) {
    const { username, password, captcha, captcha_key } = userInfo
    return new Promise((resolve, reject) => {
      // 调用商家登录接口
      mchLogin({
        username: username.trim(),
        password: password,
        captcha: captcha,
        captcha_key: captcha_key
      }).then(response => {
        const { data, code } = response

        if (code === 2) {
          // [多店选择逻辑] 后端提示有多个商城，前端需弹出选择。
          // 此时不存 Token，原样返回给 login component
          resolve(response)
          return
        }

        // 存 Token
        commit('SET_TOKEN', data.access_token)
        setToken(data.access_token)

        // 存 Mall ID (如果后端返回了)
        if (data.mall_id) {
          commit('SET_MALL', data.mall_id)
          setMallId(data.mall_id)
        }

        // [核心] 登录成功即固化当前商户上下文：把后端确定的 mch 写入 Current-Mch-Id，
        // 使后续请求带上 X-Mch-Id，避免 getInfo(默认 mch_list[0]) 与 MchFilter(默认 id ASC)
        // 各自兜底而在“同城多店”时出现 UI 显示店 ≠ 实际操作店。
        if (data.mch_info && data.mch_info.id) {
          setCurrentMchId(data.mch_info.id)
        }

        // 注意：登录成功后不直接设置商户信息，而是让 permission.js
        // 在跳转时调用 getInfo 来获取和初始化，这样逻辑更解耦。

        resolve(response)
      }).catch(error => {
        reject(error)
      })
    })
  },

  // 2. 获取商家用户信息 (初始化入口)
  getInfo({ commit, state }) {
    return new Promise((resolve, reject) => {
      // 调用商家详情接口 (PassportController::actionUserInfo)
      getMchUserInfo().then(response => {
        const { data } = response

        if (!data) {
          return reject('验证失败，请重新登录')
        }

        const { roles, name, avatar, mch_list, current_mch, user } = data

        // 权限校验
        if (!roles || roles.length <= 0) {
          reject('getInfo: roles must be a non-null array!')
          return
        }

        // 提交基础信息
        commit('SET_ROLES', roles)
        commit('SET_NAME', name)
        commit('SET_AVATAR', avatar)
        if (user) {
          commit('SET_USERINFO', user)
        }

        // [核心] 提交多商户信息
        commit('SET_MCH_LIST', mch_list || [])

        // 设置当前上下文的商户
        // 后端逻辑已经根据 Header['X-Mch-Id'] 决定了 current_mch 是哪一个
        if (current_mch) {
          commit('SET_CURRENT_MCH', current_mch)
        } else {
          // 如果后端没返回 current_mch，但有列表，默认取第一个做兜底
          if (mch_list && mch_list.length > 0) {
            commit('SET_CURRENT_MCH', mch_list[0])
          }
        }

        resolve(data)
      }).catch(error => {
        reject(error)
      })
    })
  },

  // 3. [新增] 切换店铺 Action
  switchMch({ commit }, mch) {
    return new Promise(resolve => {
      // 1. 更新 State 和 Cookie
      commit('SET_CURRENT_MCH', mch)

      // 2. 刷新页面
      // 必须刷新，因为：
      // A. request.js 里的 Cookie 已经变了，刷新后所有接口会自动带上新的 X-Mch-Id
      // B. 重新触发 permission.js -> getInfo，从后端拉取新店铺的数据（如权限、菜单等）
      // C. 清理当前页面的临时状态
      location.reload()

      resolve()
    })
  },

  // 4. 登出
  logout({ commit, state }) {
    return new Promise((resolve, reject) => {
      mchLogout(state.token).then(() => {
        removeToken()
        removeCurrentMchId() // [核心] 登出时清除店铺ID记录
        resetRouter()
        commit('RESET_STATE')
        resolve()
      }).catch(error => {
        removeToken()
        removeCurrentMchId()
        resetRouter()
        commit('RESET_STATE')
        resolve()
      })
    })
  },

  // 5. 清除 Token (被动登出)
  resetToken({ commit }) {
    return new Promise(resolve => {
      removeToken()
      removeCurrentMchId() // [核心]
      commit('RESET_STATE')
      resolve()
    })
  }
}

export default {
  namespaced: true,
  state,
  mutations,
  actions
}
