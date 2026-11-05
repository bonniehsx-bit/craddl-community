import request from '@/utils/request'
import { getMallId } from '@/utils/auth'

/**
 * 词库（名词配置）store —— 后台全局 $noun() 的数据源
 *
 * 数据来自 `/mall/mall/noun-map`（服务端 NounHelper::resolvedMap，已算好「配置值 ?: 默认词」，含插件词）。
 * 按商城隔离缓存到 localStorage，避免每次进后台首屏闪英文 key。
 */

const cacheKey = () => `noun-map-${getMallId() || 0}`

function readCache() {
  try {
    return JSON.parse(localStorage.getItem(cacheKey())) || {}
  } catch (e) {
    return {}
  }
}

function writeCache(nouns) {
  try {
    localStorage.setItem(cacheKey(), JSON.stringify(nouns))
  } catch (e) {
    /* 配额满等异常忽略，不影响取词 */
  }
}

const state = {
  // key => 最终值。首屏先用缓存，loadNouns 回来后覆盖
  nouns: readCache(),
  loaded: false,
  // 词表按商城隔离:记录当前 store 里装的是哪个商城的词，切商城时据此判断是否要重拉
  loadedMallId: null
}

const mutations = {
  SET_NOUNS: (state, payload) => {
    // 兼容旧调用(直接传 nouns 对象)与新调用({ nouns, mallId })
    const nouns = payload && payload.nouns !== undefined ? payload.nouns : payload
    state.nouns = nouns || {}
    state.loaded = true
    state.loadedMallId = (payload && payload.mallId !== undefined) ? payload.mallId : state.loadedMallId
  }
}

const actions = {
  // 进入后台（GetInfo 之后、已有 mall 上下文）时拉取一次
  loadNouns({ commit }) {
    const mallId = getMallId() || 0
    return request({ url: '/mall/mall/noun-map', method: 'get' }).then(res => {
      if (res && res.code === 0) {
        const nouns = (res.data && res.data.nouns) || {}
        commit('SET_NOUNS', { nouns, mallId })
        writeCache(nouns)
      }
      return res
    })
  }
}

export default {
  namespaced: true,
  state,
  mutations,
  actions
}
