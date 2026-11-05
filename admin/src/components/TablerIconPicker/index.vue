<template>
  <el-dialog
    title="选择 Tabler 图标"
    :visible="visible"
    width="780px"
    top="6vh"
    append-to-body
    custom-class="tabler-picker-dialog"
    :close-on-click-modal="false"

    @close="close"
  >
    <div class="tabler-picker">
      <div class="picker-filters">
        <el-input
          v-model="keyword"
          placeholder="搜索名称或关键词，如：好友、home"
          prefix-icon="el-icon-search"
          size="small"
          clearable
          @input="queueSearch"
          @keyup.enter.native="search"
        />
        <el-select v-model="category" placeholder="全部分类" size="small" @change="search">
          <el-option label="全部分类" value="" />
          <el-option v-for="item in categories" :key="item.value" :value="item.value" :label="item.label + ' · ' + item.count" />
        </el-select>
      </div>
      <div class="picker-options">
        <el-radio-group v-model="variant" size="small" @change="changeVariant">
          <el-radio-button label="outline">线性</el-radio-button>
          <el-radio-button label="filled">实心</el-radio-button>
        </el-radio-group>
        <div class="color-field"><span>图标颜色</span><el-color-picker v-model="color" size="small" :predefine="colors" :show-alpha="false" /></div>
      </div>
      <div v-loading="loading" class="picker-grid-wrap">
        <div v-if="error" class="picker-empty"><span>{{ error }}</span><el-button type="text" @click="load">重新加载</el-button></div>
        <div v-else class="picker-grid">
          <button
            v-for="item in displayList"
            :key="item.name"
            type="button"
            class="picker-cell"
            :class="selected === item.name ? 'is-active' : ''"
            :aria-label="item.name"
            :aria-pressed="selected === item.name"
            @click="selected = item.name"
          >
            <img :src="item.preview" alt="" width="28" height="28" loading="lazy">
            <span :title="item.name">{{ item.name }}</span>
          </button>
          <div v-if="!loading && !list.length" class="picker-empty">没有找到图标，试试其他关键词。</div>
        </div>
      </div>
      <el-pagination
        class="picker-pagination"
        :current-page="page"
        :page-size="limit"
        :total="total"
        layout="total, prev, pager, next, jumper"
        small
        @current-change="changePage"
      />
      <div class="picker-preview">
        <div class="preview-sizes">
          <div><div class="example-box example-box--grid"><img v-if="preview" :src="preview" alt="宫格效果"></div><span>宫格效果</span></div>
          <div><div class="example-box example-box--list"><img v-if="preview" :src="preview" alt="列表效果"></div><span>列表效果</span></div>
        </div>
        <div class="scale-field"><div class="scale-title"><span>图形占比</span><strong>{{ scale }}%</strong></div>
          <el-slider v-model="scale" :min="50" :max="100" :step="5" :show-tooltip="false" />
          <p>大小沿用页面设置，调小占比可增加透明留白。</p>
        </div>
      </div>
      <div class="picker-selection">{{ selected || '请选择图标' }}<span>Tabler Icons · MIT</span></div>
    </div>
    <span slot="footer"><el-button size="small" @click="close">取消</el-button><el-button
      type="primary"
      size="small"
      :disabled="!selected || loading || !!error"
      :loading="saving"
      @click="confirm"
    >使用图标</el-button></span>
  </el-dialog>
</template>

<script>
import { parseTablerAsset, tablerPreviewUrl } from '@/utils/tabler-icons'

export default {
  name: 'TablerIconPicker',
  props: {
    visible: { type: Boolean, default: false },
    value: { type: String, default: '' },
    defaultColor: { type: String, default: '#303133' },
    defaultScale: { type: Number, default: 75 }
  },
  data() {
    return {
      keyword: '', category: '', categories: [], variant: 'outline', color: '#303133', scale: 75,
      selected: '', list: [], page: 1, limit: 40, total: 0, base: '', version: '',
      loading: false, saving: false, error: '',
      colors: ['#303133', '#f7931e', '#409eff', '#36a77c', '#9672d4', '#ffffff']
    }
  },
  computed: {
    safeColor() { return /^#[a-f0-9]{3}(?:[a-f0-9]{3})?$/i.test(this.color || '') ? this.color : '#303133' },
    displayList() {
      return this.list.map(item => ({ ...item, preview: tablerPreviewUrl(this.base, this.version, item.name, this.safeColor, this.variant, 100) }))
    },
    preview() { return tablerPreviewUrl(this.base, this.version, this.selected, this.safeColor, this.variant, this.scale) }
  },
  watch: {
    visible: { immediate: true, handler(value) { if (value) this.open() } }
  },
  beforeDestroy() { clearTimeout(this._searchTimer); this._requestId = (this._requestId || 0) + 1 },
  methods: {
    open() {
      this.keyword = ''; this.category = ''; this.page = 1; this.list = []; this.error = ''; this.saving = false
      const saved = parseTablerAsset(this.value)
      this.variant = saved ? saved.variant : 'outline'
      this.color = saved ? saved.color : this.defaultColor
      this.scale = saved ? saved.scale : Math.max(50, Math.min(100, Math.round(this.defaultScale / 5) * 5))
      this.selected = saved ? saved.name : ''
      this.load()
    },
    close() {
      clearTimeout(this._searchTimer)
      this._requestId = (this._requestId || 0) + 1
      this.loading = false
      this.$emit('update:visible', false)
    },
    queueSearch() { clearTimeout(this._searchTimer); this._searchTimer = setTimeout(() => this.search(), 300) },
    search() { clearTimeout(this._searchTimer); this.page = 1; this.load() },
    changePage(page) { this.page = page; this.load() },
    changeVariant() { this.category = ''; this.selected = ''; this.search() },
    async load() {
      const id = this._requestId = (this._requestId || 0) + 1
      this.loading = true; this.error = ''
      try {
        const res = await this.$request({ url: 'common/icon/list', data: { keyword: this.keyword.trim(), category: this.category, variant: this.variant, page: this.page, limit: this.limit }})
        if (id !== this._requestId) return
        if (res.code !== 0) throw new Error(res.msg || '读取图标失败')
        this.list = res.data.list; this.total = res.data.total_count; this.categories = res.data.categories
        this.base = res.data.asset_base_url; this.version = res.data.version
      } catch (e) {
        if (id === this._requestId) { this.list = []; this.error = e.message || '读取图标失败，请重试' }
      } finally { if (id === this._requestId) this.loading = false }
    },
    async confirm() {
      const id = this._requestId
      this.saving = true
      try {
        const res = await this.$request({ url: 'common/icon/asset', data: { name: this.selected, variant: this.variant, color: this.safeColor, scale: this.scale }})
        if (id !== this._requestId) return
        if (res.code !== 0) throw new Error(res.msg || '图标生成失败')
        this.$emit('input', res.data.url)
        this.close()
      } catch (e) { if (id === this._requestId) this.$message.error(e.message || '图标生成失败，请重试') } finally { if (id === this._requestId) this.saving = false }
    }
  }
}
</script>

<style scoped>
.picker-filters { display:flex; gap:12px; }.picker-filters .el-input { flex:1; }.picker-filters .el-select { width:165px; flex-shrink:0; }
.picker-options { display:flex; align-items:center; justify-content:space-between; margin:14px 0; }.color-field { display:flex; align-items:center; gap:10px; color:#909399; font-size:12px; }
.picker-grid-wrap { height:300px; overflow:auto; }.picker-grid { display:grid; grid-template-columns:repeat(8,minmax(0,1fr)); gap:7px; padding:1px; }.picker-cell { border:1px solid #ebeef5; background:#fff; border-radius:6px; padding:12px 4px 9px; cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:9px; min-width:0; height:80px; }
.picker-cell:hover,.picker-cell.is-active { border-color:var(--admin-accent-text); background:var(--admin-accent-soft); }.picker-cell:focus-visible { outline:2px solid var(--admin-accent-text); }.picker-cell span { font-size:10px; color:#909399; max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.picker-empty { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:180px; grid-column:1/-1; color:#909399; font-size:13px; }.picker-pagination { margin:16px 0; text-align:right; }
.picker-preview { border-top:1px solid #ebeef5; padding-top:16px; display:flex; align-items:center; gap:32px; }.preview-sizes { display:flex; align-items:center; justify-content:center; gap:25px; width:190px; flex-shrink:0; }.preview-sizes>div { display:flex; flex-direction:column; align-items:center; gap:7px; }.preview-sizes span { color:#909399; font-size:11px; }.example-box { display:flex; align-items:center; justify-content:center; background:#f5f7fa; border-radius:8px; }.example-box--grid { width:52px; height:52px; }.example-box--list { width:32px; height:32px; }.example-box img { width:100%; height:100%; object-fit:contain; }
.scale-field { flex:1; min-width:0; }.scale-title { display:flex; justify-content:space-between; font-size:12px; color:#606266; }.scale-title strong { color:var(--admin-accent-text); font-weight:500; }.scale-field p { font-size:11px; color:#909399; margin:0; }.picker-selection { display:flex; justify-content:space-between; color:#606266; font-size:12px; padding-top:14px; }.picker-selection span { color:#b1b5bd; font-size:11px; }
</style>
