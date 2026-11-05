<template>
  <div class="style-config">
    <el-form label-width="76px" size="small" label-position="left">
      <div class="config-section">
        <div class="section-title">样式</div>
        <el-form-item label="卡片样式">
          <el-radio-group v-model="form.style">
            <el-radio-button label="stamp">邮票</el-radio-button>
            <el-radio-button label="card">卡片</el-radio-button>
            <el-radio-button label="circle">圆形</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="每行">
          <el-radio-group v-model="form.columns">
            <el-radio-button :label="3">3 个</el-radio-button>
            <el-radio-button :label="4">4 个</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="商品数">
          <el-switch v-model="form.show_count" :active-value="1" :inactive-value="0" />
          <el-input v-if="form.show_count == 1" v-model="form.count_suffix" placeholder="款" style="width: 70px; margin-left: 8px" />
          <div class="tip">角标自动显示分类下在售商品数,如「57 款」;单项填了角标文字以单项为准。</div>
        </el-form-item>
        <el-form-item v-if="form.style === 'stamp'" label="邮戳文字">
          <el-input v-model="form.mark_text" type="textarea" :rows="2" placeholder="盖在每张邮票右下角,每行一段,不填不盖" />
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">配色</div>
        <preset-bar :value="form" @input="replace" />
        <div style="margin-top: 12px">
          <color-fields :value="form" :fields="colorFields" />
        </div>
      </div>

      <div class="config-section">
        <div class="section-title">分类（{{ form.items.length }} 个）</div>
        <div v-for="(it, i) in form.items" :key="i" class="list-item">
          <div class="item-head">
            <span>分类 {{ i + 1 }}</span>
            <span class="item-ops">
              <a v-if="i > 0" @click="move(i)">上移</a>
              <a class="del" @click="form.items.splice(i, 1)">删除</a>
            </span>
          </div>
          <el-form-item label="分类">
            <cat-select v-model="it.cat_id" @change="c => onCat(it, c)" />
          </el-form-item>
          <el-form-item label="显示名">
            <el-input v-model="it.name" placeholder="不填用分类名" />
          </el-form-item>
          <el-form-item label="图片">
            <single-image-selector v-model="it.pic_url" size="200*200" />
            <div class="tip">不传用分类图;建议放该分类最有代表性的商品图。</div>
          </el-form-item>
          <el-form-item label="角标">
            <el-input v-model="it.badge" placeholder="不填显示商品数,如 限定 / 新品" />
          </el-form-item>
          <el-form-item label="跳转">
            <link-picker v-model="it.url" placeholder="不填进该分类的商品列表" />
          </el-form-item>
        </div>
        <el-button size="mini" icon="el-icon-plus" @click="form.items.push({ cat_id: 0, name: '', pic_url: '', badge: '', url: '' })">添加分类</el-button>
      </div>

      <div class="config-section">
        <div class="section-title">版式</div>
        <el-form-item label="左右边距">
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
import CatSelect from './style-kit/CatSelect'
import { applyPreset } from './style-kit/presets'

export default {
  name: 'CatGridConfig',
  components: { PresetBar, ColorFields, CatSelect },
  mixins: [configMixin],
  data() {
    return {
      colorFields: [{ key: 'bg_color', label: '底色' }, { key: 'bg_color2', label: '卡片' },
        { key: 'text_color', label: '名称' }, { key: 'accent_color', label: '角标' }, { key: 'sub_color', label: '邮戳' }]
    }
  },
  methods: {
    defaults() {
      return applyPreset({
        preset: 'paper', style: 'stamp', columns: 3, show_count: 1, count_suffix: '款', mark_text: '',
        bg_color: '', bg_color2: '', text_color: '', accent_color: '', sub_color: '',
        items: [], is_padding: '1'
      }, 'paper')
    },
    onCat(it, c) {
      // 预览里先显示分类名;真正的商品数、分类图由后端打开页面时补
      this.$set(it, 'cat_name', c ? c.name : '')
    },
    move(i) {
      const list = this.form.items
      list.splice(i - 1, 0, list.splice(i, 1)[0])
    }
  }
}
</script>

<style lang="scss" scoped>
@import './style-kit/config.scss';
</style>
