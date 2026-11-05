<template>
  <div class="trend-wrap" :style="{ height: height }">
    <div ref="chart" class="trend-chart" />
    <div v-if="isEmpty" class="trend-empty">{{ emptyText }}</div>
  </div>
</template>

<script>
// 说明:与 dealer 的同名组件是各自独立的副本。
// 这是**纯展示**组件(echarts 包装,零业务逻辑),不存在跟着算法漂移的问题;
// 反过来若跨插件 import,dealer 一旦被卸载,分销概览页就会直接崩。
import * as echarts from 'echarts'

export default {
  props: {
    height: { type: String, default: '320px' },
    // { xAxisData: [], seriesData: [] }
    chartData: { type: Object, required: true },
    name: { type: String, default: '分销佣金' },
    color: { type: String, default: '#1890ff' },
    emptyText: { type: String, default: '近 30 天暂无新增' }
  },
  data() {
    return { chart: null }
  },
  computed: {
    isEmpty() {
      const s = (this.chartData && this.chartData.seriesData) || []
      return !s.some(v => Number(v) > 0)
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
    render() {
      if (!this.chart) return
      const { xAxisData = [], seriesData = [] } = this.chartData || {}
      const c = this.color
      const money = v => '¥' + Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
      this.chart.setOption({
        grid: { left: 8, right: 16, top: 24, bottom: 4, containLabel: true },
        tooltip: {
          trigger: 'axis',
          backgroundColor: '#fff',
          borderColor: '#e5e6eb',
          textStyle: { color: '#1f2329' },
          axisPointer: { type: 'line', lineStyle: { color: '#c9cdd4', type: 'dashed' } },
          formatter: ps => {
            const p = ps[0]
            return p.axisValue + '<br/><span style="color:' + c + '">●</span> ' + this.name + ' <b>' + money(p.value) + '</b>'
          }
        },
        xAxis: {
          type: 'category',
          data: xAxisData,
          boundaryGap: false,
          axisLine: { lineStyle: { color: '#e5e6eb' } },
          axisTick: { show: false },
          axisLabel: { color: '#86909c', interval: 4 }
        },
        yAxis: {
          type: 'value',
          axisLine: { show: false },
          axisTick: { show: false },
          axisLabel: { color: '#86909c' },
          splitLine: { lineStyle: { color: '#f2f3f5' } }
        },
        series: [{
          name: this.name,
          type: 'line',
          smooth: true,
          showSymbol: false,
          symbolSize: 7,
          data: seriesData,
          lineStyle: { color: c, width: 2.5 },
          itemStyle: { color: c, borderColor: '#fff', borderWidth: 2 },
          emphasis: { focus: 'series' },
          areaStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: hexA(c, 0.22) },
              { offset: 1, color: hexA(c, 0.01) }
            ])
          }
        }]
      }, true)
    }
  }
}

function hexA(hex, a) {
  const n = parseInt(hex.slice(1), 16)
  return 'rgba(' + (n >> 16 & 255) + ',' + (n >> 8 & 255) + ',' + (n & 255) + ',' + a + ')'
}
</script>

<style scoped>
.trend-wrap { position: relative; width: 100%; }
.trend-chart { width: 100%; height: 100%; }
.trend-empty {
  position: absolute; left: 50%; top: 45%; transform: translate(-50%, -50%);
  padding: 6px 14px; border-radius: 16px; background: #f7f8fa;
  font-size: 13px; color: #86909c; pointer-events: none;
}
</style>
