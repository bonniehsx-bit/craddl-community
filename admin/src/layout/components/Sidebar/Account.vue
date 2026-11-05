<template>
  <!--
    侧边栏底部的账号区。

    分工:
      · 触发器(常驻可见)—— 一行:小头像 + 当前账号名 + 箭头。
        ⚠ 2026-08-19 按要求去掉了常驻的商城名(原来是昵称下面一行小字)。
        商城名现在只在两个地方能看到:展开菜单的第一行、以及折叠态的 tooltip。
        这等于把「当前在哪个商城」从常驻降级为点开可见 —— 下面第 2 条讲的
        跨商城误操作风险因此回来了一部分,是知情取舍,不是漏掉了。
      · 菜单第一行 —— 当前商城的 logo + 名称,点开才看到,但看得清楚。
        紧接着就是「切换商城」和「退出登录」,动手前需要这一眼确认。
        商城 logo 只出现在菜单里、头像只出现在触发器上,两个图形元素各占一处,
        堆在一起会显得很乱(试过,不好看)。

    为什么整块放左下角而不是顶栏右上角:
      · 身份与上下文和导航同侧更自然,顶栏留给当前页面的操作。
      · 当前商城的可见性 —— 平台管理员能跨商城操作,看不见就容易在 A 商城
        做了本该给 B 商城做的事。所以菜单第一行仍然把商城 logo + 名称放在最显眼处,
        动手切换/退出前必然扫到。(触发器上的常驻显示已按要求移除,见上。)
      · VS Code / Slack / Linear / 飞书都是左下角账号,用户已有肌肉记忆。

    顶栏仍保留一个头像入口作为移动端兜底 —— 手机上侧边栏是收起的抽屉,
    入口全搬走的话退出登录就得先点汉堡菜单才能摸到。
  -->
  <div class="sidebar-account" :class="{ 'is-collapse': collapse, 'theme-dark': isDark }">
    <el-dropdown trigger="click" placement="top-start" @command="onCommand">
      <!-- 折叠后只剩一个头像,靠 tooltip 才知道是谁、在哪;展开态信息就在旁边,关掉它 -->
      <el-tooltip :disabled="!collapse" effect="dark" placement="right" :content="tipText">
        <div class="account-trigger">
          <span class="avatar-box">
            <img :src="avatar" class="account-avatar" alt="">
            <!-- 日志追踪从顶栏搬进菜单后就藏起来了,出错时得有个不点开也能看见的信号 -->
            <i v-if="errorLogCount > 0 || refundAttention > 0" class="err-dot"></i>
          </span>
          <div v-if="!collapse" class="account-info">
            <div class="account-name" :title="displayName">{{ displayName }}</div>
          </div>
          <!-- 用 chevron 而不是「⋯」：三点在通用语义里是"更多操作"，
               而这块点开是个身份/上下文面板。箭头朝上，与菜单实际弹出方向一致 -->
          <i v-if="!collapse" class="el-icon-arrow-up account-arrow"></i>
        </div>
      </el-tooltip>

      <el-dropdown-menu slot="dropdown">
        <!-- 菜单第一行:当前商城(logo + 名称)。
             下面紧跟着「切换商城」和「退出登录」,动手前得一眼确认是哪个。
             触发器那边放的是个人信息,这里放商城 —— 各占一处,不会两个图标堆在一起。 -->
        <div class="dropdown-head">
          <img v-if="mallLogo" :src="mallLogo" class="dh-logo" alt="">
          <i v-else class="el-icon-office-building dh-logo-ph"></i>
          <span class="dh-mall-name">{{ mallName || '未进入商城' }}</span>
        </div>
        <!--
          菜单项统一成「图标 + 四字文案」:图标固定宽度撑出一列,文字自然左对齐成一条竖线。
          少一个图标或多两个字,视觉上就会有一行凸出来 —— 所以文案一律压到四个字
          (「刷新配置缓存」→「刷新缓存」,「错误监控」→「日志追踪」)。
        -->
        <el-dropdown-item command="profile">
          <span class="mi"><i class="el-icon-user"></i><em>我的资料</em></span>
        </el-dropdown-item>
        <!-- 紧跟「我的资料」:两项都是账号自身的事,放一起找得到。
             原先它是抽屉里的一个 tab,改密码这种目的明确的安全操作不该埋三层 -->
        <el-dropdown-item command="password">
          <span class="mi"><i class="el-icon-lock"></i><em>修改密码</em></span>
        </el-dropdown-item>
        <el-dropdown-item command="errorLog">
          <span class="mi">
            <i class="el-icon-warning-outline"></i><em>日志追踪</em>
            <!-- 从顶栏搬下来后角标不能丢:有错误时这里要能一眼看见,否则等于把监控藏没了 -->
            <b v-if="errorLogCount > 0" class="mi-badge">{{ errorLogCount > 99 ? '99+' : errorLogCount }}</b>
          </span>
        </el-dropdown-item>
        <!-- 退款失败且自动重试已用完 / 处理中没结果 / 后续没做完,只能靠人处理;有才出现,点了直接去处理台 -->
        <el-dropdown-item v-if="refundAttention > 0" command="refundAttention">
          <span class="mi">
            <i class="el-icon-money"></i><em>退款待处理</em>
            <b class="mi-badge">{{ refundAttention > 99 ? '99+' : refundAttention }}</b>
          </span>
        </el-dropdown-item>
        <el-dropdown-item command="setting">
          <span class="mi"><i class="el-icon-set-up"></i><em>布局设置</em></span>
        </el-dropdown-item>
        <el-dropdown-item v-if="adminType === 0" command="refreshCache" divided>
          <span class="mi">
            <i :class="refreshingCache ? 'el-icon-loading' : 'el-icon-refresh'"></i><em>刷新缓存</em>
          </span>
        </el-dropdown-item>
        <!-- 分隔线画在这一组的第一项上:超管看得到「刷新缓存」,线由它画;
             非超管没有那一项,就得由「切换商城」自己画,否则会和上面的「布局设置」连成一片 -->
        <!-- 文案按角色分:超管去 entry 页是做「商城管理」(新增商城、调授权、看到期),
             切换只是顺带;非超管过去就只能切换,叫「切换商城」才准确。
             全站只有一个商城时超管**照样**要能进去(去开第二个),所以后端 canSwitchMall
             对超管恒为 true —— 别再按商城数量卡它 -->
        <el-dropdown-item v-if="mallSwitchable" command="switchMall" :divided="adminType !== 0">
          <span class="mi">
            <i class="el-icon-office-building"></i><em>{{ adminType === 0 ? '商城管理' : '切换商城' }}</em>
          </span>
        </el-dropdown-item>
        <el-dropdown-item command="logout" divided>
          <span class="mi"><i class="el-icon-switch-button"></i><em>退出登录</em></span>
        </el-dropdown-item>
      </el-dropdown-menu>
    </el-dropdown>

    <!-- 抽屉跟着入口一起搬下来,不必留在 Navbar 里靠事件总线遥控 -->
    <error-log-drawer ref="errorLogDrawer" @count-change="fetchErrorCount" />
    <profile-drawer ref="profileDrawer" />
    <password-dialog ref="passwordDialog" />
  </div>
</template>

<script>
import { mapGetters, mapState } from 'vuex'
import { removeMallId } from '@/utils/auth'
import ErrorLogDrawer from '../ErrorLogDrawer'
import ProfileDrawer from './ProfileDrawer'
import PasswordDialog from './PasswordDialog'
import variables from '@/styles/variables.scss'

export default {
  name: 'SidebarAccount',
  components: { ErrorLogDrawer, ProfileDrawer, PasswordDialog },
  data() {
    return { refreshingCache: false, refundAttention: 0 }
  },
  props: {
    collapse: { type: Boolean, default: false },
    // SplitNav 的底色是写死的深色(#04101A),不跟 sideTheme 走。
    // 不给它强制深色的话,用户一旦把侧边栏设成浅色主题,这里就是深色字配深色底 —— 全看不见。
    forceDark: { type: Boolean, default: false }
  },
  computed: {
    ...mapGetters(['avatar', 'name', 'mall_name']),
    ...mapState({
      nickname: state => state.user.nickname,
      adminType: state => state.user.admin_type,
      // 判据是「能进的商城多于一个」而不是「是不是超管」——
      // 名下管着多个商城的商户主账号后端本来就放行,原来卡在 admin_type === 0 上,
      // 看不到入口只能手动敲 /mall/entry 的 URL。
      mallSwitchable: state => state.user.mall_switchable,
      sideTheme: state => state.settings.sideTheme,
      errorLogCount: state => state.app.errorLogCount,
      // 商城 logo 早就存在 store 里(进商城时与商城名同一时机写入),之前没用上
      mallLogo: state => state.site.mallLogo
    }),
    isDark() {
      return this.forceDark || this.sideTheme === 'theme-dark'
    },
    // 昵称优先,没有就退回登录名,再没有给个兜底 —— 这块不能空着
    displayName() {
      return this.nickname || this.name || '管理员'
    },
    mallName() {
      return this.mall_name || ''
    },
    // 折叠态触发器只剩一个头像,昵称和商城都看不见,提示里两样都给
    tipText() {
      return this.mallName ? `${this.displayName} · ${this.mallName}` : this.displayName
    },
    variables() {
      return variables
    }
  },
  mounted() {
    // 轮询跟着入口一起搬过来。Sidebar 与 SplitNav 的账号区是互斥渲染的(v-if),
    // 全局只有一个实例,不会重复轮询。
    this.fetchErrorCount()
    this.fetchRefundAttention()
    this._errorPollTimer = setInterval(() => {
      this.fetchErrorCount()
      this.fetchRefundAttention()
    }, 60000)
  },
  beforeDestroy() {
    clearInterval(this._errorPollTimer)
  },
  methods: {
    async fetchErrorCount() {
      try {
        const res = await this.$request({ url: '/admin/yii-error-log/count' })
        if (res.code === 0) {
          this.$store.commit('app/SET_ERROR_LOG_COUNT', res.data.count)
        }
      } catch (_) {}
    },
    async fetchRefundAttention() {
      try {
        const res = await this.$request({ url: '/mall/finance/refund-attention' })
        if (res.code === 0) {
          this.refundAttention = Number(res.data.count) || 0
        }
      } catch (_) {}
    },
    onCommand(cmd) {
      switch (cmd) {
        case 'profile':
          // 改资料是「看一眼就走」的动作,整页跳转会顶掉当前页面、还多一个页签
          this.$refs.profileDrawer.show()
          break
        case 'password':
          this.$refs.passwordDialog.show()
          break
        case 'errorLog':
          this.$refs.errorLogDrawer.show()
          break
        case 'refundAttention':
          this.$router.push('/finance/refund-fail')
          break
        case 'setting':
          this.$store.dispatch('settings/changeSetting', { key: 'showSettings', value: true })
          break
        case 'refreshCache':
          this.refreshOptionCache()
          break
        case 'switchMall':
          this.switchMall()
          break
        case 'logout':
          this.logout()
          break
      }
    },
    // 从 Navbar 整段搬过来,行为保持一致(含二次确认与 loading 态)
    refreshOptionCache() {
      if (this.refreshingCache) return
      this.$confirm('将从数据库重新读取所有配置并写入 Redis 缓存，确认刷新？', '刷新配置缓存', {
        confirmButtonText: '刷新', cancelButtonText: '取消', type: 'info'
      }).then(() => {
        this.refreshingCache = true
        this.$request({ url: '/admin/site/refresh-option-cache', method: 'post' }).then(res => {
          if (res.code === 0) {
            this.$notify.success({ title: '刷新成功', message: res.msg, duration: 3000 })
          } else {
            this.$modal.msgError(res.msg || '刷新失败')
          }
        }).catch(() => {
          this.$modal.msgError('网络错误')
        }).finally(() => {
          this.refreshingCache = false
        })
      }).catch(() => {})
    },
    switchMall() {
      this.$confirm('确定要返回商城选择列表吗？', '提示', {
        confirmButtonText: '确定', cancelButtonText: '取消', type: 'info'
      }).then(() => {
        removeMallId()
        this.$store.dispatch('im/closeSocket')
        // 用 location.href 强制刷新,确保权限路由/菜单等 Vuex 缓存被彻底清空(与原顶栏实现一致)
        window.location.href = '/admin/#/mall/entry'
      }).catch(() => {})
    },
    logout() {
      this.$confirm('确定注销并退出系统吗？', '提示', {
        confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning'
      }).then(() => {
        this.$store.dispatch('LogOut').then(() => {
          window.location.href = '/admin'
        })
      }).catch(() => {})
    }
  }
}
</script>

<style lang="scss" scoped>
@import "~@/styles/variables.scss";

.sidebar-account {
  flex: none;                       // 关键:不参与 flex 伸缩,始终贴底
  border-top: 1px solid rgba(255, 255, 255, .08);
  padding: 8px 10px;
  box-sizing: border-box;

  ::v-deep .el-dropdown {
    display: block;
    width: 100%;
  }

  .account-trigger {
    display: flex;
    align-items: center;
    padding: 6px 8px;
    border-radius: 6px;
    cursor: pointer;
    transition: background-color .2s;

    &:hover {
      background-color: rgba(255, 255, 255, .08);
    }
  }

  .avatar-box {
    position: relative;
    flex: none;
    line-height: 0;
  }

  .account-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: block;
    object-fit: cover;
    background: rgba(255, 255, 255, .15);
  }

  // 错误红点:描边跟侧边栏底色一致,贴在头像边缘才不糊成一团
  .err-dot {
    position: absolute;
    top: -1px;
    right: -1px;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #F56C6C;
    border: 2px solid #304156;
  }

  .account-info {
    flex: 1;
    min-width: 0;                   // 不加这个,长昵称会把整行撑破而不是省略
    margin-left: 9px;
    line-height: 1.4;
  }

  .account-name {
    font-size: 13px;
    font-weight: 500;   // 与下一行拉开主次，不只靠颜色
    color: #fff;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  // 商城名做成次要信息:看得见,但不跟昵称抢注意力


  .account-arrow {
    flex: none;
    font-size: 14px;
    color: rgba(255, 255, 255, .45);
    margin-left: 4px;
  }

  // 折叠态只剩 54px,头像居中,信息挪进下拉菜单头部
  &.is-collapse {
    padding: 8px 0;

    .account-trigger {
      justify-content: center;
      padding: 6px 0;
    }
  }

  // 浅色侧边栏:白字在白底上看不见,整套颜色要翻过来
  &:not(.theme-dark) {
    border-top-color: rgba(0, 0, 0, .08);

    .account-trigger:hover {
      background-color: rgba(0, 0, 0, .05);
    }
    .account-name {
      color: #303133;
    }
    .account-arrow {
      color: #c0c4cc;
    }
    // 浅色侧边栏下红点的描边也要跟着换,否则会有一圈突兀的深色边
    .err-dot {
      border-color: #fff;
    }
  }
}

// 菜单项:图标占一列固定宽度,文字从同一条竖线起排 —— 不靠空格凑对齐
.mi {
  display: flex;
  align-items: center;

  > i {
    width: 16px;              // 固定宽度是对齐的关键:图标本身宽窄不一,不定宽文字就会参差
    margin-right: 9px;
    font-size: 15px;
    text-align: center;
    flex: none;
  }

  > em {
    font-style: normal;
    flex: 1;
  }
}

// 数量角标贴在行尾,不参与文字对齐
.mi-badge {
  flex: none;
  margin-left: 12px;
  min-width: 17px;
  height: 16px;
  padding: 0 5px;
  border-radius: 8px;
  background: #F56C6C;
  color: #fff;
  font-size: 11px;
  font-weight: 400;
  line-height: 16px;
  text-align: center;
  box-sizing: border-box;
}

.dropdown-head {
  display: flex;
  align-items: center;
  padding: 10px 14px 9px;
  border-bottom: 1px solid #ebeef5;
  margin-bottom: 4px;
  min-width: 176px;          // 菜单项都是四个字,不给下限的话商城名会被挤到省略

  .dh-logo {
    width: 20px;
    height: 20px;
    border-radius: 4px;      // 圆角方形是"组织/场所"的语义,圆形留给"人"
    object-fit: cover;
    flex: none;
    background: #f0f2f5;
  }

  // 没配 logo 的商城不少,占位图标保证这一行不会时高时矮
  .dh-logo-ph {
    width: 20px;
    font-size: 15px;
    line-height: 20px;
    color: #b0b6bf;
    flex: none;
    text-align: center;
  }

  .dh-mall-name {
    margin-left: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #303133;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}
</style>
