<template>
  <div :style="{ padding: d.is_padding == 1 ? '6px 10px' : '0' }">
    <div class="vc" :style="{ height: (d.height || 380) / 2 + 'px', borderRadius: d.is_circle == 1 ? '12px' : '0' }">
      <img v-if="d.cover_pic" :src="d.cover_pic" alt="">
      <div v-else class="ph"><i class="el-icon-video-camera" /></div>
      <i class="shade" :style="{ background: 'linear-gradient(180deg, transparent 35%,' + d.bg_color + ')' }" />
      <span class="play"><i :style="{ borderLeftColor: d.accent_color }" /></span>
      <div class="copy" :style="{ color: d.text_color }">
        <b>{{ d.title }}</b>
        <p v-if="d.subtitle">{{ d.subtitle }}</p>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'VideoCard',
  props: { data: { type: Object, default: () => ({}) }},
  computed: { d() { return this.data || {} } }
}
</script>

<style lang="scss" scoped>
.vc { position: relative; overflow: hidden; background: #1f2329;
  img { width: 100%; height: 100%; object-fit: cover; display: block; } }
.ph { height: 100%; display: flex; align-items: center; justify-content: center; color: #606266; font-size: 32px; }
.shade { position: absolute; inset: 0; }
.play { position: absolute; left: 50%; top: 42%; transform: translate(-50%, -50%); width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,.9);
  i { position: absolute; left: 16px; top: 12px; border-left: 12px solid; border-top: 8px solid transparent; border-bottom: 8px solid transparent; } }
.copy { position: absolute; left: 12px; right: 12px; bottom: 10px; b { font-size: 14px; } p { font-size: 10px; opacity: .8; margin-top: 2px; } }
</style>
