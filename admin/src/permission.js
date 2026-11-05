import router from './router'
import store from './store'
import {
  Message
} from 'element-ui'
import NProgress from 'nprogress'
import 'nprogress/nprogress.css'
import {
  getToken,
  getMallId
} from '@/utils/auth'
import {
  isRelogin
} from '@/utils/request'

NProgress.configure({
  showSpinner: false
})

const whiteList = ['/login', '/auth-redirect', '/bind', '/register']

// 确保当前商城的词表已装进 store。
// 触发时机不能只在「首次登录」——那样切商城不换词、且首次若未选商城就永远不加载,
// 导致页面显示 xwy.* 这类原始 key(必须去词库页保存才被动拉一次)。
// 这里改为:每次跳转都检查,商城就绪且(未加载 或 商城已变)就拉。fire-and-forget 不阻塞导航。
function ensureNounsLoaded() {
  const rawMallId = getMallId()
  if (!rawMallId) return   // 还没选商城,等选了再加载
  // 与 store.loadNouns 存的 loadedMallId 完全同源(都取 getMallId() || 0),避免字符串/数字类型不一致
  const mallId = rawMallId || 0
  const st = store.state.noun
  if (!st.loaded || st.loadedMallId !== mallId) {
    store.dispatch('noun/loadNouns').catch(() => {})
  }
}

router.beforeEach((to, from, next) => {
  NProgress.start();
  const token = getToken();
  if (token && token != undefined && token != 'undefined') {

    to.meta.title && store.dispatch('settings/setTitle', to.meta.title)
    /* has token*/
    if (to.path === '/login') {
      next({
        path: '/'
      })
      NProgress.done()
    } else {
      if (store.getters.roles.length === 0) {
        isRelogin.show = true
        // 判断当前用户是否已拉取完user_info信息
        store.dispatch('GetInfo').then(() => {

          isRelogin.show = false
          
          // 词库:交给 ensureNounsLoaded 统一判断(切商城/未加载都会补拉)
          ensureNounsLoaded()

          store.dispatch('GenerateRoutes').then(accessRoutes => {
            // 根据roles权限生成可访问的路由表
            router.addRoutes(accessRoutes) // 动态添加可访问路由表
            
            // 解决根目录白屏问题：如果有商城ID则进入该商城主页，否则去入口
            if (to.path === '/') {
              let defaultPath = store.state.permission.defaultHome;
              if (getMallId() && defaultPath) {
                next({ path: defaultPath, replace: true })
              } else {
                next({ path: '/mall/entry', replace: true })
              }
            } else {
              next({ ...to, replace: true }) // hack方法 确保addRoutes已完成
            }
          })
        }).catch(err => {
          store.dispatch('LogOut').then(() => {
            Message.error(err)
            next({
              path: '/'
            })
          })
        })
      } else {
        // roles 已就绪的后续跳转(含切商城后进业务页):词表可能是上个商城的或还没加载,补检查
        ensureNounsLoaded()
        if (to.path === '/') {
          let defaultPath = store.state.permission.defaultHome;
          if (getMallId() && defaultPath) {
            next({ path: defaultPath, replace: true })
          } else {
            next({ path: '/mall/entry', replace: true })
          }
        } else {
          next()
        }
      }
    }
  } else {
    // 没有token
    if (whiteList.indexOf(to.path) !== -1) {
      // 在免登录白名单，直接进入
      next()
    } else {
      next(`/login?redirect=${to.fullPath}`) // 否则全部重定向到登录页
      NProgress.done()
    }
  }
})

router.afterEach(() => {
  NProgress.done()
})
