<template>
  <div :style="{ padding: d.is_padding == 1 ? '6px 10px' : '0' }">
    <div class="cc" :style="{ background: d.bg_color2, borderColor: d.accent_color }">
      <div class="ava" :style="{ background: d.accent_color }">
        <img v-if="d.avatar" :src="d.avatar" alt="">
        <span v-else>{{ d.avatar_text || '客' }}</span>
      </div>
      <div class="copy">
        <b :style="{ color: d.text_color }">{{ d.title }}</b>
        <p v-if="d.desc" :style="{ color: d.sub_color }">{{ d.desc }}</p>
      </div>
      <span v-if="d.btn_text" class="btn" :style="{ background: d.btn_bg, color: d.btn_color }">{{ d.btn_text }}</span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ContactCard',
  props: { data: { type: Object, default: () => ({}) }},
  computed: { d() { return this.data || {} } }
}
</script>

<style lang="scss" scoped>
.cc { display: flex; align-items: center; gap: 10px; padding: 12px; border-radius: 10px; border: 1px dashed; }
.ava { width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; overflow: hidden; color: #fff; font-weight: 800;
  display: flex; align-items: center; justify-content: center; img { width: 100%; height: 100%; object-fit: cover; } }
.copy { flex: 1; min-width: 0; b { font-size: 12px; } p { font-size: 10px; line-height: 1.5; margin-top: 2px; } }
.btn { height: 24px; line-height: 24px; padding: 0 10px; border-radius: 12px; font-size: 10px; font-weight: 700; white-space: nowrap; }
</style>
