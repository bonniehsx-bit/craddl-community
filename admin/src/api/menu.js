import request from '@/utils/request'
import { getMallId } from '@/utils/auth'

// 获取路由
export const getRouters = () => {
  const url = getMallId() ? '/mall/menu/routers' : '/admin/admin/routers';
  return request({
    url: url,
    method: 'get'
  })
}