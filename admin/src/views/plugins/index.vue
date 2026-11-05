<template>
  <div class="app-container app-center" :class="{ 'with-tags': hasTagsView }">
    <header class="page-header">
      <div>
        <div class="title-line">
          <h1>应用中心</h1>
          <span class="total-badge">{{ totalCount }} 个应用</span>
        </div>
        <p>查找应用，管理常用入口</p>
      </div>
      <el-input v-model="keyword" class="app-search" placeholder="搜索应用名称或功能"
                prefix-icon="el-icon-search" size="medium" clearable aria-label="搜索应用" />
    </header>

    <div class="app-surface" v-loading="loading">
      <nav class="category-nav" aria-label="应用分类">
        <div class="category-caption">应用分类</div>
        <button type="button" class="category-button" :class="{ active: activeCategory === 'all' }"
                :aria-pressed="activeCategory === 'all'" @click="selectCategory('all')">
          <i class="el-icon-menu" aria-hidden="true" /><span>全部应用</span><span class="category-count">{{ totalCount }}</span>
        </button>
        <button type="button" class="category-button" :class="{ active: activeCategory === 'favorites' }"
                :aria-pressed="activeCategory === 'favorites'" @click="selectCategory('favorites')">
          <i class="el-icon-star-off" aria-hidden="true" /><span>我的常用</span><span class="category-count">{{ favoriteCount }}</span>
        </button>
        <div class="category-divider" />
        <button v-for="group in groups" :key="group.group_name" type="button" class="category-button"
                :class="{ active: activeCategory === 'group:' + group.group_name }"
                :aria-pressed="activeCategory === 'group:' + group.group_name"
                @click="selectCategory('group:' + group.group_name)">
          <i :class="groupIcon(group.group_name)" aria-hidden="true" />
          <span>{{ group.group_name }}</span><span class="category-count">{{ group.list.length }}</span>
        </button>
        <div class="category-brand"><span>Craddl</span><small>Business, on your terms.</small></div>
      </nav>

      <div ref="content" class="app-content">
        <el-empty v-if="loadFailed && !loading" description="应用加载失败，请重试">
          <el-button type="primary" size="small" @click="getList">重新加载</el-button>
        </el-empty>
        <el-empty v-else-if="!visibleGroups.length && !loading" :description="emptyDescription" />
        <section v-for="group in visibleGroups" :key="group.group_name" class="plugin-group">
          <div class="group-heading">
            <h2>{{ group.group_name }}</h2><span class="group-count">{{ group.list.length }}</span>
            <span v-if="group.description" class="group-description">{{ group.description }}</span>
          </div>
          <div class="plugin-grid">
            <article v-for="plugin in group.list" :key="plugin.name" class="plugin-item"
                     :class="{ 'is-unavailable': plugin.has_entry === false }" :data-plugin-name="plugin.name">
              <button type="button" class="plugin-open" :aria-label="'进入' + plugin.label"
                      :aria-disabled="plugin.has_entry === false"
                      :title="plugin.has_entry === false ? '暂无可进入的页面，请检查菜单权限或是否被隐藏' : plugin.description"
                      @click="enterPlugin(plugin)">
                <app-icon :app="plugin" :size="40" />
                <span class="plugin-info">
                  <span class="plugin-title">{{ plugin.label }}</span>
                  <span class="plugin-summary">{{ plugin.description || '暂无功能介绍' }}</span>
                </span>
                <i class="el-icon-right open-arrow" aria-hidden="true" />
              </button>
              <el-button type="text" class="shortcut-star" :class="{ 'is-saved': plugin.is_shortcut }"
                         :disabled="!!shortcutPending[plugin.name]"
                         :aria-label="(plugin.is_shortcut ? '移出常用：' : '加入常用：') + plugin.label"
                         :aria-pressed="!!plugin.is_shortcut" :title="plugin.is_shortcut ? '移出常用' : '加入常用'"
                         @click="toggleShortcut(plugin)">
                <i :class="plugin.is_shortcut ? 'el-icon-star-on' : 'el-icon-star-off'" aria-hidden="true" />
              </el-button>
            </article>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<script>
import AppIcon from '@/components/AppIcon'
import { invalidateApps, recordRecent } from '@/utils/appCenter'

const GROUP_ICONS = {
  '分润类': 'el-icon-coin',
  '营销类': 'el-icon-present',
  '工具类': 'el-icon-set-up',
  '客户定制类': 'el-icon-s-custom'
}

export default {
  name: 'plugins-index',
  components: { AppIcon },
  data() {
    return {
      list: [],
      keyword: '',
      activeCategory: 'all',
      loading: false,
      loadFailed: false,
      shortcutPending: {}
    }
  },
  computed: {
    hasTagsView() {
      return this.$store.state.settings.tagsView
    },
    groups() {
      return this.list.filter(group => group.list && group.list.length > 0)
    },
    totalCount() {
      return this.groups.reduce((count, group) => count + group.list.length, 0)
    },
    favoriteCount() {
      return this.groups.reduce((count, group) => count + group.list.filter(plugin => plugin.is_shortcut).length, 0)
    },
    visibleGroups() {
      const keyword = this.keyword.trim().toLowerCase()
      return this.groups.filter(group => this.activeCategory === 'all' || this.activeCategory === 'favorites'
        || this.activeCategory === 'group:' + group.group_name).map(group => ({
        ...group,
        list: group.list.filter(plugin => {
          if (this.activeCategory === 'favorites' && !plugin.is_shortcut) return false
          return !keyword || [plugin.label, plugin.description, plugin.name].some(value =>
            String(value || '').toLowerCase().includes(keyword))
        })
      })).filter(group => group.list.length > 0)
    },
    emptyDescription() {
      if (this.keyword.trim()) return '没有找到相关应用，试试其他关键词'
      if (this.activeCategory === 'favorites') return '还没有常用应用，点击星标即可收藏'
      return '当前账号暂无可用应用'
    }
  },
  watch: {
    keyword() {
      this.resetContentScroll()
    }
  },
  mounted() {
    this.getList()
    this.onFavChanged = ({ name, is_shortcut }) => {
      this.list.forEach(group => (group.list || []).forEach(plugin => {
        if (plugin.name === name) this.$set(plugin, 'is_shortcut', is_shortcut)
      }))
    }
    this.$root.$on('app-favorite-changed', this.onFavChanged)
  },
  beforeDestroy() {
    this.$root.$off('app-favorite-changed', this.onFavChanged)
  },
  methods: {
    groupIcon(name) {
      return GROUP_ICONS[name] || 'el-icon-collection-tag'
    },
    selectCategory(category) {
      this.activeCategory = category
      this.resetContentScroll()
    },
    resetContentScroll() {
      this.$nextTick(() => {
        if (this.$refs.content) this.$refs.content.scrollTop = 0
      })
    },
    enterPlugin(plugin) {
      if (plugin.has_entry === false) {
        this.$message.warning('「' + plugin.label + '」暂无可进入的页面，请检查菜单权限或是否被隐藏')
        return
      }
      // 优先使用后端按当前菜单权限解析的完整入口，兼容旧接口的相对路径。
      const path = plugin.route_path || plugin.path || 'index'
      const target = path.startsWith('/') ? path : '/plugins/' + plugin.name + '/' + path
      recordRecent(plugin.name)
      this.$go.push(target)
    },
    getList() {
      this.loading = true
      this.loadFailed = false
      // 一次获取当前账号有权限的应用，分类数量不因关键词筛选而跳动。
      this.$request({ url: '/mall/plugin/index', data: { keyword: '' } }).then(res => {
        if (res.code !== 0) {
          this.loadFailed = true
          return
        }
        this.list = res.data.list || []
        if (this.activeCategory.startsWith('group:')
          && !this.groups.some(group => this.activeCategory === 'group:' + group.group_name)) {
          this.activeCategory = 'all'
        }
      }).catch(() => {
        this.loadFailed = true
      }).finally(() => {
        this.loading = false
      })
    },
    toggleShortcut(plugin) {
      if (this.shortcutPending[plugin.name]) return
      this.$set(this.shortcutPending, plugin.name, true)
      this.$request({
        url: '/mall/plugin/toggle-shortcut',
        method: 'post',
        data: { plugin_name: plugin.name }
      }).then(res => {
        if (res.code !== 0) {
          this.$message.error(res.msg)
          return
        }
        this.$set(plugin, 'is_shortcut', res.data.is_shortcut)
        invalidateApps()
        this.$root.$emit('app-favorite-changed', { name: plugin.name, is_shortcut: res.data.is_shortcut })
        this.$root.$emit('refresh-shortcuts')
        this.$message.success(res.msg)
      }).catch(() => {}).finally(() => {
        this.$delete(this.shortcutPending, plugin.name)
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.app-center {
  height: calc(100vh - 50px);
  padding: 26px 30px 20px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--admin-canvas);
  &.with-tags { height: calc(100vh - 50px - var(--tags-view-height, 34px)); }
}
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  flex-shrink: 0;
  margin-bottom: 22px;
  .title-line { display: flex; align-items: center; gap: 12px; }
  h1 { margin: 0; color: #303133; font-size: 22px; font-weight: 600; line-height: 1.4; letter-spacing: .4px; }
  p { margin: 8px 0 0; color: #909399; font-size: 12px; }
  .total-badge { padding: 3px 10px; border: 1px solid #e3e7ed; border-radius: 20px; color: #909399; background: #fff; font-size: 12px; }
  .app-search { width: 270px; flex-shrink: 0; }
  .app-search ::v-deep .el-input__inner { font-size: 12px; border-radius: 5px; }
}
.app-surface {
  display: flex;
  flex: 1;
  min-height: 0;
  overflow: hidden;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  background: #fff;
}
.category-nav {
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  gap: 5px;
  width: 174px;
  padding: 22px 14px;
  overflow-y: auto;
  border-right: 1px solid #ebeef5;
  .category-caption { margin: 0 12px 10px; font-size: 11px; color: #a0a5ae; letter-spacing: 1.4px; }
  .category-divider { height: 1px; flex-shrink: 0; margin: 12px; background: #ebeef5; }
}
.category-button {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  gap: 9px;
  width: 100%;
  padding: 11px 12px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: #606266;
  font: inherit;
  font-size: 12px;
  text-align: left;
  cursor: pointer;
  transition: background .15s, color .15s;
  i { color: #8e96a2; font-size: 17px; }
  .category-count { margin-left: auto; color: #a0a6b0; font-size: 11px; font-variant-numeric: tabular-nums; }
  &:hover { background: #f6f7f9; }
  &.active {
    background: var(--admin-accent-soft);
    color: var(--admin-accent-text);
    font-weight: 500;
    i, .category-count { color: var(--admin-accent-text); }
  }
  &:focus-visible { outline: 2px solid var(--admin-accent-text); outline-offset: 2px; }
}
.category-brand {
  margin-top: auto;
  padding: 20px 12px 0;
  color: #b2b6be;
  font-size: 10px;
  line-height: 1.8;
  span { display: block; color: #969da7; font-size: 12px; font-weight: 500; }
  small { font-size: inherit; }
}
.app-content {
  flex: 1;
  min-width: 0;
  padding: 0 26px 28px;
  overflow: auto;
  overflow-anchor: none;
  scrollbar-width: thin;
  scrollbar-color: #dcdfe6 transparent;
}
.plugin-group { padding-top: 24px; }
.group-heading {
  display: flex;
  align-items: center;
  gap: 9px;
  margin-bottom: 13px;
  h2 { margin: 0; color: #303133; font-size: 14px; font-weight: 600; line-height: 24px; white-space: nowrap; }
  .group-count { margin-left: 1px; color: #909399; font-size: 11px; }
  .group-description { margin-left: 9px; color: #a0a5ad; font-size: 11px; }
  &::after { content: ''; height: 1px; flex: 1; margin-left: 5px; background: #f0f1f4; }
}
.plugin-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.plugin-item {
  position: relative;
  min-width: 0;
  border: 1px solid #ebeef5;
  border-radius: 7px;
  background: #fff;
  transition: border-color .18s, box-shadow .18s, transform .18s;
  &:hover, &:focus-within { border-color: var(--admin-accent-border); box-shadow: 0 3px 12px rgba(24,32,51,.03); }
  &:hover { transform: translateY(-1px); }
  &:hover .open-arrow, &:focus-within .open-arrow { opacity: 1; transform: translateX(0); }
  &.is-unavailable .plugin-title { color: #909399; }
}
.plugin-open {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  width: 100%;
  height: 100%;
  min-height: 96px;
  padding: 18px 14px;
  border: 0;
  border-radius: inherit;
  background: transparent;
  font: inherit;
  text-align: left;
  cursor: pointer;
  &:focus-visible { outline: 2px solid var(--admin-accent-text); outline-offset: 2px; }
  .plugin-info { flex: 1; min-width: 0; padding-right: 11px; }
  .plugin-title { display: block; overflow: hidden; margin-bottom: 4px; color: #303133; font-size: 13px; font-weight: 500; line-height: 20px; text-overflow: ellipsis; white-space: nowrap; }
  .plugin-summary { display: -webkit-box; height: 36px; overflow: hidden; color: #909399; font-size: 11px; line-height: 1.65; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
  .open-arrow { position: absolute; bottom: 11px; right: 10px; color: var(--admin-accent-text); font-size: 13px; opacity: 0; transform: translateX(-3px); transition: opacity .15s, transform .15s; }
}
.plugin-item .shortcut-star.el-button--text {
  position: absolute;
  top: 6px;
  right: 5px;
  width: 25px;
  height: 25px;
  padding: 3px;
  border-radius: 4px;
  color: #c0c4cc;
  font-size: 15px;
  &:hover, &:focus-visible { color: var(--admin-accent-text); background: var(--admin-accent-soft); }
  &.is-saved { color: var(--admin-accent-text); }
  &.is-disabled { opacity: .5; }
}
@media (min-width: 1600px) { .plugin-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
@media (max-width: 1100px) {
  .app-center { padding: 22px 22px 18px; }
  .category-nav { width: 158px; padding: 22px 10px; }
  .app-content { padding: 0 20px 24px; }
  .plugin-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .group-description { display: none; }
}
@media (max-width: 820px) {
  .app-center { padding: 22px 20px 16px; }
  .page-header { margin-bottom: 18px; .app-search { width: 230px; } }
  .app-surface { flex-direction: column; }
  .category-nav { flex-direction: row; width: auto; padding: 13px 18px; overflow-x: auto; overflow-y: hidden; border-right: 0; border-bottom: 1px solid #ebeef5; scrollbar-width: none; }
  .category-nav .category-caption, .category-divider, .category-brand { display: none; }
  .category-button { width: auto; padding: 9px 12px; gap: 6px; white-space: nowrap; .category-count { margin-left: 4px; } }
  .plugin-open { padding: 17px 13px; }
}
@media (max-width: 560px) {
  .app-center { padding: 21px 14px 14px; }
  .page-header { display: block; margin-bottom: 17px; .title-line { gap: 9px; } h1 { font-size: 21px; } p { margin-top: 6px; font-size: 11px; } .app-search { width: 100%; margin-top: 17px; } .total-badge { padding: 2px 8px; font-size: 11px; } }
  .category-nav { padding: 10px; gap: 3px; }
  .category-button { padding: 8px 10px; font-size: 11px; border-radius: 5px; i { display: none; } .category-count { font-size: 10px; } }
  .app-content { padding: 0 14px 20px; }
  .plugin-group { padding-top: 19px; }
  .group-heading { margin-bottom: 11px; h2 { font-size: 13px; } }
  .plugin-grid { grid-template-columns: 1fr; gap: 10px; }
  .plugin-open { min-height: 86px; padding: 16px 13px; .plugin-info { padding-right: 20px; } .open-arrow { opacity: 1; bottom: 16px; right: 13px; color: #b9c0ca; } }
  .plugin-item .shortcut-star.el-button--text { top: 8px; right: 7px; }
}
@media (prefers-reduced-motion: reduce) {
  .category-button, .plugin-item, .open-arrow { transition: none; }
}
</style>
