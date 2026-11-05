<template>
  <div class="sidebar-logo-container" :class="{'collapse':collapse}"
       :style="{ backgroundColor: sideTheme === 'theme-dark' ? variables.menuBackground : variables.menuLightBackground }">
    <transition name="sidebarLogoFade">
        <router-link v-if="collapse" key="collapse" class="sidebar-logo-link" :to="defaultHome">
          <img v-if="site_logo||logo" :src="site_logo||logo" class="sidebar-logo"/>
          <h1 v-else class="sidebar-title"
              :style="{ color: sideTheme === 'theme-dark' ? variables.logoTitleColor : variables.logoLightTitleColor }">
            {{ site_name ? site_name : title }} </h1>
        </router-link>
      <router-link v-else key="expand" class="sidebar-logo-link" :to="defaultHome">
        <img v-if="site_logo||logo" :src="site_logo||logo" class="sidebar-logo"/>
        <h1 class="sidebar-title"
            :style="{ color: sideTheme === 'theme-dark' ? variables.logoTitleColor : variables.logoLightTitleColor }">
          {{ site_name ? site_name : title }} </h1>
      </router-link>
    </transition>
  </div>
</template>

<script>
import logoImg from '@/assets/logo/login_logo.png'
import variables from '@/styles/variables.scss'

export default {
  name: 'SidebarLogo',
  props: {
    collapse: {
      type: Boolean,
      required: true
    }
  },
  computed: {
    variables() {
      return variables
    },
    site_name() {
      if (!this.$store.state.site.setting) {
        return ""
      }
      return this.$store.state.site.setting.name
    },
    site_logo() {
      // 优先用当前商城 logo，其次平台 header_pic，都没有则用本地静态图（logo data 里兜底）
      return this.$store.state.site.mallLogo
        || (this.$store.state.site.setting && this.$store.state.site.setting.header_pic)
        || ''
    },
    sideTheme() {
      return this.$store.state.settings.sideTheme
    },
    defaultHome() {
      return this.$store.state.permission.defaultHome || '/index'
    }
  },
  data() {
    return {
      title: 'Craddl',
      logo: logoImg
    }
  }
}
</script>

<style lang="scss" scoped>
.sidebarLogoFade-enter-active {
  transition: opacity 1.5s;
}

.sidebarLogoFade-enter,
.sidebarLogoFade-leave-to {
  opacity: 0;
}

.sidebar-logo-container {
  position: relative;
  width: 100%;
  height: 50px;
  line-height: 50px;
  background: #2b2f3a;
  text-align: center;
  overflow: hidden;

  & .sidebar-logo-link {
    height: 100%;
    width: 100%;

    & .sidebar-logo {
      width: 32px;
      height: 32px;
      object-fit: contain;
      border-radius: 6px;
      vertical-align: middle;
      margin-right: 12px;
    }

    & .sidebar-title {
      display: inline-block;
      margin: 0;
      color: #fff;
      font-weight: 600;
      line-height: 50px;
      font-size: 14px;
      font-family: Avenir, Helvetica Neue, Arial, Helvetica, sans-serif;
      vertical-align: middle;
    }
  }

  &.collapse {
    .sidebar-logo {
      margin-right: 0px;
    }
  }
}
</style>
