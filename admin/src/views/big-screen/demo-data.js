/**
 * 数据大屏演示数据:地址带 ?demo=1 时用(本地库没订单、给客户演示时)。
 * 页面标题旁会标「演示数据」,别拿去当真实经营数字。结构和 mall/dashboard/data 返回的一致。
 */

// 近 30 天各省成交:[adcode, 订单数, 今日单数]
const PROVINCES = [
  [440000, 486, 21], [110000, 212, 9], [310000, 188, 8], [330000, 165, 7], [320000, 142, 6],
  [510000, 96, 4], [430000, 88, 3], [350000, 77, 3], [420000, 64, 2], [370000, 58, 2],
  [450000, 45, 2], [410000, 40, 1], [500000, 30, 1], [610000, 28, 1], [530000, 22, 0],
  [120000, 18, 1], [210000, 15, 0], [460000, 12, 1], [230000, 8, 0], [650000, 5, 0]
]

// 每单均价 260 元上下,按省份做一点浮动,保证同一份数据每次打开一样
function amountOf(code, count) {
  return Math.round(count * (240 + (code % 7) * 9) * 100) / 100
}

// 15 天趋势:平稳上行 + 周末小高峰
function trend(base, step, wave) {
  return Array.from({ length: 15 }, (_, i) => Math.round(base + step * i + wave * Math.sin(i * 0.9)))
}

export function buildDemoData() {
  const now = Math.floor(Date.now() / 1000)
  const dates = Array.from({ length: 15 }, (_, i) => {
    const d = new Date(Date.now() - (14 - i) * 86400000)
    return String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0')
  })
  const orders = trend(96, 2.4, 14)
  orders[14] = 132

  return {
    mall_name: '演示商城',
    card: {
      today_sales: 34320.5, yesterday_sales: 29860, sales_rate: 14.94,
      today_pv: 8420, today_uv: 2136,
      today_order_count: 132, yesterday_order_count: 118, order_rate: 11.86,
      total_users: 52318, new_users: 86, pending_cash: 3
    },
    pipeline: { to_ship: 23, refunding: 4, low_stock: 7, refund_attention: 0 },
    commission: { total: 186420.35, today: 1284.6 },
    chart: {
      dates,
      sales: orders.map((n, i) => Math.round(n * (248 + 6 * Math.cos(i)))),
      orders,
      pv: trend(6200, 140, 600),
      uv: trend(1600, 34, 160)
    },
    pie: [
      { name: '微信小程序', value: 3120 },
      { name: 'H5/手机', value: 860 },
      { name: '微信公众号', value: 420 }
    ],
    top_goods: [
      { name: '有机鲜果礼盒 5 斤装', sales: 1286, cover_pic: '' },
      { name: '全棉亲肤四件套', sales: 932, cover_pic: '' },
      { name: '便携无线榨汁杯', sales: 761, cover_pic: '' },
      { name: '儿童保温水杯 500ml', sales: 588, cover_pic: '' },
      { name: '护眼阅读台灯', sales: 437, cover_pic: '' }
    ],
    geo: {
      days: 30,
      provinces: PROVINCES.map(([code, count, today]) => ({ code, count, amount: amountOf(code, count), today })),
      no_region: 37,
      recent: [
        [440000, 399, 40], [310000, 128.5, 170], [110000, 699, 320], [330000, 89.9, 610],
        [510000, 259, 900], [440000, 1299, 1500], [320000, 56, 2100], [430000, 318, 3000]
      ].map(([code, amount, ago]) => ({ code, amount, pay_at: now - ago })),
      origin: [113.26, 23.13] // 广州
    }
  }
}
