<template>
  <div class="app-container">
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <span>余额记录</span>
      </div>

      <el-form :model="searchForm" ref="searchForm" :inline="true" label-width="80px" size="small"
               @submit.native.prevent="handleSearch"
      >
        <el-form-item label="用户昵称" prop="nickname">
          <el-input v-model="searchForm.nickname" placeholder="请输入用户昵称" clearable></el-input>
        </el-form-item>
        <el-form-item label="用户ID" prop="user_id">
          <el-input v-model="searchForm.user_id" placeholder="请输入用户ID" clearable></el-input>
        </el-form-item>
        <el-form-item label="关键词" prop="keyword">
          <el-input v-model="searchForm.keyword" placeholder="搜索备注/标题" clearable></el-input>
        </el-form-item>

        <el-form-item label="收支类型" prop="type">
          <el-select v-model="searchForm.type" placeholder="请选择" clearable style="width: 150px;">
            <el-option label="全部" value=""></el-option>
            <el-option label="收入" :value="1"></el-option>
            <el-option label="支出" :value="0"></el-option>
          </el-select>
        </el-form-item>

        <el-form-item label="创建时间" prop="search_date">
          <el-date-picker
            v-model="searchForm.search_date"
            type="datetimerange"
            value-format="yyyy-MM-dd HH:mm:ss"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            :default-time="['00:00:00', '23:59:59']"
          >
          </el-date-picker>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="handleSearch">搜索</el-button>
          <el-button icon="el-icon-refresh" @click="resetSearch">重置</el-button>
          <el-button type="success" icon="el-icon-download" @click="exportExcel" :loading="exportLoading">导出
          </el-button>
        </el-form-item>
      </el-form>

      <el-table :data="list" style="width: 100%" v-loading="loading" border stripe>
        <el-table-column prop="id" label="ID" width="80" align="center"></el-table-column>
        <el-table-column label="用户信息" width="250">
          <template slot-scope="scope">
            <div class="user-info">
              <el-avatar :size="40" :src="scope.row.avatar_url"></el-avatar>
              <div class="user-details">
                <div class="nickname">{{ scope.row.nickname }}</div>
                <div class="user-id">ID: {{ scope.row.user_id }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="变动金额" align="center" width="120">
          <template slot-scope="scope">
            <span :class="scope.row.type === 1 ? 'money-income' : 'money-expense'">
              {{ scope.row.type === 1 ? '+' : '-' }} {{ scope.row.money }}
            </span>
          </template>
        </el-table-column>
        <el-table-column label="当前余额" prop="current" align="center" width="120"></el-table-column>
        <el-table-column label="类型" align="center" width="100">
          <template slot-scope="scope">
            <el-tag :type="scope.row.type === 1 ? 'success' : 'warning'" size="small" effect="dark">
              {{ scope.row.type === 1 ? '收入' : '支出' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="title" label="标题" min-width="180" show-overflow-tooltip></el-table-column>
        <el-table-column prop="content" label="详情" min-width="200" show-overflow-tooltip></el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" align="center"></el-table-column>
      </el-table>

      <div style="text-align: right; margin-top: 20px;">
        <el-pagination
          v-if="pagination"
          background
          layout="total , prev, pager, next, jumper"
          :page-count="pagination.page_count"
          :total="pagination.total_count"
          :current-page="pagination.current_page"
          @current-change="pageChange"
        ></el-pagination>
      </div>
    </el-card>

    <el-dialog
      title="余额记录导出进度"
      :visible.sync="exportVisible"
      width="400px"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      :show-close="false"
    >
      <div style="text-align: center; padding: 20px 0;">
        <el-progress
          type="circle"
          :percentage="exportPercentage"
          :status="exportStatus === 'success' ? 'success' : (exportStatus === 'failed' ? 'exception' : '')"
        ></el-progress>
        <div style="margin-top: 15px; font-size: 14px; color: #606266;">{{ exportMsg }}</div>
      </div>
      <span slot="footer" class="dialog-footer" v-if="exportStatus === 'success' || exportStatus === 'failed'">
        <el-button type="primary" size="small" @click="exportVisible = false">关闭窗口</el-button>
        <el-button v-if="exportStatus === 'success'" type="success" size="small"
                   @click="downloadFile">下载文件</el-button>
      </span>
    </el-dialog>

  </div>
</template>

<script>
export default {
  name: 'finance-balance-log',
  data() {
    const initialSearchForm = {
      page: 1,
      keyword: '',
      user_id: '',
      nickname: '',
      type: '', // [新增] 默认为空，表示全部
      search_date: []
    }
    return {
      // 列表相关
      loading: false,
      list: [],
      pagination: null,
      searchForm: {...initialSearchForm},
      initialSearchForm,

      // 导出相关
      exportLoading: false,
      exportVisible: false,
      exportPercentage: 0,
      exportMsg: '正在准备...',
      exportStatus: 'processing',
      exportToken: '',
      exportFileUrl: '',
      exportTimer: null
    }
  },
  created() {
    if (this.$route.query.user_id) {
      this.searchForm.user_id = this.$route.query.user_id
    }
    this.getList()
  },
  beforeDestroy() {
    this.clearExportTimer()
  },
  methods: {
    // API参数处理
    getApiParams(isExport = 0) {
      const params = {...this.searchForm}
      if (isExport) {
        params.is_export = 1
      }
      return params
    },

    // 获取列表
    async getList() {
      this.loading = true
      try {
        const res = await this.$request({
          url: '/mall/finance/balance-log',
          method: 'get',
          data: this.getApiParams()
        })
        if (res.code === 0) {
          this.list = res.data.list
          this.pagination = res.data.pagination
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {
        this.$message.error('数据加载失败')
      } finally {
        this.loading = false
      }
    },

    handleSearch() {
      this.searchForm.page = 1
      this.getList()
    },

    resetSearch() {
      this.$refs.searchForm.resetFields()
      // 注意：resetFields 只会重置 prop 绑定的字段，且需要 prop 对应的初始值正确
      // 这里手动覆盖确保万无一失
      this.searchForm = {...this.initialSearchForm}
      this.getList()
    },

    pageChange(page) {
      this.searchForm.page = page
      this.getList()
    },

    // --- 导出核心逻辑 (异步) ---

    async exportExcel() {
      try {
        await this.$confirm('确认导出当前筛选条件下的所有记录？', '导出确认')
        this.exportLoading = true

        // 1. 请求异步导出接口
        const res = await this.$request({
          url: '/mall/finance/balance-log-async-export', // 对应后端 Action
          method: 'get',
          data: this.getApiParams()
        })

        if (res.code === 0) {
          // 2. 获取 Token 并启动轮询
          this.exportToken = res.data.token
          this.initExportDialog()
          this.startExportPolling()
        } else {
          this.$message.error(res.msg)
        }
      } catch (error) {
        if (error !== 'cancel') {
          this.$message.error('提交导出任务失败')
        }
      } finally {
        this.exportLoading = false
      }
    },

    initExportDialog() {
      this.exportVisible = true
      this.exportPercentage = 0
      this.exportMsg = '正在提交任务...'
      this.exportStatus = 'processing'
      this.exportFileUrl = ''
    },

    startExportPolling() {
      this.clearExportTimer()

      this.exportTimer = setInterval(async () => {
        try {
          const res = await this.$request({
            url: '/mall/finance/balance-log-export-status', // 对应后端 Action
            method: 'get',
            data: {token: this.exportToken}
          })

          if (res.code === 0 && res.data) {
            const data = res.data
            this.exportPercentage = data.percentage || 0
            this.exportMsg = data.msg || '正在处理中...'
            this.exportStatus = data.status

            if (data.status === 'success') {
              this.clearExportTimer()
              this.exportFileUrl = data.url
              this.$message.success('导出完成！')
              // 自动弹出下载
              window.open(this.exportFileUrl)
            } else if (data.status === 'failed') {
              this.clearExportTimer()
              this.$message.error(data.msg || '导出失败')
            }
          }
        } catch (e) {
          this.clearExportTimer()
          this.exportStatus = 'failed'
          this.exportMsg = '网络请求异常'
        }
      }, 1500)
    },

    clearExportTimer() {
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
.box-card {
  min-height: calc(100vh - 124px);
}

.user-info {
  display: flex;
  align-items: center;
}

.user-details {
  margin-left: 10px;
}

.nickname {
  font-weight: 500;
  color: #303133;
}

.user-id {
  font-size: 12px;
  color: #909399;
}

.money-income {
  color: #67C23A;
  font-weight: bold;
}

.money-expense {
  color: #F56C6C;
  font-weight: bold;
}

.dialog-footer {
  text-align: center;
}
</style>
