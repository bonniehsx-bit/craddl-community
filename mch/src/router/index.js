import Vue from 'vue'
import Router from 'vue-router'

Vue.use(Router)

/* Layout (后台主框架) */
import Layout from '@/layout/index.vue'

/* 核心路由模块(与具体插件无关，任何商户都有：店铺运营 / 订单 / 门店资产) */
import mallRouter from './modules/mall'
import storeRouter from './modules/store'
import orderRouter from './modules/order'

/**
 * constantRoutes
 * 常驻路由：登录、404、首页概览 + 核心业务(店铺运营/订单/门店资产)。
 * 这些与商城开通了哪些插件无关，恒常存在，编译期即注册。
 *
 * 【插件菜单不在这里】group/hotel/yuda/otv 等插件的 mch 路由改由各插件 Plugin::getMchMenus() 声明，
 * 登录后经 /plugin/mch/admin/menu/routers 聚合下发、动态 addRoutes(见 store/modules/permission.js)。
 */
export const constantRoutes = [
  {
    path: '/login',
    component: () => import('@/views/login/index.vue'),
    hidden: true
  },
  {
    path: '/404',
    component: () => import('@/views/404.vue'),
    hidden: true
  },
  {
    path: '/',
    component: Layout,
    redirect: '/dashboard',
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/dashboard/index.vue'),
        meta: { title: '首页概览', icon: 'el-icon-data-board', affix: true }
      }
    ]
  },

  ...mallRouter,
  ...orderRouter,
  ...storeRouter
]

/**
 * 通配 404 —— 必须在动态路由 addRoutes 之后追加，否则会抢先匹配掉未注册的动态路由。
 * 由 store/modules/permission.js 在 generateRoutes 末尾统一 addRoutes。
 */
export const wildcardRoute = { path: '*', redirect: '/404', hidden: true }

const createRouter = () => new Router({
  // mode: 'history', // require service support
  scrollBehavior: () => ({ y: 0 }),
  routes: constantRoutes
})

const router = createRouter()

export function resetRouter() {
  const newRouter = createRouter()
  router.matcher = newRouter.matcher // reset router
}

export default router
