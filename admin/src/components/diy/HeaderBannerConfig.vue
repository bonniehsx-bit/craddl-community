<!-- src/components/diy/HeaderBannerConfig.vue -->
<template>
  <div class="header-banner-config">
    <el-form label-width="90px" size="small">

      <!-- 1. 基础设置 -->
      <div class="config-section">
        <div class="section-title">基础设置</div>
        <el-form-item label="搜索提示">
          <el-input v-model="form.search_placeholder" placeholder="请输入搜索框提示文字"></el-input>
        </el-form-item>
        <el-form-item label="显示定位">
          <!-- 注意：这里使用 string 类型 '1'/'0' 以兼容大部分后台逻辑 -->
          <el-switch v-model="form.show_location" active-value="1" inactive-value="0"></el-switch>
        </el-form-item>
        <el-form-item label="显示消息">
          <el-switch v-model="form.show_message" active-value="1" inactive-value="0"></el-switch>
        </el-form-item>
      </div>

      <!-- 2. 轮播图设置 -->
      <div class="config-section">
        <div class="section-title">轮播图设置 (可拖拽排序)</div>

        <!-- 拖拽组件 -->
        <draggable v-model="form.banners" :options="{ animation: 200, handle: '.drag-handle' }">
          <div v-for="(item, index) in form.banners" :key="index" class="banner-item">

            <!-- 删除按钮 -->
            <div class="delete-btn" @click="deleteBanner(index)">
              <i class="el-icon-close"></i>
            </div>

            <!-- 拖拽手柄 -->
            <div class="drag-handle">
              <i class="el-icon-s-operation"></i>
            </div>

            <!-- 图片上传区域 -->
            <div class="img-upload">
              <!-- 请确保你的项目中注册了 single-image-selector 组件 -->
              <single-image-selector v-model="item.pic_url"></single-image-selector>
            </div>

            <!-- 属性设置区域 -->
            <div class="info-input">
              <el-form-item label="背景颜色" label-width="70px" style="margin-bottom: 8px;">
                <div style="display:flex;align-items:center;">
                  <el-color-picker v-model="item.bg_color" size="mini"></el-color-picker>
                  <span style="margin-left:8px;font-size:12px;color:#999">{{ item.bg_color }}</span>
                </div>
              </el-form-item>

              <el-form-item label="链接" label-width="70px" style="margin-bottom: 0;">
                <!-- 请确保你的项目中注册了 link-picker 组件 -->
                <link-picker v-model="item.link"></link-picker>
              </el-form-item>
            </div>
          </div>
        </draggable>

        <el-button type="primary" plain size="small" style="width: 100%; margin-top: 10px;" @click="addBanner">
          + 添加轮播图
        </el-button>
      </div>

    </el-form>
  </div>
</template>

<script>
import draggable from 'vuedraggable';

export default {
  name: 'HeaderBannerConfig',
  components: { draggable },
  props: {
    value: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      // 默认数据结构
      defaultConfig: {
        search_placeholder: '搜索好物、店铺',
        show_location: '1',
        show_message: '1',
        banners: [
          { pic_url: '', link: '', bg_color: '#AC1B26' }
        ]
      },
      // 本地表单对象
      form: {
        search_placeholder: '',
        show_location: '1',
        show_message: '1',
        banners: []
      }
    };
  },
  watch: {
    // 监听父组件传入的 value (解决初始化和切换组件时的数据更新)
    value: {
      handler(newVal) {
        // 【关键修复】深度比较，如果数据内容一致，则不更新 form，防止死循环
        if (JSON.stringify(newVal) === JSON.stringify(this.form)) {
          return;
        }

        // 合并数据，确保缺少的字段被默认值填充
        this.form = {
          ...this.defaultConfig,
          ...(newVal || {})
        };

        // 额外保证 banners 数组存在
        if (!this.form.banners) {
          this.$set(this.form, 'banners', JSON.parse(JSON.stringify(this.defaultConfig.banners)));
        }
      },
      immediate: true,
      deep: true
    },

    // 监听本地 form 变化，通知父组件 (解决预览更新)
    form: {
      handler(newVal) {
        // 发送深拷贝的对象，切断引用
        this.$emit('input', JSON.parse(JSON.stringify(newVal)));
      },
      deep: true
    }
  },
  methods: {
    addBanner() {
      this.form.banners.push({
        pic_url: '',
        link: '',
        bg_color: '#AC1B26'
      });
    },
    deleteBanner(index) {
      this.form.banners.splice(index, 1);
    }
  }
};
</script>

<style lang="scss" scoped>
.header-banner-config {
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

.banner-item {
  background: #f8f9fa;
  padding: 15px 10px;
  border: 1px solid #eee;
  border-radius: 4px;
  margin-bottom: 10px;
  display: flex;
  position: relative;
  align-items: flex-start;

  &:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  }

  .delete-btn {
    position: absolute;
    right: -8px;
    top: -8px;
    width: 20px;
    height: 20px;
    background: #ff4d4f;
    color: #fff;
    border-radius: 50%;
    text-align: center;
    line-height: 20px;
    cursor: pointer;
    font-size: 12px;
    z-index: 5;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  }

  .drag-handle {
    margin-right: 8px;
    margin-top: 25px;
    cursor: move;
    color: #909399;
  }

  .img-upload {
    width: 80px;
    margin-right: 12px;
    flex-shrink: 0;
  }

  .info-input {
    flex: 1;
  }
}
</style>
