<template>
  <div class="notice-config">
    <el-form label-width="80px" size="small" label-position="left">

      <!-- 样式设置 -->
      <div class="config-section">
        <div class="section-title">样式设置</div>

        <el-form-item label="背景颜色">
          <div class="flex-row">
            <el-color-picker v-model="form.bg_color"></el-color-picker>
            <span class="color-text">{{ form.bg_color }}</span>
          </div>
        </el-form-item>

        <el-form-item label="文字颜色">
          <div class="flex-row">
            <el-color-picker v-model="form.text_color"></el-color-picker>
            <span class="color-text">{{ form.text_color }}</span>
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

        <el-form-item label="公告图标">
          <single-image-selector v-model="form.icon"></single-image-selector>
          <div class="tip">建议尺寸 40x40 像素</div>
        </el-form-item>
      </div>

      <!-- 内容设置 -->
      <div class="config-section">
        <div class="section-title">
          <span>公告内容</span>
          <!-- 如果支持多条轮播，可以放开这个按钮 -->
          <!-- <el-button type="text" icon="el-icon-plus" @click="handleAdd">添加</el-button> -->
        </div>

        <div v-for="(item, index) in form.list" :key="index" class="notice-edit-item">
          <!-- 这里暂时只支持单条编辑，或者你可以循环 -->
          <el-form-item label="内容" label-width="50px">
            <el-input v-model="item.content" placeholder="请输入公告内容" type="textarea" :rows="2"></el-input>
          </el-form-item>

          <el-form-item label="链接" label-width="50px" style="margin-bottom: 0;">
            <link-picker v-model="item.url"></link-picker>
          </el-form-item>
        </div>

        <!-- 如果支持多条，可以用draggable或者列表 -->
      </div>

    </el-form>
  </div>
</template>

<script>
// 确保 single-image-selector 和 link-picker 已注册

export default {
  name: 'NoticeConfig',
  props: {
    value: Object
  },
  data() {
    return {
      defaultConfig: {
        is_padding: '0',
        is_circle: '0',
        bg_color: '#fff8e1',
        text_color: '#ff6600',
        icon: '',
        type: 1, // 默认滚动
        list: [
          { content: '这里是公告内容，点击编辑修改', url: '' }
        ]
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
        } else {
          // 确保 list 至少有一项
          if (!val.list || val.list.length === 0) {
            this.$set(val, 'list', [{ content: '', url: '' }]);
          }
        }
      },
      immediate: true,
      deep: true
    }
  },
  methods: {
    // 如果需要支持多条公告轮播，可以使用此方法
    handleAdd() {
      this.form.list.push({ content: '', url: '' });
    }
  }
}
</script>

<style lang="scss" scoped>
.notice-config {
  .config-section {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;

    .section-title {
      font-weight: bold;
      font-size: 13px;
      margin-bottom: 15px;
      color: #333;
      display: flex; justify-content: space-between; align-items: center;
    }
  }

  .tip { font-size: 12px; color: #999; margin-top: 5px; }
  .color-text { margin-left: 10px; font-size: 12px; color: #999; }
  .flex-row { display: flex; align-items: center; }

  .notice-edit-item {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 10px;
    border: 1px solid #eee;
  }
}
</style>
