import Cookies from 'js-cookie'

// ⚠ Cookie 名必须与平台后台(admin)区分开。
//   两个前端部署在**同一域名**下(/mch/ 与 /admin/)，而 js-cookie 默认 path=/ 全站可见，
//   同名就会互相覆盖:在同一浏览器先登平台后台、再登商家后台，切回平台后台点任何东西
//   都会被判「登录信息已过期」踢下线，反之亦然 —— 而且完全静默，
//   用户只会觉得"后台老掉线"，根本想不到是另一个标签页干的。
//   (两端请求头本来是分开的:x-access-token / x-admin-token，后端边界干净，
//    唯独 Cookie 这一层撞了，所以不是越权，是会话互毁。)
const TokenKey = 'Mch-Token'
const MchIdKey = 'Mch-Current-Mch-Id'

export function getToken() {
  return Cookies.get(TokenKey)
}

export function setToken(token) {
  return Cookies.set(TokenKey, token)
}

export function removeToken() {
  return Cookies.remove(TokenKey)
}

// [新增] 获取当前商户ID
export function getCurrentMchId() {
  return Cookies.get(MchIdKey)
}

// [新增] 设置当前商户ID
export function setCurrentMchId(id) {
  return Cookies.set(MchIdKey, id)
}

// [新增] 移除当前商户ID
export function removeCurrentMchId() {
  return Cookies.remove(MchIdKey)
}
