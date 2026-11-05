<template>
  <div class="app-container dashboard-container custom-scrollbar" v-loading="loading">

    <div class="dashboard-header mb-20">
      <div class="header-left">
        <h2 class="welcome-title">工作台概览</h2>
        <span class="sub-title">今日数据实时更新</span>
      </div>
      <div class="header-right">
        <div class="big-screen-btn" @click="goBigScreen">
          <i class="el-icon-monitor btn-icon"></i>
          <span class="btn-text">数据大屏</span>
          <span class="btn-glow"></span>
        </div>
        <span class="date-text"><i class="el-icon-date"></i> {{ currentDate }}</span>
      </div>
    </div>

    <!-- 为了支持 7 等分，使用自定义 Flex 布局而不是固定的 24 栅格 -->
    <div class="panel-group custom-7-cols">
      <!-- 第一张卡片：PV -->
      <div class="col-item">
        <el-card shadow="hover" class="data-card card-pink">
          <div class="card-content">
            <div class="card-left">
              <div class="card-label">今日访问量(PV)</div>
              <div class="card-num">
                <span>{{ data.card.today_pv }}</span>
              </div>
              <div class="card-footer">
                <span>全天首页打开频次</span>
              </div>
            </div>
            <div class="card-icon"><i class="el-icon-view"></i></div>
          </div>
        </el-card>
      </div>
      
      <!-- 第二张卡片：UV -->
      <div class="col-item">
        <el-card shadow="hover" class="data-card card-green">
          <div class="card-content">
            <div class="card-left">
              <div class="card-label">今日访客数(UV)</div>
              <div class="card-num">
                <span>{{ data.card.today_uv }}</span>
              </div>
              <div class="card-footer">
                <span>精确去重活跃人数</span>
              </div>
            </div>
            <div class="card-icon"><i class="el-icon-magic-stick"></i></div>
          </div>
        </el-card>
      </div>

      <!-- 第三张卡片：销售额 -->
      <div class="col-item">
        <el-card shadow="hover" class="data-card card-blue">
          <div class="card-content">
            <div class="card-left">
              <div class="card-label">今日销售额</div>
              <div class="card-num">
                <span class="unit">¥</span><span>{{ data.card.today_sales | toFixed2 }}</span>
              </div>
              <div class="card-footer">
                <span>昨日 ¥{{ data.card.yesterday_sales }}</span>
                <span class="trend" :class="getTrendClass(data.card.sales_rate)">
                  {{ Math.abs(data.card.sales_rate) }}% <i :class="getTrendIcon(data.card.sales_rate)"></i>
                </span>
              </div>
            </div>
            <div class="card-icon"><i class="el-icon-money"></i></div>
          </div>
        </el-card>
      </div>

      <!-- 第四张卡片：订单量 -->
      <div class="col-item">
        <el-card shadow="hover" class="data-card card-purple">
          <div class="card-content">
            <div class="card-left">
              <div class="card-label">今日支付订单</div>
              <div class="card-num">
                <span>{{ data.card.today_order_count }}</span><span class="unit-small">单</span>
              </div>
              <div class="card-footer">
                <span>昨日 {{ data.card.yesterday_order_count }}</span>
                <span class="trend" :class="getTrendClass(data.card.order_rate)">
                  {{ Math.abs(data.card.order_rate) }}% <i :class="getTrendIcon(data.card.order_rate)"></i>
                </span>
              </div>
            </div>
            <div class="card-icon"><i class="el-icon-s-order"></i></div>
          </div>
        </el-card>
      </div>

      <!-- 第五张卡片：平台总用户 -->
      <div class="col-item">
        <el-card shadow="hover" class="data-card card-cyan">
          <div class="card-content">
            <div class="card-left">
              <div class="card-label">平台总用户</div>
              <div class="card-num">
                <span>{{ data.card.total_users }}</span><span class="unit-small">人</span>
              </div>
              <div class="card-footer">
                <span>平台累计注册用户</span>
              </div>
            </div>
            <div class="card-icon"><i class="el-icon-user-solid"></i></div>
          </div>
        </el-card>
      </div>

      <!-- 第六张卡片：今日新增用户 -->
      <div class="col-item">
        <el-card shadow="hover" class="data-card card-teal">
          <div class="card-content">
            <div class="card-left">
              <div class="card-label">今日新增用户</div>
              <div class="card-num">
                <span class="highlight">+{{ data.card.new_users }}</span><span class="unit-small">人</span>
              </div>
              <div class="card-footer">
                <span>平台新鲜血液注入</span>
              </div>
            </div>
            <div class="card-icon"><i class="el-icon-user"></i></div>
          </div>
        </el-card>
      </div>

      <!-- 第七张卡片：待提现审核 -->
      <div class="col-item">
        <el-card shadow="hover" class="data-card card-orange" :class="{ 'has-pending': data.card.pending_cash > 0 }">
          <div class="card-content">
            <div class="card-left">
              <div class="card-label">待提现审核</div>
              <div class="card-num">
                <span>{{ data.card.pending_cash }}</span><span class="unit-small">笔</span>
              </div>
              <div class="card-footer">
                <span v-if="data.card.pending_cash > 0" class="badge-text">请及时处理财务申请</span>
                <span v-else>暂无待办事项</span>
              </div>
            </div>
            <div class="card-icon"><i class="el-icon-s-finance"></i></div>
          </div>
        </el-card>
      </div>
    </div>

    <!-- 退款卡住只能靠人处理(失败且重试已用完 / 处理中没结果 / 后续没做完):有就提醒,点了直接去处理台 -->
    <el-alert v-if="data.pipeline.refund_attention > 0" class="mt-20" type="error" :closable="false" show-icon>
      <span slot="title">
        有 {{ data.pipeline.refund_attention }} 笔退款需要人工处理,钱可能还没退到顾客手里
        <el-button type="text" class="refund-alert-link" @click="$router.push('/finance/refund-fail')">去处理 →</el-button>
      </span>
    </el-alert>

    <el-row :gutter="20" class="mt-20">
      <el-col :span="6">
        <div class="status-item bg-white" @click="$router.push('/order/index?status=20')">
          <div class="icon-wrap i-blue"><i class="el-icon-truck"></i></div>
          <div class="text-wrap">
            <div class="label">待发货订单</div>
            <div class="val">{{ data.pipeline.to_ship }}</div>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="status-item bg-white" @click="$router.push('/order/refund')">
          <div class="icon-wrap i-red"><i class="el-icon-service"></i></div>
          <div class="text-wrap">
            <div class="label">售后处理中</div>
            <div class="val">{{ data.pipeline.refunding }}</div>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="status-item bg-white" @click="$router.push('/goods/index?low_stock=1')">
          <div class="icon-wrap i-orange"><i class="el-icon-warning-outline"></i></div>
          <div class="text-wrap">
            <div class="label">库存预警</div>
            <div class="val">{{ data.pipeline.low_stock }}</div>
          </div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="status-item bg-white">
          <div class="icon-wrap i-green"><i class="el-icon-coin"></i></div>
          <div class="text-wrap">
            <div class="label">今日产生佣金</div>
            <!-- 后端一直在算 commission.total(累计已结算佣金),之前没有任何地方显示它,
                 等于白查一次表。放在金额右边当副标题 —— 单独占一行会让这张卡比同排的高一截 -->
            <div class="val-row">
              <span class="val">¥{{ data.commission.today }}</span>
              <span class="sub-val" :title="'累计已结算 ¥' + data.commission.total">累计已结算 ¥{{ data.commission.total }}</span>
            </div>
          </div>
        </div>
      </el-col>
    </el-row>

    <el-row :gutter="20" class="mt-20">
      <el-col :span="12">
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
      <el-col :span="12">
        <el-card class="chart-card main-chart" shadow="never">
          <div slot="header" class="chart-header">
            <div class="header-title">
              <i class="el-icon-data-analysis"></i> 近15天访问趋势
            </div>
            <el-radio-group v-model="visitRadio" size="mini" @change="updateVisitChart">
              <el-radio-button label="pv">访问量(PV)</el-radio-button>
              <el-radio-button label="uv">访客数(UV)</el-radio-button>
            </el-radio-group>
          </div>
          <div id="visitChart" style="width: 100%; height: 380px;"></div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="20" class="mt-20">
      <el-col :span="12">
        <el-card class="chart-card" shadow="never">
          <div slot="header" class="chart-header">
            <span class="header-title"><i class="el-icon-pie-chart"></i> 用户来源分布</span>
          </div>
          <div id="pieChart" style="width: 100%; height: 350px;"></div>
        </el-card>
      </el-col>

      <el-col :span="12">
        <el-card class="chart-card" shadow="never">
          <div slot="header" class="chart-header">
            <span class="header-title"><i class="el-icon-trophy"></i> 热销商品 Top 5</span>
          </div>
          <el-table :data="data.top_goods" style="width: 100%; height: 350px;" :show-header="true" class="rank-table">
            <el-table-column label="排名" width="70" align="center">
              <template slot-scope="scope">
                <div class="rank-badge" :class="'rank-'+(scope.$index+1)">
                  {{ scope.$index + 1 }}
                </div>
              </template>
            </el-table-column>

            <el-table-column label="商品信息" show-overflow-tooltip>
              <template slot-scope="scope">
                <div class="goods-info">
                  <el-image :src="scope.row.cover_pic" class="goods-img" fit="cover">
                    <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
                  </el-image>
                  <span class="goods-name">{{ scope.row.name }}</span>
                </div>
              </template>
            </el-table-column>

            <el-table-column prop="sales" label="销量" width="100" align="right">
              <template slot-scope="scope">
                <span class="sales-text">{{ scope.row.sales }}</span>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-col>
    </el-row>

    <goods-traffic-rank class="mt-20" />

    <div style="height: 20px;"></div>

  </div>
</template>

<script>
import * as echarts from 'echarts';
import GoodsTrafficRank from './dashboard/GoodsTrafficRank.vue';

export default {
  name: 'Dashboard',
  components: { GoodsTrafficRank },
  data() {
    return {
      loading: false,
      currentDate: '',
      chartRadio: 'sales',
      visitRadio: 'pv',

      // 对应 PHP 返回的数据结构
      data: {
        card: {
          today_pv: 0,
          today_uv: 0,
          today_sales: 0,
          yesterday_sales: 0,
          sales_rate: 0,
          today_order_count: 0,
          yesterday_order_count: 0,
          order_rate: 0,
          total_users: 0,
          new_users: 0,
          pending_cash: 0
        },
        pipeline: {
          to_ship: 0,
          refunding: 0,
          low_stock: 0,
          refund_attention: 0
        },
        commission: {
          total: 0,
          today: 0
        },
        chart: {
          dates: [],
          sales: [],
          orders: [],
          pv: [],
          uv: []
        },
        pie: [],
        top_goods: []
      },

      lineChart: null,
      visitChart: null,
      pieChart: null
    };
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
    this.getData();
    window.addEventListener('resize', this.handleResize);
  },
  beforeDestroy() {
    window.removeEventListener('resize', this.handleResize);
    if (this.lineChart) this.lineChart.dispose();
    if (this.visitChart) this.visitChart.dispose();
    if (this.pieChart) this.pieChart.dispose();
  },
  methods: {
    goBigScreen() {
      const url = this.$router.resolve({ path: '/big-screen' }).href
      window.open(url, '_blank')
    },
    getData() {
      this.loading = true;
      this.$request({
        url: 'mall/dashboard/data',
        method: 'get'
      }).then(res => {
        if (res.code === 0) {
          this.data = Object.assign({}, this.data, res.data);
          this.$nextTick(() => {
            this.initLineChart();
            this.initVisitChart();
            this.initPieChart();
          });
        } else {
          this.$message.error(res.msg);
        }
      }).catch(e => {
        console.error(e);
      }).finally(() => {
        this.loading = false;
      });
    },

    getTrendClass(rate) {
      if (rate > 0) return 'up';
      if (rate < 0) return 'down';
      return 'flat';
    },
    getTrendIcon(rate) {
      if (rate > 0) return 'el-icon-caret-top';
      if (rate < 0) return 'el-icon-caret-bottom';
      return 'el-icon-minus';
    },

    handleResize() {
      this.lineChart && this.lineChart.resize();
      this.visitChart && this.visitChart.resize();
      this.pieChart && this.pieChart.resize();
    },

    updateLineChart() {
      if (this.lineChart) this.initLineChart();
    },

    initLineChart() {
      const chartDom = document.getElementById('lineChart');
      if (!chartDom) return;
      if (!this.lineChart) this.lineChart = echarts.init(chartDom);

      const isSales = this.chartRadio === 'sales';
      const seriesData = isSales ? this.data.chart.sales : this.data.chart.orders;
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
          data: this.data.chart.dates,
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
          name: isSales ? '销售额' : '订单量',
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
      this.lineChart.setOption(option);
    },

    updateVisitChart() {
      if (this.visitChart) this.initVisitChart();
    },

    initVisitChart() {
      const chartDom = document.getElementById('visitChart');
      if (!chartDom) return;
      if (!this.visitChart) this.visitChart = echarts.init(chartDom);

      const isPv = this.visitRadio === 'pv';
      const seriesData = isPv ? this.data.chart.pv : this.data.chart.uv;
      const primaryColor = isPv ? '#EC4899' : '#8B5CF6';
      const title = isPv ? '访问量 (PV)' : '访客数 (UV)';

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
          data: this.data.chart.dates,
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
          name: isPv ? '访问量' : '访客数',
          type: 'line',
          smooth: true,
          symbol: 'emptyCircle',
          symbolSize: 6,
          showSymbol: false,
          lineStyle: { width: 3, color: primaryColor },
          itemStyle: { color: primaryColor, borderColor: '#fff', borderWidth: 2 },
          areaStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: isPv ? 'rgba(236, 72, 153, 0.2)' : 'rgba(139, 92, 246, 0.2)' },
              { offset: 1, color: isPv ? 'rgba(236, 72, 153, 0.01)' : 'rgba(139, 92, 246, 0.01)' }
            ])
          },
          data: seriesData
        }]
      };
      this.visitChart.setOption(option);
    },

    initPieChart() {
      const chartDom = document.getElementById('pieChart');
      if (!chartDom) return;
      if (!this.pieChart) this.pieChart = echarts.init(chartDom);

      const pieData = this.data.pie.length > 0 ? this.data.pie : [{name: '暂无数据', value: 0}];

      const option = {
        color: ['#6366F1', '#8B5CF6', '#EC4899', '#F472B6', '#FCD34D', '#34D399', '#60A5FA'],
        tooltip: {
          trigger: 'item',
          formatter: '{b}: {c} ({d}%)'
        },
        legend: {
          bottom: '0%',
          left: 'center',
          itemWidth: 10,
          itemHeight: 10,
          icon: 'circle'
        },
        series: [{
          name: '用户来源',
          type: 'pie',
          radius: ['40%', '70%'],
          center: ['50%', '45%'],
          itemStyle: {
            borderRadius: 6,
            borderColor: '#fff',
            borderWidth: 2
          },
          label: { show: false },
          emphasis: {
            label: { show: true, fontSize: '14', fontWeight: 'bold' },
            itemStyle: { shadowBlur: 10, shadowOffsetX: 0, shadowColor: 'rgba(0, 0, 0, 0.2)' }
          },
          data: pieData
        }]
      };
      this.pieChart.setOption(option);
    }
  }
};
</script>

<style lang="scss" scoped>
.refund-alert-link { padding: 0; margin-left: 8px; }
/* 全局变量 */
$bg-color: #f3f4f6;
$card-radius: 12px;
$text-main: #1F2937;
$text-sub: #6B7280;
$primary: #3B82F6;

/* 核心滚动修复：
   将高度固定为视口高度减去头部(约84px)，
   并开启 overflow-y: auto，确保内容在容器内滚动 */
.dashboard-container {
  padding: 24px;
  background-color: $bg-color;
  height: calc(100vh - 50px - var(--tags-view-height, 34px));
  overflow-y: auto;           /* 关键修复 */
  box-sizing: border-box;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}

/* 滚动条美化 */
.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }

.mb-20 { margin-bottom: 20px; }
.mt-20 { margin-top: 20px; }
.bg-white { background: #fff; }

/* 头部 Header */
.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #fff;
  padding: 16px 24px;
  border-radius: $card-radius;
  box-shadow: 0 1px 2px rgba(0,0,0,0.03);

  .header-left {
    .welcome-title {
      font-size: 20px; font-weight: 700; color: $text-main; margin: 0 0 4px 0;
    }
    .sub-title { font-size: 13px; color: $text-sub; }
  }
  .header-right {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .header-right .date-text {
    font-size: 14px; color: $text-sub; background: #f9fafb; padding: 6px 12px; border-radius: 6px;
  }
}

/* 数据大屏入口按钮 */
.big-screen-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 18px;
  border-radius: 20px;
  cursor: pointer;
  overflow: hidden;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.5px;
  color: #fff;
  background: linear-gradient(135deg, #3B82F6 0%, #8B5CF6 60%, #EC4899 100%);
  box-shadow: 0 4px 14px rgba(59, 130, 246, 0.35), 0 0 0 0 rgba(59, 130, 246, 0.4);
  transition: transform 0.25s, box-shadow 0.25s;
  user-select: none;

  .btn-icon {
    font-size: 15px;
    filter: drop-shadow(0 0 4px rgba(255,255,255,0.8));
  }
  .btn-text {
    position: relative;
    z-index: 1;
  }
  /* 滑动光晕层 */
  .btn-glow {
    position: absolute;
    top: 0; left: -75%;
    width: 50%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.25), transparent);
    transform: skewX(-20deg);
    transition: left 0.5s;
  }

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 22px rgba(59, 130, 246, 0.5), 0 0 0 3px rgba(139, 92, 246, 0.2);
    .btn-glow { left: 125%; }
  }

  &:active {
    transform: translateY(0);
    box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
  }
}

/* 数据卡片 Top Row */
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
    .trend {
      display: flex; align-items: center; gap: 2px;
      &.up { color: #ef4444; }
      &.down { color: #10b981; }
      &.flat { color: $text-sub; }
    }
    .highlight { color: $primary; font-weight: bold; }
    .badge-text { color: #F59E0B; background: #FEF3C7; padding: 2px 6px; border-radius: 4px; transform: scale(0.9); transform-origin: left center; }
  }

  /* 右侧背景大图标 */
  .card-icon {
    position: absolute; right: -10px; bottom: -15px; font-size: 80px; opacity: 0.1;
    transform: rotate(-15deg); transition: all 0.3s; z-index: 0;
  }
  &:hover .card-icon { opacity: 0.2; transform: rotate(0deg) scale(1.1); }

  /* 颜色主题 */
  &.card-pink .card-icon { color: #EC4899; }
  &.card-green .card-icon { color: #10B981; }
  &.card-teal .card-icon { color: #14B8A6; }
  &.card-blue .card-icon { color: #3B82F6; }
  &.card-purple .card-icon { color: #8B5CF6; }
  &.card-cyan .card-icon { color: #06B6D4; }
  &.card-orange .card-icon { color: #F59E0B; }
  &.has-pending { border-left: 4px solid #F59E0B; }
}

/* 7等分卡片自定义布局 */
.custom-7-cols {
  display: flex;
  flex-wrap: nowrap;
  gap: 20px;
  width: 100%;
}
.custom-7-cols .col-item {
  flex: 1 1 0;
  min-width: 0;
}

/* 运营状态看板 Pipeline Row */
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
    flex: 1; min-width: 0;
    .label { font-size: 13px; color: $text-sub; margin-bottom: 2px; }
    .val { font-size: 20px; font-weight: bold; color: $text-main; font-family: 'DIN Alternate', sans-serif; }
    .val-row { display: flex; align-items: baseline; gap: 8px; min-width: 0; .val { flex-shrink: 0; } }
    // 跟在金额后面,放不下就省略号(悬停看全),不换行
    .sub-val { font-size: 12px; color: #9CA3AF; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  }
}

/* 图表卡片 */
.chart-card {
  border: none; border-radius: $card-radius;
  ::v-deep .el-card__header { padding: 15px 20px; border-bottom: 1px solid #f9fafb; }
  .chart-header {
    display: flex; justify-content: space-between; align-items: center;
    .header-title { font-size: 16px; font-weight: 600; color: $text-main; i { margin-right: 6px; color: var(--admin-accent-text); } }
  }
}

/* 排行榜表格 */
.rank-table {
  ::v-deep th { background-color: #F9FAFB !important; color: $text-sub; font-weight: 500; border-bottom: none; }
  ::v-deep td { border-bottom: 1px solid #f3f4f6; padding: 12px 0; }

  .rank-badge {
    width: 22px; height: 22px; line-height: 22px; text-align: center; border-radius: 6px;
    background: #F3F4F6; color: $text-sub; font-size: 12px; font-weight: bold; margin: 0 auto;

    &.rank-1 { background: linear-gradient(135deg, #FFD700, #FDB931); color: #fff; }
    &.rank-2 { background: linear-gradient(135deg, #E0E0E0, #BDBDBD); color: #fff; }
    &.rank-3 { background: linear-gradient(135deg, #E6A23C, #CD7F32); color: #fff; }
  }

  .goods-info {
    display: flex; align-items: center;
    .goods-img { width: 36px; height: 36px; border-radius: 4px; margin-right: 10px; border: 1px solid #f0f0f0; flex-shrink: 0; }
    .goods-name { font-size: 13px; color: $text-main; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  }

  .sales-text { font-family: 'DIN Alternate'; font-weight: 600; color: $text-main; }
}
</style>
