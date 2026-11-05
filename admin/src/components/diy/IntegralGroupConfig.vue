<template>
  <div class="integral-group-config">
    <el-form label-width="90px" size="small" label-position="left">

      <el-divider>数据设置</el-divider>
      <el-form-item label="加载数量">
        <el-input-number v-model="form.limit" :min="1" :max="20" label="描述文字"></el-input-number>
        <div class="tip">小程序端默认加载前 N 个最新积分商品</div>
      </el-form-item>

      <el-form-item label="显示标题">
        <el-switch v-model="form.show_title" :active-value="1" :inactive-value="0"></el-switch>
      </el-form-item>

      <el-form-item label="标题文字" v-if="form.show_title == 1">
        <el-input v-model="form.title" placeholder="积分商城专区"></el-input>
      </el-form-item>

      <el-divider>布局样式</el-divider>
      <el-form-item label="显示模式">
        <el-radio-group v-model="form.type">
          <el-radio :label="0">网格</el-radio>
          <el-radio :label="1">列表</el-radio>
        </el-radio-group>
      </el-form-item>

      <el-form-item label="每行数量" v-if="form.type === 0">
        <el-radio-group v-model="form.columns">
          <el-radio :label="2">2个</el-radio>
          <el-radio :label="3">3个</el-radio>
        </el-radio-group>
      </el-form-item>

      <el-divider>组件外观</el-divider>
      <el-form-item label="左右边距">
        <el-switch v-model="form.is_padding" :active-value="1" :inactive-value="0"></el-switch>
      </el-form-item>

      <el-form-item label="圆角设置">
        <el-switch v-model="form.is_circle" :active-value="1" :inactive-value="0"></el-switch>
      </el-form-item>

      <el-form-item label="背景颜色">
        <el-color-picker v-model="form.background" show-alpha></el-color-picker>
      </el-form-item>

      <el-form-item label="显示价格">
        <el-switch v-model="form.show_price" :active-value="1" :inactive-value="0"></el-switch>
      </el-form-item>

      <el-form-item label="价格颜色" v-if="form.show_price == 1">
        <el-color-picker v-model="form.price_color"></el-color-picker>
      </el-form-item>

    </el-form>
  </div>
</template>

<script>
export default {
  name: 'IntegralGroupConfig',
  props: {
    value: Object
  },
  data() {
    return {
      defaultConfig: {
        show_title: 1,
        title: '积分商城专区',
        limit: 4,
        type: 0,
        columns: 2,
        is_padding: 1,
        is_circle: 1,
        background: '#ffffff',
        show_price: 1,
        price_color: '#ff4544'
      }
    }
  },
  computed: {
    form: {
      get() {
        return this.value || JSON.parse(JSON.stringify(this.defaultConfig));
      },
      set(val) {
        this.$emit('input', val);
      }
    }
  },
  watch: {
    value: {
      handler(val) {
        if (!val) {
          this.$emit('input', JSON.parse(JSON.stringify(this.defaultConfig)));
        }
      },
      immediate: true
    }
  }
}
</script>

<style scoped>
.tip {
  font-size: 12px;
  color: #999;
}
</style>
