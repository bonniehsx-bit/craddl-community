<template>
  <div class="app-container dashboard-container custom-scrollbar" v-loading="loading">
    
    <div class="dashboard-header mb-20">
      <div class="header-left user-info" style="display: flex; align-items: center;">
        <el-avatar :size="64" :src="avatar || (mchInfo && mchInfo.logo) || defaultAvatar" class="user-avatar" style="margin-right: 15px;"></el-avatar>
        <div class="user-text">
          <h2 class="welcome-title">{{ hello }}，{{ mchInfo ? mchInfo.name : name }}</h2>
          <span class="sub-title" v-if="mchInfo">
            商户号: {{ mchInfo.id }} | 店铺状态: <el-tag size="mini" :type="mchInfo.is_clerk ? 'success' : 'primary'" effect="dark">{{ mchInfo.is_clerk ? '店员营业中' : '店长营业中' }}</el-tag>
          </span>
        </div>
      </div>
      <div class="header-right">
        <span class="date-text"><i class="el-icon-date"></i> {{ currentDate }}</span>
      </div>
    </div>

    <!-- 核心数据卡片区 (4栏布局) -->
    <div class="panel-group custom-4-cols" v-if="stats && stats.revenue_stats">
      <!-- 针对不同类型的商家渲染专属财务视图 -->
      <template v-if="stats.mch_type == 2"> <!-- TYPE_DOWN_STORE 线下门店 -->
        <div class="col-item">
          <el-card shadow="hover" class="data-card card-blue">
            <div class="card-content">
              <div class="card-left">
                <div class="card-label">今日总收款</div>
                <div class="card-num">
                  <span class="unit">¥</span><span>{{ stats.revenue_stats.collection_amount | toFixed2 }}</span>
                </div>
              </div>
              <div class="card-icon"><i class="el-icon-money"></i></div>
            </div>
          </el-card>
        </div>
        <div class="col-item">
          <el-card shadow="hover" class="data-card card-cyan">
            <div class="card-content">
               <div class="card-left">
                 <div class="card-label">线上商品收款</div>
                 <div class="card-num">
                   <span class="unit">¥</span><span>{{ stats.revenue_stats.online_amount | toFixed2 }}</span>
                 </div>
               </div>
               <div class="card-icon"><i class="el-icon-shopping-cart-full"></i></div>
            </div>
          </el-card>
        </div>
        <div class="col-item">
          <el-card shadow="hover" class="data-card card-purple">
            <div class="card-content">
               <div class="card-left">
                 <div class="card-label">门店收银台收款</div>
                 <div class="card-num">
                   <span class="unit">¥</span><span>{{ stats.revenue_stats.qrcode_amount | toFixed2 }}</span>
                 </div>
               </div>
               <div class="card-icon"><i class="el-icon-mobile-phone"></i></div>
            </div>
          </el-card>
        </div>
      </template>

      <template v-else> <!-- TYPE_UP_STORE 供应商或其他 -->
        <div class="col-item">
          <el-card shadow="hover" class="data-card card-blue">
            <div class="card-content">
              <div class="card-left">
                <div class="card-label">今日销售总额</div>
                <div class="card-num">
                  <span class="unit">¥</span><span>{{ stats.revenue_stats.sales_amount | toFixed2 }}</span>
                </div>
              </div>
              <div class="card-icon"><i class="el-icon-money"></i></div>
            </div>
          </el-card>
        </div>
        <div class="col-item">
          <el-card shadow="hover" class="data-card card-purple">
            <div class="card-content">
               <div class="card-left">
                 <div class="card-label">今日支付单数</div>
                 <div class="card-num">
                   <span>{{ stats.revenue_stats.sales_count }}</span><span class="unit-small">单</span>
                 </div>
               </div>
               <div class="card-icon"><i class="el-icon-s-order"></i></div>
            </div>
          </el-card>
        </div>
        <div class="col-item">
          <el-card shadow="hover" class="data-card card-green">
            <div class="card-content">
               <div class="card-left">
                 <div class="card-label">在售商品数</div>
                 <div class="card-num">
                   <span>{{ stats.common_stats.on_sale_goods }}</span><span class="unit-small">件</span>
                 </div>
               </div>
               <div class="card-icon"><i class="el-icon-goods"></i></div>
            </div>
          </el-card>
        </div>
      </template>

      <!-- 通用: 取消/退回金 -->
      <div class="col-item">
        <el-card shadow="hover" class="data-card card-orange" :class="{ 'has-pending': stats.revenue_stats.cancel_count > 0 }">
          <div class="card-content">
             <div class="card-left">
               <div class="card-label">今日退刷/取消</div>
               <div class="card-num">
                 <span class="unit">¥</span><span>{{ stats.revenue_stats.cancel_amount | toFixed2 }}</span>
               </div>
               <div class="card-footer">
                 <span v-if="stats.revenue_stats.cancel_count > 0" class="badge-text" style="background:#FEE2E2; color:#EF4444;">包含 {{ stats.revenue_stats.cancel_count }} 笔闭金</span>
                 <span v-else>经营状况良好</span>
               </div>
             </div>
             <div class="card-icon"><i class="el-icon-warning"></i></div>
          </div>
        </el-card>
      </div>
    </div>

    <!-- 运营状态管道 (快速路由通道) -->
    <el-row :gutter="20" class="mt-20" v-if="stats && stats.order_stats">
      <el-col :span="6">
        <div class="status-item bg-white" @click="$router.push('/order/index?status=status_1')">
          <div class="icon-wrap i-blue"><i class="el-icon-truck"></i></div>
          <div class="text-wrap">
            <div class="label">待发货订单</div>
            <div class="val">{{ stats.order_stats.to_be_shipped || 0 }}</div>
          </div>
        </div>
      </el-col>
      <el-col :span="6" v-if="stats.mch_type == 2">
        <div class="status-item bg-white" @click="$router.push('/order/index?status=status_1')">
          <div class="icon-wrap i-green"><i class="el-icon-s-ticket"></i></div>
          <div class="text-wrap">
            <div class="label">待核销订单</div>
            <div class="val">{{ stats.order_stats.to_be_verified || 0 }}</div>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="status-item bg-white" @click="$router.push('/refund/index')">
          <div class="icon-wrap i-red"><i class="el-icon-service"></i></div>
          <div class="text-wrap">
            <div class="label">售后处理中</div>
            <div class="val">{{ stats.order_stats.processingRefund || 0 }}</div>
          </div>
        </div>
      </el-col>
      <el-col :span="6" v-if="stats.mch_type != 2">
        <div class="status-item bg-white" @click="$router.push('/goods/index')">
          <div class="icon-wrap i-orange"><i class="el-icon-folder-opened"></i></div>
          <div class="text-wrap">
            <div class="label">下架/审核中商品</div>
            <div class="val">{{ (stats.common_stats.off_shelf_goods || 0) + (stats.common_stats.on_check_goods || 0) }}</div>
          </div>
        </div>
      </el-col>
    </el-row>

    <!-- 15天交易趋势折线图 -->
    <el-row :gutter="20" class="mt-20" v-if="stats && stats.chart">
      <el-col :span="24">
        <el-card class="chart-card main-chart" shadow="never">
          <div slot="header" class="chart-header">
            <div class="header-title">
              <i class="el-icon-data-line"></i> 近15天交易趋势
            </div>
            <el-radio-group v-model="chartRadio" size="mini" @change="updateLineChart">
              <el-radio-button label="sales">销售额</el-radio-button>
              <el-radio-button label="orders">订单量</el-radio-button>
             </el-radio-group>
          </div>
          <div id="lineChart" style="width: 100%; height: 380px;"></div>
        </el-card>
      </el-col>
    </el-row>

    <div style="height: 20px;"></div>
  </div>
</template>

<script>
import { mapGetters, mapState } from 'vuex'
import * as echarts from 'echarts';

export default {
  name: 'Dashboard',
  data() {
    return {
      defaultAvatar: 'https://wpimg.wallstcn.com/f778738c-e4f8-4870-b634-56703b4acafe.gif',
      stats: null,
      loading: false,
      currentDate: '',
      chartRadio: 'sales',
      lineChart: null
    }
  },
  computed: {
    ...mapGetters(['name', 'avatar']),
    ...mapState('user', ['mchInfo']),

    hello() {
      let hour = new Date().getHours();
      if (hour < 6) return "凌晨好";
      if (hour < 9) return "早上好";
      if (hour < 12) return "上午好";
      if (hour < 14) return "中午好";
      if (hour < 17) return "下午好";
      if (hour < 19) return "傍晚好";
      if (hour < 22) return "晚上好";
      return "夜里好";
    }
  },
  filters: {
    toFixed2(val) {
      if (!val) return '0.00';
      return parseFloat(val).toFixed(2);
    }
  },
  created() {
    const date = new Date();
    this.currentDate = `${date.getFullYear()}年${date.getMonth() + 1}月${date.getDate()}日`;
  },
  mounted() {
    this.getStatistics();
    window.addEventListener('resize', this.handleResize);
  },
  beforeDestroy() {
    window.removeEventListener('resize', this.handleResize);
    if (this.lineChart) {
      this.lineChart.dispose();
    }
  },
  methods: {
    getStatistics() {
      this.loading = true;
      this.$request({
        url: '/plugin/mch/admin/statistics/dashboard',
        method: 'get',
        data: { period: 'today' }
      }).then(res => {
        this.loading = false;
        if (res.code === 0) {
          this.stats = res.data;
          this.$nextTick(() => {
            if (this.stats.chart && this.stats.chart.dates) {
               this.initLineChart();
            }
          });
        }
      }).catch(e => {
        this.loading = false;
        console.error(e);
      });
    },

    handleResize() {
      if (this.lineChart) {
        this.lineChart.resize();
      }
    },

    updateLineChart() {
      if (this.lineChart) {
        this.initLineChart();
      }
    },

    initLineChart() {
      const chartDom = document.getElementById('lineChart');
      if (!chartDom) return;
      if (!this.lineChart) {
        this.lineChart = echarts.init(chartDom);
      }

      const isSales = this.chartRadio === 'sales';
      const seriesData = isSales ? this.stats.chart.sales : this.stats.chart.orders;
      const primaryColor = isSales ? '#3B82F6' : '#10B981';
      const title = isSales ? '销售额 (元)' : '订单量 (单)';

      const option = {
        backgroundColor: '#fff',
        tooltip: {
          trigger: 'axis',
          backgroundColor: 'rgba(255,255,255,0.95)',
          textStyle: { color: '#333' },
          extraCssText: 'box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-radius: 4px;'
        },
        grid: { left: '2%', right: '3%', bottom: '3%', top: '15%', containLabel: true },
        xAxis: {
          type: 'category',
          boundaryGap: false,
          data: this.stats.chart.dates.map(date => date.substring(5)), // 只展示 MM-DD
          axisLine: { show: false },
          axisTick: { show: false },
          axisLabel: { color: '#9CA3AF', margin: 15 }
        },
        yAxis: [{
          type: 'value',
          name: title,
          nameTextStyle: { color: '#9CA3AF', padding: [0, 0, 0, 10] },
          splitLine: { lineStyle: { type: 'dashed', color: '#E5E7EB' } },
          axisLabel: { color: '#9CA3AF' }
        }],
        series: [{
          name: isSales ? '日单额' : '日单量',
          type: 'line',
          smooth: true,
          symbol: 'emptyCircle',
          symbolSize: 6,
          showSymbol: false,
          lineStyle: { width: 3, color: primaryColor },
          itemStyle: { color: primaryColor, borderColor: '#fff', borderWidth: 2 },
          areaStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: isSales ? 'rgba(59, 130, 246, 0.2)' : 'rgba(16, 185, 129, 0.2)' },
              { offset: 1, color: isSales ? 'rgba(59, 130, 246, 0.01)' : 'rgba(16, 185, 129, 0.01)' }
            ])
          },
          data: seriesData
        }]
      };
      this.lineChart.setOption(option, true);
    }
  }
}
</script>

<style lang="scss" scoped>
$bg-color: #f3f4f6;
$card-radius: 12px;
$text-main: #1F2937;
$text-sub: #6B7280;
$primary: #3B82F6;

.dashboard-container {
  padding: 24px;
  background-color: $bg-color;
  height: calc(100vh - 84px);
  overflow-y: auto;
  box-sizing: border-box;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }

.mb-20 { margin-bottom: 20px; }
.mt-20 { margin-top: 20px; }
.bg-white { background: #fff; }

.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #fff;
  padding: 16px 24px;
  border-radius: $card-radius;
  box-shadow: 0 1px 2px rgba(0,0,0,0.03);

  .header-left {
    .welcome-title { font-size: 20px; font-weight: 700; color: $text-main; margin: 0 0 4px 0; }
    .sub-title { font-size: 13px; color: $text-sub; }
  }
  .header-right .date-text {
    font-size: 14px; color: $text-sub; background: #f9fafb; padding: 6px 12px; border-radius: 6px;
  }
}

.data-card {
  border: none; border-radius: $card-radius; height: 140px;
  position: relative; overflow: hidden; transition: all 0.3s;
  &:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0,0,0,0.08); }
  ::v-deep .el-card__body { padding: 20px; height: 100%; box-sizing: border-box; }
  .card-content { display: flex; justify-content: space-between; height: 100%; position: relative; z-index: 1; }
  .card-left { display: flex; flex-direction: column; justify-content: space-between; flex: 1; }
  .card-label { font-size: 14px; color: $text-sub; }
  .card-num {
    font-size: 28px; font-weight: 700; color: $text-main; font-family: 'DIN Alternate', sans-serif; margin: 5px 0;
    .unit { font-size: 16px; margin-right: 2px; }
    .unit-small { font-size: 14px; margin-left: 4px; font-weight: normal; color: $text-sub; }
  }
  .card-footer {
    font-size: 12px; color: #9CA3AF; display: flex; align-items: center; gap: 10px;
    .badge-text { color: #F59E0B; background: #FEF3C7; padding: 2px 6px; border-radius: 4px; transform: scale(0.9); transform-origin: left center; }
  }
  .card-icon {
    position: absolute; right: -10px; bottom: -15px; font-size: 80px; opacity: 0.1;
    transform: rotate(-15deg); transition: all 0.3s; z-index: 0;
  }
  &:hover .card-icon { opacity: 0.2; transform: rotate(0deg) scale(1.1); }
  &.card-pink .card-icon { color: #EC4899; }
  &.card-green .card-icon { color: #10B981; }
  &.card-teal .card-icon { color: #14B8A6; }
  &.card-blue .card-icon { color: #3B82F6; }
  &.card-purple .card-icon { color: #8B5CF6; }
  &.card-cyan .card-icon { color: #06B6D4; }
  &.card-orange .card-icon { color: #F59E0B; }
  &.has-pending { border-left: 4px solid #F59E0B; }
}

.custom-4-cols {
  display: flex;
  flex-wrap: nowrap;
  gap: 20px;
  width: 100%;
}
.custom-4-cols .col-item {
  flex: 1 1 0;
  min-width: 0;
}

.status-item {
  display: flex; align-items: center; padding: 16px; border-radius: $card-radius; cursor: pointer;
  transition: all 0.3s; box-shadow: 0 1px 2px rgba(0,0,0,0.03);
  &:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); transform: translateY(-2px); }

  .icon-wrap {
    width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
    font-size: 24px; margin-right: 15px; flex-shrink: 0;

    &.i-blue { background: #EFF6FF; color: #3B82F6; }
    &.i-red { background: #FEF2F2; color: #EF4444; }
    &.i-orange { background: #FFFBEB; color: #F59E0B; }
    &.i-green { background: #ECFDF5; color: #10B981; }
  }

  .text-wrap {
    .label { font-size: 13px; color: $text-sub; margin-bottom: 2px; }
    .val { font-size: 20px; font-weight: bold; color: $text-main; font-family: 'DIN Alternate', sans-serif; }
  }
}

.chart-card {
  border: none; border-radius: $card-radius;
  ::v-deep .el-card__header { padding: 15px 20px; border-bottom: 1px solid #f9fafb; }
  .chart-header {
    display: flex; justify-content: space-between; align-items: center;
    .header-title { font-size: 16px; font-weight: 600; color: $text-main; i { margin-right: 6px; color: $primary; } }
  }
}
</style>
