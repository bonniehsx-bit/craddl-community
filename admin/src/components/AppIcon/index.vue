<template>
  <div class="app-icon" :style="boxStyle">
    <!-- 有真实 logo 用 logo;加载失败回退矢量图标 / 首字母,杜绝裂图 -->
    <img v-if="app.pic_url && !broken" :src="app.pic_url" alt="" @error="broken = true" />
    <i v-else-if="app.icon" :class="app.icon" :style="{ fontSize: Math.round(size * 0.5) + 'px' }"></i>
    <span v-else :style="{ fontSize: Math.round(size * 0.42) + 'px' }">{{ app.label ? app.label.charAt(0) : '?' }}</span>
  </div>
</template>

<script>
import { colorOf } from '@/utils/appCenter'

export default {
  name: 'AppIcon',
  props: {
    app: { type: Object, required: true },
    size: { type: Number, default: 40 }
  },
  data() {
    return { broken: false }
  },
  computed: {
    boxStyle() {
      const s = this.size + 'px'
      const plain = !this.app.pic_url || this.broken
      return {
        width: s,
        height: s,
        borderRadius: Math.round(this.size * 0.28) + 'px',
        background: plain ? colorOf(this.app.label) : '#fff'
      }
    }
  },
  watch: {
    'app.pic_url'() {
      this.broken = false
    }
  }
}
</script>

<style lang="scss" scoped>
.app-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  overflow: hidden;
  color: #fff;
  font-weight: 600;

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
}
</style>
