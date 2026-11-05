import Cookies from 'js-cookie'

// ⚠ 同上:不能与平台后台共用 'x-mall-id'。撞了之后平台运营切到商城 5、
//   商家在商城 8，会互相改掉对方的商城上下文 —— 比 token 撞车更隐蔽:
//   不是掉线，是**两边都看错商城的数据**。
//   注意这只是 Cookie 存储名，发出去的请求头仍是 'x-mall-id'(后端契约不变)。
const x_mall_id = 'Mch-Mall-Id'
const url_query_key = 'mall_id' // 地址栏中的参数 key，例如 ?mall_id=5

export function getMallId() {
  // 1. 优先从 Cookie 获取
  let mallId = Cookies.get(x_mall_id)

  // 2. 如果 Cookie 没有，尝试从浏览器地址栏获取
  if (!mallId) {
    try {
      // 获取当前 URL 的查询参数
      const searchParams = new URLSearchParams(window.location.search)
      mallId = searchParams.get(url_query_key)
      // 3. 如果地址栏里有，获取下来并存到 Cookie 中，方便后续请求使用
      if (mallId) {
        setMallId(mallId)
      }
    } catch (e) {
      console.error('获取URL参数失败', e)
    }
  }

  return mallId
}

export function setMallId(mall_id) {
  // 修正：这里之前写死了 1，应该使用传入的 mall_id
  return Cookies.set(x_mall_id, mall_id)
}

export function removeMallId() {
  return Cookies.remove(x_mall_id)
}
