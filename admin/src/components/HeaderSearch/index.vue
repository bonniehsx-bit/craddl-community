<template>
  <div class="header-search visible-search">
    <svg-icon class-name="search-icon" icon-class="search" />
    <el-select
      ref="headerSearchSelect"
      v-model="search"
      :remote-method="querySearch"
      filterable
      default-first-option
      remote
      placeholder="快速搜索菜单..."
      class="header-search-select"
      @change="change"
      @focus="handleFocus"
    >
      <el-option v-for="option in options" :key="option.item.path" :value="option.item" :label="formatTitle(option.item.title)" />
    </el-select>
  </div>
</template>

<script>
// fuse is a lightweight fuzzy-search module
// make search results more in line with expectations
import Fuse from 'fuse.js/dist/fuse.min.js'
import path from 'path'

export default {
  name: 'HeaderSearch',
  data() {
    return {
      search: '',
      options: [],
      searchPool: [],
      fuse: undefined
    }
  },
  computed: {
    routes() {
      // 使用未被打平(unflattened)的 topbarRouters，这样所有的 ParentView (如插件名称层级) 都不会丢失！
      return this.$store.getters.topbarRouters || this.$store.getters.permission_routes
    }
  },
  watch: {
    routes() {
      this.searchPool = this.generateRoutes(this.routes)
    },
    searchPool(list) {
      this.initFuse(list)
    }
  },
  mounted() {
    this.searchPool = this.generateRoutes(this.routes)
  },
  methods: {
    // 监听聚焦时，自动把焦距给到 input 内部，同时强刷最新路由（防止被 sandbox 污染）
    handleFocus() {
      this.searchPool = this.generateRoutes(this.routes)
      this.initFuse(this.searchPool)
    },
    // 显示优化：剥离无意义的顶级前缀
    formatTitle(titleArray) {
      if (!titleArray || titleArray.length === 0) return '';
      // 如果层级大于1（意味着当前是子菜单），为了视觉清爽，过滤掉顶部的“应用管理”祖先名词
      if (titleArray.length > 1) {
        return titleArray.filter(t => t !== '应用管理' && t !== '主导航').join(' > ');
      }
      return titleArray.join(' > ');
    },
    change(val) {
      const path = val.path;
      if(this.ishttp(val.path)) {
        // http(s):// 路径新窗口打开
        const pindex = path.indexOf("http");
        window.open(path.substr(pindex, path.length), "_blank");
      } else {
        this.$router.push(val.path)
      }
      this.search = ''
      this.options = []
      this.$refs.headerSearchSelect && this.$refs.headerSearchSelect.blur()
    },
    initFuse(list) {
      this.fuse = new Fuse(list, {
        shouldSort: true,
        threshold: 0.4,
        location: 0,
        distance: 100,
        maxPatternLength: 32,
        minMatchCharLength: 1,
        keys: [{
          name: 'title',
          weight: 0.7
        }, {
          name: 'path',
          weight: 0.3
        }]
      })
    },
    // Filter out the routes that can be displayed in the sidebar
    // And generate the internationalized title
    generateRoutes(routes, basePath = '/', prefixTitle = []) {
      let res = []

      for (const router of routes) {
        // skip hidden router
        if (router.hidden) { continue }

        const data = {
          path: !this.ishttp(router.path) ? path.resolve(basePath, router.path) : router.path,
          title: [...prefixTitle]
        }

        if (router.meta && router.meta.title) {
          data.title = [...data.title, router.meta.title]

          // 强制取消 `redirect !== 'noRedirect'` 的拦截，保证所有的顶级应用(如："多商户")也能被搜索到。
          res.push(data)
        }

        // recursive child routes
        if (router.children) {
          const tempRoutes = this.generateRoutes(router.children, data.path, data.title)
          if (tempRoutes.length >= 1) {
            res = [...res, ...tempRoutes]
          }
        }
      }
      return res
    },
    querySearch(query) {
      if (query !== '') {
        this.options = this.fuse.search(query)
      } else {
        this.options = []
      }
    },
    ishttp(url) {
      return url.indexOf('http://') !== -1 || url.indexOf('https://') !== -1
    }
  }
}
</script>

<style lang="scss" scoped>
.visible-search {
  display: inline-flex;
  align-items: center;
  background: #f5f7fa;
  border-radius: 20px;
  padding: 0 16px;
  height: 32px;
  margin-right: 15px;
  border: 1px solid transparent;
  transition: all 0.3s cubic-bezier(0.645, 0.045, 0.355, 1);

  &:hover {
    border-color: #dcdfe6;
    background: #fff;
  }

  /* 聚焦状态的高级发光感 */
  &:focus-within {
    background: #fff;
    border-color: var(--admin-accent-text);
    box-shadow: 0 0 0 2px rgba(var(--admin-accent-rgb), 0.1);
  }

  .search-icon {
    color: #909399;
    font-size: 14px;
    margin-right: 8px;
    cursor: default;
  }

  .header-search-select {
    width: 200px;
    font-size: 13px;

    ::v-deep .el-input__inner {
      background: transparent !important;
      border: none;
      padding: 0;
      height: 32px;
      line-height: 32px;
      color: #606266;
      box-shadow: none !important;
      
      &::placeholder {
        color: #a8abb2;
      }
    }
    
    ::v-deep .el-input__suffix {
      display: none; // 隐藏下拉箭头，让它更像一个纯粹的搜索框
    }
  }
}
</style>
