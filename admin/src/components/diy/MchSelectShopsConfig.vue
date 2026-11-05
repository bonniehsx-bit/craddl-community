<template>
  <div class="diy-mch-select-shops-config">
    <el-form label-width="90px" size="small" label-position="left">

      <div class="config-section">
        <div class="section-title">选择商家</div>
        <el-form-item label="展示商户">
          <el-select
            v-model="form.mch_ids"
            multiple
            filterable
            remote
            clearable
            placeholder="输入商户名称搜索，可多选"
            :remote-method="searchMch"
            :loading="loading"
            size="small"
            style="width: 100%">
            <el-option v-for="m in mchOptions" :key="m.id" :label="m.name" :value="m.id">
              <span style="float:left">{{ m.name }}</span>
              <span style="float:right;color:#8492a6;font-size:12px">ID:{{ m.id }}</span>
            </el-option>
          </el-select>
          <div class="help-text">
            已选 {{ selectedCount }} 个 · C 端按你勾选的先后顺序展示；下架/删除的商户会自动跳过
          </div>
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">标题设置</div>
        <el-form-item label="主标题">
          <el-input v-model="form.title" placeholder="精选商家" maxlength="20" show-word-limit />
        </el-form-item>
        <el-form-item label="副标题">
          <el-input v-model="form.subtitle" placeholder="为您甄选优质商户" maxlength="30" show-word-limit />
        </el-form-item>
        <el-form-item label="查看更多">
          <el-switch v-model="form.show_more" :active-value="1" :inactive-value="0" />
          <div class="help-text">开启后右上角显示「查看更多」，点击跳商家列表页</div>
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">布局样式</div>
        <el-form-item label="样式">
          <el-radio-group v-model="form.style">
            <el-radio label="grid">网格（推荐）</el-radio>
            <el-radio label="scroll">横向滑动</el-radio>
            <el-radio label="list">大图列表</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="左右边距">
          <el-radio-group v-model="form.is_padding">
            <el-radio-button :label="0">无边距</el-radio-button>
            <el-radio-button :label="1">有边距</el-radio-button>
          </el-radio-group>
          <div class="help-text">关闭后组件铺满屏幕宽度（贴边）</div>
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">内容显示</div>
        <el-form-item label="显示评分">
          <el-switch v-model="form.show_rating" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="显示地址">
          <el-switch v-model="form.show_address" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">配色（留空走默认）</div>
        <el-form-item label="卡片背景">
          <div class="color-row">
            <el-color-picker v-model="form.bg_color" show-alpha></el-color-picker>
            <span class="color-text">{{ form.bg_color || '默认 #fff' }}</span>
            <el-button type="text" size="mini" v-if="form.bg_color" @click="form.bg_color = ''">清除</el-button>
          </div>
        </el-form-item>
        <el-form-item label="标题颜色">
          <div class="color-row">
            <el-color-picker v-model="form.title_color"></el-color-picker>
            <span class="color-text">{{ form.title_color || '默认 #0F172A' }}</span>
            <el-button type="text" size="mini" v-if="form.title_color" @click="form.title_color = ''">清除</el-button>
          </div>
        </el-form-item>
        <el-form-item label="子卡背景">
          <div class="color-row">
            <el-color-picker v-model="form.item_bg_color" show-alpha></el-color-picker>
            <span class="color-text">{{ form.item_bg_color || '默认 #F7F9FC' }}</span>
            <el-button type="text" size="mini" v-if="form.item_bg_color" @click="form.item_bg_color = ''">清除</el-button>
          </div>
        </el-form-item>
      </div>
    </el-form>
  </div>
</template>

<script>
export default {
  name: 'MchSelectShopsConfig',
  props: { value: Object },
  data() {
    return {
      loading: false,
      mchOptions: [], // el-select 选项 {id, name}
      defaultConfig: {
        title: '精选商家',
        subtitle: '为您甄选优质商户',
        mch_ids: [],
        show_rating: 1,
        show_address: 1,
        show_more: 0,
        style: 'grid',
        is_padding: 1,
        bg_color: '',
        title_color: '',
        item_bg_color: '',
      },
    }
  },
  computed: {
    form: {
      get() { return this.value || JSON.parse(JSON.stringify(this.defaultConfig)) },
      set(val) { this.$emit('input', val) },
    },
    selectedCount() {
      return Array.isArray(this.form.mch_ids) ? this.form.mch_ids.length : 0
    },
  },
  watch: {
    value: {
      handler(val) {
        // 老配置缺字段时灌入默认结构，保证 mch_ids 等键存在（响应式）
        if (!val || typeof val.mch_ids === 'undefined') {
          this.$emit('input', JSON.parse(JSON.stringify(this.defaultConfig)))
        }
      },
      immediate: true,
      deep: true,
    },
  },
  mounted() {
    this.loadSelected() // 回显已勾选商户的名称
  },
  methods: {
    // 远程搜索；保留当前已勾选项，避免它们的标签丢失
    searchMch(keyword) {
      this.loading = true
      this.$request({
        url: '/plugin/mch/mall/mch/search',
        method: 'get',
        data: { keyword: keyword || '' },
      }).then(res => {
        this.loading = false
        if (res.code === 0) {
          const results = res.data.list || []
          const selectedIds = this.form.mch_ids || []
          const kept = this.mchOptions.filter(o => selectedIds.includes(o.id) && !results.some(r => r.id === o.id))
          this.mchOptions = [...kept, ...results]
        }
      }).catch(() => { this.loading = false })
    },
    // 编辑回显：① 先按空关键词预拉一批默认商家，保证下拉一打开(未输入)就能看到全部可选；
    //          ② 已存的 mch_ids 可能不在默认前 N 条里，再单独取名合并进选项(去重)。
    loadSelected() {
      // ① 默认列表(未输入即可见全部商家)
      this.searchMch('')
      // ② 回显已选商户名称(仅当确有勾选时才带 ids)
      const ids = (this.form && this.form.mch_ids) || []
      if (!ids.length) return
      this.$request({
        url: '/plugin/mch/mall/mch/search',
        method: 'get',
        data: { ids: ids.join(',') },
      }).then(res => {
        if (res.code === 0) {
          const map = {}
          this.mchOptions.forEach(o => { map[o.id] = o })
          ;(res.data.list || []).forEach(o => { map[o.id] = o })
          this.mchOptions = Object.values(map)
        }
      })
    },
  },
}
</script>

<style lang="scss" scoped>
.diy-mch-select-shops-config {
  .config-section {
    margin-bottom: 25px;
    padding-bottom: 25px;
    border-bottom: 1px solid #ebeef5;
    &:last-child { border-bottom: none; padding-bottom: 0; }

    .section-title {
      font-weight: bold;
      font-size: 14px;
      margin-bottom: 18px;
      color: #303133;
      padding-left: 10px;
      position: relative;
      &::before {
        content: '';
        position: absolute;
        left: 0; top: 2px; bottom: 2px;
        width: 3px;
        background: var(--admin-accent-text);
        border-radius: 2px;
      }
    }
  }
  .help-text { font-size: 12px; color: #c0c4cc; line-height: 1.4; margin-top: 4px; }
  .color-row {
    display: flex;
    align-items: center;
    gap: 10px;
    .color-text { font-size: 12px; color: #909399; min-width: 110px; }
  }
}
</style>
