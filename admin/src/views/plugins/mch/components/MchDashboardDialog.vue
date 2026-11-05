<template>
  <el-dialog title="商户营业数据" :visible.sync="visible" width="70%" @closed="data = null">
    <div v-loading="loading">
      <div class="dashboard-header" v-if="mch">
        <h3>{{ mch.mch_name }} (ID: {{ mch.id }})</h3>
        <el-radio-group v-model="period" size="mini" @change="getData">
          <el-radio-button label="today">今日</el-radio-button>
          <el-radio-button label="yesterday">昨日</el-radio-button>
          <el-radio-button label="week">近7天</el-radio-button>
          <el-radio-button label="month">本月</el-radio-button>
          <el-radio-button label="total">累计</el-radio-button>
        </el-radio-group>
      </div>

      <div v-if="data">
        <el-divider content-position="left">收款统计</el-divider>
        <el-row :gutter="20" class="stats-row">
          <el-col :span="6"><div class="stat-box"><div class="label">总支付金额</div><div class="value">¥{{ data.revenueStats.total_pay_price }}</div></div></el-col>
        </el-row>
      </div>
    </div>
  </el-dialog>
</template>

<script>
export default {
  name: 'MchDashboardDialog',
  data() {
    return {
      visible: false,
      loading: false,
      period: 'today',
      mch: null,
      data: null
    };
  },
  methods: {
    open(row) {
      this.mch = row;
      this.period = 'today';
      this.visible = true;
      this.getData();
    },
    async getData() {
      if (!this.mch) return;
      this.loading = true;
      try {
        const res = await this.$request({
          url: '/plugin/mch/mall/mch/mch-dashboard',
          data: { mch_id: this.mch.id, period: this.period }
        });
        if (res.code === 0) this.data = res.data;
      } finally {
        this.loading = false;
      }
    }
  }
}
</script>
<style scoped>
/* 复制原 CSS */
.dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.stat-box { background-color: #f5f7fa; padding: 20px; text-align: center; margin-bottom: 10px; }
.stat-box .value { font-size: 24px; font-weight: bold; }
</style>
