import request from '@/utils/request'

export function login(data) {
  return request({
    url: '/plugin/mch/admin/passport/login',
    method: 'post',
    data
  })
}

export function getInfo() {
  return request({
    url: '/api/mch/info', method: 'get',
  })
}

export function logout() {
  return request({
    url: '/api/user/logout', method: 'post'
  })
}

export function getMchCaptcha() {
  return request({
    url: '/plugin/mch/admin/passport/captcha',
    method: 'get',
    data: {}
  })
}
