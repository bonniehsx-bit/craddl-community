<template>
  <div class="app-container ov-page" v-loading="loading">
    <!-- 页头 -->
    <div class="ov-head">
      <div>
        <div class="ov-title">分销数据概览</div>
        <div class="ov-desc">只统计分销佣金,不含其它插件的分润</div>
      </div>
      <div class="ov-head-right">
        <span v-if="updatedAt" class="ov-time">更新于 {{ updatedAt }}</span>
        <el-button size="small" icon="el-icon-refresh" @click="fetchData">刷新</el-button>
      </div>
    </div>

    <!-- 核心指标 -->
    <div class="kpi-grid">
      <div class="kpi kpi-hero">
        <div class="kpi-label">累计分销佣金(元)</div>
        <div class="kpi-value"><span class="cur">¥</span>{{ money(stats.total_commission) }}</div>
        <div class="kpi-sub">
          <span>待结算 ¥{{ money(stats.pending_commission) }}</span>
          <i class="dot" />
          <span>已结算 ¥{{ money(stats.settled_commission) }}</span>
        </div>
      </div>
      <div class="kpi">
        <div class="kpi-top">
          <span class="kpi-label">本月新增佣金(元)</span>
          <span class="kpi-ico ico-blue"><i class="el-icon-date" /></span>
        </div>
        <div class="kpi-value"><span class="cur">¥</span>{{ money(stats.month_commission) }}</div>
        <div class="kpi-sub">今日新增 ¥{{ money(stats.today_commission) }}</div>
      </div>
      <div class="kpi">
        <div class="kpi-top">
          <span class="kpi-label">分销员(人)</span>
          <span class="kpi-ico ico-purple"><i class="el-icon-user" /></span>
        </div>
        <div class="kpi-value">{{ int(stats.total_distributors) }}</div>
        <div class="kpi-sub">
          <span>已获佣 {{ int(stats.earned_users) }} 人</span>
          <i class="dot" />
          <span>获佣占比 {{ activeRate }}</span>
        </div>
      </div>
      <div class="kpi">
        <div class="kpi-top">
          <span class="kpi-label">产生佣金的订单(单)</span>
          <span class="kpi-ico ico-cyan"><i class="el-icon-s-order" /></span>
        </div>
        <div class="kpi-value">{{ int(stats.total_orders) }}</div>
        <div class="kpi-sub">
          <span>退款作废 <b class="warn">¥{{ money(stats.invalid_commission) }}</b></span>
        </div>
      </div>
    </div>

    <!-- 趋势 + 层级占比 -->
    <div class="ov-row">
      <div class="ov-card grow">
        <div class="card-head">
          <span class="card-title">近 30 日佣金趋势</span>
          <span class="card-hint">按计提日期,不含已失效</span>
        </div>
        <trend-chart :chart-data="trendChartData" name="分销佣金" color="#1890ff" empty-text="近 30 天暂无新增佣金" />
      </div>
      <div class="ov-card side">
        <div class="card-head">
          <span class="card-title">各层级分佣占比</span>
        </div>
        <level-chart :chart-data="levelChartData" center-label="累计分佣" empty-text="暂无分佣数据" />
      </div>
    </div>

    <!-- 排行 + 层级明细 -->
    <div class="ov-row">
      <div class="ov-card grow">
        <div class="card-head">
          <span class="card-title">分销员佣金排行</span>
          <span class="card-hint">TOP 10 · 累计佣金,不含已失效</span>
        </div>
        <div v-if="rankingList.length" class="rank-list">
          <div v-for="(r, i) in rankingList" :key="i" class="rank-row">
            <span class="rank-no" :class="'no-' + (i + 1)">{{ i + 1 }}</span>
            <el-avatar :size="28" :src="r.avatar_url" icon="el-icon-user-solid" />
            <span class="rank-name">{{ r.nickname || '未知用户' }}</span>
            <span class="rank-bar"><i :style="'width:' + barPct(r.amount) + '%'" /></span>
            <span class="rank-amt">¥{{ money(r.amount) }}</span>
          </div>
        </div>
        <el-empty v-else description="暂无分销佣金记录" :image-size="72" />
      </div>
      <div class="ov-card side">
        <div class="card-head">
          <span class="card-title">各层级明细</span>
        </div>
        <div v-if="levelChartData.length" class="lv-list">
          <div v-for="(lv, i) in levelChartData" :key="i" class="lv-row">
            <span class="lv-dot" :style="'background:' + lvColor(i)" />
            <span class="lv-name">{{ lv.name }}</span>
            <span class="lv-count">{{ int(lv.count) }} 笔</span>
            <span class="lv-amt">¥{{ money(lv.value) }}</span>
            <span class="lv-pct">{{ lvPct(lv.value) }}</span>
          </div>
        </div>
        <el-empty v-else description="暂无分层数据" :image-size="72" />
      </div>
    </div>

    <div class="ov-foot">
      <i class="el-icon-info" />
      统计口径:「累计分销佣金」= 待结算 + 已结算,不含已失效(退款追回的不算发出去过),已失效单独列在「退款作废」。
      「分销员」按有推广资格的用户统计,与【分销人员】页同口径;「已获佣」是真正分到过钱的去重人数,两者的差反映分销活跃度。
    </div>
  </div>
</template>

<script>
import TrendChart from './components/TrendChart'
import LevelChart from './components/LevelChart'

// 与 LevelChart 里的配色顺序一致,明细列表的色点才对得上环形图
const LV_COLORS = ['#1890ff', '#13c2c2', '#722ed1', '#fa8c16', '#eb2f96', '#52c41a', '#faad14']

export default {
  name: 'distribution-dashboard',
  components: { TrendChart, LevelChart },
  data() {
    return {
      loading: false,
      updatedAt: '',
      stats: {},
      trendChartData: { xAxisData: [], seriesData: [] },
      levelChartData: [],
      rankingList: []
    }
  },
  computed: {
    activeRate() {
      const t = Number(this.stats.total_distributors) || 0
      const e = Number(this.stats.earned_users) || 0
      return t ? (Math.round(e / t * 1000) / 10) + '%' : '—'
    },
    levelTotal() {
      return this.levelChartData.reduce((s, l) => s + (Number(l.value) || 0), 0)
    },
    rankMax() {
      return Math.max(1, ...this.rankingList.map(r => toNum(r.amount)))
    }
  },
  created() {
    this.fetchData()
  },
  methods: {
    fetchData() {
      this.loading = true
      this.$request({ url: 'plugin/distribution/mall/index/index', method: 'get' })
        .then(res => {
          if (res && res.code === 0) {
            const d = res.data
            this.stats = d
            this.trendChartData = d.trend || { xAxisData: [], seriesData: [] }
            this.levelChartData = d.level_distribution || []
            this.rankingList = d.ranking || []
            const now = new Date()
            this.updatedAt = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0')
          } else if (res) {
            this.$message.error(res.msg || '获取数据失败')
          }
        })
        .finally(() => { this.loading = false })
    },
    money(v) {
      return toNum(v).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
    int(v) {
      return toNum(v).toLocaleString('zh-CN')
    },
    barPct(v) {
      return Math.max(3, Math.round(toNum(v) / this.rankMax * 100))
    },
    lvColor(i) {
      return LV_COLORS[i % LV_COLORS.length]
    },
    lvPct(v) {
      return this.levelTotal ? (Math.round(toNum(v) / this.levelTotal * 1000) / 10) + '%' : '—'
    }
  }
}

// 接口里金额有的带千分位逗号(number_format),统一去掉再转数字
function toNum(v) {
  return Number(String(v == null ? 0 : v).replace(/,/g, '')) || 0
}
</script>

<style scoped lang="scss">
/* 概览页版式与 dealer/index.vue 同一套,改一边记得同步另一边 */
$text: #1f2329;
$text-2: #4e5969;
$text-3: #86909c;
$line: #eef0f3;
$primary: #1890ff;

.ov-page { background: #f5f6f8; min-height: 100%; }

.ov-head { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 16px; }
.ov-title { font-size: 20px; font-weight: 700; color: $text; }
.ov-desc { margin-top: 4px; font-size: 13px; color: $text-3; }
.ov-head-right { display: flex; align-items: center; gap: 12px; }
.ov-time { font-size: 12px; color: $text-3; }

/* 指标卡:第一张主色铺满,其余白卡 */
.kpi-grid { display: grid; grid-template-columns: 1.3fr 1fr 1fr 1fr; gap: 16px; }
.kpi { background: #fff; border: 1px solid $line; border-radius: 10px; padding: 18px 20px; min-width: 0; }
.kpi-top { display: flex; align-items: center; justify-content: space-between; }
.kpi-label { font-size: 13px; color: $text-3; }
.kpi-ico { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.ico-blue { background: #e8f3ff; color: #1890ff; }
.ico-purple { background: #f5edff; color: #722ed1; }
.ico-cyan { background: #e6fffb; color: #13c2c2; }
.ico-orange { background: #fff4e6; color: #fa8c16; }
.kpi-value { margin-top: 10px; font-size: 28px; font-weight: 700; color: $text; font-variant-numeric: tabular-nums; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cur { font-size: 16px; margin-right: 2px; font-weight: 600; }
.kpi-sub { margin-top: 10px; font-size: 12px; color: $text-3; display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
.kpi-sub b.warn { color: #f53f3f; font-weight: 600; }
.dot { width: 3px; height: 3px; border-radius: 50%; background: #c9cdd4; }
.kpi-hero {
  border: none; color: #fff;
  background: linear-gradient(135deg, #1890ff 0%, #3f8cff 55%, #6aa8ff 100%);
  box-shadow: 0 8px 24px rgba(24, 144, 255, 0.25);
  .kpi-label, .kpi-sub { color: rgba(255, 255, 255, 0.85); }
  .kpi-value { color: #fff; font-size: 32px; margin-top: 12px; }
  .dot { background: rgba(255, 255, 255, 0.6); }
}

/* 内容行 */
.ov-row { display: flex; gap: 16px; margin-top: 16px; }
.ov-card { background: #fff; border: 1px solid $line; border-radius: 10px; padding: 16px 20px; min-width: 0; }
.ov-card.grow { flex: 2; }
.ov-card.side { flex: 1; }
.card-head { display: flex; align-items: baseline; justify-content: space-between; margin-bottom: 8px; }
.card-title { font-size: 15px; font-weight: 600; color: $text; }
.card-hint { font-size: 12px; color: $text-3; }

/* 排行 */
.rank-list { padding-top: 4px; }
.rank-row { display: flex; align-items: center; gap: 12px; padding: 9px 0; border-bottom: 1px solid #f5f6f8; }
.rank-row:last-child { border-bottom: none; }
.rank-no { width: 22px; height: 22px; flex-shrink: 0; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: $text-3; background: #f2f3f5;
  &.no-1 { color: #fff; background: #ff9a2e; }
  &.no-2 { color: #fff; background: #a9aeb8; }
  &.no-3 { color: #fff; background: #d4915b; }
}
.rank-name { width: 160px; flex-shrink: 0; font-size: 13px; color: $text; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.rank-bar { flex: 1; height: 6px; border-radius: 3px; background: #f2f3f5; overflow: hidden;
  i { display: block; height: 100%; border-radius: 3px; background: linear-gradient(90deg, #6aa8ff, $primary); }
}
.rank-amt { width: 110px; flex-shrink: 0; text-align: right; font-size: 13px; font-weight: 600; color: $text; font-variant-numeric: tabular-nums; }

/* 层级明细 */
.lv-list { padding-top: 4px; }
.lv-row { display: flex; align-items: center; gap: 10px; padding: 11px 0; border-bottom: 1px solid #f5f6f8; font-size: 13px; }
.lv-row:last-child { border-bottom: none; }
.lv-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.lv-name { flex: 1; color: $text; }
.lv-count { width: 64px; text-align: right; color: $text-3; font-variant-numeric: tabular-nums; }
.lv-amt { width: 110px; text-align: right; color: $text; font-weight: 600; font-variant-numeric: tabular-nums; }
.lv-pct { width: 52px; text-align: right; color: $text-3; font-variant-numeric: tabular-nums; }

.ov-foot { margin-top: 16px; padding: 12px 16px; border-radius: 8px; background: #fff; border: 1px dashed $line; font-size: 12px; line-height: 1.9; color: $text-3; }

@media (max-width: 1280px) {
  .kpi-grid { grid-template-columns: repeat(2, 1fr); }
  .ov-row { flex-direction: column; }
}
</style>
