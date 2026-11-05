<template>
  <div class="donut-wrap" :style="{ height: height }">
    <div ref="chart" class="donut-chart" />
    <div v-if="!hasData" class="donut-empty">{{ emptyText }}</div>
  </div>
</template>

<script>
// 与 dealer 同名组件各自独立(插件可单独卸载,别跨插件 import),纯展示
import * as echarts from 'echarts'

const COLORS = ['#1890ff', '#13c2c2', '#722ed1', '#fa8c16', '#eb2f96', '#52c41a', '#faad14']

export default {
  props: {
    height: { type: String, default: '320px' },
    // [{ name, value }]
    chartData: { type: Array, required: true },
    // 中间那行小字 + 数值怎么显示
    centerLabel: { type: String, default: '合计' },
    money: { type: Boolean, default: true },
    unit: { type: String, default: '' },
    emptyText: { type: String, default: '暂无数据' }
  },
  data() {
    return { chart: null }
  },
  computed: {
    hasData() {
      return (this.chartData || []).some(i => Number(i.value) > 0)
    }
  },
  watch: {
    chartData: { deep: true, handler() { this.render() } }
  },
  mounted() {
    this.$nextTick(() => {
      this.chart = echarts.init(this.$refs.chart)
      this.render()
      window.addEventListener('resize', this.resize)
    })
  },
  beforeDestroy() {
    window.removeEventListener('resize', this.resize)
    if (this.chart) this.chart.dispose()
    this.chart = null
  },
  methods: {
    resize() {
      if (this.chart) this.chart.resize()
    },
    fmt(v) {
      return this.money
        ? '¥' + Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : Number(v || 0).toLocaleString('zh-CN') + this.unit
    },
    render() {
      if (!this.chart) return
      const data = (this.chartData || []).map((d, i) => ({ name: d.name, value: Number(d.value) || 0, itemStyle: { color: COLORS[i % COLORS.length] } }))
      const total = data.reduce((s, d) => s + d.value, 0)
      this.chart.setOption({
        tooltip: {
          trigger: 'item',
          backgroundColor: '#fff',
          borderColor: '#e5e6eb',
          textStyle: { color: '#1f2329' },
          formatter: p => p.marker + p.name + '<br/><b>' + this.fmt(p.value) + '</b>(' + p.percent + '%)'
        },
        legend: {
          bottom: 0,
          left: 'center',
          icon: 'circle',
          itemWidth: 8,
          itemHeight: 8,
          itemGap: 14,
          textStyle: { color: '#4e5969', fontSize: 12 }
          // 不用 scroll:等级名长的时候翻页图例要点箭头才看得全,直接换行
        },
        title: this.hasData ? {
          text: this.fmt(total),
          subtext: this.centerLabel,
          left: 'center',
          top: '30%',
          itemGap: 4,
          textStyle: { fontSize: 18, fontWeight: 700, color: '#1f2329' },
          subtextStyle: { fontSize: 12, color: '#86909c' }
        } : { text: '' },
        series: [{
          type: 'pie',
          radius: ['44%', '62%'],
          center: ['50%', '38%'],
          avoidLabelOverlap: true,
          label: { show: false },
          labelLine: { show: false },
          itemStyle: { borderColor: '#fff', borderWidth: 2 },
          emphasis: { scale: true, scaleSize: 4 },
          data
        }]
      }, true)
    }
  }
}
</script>

<style scoped>
.donut-wrap { position: relative; width: 100%; }
.donut-chart { width: 100%; height: 100%; }
.donut-empty {
  position: absolute; left: 50%; top: 45%; transform: translate(-50%, -50%);
  padding: 6px 14px; border-radius: 16px; background: #f7f8fa;
  font-size: 13px; color: #86909c; pointer-events: none;
}
</style>
