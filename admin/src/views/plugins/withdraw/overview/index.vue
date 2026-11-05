<template>
  <div class="withdraw-overview">

    <!-- ── 顶部工具栏 ── -->
    <div class="top-bar">
      <div class="top-bar__left">
        <span class="top-bar__title">提现数据概览</span>
        <span class="top-bar__date">{{ todayLabel }}</span>
      </div>
      <div class="top-bar__right">
        <el-radio-group v-model="trendDays" size="small" @change="reload">
          <el-radio-button :label="7">近7天</el-radio-button>
          <el-radio-button :label="14">近14天</el-radio-button>
          <el-radio-button :label="30">近30天</el-radio-button>
        </el-radio-group>
        <el-button type="primary" size="small" icon="el-icon-refresh" :loading="loading" style="margin-left:10px" @click="reload">刷新</el-button>
      </div>
    </div>

    <div v-loading="loading">

      <!-- ── Row 1: 6 KPI 卡 ── -->
      <el-row :gutter="14" class="kpi-row">
        <el-col :span="4" v-for="card in kpiCards" :key="card.key">
          <div class="kpi-card" :style="{ borderTopColor: card.color }">
            <div class="kpi-card__icon" :style="{ background: card.bg }">
              <i :class="card.icon" :style="{ color: card.color }"></i>
            </div>
            <div class="kpi-card__body">
              <div class="kpi-card__value" :style="{ color: card.color }">{{ formatKpi(card) }}</div>
              <div class="kpi-card__label">{{ card.label }}</div>
            </div>
          </div>
        </el-col>
      </el-row>

      <!-- ── Row 2: 提现趋势 (16) + 状态分布甜甜圈 (8) ── -->
      <el-row :gutter="14" style="margin-top:14px">
        <el-col :span="16">
          <el-card shadow="never" class="chart-card">
            <div slot="header" class="chart-card__header">
              <span class="chart-card__title"><i class="el-icon-trend-charts"></i> 提现趋势</span>
              <div class="chart-card__legend">
                <span class="legend-dot" style="background:#6366f1"></span><span class="legend-text">申请金额</span>
                <span class="legend-dot" style="background:#10b981;margin-left:14px"></span><span class="legend-text">到账金额</span>
              </div>
            </div>
            <div ref="trendChart" style="height:280px"></div>
          </el-card>
        </el-col>
        <el-col :span="8">
          <el-card shadow="never" class="chart-card">
            <div slot="header" class="chart-card__header">
              <span class="chart-card__title"><i class="el-icon-pie-chart"></i> 状态分布</span>
            </div>
            <div ref="statusChart" style="height:280px"></div>
          </el-card>
        </el-col>
      </el-row>

      <!-- ── Row 3: 渠道排行 (14) + 资金来源 (10) ── -->
      <el-row :gutter="14" style="margin-top:14px">
        <el-col :span="14">
          <el-card shadow="never" class="chart-card">
            <div slot="header" class="chart-card__header">
              <span class="chart-card__title"><i class="el-icon-s-data"></i> 渠道排行 Top</span>
              <span class="chart-card__sub">按累计申请金额</span>
            </div>
            <div ref="channelChart" style="height:280px"></div>
          </el-card>
        </el-col>
        <el-col :span="10">
          <el-card shadow="never" class="chart-card">
            <div slot="header" class="chart-card__header">
              <span class="chart-card__title"><i class="el-icon-coin"></i> 资金来源分布</span>
            </div>
            <div ref="assetChart" style="height:280px"></div>
          </el-card>
        </el-col>
      </el-row>

      <!-- ── Row 4: 最近提现 ── -->
      <el-card shadow="never" class="chart-card" style="margin-top:14px">
        <div slot="header" class="chart-card__header">
          <span class="chart-card__title"><i class="el-icon-time"></i> 最近提现</span>
        </div>
        <el-table :data="recent" size="small" border style="width:100%" empty-text="暂无提现记录">
          <el-table-column prop="order_no" label="单号" width="180" show-overflow-tooltip />
          <el-table-column prop="nickname" label="用户" width="120" show-overflow-tooltip />
          <el-table-column prop="channel_name" label="渠道" width="120" show-overflow-tooltip />
          <el-table-column prop="asset_label" label="来源" width="90" align="center" />
          <el-table-column label="申请金额" width="110" align="right">
            <template #default="{ row }"><span style="color:#1e293b;font-weight:600">{{ money(row.amount) }}</span></template>
          </el-table-column>
          <el-table-column label="实际到账" width="110" align="right">
            <template #default="{ row }"><span style="color:#22c55e">{{ money(row.actual_amount) }}</span></template>
          </el-table-column>
          <el-table-column label="状态" width="90" align="center">
            <template #default="{ row }">
              <el-tag :type="statusTagType(row.status)" size="mini" effect="plain">{{ row.status_text }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="created_time" label="申请时间" min-width="160" align="center" />
        </el-table>
      </el-card>

    </div>
  </div>
</template>

<script>
import * as echarts from 'echarts'

export default {
  name: 'WithdrawOverview',

  data() {
    return {
      loading: false,
      trendDays: 14,
      kpi: {
        total_amount: 0, total_count: 0, month_amount: 0,
        pending_count: 0, success_rate: 0, total_fee: 0,
      },
      trend: { labels: [], apply_series: [], paid_series: [] },
      statusDist: [],
      assetDist: [],
      channelRank: [],
      recent: [],
      // echarts instances
      _trendChart: null, _statusChart: null, _channelChart: null, _assetChart: null,
    }
  },

  computed: {
    todayLabel() {
      const d = new Date()
      return d.getFullYear() + '年' + (d.getMonth() + 1) + '月' + d.getDate() + '日'
    },
    kpiCards() {
      return [
        { key: 'total_amount',  label: '累计提现金额', icon: 'el-icon-wallet',     color: '#6366f1', bg: '#eef2ff', money: true },
        { key: 'total_count',   label: '累计提现笔数', icon: 'el-icon-finished',   color: '#0ea5e9', bg: '#e0f2fe' },
        { key: 'month_amount',  label: '本月提现金额', icon: 'el-icon-date',       color: '#22c55e', bg: '#f0fdf4', money: true },
        { key: 'pending_count', label: '待处理笔数',   icon: 'el-icon-time',       color: '#f59e0b', bg: '#fffbeb' },
        { key: 'success_rate',  label: '提现成功率',   icon: 'el-icon-data-line',  color: '#7c3aed', bg: '#f5f3ff', suffix: '%' },
        { key: 'total_fee',     label: '累计手续费',   icon: 'el-icon-coin',       color: '#ef4444', bg: '#fef2f2', money: true },
      ]
    },
  },

  mounted() {
    this.reload()
    window.addEventListener('resize', this.resizeCharts)
  },

  beforeDestroy() {
    window.removeEventListener('resize', this.resizeCharts)
    ;[this._trendChart, this._statusChart, this._channelChart, this._assetChart].forEach(c => c && c.dispose())
  },

  methods: {
    money(v) {
      return '¥' + Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
    formatKpi(card) {
      const v = this.kpi[card.key] || 0
      if (card.money) return this.money(v)
      if (card.suffix) return v + card.suffix
      return Number(v).toLocaleString('zh-CN')
    },
    statusTagType(s) {
      return { 0: 'info', 1: 'primary', 2: 'warning', 3: 'success', 4: 'danger', 5: 'danger', 6: 'info' }[s] || 'info'
    },

    async reload() {
      this.loading = true
      try {
        const res = await this.$request({ url: '/plugin/withdraw/admin/overview/index', method: 'get', data: { trend_days: this.trendDays } })
        if (res.code === 0) {
          const d = res.data
          this.kpi = d.kpi || this.kpi
          this.trend = d.trend || { labels: [], apply_series: [], paid_series: [] }
          this.statusDist = d.status_dist || []
          this.assetDist = d.asset_dist || []
          this.channelRank = d.channel_rank || []
          this.recent = d.recent || []
          this.$nextTick(() => {
            this.renderTrendChart()
            this.renderStatusChart()
            this.renderChannelChart()
            this.renderAssetChart()
          })
        } else {
          this.$message.error(res.msg || '加载失败')
        }
      } finally {
        this.loading = false
      }
    },

    // ── ECharts ────────────────────────────────────────────────

    renderTrendChart() {
      const dom = this.$refs.trendChart
      if (!dom) return
      if (!this._trendChart) this._trendChart = echarts.init(dom)
      const t = this.trend
      this._trendChart.setOption({
        tooltip: { trigger: 'axis', axisPointer: { type: 'cross' }, valueFormatter: v => '¥' + Number(v).toLocaleString('zh-CN') },
        grid: { top: 20, right: 20, bottom: 36, left: 56 },
        xAxis: { type: 'category', data: t.labels, axisLine: { lineStyle: { color: '#e2e8f0' } }, axisTick: { show: false }, axisLabel: { color: '#94a3b8', fontSize: 11 } },
        yAxis: { type: 'value', splitLine: { lineStyle: { color: '#f1f5f9', type: 'dashed' } }, axisLabel: { color: '#94a3b8', fontSize: 11 } },
        series: [
          {
            name: '申请金额', type: 'line', smooth: true, symbol: 'circle', symbolSize: 6,
            data: t.apply_series,
            lineStyle: { color: '#6366f1', width: 3 }, itemStyle: { color: '#6366f1' },
            areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: 'rgba(99,102,241,0.25)' }, { offset: 1, color: 'rgba(99,102,241,0.02)' }]) },
          },
          {
            name: '到账金额', type: 'line', smooth: true, symbol: 'circle', symbolSize: 6,
            data: t.paid_series,
            lineStyle: { color: '#10b981', width: 3 }, itemStyle: { color: '#10b981' },
            areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: 'rgba(16,185,129,0.2)' }, { offset: 1, color: 'rgba(16,185,129,0.02)' }]) },
          },
        ],
      })
    },

    renderStatusChart() {
      const dom = this.$refs.statusChart
      if (!dom) return
      if (!this._statusChart) this._statusChart = echarts.init(dom)
      const colorMap = { 0: '#94a3b8', 1: '#6366f1', 2: '#f59e0b', 3: '#22c55e', 4: '#ef4444', 5: '#ef4444', 6: '#94a3b8' }
      const data = this.statusDist
        .filter(s => s.value > 0)
        .map(s => ({ value: s.value, name: s.name, itemStyle: { color: colorMap[s.status] || '#94a3b8' } }))
      const total = data.reduce((a, b) => a + b.value, 0)
      this._statusChart.setOption({
        tooltip: { trigger: 'item', formatter: '{b}: {c} ({d}%)' },
        legend: { bottom: 0, itemWidth: 10, itemHeight: 10, textStyle: { color: '#64748b', fontSize: 12 } },
        series: [{
          type: 'pie', radius: ['52%', '76%'], center: ['50%', '42%'], avoidLabelOverlap: false,
          label: { show: true, position: 'center', formatter: () => `{total|${total}}\n{sub|提现总笔数}`, rich: { total: { fontSize: 28, fontWeight: 700, color: '#1e293b', lineHeight: 36 }, sub: { fontSize: 12, color: '#94a3b8' } } },
          emphasis: { label: { show: true }, scale: true, scaleSize: 6 },
          labelLine: { show: false },
          data: data.length ? data : [{ value: 1, name: '暂无数据', itemStyle: { color: '#e2e8f0' } }],
        }],
      })
    },

    renderChannelChart() {
      const dom = this.$refs.channelChart
      if (!dom) return
      if (!this._channelChart) this._channelChart = echarts.init(dom)
      const list = [...this.channelRank].reverse()
      const colors = ['#6366f1', '#8b5cf6', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#14b8a6']
      this._channelChart.setOption({
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: v => '¥' + Number(v).toLocaleString('zh-CN') },
        grid: { top: 10, right: 70, bottom: 10, left: 10, containLabel: true },
        xAxis: { type: 'value', axisLabel: { color: '#94a3b8', fontSize: 11 }, splitLine: { lineStyle: { color: '#f1f5f9', type: 'dashed' } } },
        yAxis: { type: 'category', data: list.map(c => (c.name.length > 10 ? c.name.slice(0, 10) + '…' : c.name)), axisLabel: { color: '#475569', fontSize: 12 }, axisTick: { show: false }, axisLine: { show: false } },
        series: [{
          type: 'bar', barMaxWidth: 22,
          data: list.map((c, i) => ({ value: c.amount, itemStyle: { color: colors[i % colors.length], borderRadius: [0, 6, 6, 0] } })),
          label: { show: true, position: 'right', color: '#64748b', fontSize: 12, formatter: p => '¥' + Number(p.value).toLocaleString('zh-CN') },
        }],
      })
    },

    renderAssetChart() {
      const dom = this.$refs.assetChart
      if (!dom) return
      if (!this._assetChart) this._assetChart = echarts.init(dom)
      const palette = { balance: '#0ea5e9', price: '#6366f1', integral: '#f59e0b', other: '#94a3b8' }
      const data = this.assetDist.map(a => ({ value: a.amount, name: a.label, itemStyle: { color: palette[a.asset] || '#94a3b8' } }))
      this._assetChart.setOption({
        tooltip: { trigger: 'item', formatter: '{b}: ¥{c} ({d}%)' },
        legend: { bottom: 0, itemWidth: 10, itemHeight: 10, textStyle: { color: '#64748b', fontSize: 12 } },
        series: [{
          type: 'pie', radius: ['50%', '74%'], center: ['50%', '44%'],
          roseType: 'radius', avoidLabelOverlap: false,
          label: { show: false }, labelLine: { show: false },
          emphasis: { scale: true, scaleSize: 6 },
          data: data.length ? data : [{ value: 1, name: '暂无数据', itemStyle: { color: '#e2e8f0' } }],
        }],
      })
    },

    resizeCharts() {
      ;[this._trendChart, this._statusChart, this._channelChart, this._assetChart].forEach(c => c && c.resize())
    },
  },
}
</script>

<style scoped>
.withdraw-overview { padding: 16px; background: #f8fafc; min-height: calc(100vh - 84px); }

/* 顶部栏 */
.top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.top-bar__left { display: flex; align-items: baseline; gap: 12px; }
.top-bar__title { font-size: 20px; font-weight: 700; color: #0f172a; }
.top-bar__date  { font-size: 13px; color: #94a3b8; }
.top-bar__right { display: flex; align-items: center; }

/* KPI 卡片 */
.kpi-row { margin-top: 0; }
.kpi-card {
  background: #fff;
  border-radius: 12px;
  border-top: 3px solid #e2e8f0;
  padding: 16px 14px;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06);
  transition: transform 0.15s, box-shadow 0.15s;
  cursor: default;
}
.kpi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.09); }
.kpi-card__icon {
  width: 44px; height: 44px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; flex-shrink: 0;
}
.kpi-card__value { font-size: 24px; font-weight: 700; line-height: 1.1; word-break: break-all; }
.kpi-card__label { font-size: 12px; color: #94a3b8; margin-top: 3px; }

/* 图表卡片 */
.chart-card { border-radius: 12px; }
.chart-card >>> .el-card__header { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; }
.chart-card >>> .el-card__body  { padding: 16px; }
.chart-card__header { display: flex; align-items: center; justify-content: space-between; }
.chart-card__title  { font-size: 14px; font-weight: 600; color: #1e293b; }
.chart-card__title i { margin-right: 6px; }
.chart-card__sub    { font-size: 12px; color: #94a3b8; }

/* 图例 */
.legend-dot  { display: inline-block; width: 8px; height: 8px; border-radius: 50%; vertical-align: middle; }
.legend-text { font-size: 12px; color: #64748b; margin-left: 4px; vertical-align: middle; }
</style>
