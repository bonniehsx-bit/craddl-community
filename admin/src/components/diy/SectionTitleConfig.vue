<template>
  <div class="style-config">
    <el-form label-width="76px" size="small" label-position="left">
      <div class="config-section">
        <div class="section-title">内容</div>
        <el-form-item label="标题">
          <el-input v-model="form.title" placeholder="如 本季推荐" />
        </el-form-item>
        <el-form-item label="英文小字">
          <el-input v-model="form.en_title" placeholder="如 RECOMMEND,不填不显示" />
        </el-form-item>
        <el-form-item label="右侧文字">
          <el-input v-model="form.more_text" placeholder="如 查看全部,不填不显示" />
        </el-form-item>
        <el-form-item v-if="form.more_text" label="跳转">
          <link-picker v-model="form.url" />
        </el-form-item>
      </div>
      <div class="config-section">
        <div class="section-title">样式</div>
        <el-form-item label="字体">
          <el-radio-group v-model="form.font">
            <el-radio-button label="serif">衬线(宋体)</el-radio-button>
            <el-radio-button label="sans">黑体</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="对齐">
          <el-radio-group v-model="form.align">
            <el-radio-button label="left">左对齐</el-radio-button>
            <el-radio-button label="center">居中</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="装饰">
          <el-radio-group v-model="form.decor">
            <el-radio-button label="none">无</el-radio-button>
            <el-radio-button label="bar">竖条</el-radio-button>
            <el-radio-button label="line">两侧细线</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="字号">
          <el-slider v-model="form.size" :min="14" :max="26" style="padding: 0 8px" />
        </el-form-item>
        <preset-bar :value="form" @input="replace" />
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

export default {
  name: 'SectionTitleConfig',
  components: { PresetBar, ColorFields },
  mixins: [configMixin],
  data() {
    return {
      colorFields: [{ key: 'bg_color', label: '背景' }, { key: 'text_color', label: '标题' },
        { key: 'sub_color', label: '小字' }, { key: 'accent_color', label: '装饰' }]
    }
  },
  methods: {
    defaults() {
      return applyPreset({
        preset: 'paper', title: '本季推荐', en_title: 'RECOMMEND', more_text: '查看全部', url: '',
        font: 'serif', align: 'left', decor: 'none', size: 19,
        bg_color: '', text_color: '', sub_color: '', accent_color: ''
      }, 'paper')
    }
  }
}
</script>

<style lang="scss" scoped>
@import './style-kit/config.scss';
</style>
