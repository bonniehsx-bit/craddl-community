<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="page-header">
        <div class="page-title">
          <i class="el-icon-s-finance"></i>
          <span>分销佣金记录</span>
        </div>
      </div>

      <el-form :inline="true" :model="search" size="small" class="filter-form shrink-0" @submit.native.prevent>
        <el-form-item label="关键词">
          <el-input v-model="search.keyword" placeholder="用户ID/昵称/订单号" clearable style="width: 200px;"
                    @clear="searchData" @keyup.enter.native="searchData" />
        </el-form-item>
        <el-form-item label="佣金状态">
          <el-select v-model="search.status" clearable placeholder="全部状态" style="width: 120px;" @change="searchData">
            <el-option v-for="item in statusOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="申请日期">
          <el-date-picker v-model="search.date_range" type="daterange" value-format="yyyy-MM-dd"
                          range-separator="至" start-placeholder="开始" end-placeholder="结束"
                          clearable @change="searchData" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="searchData">查询</el-button>
          <el-button icon="el-icon-refresh" @click="resetSearch">重置</el-button>
          <el-button icon="el-icon-download" @click="exportData">导出</el-button>
        </el-form-item>
      </el-form>

      <div class="table-wrapper flex-1">
        <div class="absolute-table-container">
          <el-table :data="list" v-loading="loading" border size="small" height="100%"
                    :header-cell-style="{ background: '#F5F7FA', color: '#606266', fontSize: '13px' }"
                    :cell-style="{ fontSize: '13px' }">
            <el-table-column prop="id" label="ID" width="70" align="center" />
            <el-table-column prop="order_no" label="来源订单号" width="200" show-overflow-tooltip />
            <el-table-column label="获得者" width="200">
              <template slot-scope="scope">
                <div style="display: flex; align-items: center; gap: 8px;">
                  <el-avatar shape="square" :size="32" :src="scope.row.user_avatar" style="flex-shrink: 0;" />
                  <div>
                    <div style="font-weight: 500;">{{ scope.row.user_nickname }}</div>
                    <div style="font-size: 12px; color: #909399;">ID: {{ scope.row.user_id }}</div>
                  </div>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="购买者" width="160">
              <template slot-scope="scope">
                <div style="font-weight: 500;">{{ scope.row.buy_user_nickname }}</div>
                <div style="font-size: 12px; color: #909399;">ID: {{ scope.row.buy_user_id }}</div>
              </template>
            </el-table-column>
            <el-table-column label="触发商品" width="140" show-overflow-tooltip>
              <template slot-scope="scope">
                <span style="font-size: 12px; color: #606266;">{{ scope.row.goods_name || '-' }}</span>
              </template>
            </el-table-column>
            <el-table-column label="分销层级" width="100" align="center">
              <template slot-scope="scope">
                <el-tag size="mini" type="info">{{ scope.row.tier_name || (scope.row.level + '级分销') }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="分销商等级" width="100" align="center">
              <template slot-scope="scope">
                <el-tag v-if="scope.row.dist_level_name" size="mini" type="warning">{{ scope.row.dist_level_name }}</el-tag>
                <span v-else style="color: #C0C4CC;">-</span>
              </template>
            </el-table-column>
            <el-table-column label="计算基数" width="100" align="right">
              <template slot-scope="scope">¥{{ scope.row.calculation_price }}</template>
            </el-table-column>
            <el-table-column label="分销比例" width="90" align="center">
              <template slot-scope="scope">{{ scope.row.commission_rate }}%</template>
            </el-table-column>
            <el-table-column label="佣金" width="100" align="right">
              <template slot-scope="scope">
                <span style="color: #F56C6C; font-weight: bold;">¥{{ scope.row.commission_amount }}</span>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="100" align="center">
              <template slot-scope="scope">
                <el-tag size="mini" :type="getStatusTagType(scope.row.status)" effect="dark">{{ scope.row.status_text }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" label="创建时间" width="155" align="center" />
            <el-table-column prop="settled_at" label="结算时间" width="155" align="center" />
            <el-table-column label="操作" width="130" align="center" fixed="right">
              <template slot-scope="scope">
                <template v-if="scope.row.status === 0">
                  <el-button type="text" size="mini" @click="settleOne(scope.row)">结算</el-button>
                  <el-button type="text" size="mini" style="color:#F56C6C;" @click="invalidOne(scope.row)">作废</el-button>
                </template>
                <span v-else style="color:#C0C4CC;">-</span>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </div>

      <div class="pagination-container shrink-0">
        <el-pagination v-if="pagination" background layout="total, prev, pager, next, jumper"
                       @current-change="handlePageChange"
                       :current-page="currentPage"
                       :page-size="pagination.page_size || pagination.pageSize || 20"
                       :total="pagination.total_count || 0" />
      </div>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'DistributionCommisionList',
  data() {
    return {
      loading: false,
      currentPage: 1,
      search: { keyword: '', status: '', date_range: [] },
      list: [],
      pagination: null,
      statusMap: {},
    }
  },
  computed: {
    statusOptions() {
      return Object.keys(this.statusMap).map(key => ({ value: key, label: this.statusMap[key] }));
    }
  },
  created() { this.fetchData(1); },
  methods: {
    getApiParams(page) {
      const params = { page, keyword: this.search.keyword, status: this.search.status };
      if (this.search.date_range && this.search.date_range.length === 2) {
        params.date_start = this.search.date_range[0];
        params.date_end   = this.search.date_range[1];
      }
      return params;
    },
    fetchData(page = 1) {
      this.loading = true;
      this.currentPage = page;
      this.$request({ url: 'plugin/distribution/mall/distribution/index', method: 'get', data: this.getApiParams(page) })
        .then(response => {
          if (response.code === 0) {
            const { list, pagination, status_map } = response.data;
            this.list = list;
            this.pagination = { ...pagination, current_page: page };
            this.statusMap = status_map;
          } else {
            this.$message.error(response.msg);
          }
        }).finally(() => { this.loading = false; });
    },
    searchData() { this.fetchData(1); },
    exportData() {
      // 走 download()：POST + blob 且带 x-admin-token / x-mall-id，
      // window.open 不带请求头会被判未登录
      const params = this.getApiParams(1);
      delete params.page;
      const d = new Date(); const p = n => String(n).padStart(2, '0');
      const stamp = `${d.getFullYear()}${p(d.getMonth() + 1)}${p(d.getDate())}${p(d.getHours())}${p(d.getMinutes())}${p(d.getSeconds())}`;
      this.download('plugin/distribution/mall/distribution/export', params, `分销佣金明细_${stamp}.csv`);
    },
    settleOne(row) {
      this.$confirm(`确认结算这笔 ¥${row.commission_amount} 的分销佣金？结算后将实际发放到分销员账户。`, '手动结算', { type: 'warning' })
        .then(() => this.$request({ url: 'plugin/distribution/mall/distribution/settle', method: 'post', data: { log_id: row.id } }))
        .then(res => {
          if (!res) return;
          if (res.code === 0) { this.$message.success(res.msg || '结算成功'); this.fetchData(); }
          else { this.$message.error(res.msg || '结算失败'); }
        })
        .catch(() => {});
    },
    invalidOne(row) {
      this.$confirm('确认作废这笔待结算佣金？作废后不再发放。', '作废佣金', { type: 'warning' })
        .then(() => this.$request({ url: 'plugin/distribution/mall/distribution/invalid', method: 'post', data: { log_id: row.id } }))
        .then(res => {
          if (!res) return;
          if (res.code === 0) { this.$message.success(res.msg || '已作废'); this.fetchData(); }
          else { this.$message.error(res.msg || '作废失败'); }
        })
        .catch(() => {});
    },
    resetSearch() { this.search = { keyword: '', status: '', date_range: [] }; this.fetchData(1); },
    handlePageChange(page) { this.fetchData(page); },
    getStatusTagType(status) {
      return { 0: 'warning', 1: 'success', 2: 'danger' }[status] || 'info';
    },
  }
}
</script>

<style scoped lang="scss">
.app-container {
  height: calc(100vh - 85px); width: 100%; background-color: #f0f2f5;
  padding: 15px; box-sizing: border-box; display: flex; flex-direction: column; overflow: hidden;
}
.box-card {
  flex: 1; display: flex; flex-direction: column; border: none; border-radius: 6px;
  ::v-deep .el-card__header { padding: 10px 15px; border-bottom: 1px solid #ebeef5; }
  ::v-deep .el-card__body { flex: 1; display: flex; flex-direction: column; overflow: hidden; padding: 12px 15px; min-height: 0; }
}
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-title { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; color: #303133; i { color: var(--admin-accent-text); font-size: 18px; } }
.filter-form { flex-shrink: 0; margin-bottom: 8px; ::v-deep .el-form-item { margin-bottom: 8px; } }
.shrink-0 { flex-shrink: 0; }
.flex-1   { flex: 1; }
.table-wrapper { flex: 1; position: relative; min-height: 0; }
.absolute-table-container { position: absolute; top: 0; left: 0; right: 0; bottom: 0; }
.pagination-container { padding-top: 10px; text-align: right; }
</style>
