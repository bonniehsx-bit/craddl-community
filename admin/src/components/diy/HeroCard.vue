<template>
  <div class="hero-wrap" :style="{ padding: d.is_padding == 1 ? '0 10px' : '0' }">
    <div class="hero" :style="boxStyle">
      <div v-if="d.glow == 1" class="glow" :style="{ background: 'radial-gradient(ellipse at 30% 60%,' + d.glow_color + ', transparent 60%)' }" />
      <!-- 顶边淡入:主色 → 透明,和上面同色的顶栏搜索等无缝衔接(和手机端同一规则) -->
      <div v-if="d.bg_type !== 'image'" class="top-fade" :style="{ background: 'linear-gradient(to bottom,' + (d.bg_color || '#0e1a2b') + ',' + fadeEnd + ')' }" />
      <div class="inner">
        <div class="copy">
          <div v-if="d.eyebrow" class="eyebrow" :style="{ color: d.accent_color }">{{ d.eyebrow }}</div>
          <div class="title" :style="{ color: d.text_color, fontFamily: font }">
            {{ d.title }}<br v-if="d.title && d.title_hl"><span :style="{ color: d.accent_color }">{{ d.title_hl }}</span>
          </div>
          <div v-if="d.subtitle" class="sub" :style="{ color: d.sub_color }">{{ d.subtitle }}</div>
          <div v-if="days !== null && days >= 0" class="countdown" :style="{ color: d.accent_color, borderColor: d.accent_color }">
            <b v-if="days === 0">{{ d.countdown_today || '今天截止' }}</b>
            <template v-else>{{ d.countdown_prefix }} <b>{{ days }}</b> {{ d.countdown_suffix }}</template>
          </div>
          <div v-if="d.btn1_text || d.btn2_text" class="btns">
            <span v-if="d.btn1_text" class="btn" :style="{ background: d.btn_bg, color: d.btn_color }">{{ d.btn1_text }}</span>
            <span v-if="d.btn2_text" class="btn ghost" :style="{ color: d.text_color, borderColor: d.sub_color }">{{ d.btn2_text }}</span>
          </div>
        </div>
        <div v-if="d.image" class="art" :style="{ transform: 'rotate(' + (d.image_rotate || 0) + 'deg)' }">
          <div class="frame" :class="'frame-' + d.image_frame">
            <img :src="d.image" alt="">
            <template v-if="d.image_frame === 'envelope'">
              <i class="line" /><i class="line short" />
            </template>
          </div>
          <div v-if="d.mark_text" class="mark" :style="{ color: d.accent_color, borderColor: d.accent_color }">
            <span v-for="(l, i) in markLines" :key="i">{{ l }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { FONT_FAMILY, daysLeft, clearOf } from './style-kit/presets'

export default {
  name: 'HeroCard',
  props: { data: { type: Object, default: () => ({}) }},
  computed: {
    d() {
      return this.data || {}
    },
    font() {
      return FONT_FAMILY[this.d.title_font] || FONT_FAMILY.sans
    },
    days() {
      return daysLeft(this.d.countdown_date)
    },
    markLines() {
      return String(this.d.mark_text || '').split('\n').filter(Boolean).slice(0, 3)
    },
    fadeEnd() {
      return clearOf(this.d.bg_color || '#0e1a2b')
    },
    boxStyle() {
      const d = this.d
      let bg = d.bg_color
      if (d.bg_type === 'gradient') bg = 'linear-gradient(135deg,' + d.bg_color + ',' + d.bg_color2 + ')'
      if (d.bg_type === 'image' && d.bg_image) bg = 'url(' + d.bg_image + ') center/cover no-repeat, ' + d.bg_color
      return { background: bg, borderRadius: d.is_circle == 1 ? '10px' : '0' }
    }
  }
}
</script>

<style lang="scss" scoped>
.hero { position: relative; overflow: hidden; padding: 14px 14px 18px; }
.glow { position: absolute; left: -40px; right: -40px; top: -30px; height: 140px; filter: blur(6px); pointer-events: none; }
.top-fade { position: absolute; left: 0; right: 0; top: 0; height: 56px; pointer-events: none; }
.inner { position: relative; display: flex; gap: 10px; }
.copy { flex: 1; min-width: 0; }
.eyebrow { font-size: 9px; letter-spacing: 2px; }
.title { margin-top: 6px; font-size: 22px; font-weight: 900; line-height: 1.25; letter-spacing: 1px; }
.sub { margin-top: 6px; font-size: 11px; line-height: 1.6; white-space: pre-line; }
.countdown { display: inline-block; margin-top: 10px; padding: 2px 8px; font-size: 10px; border: 1px dashed; border-radius: 3px; b { font-size: 15px; } }
.btns { margin-top: 10px; display: flex; gap: 6px; }
.btn { height: 28px; line-height: 28px; padding: 0 12px; border-radius: 14px; font-size: 11px; font-weight: 700; white-space: nowrap; }
.btn.ghost { background: transparent; border: 1px solid; font-weight: 500; }
.art { position: relative; width: 104px; flex-shrink: 0; margin-top: 8px; }
.frame { position: relative; img { display: block; width: 100%; height: 96px; object-fit: cover; } }
.frame-envelope { background: #d8b98a; padding: 6px; border-radius: 3px; box-shadow: 0 8px 18px rgba(0,0,0,.35);
  img { border: 2px solid #fff; }
  .line { display: block; height: 3px; border-radius: 2px; background: rgba(42,37,34,.25); margin-top: 5px; }
  .line.short { width: 60%; } }
.frame-polaroid { background: #fff; padding: 5px 5px 16px; box-shadow: 0 8px 18px rgba(0,0,0,.3); }
.frame-stamp { background: #fff; padding: 5px; box-shadow: 0 6px 14px rgba(0,0,0,.25);
  -webkit-mask: linear-gradient(#000 0 0) content-box, radial-gradient(circle 2.6px at 3.8px 3.8px, transparent 96%, #000) -3.8px -3.8px / 7.6px 7.6px; }
.mark { position: absolute; right: -5px; top: -13px; width: 56px; height: 56px; border-radius: 50%; border: 2px solid;
  background: rgba(255,255,255,.9); display: flex; flex-direction: column; align-items: center; justify-content: center;
  transform: rotate(-16deg); font-size: 7px; font-weight: 700; line-height: 1.2; text-align: center; }
</style>
