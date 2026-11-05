import axios from 'axios'
import {
  MessageBox,
  Message
} from 'element-ui'
import store from '@/store'
import {
  getToken,
  getCurrentMchId
} from '@/utils/auth'
import {
  getMallId
} from '@/utils/mall'

axios.defaults.headers['Content-Type'] = 'application/x-www-form-urlencoded'
import qs from 'qs'

if (process.env.NODE_ENV === 'development') {
  axios.defaults.baseURL = '/api?' + 'r='
} else if (process.env.NODE_ENV === 'debug') {
  axios.defaults.baseURL = ''
} else if (process.env.NODE_ENV === 'production') {
  axios.defaults.baseURL = window.location.protocol + '//' + window.location.host + '/index.php?r=' //独立版
}
// create an axios instance
const service = axios.create({
  baseURL: axios.defaults.baseURL,
  timeout: 5000 // request timeout
})

// request interceptor
service.interceptors.request.use(
  config => {
    if (store.getters.token) {
      config.headers['x-access-token'] = getToken()
    }
    const mchId = getCurrentMchId()
    if (mchId) {
      config.headers['X-Mch-Id'] = mchId
    }
    config.headers['x-mall-id'] = getMallId()
    if (config.method === 'post' || config.method === 'POST') {
      if (config.data) {
        config.data = qs.stringify(config.data)
      }
    } else {
      config.params = config.data;
    }
    return config
  },
  error => {
    // do something with request error
    console.log(error) // for debug
    return Promise.reject(error)
  }
)

// response interceptor
service.interceptors.response.use(
  response => {
    const res = response.data

    if (res.code != 0 && res.code !== 2) {
      Message({
        message: res.msg || 'Error',
        type: 'error',
        duration: 5 * 1000
      })
      if (res.code === -1) {
        // to re-login
        MessageBox.confirm('登录信息已过期', {
          confirmButtonText: '重新登录',
          cancelButtonText: '取消',
          type: 'warning'
        }).then(() => {
          store.dispatch('user/resetToken').then(() => {
            location.reload()
          })
        })
      }
      return Promise.reject(new Error(res.msg || 'Error'))
    } else {
      // res.code === 0 或者 res.code === 2(多店选择) 都放行给业务层处理
      return res
    }
  },
  error => {
    Message({
      message: error.msg,
      type: 'error',
      duration: 5 * 1000
    })
    return Promise.reject(error)
  }
)

export default service
