<template>
  <div class="img-group-config">
    <el-form label-width="80px" size="small" label-position="left">

      <!-- 布局设置 -->
      <div class="config-section">
        <div class="section-title">布局设置</div>

        <el-form-item label="布局样式">
          <div class="layout-selector">
            <div
              class="layout-item"
              :class="{active: form.type === 1}"
              @click="form.type = 1"
              title="单图"
            >
              <div class="preview type-1"></div>
            </div>
            <div
              class="layout-item"
              :class="{active: form.type === 2}"
              @click="form.type = 2"
              title="双图"
            >
              <div class="preview type-2"></div>
            </div>
            <div
              class="layout-item"
              :class="{active: form.type === 3}"
              @click="form.type = 3"
              title="一左二右"
            >
              <div class="preview type-3"></div>
            </div>
          </div>
        </el-form-item>

        <el-form-item label="总高度">
          <el-slider v-model="form.height" :min="100" :max="1000" show-input input-size="mini"></el-slider>
        </el-form-item>

        <el-form-item label="左右边距">
          <el-radio-group v-model="form.is_padding">
            <el-radio-button label="0">无</el-radio-button>
            <el-radio-button label="1">有</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="图片圆角">
          <el-radio-group v-model="form.is_circle">
            <el-radio-button label="0">直角</el-radio-button>
            <el-radio-button label="1">圆角</el-radio-button>
          </el-radio-group>
        </el-form-item>
      </div>

      <!-- 图片内容设置 -->
      <div class="config-section">
        <div class="section-title">图片内容</div>

        <!-- 循环渲染图片配置项，根据 type 决定显示数量 -->
        <div v-for="(item, index) in form.list" :key="index" v-if="index < form.type || (form.type === 3 && index < 3)">
          <!-- 如果 type=3，显示3张图；type=2显示2张；type=1显示1张 -->
          <div class="img-setting-card">
            <div class="card-title">图片 {{ index + 1 }}</div>
            <div class="card-body">
              <el-form-item label="上传图片" label-width="60px">
                <single-image-selector v-model="item.pic_url"></single-image-selector>
              </el-form-item>

              <el-form-item label="点击事件" label-width="60px">
                <el-radio-group v-model="item.is_preview">
                  <el-radio label="0">跳转链接</el-radio>
                  <el-radio label="1">放大预览</el-radio>
                </el-radio-group>
              </el-form-item>

              <el-form-item label="链接" label-width="60px" v-if="item.is_preview == '0'" style="margin-bottom: 0;">
                <link-picker v-model="item.url"></link-picker>
              </el-form-item>
            </div>
          </div>
        </div>
      </div>

    </el-form>
  </div>
</template>

<script>
// 请确保已引入 single-image-selector 和 link-picker

export default {
  name: 'ImgGroupConfig',
  props: {
    value: Object
  },
  data() {
    return {
      defaultConfig: {
        is_padding: '0',
        is_circle: '0',
        height: 375,
        type: 1,
        // 初始化3个空对象，防止切换 layout 时数组越界
        list: [
          { is_preview: "0", pic_url: '', url: '' },
          { is_preview: "0", pic_url: '', url: '' },
          { is_preview: "0", pic_url: '', url: '' }
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
          // 数据修复：如果历史数据的 list 长度不够3个，补齐
          if (!val.list) {
            this.$set(val, 'list', JSON.parse(JSON.stringify(this.defaultConfig.list)));
          } else if (val.list.length < 3) {
            const diff = 3 - val.list.length;
            for(let i=0; i<diff; i++) {
              val.list.push({ is_preview: "0", pic_url: '', url: '' });
            }
          }
        }
      },
      immediate: true,
      deep: true
    }
  }
}
</script>

<style lang="scss" scoped>
.img-group-config {
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

/* 布局选择器样式 */
.layout-selector {
  display: flex;
  gap: 10px;

  .layout-item {
    border: 1px solid #e0e0e0;
    padding: 4px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;

    &:hover { border-color: var(--admin-accent-text); }
    &.active { border-color: var(--admin-accent-text); background: var(--admin-accent-soft); }

    .preview {
      width: 40px; height: 30px; background: #ddd; position: relative;

      &.type-1 { background: #909399; } /* 全灰 */

      &.type-2 {
        display: flex; gap: 2px;
        &::before, &::after { content:''; flex:1; background: #909399; }
      }

      &.type-3 {
        display: flex; gap: 2px;
        &::before { content:''; width: 50%; background: #909399; }
        &::after {
          content:''; width: 50%;
          background: linear-gradient(to bottom, #909399 48%, transparent 48%, transparent 52%, #909399 52%);
        }
      }
    }
  }
}

/* 图片设置卡片 */
.img-setting-card {
  background: #f8f9fa;
  border: 1px solid #eee;
  border-radius: 4px;
  margin-bottom: 15px;

  .card-title {
    padding: 8px 10px;
    background: #f0f2f5;
    font-size: 12px;
    font-weight: bold;
    border-bottom: 1px solid #eee;
  }

  .card-body {
    padding: 10px;
  }
}
</style>
