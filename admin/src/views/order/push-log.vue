<template>
  <div class="app-container">
    <el-card shadow="never">
      <div slot="header" class="clearfix">
        <span>微信履约推送记录</span>
        <span style="float: right; font-size: 13px; color: #909399;">近30天 · 仅微信支付订单</span>
      </div>

      <!-- 筛选栏 -->
      <div style="margin-bottom: 15px;">
        <el-form :inline="true" size="small">
          <el-form-item>
            <el-input v-model="search.keyword" placeholder="订单号/交易号" clearable style="width: 220px;"
              @keyup.enter.native="handleSearch" @clear="handleSearch"></el-input>
          </el-form-item>
          <el-form-item>
            <el-select v-model="search.push_status" placeholder="推送状态" clearable style="width: 130px;" @change="handleSearch">
              <el-option label="待推送" :value="0"></el-option>
              <el-option label="已成功" :value="1"></el-option>
              <el-option label="推送失败" :value="2"></el-option>
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" icon="el-icon-search" @click="handleSearch">搜索</el-button>
          </el-form-item>
        </el-form>
      </div>

      <!-- 数据表格 -->
      <el-table :data="list" v-loading="loading" border stripe size="small"
        :header-cell-style="{ background: '#F5F7FA', color: '#606266' }">

        <el-table-column type="expand">
          <template slot-scope="{ row }">
            <div style="padding: 10px 20px; font-size: 13px;">
              <div v-if="row.push_errors" style="margin-bottom: 8px;">
                <span style="color: #909399;">错误信息：</span>
                <span style="color: #F56C6C;">{{ row.push_errors }}</span>
              </div>
              <div v-if="row.push_wechat_params">
                <span style="color: #909399;">推送参数：</span>
                <pre style="background: #2d2d2d; color: #ccc; padding: 10px; border-radius: 4px; font-size: 12px; max-height: 200px; overflow: auto; margin-top: 5px;">{{ formatJson(row.push_wechat_params) }}</pre>
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="订单号" min-width="200">
          <template slot-scope="{ row }">
            <div style="font-family: monospace; font-size: 13px;">{{ row.order_no }}</div>
            <div style="font-size: 11px; color: #909399;">{{ row.pay_time_text }}</div>
          </template>
        </el-table-column>

        <el-table-column label="交易号" min-width="220" prop="transaction_id" show-overflow-tooltip>
          <template slot-scope="{ row }">
            <span style="font-family: monospace; font-size: 12px; color: #606266;">{{ row.transaction_id || '--' }}</span>
          </template>
        </el-table-column>

        <el-table-column label="金额" width="90" align="center">
          <template slot-scope="{ row }">
            <span style="font-weight: 600;">¥{{ row.pay_price }}</span>
          </template>
        </el-table-column>

        <el-table-column label="订单状态" width="90" align="center">
          <template slot-scope="{ row }">
            <span>{{ row.order_status_text }}</span>
          </template>
        </el-table-column>

        <el-table-column label="推送状态" width="110" align="center">
          <template slot-scope="{ row }">
            <el-tag :type="pushTagType(row.is_push_wechat)" size="small" effect="dark">
              {{ row.push_status_text }}
            </el-tag>
            <div v-if="row.push_retry_count > 0" style="font-size: 11px; color: #909399; margin-top: 2px;">
              已重试 {{ row.push_retry_count }} 次
            </div>
          </template>
        </el-table-column>

        <el-table-column label="操作" width="100" align="center">
          <template slot-scope="{ row }">
            <el-button v-if="row.can_retry" type="text" size="small" icon="el-icon-refresh"
              @click="handleRetry(row)">重新推送</el-button>
            <span v-else style="color: #67C23A; font-size: 12px;">
              <i class="el-icon-check"></i> 已完成
            </span>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div style="margin-top: 15px; text-align: right;" v-if="pagination">
        <el-pagination background small layout="total, prev, pager, next, jumper"
          :current-page.sync="search.page" :page-size="search.limit" :total="pagination.total_count"
          @current-change="loadData">
        </el-pagination>
      </div>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'OrderPushLog',
  data() {
    return {
      loading: false,
      list: [],
      search: { keyword: '', push_status: '', page: 1, limit: 20 },
      pagination: null
    }
  },
  created() {
    this.loadData();
  },
  methods: {
    async loadData() {
      this.loading = true;
      try {
        const res = await this.$request({ url: '/mall/order/order-push-log', method: 'get', data: this.search });
        if (res.code === 0) {
          this.list = res.data.list || [];
          this.pagination = res.data.pagination;
        }
      } finally {
        this.loading = false;
      }
    },
    handleSearch() {
      this.search.page = 1;
      this.loadData();
    },
    async handleRetry(row) {
      try {
        await this.$confirm('确认将该记录重置为待推送？系统将在1分钟内自动重试。', '重新推送', { type: 'info' });
        const res = await this.$request({ url: '/mall/order/push-retry', method: 'post', data: { id: row.id } });
        if (res.code === 0) {
          this.$message.success(res.msg);
          this.loadData();
        } else {
          this.$message.error(res.msg);
        }
      } catch {}
    },
    pushTagType(status) {
      return { 0: 'warning', 1: 'success', 2: 'danger' }[status] || 'info';
    },
    formatJson(str) {
      try {
        return JSON.stringify(JSON.parse(str), null, 2);
      } catch {
        return str || '无';
      }
    }
  }
}
</script>
