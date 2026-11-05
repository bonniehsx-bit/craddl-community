import request from '@/utils/request'

// 获取当前登录管理员的快捷菜单集合
export function getShortcutList() {
  return request({
    url: '/admin/admin/shortcut-list',
    method: 'get'
  })
}

// 覆写式保存快捷菜单数组
export function updateShortcutList(data) {
  return request({
    url: '/admin/admin/shortcut-update',
    method: 'post',
    data: data
  })
}
