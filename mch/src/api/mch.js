import request from '@/utils/request'

// 1. 商家登录
export function mchLogin(data) {
  return request({
    url: '/plugin/mch/admin/passport/login',
    method: 'post',
    data,
    // 登录需跨商城遍历 User + 逐店查 Mch/Clerk，单独放宽超时，避免账号/店铺多或库慢时误报超时
    timeout: 15000
  })
}

// 2. 获取商家/店员详情 (对应 permission.js 中的 getInfo)
// 注意：你需要确保后端 PassportController 有 actionUserInfo 方法
export function getMchUserInfo() {
  return request({
    url: '/plugin/mch/admin/passport/user-info',
    method: 'get'
  })
}

// 3. 商家登出
export function mchLogout() {
  return request({
    url: '/plugin/mch/admin/passport/logout', // 如果后端没写这个，前端直接清本地Token也可
    method: 'post'
  })
}

// 4. 获取验证码
export function getMchCaptcha() {
  return request({
    url: '/plugin/mch/admin/passport/captcha',
    method: 'get'
  })
}

// 5. 动态菜单/路由：后端按当前商城已开通插件聚合各插件 getMchMenus() 下发
export function getMchRouters() {
  return request({
    url: '/plugin/mch/admin/menu/routers',
    method: 'get'
  })
}
