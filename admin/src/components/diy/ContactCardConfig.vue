<template>
  <div class="style-config">
    <el-form label-width="76px" size="small" label-position="left">
      <div class="config-section">
        <div class="section-title">内容</div>
        <el-form-item label="头像">
          <single-image-selector v-model="form.avatar" size="100*100" />
        </el-form-item>
        <el-form-item v-if="!form.avatar" label="头像文字">
          <el-input v-model="form.avatar_text" maxlength="2" style="width: 100px" placeholder="没传头像时显示" />
        </el-form-item>
        <el-form-item label="标题">
          <el-input v-model="form.title" maxlength="14" show-word-limit />
        </el-form-item>
        <el-form-item label="说明">
          <el-input v-model="form.desc" type="textarea" :rows="2" maxlength="40" show-word-limit />
        </el-form-item>
      </div>
      <div class="config-section">
        <div class="section-title">按钮</div>
        <el-form-item label="按钮文字">
          <el-input v-model="form.btn_text" placeholder="不填不显示按钮" maxlength="6" />
        </el-form-item>
        <template v-if="form.btn_text">
          <el-form-item label="点了做什么">
            <el-radio-group v-model="form.action">
              <el-radio-button label="copy">复制文字</el-radio-button>
              <el-radio-button label="phone">打电话</el-radio-button>
              <el-radio-button label="link">跳转页面</el-radio-button>
            </el-radio-group>
          </el-form-item>
          <el-form-item v-if="form.action !== 'link'" :label="form.action === 'phone' ? '电话' : '复制内容'">
            <el-input v-model="form.value" :placeholder="form.action === 'phone' ? '如 400-xxx-xxxx' : '如 微信号'" />
          </el-form-item>
          <el-form-item v-else label="跳转">
            <link-picker v-model="form.value" />
          </el-form-item>
        </template>
      </div>
      <div class="config-section">
        <div class="section-title">配色</div>
        <preset-bar :value="form" @input="replace" />
        <div style="margin-top: 12px">
          <color-fields :value="form" :fields="colorFields" />
        </div>
        <el-form-item label="左右边距" style="margin-top: 12px">
          <el-radio-group v-model="form.is_padding">
            <el-radio-button label="0">无</el-radio-button>
            <el-radio-button label="1">有</el-radio-button>
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
  name: 'ContactCardConfig',
  components: { PresetBar, ColorFields },
  mixins: [configMixin],
  data() {
    return {
      colorFields: [{ key: 'bg_color2', label: '背景' }, { key: 'text_color', label: '标题' }, { key: 'sub_color', label: '说明' },
        { key: 'accent_color', label: '头像/边框' }, { key: 'btn_bg', label: '按钮底' }, { key: 'btn_color', label: '按钮字' }]
    }
  },
  methods: {
    defaults() {
      return applyPreset({
        preset: 'paper', avatar: '', avatar_text: '客', title: '有问题随时找我们', desc: '工作日 9:00–21:00 在线，下单后有任何问题都可以联系',
        btn_text: '复制微信号', action: 'copy', value: '',
        bg_color2: '', text_color: '', sub_color: '', accent_color: '', btn_bg: '', btn_color: '',
        is_padding: '1'
      }, 'paper')
    }
  }
}
</script>

<style lang="scss" scoped>
@import './style-kit/config.scss';
</style>
