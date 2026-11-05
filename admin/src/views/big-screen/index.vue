<template>
  <!-- 自适应铺满:按 1920×1080 设计稿等比缩放,多出来的那一边把设计尺寸放大填满,不留空白 -->
  <div class="cyber-screen-wrapper" :style="screenStyle">
    <!-- 背景层 -->
    <div class="cyber-bg-grid"></div>
    <div class="cyber-stars">
      <div class="star" v-for="i in 55" :key="'s'+i" :style="getStarStyle(i)"></div>
    </div>
    <div class="cyber-scan-line"></div>
    <div class="cyber-vignette"></div>

    <!-- 顶部标题栏 -->
    <div class="bs-header animate-drop">
      <dv-decoration-8 class="header-decor-left" :color="['#00f2fe', 'transparent']" />
      <div class="header-title">
        <h1 class="glitch-text" :data-text="mallTitle">{{ mallTitle }}</h1>
        <span v-if="demo" class="demo-badge">演示数据</span>
        <dv-decoration-5 class="header-title-deco" :color="['#00f2fe', '#8a2be2']" />
      </div>
      <dv-decoration-8 class="header-decor-right" :color="['#00f2fe', 'transparent']" :reverse="true" />
      <div class="header-time">
        <span class="time-date neon-cyan">{{ now.date }} {{ now.weekday }}</span>
        <span class="time-clock neon-pink-text">{{ now.time }}</span>
      </div>
      <div class="header-back" @click="goBack" title="返回工作台">
        <i class="el-icon-back"></i> 返回
      </div>
    </div>

    <!-- KPI 指标行 -->
    <div class="bs-kpi-row animate-fade-up">
      <dv-border-box-8 class="kpi-box cyber-box" :reverse="true" :color="['#00f2fe', '#8a2be2']">
        <div class="kpi-content">
          <div class="kpi-icon neon-cyan">👁</div>
          <div class="kpi-info">
            <dv-digital-flop :config="kpiConfigs.pv" style="width:140px;height:42px;" />
            <div class="kpi-label">今日 PV</div>
          </div>
        </div>
      </dv-border-box-8>
      <dv-border-box-8 class="kpi-box cyber-box" :color="['#00f2fe', '#00f2fe']">
        <div class="kpi-content">
          <div class="kpi-icon neon-cyan">👥</div>
          <div class="kpi-info">
            <dv-digital-flop :config="kpiConfigs.uv" style="width:140px;height:42px;" />
            <div class="kpi-label">今日 UV</div>
          </div>
        </div>
      </dv-border-box-8>
      <dv-border-box-8 class="kpi-box cyber-box box-highlight" :color="['#ff007a', '#8a2be2']">
        <div class="kpi-content">
          <div class="kpi-icon neon-pink-text">💰</div>
          <div class="kpi-info">
            <dv-digital-flop :config="kpiConfigs.sales" style="width:180px;height:42px;" />
            <div class="kpi-label neon-pink-text">今日销售额 (元)</div>
          </div>
        </div>
      </dv-border-box-8>
      <dv-border-box-8 class="kpi-box cyber-box" :color="['#00f2fe', '#00f2fe']">
        <div class="kpi-content">
          <div class="kpi-icon neon-cyan">📦</div>
          <div class="kpi-info">
            <dv-digital-flop :config="kpiConfigs.orders" style="width:140px;height:42px;" />
            <div class="kpi-label">今日支付订单 (单)</div>
          </div>
        </div>
      </dv-border-box-8>
      <dv-border-box-8 class="kpi-box cyber-box" :reverse="true" :color="['#00f2fe', '#8a2be2']">
        <div class="kpi-content">
          <div class="kpi-icon neon-cyan">💎</div>
          <div class="kpi-info">
            <dv-digital-flop :config="kpiConfigs.totalUsers" style="width:140px;height:42px;" />
            <div class="kpi-label">平台总用户 (人)</div>
          </div>
        </div>
      </dv-border-box-8>
      <dv-border-box-8 class="kpi-box cyber-box" :color="['#f8b500', '#ff007a']">
        <div class="kpi-content">
          <div class="kpi-icon neon-pink-text">✨</div>
          <div class="kpi-info">
            <dv-digital-flop :config="kpiConfigs.newUsers" style="width:140px;height:42px;" />
            <div class="kpi-label neon-pink-text">今日新增用户</div>
          </div>
        </div>
      </dv-border-box-8>
      <dv-border-box-8 class="kpi-box cyber-box" :reverse="true" :color="['#f8b500', '#00f2fe']">
        <div class="kpi-content">
          <div class="kpi-icon" style="color:#f8b500">💸</div>
          <div class="kpi-info">
            <dv-digital-flop :config="kpiConfigs.commission" style="width:140px;height:42px;" />
            <div class="kpi-label" style="color:#f8b500">今日产生佣金 (元)</div>
          </div>
        </div>
      </dv-border-box-8>
    </div>

    <!-- 主体三栏 -->
    <div class="bs-main">
      <!-- 左栏 -->
      <div class="bs-col bs-left">
        <dv-border-box-13 class="panel cyber-panel animate-fade-left">
          <div class="panel-title"><span class="title-icon">▊</span>用户来源分布</div>
          <div ref="pieChart" class="chart-box"></div>
        </dv-border-box-13>

        <dv-border-box-12 class="panel cyber-panel animate-fade-left" style="animation-delay: 0.1s;">
          <div class="panel-title"><span class="title-icon">▊</span>近15天访问趋势</div>
          <div ref="visitChart" class="chart-box"></div>
        </dv-border-box-12>

        <dv-border-box-13 class="panel cyber-panel animate-fade-left" style="animation-delay: 0.2s;" :color="['#00f2fe', '#000']">
          <div class="panel-title">
            <span class="title-icon">▊</span>运营待办状态
            <span class="panel-title-tag" v-if="pipelineData.to_ship > 0">PENDING</span>
          </div>
          <div class="pipeline-grid">
            <div class="pipe-item" v-for="p in pipelineItems" :key="p.key">
              <div class="pipe-icon" :style="{ color: p.color }">{{ p.icon }}</div>
              <div class="pipe-info">
                <div class="pipe-num" :style="{ color: p.color }">{{ p.value }}</div>
                <div class="pipe-label">{{ p.label }}</div>
              </div>
            </div>
          </div>
        </dv-border-box-13>
      </div>

      <!-- 中栏:3D 成交地图 + 交易趋势 -->
      <div class="bs-col bs-center">
        <dv-border-box-12 class="panel map-panel cyber-panel animate-fade-up" style="animation-delay: 0.3s;" :color="['#8a2be2', '#00f2fe']">
          <div class="panel-title">
            <span class="title-icon neon-pink-text">▊</span>成交地域分布
            <span class="panel-sub">近 {{ geoData.days }} 天 · 按收货省份</span>
          </div>
          <div class="map-wrap">
            <div ref="mapChart" class="map-box"></div>

            <!-- 左上:今日达成 -->
            <div class="map-overlay map-tl">
              <div class="ov-label">今日订单达成率</div>
              <div class="ov-big neon-cyan">{{ centerIndex }}<span class="ov-unit">%</span></div>
              <div class="ov-sub">今日 {{ cardData.today_order_count }} 单 / 昨日同时段 {{ cardData.yesterday_order_count }} 单</div>
              <div class="ov-track"><div class="ov-fill" :style="'width:' + Math.min(salesCompareRate, 100) + '%'"></div></div>
              <div class="ov-sub">销售额 ¥{{ cardData.today_sales }} · 昨日同时段 ¥{{ cardData.yesterday_sales }}</div>
            </div>

            <!-- 右上:省份排行 -->
            <div class="map-overlay map-tr" v-if="topProvinces.length">
              <div class="ov-label">省份排行</div>
              <div class="prov-row" v-for="(p, i) in topProvinces" :key="p.code">
                <span class="prov-rank" :class="'rank-' + (i + 1)">{{ i + 1 }}</span>
                <span class="prov-name">{{ p.name }}</span>
                <span class="prov-bar"><i :style="'width:' + p.pct + '%'"></i></span>
                <span class="prov-num neon-cyan">{{ p.count }}</span>
              </div>
            </div>

            <!-- 左下:最近成交 -->
            <div class="map-overlay map-bl" v-if="recentList.length">
              <div class="ov-label">最近成交 <span class="blink-dot"></span></div>
              <div class="recent-row" v-for="(r, i) in recentList" :key="i">
                <span class="recent-prov">{{ r.name }}</span>
                <span class="recent-amt neon-pink-text">¥{{ r.amount }}</span>
                <span class="recent-time">{{ r.ago }}</span>
              </div>
            </div>

            <!-- 右下:图例 / 说明 -->
            <div class="map-overlay map-br">
              <div class="legend-row"><i class="lg-bar"></i>光柱高度 = 近 {{ geoData.days }} 天订单数</div>
              <div class="legend-row"><i class="lg-line"></i>{{ linesFromToday ? '飞线 = 今日成交' : '飞线 = 近 ' + geoData.days + ' 天成交(今日暂无)' }}</div>
              <div class="legend-row" v-if="geoData.no_region > 0">另有 {{ geoData.no_region }} 单没有收货省份(自提 / 虚拟商品)</div>
            </div>

            <div class="map-empty" v-if="mapReady && !geoData.provinces.length">&gt; 近 {{ geoData.days }} 天暂无带收货地址的成交 &lt;</div>
            <div class="map-empty" v-if="mapError">&gt; {{ mapError }} &lt;</div>
          </div>
        </dv-border-box-12>

        <dv-border-box-13 class="panel trend-panel cyber-panel animate-fade-up" style="animation-delay: 0.4s;">
          <div class="panel-title">
            <span class="title-icon neon-pink-text">▊</span>近15天交易趋势
            <div class="center-tab-bar">
              <span class="center-tab-item" :class="{ active: chartMode === 'sales' }" @click="chartMode = 'sales'; renderSalesTrendChart()">💰 销售额</span>
              <span class="center-tab-item" :class="{ active: chartMode === 'orders' }" @click="chartMode = 'orders'; renderSalesTrendChart()">📦 订单量</span>
            </div>
          </div>
          <div ref="salesChart" class="chart-box"></div>
        </dv-border-box-13>
      </div>

      <!-- 右栏 -->
      <div class="bs-col bs-right">
        <dv-border-box-13 class="panel cyber-panel animate-fade-right">
          <div class="panel-title"><span class="title-icon">▊</span>热销商品 Top 5</div>
          <div class="goods-list">
            <div class="goods-item" v-for="(g, i) in topGoods" :key="i">
              <div class="goods-rank" :class="'rank-' + (i + 1)">NO.{{ i + 1 }}</div>
              <el-image :src="g.cover_pic" class="goods-img" fit="cover">
                <div slot="error" class="goods-img-err"><i class="el-icon-picture-outline"></i></div>
              </el-image>
              <div class="goods-info">
                <div class="goods-name">{{ g.name }}</div>
                <div class="goods-sales">销量 <span class="neon-cyan">{{ g.sales }}</span></div>
              </div>
            </div>
            <div v-if="topGoods.length === 0" class="empty-goods">
              <span style="color:rgba(0,242,254,0.3); font-size:12px; font-family:monospace;">&gt; 暂无商品数据 &lt;</span>
            </div>
          </div>
        </dv-border-box-13>

        <dv-border-box-12 class="panel cyber-panel animate-fade-right" style="animation-delay: 0.1s;">
          <div class="panel-title"><span class="title-icon">▊</span>订单量分布（近15天）</div>
          <div ref="orderDistChart" class="chart-box"></div>
        </dv-border-box-12>

        <dv-border-box-13 class="panel cyber-panel animate-fade-right" style="animation-delay: 0.2s;" :color="['#00f2fe', '#000']">
          <div class="panel-title">
            <span class="title-icon">▊</span>
            <div class="tab-bar">
              <span class="tab-item" :class="{ active: rightTab === 'pipeline' }" @click="rightTab = 'pipeline'">🚚 运营看板</span>
              <span class="tab-item" :class="{ active: rightTab === 'commission' }" @click="rightTab = 'commission'">💰 佣金概览</span>
            </div>
          </div>
          <div v-if="rightTab === 'pipeline'" class="right-tab-content">
            <div class="pipe-stat-row" v-for="p in pipelineItems" :key="p.key">
              <div class="pipe-stat-icon" :style="{ color: p.color }">{{ p.icon }}</div>
              <div class="pipe-stat-name">{{ p.label }}</div>
              <div class="pipe-stat-bar">
                <div class="pipe-stat-fill" :style="{ width: getPipePercent(p.value) + '%', background: p.color }"></div>
              </div>
              <div class="pipe-stat-num" :style="{ color: p.color }">{{ p.value }}</div>
            </div>
          </div>
          <div v-else class="right-tab-content">
            <div class="commission-big">
              <div class="com-item">
                <div class="com-label">今日产生佣金</div>
                <div class="com-value neon-pink-text">¥{{ commissionData.today }}</div>
              </div>
              <div class="com-divider"></div>
              <div class="com-item">
                <div class="com-label">累计产生佣金</div>
                <div class="com-value neon-cyan">¥{{ commissionData.total }}</div>
              </div>
            </div>
          </div>
        </dv-border-box-13>
      </div>
    </div>

    <!-- 底部跑马灯：热销商品 -->
    <div class="bs-footer-marquee animate-fade-up" v-if="topGoods.length > 0">
      <div class="marquee-label neon-pink-text">⭐ 热销商品 TOP {{ topGoods.length }}</div>
      <div class="marquee-track">
        <div class="marquee-content">
          <span class="marquee-item" v-for="(g, idx) in topGoodsLoop" :key="idx">
            <span class="rank-badge" :class="'rank-' + (g.__rank + 1)">NO.{{ g.__rank + 1 }}</span>
            <span class="rank-name">{{ g.name }}</span>
            <span class="rank-value neon-cyan">销量 {{ g.sales }}</span>
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import Vue from 'vue'
import dataV from '@jiaminghi/data-view'
Vue.use(dataV)

import * as echarts from 'echarts'
import 'echarts-gl'
import { buildDemoData } from './demo-data'

// 设计稿尺寸:所有面板按这个尺寸排版,再整体缩放到窗口
const DESIGN_W = 1920
const DESIGN_H = 1080
const MAP_NAME = 'jx-china'
const REGION_H = 3 // 省份板块挤出高度
// 地图边界(阿里 DataV 省级数据,已压缩精度),放在 public 下,只有大屏会加载
const GEO_URL = process.env.BASE_URL + 'static/geo/china.json'

/** 两个 #rrggbb 之间按 t(0-1)插值 */
function mixColor(a, b, t) {
  const pa = [1, 3, 5].map(i => parseInt(a.slice(i, i + 2), 16))
  const pb = [1, 3, 5].map(i => parseInt(b.slice(i, i + 2), 16))
  const k = Math.max(0, Math.min(1, t))
  return '#' + pa.map((v, i) => Math.round(v + (pb[i] - v) * k).toString(16).padStart(2, '0')).join('')
}

export default {
  name: 'MainBigScreen',
  data() {
    return {
      mallName: '',
      now: { time: '', date: '', weekday: '' },
      timer: null,
      refreshTimer: null,
      pulseTimer: null,
      apiReady: false,
      centerIndex: 0,

      chartMode: 'sales',
      demo: false,
      screen: { scale: 1, w: DESIGN_W, h: DESIGN_H },
      geoData: { days: 30, provinces: [], no_region: 0, recent: [], origin: null },
      provinceMeta: {}, // adcode → { name, center }
      mapReady: false,
      mapError: '',
      nowTs: Date.now(),
      rightTab: 'pipeline',

      cardData: {
        today_pv: 0, today_uv: 0,
        today_sales: 0, yesterday_sales: 0, sales_rate: 0,
        today_order_count: 0, yesterday_order_count: 0, order_rate: 0,
        total_users: 0, new_users: 0, pending_cash: 0
      },
      pipelineData: { to_ship: 0, refunding: 0, low_stock: 0 },
      commissionData: { total: 0, today: 0 },
      chartData: { dates: [], sales: [], orders: [], pv: [], uv: [] },
      pieData: [],
      topGoods: [],

      kpiConfigs: {
        pv:         { number: [0], content: '{nt}', textAlign: 'center', style: { fontSize: 36, fill: '#00f2fe', fontWeight: '900', fontFamily: 'Arial' } },
        uv:         { number: [0], content: '{nt}', textAlign: 'center', style: { fontSize: 36, fill: '#00f2fe', fontWeight: '900', fontFamily: 'Arial' } },
        sales:      { number: [0], content: '{nt}', textAlign: 'center', style: { fontSize: 36, fill: '#ff007a', fontWeight: '900', fontFamily: 'Arial' }, formatter: n => Number(n).toLocaleString() },
        orders:     { number: [0], content: '{nt}', textAlign: 'center', style: { fontSize: 36, fill: '#00f2fe', fontWeight: '900', fontFamily: 'Arial' } },
        totalUsers: { number: [0], content: '{nt}', textAlign: 'center', style: { fontSize: 36, fill: '#00f2fe', fontWeight: '900', fontFamily: 'Arial' }, formatter: n => Number(n).toLocaleString() },
        newUsers:   { number: [0], content: '{nt}', textAlign: 'center', style: { fontSize: 36, fill: '#ff007a', fontWeight: '900', fontFamily: 'Arial' } },
        commission: { number: [0], content: '{nt}', textAlign: 'center', style: { fontSize: 36, fill: '#f8b500', fontWeight: '900', fontFamily: 'Arial' }, formatter: n => Number(n).toFixed(2) },
      },

      charts: {}
    }
  },
  computed: {
    screenStyle() {
      const { w, h, scale } = this.screen
      return 'width:' + w + 'px;height:' + h + 'px;transform:scale(' + scale + ');'
    },
    topProvinces() {
      const list = this.geoData.provinces.slice(0, 6)
      const max = list.length ? list[0].count : 1
      return list.map(p => ({ ...p, name: this.provinceName(p.code), pct: Math.max(4, Math.round(p.count / max * 100)) }))
    },
    recentList() {
      return this.geoData.recent.slice(0, 5).map(r => ({
        name: r.code ? this.provinceName(r.code) : '无收货地址',
        amount: Number(r.amount).toFixed(2),
        ago: this.timeAgo(r.pay_at)
      }))
    },
    linesFromToday() {
      return this.geoData.provinces.some(p => p.today > 0)
    },
    mallTitle() {
      const name = (this.mallName || '').trim()
      if (!name) return '数 据 中 台'
      // 字符间加空格，与标题字距风格一致
      return name.split('').join(' ') + ' · 数 据 中 台'
    },
    topGoodsLoop() {
      const arr = this.topGoods.map((g, i) => ({ ...g, __rank: i }))
      return arr.length ? [...arr, ...arr] : []
    },
    salesCompareRate() {
      const y = parseFloat(this.cardData.yesterday_sales) || 0
      const t = parseFloat(this.cardData.today_sales) || 0
      if (y === 0) return t > 0 ? 100 : 0
      return Math.min(Math.round((t / y) * 100), 200)
    },
    pipelineItems() {
      return [
        { key: 'to_ship', label: '待发货', value: this.pipelineData.to_ship, icon: '🚚', color: '#00f2fe' },
        { key: 'refunding', label: '售后处理中', value: this.pipelineData.refunding, icon: '🔄', color: '#ff007a' },
        { key: 'low_stock', label: '库存预警', value: this.pipelineData.low_stock, icon: '⚠️', color: '#f8b500' },
        { key: 'pending_cash', label: '待提现审核', value: this.cardData.pending_cash, icon: '💳', color: '#8a2be2' },
      ]
    }
  },
  mounted() {
    // ?demo=1 用演示数据(本地没订单、给客户演示时用),页面标题旁会标「演示数据」
    this.demo = String(this.$route.query.demo || '') === '1'
    this.fitScreen()
    this.startClock()
    this.loadGeo()
    this.loadData()
    if (!this.demo) this.refreshTimer = setInterval(this.loadData, 30000)
    this.pulseTimer = setInterval(this.simulatePulse, 3000)
    window.addEventListener('resize', this.onResize)
  },
  beforeDestroy() {
    clearInterval(this.timer)
    clearInterval(this.refreshTimer)
    clearInterval(this.pulseTimer)
    window.removeEventListener('resize', this.onResize)
    Object.values(this.charts).forEach(c => c && c.dispose && c.dispose())
  },
  methods: {
    goBack() {
      if (window.history.length > 1) {
        window.close()
      } else {
        this.$router.push('/')
      }
    },
    /**
     * 等比缩放铺满窗口:取宽、高两个缩放比里小的那个,保证 1920×1080 的内容完整放得下;
     * 另一边按窗口比例把「设计尺寸」放大(比如窗口偏高,设计高度就从 1080 变成 1200),
     * 中间主体是 flex 撑开的,会把多出来的空间吃掉 —— 不再像原来那样只按宽度缩、底下空一截。
     */
    fitScreen() {
      const vw = window.innerWidth
      const vh = window.innerHeight
      const scale = Math.min(vw / DESIGN_W, vh / DESIGN_H)
      this.screen = { scale, w: Math.round(vw / scale), h: Math.round(vh / scale) }
    },
    onResize() {
      this.fitScreen()
      // DataV 边框组件自己也监听 window resize(带防抖),会在容器改尺寸后重新量
      this.$nextTick(this.resizeCharts)
    },
    provinceName(code) {
      const m = this.provinceMeta[code]
      return m ? m.short : String(code)
    },
    timeAgo(ts) {
      const diff = Math.max(0, Math.floor(this.nowTs / 1000) - Number(ts || 0))
      if (diff < 60) return '刚刚'
      if (diff < 3600) return Math.floor(diff / 60) + ' 分钟前'
      if (diff < 86400) return Math.floor(diff / 3600) + ' 小时前'
      return Math.floor(diff / 86400) + ' 天前'
    },
    loadGeo() {
      fetch(GEO_URL).then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status)
        return r.json()
      }).then(geo => {
        const meta = {}
        ;(geo.features || []).forEach(f => {
          const p = f.properties || {}
          if (typeof p.adcode !== 'number') return
          meta[p.adcode] = {
            name: p.name,
            short: String(p.name).replace(/(省|市|自治区|特别行政区|壮族|回族|维吾尔)/g, ''),
            center: p.centroid || p.center
          }
        })
        this.provinceMeta = meta
        echarts.registerMap(MAP_NAME, geo)
        this.mapReady = true
        this.$nextTick(this.renderMap)
      }).catch(e => {
        this.mapError = '地图数据加载失败(' + e.message + ')'
      })
    },
    startClock() {
      const update = () => {
        const d = new Date()
        const pad = n => String(n).padStart(2, '0')
        this.now.time = `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
        this.now.date = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
        const week = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT']
        this.now.weekday = week[d.getDay()]
        this.nowTs = d.getTime()
      }
      update()
      this.timer = setInterval(update, 1000)
    },
    getStarStyle(i) {
      const seed = i * 137.508
      const left = (seed * 7) % 100
      const top = (seed * 13) % 100
      const delay = (seed * 0.3) % 5
      const duration = 2 + (seed * 0.1) % 4
      const size = 1 + (seed * 0.05) % 2.5
      return { left: left + '%', top: top + '%', animationDelay: delay + 's', animationDuration: duration + 's', width: size + 'px', height: size + 'px' }
    },
    loadData() {
      if (this.demo) {
        this.applyData(buildDemoData())
        return
      }
      this.$request({ url: 'mall/dashboard/data', method: 'get' }).then(res => {
        if (res.code !== 0) return
        this.applyData(res.data || {})
      }).catch(() => {})
    },
    applyData(d) {
      if (d.mall_name) this.mallName = d.mall_name
      const card     = d.card     || {}
      const pipeline = d.pipeline || {}
      const comm     = d.commission || {}
      const chart    = d.chart || { dates: [], sales: [], orders: [], pv: [], uv: [] }

      this.cardData       = { ...this.cardData, ...card }
      this.pipelineData   = { ...this.pipelineData, ...pipeline }
      this.commissionData = { total: comm.total || 0, today: comm.today || 0 }
      this.chartData      = chart
      this.pieData        = Array.isArray(d.pie) ? d.pie : []
      this.topGoods       = Array.isArray(d.top_goods) ? d.top_goods : []
      const geo = d.geo || {}
      this.geoData = {
        days: geo.days || 30,
        provinces: Array.isArray(geo.provinces) ? geo.provinces : [],
        no_region: Number(geo.no_region || 0),
        recent: Array.isArray(geo.recent) ? geo.recent : [],
        origin: Array.isArray(geo.origin) ? geo.origin : null
      }

      // 今日订单达成率（今日 vs 昨日）
      const yo = Number(card.yesterday_order_count || 0)
      const to = Number(card.today_order_count || 0)
      this.centerIndex = yo > 0 ? Math.min(Math.round((to / yo) * 100), 999) : (to > 0 ? 100 : 0)

      // 更新 KPI flops
      this.kpiConfigs.pv         = { ...this.kpiConfigs.pv,         number: [Number(card.today_pv || 0)] }
      this.kpiConfigs.uv         = { ...this.kpiConfigs.uv,         number: [Number(card.today_uv || 0)] }
      this.kpiConfigs.sales      = { ...this.kpiConfigs.sales,      number: [Number(card.today_sales || 0)] }
      this.kpiConfigs.orders     = { ...this.kpiConfigs.orders,     number: [Number(card.today_order_count || 0)] }
      this.kpiConfigs.totalUsers = { ...this.kpiConfigs.totalUsers, number: [Number(card.total_users || 0)] }
      this.kpiConfigs.newUsers   = { ...this.kpiConfigs.newUsers,   number: [Number(card.new_users || 0)] }
      this.kpiConfigs.commission = { ...this.kpiConfigs.commission, number: [Number(comm.today || 0)] }

      const renderAll = () => {
        this.$nextTick(() => {
          this.renderPieChart()
          this.renderVisitChart()
          this.renderSalesTrendChart()
          this.renderOrderDistChart()
          this.renderMap()
          setTimeout(() => this.resizeCharts(), 100)
          setTimeout(() => this.resizeCharts(), 500)
          setTimeout(() => this.resizeCharts(), 1200)
        })
      }
      if (!this.apiReady) {
        this.apiReady = true
        setTimeout(renderAll, 1500)
      } else {
        renderAll()
      }
    },
    simulatePulse() {
      if (!this.apiReady) return
      const base = Number(this.centerIndex) || 0
      let v = base + (Math.random() * 2 - 1)
      if (v < 0) v = 0
      this.centerIndex = Math.round(v)
    },
    getPipePercent(val) {
      const max = Math.max(...this.pipelineItems.map(p => p.value), 1)
      return Math.round((val / max) * 100)
    },
    resizeCharts() {
      Object.values(this.charts).forEach(c => { if (c) c.resize() })
    },
    initChart(key, dom) {
      if (!dom) return null
      if (this.charts[key]) this.charts[key].dispose()
      const inst = echarts.init(dom)
      this.charts[key] = inst
      setTimeout(() => inst && inst.resize(), 0)
      return inst
    },
    showEmpty(chart, text = '暂无数据') {
      if (!chart) return
      chart.setOption({ title: { text: '> ' + text + ' <', left: 'center', top: 'middle', textStyle: { color: 'rgba(0, 242, 254, 0.4)', fontSize: 14, fontWeight: 'bold', fontFamily: 'monospace' } }, series: [] }, true)
    },
    hasValue(arr) {
      if (!Array.isArray(arr) || !arr.length) return false
      return arr.some(item => Number(typeof item === 'object' ? item.value : item) > 0)
    },

    renderPieChart() {
      const chart = this.initChart('pie', this.$refs.pieChart)
      if (!chart) return
      if (!this.hasValue(this.pieData)) { this.showEmpty(chart, '暂无用户来源数据'); return }
      const colors = ['#00f2fe', '#8a2be2', '#ff007a', '#f8b500', '#10b981', '#60a5fa', '#c084fc']
      const data = this.pieData.map((item, i) => ({ name: item.name, value: item.value, itemStyle: { color: colors[i % colors.length], shadowBlur: 15, shadowColor: colors[i % colors.length] } }))
      chart.setOption({
        tooltip: { trigger: 'item', backgroundColor: 'rgba(2,8,23,0.9)', borderColor: '#00f2fe', textStyle: { color: '#fff' }, formatter: '{b}: {c} ({d}%)' },
        legend: { bottom: 0, textStyle: { color: '#a0aabf' }, itemWidth: 10, itemHeight: 10, icon: 'circle' },
        series: [{
          type: 'pie', radius: ['50%', '72%'], center: ['50%', '43%'],
          itemStyle: { borderRadius: 4, borderColor: '#020817', borderWidth: 3 },
          label: { show: false },
          data
        }]
      }, true)
    },

    renderVisitChart() {
      const chart = this.initChart('visit', this.$refs.visitChart)
      if (!chart) return
      if (!this.hasValue(this.chartData.pv)) { this.showEmpty(chart, '暂无访问数据'); return }
      chart.setOption({
        tooltip: { trigger: 'axis', backgroundColor: 'rgba(2,8,23,0.9)', borderColor: '#8a2be2', textStyle: { color: '#fff' } },
        grid: { left: 40, right: 20, top: 30, bottom: 30 },
        xAxis: { type: 'category', boundaryGap: false, data: this.chartData.dates, axisLine: { lineStyle: { color: '#1a2e4a' } }, axisLabel: { color: '#a0aabf', interval: 4 } },
        yAxis: { type: 'value', axisLine: { show: false }, axisTick: { show: false }, axisLabel: { color: '#a0aabf' }, splitLine: { lineStyle: { color: 'rgba(0,242,254,0.1)', type: 'dashed' } } },
        legend: { data: ['PV', 'UV'], textStyle: { color: '#a0aabf' }, top: 0, right: 0, itemWidth: 10, itemHeight: 10 },
        series: [
          {
            name: 'PV', type: 'line', smooth: true, showSymbol: false,
            data: this.chartData.pv,
            lineStyle: { color: '#00f2fe', width: 3, shadowColor: '#00f2fe', shadowBlur: 10 },
            itemStyle: { color: '#00f2fe' },
            areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: 'rgba(0,242,254,0.3)' }, { offset: 1, color: 'rgba(0,242,254,0.01)' }]) }
          },
          {
            name: 'UV', type: 'line', smooth: true, showSymbol: false,
            data: this.chartData.uv,
            lineStyle: { color: '#8a2be2', width: 3, shadowColor: '#8a2be2', shadowBlur: 10 },
            itemStyle: { color: '#8a2be2' },
            areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: 'rgba(138,43,226,0.2)' }, { offset: 1, color: 'rgba(138,43,226,0.01)' }]) }
          }
        ]
      }, true)
    },

    renderSalesTrendChart() {
      const chart = this.initChart('sales', this.$refs.salesChart)
      if (!chart) return
      const isSales = this.chartMode === 'sales'
      const seriesData = isSales ? this.chartData.sales : this.chartData.orders
      if (!this.hasValue(seriesData)) { this.showEmpty(chart, isSales ? '暂无销售数据' : '暂无订单数据'); return }
      const color = isSales ? '#ff007a' : '#00f2fe'
      chart.setOption({
        tooltip: { trigger: 'axis', backgroundColor: 'rgba(2,8,23,0.9)', borderColor: color, textStyle: { color: '#fff' } },
        grid: { left: 55, right: 20, top: 40, bottom: 30 },
        xAxis: { type: 'category', boundaryGap: false, data: this.chartData.dates, axisLine: { lineStyle: { color: '#1a2e4a' } }, axisLabel: { color: '#a0aabf', interval: 2 } },
        yAxis: { type: 'value', axisLine: { show: false }, axisTick: { show: false }, axisLabel: { color: '#a0aabf' }, splitLine: { lineStyle: { color: 'rgba(0,242,254,0.1)', type: 'dashed' } } },
        series: [{
          name: isSales ? '销售额' : '订单量',
          type: 'line', smooth: true, symbol: 'circle', symbolSize: 8, showSymbol: false,
          data: seriesData,
          itemStyle: { color, borderColor: '#fff', borderWidth: 2 },
          lineStyle: { color, width: 4, shadowColor: color, shadowBlur: 15 },
          areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: color.replace(')', ', 0.5)').replace('rgb', 'rgba') }, { offset: 1, color: color.replace(')', ', 0.01)').replace('rgb', 'rgba') }]) }
        }]
      }, true)
    },

    /**
     * 3D 成交地图(echarts-gl):省份按订单量着色 + 光柱 + 从门店飞向各省的飞线。
     * 只初始化一次,之后 30 秒刷新走 merge,不重置用户拖过的视角。
     */
    renderMap() {
      if (!this.mapReady || !this.$refs.mapChart) return
      let chart = this.charts.map
      if (!chart) {
        chart = echarts.init(this.$refs.mapChart)
        this.charts.map = chart
      } else {
        chart.resize() // 初始化时容器可能还没撑开(高度 0),每次渲染前按当前尺寸校正
      }
      const meta = this.provinceMeta
      const provinces = this.geoData.provinces.filter(p => meta[p.code])
      const max = Math.max(1, ...provinces.map(p => p.count))
      const byCode = {}
      provinces.forEach(p => { byCode[p.code] = p })

      // 省份底色:没单的深蓝,单越多越亮(开方拉开小值之间的差别)
      const regions = Object.keys(meta).map(code => {
        const p = byCode[code]
        const t = p ? Math.sqrt(p.count / max) : 0
        return {
          name: meta[code].name,
          itemStyle: { color: p ? mixColor('#0d3a66', '#00b8e6', t) : '#0a2440', opacity: 1 }
        }
      })

      const bars = provinces.map((p, i) => ({
        name: meta[p.code].short,
        value: [meta[p.code].center[0], meta[p.code].center[1], 2 + 22 * Math.sqrt(p.count / max)],
        count: p.count,
        amount: p.amount,
        itemStyle: { color: i < 3 ? '#ff2d95' : mixColor('#00f2fe', '#8a2be2', i / Math.max(provinces.length - 1, 1)) },
        label: { show: i < 3 } // 华东几个省挨得近,全显示会叠成一团;其余悬停看
      }))

      // 起点:门店坐标;没配就用订单最多的省份
      const origin = this.geoData.origin || (provinces.length ? meta[provinces[0].code].center : null)
      const lineSrc = this.linesFromToday ? provinces.filter(p => p.today > 0) : provinces
      const lines = origin ? lineSrc
        .map(p => meta[p.code].center)
        .filter(c => Math.abs(c[0] - origin[0]) + Math.abs(c[1] - origin[1]) > 1.5)
        // 起止点要抬到省份板块顶面之上,不给高度会画在地面上,被抬高的板块整个盖住
        .map(c => ({ coords: [[origin[0], origin[1], REGION_H + 0.3], [c[0], c[1], REGION_H + 0.3]] })) : []

      const tip = { backgroundColor: 'rgba(2,8,23,0.92)', borderColor: '#00f2fe', textStyle: { color: '#fff' } }
      chart.setOption({
        tooltip: {
          ...tip,
          formatter: p => p.seriesType === 'bar3D'
            ? p.name + '<br/>订单 ' + p.data.count + ' 单<br/>金额 ¥' + Number(p.data.amount).toFixed(2)
            : (p.name || '')
        },
        geo3D: {
          map: MAP_NAME,
          regionHeight: REGION_H,
          boxWidth: 124,
          shading: 'lambert',
          light: {
            main: { intensity: 1.15, alpha: 55, beta: 20, shadow: false },
            ambient: { intensity: 0.55 }
          },
          viewControl: {
            alpha: 40, beta: 0, distance: 118, minDistance: 60, maxDistance: 170,
            center: [0, 0, 0], panSensitivity: 0, zoomSensitivity: 0.6, rotateSensitivity: 0.5,
            animationDurationUpdate: 1200
          },
          itemStyle: { color: '#0a2440', borderColor: 'rgba(80, 230, 255, 0.85)', borderWidth: 1 },
          emphasis: {
            label: { show: true, textStyle: { color: '#fff', fontSize: 14, backgroundColor: 'rgba(2,8,23,0.7)', padding: [3, 6] } },
            itemStyle: { color: '#1b78b4' }
          },
          label: { show: false },
          regions,
          postEffect: { enable: true, bloom: { enable: true, bloomIntensity: 0.14 } },
          temporalSuperSampling: { enable: true }
        },
        series: [
          {
            type: 'bar3D', coordinateSystem: 'geo3D', shading: 'lambert',
            barSize: 1.1, minHeight: 1, bevelSize: 0.4, silent: false,
            data: bars,
            label: {
              formatter: p => p.name + ' ' + p.data.count,
              distance: 1.5,
              textStyle: { color: '#fff', fontSize: 13, fontWeight: 'bold', backgroundColor: 'rgba(2,8,23,0.65)', padding: [2, 6], borderRadius: 3 }
            },
            emphasis: { label: { show: true } }
          },
          {
            type: 'lines3D', coordinateSystem: 'geo3D',
            blendMode: 'lighter', polyline: false,
            effect: { show: true, period: 2.4, trailWidth: 4, trailLength: 0.3, trailOpacity: 1, trailColor: '#ff5cb0' },
            lineStyle: { width: 1.5, color: '#ff4fa3', opacity: 0.35 },
            data: lines
          },
          {
            type: 'scatter3D', coordinateSystem: 'geo3D',
            symbol: 'circle', symbolSize: 13,
            itemStyle: { color: '#f8b500', borderColor: '#fff', borderWidth: 1 },
            label: { show: true, formatter: '门店', textStyle: { color: '#f8b500', fontSize: 13, fontWeight: 'bold' } },
            data: origin ? [{ name: '门店', value: [origin[0], origin[1], REGION_H + 0.5] }] : []
          }
        ]
      })
    },

    renderOrderDistChart() {
      const chart = this.initChart('orderDist', this.$refs.orderDistChart)
      if (!chart) return
      if (!this.hasValue(this.chartData.orders)) { this.showEmpty(chart, '暂无订单数据'); return }
      const dates = this.chartData.dates || []
      const orders = (this.chartData.orders || []).map(Number)
      chart.setOption({
        tooltip: { trigger: 'axis', backgroundColor: 'rgba(2,8,23,0.9)', borderColor: '#f8b500', textStyle: { color: '#fff' } },
        grid: { left: 40, right: 20, top: 20, bottom: 30 },
        xAxis: { type: 'category', data: dates, axisLine: { lineStyle: { color: '#1a2e4a' } }, axisLabel: { color: '#a0aabf', interval: 4 } },
        yAxis: { type: 'value', axisLine: { show: false }, axisTick: { show: false }, axisLabel: { color: '#a0aabf' }, splitLine: { lineStyle: { color: 'rgba(248,181,0,0.08)', type: 'dashed' } } },
        series: [{
          name: '订单量', type: 'line', smooth: true, showSymbol: false,
          data: orders,
          lineStyle: { color: '#f8b500', width: 3, shadowColor: '#f8b500', shadowBlur: 10 },
          itemStyle: { color: '#f8b500' },
          areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: 'rgba(248,181,0,0.4)' }, { offset: 1, color: 'rgba(248,181,0,0.01)' }]) }
        }]
      }, true)
    }
  }
}
</script>

<style lang="scss" scoped>
/* 全局沉浸式背景 */
.cyber-screen-wrapper {
  background: #020817;
  color: #fff;
  font-family: 'PingFang SC', 'Microsoft YaHei', sans-serif;
  position: fixed; top: 0; left: 0;
  transform-origin: 0 0;
  overflow: hidden;
  display: flex; flex-direction: column;
  background-image:
    radial-gradient(circle at 15% 15%, rgba(0, 242, 254, 0.08) 0%, transparent 40%),
    radial-gradient(circle at 85% 85%, rgba(138, 43, 226, 0.08) 0%, transparent 40%);
}

/* 背景网格 */
.cyber-bg-grid {
  position: absolute; inset: -50% -50% 0 -50%;
  background-image: linear-gradient(rgba(0, 242, 254, 0.08) 1px, transparent 1px), linear-gradient(90deg, rgba(0, 242, 254, 0.08) 1px, transparent 1px);
  background-size: 60px 60px;
  transform: perspective(600px) rotateX(60deg) translateY(-100px);
  animation: gridForward 10s linear infinite;
  z-index: 0; pointer-events: none;
  mask-image: linear-gradient(to top, rgba(0,0,0,1) 0%, rgba(0,0,0,0) 80%);
}
@keyframes gridForward { 0% { transform: perspective(600px) rotateX(60deg) translateY(0); } 100% { transform: perspective(600px) rotateX(60deg) translateY(60px); } }

/* 星空 */
.cyber-stars { position: absolute; inset: 0; pointer-events: none; z-index: 0; }
.star {
  position: absolute; background: #00f2fe; border-radius: 50%;
  box-shadow: 0 0 10px 2px rgba(0,242,254,0.8); animation: twinkle ease-in-out infinite; opacity: 0;
}
@keyframes twinkle { 0%, 100% { opacity: 0; transform: scale(0.3); } 50% { opacity: 1; transform: scale(1.2); } }

/* 扫描线 */
.cyber-scan-line {
  position: absolute; left: 0; right: 0; top: 0; height: 10px;
  background: rgba(0, 242, 254, 0.5); box-shadow: 0 0 30px 10px rgba(0, 242, 254, 0.2);
  pointer-events: none; z-index: 0; animation: scanRadar 8s linear infinite; opacity: 0.6;
}
@keyframes scanRadar { 0% { transform: translateY(-20px); } 100% { transform: translateY(1080px); } }

/* 暗角 */
.cyber-vignette { position: absolute; inset: 0; pointer-events: none; z-index: 1; box-shadow: inset 0 0 200px rgba(0,0,0,0.9); }

/* 闪烁小点(最近成交标题用) */
.blink-dot { display: inline-block; width: 6px; height: 6px; background: #00f2fe; border-radius: 50%; box-shadow: 0 0 5px #00f2fe; animation: blink 1s infinite; vertical-align: middle; margin-left: 4px; }
@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }

/* 布局层级 */
.bs-header, .bs-kpi-row, .bs-main { position: relative; z-index: 2; flex-shrink: 0; }

/* 动画 */
.animate-drop { animation: dropIn 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; opacity: 0; transform: translateY(-50px); }
.animate-fade-up { animation: fadeUp 0.8s ease-out forwards; opacity: 0; transform: translateY(30px); }
.animate-fade-left { animation: fadeLeft 0.8s ease-out forwards; opacity: 0; transform: translateX(-30px); }
.animate-fade-right { animation: fadeRight 0.8s ease-out forwards; opacity: 0; transform: translateX(30px); }
@keyframes dropIn { to { opacity: 1; transform: translateY(0); } }
@keyframes fadeUp { to { opacity: 1; transform: translateY(0); } }
@keyframes fadeLeft { to { opacity: 1; transform: translateX(0); } }
@keyframes fadeRight { to { opacity: 1; transform: translateX(0); } }

/* 颜色工具 */
.neon-cyan { color: #00f2fe; text-shadow: 0 0 10px rgba(0, 242, 254, 0.8); }
.neon-pink-text { color: #ff007a; text-shadow: 0 0 10px rgba(255, 0, 122, 0.8); }

/* 顶部 Header */
.bs-header { height: 80px; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; position: relative; }
.header-decor-left, .header-decor-right { width: 260px; height: 50px; flex-shrink: 0; }
.header-title { position: absolute; left: 50%; top: 40%; transform: translate(-50%, -50%); text-align: center; width: 700px; }
.header-title-deco { width: 80%; height: 20px; margin: 0 auto; margin-top: 5px; }
.glitch-text {
  font-size: 38px; color: #fff; margin: 0; letter-spacing: 10px; font-weight: 900;
  position: relative; text-shadow: 0 0 20px rgba(0, 242, 254, 0.8);
}
.glitch-text::before, .glitch-text::after {
  content: attr(data-text); position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: transparent;
}
.glitch-text::before { left: 2px; text-shadow: -2px 0 #ff007a; clip: rect(24px, 550px, 90px, 0); animation: glitchAnim 3s infinite linear alternate-reverse; }
.glitch-text::after  { left: -2px; text-shadow: -2px 0 #00f2fe; clip: rect(85px, 550px, 140px, 0); animation: glitchAnim 2.5s infinite linear alternate-reverse; }
@keyframes glitchAnim {
  0% { clip: rect(10px, 9999px, 30px, 0); } 20% { clip: rect(50px, 9999px, 60px, 0); }
  40% { clip: rect(20px, 9999px, 50px, 0); } 60% { clip: rect(80px, 9999px, 100px, 0); }
  80% { clip: rect(30px, 9999px, 40px, 0); } 100% { clip: rect(70px, 9999px, 90px, 0); }
}
.header-time { position: absolute; right: 100px; top: 50%; transform: translateY(-50%); display: flex; flex-direction: column; align-items: flex-end; font-family: 'Arial'; }
.time-date { font-size: 13px; letter-spacing: 1px; margin-bottom: 2px; }
.time-clock { font-size: 24px; font-weight: 900; }
.header-back {
  position: absolute; right: 20px; top: 50%; transform: translateY(-50%);
  font-size: 13px; color: rgba(0, 242, 254, 0.7); cursor: pointer; letter-spacing: 1px;
  padding: 6px 14px; border: 1px solid rgba(0, 242, 254, 0.3); border-radius: 4px;
  transition: all 0.3s;
  &:hover { color: #00f2fe; border-color: #00f2fe; box-shadow: 0 0 10px rgba(0, 242, 254, 0.3); }
  i { margin-right: 4px; }
}

/* KPI 行 */
.bs-kpi-row { display: flex; gap: 14px; padding: 0 20px; height: 100px; margin-top: 10px; }
.kpi-box { flex: 1; transition: transform 0.3s, filter 0.3s; }
.kpi-box:hover { transform: translateY(-5px) scale(1.02); filter: drop-shadow(0 0 15px rgba(0,242,254,0.4)); z-index: 10; }
.box-highlight { filter: drop-shadow(0 0 10px rgba(255,0,122,0.3)); }
.kpi-content { display: flex; align-items: center; justify-content: center; height: 100%; padding: 0 10px; }
.kpi-icon { font-size: 38px; margin-right: 14px; }
.kpi-info { display: flex; flex-direction: column; align-items: center; }
.kpi-label { font-size: 13px; color: #a0aabf; text-align: center; margin-top: 4px; font-weight: bold; letter-spacing: 0.5px; }

/* 主体布局:吃掉标题 / KPI / 底部跑马灯之外的全部高度(窗口偏高时也不会在底下空一截) */
.bs-main { display: flex; gap: 18px; padding: 18px 20px 10px; flex: 1 1 auto; min-height: 0; box-sizing: border-box; }
.bs-col { display: flex; flex-direction: column; gap: 18px; }
.bs-left, .bs-right { width: 25%; z-index: 5; }
.bs-center { flex: 1; display: flex; flex-direction: column; }

.cyber-panel {
  flex: 1; display: flex; flex-direction: column; padding: 18px;
  background: rgba(2, 8, 23, 0.4) !important;
  backdrop-filter: blur(8px);
  position: relative; min-height: 0; overflow: hidden;
}
.map-panel { flex: 1 1 auto; }
/* DataV 边框把插槽内容包在 .border-box-content 里(普通块级),要让地图区按剩余高度撑开,这层得是 flex 列 */
.map-panel ::v-deep .border-box-content { display: flex; flex-direction: column; min-height: 0; }
.trend-panel { flex: 0 0 250px; }
.panel-sub { font-size: 12px; color: #a0aabf; font-weight: normal; letter-spacing: 0.5px; text-shadow: none; margin-left: 4px; }

/* 3D 地图 */
.map-wrap { position: relative; flex: 1; min-height: 0; }
.map-box { position: absolute; inset: 0; }
.map-overlay {
  position: absolute; z-index: 2; pointer-events: none;
  background: rgba(2, 8, 23, 0.55); border: 1px solid rgba(0, 242, 254, 0.18); border-radius: 6px;
  padding: 10px 12px; backdrop-filter: blur(4px);
}
.map-tl { left: 6px; top: 4px; width: 196px; }
.map-tr { right: 6px; top: 4px; width: 190px; }
.map-bl { left: 6px; bottom: 6px; width: 196px; }
.map-br { right: 6px; bottom: 6px; max-width: 280px; }
.ov-label { font-size: 12px; color: #a0aabf; letter-spacing: 1px; margin-bottom: 6px; }
.ov-big { font-size: 34px; font-weight: 900; font-family: 'Arial'; line-height: 1; }
.ov-unit { font-size: 18px; margin-left: 3px; opacity: 0.8; }
.ov-sub { font-size: 11px; color: #a0aabf; margin-top: 6px; }
.ov-track { height: 4px; background: rgba(0, 242, 254, 0.1); border-radius: 2px; overflow: hidden; margin-top: 8px; }
.ov-fill { height: 100%; background: linear-gradient(90deg, #00f2fe, #ff007a); border-radius: 2px; transition: width 0.6s ease; }
.prov-row { display: flex; align-items: center; gap: 8px; font-size: 12px; margin-top: 5px; }
.prov-rank { width: 18px; height: 18px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 900; border-radius: 3px; background: rgba(0, 242, 254, 0.15); color: #9feeff;
  &.rank-1 { background: rgba(255, 0, 122, 0.35); color: #ffb3d9; }
  &.rank-2 { background: rgba(248, 181, 0, 0.3); color: #ffe08a; }
  &.rank-3 { background: rgba(138, 43, 226, 0.35); color: #d3a8ff; }
}
.prov-name { width: 48px; flex-shrink: 0; color: #fff; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.prov-bar { flex: 1; height: 4px; background: rgba(255, 255, 255, 0.06); border-radius: 2px; overflow: hidden;
  i { display: block; height: 100%; background: linear-gradient(90deg, #00f2fe, #8a2be2); border-radius: 2px; }
}
.prov-num { width: 40px; text-align: right; font-weight: 900; font-family: 'Arial'; font-variant-numeric: tabular-nums; }
.recent-row { display: flex; align-items: center; gap: 8px; font-size: 12px; margin-top: 5px; }
.recent-prov { width: 52px; flex-shrink: 0; color: #fff; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.recent-amt { flex: 1; font-weight: 900; font-family: 'Arial'; font-variant-numeric: tabular-nums; }
.recent-time { color: #a0aabf; font-size: 11px; flex-shrink: 0; }
.legend-row { font-size: 11px; color: #a0aabf; display: flex; align-items: center; gap: 6px; line-height: 1.8; }
.lg-bar { width: 6px; height: 12px; background: linear-gradient(#ff2d95, #8a2be2); border-radius: 1px; }
.lg-line { width: 18px; height: 2px; background: linear-gradient(90deg, transparent, #ff4fa3); }
.map-empty { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); z-index: 2; color: rgba(0, 242, 254, 0.5); font-size: 14px; font-weight: bold; font-family: monospace; pointer-events: none; }

/* 演示数据标记 */
.demo-badge { position: absolute; right: -96px; top: 6px; font-size: 12px; color: #f8b500; border: 1px solid rgba(248, 181, 0, 0.6); background: rgba(248, 181, 0, 0.1); padding: 2px 8px; border-radius: 3px; letter-spacing: 1px; }

.panel-title {
  font-size: 17px; color: #fff; font-weight: bold; margin-bottom: 12px;
  display: flex; align-items: center; gap: 8px; letter-spacing: 1px;
  text-shadow: 0 0 8px rgba(255,255,255,0.4);
}
.title-icon { color: #00f2fe; text-shadow: 0 0 10px #00f2fe; font-size: 15px; }
.panel-title-tag { margin-left: auto; font-size: 10px; color: #ff007a; font-weight: 900; letter-spacing: 1.5px; padding: 2px 8px; border: 1px solid rgba(255, 0, 122, 0.5); border-radius: 3px; background: rgba(255, 0, 122, 0.08); animation: tagBlink 2s ease-in-out infinite; }
@keyframes tagBlink { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }

.chart-box { flex: 1; min-height: 160px; width: 100%; }

/* 运营待办状态（左下角） */
.pipeline-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; flex: 1; align-content: center; }
.pipe-item { display: flex; align-items: center; gap: 8px; padding: 8px 10px; background: rgba(0,242,254,0.04); border: 1px solid rgba(0,242,254,0.12); border-radius: 6px; }
.pipe-icon { font-size: 22px; }
.pipe-num { font-size: 22px; font-weight: 900; font-family: 'Arial'; line-height: 1.1; }
.pipe-label { font-size: 11px; color: #a0aabf; margin-top: 2px; }

/* 中栏 tab */
.center-tab-bar { display: flex; gap: 12px; margin-left: auto; }
.center-tab-item { font-size: 13px; color: #a0aabf; cursor: pointer; padding: 2px 8px; border-radius: 3px; transition: all 0.3s; letter-spacing: 0.5px;
  &.active { color: #00f2fe; background: rgba(0,242,254,0.1); border: 1px solid rgba(0,242,254,0.3); text-shadow: 0 0 8px rgba(0,242,254,0.8); }
  &:hover { color: #fff; }
}

/* 右栏热销商品 */
.goods-list { flex: 1; display: flex; flex-direction: column; gap: 8px; overflow: hidden; }
.goods-item { display: flex; align-items: center; gap: 10px; padding: 8px 10px; background: rgba(0,242,254,0.04); border: 1px solid rgba(0,242,254,0.1); border-radius: 6px; transition: all 0.3s;
  &:hover { border-color: rgba(0,242,254,0.3); background: rgba(0,242,254,0.08); }
}
.goods-rank { flex-shrink: 0; min-width: 44px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 900; color: #fff; background: rgba(0,242,254,0.2); border: 1px solid rgba(0,242,254,0.5); border-radius: 3px; letter-spacing: 0.5px;
  &.rank-1 { background: rgba(255,0,122,0.3); border-color: #ff007a; color: #ffb3d9; text-shadow: 0 0 6px #ff007a; }
  &.rank-2 { background: rgba(248,181,0,0.3); border-color: #f8b500; color: #ffe08a; }
  &.rank-3 { background: rgba(138,43,226,0.3); border-color: #8a2be2; color: #d3a8ff; }
}
.goods-img { width: 38px; height: 38px; border-radius: 4px; border: 1px solid rgba(0,242,254,0.2); flex-shrink: 0; background: rgba(2,8,23,0.5); }
.goods-img-err { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: rgba(0,242,254,0.3); font-size: 16px; }
.goods-info { flex: 1; min-width: 0; }
.goods-name { font-size: 12px; color: #fff; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.goods-sales { font-size: 11px; color: #a0aabf; margin-top: 2px; }
.empty-goods { flex: 1; display: flex; align-items: center; justify-content: center; }

/* 右栏运营状态条 */
.right-tab-content { flex: 1; display: flex; flex-direction: column; gap: 10px; justify-content: center; }
.pipe-stat-row { display: flex; align-items: center; gap: 8px; padding: 4px 0; }
.pipe-stat-icon { font-size: 16px; flex-shrink: 0; width: 22px; }
.pipe-stat-name { font-size: 12px; color: #a0aabf; width: 75px; flex-shrink: 0; }
.pipe-stat-bar { flex: 1; height: 6px; background: rgba(255,255,255,0.06); border-radius: 3px; overflow: hidden; }
.pipe-stat-fill { height: 100%; border-radius: 3px; transition: width 0.6s ease; opacity: 0.85; }
.pipe-stat-num { font-size: 16px; font-weight: 900; font-family: 'Arial'; width: 36px; text-align: right; }

/* 佣金面板 */
.commission-big { display: flex; flex: 1; align-items: center; justify-content: center; gap: 0; }
.com-item { flex: 1; text-align: center; }
.com-divider { width: 1px; height: 60px; background: rgba(0,242,254,0.2); }
.com-label { font-size: 13px; color: #a0aabf; margin-bottom: 8px; letter-spacing: 1px; }
.com-value { font-size: 28px; font-weight: 900; font-family: 'Arial'; text-shadow: 0 0 15px rgba(0,242,254,0.6); }

/* Tab 切换（右栏） */
.tab-bar { display: flex; gap: 14px; }
.tab-item { font-size: 13px; color: #a0aabf; cursor: pointer; padding: 2px 4px; transition: all 0.3s; letter-spacing: 0.5px;
  &.active { color: #00f2fe; text-shadow: 0 0 8px rgba(0,242,254,0.8); border-bottom: 1px solid #00f2fe; }
  &:hover { color: #fff; }
}

/* 底部跑马灯 */
.bs-footer-marquee { position: relative; z-index: 3; flex-shrink: 0; display: flex; align-items: center; height: 48px; margin: 0 20px 10px; padding: 0 16px; background: linear-gradient(90deg, rgba(255,0,122,0.15) 0%, rgba(0,242,254,0.08) 50%, rgba(138,43,226,0.15) 100%); border-top: 1px solid rgba(0,242,254,0.3); border-bottom: 1px solid rgba(0,242,254,0.3); overflow: hidden; }
.marquee-label { flex-shrink: 0; font-size: 14px; font-weight: 900; letter-spacing: 2px; padding-right: 20px; border-right: 1px solid rgba(255,0,122,0.4); margin-right: 20px; }
.marquee-track { flex: 1; overflow: hidden; height: 100%; display: flex; align-items: center; }
.marquee-content { display: flex; align-items: center; gap: 40px; white-space: nowrap; animation: marqueeScroll 40s linear infinite; }
@keyframes marqueeScroll { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }
.marquee-item { display: inline-flex; align-items: center; gap: 10px; font-size: 14px; color: #fff; }
.rank-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 44px; height: 22px; font-size: 11px; font-weight: 900; color: #fff; background: rgba(0,242,254,0.2); border: 1px solid rgba(0,242,254,0.6); border-radius: 3px; letter-spacing: 0.5px;
  &.rank-1 { background: rgba(255,0,122,0.3); border-color: #ff007a; color: #ffb3d9; text-shadow: 0 0 6px #ff007a; }
  &.rank-2 { background: rgba(248,181,0,0.3); border-color: #f8b500; color: #ffe08a; }
  &.rank-3 { background: rgba(138,43,226,0.3); border-color: #8a2be2; color: #d3a8ff; }
}
.rank-name { color: #fff; font-weight: bold; }
.rank-value { font-size: 14px; font-weight: 900; font-family: 'Arial'; }
</style>
