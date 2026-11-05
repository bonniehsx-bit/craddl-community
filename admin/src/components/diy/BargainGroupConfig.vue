<template>
  <div class="diy-bargain-group-config">
    <el-form label-width="90px" size="small" label-position="left">

      <div class="config-section">
        <div class="section-title">标题设置</div>

        <el-form-item label="显示标题">
          <el-switch v-model="form.show_title" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item label="标题文字" v-if="form.show_title == 1">
          <el-input v-model="form.title" :placeholder="defaultTitle"></el-input>
          <div class="help-text">留空显示「{{ defaultTitle }}」，跟着名词配置里的活动名走</div>
        </el-form-item>

        <el-form-item label="显示更多" v-if="form.show_title == 1">
          <el-switch v-model="form.show_more" :active-value="1" :inactive-value="0"></el-switch>
          <div class="help-text">右上角「更多」进入{{ bn }}专区</div>
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">数据设置</div>

        <el-form-item label="显示数量">
          <el-input-number v-model="form.limit" :min="1" :max="20" controls-position="right"></el-input-number>
          <div class="help-text">按活动排序取正在进行的{{ bn }}商品；没有进行中的活动时整块不显示</div>
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">布局与外观</div>

        <el-form-item label="列表样式">
          <el-radio-group v-model="form.type">
            <el-radio :label="0">列表（左图右文）</el-radio>
            <el-radio :label="1">两列网格</el-radio>
            <el-radio :label="2">大图主推 + 两列</el-radio>
          </el-radio-group>
          <div class="help-text" v-if="form.type === 2">第一件做大图主推，其余两列；头部换成主题色专区头，带本场倒计时</div>
        </el-form-item>

        <el-form-item label="本场倒计时" v-if="form.type === 2">
          <el-switch v-model="form.show_countdown" :active-value="1" :inactive-value="0"></el-switch>
          <div class="help-text">按列表里最早结束的活动倒数；关掉标题时不显示</div>
        </el-form-item>

        <el-form-item label="左右边距">
          <el-switch v-model="form.is_padding" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item label="圆角卡片">
          <el-switch v-model="form.is_circle" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item label="卡片背景色">
          <el-color-picker v-model="form.background" show-alpha></el-color-picker>
        </el-form-item>

        <el-form-item label="强调色">
          <el-color-picker v-model="form.main_color"></el-color-picker>
          <div class="help-text">底价和按钮的颜色；不选就跟商城主题色</div>
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">内容显示</div>

        <el-form-item label="显示原价">
          <el-switch v-model="form.show_origin" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item label="显示人数">
          <el-switch v-model="form.show_success" :active-value="1" :inactive-value="0"></el-switch>
          <div class="help-text">「已有 N 人砍成」，没人砍成时不显示</div>
        </el-form-item>

        <el-form-item label="砍成头像" v-if="form.type === 2 && form.show_success == 1">
          <el-switch v-model="form.show_avatar" :active-value="1" :inactive-value="0"></el-switch>
          <div class="help-text">主推大图上显示最近几位砍成的人的头像</div>
        </el-form-item>

        <el-form-item label="显示库存">
          <el-switch v-model="form.show_stock" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>
      </div>

    </el-form>
  </div>
</template>

<script>
// 砍价专区装修组件的默认配置;C 端 tui-bargain-group 按同一套字段渲染
export const BARGAIN_GROUP_DEFAULT = {
  show_title: 1,
  title: '',
  show_more: 1,
  limit: 4,
  type: 0,
  is_padding: 1,
  is_circle: 1,
  background: '#ffffff',
  main_color: '',
  show_origin: 1,
  show_success: 1,
  show_stock: 1,
  show_countdown: 1,
  show_avatar: 1
}

export default {
  name: 'BargainGroupConfig',
  props: {
    value: Object
  },
  computed: {
    // 活动名走词库 bargain.name(商户可能改叫「助力价」),没配回落「砍价」
    bn() {
      const w = this.$noun ? this.$noun('bargain.name') : ''
      return w && w !== 'bargain.name' ? w : '砍价'
    },
    defaultTitle() {
      return this.bn + '专区'
    },
    form: {
      get() {
        return this.value || JSON.parse(JSON.stringify(BARGAIN_GROUP_DEFAULT))
      },
      set(val) {
        this.$emit('input', val)
      }
    }
  },
  watch: {
    value: {
      handler(val) {
        // 新拖进来的组件 data 为 null:补默认值
        if (!val || typeof val.limit === 'undefined') {
          this.$emit('input', JSON.parse(JSON.stringify(BARGAIN_GROUP_DEFAULT)))
          return
        }
        // 老页面里存的组件缺后来加的字段(倒计时 / 头像):补上默认值,
        // 否则开关绑的是不存在的属性,Vue 2 不响应,点了没反应
        if (Object.keys(BARGAIN_GROUP_DEFAULT).some(k => !(k in val))) {
          this.$emit('input', Object.assign({}, BARGAIN_GROUP_DEFAULT, val))
        }
      },
      immediate: true,
      deep: true
    }
  }
}
</script>

<style lang="scss" scoped>
.diy-bargain-group-config {
  .config-section {
    margin-bottom: 25px;
    padding-bottom: 25px;
    border-bottom: 1px solid #ebeef5;

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
        left: 0;
        top: 2px;
        bottom: 2px;
        width: 3px;
        background-color: var(--admin-accent-text);
        border-radius: 2px;
      }
    }

    .help-text {
      color: #909399;
      font-size: 12px;
      line-height: 1.4;
      margin-top: 5px;
    }
  }
}
</style>
