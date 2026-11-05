<template>
  <div class="style-config">
    <el-form label-width="76px" size="small" label-position="left">
      <div class="config-section">
        <div class="section-title">内容</div>
        <el-form-item label="左侧图标">
          <el-radio-group v-model="form.icon_type">
            <el-radio-button label="emoji">表情 / 字</el-radio-button>
            <el-radio-button label="image">图片</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="form.icon_type === 'emoji'" label="表情">
          <el-input v-model="form.icon" maxlength="2" style="width: 100px" />
        </el-form-item>
        <el-form-item v-else label="图片">
          <single-image-selector v-model="form.pic_url" size="120*120" />
        </el-form-item>
        <el-form-item label="标题">
          <el-input v-model="form.title" maxlength="12" show-word-limit />
        </el-form-item>
        <el-form-item label="副标题">
          <el-input v-model="form.subtitle" maxlength="20" show-word-limit />
        </el-form-item>
        <el-form-item label="按钮">
          <el-input v-model="form.btn_text" placeholder="不填不显示按钮" maxlength="6" />
        </el-form-item>
        <el-form-item label="跳转">
          <link-picker v-model="form.url" />
          <div class="tip">整张卡片都可以点。</div>
        </el-form-item>
      </div>
      <div class="config-section">
        <div class="section-title">配色</div>
        <preset-bar :value="form" @input="replace" />
        <el-form-item label="光晕" style="margin-top: 12px">
          <el-switch v-model="form.glow" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <color-fields :value="form" :fields="colorFields" />
      </div>
      <div class="config-section">
        <div class="section-title">版式</div>
        <el-form-item label="左右边距">
          <el-radio-group v-model="form.is_padding">
            <el-radio-button label="0">无</el-radio-button>
            <el-radio-button label="1">有</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="圆角">
          <el-radio-group v-model="form.is_circle">
            <el-radio-button label="0">直角</el-radio-button>
            <el-radio-button label="1">圆角</el-radio-button>
          </el-radio-group>
        </el-form-item>
      </div>
    </el-form>
  </div>
</template>

<script>
import configMixin from './style-kit/configMixin'
import PresetBar from './style-kit/PresetBar'
import ColorFields from './style-kit/ColorFields'
import { applyPreset } from './style-kit/presets'

export default {
  name: 'PromoCardConfig',
  components: { PresetBar, ColorFields },
  mixins: [configMixin],
  data() {
    return {
      colorFields: [{ key: 'bg_color', label: '背景' }, { key: 'bg_color2', label: '渐变' }, { key: 'glow_color', label: '光晕' },
        { key: 'text_color', label: '标题' }, { key: 'sub_color', label: '副标题' }, { key: 'accent_color', label: '图标圈' },
        { key: 'btn_bg', label: '按钮底' }, { key: 'btn_color', label: '按钮字' }]
    }
  },
  methods: {
    defaults() {
      const d = applyPreset({
        preset: 'night', icon_type: 'emoji', icon: '🎁', pic_url: '',
        title: '新人专享礼', subtitle: '注册即领 3 张优惠券', btn_text: '去看看', url: '', glow: 1,
        bg_color: '', bg_color2: '', glow_color: '', text_color: '', sub_color: '', accent_color: '', btn_bg: '', btn_color: '',
        is_padding: '1', is_circle: '1'
      }, 'night')
      // 深色卡片上的按钮用强调色更醒目
      d.btn_bg = d.accent_color
      d.btn_color = d.bg_color
      return d
    }
  }
}
</script>

<style lang="scss" scoped>
@import './style-kit/config.scss';
</style>
