<!--
  日志详情抽屉(操作日志 / 用户日志共用)

  设计取舍:日志的核心诉求是「这次到底改了什么」,而不是「原始 JSON 长什么样」。
  所以主视图是 **变更对比表**:把 before/after 两个 JSON 按 key 合并,逐字段列出旧值→新值,
  默认只显示【真正变化】的字段 —— 一条 User 更新日志往往带十几个字段,其中只有一两个变了,
  全铺出来反而找不到重点。原始 JSON 放在下面折叠区,需要时再看,并做格式化缩进。
-->
<template>
  <el-drawer :title="title" :visible.sync="drawerVisible" size="640px" append-to-body
    modal-append-to-body custom-class="log-detail-drawer" :destroy-on-close="true">
    <div class="drawer-body" v-if="row">
      <!-- 基本信息 -->
      <el-descriptions :column="2" size="small" border class="mb-12">
        <el-descriptions-item label="日志ID">{{ row.id }}</el-descriptions-item>
        <el-descriptions-item label="时间">{{ row.created_time || '-' }}</el-descriptions-item>
        <template v-if="type === 'admin'">
          <el-descriptions-item label="操作人">
            {{ row.admin_name || '-' }}<span v-if="row.admin_id" class="dim"> (#{{ row.admin_id }})</span>
          </el-descriptions-item>
          <el-descriptions-item label="模型">
            <span :title="row.model">{{ row.model_short || row.model || '-' }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="数据ID">{{ row.model_id || '-' }}</el-descriptions-item>
        </template>
        <template v-else>
          <el-descriptions-item label="用户">
            {{ row.user_nickname || '-' }}<span v-if="row.user_id" class="dim"> (#{{ row.user_id }})</span>
          </el-descriptions-item>
          <el-descriptions-item label="手机号">{{ row.user_mobile || '-' }}</el-descriptions-item>
        </template>
        <el-descriptions-item label="备注">{{ row.remarks || '-' }}</el-descriptions-item>
      </el-descriptions>

      <!-- 字段过滤 + 变更/全部切换 -->
      <div class="filter-bar">
        <el-input v-model="fieldFilter" size="mini" placeholder="过滤字段名或值" clearable
          prefix-icon="el-icon-search" style="width: 220px" />
        <el-radio-group v-model="showMode" size="mini" style="margin-left: 10px">
          <el-radio-button label="changed">仅变更 ({{ changedCount }})</el-radio-button>
          <el-radio-button label="all">全部 ({{ allRows.length }})</el-radio-button>
        </el-radio-group>
      </div>

      <el-table :data="filteredRows" size="mini" border max-height="380" class="diff-table"
        :row-class-name="rowClass" empty-text="没有匹配的字段">
        <el-table-column prop="key" label="字段" width="170" show-overflow-tooltip />
        <el-table-column label="变更前" min-width="150">
          <template slot-scope="s"><span class="val old">{{ s.row.beforeText }}</span></template>
        </el-table-column>
        <el-table-column label="变更后" min-width="150">
          <template slot-scope="s"><span class="val new">{{ s.row.afterText }}</span></template>
        </el-table-column>
      </el-table>

      <!-- 原始 JSON:格式化后折叠,排查解析异常时才需要 -->
      <el-collapse v-model="rawOpen" class="mt-12">
        <el-collapse-item name="raw" title="原始数据 (JSON)">
          <div class="raw-grid">
            <div class="raw-col">
              <div class="raw-title">变更前</div>
              <pre class="raw-json">{{ prettyBefore }}</pre>
            </div>
            <div class="raw-col">
              <div class="raw-title">变更后</div>
              <pre class="raw-json">{{ prettyAfter }}</pre>
            </div>
          </div>
        </el-collapse-item>
      </el-collapse>
    </div>
  </el-drawer>
</template>

<script>
export default {
  name: 'LogDetailDrawer',
  props: {
    visible: { type: Boolean, default: false },
    row: { type: Object, default: null },
    // admin = 操作日志(有模型/操作人),user = 用户日志
    type: { type: String, default: 'admin' }
  },
  data() {
    return {
      fieldFilter: '',
      showMode: 'changed',
      rawOpen: []
    }
  },
  computed: {
    drawerVisible: {
      get() { return this.visible },
      set(v) { this.$emit('update:visible', v) }
    },
    title() {
      if (!this.row) return '日志详情'
      return (this.type === 'admin' ? '操作日志 #' : '用户日志 #') + this.row.id
    },
    beforeObj() { return this.parse(this.row && this.row.before_update) },
    afterObj() { return this.parse(this.row && this.row.after_update) },
    prettyBefore() { return this.pretty(this.row && this.row.before_update) },
    prettyAfter() { return this.pretty(this.row && this.row.after_update) },

    // 按 key 并集合并,逐字段算出 旧值/新值/是否变化
    allRows() {
      const b = this.beforeObj
      const a = this.afterObj
      const keys = Array.from(new Set([...Object.keys(b), ...Object.keys(a)]))
      return keys.map(k => {
        const beforeText = this.toText(b[k])
        const afterText = this.toText(a[k])
        return { key: k, beforeText, afterText, changed: beforeText !== afterText }
      })
    },
    changedCount() { return this.allRows.filter(r => r.changed).length },
    filteredRows() {
      let rows = this.showMode === 'changed' ? this.allRows.filter(r => r.changed) : this.allRows
      const kw = this.fieldFilter.trim().toLowerCase()
      if (kw) {
        // 字段名和新旧值都参与匹配 —— 想找"哪条日志把手机号改成 186"时按值搜更直接
        rows = rows.filter(r =>
          r.key.toLowerCase().includes(kw) ||
          r.beforeText.toLowerCase().includes(kw) ||
          r.afterText.toLowerCase().includes(kw)
        )
      }
      return rows
    }
  },
  watch: {
    // 换一条日志时重置过滤,否则上一条的过滤词会让新日志看着是空的
    row() {
      this.fieldFilter = ''
      this.showMode = 'changed'
      this.rawOpen = []
    }
  },
  methods: {
    // 日志里的 JSON 是历史数据,格式不保证 —— 解析失败一律降级成空对象,不能让抽屉白屏
    parse(str) {
      if (!str) return {}
      if (typeof str === 'object') return str
      try {
        const o = JSON.parse(str)
        return (o && typeof o === 'object' && !Array.isArray(o)) ? o : { _value: o }
      } catch (e) {
        return { _raw: String(str) }
      }
    },
    pretty(str) {
      if (!str) return '(空)'
      try {
        return JSON.stringify(typeof str === 'object' ? str : JSON.parse(str), null, 2)
      } catch (e) {
        return String(str)   // 解析不了就原样展示,至少让人看得到内容
      }
    },
    toText(v) {
      if (v === undefined) return '—'
      if (v === null) return 'null'
      if (typeof v === 'object') return JSON.stringify(v)
      return String(v)
    },
    rowClass({ row }) { return row.changed ? 'row-changed' : '' }
  }
}
</script>

<style lang="scss" scoped>
.drawer-body { padding: 0 20px 20px; }
.mb-12 { margin-bottom: 12px; }
.mt-12 { margin-top: 12px; }
.dim { color: #909399; font-size: 12px; }

.filter-bar {
  display: flex;
  align-items: center;
  margin-bottom: 8px;
}

.diff-table {
  ::v-deep .row-changed { background: var(--admin-accent-soft); }
  .val {
    font-family: Menlo, Consolas, monospace;
    font-size: 12px;
    word-break: break-all;
    &.old { color: #909399; text-decoration: line-through; }
    &.new { color: #67c23a; }
  }
}

.raw-grid { display: flex; gap: 10px; }
.raw-col { flex: 1; min-width: 0; }
.raw-title { font-size: 12px; color: #909399; margin-bottom: 4px; }
.raw-json {
  margin: 0;
  padding: 8px;
  background: #f5f7fa;
  border-radius: 4px;
  font-family: Menlo, Consolas, monospace;
  font-size: 12px;
  line-height: 1.5;
  max-height: 260px;
  overflow: auto;
  white-space: pre-wrap;
  word-break: break-all;
}
</style>
