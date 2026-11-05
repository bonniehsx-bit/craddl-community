<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="card-header-flex"><span>效果看板</span>
        <el-button size="mini" icon="el-icon-refresh" @click="getData">刷新</el-button>
      </div>

      <!-- 汇总卡片 -->
      <div class="summary shrink-0">
        <div class="stat-card"><div class="num">{{ summary.grant_count }}</div><div class="lbl">发放张数</div></div>
        <div class="stat-card"><div class="num">{{ summary.redeem_count }}</div><div class="lbl">核销张数</div></div>
        <div class="stat-card"><div class="num">{{ summary.redeem_rate }}%</div><div class="lbl">核销率</div></div>
        <div class="stat-card"><div class="num">{{ summary.integral_sum }}</div><div class="lbl">发放{{ $noun('integral') }}</div></div>
        <div class="stat-card"><div class="num">¥{{ summary.balance_sum }}</div><div class="lbl">发放{{ $noun('balance') }}</div></div>
        <div class="stat-card"><div class="num">¥{{ summary.gmv }}</div><div class="lbl">带动GMV</div></div>
        <div class="stat-card"><div class="num">¥{{ summary.cost }}</div><div class="lbl">券成本(实际抵扣)</div></div>
        <div class="stat-card highlight"><div class="num">{{ summary.roi }}</div><div class="lbl">ROI(GMV/成本)</div></div>
      </div>

      <div class="table-wrapper flex-1">
        <div class="absolute-table-container">
          <el-table :data="rows" v-loading="loading" border size="small" height="100%" style="width:100%">
            <el-table-column prop="campaign_id" label="ID" width="60" align="center"></el-table-column>
            <el-table-column prop="name" label="活动" min-width="150" show-overflow-tooltip></el-table-column>
            <el-table-column prop="status_text" label="状态" width="90" align="center"></el-table-column>
            <el-table-column prop="grant_count" label="发放张数" width="90" align="center"></el-table-column>
            <el-table-column prop="user_count" label="获奖人数" width="90" align="center"></el-table-column>
            <el-table-column prop="integral_sum" :label="'发放' + $noun('integral')" width="100" align="center"></el-table-column>
            <el-table-column :label="'发放' + $noun('balance')" width="100" align="right">
              <template slot-scope="s">¥{{ s.row.balance_sum }}</template>
            </el-table-column>
            <el-table-column prop="redeem_count" label="核销张数" width="90" align="center"></el-table-column>
            <el-table-column label="核销率" width="90" align="center">
              <template slot-scope="s">{{ s.row.redeem_rate }}%</template>
            </el-table-column>
            <el-table-column prop="order_count" label="带动订单" width="90" align="center"></el-table-column>
            <el-table-column label="带动GMV" width="110" align="right">
              <template slot-scope="s">¥{{ s.row.gmv }}</template>
            </el-table-column>
            <el-table-column label="券成本" width="100" align="right">
              <template slot-scope="s">¥{{ s.row.cost }}</template>
            </el-table-column>
            <el-table-column label="ROI" width="80" align="center">
              <template slot-scope="s"><span :style="{color: s.row.roi>=1?'#67C23A':'#F56C6C'}">{{ s.row.roi }}</span></template>
            </el-table-column>
          </el-table>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'marketing-dashboard',
  data() {
    return {
      loading: false, rows: [],
      summary: { grant_count: 0, redeem_count: 0, redeem_rate: 0, integral_sum: 0, balance_sum: 0, gmv: 0, cost: 0, roi: 0 },
    };
  },
  created() { this.getData(); },
  methods: {
    async getData() {
      this.loading = true;
      try {
        const res = await this.$request({ url: '/plugin/marketing/mall/stat/overview', data: {} });
        if (res.code === 0) { this.rows = res.data.rows; this.summary = res.data.summary; } else this.$message.error(res.msg);
      } finally { this.loading = false; }
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
.table-wrapper { flex: 1; position: relative; min-height: 0; }
.absolute-table-container { position: absolute; top: 0; left: 0; right: 0; bottom: 0; }
.summary { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
.stat-card { flex: 1; min-width: 120px; background: #fff; border: 1px solid #ebeef5; border-radius: 6px; padding: 14px; text-align: center; }
.stat-card .num { font-size: 22px; font-weight: 600; color: #303133; }
.stat-card .lbl { font-size: 12px; color: #909399; margin-top: 4px; }
.stat-card.highlight { background: #ecf5ff; border-color: #b3d8ff; }
.stat-card.highlight .num { color: #409EFF; }
</style>
