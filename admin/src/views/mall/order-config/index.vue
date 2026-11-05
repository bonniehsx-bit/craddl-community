<template>
  <div class="app-container order-config">
    <el-card shadow="never" v-loading="loading">
      <div slot="header" class="card-head">
        <div class="head-left">
          <span class="title"><i class="el-icon-tickets"></i> 订单列表配置</span>
          <span class="tip">控制 C 端「我的订单」页：顶部状态 Tab 的显隐 / 改名 / 排序，以及本列表展示哪些订单类型。<b>个人中心的订单入口与角标也跟随本页配置</b></span>
        </div>
        <div class="head-actions">
          <el-button type="primary" size="small" :loading="saving" @click="save">保存配置</el-button>
          <el-button size="small" @click="load">重置</el-button>
        </div>
      </div>

      <div class="config-body">
        <!-- 状态 Tab -->
        <div class="section left-pane">
          <div class="section-title">状态 Tab（拖拽排序 · 可改名 · 可隐藏）</div>
          <draggable v-model="config.status_tabs" handle=".drag-handle" animation="200">
            <div v-for="(t, i) in config.status_tabs" :key="t.key || i" class="tab-row">
              <i class="el-icon-rank drag-handle"></i>
              <el-switch v-model="t.enabled" active-color="#13ce66" />
              <el-input v-model="t.name" size="small" maxlength="8" show-word-limit
                class="tab-name" placeholder="Tab 名称" />
              <span class="tab-meta">筛选：{{ statusText(t.status) }}</span>
            </div>
          </draggable>
          <div class="muted">
            建议保留「全部」；隐藏后该 Tab 不在 C 端展示。名称留空保存时会回退默认。<br>
            <b>连带影响：</b>个人中心（我的）里指向该状态的订单入口会一并隐藏 —— 否则用户点进去会落到「全部」，看起来像坏了。
          </div>
        </div>

        <!-- 订单类型 -->
        <div class="section right-pane">
          <div class="section-title">展示哪些订单类型</div>
          <div class="muted" style="margin-bottom: 12px;">
            不勾选 = 显示<b>全部</b>类型（向后兼容）。勾选后，本列表只展示选中类型的订单；其余类型（如各业务插件订单）不再混入。<br>
            <b>连带影响：</b>个人中心的订单角标只统计这里选中的类型 —— 保证「待发货 3」点进去就是 3 条，数字不会对不上。
          </div>
          <el-checkbox-group v-model="config.included_types" class="types-grid-container">
            <div v-for="g in groupedTypes" :key="g.label" class="type-group">
              <div class="group-label">{{ g.label }}</div>
              <div class="group-content">
                <el-checkbox v-for="t in g.items" :key="t.key" :label="t.key" border class="type-cb">{{ t.name }}</el-checkbox>
              </div>
            </div>
          </el-checkbox-group>
          <div class="muted" v-if="!types.length" style="margin-top: 10px;">暂无可选类型（插件未声明或未授权本商城）。</div>
        </div>
      </div>

      <!-- Footer actions moved to card header -->
    </el-card>
  </div>
</template>

<script>
import draggable from 'vuedraggable'

export default {
  name: 'MallOrderConfig',
  components: { draggable },
  data() {
    return {
      loading: false,
      saving: false,
      config: { status_tabs: [], included_types: [] },
      types: []
    }
  },
  computed: {
    // 订单类型按「声明它的插件」分组,后台一眼看清归属
    groupedTypes() {
      const map = {}
      const order = []
      ;(this.types || []).forEach(t => {
        const k = t.plugin_label || t.plugin || '其他'
        if (!map[k]) { map[k] = []; order.push(k) }
        map[k].push(t)
      })
      return order.map(k => ({ label: k, items: map[k] }))
    }
  },
  created() {
    this.load()
  },
  methods: {
    load() {
      this.loading = true
      this.$request({ url: '/mall/order-config/config', method: 'get' }).then(res => {
        this.loading = false
        if (res.code === 0) {
          const c = res.data.config || {}
          this.config = {
            status_tabs: Array.isArray(c.status_tabs) ? c.status_tabs : [],
            included_types: Array.isArray(c.included_types) ? c.included_types : []
          }
          this.types = res.data.types || []
        } else {
          this.$message.error(res.msg || '加载失败')
        }
      }).catch(() => { this.loading = false })
    },
    statusText(s) {
      const m = { 0: '全部', 1: '待付款', 2: '待发货/使用', 3: '待收货', 4: '待评价', 5: '关闭/退款' }
      return m[s] !== undefined ? m[s] : ('状态 ' + s)
    },
    save() {
      this.saving = true
      this.$request({
        url: '/mall/order-config/config',
        method: 'post',
        data: { config: this.config }
      }).then(res => {
        this.saving = false
        if (res.code === 0) {
          this.$message.success('保存成功')
        } else {
          this.$message.error(res.msg || '保存失败')
        }
      }).catch(() => { this.saving = false })
    }
  }
}
</script>

<style scoped>
.order-config .card-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.order-config .card-head .head-left {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.order-config .card-head .title { font-weight: bold; font-size: 15px; color: #303133; }
.order-config .card-head .tip { color: #909399; font-size: 13px; margin: 0; }
.order-config .card-head .head-actions {
  display: flex;
  gap: 10px;
  flex-shrink: 0;
}

.section { margin-bottom: 30px; }
.config-body .section { margin-bottom: 0; }

.config-body {
  display: grid;
  grid-template-columns: 4fr 6fr;
  gap: 30px;
  align-items: start;
}

@media (max-width: 1200px) {
  .config-body {
    grid-template-columns: 1fr;
  }
}

.left-pane {
  border-right: 1px solid #f0f2f5;
  padding-right: 30px;
}

@media (max-width: 1200px) {
  .left-pane {
    border-right: none;
    padding-right: 0;
    margin-bottom: 24px;
  }
}
.section-title {
  font-weight: bold; font-size: 14px; color: #303133; margin-bottom: 16px;
  padding-left: 10px; border-left: 3px solid var(--admin-accent-text);
}

.tab-row {
  display: flex; align-items: center; gap: 14px;
  padding: 10px 14px; margin-bottom: 10px;
  background: #f7f9fc; border: 1px solid #ebeef5; border-radius: 6px;
}
.tab-row .drag-handle { cursor: move; color: #c0c4cc; font-size: 16px; }
.tab-row .tab-name { width: 200px; }
.tab-row .tab-meta { color: #909399; font-size: 12px; }

.types-grid-container {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
  margin-top: 15px;
}

@media (max-width: 768px) {
  .types-grid-container {
    grid-template-columns: 1fr;
  }
}

.type-group {
  background: #fcfdfe;
  border: 1px solid #ebeef5;
  border-radius: 6px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: all 0.2s;
}

.type-group:hover {
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.04);
  border-color: #dcdfe6;
}

.type-group .group-label {
  font-size: 13px;
  color: #303133;
  font-weight: 600;
  padding-bottom: 8px;
  border-bottom: 1px dashed #e4e7ed;
  margin: 0;
}

.type-group .group-content {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.type-cb {
  width: 100%;
  margin: 0 !important;
  box-sizing: border-box;
}

.muted { color: #c0c4cc; font-size: 12px; line-height: 1.6; }

</style>
