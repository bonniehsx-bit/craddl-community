<template>
  <div class="search-bar-config">
    <el-form label-width="80px" size="small" label-position="left">

      <div class="config-section">
        <div class="section-title">样式设置</div>

        <el-form-item label="背景颜色">
          <div class="flex-row">
            <el-color-picker v-model="form.background"></el-color-picker>
            <span class="color-text">{{ form.background || '默认' }}</span>
          </div>
        </el-form-item>

        <el-form-item label="左右边距">
          <el-radio-group v-model="form.is_padding">
            <el-radio-button label="0">无</el-radio-button>
            <el-radio-button label="1">有</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="框体圆角">
          <el-radio-group v-model="form.is_circle">
            <el-radio-button label="0">直角</el-radio-button>
            <el-radio-button label="1">圆角</el-radio-button>
          </el-radio-group>
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">内容设置</div>

        <el-form-item label="提示文字">
          <el-input v-model="form.tips" placeholder="例如：输入内容搜索"></el-input>
        </el-form-item>

        <el-form-item label="跳转链接">
          <link-picker v-model="form.url"></link-picker>
        </el-form-item>
      </div>

    </el-form>
  </div>
</template>

<script>
// 确保 link-picker 已注册

export default {
  name: 'SearchBarConfig',
  props: {
    value: Object
  },
  data() {
    return {
      defaultConfig: {
        is_padding: '0',
        is_circle: '0',
        background: '#f4f4f4', // 默认浅灰色更像搜索框
        url: '',
        tips: '输入内容进行搜索'
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
.search-bar-config {
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
</style>
