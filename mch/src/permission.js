import router, { wildcardRoute } from './router'
import store from './store'
import { Message } from 'element-ui'
import NProgress from 'nprogress'
import 'nprogress/nprogress.css'
import { getToken } from '@/utils/auth'
import { getMallId } from '@/utils/mall' // 保持你的路径引用
import getPageTitle from '@/utils/get-page-title'

NProgress.configure({ showSpinner: false })

const whiteList = ['/login', '/404']

router.beforeEach(async(to, from, next) => {
  NProgress.start()
  document.title = getPageTitle(to.meta.title)

  // 初始化商城ID
  getMallId()

  const hasToken = getToken()

  if (hasToken) {
    if (to.path === '/login') {
      next({ path: '/' })
      NProgress.done()
    } else {
      // 判断 Vuex 中是否有用户信息
      const hasRoles = store.getters.roles && store.getters.roles.length > 0

      if (hasRoles) {
        // 有用户信息，直接放行
        next()
      } else {
        try {
          // 1. 拉取用户信息(角色 / 当前商户上下文)，commit 到 Vuex
          await store.dispatch('user/getInfo')

          // 2. 拉取后端按【当前商城已开通插件】聚合的插件菜单，转成路由并注册
          //    通配 404 必须在动态路由之后追加，否则会抢先匹配掉未注册的动态路由
          const accessRoutes = await store.dispatch('permission/generateRoutes')
          router.addRoutes([...accessRoutes, wildcardRoute])

          // 3. hack：确保 addRoutes 完成后再进入目标路由
          next({ ...to, replace: true })
        } catch (error) {
          // 获取用户信息失败（Token过期等），重置 Token 并跳回登录页
          await store.dispatch('user/resetToken')
          Message.error(error || '验证失败，请重新登录')
          next(`/login?redirect=${to.path}`)
          NProgress.done()
        }
      }
    }
  } else {
    /* no token */
    if (whiteList.indexOf(to.path) !== -1) {
      next()
    } else {
      next(`/login?redirect=${to.path}`)
      NProgress.done()
    }
  }
})

router.afterEach(() => {
  NProgress.done()
})
