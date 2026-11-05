import Cookies from 'js-cookie'

const TokenKey = 'Admin-Token'
const MallIdKey = 'x-mall-id' // 定义 Key

export function getToken() {
  return Cookies.get(TokenKey)
}

export function setToken(token) {
  return Cookies.set(TokenKey, token)
}

export function removeToken() {
  return Cookies.remove(TokenKey)
}

// === 修正部分 ===
export function getMallId() {
  // 不要返回 1，要从 Cookie 取
  return Cookies.get(MallIdKey)
}

export function setMallId(mall_id) {
  // 不要写死 1，要存传入的值
  return Cookies.set(MallIdKey, mall_id)
}

export function removeMallId() {
  return Cookies.remove(MallIdKey)
}
