<template>
  <div style="display: inline-block;">
    <el-button
      :type="buttonType"
      :icon="buttonIcon"
      :loading="exportLoading"
      size="small"
      @click="openFieldDialog"
    >{{ buttonText }}</el-button>

    <!-- 字段选择弹窗 -->
    <el-dialog title="选择导出字段" :visible.sync="fieldDialogVisible" width="680px" append-to-body>
      <div class="export-field-selector">
        <div class="selector-toolbar">
          <el-checkbox v-model="checkAll" :indeterminate="isIndeterminate" @change="handleCheckAll">全选</el-checkbox>
          <span class="selected-count">已选 {{ checkedFields.length }} / {{ flatFields.length }} 个字段</span>
        </div>
        <div class="field-groups">
          <div v-for="group in fieldGroups" :key="group.group" class="field-group">
            <div class="group-title">{{ group.group }}</div>
            <el-checkbox-group v-model="checkedFields">
              <el-checkbox
                v-for="f in group.fields"
                :key="f.prop"
                :label="f.prop"
                class="field-checkbox"
              >{{ f.label }}</el-checkbox>
            </el-checkbox-group>
          </div>
        </div>
      </div>
      <span slot="footer">
        <el-button size="small" @click="fieldDialogVisible = false">取消</el-button>
        <el-button size="small" type="primary" :loading="exportLoading" @click="startExport">确定导出</el-button>
      </span>
    </el-dialog>

    <!-- 导出进度弹窗 -->
    <el-dialog
      :title="progressTitle"
      :visible.sync="progressVisible"
      width="400px"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      :show-close="false"
      append-to-body
    >
      <div style="text-align: center; padding: 20px 0;">
        <el-progress
          type="circle"
          :percentage="exportPercentage"
          :status="exportStatus === 'success' ? 'success' : (exportStatus === 'failed' ? 'exception' : '')"
        />
        <div style="margin-top: 15px; font-size: 14px; color: #606266;">{{ exportMsg }}</div>
      </div>
      <span v-if="exportStatus === 'success' || exportStatus === 'failed'" slot="footer" style="text-align: center;">
        <el-button size="small" @click="progressVisible = false">关闭</el-button>
        <el-button v-if="exportStatus === 'success'" type="success" size="small" @click="downloadFile">下载文件</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'AsyncExportButton',
  props: {
    // 按钮
    buttonText: { type: String, default: '导出' },
    buttonType: { type: String, default: 'success' },
    buttonIcon: { type: String, default: 'el-icon-download' },

    // 字段定义：[{ group: '基本信息', fields: [{ prop: 'id', label: 'ID' }, ...] }, ...]
    fieldGroups: { type: Array, required: true },

    // 异步导出提交接口
    exportUrl: { type: String, required: true },
    // 进度查询接口
    statusUrl: { type: String, required: true },

    // 附加搜索参数
    apiParams: { type: Object, default: () => ({}) },

    // 进度弹窗标题
    progressTitle: { type: String, default: '导出进度' }
  },
  data() {
    return {
      // 字段选择
      fieldDialogVisible: false,
      checkAll: true,
      isIndeterminate: false,
      checkedFields: [],

      // 导出状态
      exportLoading: false,
      progressVisible: false,
      exportPercentage: 0,
      exportMsg: '正在准备...',
      exportStatus: 'processing',
      exportToken: '',
      exportFileUrl: '',
      exportTimer: null
    }
  },
  computed: {
    flatFields() {
      const arr = []
      this.fieldGroups.forEach(g => {
        g.fields.forEach(f => arr.push(f))
      })
      return arr
    }
  },
  watch: {
    checkedFields(val) {
      const total = this.flatFields.length
      this.checkAll = val.length === total
      this.isIndeterminate = val.length > 0 && val.length < total
    }
  },
  beforeDestroy() {
    this.clearTimer()
  },
  methods: {
    openFieldDialog() {
      // 每次打开默认全选
      this.checkedFields = this.flatFields.map(f => f.prop)
      this.checkAll = true
      this.isIndeterminate = false
      this.fieldDialogVisible = true
    },

    handleCheckAll(val) {
      this.checkedFields = val ? this.flatFields.map(f => f.prop) : []
      this.isIndeterminate = false
    },

    // 监听勾选变化
    startExport() {
      if (this.checkedFields.length === 0) {
        this.$message.warning('请至少选择一个导出字段')
        return
      }
      this.fieldDialogVisible = false
      this.doExport()
    },

    async doExport() {
      this.exportLoading = true
      try {
        const res = await this.$request({
          url: this.exportUrl,
          method: 'get',
          data: {
            ...this.apiParams,
            export_fields: this.checkedFields.join(',')
          }
        })
        if (res.code === 0) {
          this.exportToken = res.data.token
          this.initProgress()
          this.startPolling()
        } else {
          this.$message.error(res.msg || '提交导出任务失败')
        }
      } catch (e) {
        this.$message.error('提交导出任务异常')
      } finally {
        this.exportLoading = false
      }
    },

    initProgress() {
      this.progressVisible = true
      this.exportPercentage = 0
      this.exportMsg = '任务排队中...'
      this.exportStatus = 'processing'
      this.exportFileUrl = ''
    },

    startPolling() {
      this.clearTimer()
      this.exportTimer = setInterval(async() => {
        try {
          const res = await this.$request({
            url: this.statusUrl,
            method: 'get',
            data: { token: this.exportToken }
          })
          if (res.code === 0 && res.data) {
            this.exportPercentage = res.data.percentage || 0
            this.exportMsg = res.data.msg || '处理中...'
            this.exportStatus = res.data.status

            if (res.data.status === 'success') {
              this.clearTimer()
              this.exportFileUrl = res.data.url
              this.$message.success('导出完成！')
              window.open(this.exportFileUrl)
            } else if (res.data.status === 'failed') {
              this.clearTimer()
              this.$message.error(res.data.msg || '导出失败')
            }
          }
        } catch (e) {
          this.clearTimer()
          this.exportStatus = 'failed'
          this.exportMsg = '网络请求异常'
        }
      }, 1500)
    },

    clearTimer() {
      if (this.exportTimer) {
        clearInterval(this.exportTimer)
        this.exportTimer = null
      }
    },

    downloadFile() {
      if (this.exportFileUrl) {
        window.open(this.exportFileUrl)
      } else {
        this.$message.warning('文件链接无效')
      }
    }
  }
}
</script>

<style scoped>
.export-field-selector {
  max-height: 450px;
  overflow-y: auto;
}
.selector-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 12px;
  border-bottom: 1px solid #EBEEF5;
  margin-bottom: 12px;
}
.selected-count {
  font-size: 12px;
  color: #909399;
}
.field-group {
  margin-bottom: 16px;
}
.group-title {
  font-weight: bold;
  font-size: 13px;
  color: #303133;
  margin-bottom: 8px;
  padding-left: 2px;
}
.field-checkbox {
  width: 140px;
  margin-bottom: 6px !important;
  margin-right: 8px !important;
}
</style>
