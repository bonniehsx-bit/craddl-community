import auth from '@/plugins/auth'
import router, { constantRoutes, dynamicRoutes } from '@/router'
import { getRouters } from '@/api/menu'
import { getMallId } from '@/utils/auth'
import Layout from '@/layout/index'
import ParentView from '@/components/ParentView'
import InnerLink from '@/layout/components/InnerLink'

const permission = {
  state: {
    routes: [],
    addRoutes: [],
    defaultRoutes: [],
    topbarRouters: [],
    sidebarRouters: [],
    defaultHome: '/index'
  },
  mutations: {
    SET_DEFAULT_HOME: (state, path) => {
      state.defaultHome = path
    },
    SET_ROUTES: (state, routes) => {
      state.addRoutes = routes
      state.routes = constantRoutes.concat(routes)
    },
    SET_DEFAULT_ROUTES: (state, routes) => {
      state.defaultRoutes = constantRoutes.concat(routes)
    },
    SET_TOPBAR_ROUTES: (state, routes) => {
      state.topbarRouters = routes
    },
    SET_SIDEBAR_ROUTERS: (state, routes) => {
      state.sidebarRouters = routes
    },
  },
  actions: {
    // 生成路由
    GenerateRoutes({ commit, dispatch }) {
      const mallId = Number(getMallId() || 0)
      commit('SET_HAS_IM', false)
      dispatch('im/reset')
      return new Promise(resolve => {
        // 向后端请求路由数据
        getRouters().then(res => {
          const sdata = JSON.parse(JSON.stringify(res.data))
          const rdata = JSON.parse(JSON.stringify(res.data))
          const sidebarRoutes = filterAsyncRouter(sdata)
          const rewriteRoutes = filterAsyncRouter(rdata, false, true)
          const asyncRoutes = filterDynamicRoutes(dynamicRoutes);
          rewriteRoutes.push({ path: '*', redirect: '/404', hidden: true })
          router.addRoutes(asyncRoutes);
          commit('SET_ROUTES', rewriteRoutes)
          commit('SET_SIDEBAR_ROUTERS', constantRoutes.concat(sidebarRoutes))
          commit('SET_DEFAULT_ROUTES', sidebarRoutes)
          commit('SET_TOPBAR_ROUTES', sidebarRoutes)

          // 智能主页推演引擎
          const findHomePath = (routes, basePath = '') => {
            for (let r of routes) {
              let fullPath = r.path.startsWith('/') || r.path.startsWith('http') ? r.path : `${basePath}/${r.path}`.replace(/\/\//g, '/');
              if (r.is_home === 1 || (r.meta && r.meta.is_home === 1)) {
                // 如果命中星标的是一个外壳架构或目录，则自动下钻取它的第一个物理子页面
                if (r.children && r.children.length > 0) {
                  let childPath = r.children[0].path;
                  return childPath.startsWith('/') ? childPath : `${fullPath}/${childPath}`.replace(/\/\//g, '/');
                }
                return fullPath;
              }
              if (r.children && r.children.length > 0) {
                const childPath = findHomePath(r.children, fullPath);
                if (childPath) return childPath;
              }
            }
            return null;
          };

          const findFirstPath = (routes, basePath = '') => {
            for (let r of routes) {
              if (r.hidden || r.path === '*') continue;
              let fullPath = r.path.startsWith('/') || r.path.startsWith('http') ? r.path : `${basePath}/${r.path}`.replace(/\/\//g, '/');
              if (r.children && r.children.length > 0) {
                const childPath = findFirstPath(r.children, fullPath);
                if (childPath) return childPath;
              } else if (r.component !== 'Layout' && r.component !== 'ParentView') {
                return fullPath;
              }
            }
            return null;
          };

          let calculatedHome = '/index';
          const explicitHome = findHomePath(rewriteRoutes);
          if (explicitHome) {
            calculatedHome = explicitHome;
          } else {
            const first = findFirstPath(rewriteRoutes);
            if (first) calculatedHome = first;
          }
          commit('SET_DEFAULT_HOME', calculatedHome);

          // 商城菜单已返回插件安装和授权状态，平台菜单不参与 IM 初始化。
          if (mallId > 0 && mallId === Number(getMallId()) && res.has_im === true) {
            commit('SET_HAS_IM', true)
            dispatch('im/loadImConfig', mallId).catch(() => {})
          }

          resolve(rewriteRoutes)
        })
      })
    }
  }
}

// 遍历后台传来的路由字符串，转换为组件对象
function filterAsyncRouter(asyncRouterMap, lastRouter = false, type = false) {
  return asyncRouterMap.filter(route => {
    if (type && route.children) {
      route.children = filterChildren(route.children)
    }
    if (route.component) {
      // Layout ParentView 组件特殊处理
      if (route.component === 'Layout') {
        route.component = Layout
      } else if (route.component === 'ParentView') {
        route.component = ParentView
      } else if (route.component === 'InnerLink') {
        route.component = InnerLink
      } else {
        route.component = loadView(route.component)
      }
    }
    if (route.children != null && route.children && route.children.length) {
      route.children = filterAsyncRouter(route.children, route, type)
    } else {
      delete route['children']
      delete route['redirect']
    }
    return true
  })
}

function filterChildren(childrenMap, lastRouter = false) {
  var children = []
  childrenMap.forEach((el, index) => {
    if (el.children && el.children.length) {
      if (el.component === 'ParentView' && !lastRouter) {
        el.children.forEach(c => {
          if (c.path && !c.path.startsWith('/')) {
            c.path = el.path + '/' + c.path
          }
          if (c.children && c.children.length) {
            children = children.concat(filterChildren(c.children, c))
            return
          }
          children.push(c)
        })
        return
      }
    }
    if (lastRouter) {
      if (el.path && !el.path.startsWith('/')) {
        el.path = lastRouter.path + '/' + el.path
      }
    }
    children = children.concat(el)
  })
  return children
}

// 动态路由遍历，验证是否具备权限
export function filterDynamicRoutes(routes) {
  const res = []
  routes.forEach(route => {
    if (route.permissions) {
      if (auth.hasPermiOr(route.permissions)) {
        res.push(route)
      }
    } else if (route.roles) {
      if (auth.hasRoleOr(route.roles)) {
        res.push(route)
      }
    }
  })
  return res
}

export const loadView = (view) => {
  if (process.env.NODE_ENV === 'development') {
    return (resolve) => require([`@/views/${view}`], resolve)
  } else {
    // 使用 import 实现生产环境的路由懒加载
    return () => import(`@/views/${view}`)
  }
}

export default permission
