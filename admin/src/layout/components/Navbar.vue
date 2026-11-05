<template>
  <div class="navbar">
    <hamburger id="hamburger-container" :is-active="sidebar.opened" class="hamburger-container"
               @toggleClick="toggleSideBar"/>

    <breadcrumb id="breadcrumb-container" class="breadcrumb-container" v-if="!topNav"/>
    <top-nav id="topmenu-container" class="topmenu-container" v-if="topNav"/>

    <div class="right-menu">
      <template v-if="device!=='mobile'">
        <div class="search-wrapper">
          <search id="header-search" />
        </div>

        <!-- 应用中心:从顶栏下沿推出全部应用面板(原「极速工作台」的收藏并进了面板里的「我的常用」)。
             图标与右侧全屏 / 布局大小同一套 svg-icon;面板打开时收起提示,免得压在面板上 -->
        <el-tooltip content="应用中心" effect="dark" placement="bottom" :disabled="launcherOpen">
          <div id="app-launcher" class="right-menu-item hover-effect" @click="$refs.appLauncher.toggle($el)">
            <svg-icon icon-class="app-grid" />
          </div>
        </el-tooltip>

        <!-- 「日志追踪」「刷新配置缓存」已挪进侧边栏账号菜单:
             前者低频查看、后者超管专属运维,都不值得常占顶栏图标位。
             顶栏只留搜索、高频入口(应用中心)和带未读状态的客服台。 -->
        <!-- 聊天通知抽屉 -->
        <!-- 图标与旁边几个同一套 svg-icon(18px、同样线宽);未读数是贴在图标右上角的小红点,不撑大按钮 -->
        <el-tooltip v-if="has_im && imToken" content="商城客服台" effect="dark" placement="bottom">
          <div id="chat-entry" class="right-menu-item hover-effect" @click="openChatDrawer">
            <span class="chat-icon">
              <svg-icon icon-class="chat-line" />
              <span v-if="imUnread > 0" class="chat-unread">{{ imUnread > 99 ? '99+' : imUnread }}</span>
            </span>
          </div>
        </el-tooltip>

        <screenfull id="screenfull" class="right-menu-item hover-effect"/>

        <el-tooltip content="布局大小" effect="dark" placement="bottom">
          <size-select id="size-select" class="right-menu-item hover-effect"/>
        </el-tooltip>
      </template>

      <!--
        账号入口已移到侧边栏底部(Sidebar/Account.vue),那里能常驻显示昵称和当前商城名;
        splitMenu 分栏模式下则挂在最左侧 SplitNav 的底部。

        这里只为**移动端**保留 —— 手机上侧边栏是收起的抽屉,入口全搬走的话
        退出登录得先点汉堡菜单才能摸到。其余情况不显示,避免同一功能两个入口。
      -->
      <el-dropdown v-if="device === 'mobile'"
                   class="avatar-container right-menu-item hover-effect" trigger="click">
        <div class="avatar-wrapper">
          <img :src="avatar" class="user-avatar">
          <i class="el-icon-caret-bottom"/>
        </div>
        <el-dropdown-menu slot="dropdown">
          <router-link to="/user/profile">
            <el-dropdown-item>个人中心</el-dropdown-item>
          </router-link>

          <el-dropdown-item @click.native="setting = true">
            <span>布局设置</span>
          </el-dropdown-item>

          <!-- 【新增】切换商城按钮 -->
          <el-dropdown-item divided @click.native="switchMall" v-if="mallSwitchable">
            <span>切换商城</span>
          </el-dropdown-item>

          <el-dropdown-item divided @click.native="logout">
            <span>退出登录</span>
          </el-dropdown-item>
        </el-dropdown-menu>
      </el-dropdown>
    </div>

    <!-- 应用中心面板(自己挂到 body 上) -->
    <app-launcher ref="appLauncher" @change="launcherOpen = $event" />

    <!-- IM 客服交互终端 -->
    <chat-drawer ref="chatDrawer" />

  </div>
</template>

<script>
import {mapGetters, mapState} from 'vuex'
import Breadcrumb from '@/components/Breadcrumb'
import TopNav from '@/components/TopNav'
import Hamburger from '@/components/Hamburger'
import Screenfull from '@/components/Screenfull'
import SizeSelect from '@/components/SizeSelect'
import Search from '@/components/HeaderSearch'
// 【新增】引入移除 MallId 的方法
import {removeMallId} from '@/utils/auth'
// 【新增】引入聊天抽屉
import ChatDrawer from './ChatDrawer'
import AppLauncher from './AppLauncher'

export default {
  components: {
    Breadcrumb,
    TopNav,
    Hamburger,
    Screenfull,
    SizeSelect,
    Search,
    AppLauncher,
    ChatDrawer,
  },
  data() {
    return {
      launcherOpen: false
    }
  },
  computed: {
    ...mapGetters([
      'sidebar',
      'avatar',
      'device',
      'has_im'
    ]),
    ...mapState({
      // 与侧边栏账号菜单同一判据,别再各写一套(见 Admin::canSwitchMall)
      mallSwitchable: state => state.user.mall_switchable,
      imUnread: state => state.im.globalUnread,
      imToken: state => state.im.imToken,
    }),
    ...mapGetters([
       'imConnected'
    ]),
    setting: {
      get() {
        return this.$store.state.settings.showSettings
      },
      set(val) {
        this.$store.dispatch('settings/changeSetting', {
          key: 'showSettings',
          value: val
        })
      }
    },
    topNav: {
      get() {
        return this.$store.state.settings.topNav
      }
    }
  },
  methods: {
    toggleSideBar() {
      this.$store.dispatch('app/toggleSideBar')
    },
    
    // 打开客服抽屉
    openChatDrawer() {
      // 只有连接了 IM 才能打开，或者无论如何都能打开，但提示未连接
      this.$refs.chatDrawer.show();
    },

    // 【新增】切换商城逻辑
    switchMall() {
      this.$confirm('确定要返回商城选择列表吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'info'
      }).then(() => {
        // 1. 清除 MallID 和 IM
        removeMallId();
        this.$store.dispatch('im/closeSocket');

        // 2. 跳转到入口页
        // 使用 location.href 强制刷新跳转，确保 Vuex 中的权限路由、菜单等缓存数据被彻底清空
        // 如果用 this.$router.push('/mall/entry')，可能需要手动重置 Vuex 状态
        // 建议用下面这种方式：
        window.location.href = '/admin/#/mall/entry';

      }).catch(() => {
      });
    },

    async logout() {
      this.$confirm('确定注销并退出系统吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        this.$store.dispatch('LogOut').then(() => {
          window.location.href = '/admin';
        })
      }).catch(() => {
      });
    }
  }
}
</script>

<style lang="scss" scoped>
.navbar {
  height: 50px;
  overflow: hidden;
  position: relative;
  background: #fff;
  box-shadow: 0 1px 4px rgba(0, 21, 41, .08);

  .hamburger-container {
    line-height: 46px;
    height: 100%;
    float: left;
    cursor: pointer;
    transition: background .3s;
    -webkit-tap-highlight-color: transparent;

    &:hover {
      background: rgba(0, 0, 0, .025)
    }
  }

  .breadcrumb-container {
    float: left;
  }

  .topmenu-container {
    position: absolute;
    left: 50px;
  }

  .errLog-container {
    display: inline-block;
    vertical-align: top;
  }

  .right-menu {
    float: right;
    height: 100%;
    line-height: 50px;

    &:focus {
      outline: none;
    }

    .search-wrapper {
      display: inline-flex;
      align-items: center;
      height: 100%;
      vertical-align: top;
    }

    .right-menu-item {
      display: inline-block;
      padding: 0 8px;
      height: 100%;
      font-size: 18px;
      color: #5a5e66;
      vertical-align: text-bottom;

      &.hover-effect {
        cursor: pointer;
        transition: background .3s;

        &:hover {
          background: rgba(0, 0, 0, .025)
        }
      }
    }

    .chat-icon {
      position: relative;
      display: inline-block;
      line-height: 1;
    }

    .chat-unread {
      position: absolute;
      top: -7px;
      left: 11px;
      min-width: 16px;
      height: 16px;
      padding: 0 4px;
      box-sizing: border-box;
      border-radius: 8px;
      border: 1px solid #fff;
      background: #f56c6c;
      color: #fff;
      font-size: 10px;
      line-height: 14px;
      text-align: center;
      white-space: nowrap;
    }

    .avatar-container {
      margin-right: 30px;

      .avatar-wrapper {
        margin-top: 5px;
        position: relative;

        .user-avatar {
          cursor: pointer;
          width: 40px;
          height: 40px;
          border-radius: 10px;
        }

        .el-icon-caret-bottom {
          cursor: pointer;
          position: absolute;
          right: -20px;
          top: 25px;
          font-size: 12px;
        }
      }
    }
  }
}
</style>
