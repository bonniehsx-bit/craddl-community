<template>
  <!--
    「我的资料」抽屉。

    原来点它是 $router.push('/user/profile') 整页跳转 —— 改资料这种事看一眼就走,
    跳走会顶掉当前正在看的页面(还会多一个页签),回来又得重新找。抽屉更贴合这个动作。

    内容直接复用原页面的 userAvatar / userInfo,不另写一套表单,
    免得两边校验规则和提交逻辑各自演化。原页面路由保留不动:
    菜单搜索、直接输 URL 进来的场景还走得通(它那边仍是三件套,含 resetPwd)。

    修改密码不在这里 —— 已拆成左下角菜单的独立弹窗 PasswordDialog,见下面的说明。

    布局改成单列 —— 原页面是 6/18 两列,塞进 520px 的抽屉会挤成一团。
    正因为是单列,子组件自带的按钮会上下叠在一起,所以头像传 auto-save(即选即存、
    藏掉自己的保存按钮)、userInfo 传 show-close=false,最终整个抽屉只留一个保存按钮。
  -->
  <el-drawer
    :visible.sync="visible"
    :with-header="false"
    size="520px"
    direction="rtl"
    append-to-body
    @open="onOpen">
    <div class="profile-drawer" v-loading="loading">
      <div class="pd-head">
        <span class="pd-title">我的资料</span>
        <i class="el-icon-close pd-close" @click="visible = false"></i>
      </div>

      <div class="pd-body">
        <div class="pd-avatar">
          <!--
            auto-save:选完图立即保存,并隐去组件自带的「保存」按钮。
            抽屉是单列布局,头像的保存按钮会和下面「基本资料」里的保存按钮上下叠在一起,
            两个「保存」挨着并不知道各自管什么。统一成:头像即选即存,底下那个只管表单。
          -->
          <user-avatar :user="user" auto-save />
          <div class="pd-name">{{ user.nickname || user.username }}</div>
          <div v-if="roleText" class="pd-role">{{ roleText }}</div>
        </div>

        <ul class="pd-list">
          <li><span class="k">用户名称</span><span class="v">{{ user.username || '—' }}</span></li>
          <li><span class="k">所属角色</span><span class="v">{{ roleText || '—' }}</span></li>
          <li>
            <span class="k">前台真身 UID</span>
            <span class="v">{{ user.bind_uid ? user.bind_uid : '未绑定' }}</span>
          </li>
          <li>
            <span class="k">客服状态</span>
            <span class="v">
              <el-tag v-if="user.is_kefu === 1" type="success" size="mini">🈺 营业中</el-tag>
              <el-tag v-else type="info" size="mini">💤 休息</el-tag>
            </span>
          </li>
          <li><span class="k">创建日期</span><span class="v">{{ user.created_time || '—' }}</span></li>
        </ul>

        <!--
          原先这里是 el-tabs(基本资料 / 修改密码)。修改密码已拆成左下角菜单里的独立弹窗
          (PasswordDialog) —— 它是目的明确的安全操作,不该埋在「菜单 → 抽屉 → 切 tab」三层下面;
          而且两个 tab 提交的是完全不同的东西、各有各的按钮,挤在同一块区域反而让人不知道点哪个。
          只剩一个 pane 的 el-tabs 没有存在意义,直接摊平:抽屉自上而下一条线,只有一个保存按钮。
        -->
        <div class="pd-form">
          <user-info :user="user" :show-close="false" />
        </div>
      </div>
    </div>
  </el-drawer>
</template>

<script>
import userAvatar from '@/views/system/user/profile/userAvatar'
import userInfo from '@/views/system/user/profile/userInfo'
import { getUserProfile } from '@/api/system/user'

export default {
  name: 'ProfileDrawer',
  components: { userAvatar, userInfo },
  data() {
    return {
      visible: false,
      loading: false,
      user: {},
      roles: {}
    }
  },
  computed: {
    // 接口这个字段历史上给过数组也给过字符串,直接渲染会出现 [object Object]
    roleText() {
      const r = this.roles
      if (!r) return ''
      if (Array.isArray(r)) return r.join('、')
      if (typeof r === 'object') return Object.values(r).join('、')
      return String(r)
    }
  },
  methods: {
    show() {
      this.visible = true
    },
    // 每次打开都重新拉 —— 改完资料再打开要看到新值,缓存住反而误导
    onOpen() {
      this.loading = true
      getUserProfile().then(res => {
        this.user = res.user || {}
        this.roles = res.roles
      }).finally(() => {
        this.loading = false
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.profile-drawer {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.pd-head {
  flex: none;
  height: 52px;
  padding: 0 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid #ebeef5;

  .pd-title {
    font-size: 15px;
    font-weight: 600;
    color: #303133;
  }

  .pd-close {
    font-size: 18px;
    color: #909399;
    cursor: pointer;

    &:hover {
      color: #303133;
    }
  }
}

.pd-body {
  flex: 1;
  min-height: 0;          // 同侧边栏那处:不加 flex 子项不收缩,内容一多就撑破、滚动失效
  overflow-y: auto;
  padding: 20px;
}

.pd-avatar {
  text-align: center;
  padding-bottom: 16px;

  .pd-name {
    margin-top: 10px;
    font-size: 15px;
    font-weight: 600;
    color: #303133;
  }

  .pd-role {
    margin-top: 3px;
    font-size: 12px;
    color: #909399;
  }
}

.pd-list {
  list-style: none;
  margin: 0 0 8px;
  padding: 0;
  border-top: 1px solid #ebeef5;

  li {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 2px;
    border-bottom: 1px solid #f2f6fc;
    font-size: 13px;
  }

  .k {
    color: #909399;
    flex: none;
  }

  .v {
    color: #303133;
    text-align: right;
    margin-left: 16px;
    word-break: break-all;
  }
}

.pd-form {
  margin-top: 16px;
}
</style>
