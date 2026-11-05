<template>
  <div>
    <el-drawer
      title=""
      :visible.sync="visible"
      direction="rtl"
      size="600px"
      :append-to-body="true"
      custom-class="error-log-drawer"
    >
      <div slot="title" class="drawer-header">
        <span class="drawer-title">🐛 错误监控</span>
        <div class="drawer-actions">
          <el-radio-group v-model="filter" size="mini" @change="onFilterChange">
            <el-radio-button label="">全部</el-radio-button>
            <el-radio-button label="0">未处理</el-radio-button>
            <el-radio-button label="1">已处理</el-radio-button>
          </el-radio-group>
          <el-button
            size="mini" type="warning" plain
            style="margin-left:12px;"
            :loading="handlingAll"
            @click="handleAll"
          >全部标记已处理</el-button>
        </div>
      </div>

      <div class="drawer-body" v-loading="loading">
        <div v-if="list.length === 0 && !loading" class="empty-block">
          <i class="el-icon-check" style="font-size:40px;color:#67C23A;"></i>
          <p>暂无错误日志</p>
        </div>

        <div v-for="item in list" :key="item.id" class="log-item" :class="{ handled: item.is_handled == 1 }" @click="toggleExpand(item.id)">
          <!-- 头部行 -->
          <div class="log-header">
            <el-tag size="mini" :type="item.level === 'error' ? 'danger' : 'warning'" effect="dark">
              {{ item.level.toUpperCase() }}
            </el-tag>
            <span class="log-category">{{ item.category }}</span>
            <span class="log-count" v-if="item.count > 1" title="重复次数">×{{ item.count }}</span>
            <span class="log-time">{{ item.last_seen_text || item.created_time_text }}</span>
            <div class="log-ops" @click.stop>
              <el-tooltip content="复制完整日志" placement="top">
                <i class="el-icon-document-copy log-op-btn" @click="copyItem(item)"></i>
              </el-tooltip>
              <el-tooltip content="标记已处理" placement="top" v-if="item.is_handled == 0">
                <i class="el-icon-check log-op-btn" @click="handleOne(item)"></i>
              </el-tooltip>
              <el-tooltip content="删除" placement="top">
                <i class="el-icon-delete log-op-btn del" @click="deleteOne(item)"></i>
              </el-tooltip>
              <i class="log-op-btn" :class="expanded[item.id] ? 'el-icon-arrow-up' : 'el-icon-arrow-down'"></i>
            </div>
          </div>

          <!-- 消息摘要 -->
          <div class="log-message">{{ item.message | msgPreview }}</div>

          <!-- URL -->
          <div class="log-url" v-if="item.url">{{ item.url }}</div>

          <!-- 展开详情 -->
          <div class="log-detail" v-if="expanded[item.id]" @click.stop>
            <div class="detail-section">
              <div class="detail-label">
                <span>完整消息</span>
                <el-button type="text" size="mini" icon="el-icon-document-copy" @click="copyText(item.message, '完整消息')">复制</el-button>
              </div>
              <pre class="detail-content">{{ item.message }}</pre>
            </div>
            <div class="detail-section" v-if="item.trace">
              <div class="detail-label">
                <span>调用堆栈</span>
                <el-button type="text" size="mini" icon="el-icon-document-copy" @click="copyText(item.trace, '调用堆栈')">复制</el-button>
              </div>
              <pre class="detail-content">{{ item.trace }}</pre>
            </div>
            <div class="detail-meta">
              首次出现：{{ item.created_time_text }} &nbsp;·&nbsp;
              最后出现：{{ item.last_seen_text }} &nbsp;·&nbsp;
              共触发 {{ item.count }} 次
            </div>
          </div>
        </div>

        <!-- 分页 -->
        <div class="drawer-pagination" v-if="total > limit">
          <el-pagination
            small layout="prev, pager, next"
            :current-page="page"
            :page-size="limit"
            :total="total"
            @current-change="onPageChange"
          />
        </div>
      </div>
    </el-drawer>
  </div>
</template>

<script>
export default {
  name: 'ErrorLogDrawer',
  filters: {
    msgPreview(val) {
      if (!val) return ''
      const first = val.split('\n')[0]
      return first.length > 120 ? first.slice(0, 120) + '…' : first
    }
  },
  data() {
    return {
      visible: false,
      loading: false,
      handlingAll: false,
      list: [],
      total: 0,
      page: 1,
      limit: 15,
      filter: '0',   // 默认只看未处理
      expanded: {},  // { id: true/false }
    }
  },
  methods: {
    show() {
      this.visible = true
      this.page = 1
      this.loadList()
    },
    onFilterChange() {
      this.page = 1
      this.loadList()
    },
    onPageChange(p) {
      this.page = p
      this.loadList()
    },
    toggleExpand(id) {
      this.$set(this.expanded, id, !this.expanded[id])
    },
    async loadList() {
      this.loading = true
      try {
        const params = { page: this.page, limit: this.limit }
        if (this.filter !== '') params.is_handled = this.filter
        const res = await this.$request({ url: '/admin/yii-error-log/index', data: params })
        if (res.code === 0) {
          this.list  = res.data.list
          this.total = res.data.total
        }
      } finally {
        this.loading = false
      }
    },
    async handleOne(item) {
      await this.$request({ url: '/admin/yii-error-log/handle', method: 'POST', data: { id: item.id } })
      item.is_handled = 1
      this.$emit('count-change')
    },
    async handleAll() {
      this.handlingAll = true
      try {
        await this.$request({ url: '/admin/yii-error-log/handle-all', method: 'POST' })
        this.$message.success('已全部标记处理')
        this.loadList()
        this.$emit('count-change')
      } finally {
        this.handlingAll = false
      }
    },
    async deleteOne(item) {
      try {
        await this.$confirm('确认删除该条日志？', '提示', { type: 'warning' })
      } catch { return }
      await this.$request({ url: '/admin/yii-error-log/delete', method: 'POST', data: { id: item.id } })
      this.list = this.list.filter(i => i.id !== item.id)
      this.total--
      this.$emit('count-change')
    },

    // 复制单条日志的完整信息（含级别/分类/URL/消息/堆栈，方便贴给开发同事/AI 排查）
    copyItem(item) {
      const lines = [
        `[${item.level.toUpperCase()}] ${item.category}`,
        `时间：${item.last_seen_text || item.created_time_text}（共 ${item.count} 次）`,
        item.url ? `URL：${item.url}` : null,
        '',
        '── 完整消息 ──',
        item.message || '',
      ];
      if (item.trace) {
        lines.push('', '── 调用堆栈 ──', item.trace);
      }
      this.copyText(lines.filter(l => l !== null).join('\n'), '完整日志');
    },

    // 通用复制：优先用 navigator.clipboard，降级到 textarea + execCommand（兼容非 HTTPS / 老浏览器）
    async copyText(text, label = '内容') {
      if (!text) {
        this.$message.warning(`${label}为空，无需复制`);
        return;
      }
      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(text);
        } else {
          const ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position = 'fixed';
          ta.style.left = '-9999px';
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          document.body.removeChild(ta);
        }
        this.$message.success(`${label}已复制到剪贴板`);
      } catch (e) {
        this.$message.error('复制失败：' + (e.message || e));
      }
    },
  }
}
</script>

<style lang="scss">
.error-log-drawer {
  .el-drawer__header { margin-bottom: 0; padding: 16px 20px 12px; border-bottom: 1px solid #f0f0f0; }
}
</style>

<style lang="scss" scoped>
.drawer-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
  .drawer-title { font-size: 16px; font-weight: 700; color: #303133; }
  .drawer-actions { display: flex; align-items: center; }
}

.drawer-body {
  padding: 16px 20px;
  min-height: 200px;
}

.empty-block {
  text-align: center;
  padding: 60px 0;
  color: #909399;
  p { margin-top: 12px; font-size: 14px; }
}

.log-item {
  border: 1px solid #ebeef5;
  border-radius: 8px;
  padding: 12px 14px;
  margin-bottom: 10px;
  transition: border-color .2s;
  cursor: pointer;
  &:hover { border-color: #c0c4cc; }
  &.handled { opacity: .55; }

  .log-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
    .log-category { font-size: 12px; color: #606266; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .log-count { font-size: 11px; color: #fff; background: #F56C6C; border-radius: 10px; padding: 0 6px; }
    .log-time { font-size: 11px; color: #c0c4cc; flex-shrink: 0; }
    .log-ops {
      display: flex;
      align-items: center;
      gap: 10px;
      .log-op-btn {
        cursor: pointer;
        font-size: 14px;
        color: #909399;
        &:hover { color: var(--admin-accent-text); }
        &.del:hover { color: #F56C6C; }
      }
    }
  }

  .log-message {
    font-size: 13px;
    color: #303133;
    line-height: 1.5;
    word-break: break-all;
  }

  .log-url {
    font-size: 11px;
    color: #909399;
    margin-top: 4px;
    font-family: Consolas, monospace;
  }

  .log-detail {
    margin-top: 10px;
    border-top: 1px dashed #ebeef5;
    padding-top: 10px;
    .detail-section { margin-bottom: 8px; }
    .detail-label {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 11px;
      color: #909399;
      margin-bottom: 4px;
      .el-button { padding: 0; font-size: 12px; }
    }
    .detail-content {
      background: #f8f9fa;
      border-radius: 4px;
      padding: 8px 10px;
      font-size: 12px;
      font-family: Consolas, monospace;
      color: #303133;
      white-space: pre-wrap;
      word-break: break-all;
      max-height: 200px;
      overflow-y: auto;
      margin: 0;
    }
    .detail-meta { font-size: 11px; color: #c0c4cc; margin-top: 8px; }
  }
}

.drawer-pagination {
  text-align: center;
  padding-top: 16px;
}
</style>
