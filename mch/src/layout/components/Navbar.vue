<template>
  <div class="navbar">
    <hamburger :is-active="sidebar.opened" class="hamburger-container" @toggleClick="toggleSideBar" />

    <breadcrumb class="breadcrumb-container" />

    <div class="right-menu">

      <!-- [核心新增] 店铺切换组件 -->
      <!-- 只有当商户列表数量大于0时才显示 -->
      <el-dropdown
        class="right-menu-item hover-effect mch-selector"
        trigger="click"
        v-if="mchList && mchList.length > 0"
        @command="handleMchSwitch"
      >
        <div class="mch-avatar-wrapper">
          <!-- 显示当前店铺头像 -->
          <img :src="currentMch.logo" class="mch-logo" v-if="currentMch && currentMch.logo">
          <!-- 显示当前店铺名称 -->
          <span class="mch-name">{{ currentMch ? currentMch.name : '选择店铺' }}</span>
          <i class="el-icon-caret-bottom" />
        </div>

        <el-dropdown-menu slot="dropdown">
          <el-dropdown-item
            v-for="item in mchList"
            :key="item.id"
            :command="item"
            :disabled="currentMch && currentMch.id === item.id"
          >
            <div style="display: flex; align-items: center; justify-content: space-between; min-width: 120px;">
              <span style="font-weight: bold;">{{ item.name }}</span>
              <!-- 根据类型显示不同颜色的标签 -->
              <el-tag size="mini" v-if="item.type===3" type="warning" effect="dark" style="margin-left:10px">酒店</el-tag>
              <el-tag size="mini" v-else-if="item.type===2" type="success" effect="dark" style="margin-left:10px">门店</el-tag>
              <el-tag size="mini" v-else type="info" style="margin-left:10px">供应商</el-tag>
            </div>
          </el-dropdown-item>
        </el-dropdown-menu>
      </el-dropdown>

      <!-- 用户头像下拉菜单 -->
      <el-dropdown class="avatar-container right-menu-item hover-effect" trigger="click">
        <div class="avatar-wrapper">
          <img :src="avatar+'?imageView2/1/w/80/h/80'" class="user-avatar">
          <i class="el-icon-caret-bottom" />
        </div>
        <el-dropdown-menu slot="dropdown" class="user-dropdown">
          <router-link to="/">
            <el-dropdown-item>
              首页
            </el-dropdown-item>
          </router-link>
          <!-- 也可以在这里加一个入口跳转到店铺设置 -->
          <router-link to="/store/setting" v-if="currentMch">
            <el-dropdown-item>
              店铺设置
            </el-dropdown-item>
          </router-link>
          <el-dropdown-item divided @click.native="logout">
            <span style="display:block;">退出登录</span>
          </el-dropdown-item>
        </el-dropdown-menu>
      </el-dropdown>
    </div>
  </div>
</template>

<script>
import { mapGetters } from 'vuex'
import Breadcrumb from '@/components/Breadcrumb/index.vue'
import Hamburger from '@/components/Hamburger/index.vue'

export default {
  components: {
    Breadcrumb,
    Hamburger
  },
  computed: {
    ...mapGetters([
      'sidebar',
      'avatar',
      // [注意] 请确保 src/store/getters.js 中已经映射了这两个字段
      'mchList',    // state.user.mchList
      'currentMch'  // state.user.currentMch
    ])
  },
  methods: {
    toggleSideBar() {
      this.$store.dispatch('app/toggleSideBar')
    },
    async logout() {
      await this.$store.dispatch('user/logout')
      this.$router.push(`/login?redirect=${this.$route.fullPath}`)
    },
    // [核心新增] 切换店铺逻辑
    handleMchSwitch(item) {
      // 如果点击的是当前店铺，不做处理
      if (this.currentMch && this.currentMch.id === item.id) return

      this.$confirm(`确定切换到 “${item.name}” 吗？`, '切换店铺', {
        confirmButtonText: '切换',
        cancelButtonText: '取消',
        type: 'info'
      }).then(() => {
        // 调用 Vuex 切换 Action (会刷新页面)
        this.$store.dispatch('user/switchMch', item)
      }).catch(() => {})
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

  .right-menu {
    float: right;
    height: 100%;
    line-height: 50px;

    &:focus {
      outline: none;
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

    /* [核心新增] 店铺选择器样式 */
    .mch-selector {
      margin-right: 10px;

      .mch-avatar-wrapper {
        display: flex;
        align-items: center;
        height: 100%; /* 继承 navbar 高度 */

        .mch-logo {
          width: 24px;
          height: 24px;
          border-radius: 4px;
          margin-right: 6px;
          border: 1px solid #f0f0f0;
          object-fit: cover;
        }
        .mch-name {
          font-size: 14px;
          font-weight: 600;
          margin-right: 4px;
          max-width: 120px;
          overflow: hidden;
          white-space: nowrap;
          text-overflow: ellipsis;
          color: #333;
        }
        .el-icon-caret-bottom {
          font-size: 12px;
        }
      }
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
