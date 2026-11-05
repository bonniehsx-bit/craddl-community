<template>
  <!-- 外框按缩放后的尺寸占位,里面始终是 750×1334 的画布 -->
  <div class="ipp-frame" :style="{ width: 750 * scale + 'px', height: 1334 * scale + 'px' }">
    <!--
      结构、坐标、字号与 api/services/InvitePosterRenderer.php 一一对应,改一边记得改另一边。
      头像、昵称、小程序码是示意,生成时换成邀请人自己的。
    -->
    <div class="cv" :class="'tpl-' + template" :style="{ transform: 'scale(' + scale + ')' }">

      <!-- ═════ ① 邀请票券 ═════ -->
      <template v-if="template === 'ticket'">
        <div class="abs fill" :style="{ background: hasBg ? rgb(ticketDark) : `linear-gradient(180deg, ${rgb(theme)} 0%, ${rgb(ticketDark)} 100%)` }" />
        <template v-if="hasBg">
          <div class="abs photo" :style="{ height: '760px', backgroundImage: bgUrl }" />
          <div class="abs" :style="{ left: 0, top: 0, width: '750px', height: '760px', background: `linear-gradient(180deg, rgba(0,0,0,.55) 0%, rgba(0,0,0,.25) 45%, ${rgb(ticketDark, 0.85)} 88%, ${rgb(ticketDark)} 100%)` }" />
        </template>
        <template v-else>
          <div class="abs dot" style="left:410px; top:-200px; width:560px; height:560px; background:rgba(255,255,255,.08)" />
          <div class="abs dot" style="left:-120px; top:330px; width:300px; height:300px; background:rgba(255,255,255,.06)" />
          <div class="abs dot" style="left:540px; top:420px; width:120px; height:120px; background:rgba(255,255,255,.10)" />
        </template>
        <div v-if="mallName || mall.logo" class="abs row" style="left:56px; top:64px; right:56px;">
          <div class="round-logo">
            <img v-if="mall.logo" :src="mall.logo">
            <span v-else :style="{ color: rgb(theme) }">{{ mallName.slice(0, 1) }}</span>
          </div>
          <div class="ell" style="margin-left:16px; font-size:28px; font-weight:600; color:#fff; letter-spacing:1px;">{{ mallName }}</div>
        </div>
        <div class="abs pill">专属邀请</div>
        <div class="abs ell" style="left:52px; right:52px; top:280px; font-size:104px; font-weight:800; color:#fff; letter-spacing:6px; line-height:1.1;">{{ title }}</div>
        <div v-if="sub" class="abs ell" style="left:56px; right:56px; top:412px; font-size:32px; color:rgba(255,255,255,.88); letter-spacing:1px;">{{ sub }}</div>
        <div class="abs" style="left:40px; top:600px; width:670px; height:674px; background:#fff; border-radius:32px;">
          <div class="abs dot" :style="{ left: '-22px', top: '224px', width: '44px', height: '44px', background: ticketNotch }" />
          <div class="abs dot" :style="{ right: '-22px', top: '224px', width: '44px', height: '44px', background: ticketNotch }" />
          <div class="abs" style="left:44px; right:44px; top:245px; border-top:3px dashed #e6e8ec;" />
          <div class="abs ava-ring" style="left:265px; top:-70px; width:140px; height:140px; background:#fff; padding:7px;"><i class="ava" /></div>
          <div class="abs center ell" style="left:40px; right:40px; top:96px; font-size:36px; font-weight:700; color:#1f2229;">用户昵称</div>
          <div class="abs center ell" style="left:40px; right:40px; top:152px; font-size:26px; color:#8a8f9b;">
            <template v-if="mallName">邀请你加入 <b :style="{ color: rgb(theme) }">{{ mallName }}</b></template>
            <template v-else>邀请你一起加入</template>
          </div>
          <div class="abs box" :style="{ left: '185px', top: '292px', width: '300px', height: '300px', borderRadius: '24px', background: rgb(mix(theme, WHITE, 0.92)) }">
            <div class="code" style="width:256px; height:256px;" :style="{ backgroundImage: sampleCode }" />
          </div>
          <div class="abs center tip-lines" style="left:0; right:0; top:614px; font-size:24px; color:#a0a5b0; letter-spacing:2px;">长按识别小程序码</div>
        </div>
      </template>

      <!-- ═════ ② 简约白 ═════ -->
      <template v-else-if="template === 'simple'">
        <div class="abs fill" style="background:#f4f5f7;" />
        <div class="abs" style="left:40px; top:40px; width:670px; height:820px; border-radius:28px; overflow:hidden;"
             :style="{ background: `linear-gradient(180deg, ${rgb(mix(theme, WHITE, 0.12))} 0%, ${rgb(mix(theme, BLACK, 0.1))} 100%)` }">
          <template v-if="hasBg">
            <div class="abs photo" :style="{ width: '670px', height: '820px', backgroundImage: bgUrl }" />
            <div class="abs" style="left:0; top:0; width:670px; height:820px; background:linear-gradient(180deg, rgba(0,0,0,.55) 0%, rgba(0,0,0,.08) 45%, rgba(0,0,0,0) 100%);" />
          </template>
          <template v-else>
            <div class="abs dot" style="left:320px; top:-150px; width:520px; height:520px; background:rgba(255,255,255,.07)" />
            <div class="abs dot" style="left:400px; top:-70px; width:360px; height:360px; background:rgba(255,255,255,.07)" />
            <svg class="abs" style="left:185px; top:360px;" width="300" height="300" viewBox="0 0 300 300">
              <rect x="40" y="120" width="220" height="160" rx="18" fill="rgba(255,255,255,.95)" />
              <rect x="24" y="84" width="252" height="56" rx="14" fill="#fff" />
              <rect x="132" y="84" width="36" height="196" :fill="rgb(soft)" />
              <path d="M150 84 C 110 20, 50 40, 80 84 Z" fill="#fff" />
              <path d="M150 84 C 190 20, 250 40, 220 84 Z" fill="#fff" />
              <circle cx="150" cy="84" r="16" :fill="rgb(soft)" />
            </svg>
          </template>
        </div>
        <div v-if="mallName || mall.logo" class="abs row" style="left:88px; top:88px; right:88px;">
          <div class="square-logo">
            <img v-if="mall.logo" :src="mall.logo">
            <span v-else :style="{ color: rgb(theme) }">{{ mallName.slice(0, 1) }}</span>
          </div>
          <div class="ell" style="margin-left:14px; font-size:26px; font-weight:600; color:#fff;">{{ mallName }}</div>
        </div>
        <div class="abs ell" style="left:88px; right:88px; top:190px; font-size:88px; font-weight:800; color:#fff; letter-spacing:4px; line-height:1.1;">{{ title }}</div>
        <div v-if="sub" class="abs ell" style="left:90px; right:90px; top:300px; font-size:30px; color:rgba(255,255,255,.9);">{{ sub }}</div>
        <div class="abs" style="left:40px; top:900px; width:670px; height:394px; background:#fff; border-radius:28px;">
          <i class="abs ava" style="left:44px; top:48px; width:96px; height:96px;" />
          <div class="abs ell" style="left:160px; width:230px; top:52px; font-size:34px; font-weight:700; color:#1f2229;">用户昵称</div>
          <div class="abs" style="left:160px; top:102px; font-size:24px; color:#8a8f9b;">邀请你加入</div>
          <div class="abs" style="left:44px; top:180px; width:340px; border-top:2px solid #f0f1f4;" />
          <div v-for="(step, i) in ['长按识别右侧小程序码', '打开即可进入商城']" :key="i" class="abs row" :style="{ left: '44px', top: (217 + i * 48) + 'px' }">
            <span class="step-no" :style="{ background: rgb(soft), color: rgb(theme) }">{{ i + 1 }}</span>
            <span style="font-size:24px; color:#5a5f6b; margin-left:12px;">{{ step }}</span>
          </div>
          <div class="abs code" style="left:410px; top:56px; width:220px; height:220px;" :style="{ backgroundImage: sampleCode }" />
          <div class="abs center" style="left:410px; width:220px; top:294px; font-size:22px; color:#a0a5b0;">长按识别小程序码</div>
        </div>
      </template>

      <!-- ═════ ③ 喜庆红包 ═════ -->
      <template v-else-if="template === 'redpacket'">
        <div class="abs fill" style="background:#c8221c;" />
        <div class="abs dot" style="left:-75px; top:-240px; width:900px; height:900px; background:radial-gradient(circle, rgba(255,120,80,.5) 0%, rgba(255,120,80,0) 65%);" />
        <div class="abs dot" style="left:-150px; top:-520px; width:1050px; height:1050px; background:#e3372b;" />
        <div class="abs dot" style="left:-150px; top:-540px; width:1050px; height:1050px; border:4px solid #f29068;" />
        <div class="abs center ell" style="left:40px; right:40px; top:84px; font-size:26px; color:#ffe3b3; letter-spacing:6px;">{{ mallName ? mallName + ' · 诚意相邀' : '诚意相邀' }}</div>
        <div class="abs center ell" style="left:50px; right:50px; top:150px; font-size:110px; font-weight:800; letter-spacing:10px; color:#ffe0a3; line-height:1.1;">{{ title }}</div>
        <div v-if="sub" class="abs center ell" style="left:55px; right:55px; top:300px; font-size:30px; color:#ffd7c7; letter-spacing:2px;">{{ sub }}</div>
        <div class="abs dot box" style="left:280px; top:420px; width:190px; height:190px; background:linear-gradient(180deg, #ffe7b0, #f6c36b); box-shadow:0 0 0 10px rgba(255,217,154,.28); font-size:96px; font-weight:800; color:#c8221c;">邀</div>
        <div class="abs" style="left:70px; top:690px; width:610px; height:580px; border-radius:24px; background:#fff8ec;">
          <div class="abs ava-ring" style="left:258px; top:35px; width:94px; height:94px; background:#f6d9a6; padding:5px;"><i class="ava" /></div>
          <div class="abs center ell" style="left:40px; right:40px; top:140px; font-size:30px; font-weight:700; color:#6b2a1a;">用户昵称</div>
          <div class="abs center" style="left:0; right:0; top:186px; font-size:24px; color:#a26a4e;">邀请你一起加入</div>
          <div class="abs box" style="left:180px; top:250px; width:250px; height:250px; border-radius:18px; background:#fff; border:3px solid #f3d29c;">
            <div class="code" style="width:214px; height:214px;" :style="{ backgroundImage: sampleCode }" />
          </div>
          <div class="abs center" style="left:0; right:0; top:522px; font-size:22px; color:#b98a6e; letter-spacing:3px;">长按识别 · 立即加入</div>
        </div>
      </template>

      <!-- ═════ ④ 杂志大图 ═════ -->
      <template v-else-if="template === 'magazine'">
        <div v-if="hasBg" class="abs photo" :style="{ height: '1334px', backgroundImage: bgUrl }" />
        <template v-else>
          <div class="abs fill" :style="{ background: `linear-gradient(180deg, ${rgb(mix(theme, WHITE, 0.1))} 0%, ${rgb(mix(theme, BLACK, 0.55))} 100%)` }" />
          <div class="abs dot" style="left:100px; top:40px; width:760px; height:760px; background:radial-gradient(circle, rgba(255,255,255,.35) 0%, rgba(255,255,255,0) 70%);" />
        </template>
        <div class="abs fill" style="background:linear-gradient(180deg, rgba(0,0,0,.35) 0%, rgba(0,0,0,0) 22%, rgba(0,0,0,0) 45%, rgba(10,10,14,.82) 72%, #0e0e12 100%);" />
        <div class="abs ell" style="left:56px; width:440px; top:64px; font-size:24px; color:rgba(255,255,255,.92); letter-spacing:6px;">{{ mallName }}</div>
        <div class="abs" style="right:56px; top:64px; font-size:24px; color:rgba(255,255,255,.7); letter-spacing:4px;">INVITATION</div>
        <div class="abs" style="left:56px; right:56px; top:112px; border-top:1px solid rgba(255,255,255,.35);" />
        <div class="abs" :style="{ left: '56px', top: '820px', fontSize: '24px', letterSpacing: '8px', fontWeight: 600, color: rgb(mix(theme, WHITE, 0.45)) }">专属邀请</div>
        <div class="abs ell" style="left:52px; right:52px; top:860px; font-size:120px; font-weight:800; color:#fff; line-height:1.05; letter-spacing:2px;">{{ title }}</div>
        <div v-if="sub" class="abs ell" style="left:56px; right:56px; top:996px; font-size:30px; color:rgba(255,255,255,.78);">{{ sub }}</div>
        <div class="abs ava-ring" style="left:53px; top:1183px; width:82px; height:82px; background:rgba(255,255,255,.9); padding:3px;"><i class="ava" /></div>
        <div class="abs ell" style="left:150px; width:300px; top:1186px; font-size:28px; font-weight:700; color:#fff;">用户昵称</div>
        <div class="abs" style="left:150px; top:1228px; font-size:22px; color:rgba(255,255,255,.6);">邀请你加入</div>
        <div class="abs" style="left:480px; top:1066px; width:214px; height:230px; border-radius:20px; background:#fff;">
          <div class="abs code" style="left:22px; top:18px; width:170px; height:170px;" :style="{ backgroundImage: sampleCode }" />
          <div class="abs center" style="left:0; right:0; top:196px; font-size:20px; color:#8a8f9b;">长按识别</div>
        </div>
      </template>

      <!-- ═════ ⑤ 黑金尊享 ═════ -->
      <template v-else-if="template === 'blackgold'">
        <div class="abs fill" style="background:#111114;" />
        <div class="abs dot" style="left:55px; top:-130px; width:640px; height:640px; background:radial-gradient(circle, rgba(214,178,110,.22) 0%, rgba(214,178,110,0) 70%);" />
        <div class="abs" style="left:36px; top:36px; right:36px; bottom:36px; border:1.5px solid rgba(214,178,110,.55);" />
        <div class="abs" style="left:48px; top:48px; right:48px; bottom:48px; border:1px solid rgba(214,178,110,.22);" />
        <div class="abs corner" style="left:30px; top:30px; border-width:4px 0 0 4px;" />
        <div class="abs corner" style="right:30px; top:30px; border-width:4px 4px 0 0;" />
        <div class="abs corner" style="left:30px; bottom:30px; border-width:0 0 4px 4px;" />
        <div class="abs corner" style="right:30px; bottom:30px; border-width:0 4px 4px 0;" />
        <div class="abs center ell" style="left:95px; right:95px; top:120px; font-size:24px; color:#d6b26e; letter-spacing:10px;">{{ mallName }}</div>
        <div class="abs center" style="left:0; right:0; top:196px; font-size:22px; color:rgba(214,178,110,.7); letter-spacing:12px;">INVITATION</div>
        <div class="abs center ell gold-text" style="left:75px; right:75px; top:250px; font-size:100px; font-weight:700; letter-spacing:18px; line-height:1.1;">{{ title }}</div>
        <div class="abs" style="left:243px; top:410px; width:110px; height:1.5px; background:rgba(214,178,110,.6);" />
        <div class="abs" style="left:369px; top:405px; width:12px; height:12px; background:#d6b26e; transform:rotate(45deg);" />
        <div class="abs" style="left:397px; top:410px; width:110px; height:1.5px; background:rgba(214,178,110,.6);" />
        <div v-if="sub" class="abs center ell" style="left:75px; right:75px; top:440px; font-size:30px; color:rgba(255,255,255,.72); letter-spacing:4px;">{{ sub }}</div>
        <div class="abs ava-ring" style="left:303px; top:554px; width:144px; height:144px; background:#d6b26e; padding:2px;">
          <div class="ava-ring" style="width:100%; height:100%; background:#111114; padding:4px;"><i class="ava" /></div>
        </div>
        <div class="abs center ell" style="left:95px; right:95px; top:720px; font-size:34px; font-weight:600; color:#fff; letter-spacing:2px;">用户昵称</div>
        <div class="abs center" style="left:0; right:0; top:772px; font-size:24px; color:rgba(255,255,255,.55); letter-spacing:3px;">邀请你一起加入</div>
        <div class="abs box" style="left:230px; top:860px; width:290px; height:290px; border-radius:16px; background:#fff; box-shadow:0 0 0 1.5px #d6b26e, 0 0 0 12px rgba(214,178,110,.12);">
          <div class="code" style="width:250px; height:250px;" :style="{ backgroundImage: sampleCode }" />
        </div>
        <div class="abs center" style="left:0; right:0; top:1190px; font-size:22px; color:rgba(214,178,110,.8); letter-spacing:6px;">长按识别小程序码</div>
      </template>
    </div>
  </div>
</template>

<script>
const DEFAULT_THEME = '#FF4B3E'

/**
 * 示意用的小程序码:按太阳码的样子(三个定位圈 + 放射状码点)生成一张 SVG,
 * 用固定种子,每次刷新画出来都一样。真实海报贴的是微信返回的码
 */
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

function parseHex(hex) {
  if (typeof hex !== 'string') return null
  let h = hex.trim().replace(/^#/, '')
  if (/^[0-9a-f]{3}$/i.test(h)) h = h.split('').map(c => c + c).join('')
  if (!/^[0-9a-f]{6}$/i.test(h)) return null
  return [0, 2, 4].map(i => parseInt(h.substr(i, 2), 16))
}

export default {
  name: 'InvitePosterPreview',
  props: {
    template: { type: String, default: 'ticket' },
    themeColor: { type: String, default: DEFAULT_THEME },
    mainTitle: { type: String, default: '' },
    subTitle: { type: String, default: '' },
    bgImage: { type: String, default: '' },
    mall: { type: Object, default: () => ({ name: '', logo: '' }) },
    scale: { type: Number, default: 0.5 }
  },
  data() {
    return { WHITE: [255, 255, 255], BLACK: [0, 0, 0], sampleCode: SAMPLE_CODE }
  },
  computed: {
    theme() {
      return parseHex(this.themeColor) || parseHex(DEFAULT_THEME)
    },
    soft() {
      return this.mix(this.theme, this.WHITE, 0.9)
    },
    hasBg() {
      return !!this.bgImage
    },
    bgUrl() {
      return 'url(' + this.bgImage + ')'
    },
    title() {
      return (this.mainTitle || '').trim() || '诚邀加入'
    },
    sub() {
      return (this.subTitle || '').trim()
    },
    mallName() {
      return (this.mall && this.mall.name) || ''
    },
    ticketDark() {
      return this.mix(this.theme, this.BLACK, this.hasBg ? 0.38 : 0.15)
    },
    // 票券缺口颜色 = 缺口处(y=846)的背景色
    ticketNotch() {
      if (this.hasBg) return this.rgb(this.ticketDark)
      return this.rgb(this.mix(this.theme, this.ticketDark, 846 / 1334))
    }
  },
  methods: {
    mix(a, b, t) {
      return a.map((v, i) => Math.round(v + (b[i] - v) * t))
    },
    rgb(c, alpha) {
      return alpha === undefined ? `rgb(${c.join(',')})` : `rgba(${c.join(',')},${alpha})`
    }
  }
}
</script>

<style lang="scss" scoped>
.ipp-frame {
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
.photo { left: 0; top: 0; width: 750px; background-size: cover; background-position: center; }

.pill {
  left: 56px; top: 206px; height: 48px; padding: 0 24px; border-radius: 24px;
  background: rgba(255, 255, 255, 0.18); color: #fff; font-size: 24px; line-height: 48px; letter-spacing: 4px;
}

.round-logo, .square-logo {
  flex-shrink: 0; background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden; font-weight: 700;
}
.round-logo {
  width: 60px; height: 60px; border-radius: 50%; font-size: 28px;
  img { width: 52px; height: 52px; border-radius: 50%; object-fit: cover; }
}
.square-logo {
  width: 52px; height: 52px; border-radius: 14px; font-size: 26px;
  img { width: 44px; height: 44px; border-radius: 11px; object-fit: cover; }
}

.ava-ring { border-radius: 50%; box-sizing: border-box; }
.ava {
  display: block; width: 100%; height: 100%; border-radius: 50%;
  background: linear-gradient(145deg, #ffe0c2, #f4a988);
}

.step-no {
  display: inline-block; width: 34px; height: 34px; border-radius: 50%;
  text-align: center; line-height: 34px; font-size: 20px; font-weight: 700;
}

/* 示意小程序码,图案见 SAMPLE_CODE */
.code {
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
}

.tip-lines {
  &::before, &::after {
    content: ''; display: inline-block; width: 48px; height: 2px; background: #e1e3e8;
    vertical-align: middle; margin: 0 16px 4px;
  }
}

.corner { width: 30px; height: 30px; border-color: #d6b26e; border-style: solid; }

.gold-text {
  background: linear-gradient(180deg, #fbe7b8 0%, #d6b26e 60%, #a8813f 100%);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}
</style>
