<template>
  <div :class="['split-nav', `size-${size}`, { 'split-nav-collapse': isCollapse }]"
    :style="{ backgroundColor: splitBackgroundColor }">
    <logo v-if="showLogo" :collapse="true" />
    <div class="split-scroll-area" ref="scrollArea" @scroll="handleNavScroll">
      <el-menu :default-active="activeMenu" :collapse="isCollapse" :collapse-transition="false" mode="vertical"
        :background-color="splitBackgroundColor" :text-color="variables.menuColor" :active-text-color="activeColor"
        @select="handleSelect" class="split-nav-menu">
        <el-menu-item v-for="(item, index) in topMenus" :key="item.path || index" :index="item.path"
          class="split-menu-item"
          @mouseenter.native="handleMouseEnter(item.path)"
          @mouseleave.native="handleMouseLeave">
          <i v-if="item.meta.icon && item.meta.icon.indexOf('el-icon') > -1" :class="item.meta.icon" class="split-icon split-el-icon"></i>
          <svg-icon v-else :icon-class="item.meta.icon || 'component'" class="split-icon" />
          <span slot="title" class="split-title" :title="item.meta.title">{{ item.meta.title }}</span>
        </el-menu-item>
      </el-menu>
    </div>
    <!-- 账号区贴底。这一栏只有 122px(折叠 54px),放不下昵称+商城名,
         所以固定用紧凑态:只露头像,身份信息进下拉菜单头部。 -->
    <sidebar-account :collapse="true" :force-dark="true" />
  </div>
</template>

<script>
import { constantRoutes } from "@/router";
import { mapState } from "vuex";
import Logo from "../Sidebar/Logo";
import SidebarAccount from "../Sidebar/Account";
import variables from "@/styles/variables.scss";
import { menuActiveColor } from '@/utils/admin-theme';

export default {
  name: 'SplitNav',
  components: { Logo, SidebarAccount },
  data() {
    return {
      isFirst: false,                                                          // fix#3: isFrist → isFirst
      currentIndex: undefined,
      lastActivePlugin: sessionStorage.getItem('splitLastPlugin') || null,     // fix#4: 持久化到 sessionStorage
      hasMoreBelow: false,
      confirmedKey: null,
      hoverTimer: null,
    };
  },
  mounted() {                                                                  // fix#1: 合并两个 mounted
    this.$nextTick(() => this.checkScroll());
    window.addEventListener('resize', this.checkScroll);
    this.$store.dispatch('shortcut/initShortcuts');
  },
  beforeDestroy() {
    window.removeEventListener('resize', this.checkScroll);
  },
  computed: {
    activeColor() { return menuActiveColor(this.settings); },
    ...mapState(["settings"]),
    theme() {
      return this.$store.state.settings.theme;
    },
    showLogo() {
      return this.$store.state.settings.sidebarLogo;
    },
    size() {
      return this.$store.getters.size || 'medium';
    },
    isCollapse() {
      return !this.$store.state.app.sidebar.opened;
    },
    splitBackgroundColor() {
      // 强制锁定为深色底池（实现经典的“左深右白”跨主题混合布局）
      return '#04101A';
    },
    variables() {
      return variables;
    },
    topMenus() {
      let topMenus = [];
      // 动态注入虚拟模块：快捷菜单
      topMenus.push({
        path: '/shortcut',
        meta: { title: '我的常用', icon: 'star' },
        hidden: false
      });

      this.routers.map((menu) => {
        if (menu.hidden !== true) {
          if (menu.path === "/" || menu.path === "") {
            topMenus.push(menu.children[0]);
          } else {
            topMenus.push(menu);
          }
        }
      });
      return topMenus;
    },
    routers() {
      return this.$store.state.permission.topbarRouters;
    },
    shortcutList() {
      return this.$store.getters.shortcutList || [];
    },
    childrenMenus() {
      var childrenMenus = [];
      this.routers.map((router) => {
        for (var item in router.children) {
          if (router.children[item].parentPath === undefined) {
            const childPath = router.children[item].path;
            if (router.path === "/" || router.path === "") {
              if (childPath.startsWith('/')) {
                router.children[item].path = childPath;
              } else {
                router.children[item].path = "/" + childPath;
              }
            } else {
              if (!this.ishttp(childPath)) {
                if (childPath.startsWith('/')) {
                  router.children[item].path = childPath;
                } else {
                  router.children[item].path = router.path + "/" + childPath;
                }
              }
            }
            router.children[item].parentPath = router.path;
          }
          childrenMenus.push(router.children[item]);
        }
      });
      return constantRoutes.concat(childrenMenus);
    },
    // fix#2: 纯计算，不再包含任何副作用（commit / dispatch / this.xxx = 全部移走）
    activeMenu() {
      const path    = this.$route.path;
      const routers = this.routers;
      let activePath = path;

      if (this.$route.query && this.$route.query.from_shortcut) {
        activePath = '/shortcut';
      } else if (path === '/index' || path === '' || path === '/') {
        activePath = 'index';
      } else if (path.startsWith('/shortcut')) {
        activePath = '/shortcut';
      } else {
        let found = false;
        for (const router of routers) {
          if (this.isPathInSubTree(router, path)) {
            activePath = (router.path === '/' || router.path === '')
              ? router.children[0].path
              : router.path;
            found = true;
            break;
          }
        }
        if (!found) {
          if (!this.$route.children && path.lastIndexOf('/') <= 0) {
            activePath = path;
          } else if (path.lastIndexOf('/') > 0) {
            const tmp = path.substring(1);
            activePath = '/' + tmp.substring(0, tmp.indexOf('/'));
          }
        }
      }

      return activePath;
    },
  },
  // fix#2: 副作用统一在 watcher 里执行，避免 computed 触发循环更新
  watch: {
    activeMenu: {
      immediate: true,
      handler(activePath) {
        if (activePath === 'index' || activePath === '') {
          this.isFirst = true;                                               // fix#3
          this.$store.dispatch('app/toggleSideBarHide', true);
        }
        this.activeRoutes(activePath);
      }
    },
    // 页面内链接跳转（如从应用列表点击进入某个插件）时，
    // activeMenu 始终返回 '/plugins'（值不变），watcher 不触发。
    // 额外监听 $route.path，只要路径真正变化且仍在 /plugins 区间，就补调一次 activeRoutes。
    '$route.path'(newPath, oldPath) {
      if (newPath !== oldPath && this.activeMenu === '/plugins') {
        this.activeRoutes('/plugins');
      }
    }
  },
  methods: {
    isPathInSubTree(node, targetPath) {
      if (node.path === targetPath) return true;
      if (node.children && node.children.length > 0) {
        for (let i = 0; i < node.children.length; i++) {
          if (this.isPathInSubTree(node.children[i], targetPath)) {
            return true;
          }
        }
      }
      return false;
    },
    checkScroll() {
      const el = this.$refs.scrollArea;
      if (!el) return;
      this.hasMoreBelow = el.scrollHeight > el.clientHeight && (el.scrollTop + el.clientHeight < el.scrollHeight - 10);
    },
    handleNavScroll() {
      this.checkScroll();
    },
    handleMouseEnter(key) {
      clearTimeout(this.hoverTimer);
      this.hoverTimer = setTimeout(() => {
        this.activeRoutes(key);
      }, 50);
    },
    handleMouseLeave() {
      clearTimeout(this.hoverTimer);
      // 恢复：鼠标移开后回到真正确认的菜单
      this.hoverTimer = setTimeout(() => {
        if (this.confirmedKey) {
          this.activeRoutes(this.confirmedKey);
        } else {
          // 没有确认过的，用当前路由计算
          this.activeRoutes(this.activeMenu);
        }
      }, 200);
    },
    handleSelect(key, keyPath) {
      this.currentIndex = key;
      this.confirmedKey = key; // 标记为真正确认
      if (key === '/shortcut') {
        this.activeRoutes(key);
        // 为了体验好一点，如果不跳转的话，这里可以直接 return
        return;
      }
      const route = this.routers.find(item => item.path === key);
      if (this.ishttp(key)) {
        window.open(key, "_blank");
      } else if (!route || !route.children) {

        // 【核心大 Bug 修复】：防止 Router 误认相对路径导致原路被困！
        // 如果您在 /mall 模块里点击 index，它如果不带绝对斜杠，默认会跳转到 /mall/index！
        // 这就导致数据概览页永远打不开，而且永远滞留在 /mall 的栏目展示下。
        let targetPath = key;
        if (targetPath === 'index' || targetPath === '') {
          targetPath = '/index';
        } else if (!targetPath.startsWith('/')) {
          targetPath = '/' + targetPath;
        }

        this.$router.push({ path: targetPath }).catch(() => { });
        this.activeRoutes(key);
      } else {
        this.activeRoutes(key);
      }
    },
    activeRoutes(key) {
      if (key === '/shortcut') {
        const routes = this.shortcutList.map((item, index) => {
          return {
            path: item.path,
            hidden: false,
            meta: { title: item.title, icon: item.icon },
            name: 'ShortcutItem_' + index,
            // 注入时空印记，告诉框架：这是从捷径出发的，不要随意切换导航
            query: '{"from_shortcut": "1"}',
            parentPath: '/shortcut'
          }
        });

        // 注入“配置常用菜单”按钮
        routes.unshift({
          path: '/shortcut/config',
          hidden: false,
          meta: { title: '配置常用', icon: 'edit' },
          name: 'ShortcutConfigEntry',
          parentPath: '/shortcut'
        });

        this.$store.commit("SET_SIDEBAR_ROUTERS", routes);
        if (routes.length === 0) {
          this.$store.dispatch('app/toggleSideBarHide', true);
        } else {
          this.$store.dispatch('app/toggleSideBarHide', false);
        }
        return;
      }

      var routes = [];
      if (this.childrenMenus && this.childrenMenus.length > 0) {
        let candidateRoutes = [];
        this.childrenMenus.map((item) => {
          if (key == item.parentPath || (key == "index" && "" == item.path)) {
            candidateRoutes.push(item);
          }
        });

        // 【插件沉浸式沙盒模式】：路由级别拦截过滤
        if (key === '/plugins' || key === 'plugins') {
          const currentPath = this.$route.path; // 例如 /plugins/plaza/store/index
          const match = currentPath.match(/^\/plugins\/([^\/]+)/);

          if (match && match[1]) {
            let activePluginName = match[1]; // 比如 'plaza' 或者是 'index'

            if (activePluginName !== 'index') {
              // fix#4: 记录并持久化，刷新后恢复
              this.lastActivePlugin = activePluginName;
              sessionStorage.setItem('splitLastPlugin', activePluginName);
            } else if (this.lastActivePlugin) {
              // 用户仅仅是退回到了大厅 (index)，但之前有记录的业务上下文
              // 帮用户暂时保留之前的菜单沙盒！
              activePluginName = this.lastActivePlugin;
            }

            if (activePluginName === 'index') {
              // 1. 完全没有上下文时的大厅模式：物理隐藏侧边栏，视野全开！
              candidateRoutes = [];
            } else {
              // 2. 沉浸工作台模式：根据最新的 activePluginName 进行截断
              const targetPath = '/plugins/' + activePluginName;
              candidateRoutes = candidateRoutes.filter(item => {
                // 保留应用列表(大门入口)，防止迷路
                const isPortal = item.path === 'index' || item.path === '/plugins/index';
                const isTargetPlugin = item.path === targetPath || item.path.startsWith(targetPath + '/');
                return isPortal || isTargetPlugin;
              });
            }
          }
        }

        routes = candidateRoutes;
      }

      this.$store.commit("SET_SIDEBAR_ROUTERS", routes);

      // 【核心逻辑】：如果一级菜单下辖的路由全是隐藏状态（hidden: true），
      // 导致二级菜单根本没东西可展示，我们就直接触发物理隐藏，腾出所有空间！
      const hasVisibleChild = routes.some(item => item.hidden !== true);

      if (!hasVisibleChild) {
        this.$store.dispatch('app/toggleSideBarHide', true);
      } else {
        this.$store.dispatch('app/toggleSideBarHide', false);
      }
    },
    ishttp(url) {
      return url.indexOf('http://') !== -1 || url.indexOf('https://') !== -1
    }
  },
};
</script>

<style lang="scss" scoped>
.split-nav {
  position: fixed;
  top: 0;
  bottom: 0;
  left: 0;
  width: var(--split-nav-w, 122px);
  z-index: 1002;
  box-shadow: 2px 0 6px rgba(0, 21, 41, .05);
  transition: all 0.28s;
  display: flex;
  flex-direction: column;
  overflow: hidden;

  .split-scroll-area {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding-bottom: 80px;
    scrollbar-width: none;
    &::-webkit-scrollbar { display: none; }
  }

  .split-nav-menu {
    border-right: none !important;
    width: 100% !important;
  }

  .split-menu-item {
    text-align: left;
    color: inherit;
    transition: all 0.3s cubic-bezier(0.645, 0.045, 0.355, 1);
  }

  /* 悬浮与激活时的效果 */
  .split-menu-item.is-active,
  .split-menu-item:hover {
    background-color: rgba(255, 255, 255, 0.05) !important;
  }

  .split-icon {
    vertical-align: middle;
  }

  .split-el-icon {
    font-size: 18px;
    margin-right: 6px;
    width: 1em;
    text-align: center;
    display: inline-block;
    color: inherit;
  }

  .split-title {
    vertical-align: middle;
  }

  /* 动态缩放控制梯队 - 增加垂直间距 */
  &.size-default {
    .split-menu-item {
      height: 50px !important;
      line-height: 50px !important;
      font-size: 14px !important;
      padding: 0 10px 0 22px !important;
      /* 加大左侧内边距 */
    }

    .split-icon {
      font-size: 15px;
      margin-right: 10px;
    }

    .split-title {
      font-size: 14px;
    }
  }

  &.size-medium {
    .split-menu-item {
      height: 46px !important;
      line-height: 46px !important;
      font-size: 13px !important;
      padding: 0 10px 0 20px !important;
      /* 加大左侧内边距 */
    }

    .split-icon {
      font-size: 14px;
      margin-right: 8px;
    }

    .split-title {
      font-size: 13px;
    }
  }

  &.size-small {
    .split-menu-item {
      height: 44px !important;
      line-height: 44px !important;
      font-size: 13px !important;
      padding: 0 8px 0 18px !important;
      /* 加大左侧内边距 */
    }

    .split-icon {
      font-size: 13px;
      margin-right: 6px;
    }

    .split-title {
      font-size: 13px;
    }
  }

  &.size-mini {
    .split-menu-item {
      height: 42px !important;
      line-height: 42px !important;
      font-size: 12px !important;
      padding: 0 6px 0 14px !important;
      /* 加大左侧内边距 */
      transform: scale(0.95);
      transform-origin: left center;
    }

    .split-icon {
      font-size: 12px;
      margin-right: 4px;
    }

    .split-title {
      font-size: 12px;
    }
  }

  /* 消除子组件 Logo 私自携带的原生背景色，使其完全融于大侧栏，并彻底斩草除根任何文字以免溢出 */
  ::v-deep .sidebar-logo-container {
    background: transparent !important;

    .sidebar-title {
      display: none !important;
    }
  }
}

.split-nav-collapse {
  width: 54px !important;

  .split-menu-item {
    padding: 0 !important;
    text-align: center;
  }

  .split-icon {
    margin-right: 0 !important;
  }
}
</style>
