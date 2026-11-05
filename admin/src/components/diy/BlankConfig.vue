<template>
  <div class="blank-config">
    <el-form label-width="80px" size="small" label-position="left">

      <div class="config-section">
        <div class="section-title">样式设置</div>

        <el-form-item label="占位高度">
          <div class="flex-row">
            <el-slider
              v-model="form.height"
              :min="1"
              :max="300"
              show-input
              input-size="mini"
              style="flex: 1"
            ></el-slider>
          </div>
          <div class="tip">单位：像素 (px)</div>
        </el-form-item>

        <el-form-item label="背景颜色">
          <div class="flex-row">
            <el-color-picker v-model="form.background" show-alpha></el-color-picker>
            <span class="color-text">{{ form.background || '透明' }}</span>
          </div>
        </el-form-item>
      </div>

    </el-form>
  </div>
</template>

<script>
export default {
  name: 'BlankConfig',
  props: {
    value: Object
  },
  data() {
    return {
      defaultConfig: {
        height: 20,
        background: '#ffffff' // 默认白色，或者改为 '' 代表透明
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

<style lang="scss" scoped>
.blank-config {
  .config-section {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;

    .section-title {
      font-weight: bold;
      font-size: 13px;
      margin-bottom: 15px;
      color: #333;
    }
  }

  .tip {
    font-size: 12px;
    color: #999;
    line-height: 1.2;
    margin-top: -5px;
  }

  .flex-row {
    display: flex;
    align-items: center;
  }

  .color-text {
    margin-left: 10px;
    font-size: 12px;
    color: #999;
  }
}
</style>
