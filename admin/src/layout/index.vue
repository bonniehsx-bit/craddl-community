<template>
  <div :class="[classObj, 'app-size-' + $store.getters.size]" class="app-wrapper" :style="{'--current-color': theme, '--split-nav-w': splitNavWidth + 'px', '--sidebar-w': sidebarWidth + 'px'}">
    <div v-if="device==='mobile'&&sidebar.opened" class="drawer-bg" @click="handleClickOutside"/>
    <split-nav v-if="splitMenu" />
    <sidebar v-if="!sidebar.hide" class="sidebar-container"/>
    <div :class="{hasTagsView:needTagsView,sidebarHide:sidebar.hide}" class="main-container">
      <div :class="{'fixed-header':fixedHeader}">
        <navbar/>
        <tags-view v-if="needTagsView"/>
      </div>
      <app-main/>
      <right-panel>
        <settings/>
      </right-panel>
    </div>
  </div>
</template>

<script>
import RightPanel from '@/components/RightPanel'
import {AppMain, Navbar, Settings, Sidebar, TagsView, SplitNav} from './components'
import ResizeMixin from './mixin/ResizeHandler'
import {mapState} from 'vuex'
import variables from '@/styles/variables.scss'

export default {
  name: 'Layout',
  components: {
    AppMain,
    Navbar,
    RightPanel,
    Settings,
    Sidebar,
    TagsView,
    SplitNav
  },
  mixins: [ResizeMixin],
  computed: {
    ...mapState({
      theme: state => state.settings.theme,
      sideTheme: state => state.settings.sideTheme,
      sidebar: state => state.app.sidebar,
      device: state => state.app.device,
      needTagsView: state => state.settings.tagsView,
      fixedHeader: state => state.settings.fixedHeader,
      splitMenu: state => state.settings.splitMenu
    }),
    classObj() {
      return {
        hideSidebar: !this.sidebar.opened,
        openSidebar: this.sidebar.opened,
        withoutAnimation: this.sidebar.withoutAnimation,
        mobile: this.device === 'mobile',
        hasSplitMenu: this.splitMenu
      }
    },
    // 根据 size 决定左侧导航栏（SplitNav）的宽度，注入为 CSS 变量 --split-nav-w
    // 注：宽度需容纳 5-6 个汉字的菜单名（旧值 122 仅够 4 字，第 5 字起被省略号截断）。
    // 二级栏定位 / 正文 margin / 正文 width 均按此变量计算（见 styles/sidebar.scss），加宽全局自动避让。
    splitNavWidth() {
      const s = this.$store.getters.size || 'medium'
      if (s === 'mini')  return 99
      if (s === 'small') return 120
      return 130
    },
    // 根据 size 决定右侧二级菜单（Sidebar）的宽度，注入为 CSS 变量 --sidebar-w
    sidebarWidth() {
      const s = this.$store.getters.size || 'medium'
      if (s === 'mini')  return 110
      if (s === 'small') return 126
      return 136
    },
    variables() {
      return variables;
    }
  },
  methods: {
    handleClickOutside() {
      this.$store.dispatch('app/closeSideBar', {withoutAnimation: false})
    }
  }
}
</script>

<style lang="scss" scoped>
@import "~@/styles/mixin.scss";
@import "~@/styles/variables.scss";

.app-wrapper {
  @include clearfix;
  position: relative;
  height: 100%;
  width: 100%;

  &.mobile.openSidebar {
    position: fixed;
    top: 0;
  }
}

.drawer-bg {
  background: #000;
  opacity: 0.3;
  width: 100%;
  top: 0;
  height: 100%;
  position: absolute;
  z-index: 999;
}

.fixed-header {
  position: fixed;
  top: 0;
  right: 0;
  z-index: 1000;
  width: calc(100% - #{$base-sidebar-width});
  transition: width 0.28s;
}

.hideSidebar .fixed-header {
  width: calc(100% - 54px);
}

// splitMenu 模式的 fixed-header 宽度由 sidebar.scss 中的 !important 规则控制
// （使用 CSS 变量 --split-nav-w，随 size 动态变化）

.mobile .fixed-header {
  width: 100%;
}
</style>
