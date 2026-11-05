<template>
  <div class="title-bar-config">
    <el-form label-width="80px" size="small" label-position="left">

      <div class="config-section">
        <div class="section-title">样式设置</div>

        <el-form-item label="标题版式">
          <el-radio-group v-model="form.style_type">
            <el-radio :label="0">左对齐</el-radio>
            <el-radio :label="1">加左竖线</el-radio>
            <el-radio :label="2">居中对齐</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="前缀颜色" v-if="form.style_type == 1">
          <div class="flex-row">
            <el-color-picker v-model="form.prefix_color"></el-color-picker>
            <span class="color-text">{{ form.prefix_color }}</span>
          </div>
        </el-form-item>

        <el-form-item label="标题字号">
          <el-slider v-model="form.font_size" :min="12" :max="30" style="width: 200px; padding-left: 10px;"></el-slider>
        </el-form-item>

        <el-form-item label="标题加粗">
          <el-switch v-model="form.font_weight" :active-value="1" :inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item label="背景颜色">
          <div class="flex-row">
            <el-color-picker v-model="form.background"></el-color-picker>
            <span class="color-text">{{ form.background }}</span>
          </div>
        </el-form-item>

        <el-form-item label="标题颜色">
          <div class="flex-row">
            <el-color-picker v-model="form.title_color"></el-color-picker>
            <span class="color-text">{{ form.title_color }}</span>
          </div>
        </el-form-item>

        <el-form-item label="副标颜色">
          <div class="flex-row">
            <el-color-picker v-model="form.sub_title_color"></el-color-picker>
            <span class="color-text">{{ form.sub_title_color }}</span>
          </div>
        </el-form-item>

        <el-form-item label="左右边距">
          <el-radio-group v-model="form.is_padding">
            <el-radio-button label="0">无</el-radio-button>
            <el-radio-button label="1">有</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="圆角设置">
          <el-radio-group v-model="form.is_circle">
            <el-radio-button label="0">直角</el-radio-button>
            <el-radio-button label="1">圆角</el-radio-button>
          </el-radio-group>
        </el-form-item>
      </div>

      <div class="config-section">
        <div class="section-title">内容设置</div>

        <el-form-item label="主标题">
          <el-input v-model="form.title" placeholder="请输入标题"></el-input>
        </el-form-item>

        <el-form-item label="副标题">
          <el-input v-model="form.sub_title" placeholder="请输入副标题"></el-input>
        </el-form-item>

        <el-form-item label="显示箭头" v-if="form.style_type != 2">
          <el-switch v-model="form.show_sub_icon" :active-value="1" :inactive-value="0"></el-switch>
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
  name: 'TitleBarConfig',
  props: {
    value: Object
  },
  data() {
    return {
      defaultConfig: {
        is_padding: '0',
        is_circle: '0',
        style_type: 1,
        font_size: 16,
        font_weight: 1,
        prefix_color: '#ff4544',
        show_sub_icon: 1,
        background: '#ffffff',
        title_color: '#000000',
        sub_title_color: '#999999',
        title: '标题栏',
        sub_title: '查看更多',
        url: ''
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
.title-bar-config {
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
