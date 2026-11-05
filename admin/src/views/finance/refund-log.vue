<template>
  <div class="app-container">
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <span>退款流水记录</span>
      </div>

      <el-form :model="searchForm" ref="searchForm" :inline="true" label-width="80px" size="small"
               @submit.native.prevent="handleSearch"
      >
        <el-form-item label="订单号" prop="keyword">
          <el-input v-model="searchForm.keyword" placeholder="订单号/退款单号/手机号" clearable
                    style="width: 220px;"></el-input>
        </el-form-item>

        <el-form-item label="用户ID" prop="user_id">
          <el-input v-model="searchForm.user_id" placeholder="请输入用户ID" clearable style="width: 140px;"></el-input>
        </el-form-item>

        <el-form-item label="状态" prop="status">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 120px;">
            <el-option label="待处理" :value="0"></el-option>
            <el-option label="处理中" :value="1"></el-option>
            <el-option label="成功" :value="2"></el-option>
            <el-option label="失败" :value="3"></el-option>
          </el-select>
        </el-form-item>

        <el-form-item label="创建时间" prop="search_date">
          <el-date-picker
            v-model="searchForm.search_date"
            type="datetimerange"
            value-format="yyyy-MM-dd"
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

      <el-table :data="list" style="width: 100%" v-loading="loading" border stripe size="medium" @row-dblclick="openDetail">
        <el-table-column prop="id" label="ID" width="80" align="center"></el-table-column>

        <el-table-column label="用户信息" width="220">
          <template slot-scope="scope">
            <div class="user-info">
              <el-avatar :size="36" :src="scope.row.avatar_url" icon="el-icon-user-solid"></el-avatar>
              <div class="user-details">
                <div class="nickname text-ellipsis">{{ scope.row.nickname || '未知用户' }}</div>
                <div class="user-id">ID: {{ scope.row.user_id }}</div>
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="订单信息" min-width="260">
          <template slot-scope="scope">
            <div class="order-info-cell">
              <div class="row-item">
                <span class="label">订单单号:</span>
                <order-trigger :order-id="scope.row.order_id">
                  <el-button type="text" size="mini" style="padding: 0;">
                    {{ scope.row.order_no }}
                  </el-button>
                </order-trigger>
              </div>
              <div class="row-item sub-text">
                <span class="label">退款单号:</span> <span class="value">{{ scope.row.out_refund_no }}</span>
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="退款金额" align="center" width="140">
          <template slot-scope="scope">
            <span class="refund-amount">- ¥{{ scope.row.refund_price }}</span>
          </template>
        </el-table-column>

        <el-table-column label="状态" align="center" width="150">
          <template slot-scope="scope">
            <el-tag :type="getStatusTag(scope.row.status)" size="small" effect="dark">
              {{ scope.row.status_text }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column prop="source_text" label="来源" align="center" width="120"></el-table-column>

        <!-- 失败的看失败原因;人工处理过的看怎么处理的(备注在「退款失败」处理台上填);成功前失败过的原文只作历史 -->
        <el-table-column label="备注/异常" min-width="200" show-overflow-tooltip>
          <template slot-scope="scope">
            <span v-if="scope.row.status === 3" class="error-text">{{ scope.row.error_msg }}</span>
            <span v-else-if="scope.row.handle_text" class="handle-text">{{ handleNote(scope.row) }}</span>
            <span v-else-if="scope.row.error_msg" class="normal-text">{{ errorText(scope.row) }}</span>
            <span v-else class="normal-text">-</span>
          </template>
        </el-table-column>

        <el-table-column prop="created_at" label="创建时间" width="170" align="center"></el-table-column>

        <el-table-column label="操作" width="90" align="center" fixed="right">
          <template slot-scope="scope">
            <el-button type="text" size="mini" @click="openDetail(scope.row)">详情{{ scope.row.logs.length ? `(${scope.row.logs.length})` : '' }}</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container">
        <el-pagination
          background
          @current-change="handlePageChange"
          :current-page.sync="searchForm.page"
          :page-size="searchForm.limit"
          layout="total, prev, pager, next, jumper"
          :total="pagination ? pagination.total_count : 0">
        </el-pagination>
      </div>
    </el-card>

    <!-- 详情:退款信息 + 人工处理记录(处理完的退款会离开「退款失败」处理台,之后在这里看) -->
    <el-drawer title="退款详情" :visible.sync="detailVisible" size="560px" :wrapper-closable="true">
      <div v-if="current" class="drawer-body">
        <el-descriptions :column="1" size="small" border :label-style="{ width: '100px', whiteSpace: 'nowrap' }">
          <el-descriptions-item label="状态">
            <el-tag :type="getStatusTag(current.status)" size="mini">{{ current.status_text }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="订单号">{{ current.order_no }}</el-descriptions-item>
          <el-descriptions-item label="退款单号">{{ current.out_refund_no }}</el-descriptions-item>
          <el-descriptions-item label="用户">{{ current.nickname || '未知用户' }}(ID: {{ current.user_id }}) {{ current.mobile }}</el-descriptions-item>
          <el-descriptions-item label="退款金额">¥{{ current.refund_price }}</el-descriptions-item>
          <el-descriptions-item label="支付方式">{{ current.payment_name || '—' }}</el-descriptions-item>
          <el-descriptions-item label="来源">{{ current.source_text }}</el-descriptions-item>
          <el-descriptions-item v-if="current.error_msg" :label="current.status === 3 ? '失败原因' : (current.status === 2 ? '之前失败原因' : '上次失败原因')">{{ current.error_msg }}</el-descriptions-item>
          <el-descriptions-item label="已尝试">{{ current.retry_count }} 次</el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ current.created_at }}</el-descriptions-item>
          <el-descriptions-item v-if="current.success_time" label="退款成功时间">{{ current.success_time }}</el-descriptions-item>
        </el-descriptions>
        <refund-handle-logs :logs="current.logs" empty-text="没有人工处理记录" />
      </div>
    </el-drawer>
  </div>
</template>

<script>
// 注意：请确保 src/utils/Export2Excel.js 文件存在，或者替换为你项目中的导出方法
import RefundHandleLogs from './components/RefundHandleLogs'

export default {
  name: 'finance-refund-log',
  components: { RefundHandleLogs },
  data() {
    const initialSearchForm = {
      page: 1,
      limit: 10,
      keyword: '',
      user_id: '',
      status: '', // ''代表全部
      search_date: []
    }
    return {
      loading: false,
      exportLoading: false,
      list: [],
      pagination: null,
      searchForm: {...initialSearchForm},
      initialSearchForm,
      current: null,
      detailVisible: false
    }
  },
  created() {
    this.getList()
  },
  methods: {
    getApiParams(isExport = 0) {
      const params = {...this.searchForm}
      if (isExport) {
        params.is_export = 1
      }
      return params
    },

    async getList() {
      this.loading = true
      try {
        // 请求后端 RefundLogListForm 接口
        const res = await this.$request({
          url: '/mall/finance/refund-log',
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
        this.$message.error(e.message || '数据加载失败')
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
      // 手动重置，因为 resetFields 对非 prop 绑定的值可能无效
      this.searchForm = {...this.initialSearchForm}
      this.getList()
    },

    handlePageChange(page) {
      this.searchForm.page = page
      this.getList()
    },

    openDetail(row, column, event) {
      // 双击的是订单号、按钮这类本身能点的东西时不叠开详情(它们单击已经有动作)
      if (event && event.target.closest && event.target.closest('button, a')) return
      this.current = row
      this.detailVisible = true
    },

    // 失败原文:失败的照原样;成功的是之前失败过的历史,还在退的是上一次的失败(列表、导出同一个说法)
    errorText(row) {
      if (!row.error_msg || row.status === 3) return row.error_msg
      return (row.status === 2 ? '曾失败:' : '上次失败:') + row.error_msg
    },

    // 列表里一句话说最近一次人工处理:人工登记已退款:已在微信商户平台退款 · admin 09-27 17:03
    handleNote(row) {
      const remark = row.handle_remark ? `:${row.handle_remark}` : ''
      return `${row.handle_text}${remark} · ${row.handle_operator} ${row.handle_at.slice(5, 16)}`
    },

    getStatusTag(status) {
      const map = {
        0: 'info',    // 待处理
        1: 'primary', // 处理中
        2: 'success', // 成功
        3: 'danger'   // 失败
      }
      return map[status] || 'info'
    },

    async exportExcel() {
      try {
        await this.$confirm('确认导出当前筛选条件下的记录？', '导出确认', {type: 'warning'})
        this.exportLoading = true

        const res = await this.$request({
          url: '/mall/finance/refund-log',
          method: 'get',
          data: this.getApiParams(1)
        })

        if (res.code === 0 && res.data.list && res.data.list.length > 0) {
          const tHeader = ['ID', '用户昵称', '商户订单号', '退款流水号', '退款金额', '状态', '来源', '异常信息', '处理方式', '处理备注', '处理人', '处理时间', '创建时间']
          const filterVal = ['id', 'nickname', 'order_no', 'out_refund_no', 'refund_price', 'status_text', 'source_text', 'error_msg', 'handle_text', 'handle_remark', 'handle_operator', 'handle_at', 'created_at']

          const exportData = res.data.list.map(item => ({
            ...item,
            refund_price: '-' + item.refund_price, // 导出时显示负号更直观
            error_msg: this.errorText(item)
          }))

          // 调用全局挂载的 $export 或者引入的方法
          if (this.$export) {
            this.$export(tHeader, filterVal, exportData, '退款流水记录');
          } else {
            this.$message.error('导出插件未安装');
          }

        } else {
          this.$message.info('没有可导出的数据')
        }
      } catch (error) {
        if (error !== 'cancel') {
          this.$message.error(error.message || '导出失败')
        }
      } finally {
        this.exportLoading = false
      }
    }
  }
}
</script>

<style scoped lang="scss">
.box-card {
  min-height: calc(100vh - 124px);
}

.user-info {
  display: flex;
  align-items: center;

  .user-details {
    margin-left: 10px;
    overflow: hidden;

    .nickname {
      font-weight: 500;
      color: #333;
      font-size: 13px;
    }

    .user-id {
      font-size: 12px;
      color: #999;
      margin-top: 2px;
    }
  }
}

.order-info-cell {
  font-size: 13px;

  .row-item {
    display: flex;
    align-items: center;
    margin-bottom: 4px;

    &:last-child {
      margin-bottom: 0;
    }

    .label {
      color: #909399;
      width: 65px;
      flex-shrink: 0;
    }

    .value {
      color: #606266;
      font-family: monospace; // 等宽字体显示单号更好看
      &.link-type {
        color: var(--admin-accent-text);
        cursor: pointer;

        &:hover {
          text-decoration: underline;
        }
      }
    }
  }

  .sub-text {
    font-size: 12px;
  }
}

.refund-amount {
  color: #F56C6C; /* 红色代表支出/退款 */
  font-weight: bold;
  font-family: Arial, sans-serif;
}

.error-text {
  color: #F56C6C;
  font-size: 12px;
}

.normal-text {
  color: #909399;
  font-size: 12px;
}

.handle-text {
  color: #606266;
  font-size: 12px;
}

.drawer-body {
  padding: 0 20px 20px;
  overflow-y: auto;
  height: 100%;
  box-sizing: border-box;
}

.pagination-container {
  margin-top: 20px;
  text-align: right;
}

.text-ellipsis {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
