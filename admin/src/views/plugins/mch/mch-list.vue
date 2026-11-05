<template>
  <div class="app-container">
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <span>商户管理</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="handleEdit(null)">添加商户</el-button>
      </div>

      <el-alert
        style="margin-bottom: 20px;"
        type="success"
        :closable="false"
      >
        <div slot="title" style="font-size: 14px; display: flex; align-items: center;">
          <i class="el-icon-info" style="margin-right: 5px;"></i>
          商家独立管理后台 PC 端总登录入口：
          <el-link type="primary" :href="mchLoginUrl" target="_blank" style="margin-left: 5px; font-weight: bold; font-size: 14px;">
            {{ mchLoginUrl }}
          </el-link>
        </div>
      </el-alert>

      <mch-filter v-model="searchParams" @search="search"></mch-filter>

      <mch-table
        :list="list"
        :loading="loading"
        @edit="handleEdit"
        @dashboard="openDashboard"
          @review="openReview"
        @change-status="handleStatusChange"
        @hide-status="handleHideStatusChange"
        @delete="handleDelete"
      ></mch-table>

      <div style="text-align: right; margin: 20px 0;">
        <el-pagination
          v-if="pagination"
          background
          :current-page.sync="searchParams.page"
          :page-size="searchParams.limit"
          layout="total, prev, pager, next, jumper"
          :total="pagination.total_count"
          @current-change="pageChange"
        ></el-pagination>
      </div>
    </el-card>

    <mch-edit-drawer ref="editDrawer" @success="getList"></mch-edit-drawer>

    <mch-dashboard-dialog ref="dashboardDialog"></mch-dashboard-dialog>

    <mch-review-drawer
      :visible="reviewDialogVisible"
      :mch="currentMch"
      @close="reviewDialogVisible = false"
      @finish="getList"
    ></mch-review-drawer>


  </div>
</template>

<script>
import MchFilter from './components/MchFilter'
import MchTable from './components/MchTable'
import MchEditDrawer from './components/MchEditDrawer'
import MchDashboardDialog from './components/MchDashboardDialog'
import MchReviewDrawer from './components/MchReviewDrawer'

export default {
  name: 'MchListIndex',
  components: {
    MchFilter,
    MchTable,
    MchEditDrawer,
    MchDashboardDialog,
    MchReviewDrawer
  },
  computed: {
    mchLoginUrl() {
      return window.location.origin + '/mch/'
    }
  },
  data() {
    return {
      loading: false,
      list: [],
      pagination: null,
      searchParams: {
        page: 1,
        limit: 20,
        keyword: '',
        type: '',
        status: '',
        date_range: [],
        repurchase_zone: ''
      },
      reviewDialogVisible: false,
      currentMch: null,
    }
  },
  created() {
    this.getList()
  },
  methods: {
    async getList() {
      this.loading = true
      try {
        const params = {...this.searchParams}
        if (params.date_range && params.date_range.length === 2) {
          params.start_date = params.date_range[0]
          params.end_date = params.date_range[1]
        } else {
          params.start_date = ''
          params.end_date = ''
        }

        const res = await this.$request({
          url: '/plugin/mch/mall/mch/index',
          data: params
        })

        if (res.code === 0) {
          this.list = res.data.list
          this.pagination = res.data.pagination
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {
        console.error(e)
        this.$message.error('加载列表失败')
      } finally {
        this.loading = false
      }
    },

    search() {
      this.searchParams.page = 1
      this.getList()
    },

    pageChange(page) {
      this.searchParams.page = page
      this.getList()
    },

    handleEdit(row) {
      this.$refs.editDrawer.open(row)
    },

    openDashboard(row) {
      this.$refs.dashboardDialog.open(row)
    },

    openReview(row) {
      this.currentMch = row
      this.reviewDialogVisible = true
    },

    handleStatusChange(row, status) {
      const actionText = status === 1 ? '启用' : '禁用'
      this.$confirm(`确定要${actionText}商户 “${row.mch_name}” 吗？`, '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        this.loading = true
        this.$request({
          url: '/plugin/mch/mall/mch/status-change',
          method: 'post',
          data: {
            id: row.id,
            status: status
          }
        }).then(res => {
          if (res.code === 0) {
            this.$message.success('操作成功')
            this.getList()
          } else {
            this.$message.error(res.msg)
          }
        }).finally(() => {
          this.loading = false
        })
      }).catch(() => {
      })
    },

    handleHideStatusChange(row, is_hide) {
      const actionText = is_hide === 1 ? '隐藏' : '显示'
      this.$confirm(`确定要${actionText}商户 “${row.mch_name}” 吗？`, '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        this.$request({
          url: '/plugin/mch/mall/mch/update-hide-status',
          method: 'post',
          data: {
            id: row.id,
            is_hide: is_hide
          }
        }).then(res => {
          if (res.code === 0) {
            this.$message.success('操作成功')
            row.is_hide = is_hide
          } else {
            this.$message.error(res.msg)
          }
        })
      }).catch(() => {
      })
    },

    handleDelete(row) {
      this.$confirm(`此操作将删除商户 “${row.mch_name}”，但数据会保留，之后可在数据库中恢复。是否继续？`, '警告', {
        confirmButtonText: '确定删除',
        cancelButtonText: '取消',
        type: 'error'
      }).then(() => {
        this.loading = true
        this.$request({
          url: '/plugin/mch/mall/mch/destroy',
          method: 'post',
          data: {
            id: row.id
          }
        }).then(res => {
          if (res.code === 0) {
            this.$message.success('删除成功')
            this.getList()
          } else {
            this.$message.error(res.msg)
          }
        }).finally(() => {
          this.loading = false
        })
      }).catch(() => {
      })
    }
  }
}
</script>

<style scoped>
.box-card {
  margin-bottom: 20px;
}
</style>
