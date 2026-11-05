<template>
  <div class="style-config">
    <el-form label-width="76px" size="small" label-position="left">
      <div class="config-section">
        <div class="section-title">配色</div>
        <preset-bar :value="form" @input="replace" />
        <div style="margin-top: 12px">
          <color-fields :value="form" :fields="colorFields" />
        </div>
      </div>
      <div class="config-section">
        <div class="section-title">卖点（2–4 项）</div>
        <div v-for="(it, i) in form.items" :key="i" class="list-item">
          <div class="item-head">
            <span>卖点 {{ i + 1 }}</span>
            <span class="item-ops"><a class="del" @click="form.items.splice(i, 1)">删除</a></span>
          </div>
          <el-form-item label="图标">
            <el-input v-model="it.icon" placeholder="一个字或表情,如 ✓ 🚚" maxlength="2" style="width: 120px" />
          </el-form-item>
          <el-form-item label="文字">
            <el-input v-model="it.text" placeholder="如 正品保障" maxlength="8" show-word-limit />
          </el-form-item>
        </div>
        <el-button v-if="form.items.length < 4" size="mini" icon="el-icon-plus" @click="form.items.push({ icon: '✓', text: '' })">添加卖点</el-button>
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
  name: 'FeatureStripConfig',
  components: { PresetBar, ColorFields },
  mixins: [configMixin],
  data() {
    return {
      colorFields: [{ key: 'bg_color', label: '背景' }, { key: 'sub_color', label: '文字' },
        { key: 'accent_color', label: '图标底' }, { key: 'btn_color', label: '图标字' }]
    }
  },
  methods: {
    defaults() {
      return applyPreset({
        preset: 'paper', bg_color: '', sub_color: '', accent_color: '', btn_color: '',
        items: [{ icon: '✓', text: '正品保障' }, { icon: '✓', text: '极速发货' }, { icon: '✓', text: '售后无忧' }],
        is_padding: '0', is_circle: '0'
      }, 'paper')
    }
  }
}
</script>

<style lang="scss" scoped>
@import './style-kit/config.scss';
</style>
