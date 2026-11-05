<template>
  <div class="diy-lucky-group-config">
    <el-form label-width="90px" size="small" label-position="left">

      <div class="config-section">
        <div class="section-title">标题设置</div>

        <el-form-item label="显示标题">
          <el-switch v-model="form.show_title" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item label="标题文字" v-if="form.show_title == 1">
          <el-input v-model="form.title" placeholder="手气拼团专区"></el-input>
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">数据设置</div>

        <el-form-item label="显示数量">
          <el-input-number v-model="form.limit" :min="1" :max="50" controls-position="right"></el-input-number>
          <div class="help-text">最多显示多少个拼团商品</div>
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">布局与外观</div>

        <el-form-item label="列表样式">
          <el-radio-group v-model="form.type">
            <el-radio :label="0">网格布局</el-radio>
            <el-radio :label="1">列表布局</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="网格列数" v-if="form.type === 0">
          <el-radio-group v-model="form.columns">
            <el-radio :label="2">两列</el-radio>
            <el-radio :label="3">三列</el-radio>
          </el-radio-group>
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
      </div>

      <div class="config-section">
        <div class="section-title">内容显示</div>
        
        <el-form-item label="显示价格">
          <el-switch v-model="form.show_price" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item label="价格颜色" v-if="form.show_price == 1">
          <el-color-picker v-model="form.price_color"></el-color-picker>
        </el-form-item>
      </div>

    </el-form>
  </div>
</template>

<script>
export default {
  name: 'LuckyGroupConfig',
  props: {
    value: Object
  },
  data() {
    return {
      defaultConfig: {
        show_title: 1,
        title: '手气拼团专区',
        limit: 4,
        type: 0,
        columns: 2,
        show_price: 1,
        price_color: '#ff4544',
        is_padding: 1,
        is_circle: 1,
        background: '#ffffff'
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
        // 如果外部没有传值，或者缺少关键字段，赋予默认值
        if (!val || typeof val.limit === 'undefined') {
          this.$emit('input', JSON.parse(JSON.stringify(this.defaultConfig)));
        }
      },
      immediate: true,
      deep: true
    }
  }
}
</script>

<style lang="scss" scoped>
.diy-lucky-group-config {
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
