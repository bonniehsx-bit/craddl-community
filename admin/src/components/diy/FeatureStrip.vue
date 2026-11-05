<template>
  <div :style="{ padding: d.is_padding == 1 ? '0 10px' : '0' }">
    <div class="strip" :style="{ background: d.bg_color, borderRadius: d.is_circle == 1 ? '8px' : '0' }">
      <div v-for="(it, i) in items" :key="i" class="it" :style="{ color: d.sub_color }">
        <i :style="{ background: d.accent_color, color: d.btn_color }">{{ it.icon }}</i>{{ it.text }}
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'FeatureStrip',
  props: { data: { type: Object, default: () => ({}) }},
  computed: {
    d() { return this.data || {} },
    items() { return (this.d.items || []).filter(it => it.text).slice(0, 4) }
  }
}
</script>

<style lang="scss" scoped>
.strip { display: flex; justify-content: space-around; padding: 9px 10px; }
.it { display: flex; align-items: center; gap: 4px; font-size: 11px; white-space: nowrap; }
.it i { width: 15px; height: 15px; border-radius: 50%; font-style: normal; font-size: 9px; display: flex; align-items: center; justify-content: center; }
</style>
