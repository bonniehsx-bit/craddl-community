<template>
  <!-- 挂到 body 上:顶栏 overflow:hidden,面板放在里面会被裁掉 -->
  <div class="app-launcher-root">
    <transition name="al-fade">
      <div v-show="visible" class="al-mask" :style="{ top: anchor.top + 'px' }" @click="close"></div>
    </transition>

    <!--
      动效只用 transform / opacity —— 合成线程直接跑,每帧不重绘、不排版。
      .al-stage 是贴着顶栏下沿的静态裁切框;面板从框外(顶栏背后)往下推,内层同步反向位移抵消,
      看上去是内容基本不动、面板下沿往下扫开(卷帘)。原来用 clip-path + 毛玻璃做,每帧都要重绘,
      再叠上标题栏和 49 个格子各自缩放入场,打开时会抖。
      面板 v-show 常驻:每次打开不再重建格子、不重新解码图标。
    -->
    <div class="al-stage" :style="{ top: anchor.top + 'px' }">
      <transition name="al-drop" @enter="focusSearch">
        <div v-show="visible" class="al-panel" :style="panelStyle" role="dialog" aria-label="应用中心"
             @keydown.esc.stop="close">
          <div class="al-inner">
            <div class="al-head">
              <div class="al-search">
                <i class="el-icon-search"></i>
                <input ref="search" v-model.trim="keyword" placeholder="搜索应用,回车直接打开" @keydown.enter="openFirst" />
                <i v-if="keyword" class="el-icon-circle-close al-clear" @click="keyword = ''"></i>
              </div>
              <span class="al-total" v-if="apps.length">共 {{ apps.length }} 个应用</span>
              <a class="al-manage" @click="gotoCenter">应用中心<i class="el-icon-arrow-right"></i></a>
            </div>

            <div class="al-body" v-loading="loading && !apps.length">
              <template v-if="ready">
                <!-- 搜索结果 -->
                <template v-if="keyword">
                  <div v-if="!results.length" class="al-empty">没有找到「{{ keyword }}」相关的应用</div>
                  <div v-else class="al-grid">
                    <app-tile v-for="(a, i) in results" :key="a.name" :app="a" :first="i === 0"
                              @open="open" @star="toggleFavorite" />
                  </div>
                </template>

                <template v-else>
                  <section v-for="sec in sections" :key="sec.key" class="al-section">
                    <div class="al-section-title">
                      <span>{{ sec.title }}</span>
                      <small v-if="sec.tip">{{ sec.tip }}</small>
                    </div>
                    <div v-if="!sec.list.length" class="al-section-empty">{{ sec.empty }}</div>
                    <div v-else class="al-grid">
                      <app-tile v-for="a in sec.list" :key="sec.key + a.name" :app="a"
                                @open="open" @star="toggleFavorite" />
                    </div>
                  </section>
                  <div v-if="!loading && !apps.length" class="al-empty">当前账号还没有可用的应用</div>
                </template>
              </template>
            </div>
          </div>
        </div>
      </transition>
    </div>
  </div>
</template>

<script>
import AppIcon from '@/components/AppIcon'
import { fetchApps, invalidateApps, flattenApps, appOfPath, getRecent, recordRecent } from '@/utils/appCenter'

// 单个应用格子:图标 + 名称 + 悬停出现的星标
const AppTile = {
  name: 'AppTile',
  components: { AppIcon },
  props: { app: Object, first: Boolean },
  render(h) {
    const a = this.app
    return h('div', {
      class: ['al-tile', { 'is-disabled': !a.has_entry, 'is-first': this.first }],
      attrs: { title: a.has_entry ? (a.description || a.label) : '暂无可进入的页面', tabindex: 0 },
      on: {
        click: () => this.$emit('open', a),
        keydown: e => { if (e.key === 'Enter') this.$emit('open', a) }
      }
    }, [
      h('app-icon', { props: { app: a, size: 42 } }),
      h('div', { class: 'al-tile-name' }, a.label),
      h('i', {
        class: ['al-star', a.is_shortcut ? 'el-icon-star-on is-on' : 'el-icon-star-off'],
        attrs: { title: a.is_shortcut ? '移出常用' : '加入常用' },
        on: { click: e => { e.stopPropagation(); this.$emit('star', a) } }
      })
    ])
  }
}

export default {
  name: 'AppLauncher',
  components: { AppTile },
  data() {
    return {
      visible: false,
      ready: false, // 格子是否已渲染:数据到了就趁空闲先渲染好,第一次打开也不卡
      loading: false,
      groups: [],
      keyword: '',
      recent: [],
      anchor: { top: 50, left: 0, width: 0 }
    }
  },
  computed: {
    apps() {
      return flattenApps(this.groups)
    },
    panelStyle() {
      // 裁切框占满整宽(左右阴影不被切),面板自己对齐顶栏
      return { marginLeft: this.anchor.left + 'px', width: this.anchor.width + 'px' }
    },
    results() {
      const k = this.keyword.toLowerCase()
      return this.apps.filter(a => (a.label || '').toLowerCase().indexOf(k) !== -1
        || (a.description || '').toLowerCase().indexOf(k) !== -1
        || (a.name || '').toLowerCase().indexOf(k) !== -1)
        // 名称命中的排前面,其次能进入的
        .sort((x, y) => ((y.label || '').toLowerCase().indexOf(k) === 0) - ((x.label || '').toLowerCase().indexOf(k) === 0)
          || (!!y.has_entry) - (!!x.has_entry))
    },
    sections() {
      const byName = {}
      this.apps.forEach(a => { byName[a.name] = a })
      const fav = this.apps.filter(a => a.is_shortcut)
      const recent = this.recent.map(n => byName[n]).filter(Boolean)
      const list = [
        { key: 'fav', title: '我的常用', tip: '点应用右上角的星标加入', list: fav, empty: '还没有常用应用,把常用的点亮星标就会出现在这里' }
      ]
      if (recent.length) list.push({ key: 'recent', title: '最近使用', list: recent })
      this.groups.forEach((g, i) => {
        if (g.list && g.list.length) list.push({ key: 'g' + i, title: g.group_name, list: g.list })
      })
      return list
    }
  },
  watch: {
    '$route.path'(path) {
      if (this.visible) this.close()
      // 不管从侧边栏、搜索还是这里进的,都记一笔「最近使用」
      const app = appOfPath(this.apps, path)
      if (app) recordRecent(app.name)
    }
  },
  mounted() {
    document.body.appendChild(this.$el)
    this.load().then(() => {
      const app = appOfPath(this.apps, this.$route.path)
      if (app) recordRecent(app.name)
      const idle = window.requestIdleCallback || (fn => setTimeout(fn, 300))
      idle(() => { this.ready = true })
    })
    this.onRefresh = () => {
      invalidateApps()
      this.load(true)
    }
    this.$root.$on('refresh-shortcuts', this.onRefresh)
    this.onKeydown = e => {
      if (e.key === 'Escape' && this.visible) this.close()
    }
    document.addEventListener('keydown', this.onKeydown)
  },
  beforeDestroy() {
    this.$root.$off('refresh-shortcuts', this.onRefresh)
    document.removeEventListener('keydown', this.onKeydown)
    if (this.$el.parentNode) this.$el.parentNode.removeChild(this.$el)
  },
  methods: {
    load(force = false) {
      // 已有数据时静默刷新,不转圈
      if (!this.groups.length) this.loading = true
      return fetchApps(force).then(groups => {
        this.groups = groups
      }).catch(() => {}).finally(() => {
        this.loading = false
      })
    },
    /** anchorEl:顶栏元素,面板从它的下沿推出、和它同宽 */
    toggle(anchorEl) {
      this.visible ? this.close() : this.show(anchorEl)
    },
    show(anchorEl) {
      if (anchorEl) {
        const r = anchorEl.getBoundingClientRect()
        this.anchor = { top: Math.round(r.bottom), left: Math.round(r.left), width: Math.round(r.width) }
      }
      this.keyword = ''
      this.recent = getRecent()
      this.ready = true
      this.visible = true
      this.$emit('change', true)
      this.load()
    },
    close() {
      this.visible = false
      this.$emit('change', false)
    },
    focusSearch() {
      this.$nextTick(() => this.$refs.search && this.$refs.search.focus({ preventScroll: true }))
    },
    open(app) {
      if (!app.has_entry) {
        this.$message.warning('「' + app.label + '」暂无可进入的页面,请检查菜单权限或是否被隐藏')
        return
      }
      recordRecent(app.name)
      this.close()
      if (this.$route.path !== app.route_path) this.$router.push(app.route_path)
    },
    openFirst() {
      const first = this.results[0]
      if (first) this.open(first)
    },
    gotoCenter() {
      this.close()
      if (this.$route.path !== '/plugins/index') this.$router.push('/plugins/index')
    },
    toggleFavorite(app) {
      this.$request({
        url: '/mall/plugin/toggle-shortcut',
        method: 'post',
        data: { plugin_name: app.name }
      }).then(res => {
        if (res.code !== 0) {
          this.$message.error(res.msg)
          return
        }
        const on = res.data.is_shortcut
        // 分组里和打平后是同一个应用的两份对象,都要改
        this.groups.forEach(g => (g.list || []).forEach(a => {
          if (a.name === app.name) this.$set(a, 'is_shortcut', on)
        }))
        invalidateApps()
        // 应用中心页如果开着,让它同步星标
        this.$root.$emit('app-favorite-changed', { name: app.name, is_shortcut: on })
      })
    }
  }
}
</script>

<style lang="scss">
/* 挂在 body 上,不能 scoped 穿透到 render 函数子组件,统一用 al- 前缀避免串味 */
$al-ease: cubic-bezier(0.22, 1, 0.36, 1);
$al-ease-out: cubic-bezier(0.32, 0.72, 0, 1); // 抽屉曲线:起步快、收尾长而稳
$al-ease-in: cubic-bezier(0.4, 0, 1, 1);

.al-mask {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 2000;
  background: rgba(15, 23, 42, 0.28);
}

/* 静态裁切框:上沿贴顶栏,面板从它外面推进来;自身不动、不挡点击 */
.al-stage {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 2001;
  overflow: hidden;
  overflow: clip;
  pointer-events: none;
}

.al-panel {
  pointer-events: auto;
  display: flex;
  flex-direction: column;
  max-height: min(72vh, 720px);
  background: #fff;
  border-bottom-left-radius: 14px;
  border-bottom-right-radius: 14px;
  box-shadow: 0 24px 48px -12px rgba(15, 23, 42, 0.28), 0 1px 0 rgba(15, 23, 42, 0.06) inset;
  overflow: hidden;
  overflow: clip;
}

/* 与 .al-panel 等高(面板无内边距/边框),反向位移的百分比才能正好抵消 */
.al-inner {
  flex: 1 1 auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.al-head {
  flex: none;
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px 24px 12px;
  border-bottom: 1px solid rgba(15, 23, 42, 0.06);
}

.al-search {
  flex: 0 1 420px;
  display: flex;
  align-items: center;
  gap: 8px;
  height: 36px;
  padding: 0 14px;
  border-radius: 10px;
  background: #f2f4f8;
  border: 1px solid transparent;
  color: #909399;
  transition: border-color .2s, background .2s, box-shadow .2s;

  &:focus-within {
    background: #fff;
    border-color: var(--admin-accent-text);
    box-shadow: 0 0 0 3px rgba(var(--admin-accent-rgb), 0.12);
  }

  input {
    flex: 1;
    min-width: 0;
    border: 0;
    outline: 0;
    background: transparent;
    font-size: 14px;
    color: #303133;

    &::placeholder { color: #a8abb2; }
  }

  .al-clear { cursor: pointer; }
}

.al-total {
  font-size: 12px;
  color: #909399;
  font-variant-numeric: tabular-nums;
}

.al-manage {
  margin-left: auto;
  font-size: 13px;
  color: var(--admin-accent-text);
  cursor: pointer;
  white-space: nowrap;

  &:hover { opacity: .8; }
  i { margin-left: 2px; }
}

.al-body {
  flex: 1 1 auto;
  min-height: 160px;
  overflow-y: auto;
  padding: 8px 24px 24px;
  overscroll-behavior: contain;
}

.al-section { margin-top: 14px; }

.al-section-title {
  display: flex;
  align-items: baseline;
  gap: 8px;
  margin-bottom: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #303133;
  letter-spacing: .02em;

  small {
    font-size: 12px;
    font-weight: 400;
    color: #a8abb2;
  }
}

.al-section-empty,
.al-empty {
  font-size: 13px;
  color: #a8abb2;
  padding: 8px 0;
}

.al-empty {
  padding: 40px 0;
  text-align: center;
}

.al-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(104px, 1fr));
  gap: 4px;
}

.al-tile {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 14px 6px 12px;
  border-radius: 12px;
  cursor: pointer;
  outline: none;
  transition: background .18s, transform .18s $al-ease;

  &:hover,
  &:focus-visible,
  &.is-first {
    background: var(--admin-accent-soft);
  }

  &:hover { transform: translateY(-2px); }
  &:focus-visible { box-shadow: 0 0 0 2px rgba(var(--admin-accent-rgb), 0.5); }

  .app-icon {
    box-shadow: 0 4px 10px -4px rgba(15, 23, 42, 0.25);
    transition: transform .25s $al-ease;
  }

  &:hover .app-icon { transform: scale(1.06); }

  &.is-disabled {
    cursor: not-allowed;

    .app-icon,
    .al-tile-name { opacity: .4; }
    &:hover { transform: none; }
  }
}

.al-tile-name {
  max-width: 100%;
  font-size: 12px;
  line-height: 1.4;
  color: #4b5563;
  text-align: center;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.al-star {
  position: absolute;
  top: 6px;
  right: 8px;
  font-size: 14px;
  color: #c0c4cc;
  opacity: 0;
  transition: opacity .15s, color .15s, transform .15s;

  &:hover { color: var(--admin-accent-text); transform: scale(1.2); }
  &.is-on { color: var(--admin-accent-text); opacity: 1; }
}

.al-tile:hover .al-star,
.al-tile:focus-visible .al-star { opacity: 1; }

/* ---------------- 动效:从顶栏下沿推出(只动 transform / opacity) ---------------- */
.al-drop-enter-active,
.al-drop-leave-active {
  will-change: transform;

  .al-inner { will-change: transform, opacity; }
}

.al-drop-enter-active {
  transition: transform .46s $al-ease-out;

  .al-inner { transition: transform .46s $al-ease-out, opacity .24s ease-out; }
}

.al-drop-leave-active {
  transition: transform .24s $al-ease-in;

  .al-inner { transition: transform .24s $al-ease-in, opacity .18s ease-in; }
}

/* 外层整块在顶栏背后,内层反向推回原位并上提 12px:展开时下沿扫下来、内容轻轻落位 */
.al-drop-enter,
.al-drop-leave-to {
  transform: translateY(-100%);

  .al-inner {
    transform: translateY(calc(100% - 12px));
    opacity: 0;
  }
}

.al-fade-enter-active { transition: opacity .36s ease-out; }
.al-fade-leave-active { transition: opacity .24s ease-in; }
.al-fade-enter,
.al-fade-leave-to { opacity: 0; }

@media (prefers-reduced-motion: reduce) {
  .al-drop-enter-active,
  .al-drop-leave-active {
    transition: opacity .15s linear;

    .al-inner { transition: none; }
  }

  .al-drop-enter,
  .al-drop-leave-to {
    transform: none;
    opacity: 0;

    .al-inner {
      transform: none;
      opacity: 1;
    }
  }
}
</style>
