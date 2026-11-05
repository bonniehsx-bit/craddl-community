<template>
  <div class="diy-massage-shop-list-config">
    <el-form label-width="90px" size="small" label-position="left">

      <div class="config-section">
        <div class="section-title">标题设置</div>
        <el-form-item label="主标题">
          <el-input v-model="form.title" placeholder="附近艾灸门店" maxlength="20" show-word-limit></el-input>
        </el-form-item>
        <el-form-item label="副标题">
          <el-input v-model="form.subtitle" placeholder="选填，展示在主标题下方" maxlength="30" show-word-limit></el-input>
        </el-form-item>
        <el-form-item label="查看更多">
          <el-switch v-model="form.show_more" :active-value="1" :inactive-value="0"></el-switch>
          <div class="help-text">关闭后右上角"查看更多"入口隐藏</div>
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">数据设置</div>
        <el-form-item label="显示数量">
          <el-input-number v-model="form.limit" :min="1" :max="20" controls-position="right"></el-input-number>
          <div class="help-text">按距离/评分自动展示该数量的门店</div>
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">布局样式</div>
        <el-form-item label="样式">
          <el-radio-group v-model="form.style">
            <el-radio label="list">列表（推荐）</el-radio>
            <el-radio label="grid">网格</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="左右边距">
          <el-radio-group v-model="form.is_padding">
            <el-radio-button :label="0">无边距</el-radio-button>
            <el-radio-button :label="1">有边距</el-radio-button>
          </el-radio-group>
          <div class="help-text">关闭后组件铺满屏幕宽度（贴边）</div>
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">内容显示</div>
        <el-form-item label="显示距离">
          <el-switch v-model="form.show_distance" :active-value="1" :inactive-value="0"></el-switch>
          <div class="help-text">用户授权定位后才显示具体距离</div>
        </el-form-item>
        <el-form-item label="显示评分">
          <el-switch v-model="form.show_rating" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">配色（留空走默认）</div>
        <el-form-item label="卡片背景">
          <div class="color-row">
            <el-color-picker v-model="form.bg_color" show-alpha></el-color-picker>
            <span class="color-text">{{ form.bg_color || '默认 #fff' }}</span>
            <el-button type="text" size="mini" v-if="form.bg_color" @click="form.bg_color = ''">清除</el-button>
          </div>
        </el-form-item>
        <el-form-item label="标题颜色">
          <div class="color-row">
            <el-color-picker v-model="form.title_color"></el-color-picker>
            <span class="color-text">{{ form.title_color || '默认 #0F172A' }}</span>
            <el-button type="text" size="mini" v-if="form.title_color" @click="form.title_color = ''">清除</el-button>
          </div>
        </el-form-item>
        <el-form-item label="子项背景">
          <div class="color-row">
            <el-color-picker v-model="form.item_bg_color" show-alpha></el-color-picker>
            <span class="color-text">{{ form.item_bg_color || '默认 #F8FAFC' }}</span>
            <el-button type="text" size="mini" v-if="form.item_bg_color" @click="form.item_bg_color = ''">清除</el-button>
          </div>
        </el-form-item>
        <el-form-item label="强调色">
          <div class="color-row">
            <el-color-picker v-model="form.accent_color"></el-color-picker>
            <span class="color-text">{{ form.accent_color || '默认主题色' }}</span>
            <el-button type="text" size="mini" v-if="form.accent_color" @click="form.accent_color = ''">清除</el-button>
          </div>
          <div class="help-text">用于距离/价格/认证标签等高亮元素</div>
        </el-form-item>
      </div>

    </el-form>
  </div>
</template>

<script>
export default {
  name: 'MassageShopListConfig',
  props: { value: Object },
  data() {
    return {
      defaultConfig: {
        title: '附近艾灸门店',
        subtitle: '认准平台正品门店',
        limit: 5,
        show_distance: 1,
        show_rating: 1,
        show_more: 1,
        style: 'list',
        is_padding: 1,
        bg_color: '',
        title_color: '',
        item_bg_color: '',
        accent_color: '',
      },
    }
  },
  computed: {
    form: {
      get() { return this.value || JSON.parse(JSON.stringify(this.defaultConfig)) },
      set(val) { this.$emit('input', val) },
    },
  },
  watch: {
    value: {
      handler(val) {
        // 老版本数据 / 拖入后 data=null 时回填默认值
        if (!val || typeof val.limit === 'undefined') {
          this.$emit('input', JSON.parse(JSON.stringify(this.defaultConfig)))
        }
      },
      immediate: true,
      deep: true,
    },
  },
}
</script>

<style lang="scss" scoped>
.diy-massage-shop-list-config {
  .config-section {
    margin-bottom: 25px;
    padding-bottom: 25px;
    border-bottom: 1px solid #ebeef5;
    &:last-child { border-bottom: none; padding-bottom: 0; }

    .section-title {
      font-weight: bold;
      font-size: 14px;
      margin-bottom: 18px;
      color: #303133;
      padding-left: 10px;
      position: relative;

      &::before {
        content: '';
        position: absolute;
        left: 0; top: 2px; bottom: 2px;
        width: 3px;
        background: var(--admin-accent-text);
        border-radius: 2px;
      }
    }
  }

  .help-text {
    font-size: 12px;
    color: #c0c4cc;
    line-height: 1.4;
    margin-top: 2px;
  }
  .color-row {
    display: flex;
    align-items: center;
    gap: 10px;
    .color-text { font-size: 12px; color: #909399; min-width: 110px; }
  }
}
</style>
