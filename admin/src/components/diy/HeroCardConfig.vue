<template>
  <div class="style-config">
    <el-form label-width="76px" size="small" label-position="left">
      <div class="config-section">
        <div class="section-title">配色</div>
        <preset-bar :value="form" @input="replace" />
        <el-form-item label="背景" style="margin-top: 12px">
          <el-radio-group v-model="form.bg_type">
            <el-radio-button label="color">纯色</el-radio-button>
            <el-radio-button label="gradient">渐变</el-radio-button>
            <el-radio-button label="image">图片</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item v-if="form.bg_type === 'image'" label="背景图">
          <single-image-selector v-model="form.bg_image" size="750*600" />
        </el-form-item>
        <el-form-item label="光晕">
          <el-switch v-model="form.glow" :active-value="1" :inactive-value="0" />
          <span class="tip" style="margin-left: 8px">左上角一团柔光,深色背景更出效果</span>
        </el-form-item>
        <color-fields :value="form" :fields="colorFields" />
      </div>

      <div class="config-section">
        <div class="section-title">文字</div>
        <el-form-item label="眉题">
          <el-input v-model="form.eyebrow" placeholder="小字,如 NEW SEASON · 2026" />
        </el-form-item>
        <el-form-item label="主标题">
          <el-input v-model="form.title" placeholder="第一行" />
        </el-form-item>
        <el-form-item label="高亮部分">
          <el-input v-model="form.title_hl" placeholder="第二行,用强调色显示" />
        </el-form-item>
        <el-form-item label="标题字体">
          <el-radio-group v-model="form.title_font">
            <el-radio-button label="serif">衬线(宋体)</el-radio-button>
            <el-radio-button label="sans">黑体</el-radio-button>
          </el-radio-group>
          <div class="tip">衬线体在 iPhone 上是宋体,多数安卓手机没有,会显示成黑体。</div>
        </el-form-item>
        <el-form-item label="副标题">
          <el-input v-model="form.subtitle" type="textarea" :rows="2" placeholder="可换行" />
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">倒计时</div>
        <el-form-item label="截止日期">
          <el-date-picker v-model="form.countdown_date" type="date" value-format="yyyy-MM-dd"
                          placeholder="不填不显示" style="width: 100%"
          />
          <div class="tip">每天自动算剩余天数,截止日当天显示「当天文案」,过了截止日自动隐藏。</div>
        </el-form-item>
        <el-form-item v-if="form.countdown_date" label="文案">
          <div style="display: flex; gap: 6px; align-items: center">
            <el-input v-model="form.countdown_prefix" placeholder="距活动截止" style="flex: 1" />
            <span>N</span>
            <el-input v-model="form.countdown_suffix" placeholder="天" style="width: 60px" />
          </div>
        </el-form-item>
        <el-form-item v-if="form.countdown_date" label="当天文案">
          <el-input v-model="form.countdown_today" placeholder="如 今天截止" />
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">按钮</div>
        <el-form-item label="主按钮">
          <el-input v-model="form.btn1_text" placeholder="不填不显示" />
        </el-form-item>
        <el-form-item v-if="form.btn1_text" label="跳转">
          <link-picker v-model="form.btn1_url" />
        </el-form-item>
        <el-form-item label="次按钮">
          <el-input v-model="form.btn2_text" placeholder="不填不显示" />
        </el-form-item>
        <el-form-item v-if="form.btn2_text" label="跳转">
          <link-picker v-model="form.btn2_url" />
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">配图</div>
        <el-form-item label="图片">
          <single-image-selector v-model="form.image" size="400*400" />
          <div class="tip">不传就只显示文字,文字占满整行。</div>
        </el-form-item>
        <template v-if="form.image">
          <el-form-item label="相框">
            <el-select v-model="form.image_frame" style="width: 100%">
              <el-option label="无" value="none" />
              <el-option label="信封" value="envelope" />
              <el-option label="拍立得" value="polaroid" />
              <el-option label="邮票" value="stamp" />
            </el-select>
          </el-form-item>
          <el-form-item label="倾斜">
            <el-slider v-model="form.image_rotate" :min="-12" :max="12" style="padding: 0 8px" />
          </el-form-item>
          <el-form-item label="印章文字">
            <el-input v-model="form.mark_text" type="textarea" :rows="2" placeholder="盖在配图右上角的圆形印章,每行一段,不填不盖" />
          </el-form-item>
          <el-form-item label="点击跳转">
            <link-picker v-model="form.image_url" />
          </el-form-item>
        </template>
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
  name: 'HeroCardConfig',
  components: { PresetBar, ColorFields },
  mixins: [configMixin],
  data() {
    return {
      colorFields: [
        { key: 'bg_color', label: '背景' }, { key: 'bg_color2', label: '渐变' }, { key: 'glow_color', label: '光晕' },
        { key: 'text_color', label: '标题' }, { key: 'sub_color', label: '正文' }, { key: 'accent_color', label: '强调' },
        { key: 'btn_bg', label: '按钮底' }, { key: 'btn_color', label: '按钮字' }
      ]
    }
  },
  methods: {
    defaults() {
      return applyPreset({
        preset: 'night', bg_type: 'gradient', bg_image: '', glow: 1,
        bg_color: '', bg_color2: '', glow_color: '', text_color: '', sub_color: '', accent_color: '', btn_bg: '', btn_color: '',
        eyebrow: 'NEW SEASON · 2026', title: '把这份心意', title_hl: '寄给 TA', title_font: 'serif',
        subtitle: '精选好物 · 用心包装\n下单后尽快为你发出',
        countdown_date: '', countdown_prefix: '距活动截止', countdown_suffix: '天', countdown_today: '今天截止',
        btn1_text: '立即选购', btn1_url: '', btn2_text: '了解更多', btn2_url: '',
        image: '', image_frame: 'envelope', image_rotate: 5, mark_text: '', image_url: '',
        is_padding: '0', is_circle: '0'
      }, 'night')
    }
  }
}
</script>

<style lang="scss" scoped>
@import './style-kit/config.scss';
</style>
