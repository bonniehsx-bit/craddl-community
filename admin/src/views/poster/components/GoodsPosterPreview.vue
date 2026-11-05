<template>
  <!-- 外框按缩放后的尺寸占位,里面始终是 750×1334 的画布 -->
  <div class="gpp-frame" :style="{ width: 750 * scale + 'px', height: 1334 * scale + 'px' }">
    <!--
      结构、坐标、字号与 api/services/GoodsPosterRenderer.php 一一对应,改一边记得改另一边。
      头像、昵称、小程序码是示意;商品取商城里最近上架的一件,没有就画占位。
    -->
    <div class="cv" :style="{ transform: 'scale(' + scale + ')' }">

      <!-- ═════ ① 经典白卡 ═════ -->
      <template v-if="template === 'classic'">
        <div class="abs fill" style="background:#f4f5f7;" />
        <div v-if="mallName || mall.logo" class="abs row" style="left:60px; top:50px; right:60px;">
          <div class="logo" style="box-shadow:0 0 0 1px #e6e8ec;">
            <img v-if="mall.logo" :src="mall.logo">
            <span v-else :style="{ color: themeRgb }">{{ mallName.slice(0, 1) }}</span>
          </div>
          <div class="ell" style="margin-left:14px; font-size:26px; font-weight:700; color:#1f2229;">{{ mallName }}</div>
        </div>
        <div class="abs" style="left:40px; top:130px; width:670px; height:1164px; border-radius:28px; background:#fff;" />
        <div class="abs pic" :style="picStyle(70, 160, 610, 610, 20)">{{ goods.cover_pic ? '' : '商品主图' }}</div>
        <div class="abs clamp2" style="left:80px; top:802px; width:590px; font-size:34px; line-height:49px; font-weight:700; color:#1f2229;">{{ goods.name }}</div>
        <div class="abs price-row" :style="{ left: '80px', top: (990 - 76 * 0.82) + 'px' }">
          <template v-if="goods.is_negotiable"><b :style="{ fontSize: '46px', color: themeRgb }">价格面议</b></template>
          <template v-else>
            <b :style="{ fontSize: '38px', color: themeRgb }">¥</b>
            <b :style="{ fontSize: '76px', color: themeRgb, marginLeft: '4px' }">{{ priceInt }}</b>
            <b :style="{ fontSize: '46px', color: themeRgb, marginLeft: '2px' }">{{ priceDec }}</b>
            <s v-if="goods.origin_price" style="font-size:26px; color:#a0a5b0; margin-left:16px;">¥{{ goods.origin_price }}</s>
          </template>
        </div>
        <div v-if="goods.sales > 0" class="abs" style="right:80px; top:938px; height:36px; line-height:36px; padding:0 16px; border-radius:18px; background:#f4f5f7; font-size:22px; color:#8a8f9b;">已售 {{ salesText }}</div>
        <div class="abs" style="left:80px; top:1029px; width:590px; border-top:2px dashed #e6e8ec;" />
        <i class="abs ava" style="left:80px; top:1080px; width:80px; height:80px;" />
        <div class="abs ell" style="left:178px; width:280px; top:1082px; font-size:28px; font-weight:700; color:#1f2229;">用户昵称</div>
        <div class="abs ell" style="left:178px; width:280px; top:1126px; font-size:24px; color:#8a8f9b;">{{ recommendText }}</div>
        <div class="abs code" style="left:480px; top:1060px; width:190px; height:190px;" :style="{ backgroundImage: sampleCode }" />
        <div class="abs center" style="left:480px; width:190px; top:1256px; font-size:20px; color:#a0a5b0;">长按识别购买</div>
      </template>

      <!-- ═════ ② 主题大促 ═════ -->
      <template v-else-if="template === 'promo'">
        <div class="abs fill" style="background:#f6f6f8;" />
        <div class="abs" style="left:0; top:0; width:750px; height:640px; overflow:hidden;"
             :style="{ background: `linear-gradient(180deg, ${rgb(mix(theme, WHITE, 0.15))} 0%, ${rgb(mix(theme, BLACK, 0.1))} 100%)` }">
          <div class="abs dot" style="left:410px; top:-200px; width:520px; height:520px; background:rgba(255,255,255,.08);" />
          <div class="abs dot" style="left:-90px; top:300px; width:260px; height:260px; background:rgba(255,255,255,.07);" />
        </div>
        <div v-if="mallName || mall.logo" class="abs row" style="left:50px; top:56px; right:50px;">
          <div class="logo">
            <img v-if="mall.logo" :src="mall.logo">
            <span v-else :style="{ color: themeRgb }">{{ mallName.slice(0, 1) }}</span>
          </div>
          <div class="ell" style="margin-left:14px; font-size:26px; font-weight:700; color:#fff;">{{ mallName }}</div>
        </div>
        <div class="abs ell" style="left:50px; right:50px; top:136px; font-size:64px; line-height:80px; font-weight:800; color:#fff; letter-spacing:4px;">{{ mainTitleText }}</div>
        <div v-if="sub" class="abs ell" style="left:52px; right:50px; top:232px; font-size:28px; color:rgba(255,255,255,.88);">{{ sub }}</div>
        <div class="abs" style="left:40px; top:326px; width:670px; height:974px; border-radius:28px; background:rgba(0,0,0,.04);" />
        <div class="abs" style="left:40px; top:320px; width:670px; height:974px; border-radius:28px; background:#fff;" />
        <div class="abs pic" :style="picStyle(64, 344, 622, 560, 18)">{{ goods.cover_pic ? '' : '商品主图' }}</div>
        <div class="abs row" :style="{ left: '64px', top: '840px', height: '64px', padding: '0 22px', borderRadius: '0 32px 32px 0', background: themeRgb, color: '#fff', fontWeight: 800, alignItems: 'baseline', lineHeight: '64px' }">
          <template v-if="goods.is_negotiable"><span style="font-size:36px;">价格面议</span></template>
          <template v-else><span style="font-size:24px; margin-right:4px;">¥</span><span style="font-size:44px;">{{ goods.price }}</span></template>
        </div>
        <div class="abs clamp2" style="left:76px; top:932px; width:598px; font-size:32px; line-height:46px; font-weight:700; color:#1f2229;">{{ goods.name }}</div>
        <div v-if="goods.origin_price && !goods.is_negotiable" class="abs" style="left:76px; top:1040px; font-size:24px; color:#a0a5b0;">原价 <s>¥{{ goods.origin_price }}</s></div>
        <div class="abs" :style="{ left: '64px', top: '1096px', width: '622px', height: '174px', borderRadius: '18px', background: rgb(mix(theme, WHITE, 0.93)) }" />
        <div class="abs ava-ring" style="left:84px; top:1130px; width:80px; height:80px; background:#fff; padding:4px;"><i class="ava" /></div>
        <div class="abs ell" style="left:176px; width:270px; top:1134px; font-size:26px; font-weight:700; color:#1f2229;">用户昵称</div>
        <div class="abs ell" :style="{ left: '176px', width: '270px', top: '1173px', fontSize: '22px', color: mutedRgb }">{{ recommendText }}</div>
        <div class="abs" style="left:468px; top:1064px; width:200px; height:200px; border-radius:18px; background:rgba(0,0,0,.05);" />
        <div class="abs box" style="left:468px; top:1056px; width:200px; height:200px; border-radius:18px; background:#fff;">
          <div class="code" style="width:176px; height:176px;" :style="{ backgroundImage: sampleCode }" />
        </div>
        <div class="abs center" :style="{ left: '468px', width: '200px', top: '1268px', fontSize: '20px', color: mutedRgb }">长按识别购买</div>
      </template>

      <!-- ═════ ③ 大图杂志 ═════ -->
      <template v-else-if="template === 'magazine'">
        <div class="abs fill" style="background:#fff;" />
        <div class="abs pic" :style="picStyle(0, 0, 750, 900, 0)">{{ goods.cover_pic ? '' : '商品主图' }}</div>
        <div class="abs" style="left:0; top:0; width:750px; height:220px; background:linear-gradient(180deg, rgba(0,0,0,.4), rgba(0,0,0,0));" />
        <div v-if="mallName || mall.logo" class="abs row" style="left:50px; top:52px; right:50px;">
          <div class="logo">
            <img v-if="mall.logo" :src="mall.logo">
            <span v-else :style="{ color: themeRgb }">{{ mallName.slice(0, 1) }}</span>
          </div>
          <div class="ell" style="margin-left:14px; font-size:26px; font-weight:700; color:#fff; letter-spacing:2px;">{{ mallName }}</div>
        </div>
        <div class="abs" style="left:0; top:820px; width:750px; height:514px; border-radius:40px 40px 0 0; background:#fff;" />
        <div class="abs ell" :style="{ left: '56px', width: '420px', top: '882px', fontSize: '22px', letterSpacing: '6px', fontWeight: 700, color: themeRgb }">{{ mainTitleText }}</div>
        <div class="abs clamp2" style="left:56px; top:926px; width:420px; font-size:36px; line-height:52px; font-weight:700; color:#1f2229;">{{ goods.name }}</div>
        <div class="abs price-row" :style="{ left: '56px', top: (1118 - 68 * 0.82) + 'px' }">
          <template v-if="goods.is_negotiable"><b :style="{ fontSize: '41px', color: themeRgb }">价格面议</b></template>
          <template v-else>
            <b :style="{ fontSize: '34px', color: themeRgb }">¥</b>
            <b :style="{ fontSize: '68px', color: themeRgb, marginLeft: '4px' }">{{ priceInt }}</b>
            <b :style="{ fontSize: '41px', color: themeRgb, marginLeft: '2px' }">{{ priceDec }}</b>
            <s v-if="goods.origin_price" style="font-size:24px; color:#a0a5b0; margin-left:16px;">¥{{ goods.origin_price }}</s>
          </template>
        </div>
        <div class="abs code" style="left:500px; top:880px; width:200px; height:200px;" :style="{ backgroundImage: sampleCode }" />
        <div class="abs center" style="left:500px; width:200px; top:1090px; font-size:20px; color:#a0a5b0; letter-spacing:1px;">长按识别购买</div>
        <div class="abs" style="left:56px; top:1160px; width:638px; border-top:1px solid #ecedf0;" />
        <i class="abs ava" style="left:56px; top:1202px; width:64px; height:64px;" />
        <div class="abs row" style="left:136px; right:56px; top:1216px;">
          <span style="font-size:26px; font-weight:700; color:#1f2229; white-space:nowrap;">用户昵称</span>
          <span class="ell" style="font-size:24px; color:#8a8f9b; margin-left:12px;">{{ recommendText }}</span>
        </div>
      </template>

      <!-- ═════ ④ 黑金臻选 ═════ -->
      <template v-else-if="template === 'blackgold'">
        <div class="abs fill" style="background:#121215;" />
        <div class="abs dot" style="left:105px; top:260px; width:540px; height:540px; background:radial-gradient(circle, rgba(214,178,110,.2) 0%, rgba(214,178,110,0) 70%);" />
        <div class="abs center ell" style="left:75px; right:75px; top:64px; font-size:24px; color:#d6b26e; letter-spacing:10px;">{{ mallName }}</div>
        <div class="abs center" style="left:0; right:0; top:110px; font-size:20px; color:rgba(214,178,110,.6); letter-spacing:12px;">SELECTED</div>
        <div class="abs" style="left:75px; top:170px; width:600px; height:600px; border:1.5px solid rgba(214,178,110,.7);" />
        <div class="abs pic" :style="picStyle(95, 190, 560, 560, 0)">{{ goods.cover_pic ? '' : '商品主图' }}</div>
        <div class="abs clamp2 center" style="left:90px; top:810px; width:570px; font-size:34px; line-height:49px; font-weight:700; color:#fff; letter-spacing:1px;">{{ goods.name }}</div>
        <div class="abs" style="left:276px; top:925px; width:80px; height:1.5px; background:rgba(214,178,110,.6);" />
        <div class="abs" style="left:370px; top:921px; width:10px; height:10px; background:#d6b26e; transform:rotate(45deg);" />
        <div class="abs" style="left:394px; top:925px; width:80px; height:1.5px; background:rgba(214,178,110,.6);" />
        <div class="abs price-row" :style="{ left: 0, right: 0, justifyContent: 'center', top: (1020 - 72 * 0.82) + 'px' }">
          <template v-if="goods.is_negotiable"><b style="font-size:43px; color:#e9c98a;">价格面议</b></template>
          <template v-else>
            <b style="font-size:36px; color:#e9c98a;">¥</b>
            <b style="font-size:72px; color:#e9c98a; margin-left:4px;">{{ priceInt }}</b>
            <b style="font-size:43px; color:#e9c98a; margin-left:2px;">{{ priceDec }}</b>
            <s v-if="goods.origin_price" style="font-size:24px; color:rgba(255,255,255,.4); margin-left:16px;">¥{{ goods.origin_price }}</s>
          </template>
        </div>
        <div class="abs" style="left:60px; top:1080px; width:630px; height:1px; background:rgba(214,178,110,.25);" />
        <div class="abs ava-ring" style="left:56px; top:1136px; width:80px; height:80px; background:#d6b26e; padding:2px;">
          <div class="ava-ring" style="width:100%; height:100%; background:#121215; padding:2px;"><i class="ava" /></div>
        </div>
        <div class="abs ell" style="left:148px; width:320px; top:1140px; font-size:26px; font-weight:700; color:#fff;">用户昵称</div>
        <div class="abs ell" style="left:148px; width:360px; top:1178px; font-size:22px; color:rgba(214,178,110,.8);">长按识别右侧小程序码购买</div>
        <div class="abs box" style="left:540px; top:1110px; width:150px; height:150px; border-radius:12px; background:#fff; box-shadow:0 0 0 1.5px #d6b26e;">
          <div class="code" style="width:130px; height:130px;" :style="{ backgroundImage: sampleCode }" />
        </div>
      </template>
    </div>
  </div>
</template>

<script>
const DEFAULT_THEME = '#FF4B3E'

/** 示意用的小程序码(同 InvitePosterPreview),真实海报贴微信返回的码 */
const SAMPLE_CODE = (() => {
  const N = 256
  const R = N / 2
  const eyes = [[-1, -1], [1, -1], [-1, 1]].map(([sx, sy]) => [R + sx * 78, R + sy * 78])
  let seed = 7
  const rnd = () => (seed = (seed * 9301 + 49297) % 233280) / 233280
  const dots = []
  for (let ring = 0; ring < 9; ring++) {
    const r = 34 + ring * 10.5
    const n = Math.floor(2 * Math.PI * r / 10)
    for (let k = 0; k < n; k++) {
      if (rnd() < 0.45) continue
      const a = k / n * Math.PI * 2
      const x = R + r * Math.cos(a)
      const y = R + r * Math.sin(a)
      if (eyes.some(([ex, ey]) => Math.hypot(x - ex, y - ey) < 30)) continue
      dots.push(`<circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="3.4"/>`)
    }
  }
  const eyeSvg = eyes.map(([x, y]) =>
    `<circle cx="${x}" cy="${y}" r="24" fill="none" stroke="#1f2229" stroke-width="8"/><circle cx="${x}" cy="${y}" r="10"/>`
  ).join('')
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${N} ${N}"><g fill="#1f2229">${dots.join('')}${eyeSvg}</g>` +
    `<circle cx="${R}" cy="${R}" r="26" fill="#e9ebef"/></svg>`
  return `url("data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}")`
})()

const SAMPLE_GOODS = {
  name: '商品名称示例，最多显示两行，超出部分用省略号',
  price: '99.00',
  origin_price: '168.00',
  is_negotiable: 0,
  sales: 2300,
  cover_pic: ''
}

function parseHex(hex) {
  if (typeof hex !== 'string') return null
  let h = hex.trim().replace(/^#/, '')
  if (/^[0-9a-f]{3}$/i.test(h)) h = h.split('').map(c => c + c).join('')
  if (!/^[0-9a-f]{6}$/i.test(h)) return null
  return [0, 2, 4].map(i => parseInt(h.substr(i, 2), 16))
}

export default {
  name: 'GoodsPosterPreview',
  props: {
    template: { type: String, default: 'classic' },
    themeColor: { type: String, default: DEFAULT_THEME },
    mainTitle: { type: String, default: '' },
    subTitle: { type: String, default: '' },
    recommend: { type: String, default: '' },
    mall: { type: Object, default: () => ({ name: '', logo: '' }) },
    // 后台接口给的一件真实商品;没有就用示例
    sampleGoods: { type: Object, default: null },
    scale: { type: Number, default: 0.5 }
  },
  data() {
    return { WHITE: [255, 255, 255], BLACK: [0, 0, 0], sampleCode: SAMPLE_CODE }
  },
  computed: {
    theme() {
      return parseHex(this.themeColor) || parseHex(DEFAULT_THEME)
    },
    themeRgb() {
      return this.rgb(this.theme)
    },
    mutedRgb() {
      return this.rgb(this.mix(this.theme, [110, 110, 110], 0.55))
    },
    goods() {
      return this.sampleGoods || SAMPLE_GOODS
    },
    priceInt() {
      return String(this.goods.price || '0.00').split('.')[0]
    },
    priceDec() {
      const dec = String(this.goods.price || '0.00').split('.')[1]
      return '.' + (dec || '00')
    },
    salesText() {
      const n = Number(this.goods.sales) || 0
      return n >= 10000 ? String(Math.round(n / 1000) / 10).replace(/\.0$/, '') + '万' : String(n)
    },
    mallName() {
      return (this.mall && this.mall.name) || ''
    },
    mainTitleText() {
      return (this.mainTitle || '').trim() || '好物推荐'
    },
    sub() {
      return (this.subTitle || '').trim()
    },
    recommendText() {
      return (this.recommend || '').trim() || '为你推荐一件好物'
    }
  },
  methods: {
    mix(a, b, t) {
      return a.map((v, i) => Math.round(v + (b[i] - v) * t))
    },
    rgb(c) {
      return `rgb(${c.join(',')})`
    },
    picStyle(left, top, width, height, radius) {
      const style = { left: left + 'px', top: top + 'px', width: width + 'px', height: height + 'px', borderRadius: radius + 'px' }
      if (this.goods.cover_pic) {
        style.backgroundImage = `url(${this.goods.cover_pic})`
      }
      return style
    }
  }
}
</script>

<style lang="scss" scoped>
.gpp-frame {
  position: relative;
  overflow: hidden;
  flex-shrink: 0;
}

.cv {
  position: relative;
  width: 750px;
  height: 1334px;
  overflow: hidden;
  transform-origin: 0 0;
  font-family: "PingFang SC", "Microsoft YaHei", sans-serif;
  line-height: 1.4;
}

.abs { position: absolute; }
.fill { left: 0; top: 0; width: 750px; height: 1334px; }
.dot { border-radius: 50%; }
.row { display: flex; align-items: center; }
.center { text-align: center; }
.ell { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.box { display: flex; align-items: center; justify-content: center; }
.clamp2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

.pic {
  background-color: #eceef2;
  background-size: cover;
  background-position: center;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 32px;
  color: #b4b8c2;
}

.logo {
  flex-shrink: 0; width: 48px; height: 48px; border-radius: 50%; background: #fff;
  display: flex; align-items: center; justify-content: center; overflow: hidden;
  font-size: 24px; font-weight: 700;
  img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
}

.price-row {
  display: flex;
  align-items: baseline;
  white-space: nowrap;
  line-height: 1;
  b { font-weight: 700; }
}

.ava-ring { border-radius: 50%; box-sizing: border-box; }
.ava {
  display: block; width: 100%; height: 100%; border-radius: 50%;
  background: linear-gradient(145deg, #ffe0c2, #f4a988);
}

.code {
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
}
</style>
