<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="page-header">
        <div class="page-title">
          <i class="el-icon-tickets"></i>
          <span>充值记录</span>
        </div>
      </div>

      <el-form :inline="true" size="small" :model="searchForm" @submit.native.prevent class="filter-form">
        <el-form-item label="关键词">
          <el-input v-model="searchForm.keyword" placeholder="订单号 / 用户昵称" clearable
                    style="width: 200px;" @clear="search" @keyup.enter.native="search" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 130px;" @change="search">
            <el-option label="待支付" :value="0" />
            <el-option label="已到账" :value="1" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="search">查询</el-button>
          <el-button icon="el-icon-refresh" @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>

      <el-table v-loading="loading" :data="list" border size="small">
        <el-table-column label="用户" min-width="150">
          <template slot-scope="{ row }">
            <div class="user-cell">
              <el-avatar v-if="row.avatar_url" :size="26" :src="row.avatar_url" />
              <span class="nickname">{{ row.nickname }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="档位" prop="plan_title" min-width="130" />
        <el-table-column label="实付" width="100" align="right">
          <template slot-scope="{ row }">¥{{ row.pay_amount }}</template>
        </el-table-column>
        <el-table-column label="到账" width="160" align="right">
          <template slot-scope="{ row }">
            {{ row.total_gain }} {{ row.asset_label }}
            <el-tag v-if="Number(row.gift_amount) > 0" type="warning" size="mini" style="margin-left:4px;">
              含送{{ row.gift_amount }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="订单号" prop="order_no" width="180" />
        <el-table-column label="状态" width="90" align="center">
          <template slot-scope="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'info'" size="mini">{{ row.status_text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="下单时间" prop="created_at" width="150" align="center" />
        <el-table-column label="到账时间" prop="credited_at" width="150" align="center" />
        <el-table-column label="操作" width="110" align="center">
          <template slot-scope="{ row }">
            <el-button v-if="row.status === 0" type="text" size="mini" @click="handleRecredit(row)">
              重新发放
            </el-button>
            <span v-else class="muted">—</span>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-if="count > 0"
        style="margin-top: 16px; text-align: right;"
        background
        layout="total, prev, pager, next"
        :total="count"
        :page-size="limit"
        :current-page.sync="page"
        @current-change="load"
      />
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'WithdrawRechargeOrder',
  data() {
    return {
      loading: false,
      list: [],
      count: 0,
      page: 1,
      limit: 20,
      searchForm: { keyword: '', status: '' }
    }
  },
  created() {
    this.load()
  },
  methods: {
    load() {
      this.loading = true
      this.$request({
        url: '/plugin/withdraw/admin/recharge/orders',
        method: 'get',
        data: Object.assign({ page: this.page, limit: this.limit }, this.searchForm)
      }).then(res => {
        if (res.code !== 0) return this.$message.error(res.msg || '加载失败')
        this.list = res.data.list || []
        this.count = res.data.count || 0
      }).finally(() => { this.loading = false })
    },
    search() {
      this.page = 1
      this.load()
    },
    resetSearch() {
      this.searchForm = { keyword: '', status: '' }
      this.search()
    },
    handleRecredit(row) {
      this.$confirm(
        `确定给「${row.nickname}」补发 ${row.total_gain} ${row.asset_label}？\n仅用于「已付款但未到账」的卡单，重复点击不会重复发放。`,
        '重新发放',
        { type: 'warning' }
      ).then(() => {
        this.$request({
          url: '/plugin/withdraw/admin/recharge/recredit',
          method: 'post',
          data: { id: row.id }
        }).then(res => {
          if (res.code !== 0) return this.$message.error(res.msg || '发放失败')
          this.$message.success('已发放到账')
          this.load()
        })
      }).catch(() => {})
    }
  }
}
</script>

<style scoped>
.page-header { display: flex; align-items: center; justify-content: space-between; }
.page-title { font-size: 15px; font-weight: 600; }
.page-title i { margin-right: 6px; }
.user-cell { display: flex; align-items: center; }
.nickname { margin-left: 8px; }
.muted { color: #c0c4cc; }
</style>
