<template>
  <div class="st" :class="'align-' + d.align" :style="{ background: d.bg_color }">
    <div class="left">
      <i v-if="d.decor === 'bar'" class="bar" :style="{ background: d.accent_color }" />
      <i v-if="d.decor === 'line'" class="line" :style="{ background: d.accent_color }" />
      <span class="title" :style="{ color: d.text_color, fontFamily: font, fontSize: (d.size || 19) + 'px' }">{{ d.title }}</span>
      <span v-if="d.en_title" class="en" :style="{ color: d.sub_color }">{{ d.en_title }}</span>
      <i v-if="d.decor === 'line'" class="line" :style="{ background: d.accent_color }" />
    </div>
    <span v-if="d.more_text && d.align !== 'center'" class="more" :style="{ color: d.sub_color }">{{ d.more_text }} ›</span>
  </div>
</template>

<script>
import { FONT_FAMILY } from './style-kit/presets'

export default {
  name: 'SectionTitle',
  props: { data: { type: Object, default: () => ({}) }},
  computed: {
    d() { return this.data || {} },
    font() { return FONT_FAMILY[this.d.font] || FONT_FAMILY.sans }
  }
}
</script>

<style lang="scss" scoped>
.st { display: flex; align-items: baseline; justify-content: space-between; padding: 16px 12px 8px; }
.left { display: flex; align-items: baseline; gap: 6px; min-width: 0; }
.title { font-weight: 900; letter-spacing: .5px; white-space: nowrap; }
.en { font-size: 9px; letter-spacing: 2px; white-space: nowrap; }
.more { font-size: 11px; white-space: nowrap; }
.bar { width: 3px; height: 14px; border-radius: 2px; align-self: center; }
.line { width: 22px; height: 1px; align-self: center; }
.align-center { justify-content: center; .left { flex-direction: column; align-items: center; gap: 2px; } }
.align-center .line { display: none; }
</style>
