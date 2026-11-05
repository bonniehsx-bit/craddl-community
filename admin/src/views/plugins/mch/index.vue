<template>
  <div class="app-container mch-dash" v-loading="loading">

    <!-- ── 页头:标题 + 周期切换 + 刷新 ── -->
    <div class="dash-head">
      <div class="head-left">
        <h2 class="dash-title">多商户数据概览</h2>
        <span class="dash-sub">平台侧经营总览 · 口径见下方说明</span>
      </div>
      <div class="head-right">
        <el-radio-group v-model="period" size="small" @change="fetchData">
          <el-radio-button label="today">今日</el-radio-button>
          <el-radio-button label="yesterday">昨日</el-radio-button>
          <el-radio-button label="week">近7日</el-radio-button>
          <el-radio-button label="month">本月</el-radio-button>
          <el-radio-button label="total">累计</el-radio-button>
        </el-radio-group>
        <el-button size="small" icon="el-icon-refresh" circle @click="fetchData" title="刷新"></el-button>
      </div>
    </div>

    <!-- ── 四张主卡 ── -->
    <div class="hero-grid">
      <div class="hero-card" v-for="c in heroCards" :key="c.key" :class="'accent-' + c.tone">
        <div class="hero-bar"></div>
        <div class="hero-body">
          <div class="hero-label">
            {{ c.label }}
            <el-tooltip v-if="c.tip" effect="dark" placement="top" :content="c.tip">
              <i class="el-icon-question hero-tip"></i>
            </el-tooltip>
            <span v-if="c.realtime" class="realtime-badge">实时</span>
          </div>
          <div class="hero-value">
            <span class="cur">¥</span>{{ c.value | money }}
          </div>
          <div class="hero-foot">{{ c.foot }}</div>
        </div>
      </div>
    </div>

    <!-- ── 次级指标条 ── -->
    <div class="mini-grid">
      <div class="mini-card" v-for="m in miniCards" :key="m.label">
        <div class="mini-label">{{ m.label }}</div>
        <div class="mini-value">{{ m.value }}</div>
      </div>
    </div>

    <!-- ── 待办 ── -->
    <div class="todo-bar">
      <div class="todo-title"><i class="el-icon-bell"></i> 待处理</div>
      <div class="todo-items">
        <div
          class="todo-item"
          :class="{ active: data.todo.mch_pending > 0, clickable: true }"
          @click="goTo('/plugins/mch/mch-list')"
        >
          <span class="t-num">{{ data.todo.mch_pending }}</span>
          <span class="t-label">待审核商家</span>
          <i class="el-icon-arrow-right"></i>
        </div>
        <div class="todo-item" :class="{ active: data.todo.goods_pending > 0 }">
          <span class="t-num">{{ data.todo.goods_pending }}</span>
          <span class="t-label">待审核商品</span>
        </div>
        <div class="todo-item" :class="{ active: data.todo.refunding > 0 }">
          <span class="t-num">{{ data.todo.refunding }}</span>
          <span class="t-label">进行中售后</span>
        </div>
      </div>
    </div>

    <!-- ── 趋势图 ── -->
    <div class="panel">
      <div class="panel-head">
        <span class="panel-title">近 30 天成交趋势</span>
        <span class="panel-note">与上方卡片同口径(含已退款订单的成交额)</span>
      </div>
      <div ref="trendChart" class="chart-box"></div>
    </div>

    <!-- ── 三栏:商家 / 结构 / Top ── -->
    <el-row :gutter="16" class="bottom-row">
      <el-col :xs="24" :md="8">
        <div class="panel full-h">
          <div class="panel-head"><span class="panel-title">商家概况</span></div>

          <div class="mch-topline">
            <div class="mt-item">
              <div class="mt-num">{{ data.merchant.total }}</div>
              <div class="mt-label">商家总数</div>
            </div>
            <div class="mt-item">
              <div class="mt-num up">+{{ data.merchant.today_new }}</div>
              <div class="mt-label">今日新增</div>
            </div>
          </div>

          <div class="status-list">
            <div class="status-row" v-for="s in statusRows" :key="s.label">
              <span class="s-dot" :style="{ background: s.color }"></span>
              <span class="s-label">{{ s.label }}</span>
              <span class="s-count">{{ s.count }}</span>
              <div class="s-track">
                <div class="s-fill" :style="{ width: pct(s.count, data.merchant.total), background: s.color }"></div>
              </div>
            </div>
          </div>

          <div class="type-tags" v-if="data.merchant.by_type.length">
            <el-tag
              v-for="t in data.merchant.by_type"
              :key="t.type"
              size="mini"
              effect="plain"
              class="type-tag"
            >{{ t.label }} {{ t.count }}</el-tag>
          </div>
        </div>
      </el-col>

      <el-col :xs="24" :md="8">
        <div class="panel full-h">
          <div class="panel-head"><span class="panel-title">履约与订单结构</span></div>
          <div ref="mixChart" class="chart-box small"></div>
          <div class="type-split" v-if="data.mix.order_type.length">
            <div class="ts-item" v-for="t in data.mix.order_type" :key="t.key">
              <span class="ts-label">{{ t.label }}</span>
              <span class="ts-val">{{ t.count }} 单 · ¥{{ t.amount | money }}</span>
            </div>
          </div>
        </div>
      </el-col>

      <el-col :xs="24" :md="8">
        <div class="panel full-h">
          <div class="panel-head"><span class="panel-title">Top 商家</span><span class="panel-note">按本期成交额</span></div>
          <div class="rank-list" v-if="data.top_mch.length">
            <div class="rank-row" v-for="(m, i) in data.top_mch" :key="m.mch_id">
              <span class="rk" :class="'rk-' + (i < 3 ? (i + 1) : 'n')">{{ i + 1 }}</span>
              <span class="rk-name" :title="m.name">{{ m.name }}</span>
              <span class="rk-cnt">{{ m.count }}单</span>
              <span class="rk-amt">¥{{ m.amount | money }}</span>
            </div>
          </div>
          <div v-else class="empty-tip">本期暂无成交</div>
        </div>
      </el-col>
    </el-row>

    <!-- ── 口径说明 ── -->
    <div class="metric-note">
      <div class="note-title"><i class="el-icon-info"></i> 指标口径</div>
      <ul>
        <li><b>成交额</b>:商家订单中已支付(含部分退款、已退款)的实付金额之和,按支付时间落期 —— 退过款的单仍算成交过,再由退款额扣减得净额。</li>
        <li><b>退款额</b>:实际退款成功的金额,按退款发生时间落期;不是售后申请金额。</li>
        <li><b>平台佣金</b>:平台从商家订单中抽取的服务费,退款时会随之重算。</li>
        <li><b>待结算货款</b>:尚未打款给商家的货款,是<b>实时存量</b>,不随上方时间切换而变化。</li>
      </ul>
    </div>
  </div>
</template>

<script>
import * as echarts from 'echarts'

const emptyData = () => ({
  trade: {
    gmv: '0.00', order_count: 0, refund_amount: '0.00', net_amount: '0.00',
    platform_income: '0.00', mch_income: '0.00', express_amount: '0.00',
    avg_order_price: '0.00', refund_rate: 0
  },
  settlement: { pending_amount: '0.00', settled_amount: '0.00', pending_orders: 0 },
  merchant: { total: 0, today_new: 0, pending: 0, normal: 0, rejected: 0, disabled: 0, by_type: [] },
  goods: { on_sale: 0, pending_check: 0 },
  todo: { mch_pending: 0, goods_pending: 0, refunding: 0 },
  mix: { deliver: [], order_type: [] },
  trend: [],
  top_mch: []
})

export default {
  name: 'MchDashboard',
  filters: {
    money(v) {
      const n = Number(v || 0)
      return n.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    }
  },
  data() {
    return {
      loading: false,
      period: 'today',
      data: emptyData(),
      trendChart: null,
      mixChart: null
    }
  },
  computed: {
    heroCards() {
      const t = this.data.trade
      const s = this.data.settlement
      return [
        {
          key: 'gmv', tone: 'blue', label: '成交额', value: t.gmv,
          foot: `${t.order_count} 单 · 客单价 ¥${t.avg_order_price}`,
          tip: '商家订单已支付金额之和(含已退款的单),按支付时间落期'
        },
        {
          key: 'net', tone: 'green', label: '净额', value: t.net_amount,
          foot: `已扣退款 ¥${t.refund_amount} · 退款率 ${t.refund_rate}%`,
          tip: '成交额 − 实际退款成功金额'
        },
        {
          key: 'commission', tone: 'purple', label: '平台佣金', value: t.platform_income,
          foot: `商家应得 ¥${t.mch_income}`,
          tip: '平台从商家订单抽取的服务费,退款时随之重算'
        },
        {
          key: 'pending', tone: 'orange', label: '待结算货款', value: s.pending_amount,
          foot: `${s.pending_orders} 单待结算 · 本期已结 ¥${s.settled_amount}`,
          realtime: true,
          tip: '尚未打款给商家的货款,是实时存量,不随时间切换变化'
        }
      ]
    },
    miniCards() {
      const t = this.data.trade
      const g = this.data.goods
      return [
        { label: '订单数', value: t.order_count },
        { label: '运费收入', value: '¥' + t.express_amount },
        { label: '在售商品', value: g.on_sale },
        { label: '待审商品', value: g.pending_check }
      ]
    },
    statusRows() {
      const m = this.data.merchant
      return [
        { label: '正常营业', count: m.normal, color: '#22c55e' },
        { label: '待审核', count: m.pending, color: '#f59e0b' },
        { label: '已驳回', count: m.rejected, color: '#94a3b8' },
        { label: '已禁用', count: m.disabled, color: '#ef4444' }
      ]
    }
  },
  created() {
    this.fetchData()
  },
  mounted() {
    window.addEventListener('resize', this.resizeCharts)
  },
  beforeDestroy() {
    window.removeEventListener('resize', this.resizeCharts)
    if (this.trendChart) this.trendChart.dispose()
    if (this.mixChart) this.mixChart.dispose()
  },
  methods: {
    pct(v, total) {
      if (!total) return '0%'
      return Math.round((Number(v) / Number(total)) * 100) + '%'
    },
    goTo(path) {
      this.$router.push(path)
    },
    async fetchData() {
      this.loading = true
      try {
        const res = await this.$request({
          url: '/plugin/mch/mall/mch/dashboard',
          method: 'get',
          data: { period: this.period }
        })
        if (res.code === 0) {
          // 后端字段缺失时用空壳兜底,避免模板取值报错整页白屏
          this.data = Object.assign(emptyData(), res.data || {})
          this.$nextTick(this.renderCharts)
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {
        console.error('加载数据概览失败:', e)
      } finally {
        this.loading = false
      }
    },
    resizeCharts() {
      if (this.trendChart) this.trendChart.resize()
      if (this.mixChart) this.mixChart.resize()
    },
    renderCharts() {
      this.renderTrend()
      this.renderMix()
      // 首次渲染时侧边栏折叠动画/栅格换行可能还没沉降完,echarts 会按当时(偏窄的)宽度
      // 画好 canvas 就不再自适应 —— 实测会得到只有容器一半宽的图。window.resize 监听
      // 只覆盖窗口尺寸变化,兜不住这种"容器变了但窗口没变"的场景,故这里补一次。
      setTimeout(this.resizeCharts, 60)
    },
    renderTrend() {
      const dom = this.$refs.trendChart
      if (!dom) return
      if (!this.trendChart) this.trendChart = echarts.init(dom)

      const list = this.data.trend || []
      this.trendChart.setOption({
        grid: { left: 8, right: 16, top: 24, bottom: 8, containLabel: true },
        tooltip: {
          trigger: 'axis',
          formatter: (p) => {
            const it = p[0]
            const row = list[it.dataIndex] || {}
            return `${it.axisValue}<br/>成交额 ¥${Number(it.data).toLocaleString('zh-CN', { minimumFractionDigits: 2 })}<br/>订单 ${row.count || 0} 单`
          }
        },
        xAxis: {
          type: 'category',
          data: list.map(i => i.date.slice(5)),
          boundaryGap: false,
          axisLine: { lineStyle: { color: '#e5e7eb' } },
          axisLabel: { color: '#94a3b8', fontSize: 11, interval: 4 }
        },
        yAxis: {
          type: 'value',
          splitLine: { lineStyle: { color: '#f1f5f9' } },
          axisLabel: { color: '#94a3b8', fontSize: 11 }
        },
        series: [{
          type: 'line',
          smooth: true,
          symbol: 'circle',
          symbolSize: 5,
          showSymbol: false,
          data: list.map(i => Number(i.amount)),
          lineStyle: { width: 2.5, color: '#4f78ff' },
          itemStyle: { color: '#4f78ff' },
          areaStyle: {
            color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
              { offset: 0, color: 'rgba(79,120,255,0.28)' },
              { offset: 1, color: 'rgba(79,120,255,0.02)' }
            ])
          }
        }]
      })
    },
    renderMix() {
      const dom = this.$refs.mixChart
      if (!dom) return
      if (!this.mixChart) this.mixChart = echarts.init(dom)

      const list = (this.data.mix.deliver || []).filter(i => i.count > 0)
      const palette = ['#4f78ff', '#22c55e', '#f59e0b', '#a855f7', '#64748b']

      this.mixChart.setOption({
        tooltip: {
          trigger: 'item',
          formatter: (p) => `${p.name}<br/>${p.value} 单 (${p.percent}%)`
        },
        legend: {
          bottom: 0,
          itemWidth: 8,
          itemHeight: 8,
          textStyle: { color: '#64748b', fontSize: 11 }
        },
        series: [{
          type: 'pie',
          radius: ['45%', '68%'],
          center: ['50%', '44%'],
          avoidLabelOverlap: true,
          label: { show: false },
          data: list.length
            ? list.map((i, idx) => ({
              name: i.label,
              value: i.count,
              itemStyle: { color: palette[idx % palette.length] }
            }))
            : [{ name: '暂无数据', value: 1, itemStyle: { color: '#e2e8f0' } }]
        }]
      })
    }
  }
}
</script>

<style lang="scss" scoped>
$radius: 10px;
$border: #eef1f6;

.mch-dash {
  background: #f6f8fb;
  min-height: 100%;
}

/* ── 页头 ── */
.dash-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 18px;

  .dash-title {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: #1e293b;
  }
  .dash-sub {
    font-size: 12px;
    color: #94a3b8;
    margin-left: 2px;
  }
  .head-right {
    display: flex;
    align-items: center;
    gap: 10px;
  }
}

/* ── 主卡 ── */
/* 显式断点而非 auto-fit:auto-fit 在中等宽度会排成 3+1,落单的第四张很难看;
   这里让它 4 → 2×2 → 1,任何宽度下都是整齐的矩形。 */
.hero-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 16px;

  @media (max-width: 1400px) { grid-template-columns: repeat(2, 1fr); }
  @media (max-width: 680px)  { grid-template-columns: 1fr; }
}

.hero-card {
  position: relative;
  background: #fff;
  border: 1px solid $border;
  border-radius: $radius;
  overflow: hidden;
  transition: box-shadow .25s, transform .25s;

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(15, 23, 42, .07);
  }

  .hero-bar {
    height: 3px;
    width: 100%;
  }
  .hero-body { padding: 16px 18px 18px; }

  .hero-label {
    font-size: 13px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 5px;
  }
  .hero-tip {
    color: #cbd5e1;
    cursor: help;
    font-size: 13px;
  }
  .realtime-badge {
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 8px;
    background: #fff7ed;
    color: #ea8c00;
    border: 1px solid #fed7aa;
  }
  .hero-value {
    margin: 8px 0 6px;
    font-size: 27px;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.15;
    letter-spacing: -.4px;

    .cur {
      font-size: 15px;
      font-weight: 500;
      color: #94a3b8;
      margin-right: 3px;
    }
  }
  .hero-foot {
    font-size: 12px;
    color: #94a3b8;
  }

  &.accent-blue   .hero-bar { background: linear-gradient(90deg, #4f78ff, #7aa2ff); }
  &.accent-green  .hero-bar { background: linear-gradient(90deg, #16a34a, #4ade80); }
  &.accent-purple .hero-bar { background: linear-gradient(90deg, #7c3aed, #c084fc); }
  &.accent-orange .hero-bar { background: linear-gradient(90deg, #ea8c00, #fbbf24); }
}

/* ── 次级指标 ── */
.mini-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 16px;

  @media (max-width: 680px) { grid-template-columns: repeat(2, 1fr); }
}
.mini-card {
  background: #fff;
  border: 1px solid $border;
  border-radius: $radius;
  padding: 12px 16px;

  .mini-label { font-size: 12px; color: #94a3b8; }
  .mini-value {
    margin-top: 4px;
    font-size: 18px;
    font-weight: 600;
    color: #334155;
  }
}

/* ── 待办 ── */
.todo-bar {
  display: flex;
  align-items: center;
  gap: 18px;
  flex-wrap: wrap;
  background: #fff;
  border: 1px solid $border;
  border-radius: $radius;
  padding: 12px 18px;
  margin-bottom: 16px;

  .todo-title {
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    white-space: nowrap;
    i { color: #f59e0b; }
  }
  .todo-items { display: flex; gap: 10px; flex-wrap: wrap; }

  .todo-item {
    display: flex;
    align-items: baseline;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    background: #f8fafc;
    border: 1px solid transparent;

    .t-num { font-size: 17px; font-weight: 600; color: #cbd5e1; }
    .t-label { font-size: 12px; color: #94a3b8; }
    i { font-size: 12px; color: #cbd5e1; }

    &.clickable { cursor: pointer; }
    &.active {
      background: #fff7ed;
      border-color: #fed7aa;
      .t-num { color: #ea8c00; }
      .t-label { color: #b45309; }
      i { color: #ea8c00; }
    }
    &.clickable:hover { border-color: var(--admin-accent-text); }
  }
}

/* ── 面板 ── */
.panel {
  background: #fff;
  border: 1px solid $border;
  border-radius: $radius;
  padding: 16px 18px;
  margin-bottom: 16px;

  &.full-h { height: 100%; box-sizing: border-box; }
}
.panel-head {
  display: flex;
  align-items: baseline;
  gap: 10px;
  margin-bottom: 12px;

  .panel-title { font-size: 14px; font-weight: 600; color: #1e293b; }
  .panel-note { font-size: 11px; color: #b4bdc9; }
}
.chart-box {
  height: 260px;
  &.small { height: 200px; }
}
.empty-tip {
  text-align: center;
  color: #b4bdc9;
  font-size: 12px;
  padding: 40px 0;
}

/* ── 商家概况 ── */
.mch-topline {
  display: flex;
  gap: 26px;
  padding-bottom: 14px;
  border-bottom: 1px dashed $border;
  margin-bottom: 12px;

  .mt-num {
    font-size: 22px;
    font-weight: 600;
    color: #0f172a;
    &.up { color: #16a34a; }
  }
  .mt-label { font-size: 12px; color: #94a3b8; margin-top: 2px; }
}
.status-list .status-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 9px;

  .s-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
  .s-label { font-size: 12px; color: #64748b; width: 56px; flex-shrink: 0; }
  .s-count { font-size: 12px; color: #334155; width: 34px; text-align: right; flex-shrink: 0; }
  .s-track {
    flex: 1;
    height: 5px;
    background: #f1f5f9;
    border-radius: 3px;
    overflow: hidden;
  }
  .s-fill { height: 100%; border-radius: 3px; transition: width .4s; }
}
.type-tags {
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px dashed $border;
  .type-tag { margin: 0 6px 6px 0; }
}

/* ── 结构 ── */
.type-split {
  border-top: 1px dashed $border;
  padding-top: 10px;
  .ts-item {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    padding: 3px 0;
    .ts-label { color: #64748b; }
    .ts-val { color: #334155; }
  }
}

/* ── Top 榜 ── */
.rank-list .rank-row {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 7px 0;
  border-bottom: 1px solid #f8fafc;
  &:last-child { border-bottom: none; }

  .rk {
    width: 19px; height: 19px; line-height: 19px;
    text-align: center; border-radius: 5px;
    font-size: 11px; font-weight: 600;
    background: #f1f5f9; color: #94a3b8; flex-shrink: 0;
    &.rk-1 { background: #fef3c7; color: #b45309; }
    &.rk-2 { background: #e2e8f0; color: #475569; }
    &.rk-3 { background: #ffedd5; color: #c2410c; }
  }
  .rk-name {
    flex: 1;
    font-size: 12px;
    color: #334155;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .rk-cnt { font-size: 11px; color: #b4bdc9; flex-shrink: 0; }
  .rk-amt { font-size: 12px; font-weight: 600; color: #0f172a; flex-shrink: 0; }
}

/* ── 口径说明 ── */
.metric-note {
  background: #fff;
  border: 1px solid $border;
  border-radius: $radius;
  padding: 14px 18px;

  .note-title {
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 8px;
    i { color: var(--admin-accent-text); }
  }
  ul { margin: 0; padding-left: 18px; }
  li {
    font-size: 12px;
    color: #7c8798;
    line-height: 1.9;
    b { color: #475569; }
  }
}
</style>
