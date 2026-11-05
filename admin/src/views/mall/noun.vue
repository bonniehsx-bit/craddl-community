<template>
  <div class="app-container">
    <el-card shadow="always" :body-style="{ padding: '20px' }">
      <div slot="header" class="card-header">
        <div class="header-left">
          <span class="title">词库配置</span>
          <span class="desc">自定义本商城专属叫法（如 积分 → 金豆）；留空用默认。用法见下方「配置说明」。</span>
        </div>
        <el-button type="primary" size="small" :loading="saving" @click="saveAll">全部保存</el-button>
      </div>

      <!-- 顶部统一使用说明（可折叠） -->
      <el-collapse v-model="helpOpen" class="help-collapse">
        <el-collapse-item name="help">
          <template slot="title">
            <i class="el-icon-info-circle help-icon"></i> 词库配置说明 · 如何使用
          </template>
          <div class="help-body">
            <p><b>作用：</b>把系统名词（积分 / 余额 / 佣金 …）改成本商城的叫法，前端全站跟着变；某条留空则用默认词。</p>
            <p><b>词条类型（可用顶部标签筛选）：</b>
              <el-tag size="mini" type="primary" effect="plain">名词</el-tag> 单个词（如 余额）；
              <el-tag size="mini" type="warning" effect="plain">短语</el-tag> 句中片段 / 按钮词（如 收入、转赠）；
              <el-tag size="mini" type="success" effect="plain">标题</el-tag> 页面标题（如 我的余额）；
              <el-tag size="mini" type="danger" effect="plain">文案</el-tag> 带占位符的整句模板（如流水明细）。
            </p>
            <p><b>文案模板怎么改：</b>形如 <code>{id}</code> <code>{balance}</code> 的是<b>占位符</b>，保存时自动替换成实际数据或词库叫法（<code>{id}</code>→用户ID、<code>{balance}</code>→“余额”的当前叫法）。<b>改文字时保留 {…} 占位符不动</b>，其余文字可自由增删、重排。每条下方「示例」是套一组样例数据渲染出的效果预览。</p>
            <p class="help-last"><b>保存生效：</b>单条点「保存」或右上「全部保存」；「重置」恢复默认。保存后移动端需刷新或重新进入商城才生效（缓存刷新）。</p>
          </div>
        </el-collapse-item>
      </el-collapse>

      <div v-loading="loading">
        <div class="filter-bar">
          <el-input
            v-model="keyword"
            size="small"
            clearable
            prefix-icon="el-icon-search"
            placeholder="搜索：标准词 / key / 自定义叫法 / 分组名"
            style="width: 340px;"
          />
          <el-radio-group v-model="typeFilter" size="small" class="type-filter">
            <el-radio-button label="all">全部 {{ typeCounts.all }}</el-radio-button>
            <el-radio-button label="noun">名词 {{ typeCounts.noun }}</el-radio-button>
            <el-radio-button label="phrase">短语 {{ typeCounts.phrase }}</el-radio-button>
            <el-radio-button label="title">标题 {{ typeCounts.title }}</el-radio-button>
            <el-radio-button label="template" v-if="typeCounts.template">文案 {{ typeCounts.template }}</el-radio-button>
          </el-radio-group>
          <span class="filter-count" v-if="keyword.trim() || typeFilter !== 'all'">匹配 {{ matchCount }} 项</span>
        </div>

        <div v-for="group in filteredGroups" :key="group.source" class="noun-group">
          <div class="group-title">
            <i :class="group.source === 'core' ? 'el-icon-s-tools' : 'el-icon-s-grid'"></i>
            {{ group.label }}
            <el-tag size="mini" :type="group.source === 'core' ? 'primary' : 'success'" class="src-tag">
              {{ group.source === 'core' ? '核心' : '插件' }}
            </el-tag>
          </div>

          <el-table size="small" :data="group.items" border class="noun-table">
            <el-table-column label="标准词 / 用途" width="280">
              <template slot-scope="{ row }">
                <div class="cell-name">
                  <div class="name-main">
                    <el-tag size="mini" :type="(typeMeta[row.type] || typeMeta.phrase).tag" effect="plain" class="type-tag">{{ (typeMeta[row.type] || typeMeta.phrase).label }}</el-tag>
                    <span class="name-text">{{ row.label || row.default }}</span>
                  </div>
                  <div class="name-sub">
                    <span class="key-tag">{{ row.key }}</span>
                    <span v-if="row.label" class="def-text">默认：{{ row.default }}</span>
                  </div>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="自定义叫法" min-width="420">
              <template slot-scope="{ row }">
                <!-- 文案模板：整句 + 占位符，用多行框，不限 20 字 -->
                <template v-if="row.type === 'template'">
                  <el-input
                    v-model="row.value"
                    type="textarea"
                    :autosize="{ minRows: 2, maxRows: 5 }"
                    maxlength="200"
                    show-word-limit
                    :placeholder="'留空则使用默认：' + row.default"
                    class="tpl-input"
                  />
                  <div class="tpl-box">
                    <div class="tpl-row" v-if="row.example">
                      <span class="tpl-k">示例</span>
                      <span class="tpl-example">{{ row.example }}</span>
                    </div>
                    <div class="tpl-row" v-if="placeholderList(row).length">
                      <span class="tpl-k">占位符</span>
                      <span class="tpl-phs">
                        <code v-for="p in placeholderList(row)" :key="p.token">{{ p.token }}<i v-if="p.desc">{{ p.desc }}</i></code>
                      </span>
                    </div>
                  </div>
                </template>
                <el-input
                  v-else
                  v-model="row.value"
                  size="mini"
                  maxlength="20"
                  :placeholder="'留空则使用默认：' + row.default"
                  style="width: 260px;"
                />
              </template>
            </el-table-column>
            <el-table-column label="实际显示" width="200">
              <template slot-scope="{ row }">
                <span class="preview">{{ row.type === 'template' ? row.example : (row.value || row.default) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="150" align="center">
              <template slot-scope="{ row }">
                <el-button type="text" @click="saveRow(row)">保存</el-button>
                <el-button
                  v-if="row.value"
                  type="text"
                  style="color: #F56C6C;"
                  @click="resetRow(row)"
                >重置</el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>

        <el-empty v-if="!loading && filteredGroups.length === 0" description="没有匹配的词条"></el-empty>

      </div>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'mall-noun',
  data() {
    return {
      loading: false,
      saving: false,
      groups: [],
      keyword: '',
      typeFilter: 'all',
      helpOpen: ['help'], // 顶部使用说明默认展开
      // 类型展示元数据（与后端 NounHelper::TYPE_* 对齐）；缺省兜底短语
      typeMeta: {
        noun: { label: '名词', tag: 'primary' },
        phrase: { label: '短语', tag: 'warning' },
        title: { label: '标题', tag: 'success' },
        template: { label: '文案', tag: 'danger' }
      }
    }
  },
  computed: {
    // 先按类型筛，再按关键词过滤:命中分组名则整组保留,否则按 标准词/key/自定义叫法 过滤条目;空组隐藏
    filteredGroups() {
      const kw = this.keyword.trim().toLowerCase()
      const type = this.typeFilter
      return this.groups
        .map(g => {
          let items = (g.items || [])
          if (type !== 'all') items = items.filter(it => (it.type || 'phrase') === type)
          const labelHit = kw && (g.label || '').toLowerCase().includes(kw)
          if (kw && !labelHit) {
            items = items.filter(it =>
              (it.default || '').toLowerCase().includes(kw) ||
              (it.key || '').toLowerCase().includes(kw) ||
              (it.value || '').toLowerCase().includes(kw)
            )
          }
          return { ...g, items }
        })
        .filter(g => g.items.length > 0)
    },
    matchCount() {
      return this.filteredGroups.reduce((n, g) => n + g.items.length, 0)
    },
    // 各类型条目总数（不受当前筛选影响，用于 chip 计数）
    typeCounts() {
      const c = { all: 0, noun: 0, phrase: 0, title: 0, template: 0 }
      this.groups.forEach(g => (g.items || []).forEach(it => {
        c.all++
        const t = it.type || 'phrase'
        if (c[t] !== undefined) c[t]++
      }))
      return c
    }
  },
  created() {
    this.getList()
  },
  methods: {
    // 本条模板的占位符列表 [{token:'{id}', desc:'对方用户ID'}]：
    // 优先用后端 placeholders 说明；没说明的 token 从默认值提取、只显示 token。
    placeholderList(row) {
      const desc = (row.placeholders && typeof row.placeholders === 'object') ? row.placeholders : {}
      const tokens = [...new Set(String(row.default || '').match(/\{[\w.]+\}/g) || [])]
      // 合并：默认值里的 token + 声明里的 key（去重），token 形如 {id}，desc key 形如 id
      const keys = new Set(tokens.map(t => t.slice(1, -1)))
      Object.keys(desc).forEach(k => keys.add(k))
      return [...keys].map(k => ({ token: '{' + k + '}', desc: desc[k] || '' }))
    },
    getList() {
      this.loading = true
      this.$request({ url: '/mall/mall/noun-list' }).then(res => {
        if (res.code === 0) {
          this.groups = res.data.groups || []
        } else {
          this.$message.error(res.msg)
        }
      }).finally(() => {
        this.loading = false
      })
    },

    saveRow(row) {
      return this.$request({
        url: '/mall/mall/noun',
        method: 'post',
        data: { key: row.key, value: row.value || '' }
      }).then(res => {
        if (res.code === 0) {
          this.$message.success(`「${row.default}」已保存`)
          this.refreshGlobalNouns() // 同步刷新全局 $noun 词表，后台其它页立即用上新叫法
        } else {
          this.$message.error(res.msg)
        }
        return res
      })
    },

    // 词条改动后刷新全局词表（store.noun），避免后台仍显示旧叫法
    refreshGlobalNouns() {
      this.$store.dispatch('noun/loadNouns').catch(() => {})
    },

    resetRow(row) {
      row.value = ''
      this.saveRow(row)
    },

    saveAll() {
      this.saving = true
      const allItems = this.groups.reduce((acc, g) => acc.concat(g.items), [])
      const tasks = allItems.map(row =>
        this.$request({
          url: '/mall/mall/noun',
          method: 'post',
          data: { key: row.key, value: row.value || '' }
        })
      )
      Promise.all(tasks).then(results => {
        const failed = results.filter(r => r.code !== 0)
        if (failed.length) {
          this.$message.warning(`保存完成，${failed.length} 项失败`)
        } else {
          this.$message.success('全部保存成功')
        }
        this.refreshGlobalNouns()
      }).finally(() => {
        this.saving = false
      })
    }
  }
}
</script>

<style scoped>
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.header-left {
  display: flex;
  align-items: baseline;
  min-width: 0;
  margin-right: 12px;
}
.card-header .title {
  font-weight: 600;
  font-size: 15px;
  white-space: nowrap;
}
.card-header .desc {
  margin-left: 12px;
  color: #909399;
  font-size: 12px;
}
/* 顶部使用说明 */
.help-collapse {
  margin-bottom: 16px;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  background: var(--admin-accent-soft);
}
.help-collapse ::v-deep .el-collapse-item__header {
  height: 40px;
  line-height: 40px;
  padding: 0 14px;
  font-size: 13px;
  font-weight: 600;
  color: #303133;
  background: transparent;
  border-bottom: none;
}
.help-collapse ::v-deep .el-collapse-item__wrap {
  background: transparent;
  border-bottom: none;
}
.help-collapse ::v-deep .el-collapse-item__content {
  padding: 0 14px 12px 14px;
}
.help-icon {
  color: var(--admin-accent-text);
  margin-right: 6px;
  font-size: 15px;
}
.help-body {
  font-size: 13px;
  color: #606266;
  line-height: 1.9;
}
.help-body p {
  margin: 0 0 6px;
}
.help-body p.help-last {
  margin-bottom: 0;
}
.help-body code {
  background: #eef1f6;
  color: var(--admin-accent-text);
  padding: 0 5px;
  border-radius: 3px;
  font-family: Menlo, Consolas, monospace;
  margin: 0 1px;
}
.help-body .el-tag {
  margin: 0 2px;
}

.filter-bar {
  margin-bottom: 16px;
  display: flex;
  align-items: center;
}
.type-filter {
  margin-left: 16px;
}
.filter-count {
  margin-left: 12px;
  color: #909399;
  font-size: 12px;
}
.noun-group {
  margin-bottom: 24px;
}
.group-title {
  font-size: 14px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 10px;
}
.group-title i {
  color: var(--admin-accent-text);
  margin-right: 4px;
}
.src-tag {
  margin-left: 8px;
}

/* 单元格顶部对齐，避免长短行错位 */
.noun-table ::v-deep .el-table__cell {
  vertical-align: top;
}

/* 标准词 / 用途 列 */
.cell-name {
  padding: 2px 0;
}
.name-main {
  display: flex;
  align-items: flex-start;
}
.type-tag {
  margin-right: 8px;
  flex: none;
  margin-top: 1px;
}
.name-text {
  font-weight: 600;
  color: #303133;
  word-break: break-all;
  line-height: 1.5;
}
.name-sub {
  margin-top: 6px;
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 4px 8px;
}
.key-tag {
  font-size: 12px;
  color: #909399;
  background: #f4f4f5;
  border-radius: 3px;
  padding: 1px 6px;
  font-family: Menlo, Consolas, monospace;
}
.def-text {
  font-size: 12px;
  color: #c0c4cc;
  word-break: break-all;
}

/* 文案模板编辑区 */
.tpl-input {
  width: 100%;
  max-width: 460px;
}
.tpl-box {
  max-width: 460px;
  margin-top: 6px;
  padding: 8px 10px;
  background: #fafafa;
  border: 1px solid #ebeef5;
  border-radius: 4px;
}
.tpl-row {
  display: flex;
  font-size: 12px;
  line-height: 1.7;
}
.tpl-row + .tpl-row {
  margin-top: 4px;
}
.tpl-k {
  flex: none;
  width: 42px;
  color: #909399;
}
.tpl-example {
  color: #67c23a;
  font-weight: 500;
  word-break: break-all;
}
.tpl-phs {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}
.tpl-phs code {
  background: #fff;
  border: 1px solid #ebeef5;
  color: var(--admin-accent-text);
  padding: 0 5px;
  border-radius: 3px;
  font-family: Menlo, Consolas, monospace;
}
.tpl-phs code i {
  color: #909399;
  font-style: normal;
  font-family: inherit;
  margin-left: 4px;
}
.preview {
  color: var(--admin-accent-text);
  font-weight: 600;
  word-break: break-all;
}
</style>
