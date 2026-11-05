const getters = {
  sidebar: state => state.app.sidebar,
  device: state => state.app.device,
  token: state => state.user.token,
  avatar: state => state.user.avatar,
  name: state => state.user.name,
  userInfo: state => state.user.userInfo,
  roles: state => state.user.roles, // [必须确认这一行存在]
  socketState: state => state.socket.socketState,
  socketMessage: state => state.socket.socketMessage,
  mall_id: state => state.user.mall_id,
  mchInfo: state => state.user.mchInfo,

  mchList: state => state.user.mchList,
  currentMch: state => state.user.currentMch,

  // 动态路由：按当前商城已开通插件过滤后的完整路由(常驻 + 动态)，供 Sidebar 菜单渲染
  permission_routes: state => state.permission.routes
}
export default getters
