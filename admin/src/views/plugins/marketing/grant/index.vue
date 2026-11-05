<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="clearfix"><span>发放流水</span></div>

      <el-form :inline="true" size="small" class="filter-form shrink-0" @submit.native.prevent="search">
        <el-form-item label="来源">
          <el-select v-model="searchForm.source_event" clearable placeholder="全部" @change="search" style="width:130px">
            <el-option label="新人礼包" value="register"></el-option>
            <el-option label="订单返券" value="order_paid"></el-option>
            <el-option label="累计下单" value="order_cumulative"></el-option>
            <el-option label="领券中心" value="claim"></el-option>
            <el-option label="每满循环" value="order_repeat"></el-option>
            <el-option label="新客复购" value="new_customer_rebuy"></el-option>
            <el-option label="邀请首单" value="invite_first_order"></el-option>
            <el-option label="升级奖励" value="level_up"></el-option>
            <el-option label="生日券" value="birthday"></el-option>
            <el-option label="沉睡召回" value="inactive"></el-option>
            <el-option label="定时群发" value="batch"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="活动ID">
          <el-input v-model="searchForm.campaign_id" placeholder="活动ID" clearable style="width:120px" @keyup.enter.native="search"></el-input>
        </el-form-item>
        <el-form-item label="用户">
          <el-input v-model="searchForm.keyword" placeholder="用户ID/昵称/手机" clearable @keyup.enter.native="search"></el-input>
        </el-form-item>
        <el-form-item><el-button type="primary" @click="search">搜索</el-button></el-form-item>
      </el-form>

      <div class="table-wrapper flex-1">
        <div class="absolute-table-container">
          <el-table :data="list" v-loading="loading" border size="small" height="100%" style="width:100%">
            <el-table-column prop="id" label="ID" width="70" align="center"></el-table-column>
            <el-table-column label="用户" min-width="160">
              <template slot-scope="s">
                <div>{{ s.row.nickname }}</div>
                <div class="sub">ID:{{ s.row.user_id }} {{ s.row.mobile }}</div>
              </template>
            </el-table-column>
            <el-table-column prop="campaign_name" label="活动" min-width="140" show-overflow-tooltip></el-table-column>
            <el-table-column label="来源" width="100" align="center">
              <template slot-scope="s"><el-tag size="mini" type="info">{{ s.row.source_text }}</el-tag></template>
            </el-table-column>
            <el-table-column label="奖励" width="130" align="right">
              <template slot-scope="s">
                <span v-if="s.row.reward_type === 'integral'">{{ s.row.amount }} {{ $noun('integral') }}</span>
                <span v-else-if="s.row.reward_type === 'balance'">¥{{ Number(s.row.amount).toFixed(2) }} {{ $noun('balance') }}</span>
                <span v-else>{{ $noun('coupon') }} ¥{{ Number(s.row.face_value).toFixed(2) }}</span>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="100" align="center">
              <template slot-scope="s"><el-tag size="mini" :type="rowStatusType(s.row)">{{ s.row.status_text }}</el-tag></template>
            </el-table-column>
            <el-table-column prop="granted_time" label="发放时间" width="160" align="center"></el-table-column>
          </el-table>
        </div>
      </div>

      <div class="pagination-container shrink-0">
        <el-pagination v-if="pagination" background @current-change="pageChange"
          :current-page="searchForm.page" :page-size="searchForm.limit"
          layout="total, prev, pager, next, jumper" :total="pagination.total_count"></el-pagination>
      </div>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'marketing-grant',
  data() {
    return {
      loading: false, list: [], pagination: null,
      searchForm: { page: 1, limit: 10, source_event: '', campaign_id: '', keyword: '' },
    };
  },
  created() { this.getList(); },
  methods: {
    async getList() {
      this.loading = true;
      try {
        const res = await this.$request({ url: '/plugin/marketing/mall/grant/index', data: this.searchForm });
        if (res.code === 0) { this.list = res.data.list; this.pagination = res.data.pagination; } else this.$message.error(res.msg);
      } finally { this.loading = false; }
    },
    search() { this.searchForm.page = 1; this.getList(); },
    pageChange(p) { this.searchForm.page = p; this.getList(); },
    rowStatusType(row) {
      if (row.status === 2) return 'danger'; // 已回收/已作废
      if (row.reward_type !== 'coupon') return 'success';
      return row.uc_status === 1 ? 'success' : (row.uc_status === 2 ? 'info' : 'warning');
    },
  },
};
</script>

<style lang="scss" scoped>
.app-container { height: calc(100vh - 85px); display: flex; flex-direction: column; overflow: hidden; padding: 15px; box-sizing: border-box; background: #f0f2f5; }
.box-card { flex: 1; display: flex; flex-direction: column; border: none; }
.box-card ::v-deep .el-card__body { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0; }
.shrink-0 { flex-shrink: 0; }
.flex-1 { flex: 1; }
.filter-form { margin-bottom: 10px; }
.table-wrapper { flex: 1; position: relative; min-height: 0; }
.absolute-table-container { position: absolute; top: 0; left: 0; right: 0; bottom: 0; }
.pagination-container { padding-top: 10px; text-align: right; }
.sub { font-size: 12px; color: #909399; }
</style>
