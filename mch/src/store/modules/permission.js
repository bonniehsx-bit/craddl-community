import { constantRoutes } from '@/router'
import Layout from '@/layout/index.vue'
import { getMchRouters } from '@/api/mch'

/**
 * 组件名字符串 → 真实 Vue 组件(懒加载)
 * 后端只声明 component 名(相对 @/views，不含扩展名)，组件本身仍在 mch 前端。
 */
const loadView = (view) => () => import('@/views/' + view + '.vue')

/**
 * 后端菜单节点 → vue-router 路由对象(递归)
 * - component: 'Layout'      → 后台外壳
 *              'ParentView'/空 → 分组透传(router-view)
 *              其它            → loadView(@/views/{值}.vue)
 */
function convertRoutes(nodes) {
  if (!Array.isArray(nodes)) {
    return []
  }
  return nodes.map(node => {
    const route = {
      path: node.path,
      name: node.name,
      hidden: !!node.hidden,
      meta: node.meta || {}
    }
    if (node.redirect) {
      route.redirect = node.redirect
    }
    if (node.component === 'Layout') {
      route.component = Layout
    } else if (node.component === 'ParentView' || !node.component) {
      route.component = { render: h => h('router-view') }
    } else {
      route.component = loadView(node.component)
    }
    if (Array.isArray(node.children) && node.children.length) {
      route.children = convertRoutes(node.children)
    }
    return route
  })
}

const state = {
  // 常驻 + 动态，供 Sidebar 菜单渲染
  routes: [],
  // 仅动态部分，供 router.addRoutes 注册
  addRoutes: []
}

const mutations = {
  SET_ROUTES: (state, routes) => {
    state.addRoutes = routes
    state.routes = constantRoutes.concat(routes)
  }
}

const actions = {
  // 拉取后端聚合的插件菜单(已按当前商城开通插件过滤)，转成路由并注册
  generateRoutes({ commit }) {
    return new Promise(resolve => {
      getMchRouters().then(res => {
        const tree = (res && res.data) || []
        const accessedRoutes = convertRoutes(tree)
        commit('SET_ROUTES', accessedRoutes)
        resolve(accessedRoutes)
      }).catch(() => {
        // 接口失败降级：仅保留核心路由，不阻断进入后台
        commit('SET_ROUTES', [])
        resolve([])
      })
    })
  }
}

export default {
  namespaced: true,
  state,
  mutations,
  actions
}
