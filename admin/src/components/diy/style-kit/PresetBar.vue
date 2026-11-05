<template>
  <div class="preset-bar">
    <div
      v-for="p in presets"
      :key="p.key"
      class="preset"
      :class="{ active: value && value.preset === p.key }"
      :title="p.name"
      @click="pick(p.key)"
    >
      <span class="swatch" :style="{ background: 'linear-gradient(135deg,' + p.bg_color + ',' + p.bg_color2 + ')' }">
        <i :style="{ background: p.accent_color }"></i>
      </span>
      <span class="name">{{ p.name }}</span>
    </div>
  </div>
</template>

<script>
import { STYLE_PRESETS, applyPreset } from './presets'

/** 风格组件配置面板顶部的配色预设条:点一下把整套颜色填进配置 */
export default {
  name: 'PresetBar',
  props: { value: Object },
  data() {
    return { presets: STYLE_PRESETS }
  },
  methods: {
    pick(key) {
      this.$emit('input', applyPreset(this.value || {}, key))
    }
  }
}
</script>

<style lang="scss" scoped>
.preset-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.preset {
  width: 62px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 6px 0;
  border: 1px solid #ebeef5;
  border-radius: 6px;
  cursor: pointer;

  &:hover {
    border-color: #c0c4cc;
  }

  &.active {
    border-color: var(--admin-accent-text);
    box-shadow: 0 0 0 1px var(--admin-accent-text) inset;
  }
}

.swatch {
  position: relative;
  width: 36px;
  height: 24px;
  border-radius: 4px;
  border: 1px solid rgba(0, 0, 0, 0.06);

  i {
    position: absolute;
    right: 3px;
    bottom: 3px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
  }
}

.name {
  font-size: 11px;
  color: #606266;
}
</style>
