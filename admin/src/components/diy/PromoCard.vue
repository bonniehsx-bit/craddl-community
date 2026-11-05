<template>
  <div :style="{ padding: d.is_padding == 1 ? '6px 10px' : '0' }">
    <div class="pc" :style="boxStyle">
      <i v-if="d.glow == 1" class="glow" :style="{ background: 'radial-gradient(ellipse,' + d.glow_color + ', transparent 65%)' }" />
      <div class="face" :style="{ boxShadow: '0 0 0 2px ' + d.accent_color }">
        <img v-if="d.icon_type === 'image' && d.pic_url" :src="d.pic_url" alt="">
        <span v-else>{{ d.icon || '🎁' }}</span>
      </div>
      <div class="copy">
        <b :style="{ color: d.text_color }">{{ d.title }}</b>
        <p v-if="d.subtitle" :style="{ color: d.sub_color }">{{ d.subtitle }}</p>
      </div>
      <span v-if="d.btn_text" class="btn" :style="{ background: d.btn_bg, color: d.btn_color }">{{ d.btn_text }} ›</span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PromoCard',
  props: { data: { type: Object, default: () => ({}) }},
  computed: {
    d() { return this.data || {} },
    boxStyle() {
      return {
        background: 'linear-gradient(135deg,' + this.d.bg_color2 + ',' + this.d.bg_color + ')',
        borderRadius: this.d.is_circle == 1 ? '12px' : '0'
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.pc { position: relative; overflow: hidden; display: flex; align-items: center; gap: 10px; padding: 12px; }
.glow { position: absolute; right: -24px; top: -30px; width: 140px; height: 90px; pointer-events: none; }
.face { position: relative; width: 44px; height: 44px; border-radius: 50%; background: #fff; flex-shrink: 0; overflow: hidden;
  display: flex; align-items: center; justify-content: center; font-size: 26px;
  img { width: 100%; height: 100%; object-fit: cover; } }
.copy { position: relative; flex: 1; min-width: 0; b { font-size: 14px; } p { font-size: 10px; margin-top: 2px; } }
.btn { position: relative; height: 26px; line-height: 26px; padding: 0 10px; border-radius: 13px; font-size: 11px; font-weight: 700; white-space: nowrap; }
</style>
