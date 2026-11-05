<template>
  <div class="style-config">
    <el-form label-width="76px" size="small" label-position="left">
      <div class="config-section">
        <div class="section-title">内容</div>
        <el-form-item label="店名">
          <el-input v-model="form.brand_title" placeholder="不填只显示搜索框" />
        </el-form-item>
        <el-form-item v-if="form.brand_title" label="店名小字">
          <el-input v-model="form.brand_sub" placeholder="如 英文名 / 一句话定位,不填不显示" />
        </el-form-item>
        <el-form-item label="提示文字">
          <el-input v-model="form.tips" placeholder="如 搜 冰箱贴 / 玩偶 / 礼盒" />
        </el-form-item>
        <el-form-item label="跳转">
          <link-picker v-model="form.url" />
          <div class="tip">不选就进商城搜索页</div>
        </el-form-item>
      </div>
      <div class="config-section">
        <div class="section-title">样式</div>
        <preset-bar :value="form" @input="onPreset" />
        <div class="tip">深色配色自动配半透明白框,浅色配色配浅灰框</div>
        <div style="margin-top: 12px">
          <color-fields :value="form" :fields="colorFields" />
        </div>
      </div>
    </el-form>
  </div>
</template>

<script>
import configMixin from './style-kit/configMixin'
import PresetBar from './style-kit/PresetBar'
import ColorFields from './style-kit/ColorFields'
import { applyPreset } from './style-kit/presets'

/** 颜色亮度 0-255;认 #rgb / #rrggbb / rgb(a)(),认不出按浅色算 */
function luminance(c) {
  const s = String(c || '').trim()
  let m = s.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i)
  if (m) {
    const h = m[1].length === 3 ? m[1].replace(/./g, x => x + x) : m[1]
    return 0.299 * parseInt(h.slice(0, 2), 16) + 0.587 * parseInt(h.slice(2, 4), 16) + 0.114 * parseInt(h.slice(4, 6), 16)
  }
  m = s.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i)
  return m ? 0.299 * m[1] + 0.587 * m[2] + 0.114 * m[3] : 255
}

/** 搜索框颜色跟着底色深浅走:深底配半透明白框,浅底配浅灰框(任何浅底色上都看得见) */
function boxColors(form) {
  const dark = luminance(form.bg_color) < 140
  return {
    box_bg: dark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.04)',
    box_border: dark ? 'rgba(255,255,255,0.14)' : 'rgba(0,0,0,0.06)',
    tips_color: dark ? 'rgba(255,255,255,0.55)' : form.sub_color
  }
}

export default {
  name: 'BrandSearchConfig',
  components: { PresetBar, ColorFields },
  mixins: [configMixin],
  data() {
    return {
      colorFields: [
        { key: 'bg_color', label: '底色' }, { key: 'text_color', label: '店名' },
        { key: 'accent_color', label: '店名小字' }, { key: 'box_bg', label: '框底色' },
        { key: 'box_border', label: '框描边' }, { key: 'tips_color', label: '提示文字' }
      ]
    }
  },
  methods: {
    defaults() {
      const form = applyPreset({
        preset: 'night', brand_title: '我的小店', brand_sub: 'MY STORE', tips: '搜索商品', url: '',
        bg_color: '', text_color: '', sub_color: '', accent_color: ''
      }, 'night')
      return Object.assign(form, boxColors(form))
    },
    onPreset(next) {
      this.replace(Object.assign(next, boxColors(next)))
    }
  }
}
</script>

<style lang="scss" scoped>
@import './style-kit/config.scss';
</style>
