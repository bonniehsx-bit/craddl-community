<template>
  <el-breadcrumb class="app-breadcrumb" separator="/">
    <transition-group name="breadcrumb">
      <el-breadcrumb-item v-for="(item,index) in levelList" :key="item.path">
        <span v-if="item.redirect === 'noRedirect' || index == levelList.length - 1" class="no-redirect">{{ item.meta.title }}</span>
        <a v-else @click.prevent="handleLink(item)">{{ item.meta.title }}</a>
      </el-breadcrumb-item>
    </transition-group>
  </el-breadcrumb>
</template>

<script>
export default {
  data() {
    return {
      levelList: null
    }
  },
  watch: {
    $route(route) {
      // if you go to the redirect page, do not update the breadcrumbs
      if (route.path.startsWith('/redirect/')) {
        return
      }
      this.getBreadcrumb()
    }
  },
  created() {
    this.getBreadcrumb()
  },
  methods: {
    getBreadcrumb() {
      // only show routes with meta.title
      let matched = this.$route.matched.filter(item => item.meta && item.meta.title)
      const first = matched[0]

      if (!this.isDashboard(first)) {
        matched = [{ path: this.$store.state.permission.defaultHome || '/index', meta: { title: '首页' }}].concat(matched)
      }

      this.levelList = matched
        .filter(item => item.meta && item.meta.title && item.meta.breadcrumb !== false)
        .reduceRight((acc, item) => {
          // 按 path 去重，同路径只保留最深层的那条（即实际页面而非 Layout 壳）
          if (!acc.some(a => a.path === item.path)) acc.unshift(item)
          return acc
        }, [])
    },
    isDashboard(route) {
      const defaultHome = this.$store.state.permission.defaultHome || '/index';
      if (route && (route.path === defaultHome || route.path === '/index' || (route.name && route.name.trim() === 'Index'))) {
        return true
      }
      return false
    },
    handleLink(item) {
      const { redirect, path } = item
      if (redirect) {
        this.$router.push(redirect)
        return
      }
      this.$router.push(path)
    }
  }
}
</script>

<style lang="scss" scoped>
.app-breadcrumb.el-breadcrumb {
  display: inline-block;
  font-size: 14px;
  line-height: 50px;
  margin-left: 8px;

  .no-redirect {
    color: #97a8be;
    cursor: text;
  }
}
</style>
