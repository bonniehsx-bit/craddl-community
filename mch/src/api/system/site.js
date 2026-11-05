import request from '@/utils/request'

export function getSetting() {
    return request({
        url: '/admin/site/setting',
        method: 'get'
    })
}
