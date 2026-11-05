<template>
  <div class="navbar-config">
    <el-form label-width="80px" size="small" label-position="left">

      <!-- 样式设置 -->
      <div class="config-section">
        <div class="section-title">样式设置</div>

        <el-form-item label="显示样式">
          <el-radio-group v-model="form.style">
            <el-radio-button :label="0">图文</el-radio-button>
            <el-radio-button :label="1">纯文字</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="每行个数">
          <el-radio-group v-model="form.columns">
            <el-radio-button :label="3">3个</el-radio-button>
            <el-radio-button :label="4">4个</el-radio-button>
            <el-radio-button :label="5">5个</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="背景颜色">
          <div class="flex-row">
            <el-color-picker v-model="form.background" show-alpha></el-color-picker>
            <span class="color-text">{{ form.background || '默认' }}</span>
          </div>
        </el-form-item>

        <el-form-item label="文字颜色">
          <div class="flex-row">
            <el-color-picker v-model="form.color"></el-color-picker>
            <span class="color-text">{{ form.color }}</span>
          </div>
        </el-form-item>

        <el-form-item label="文字大小">
          <el-slider v-model="form.font_size" :min="10" :max="30" show-input input-size="mini"></el-slider>
        </el-form-item>

        <el-form-item label="图标大小" v-if="form.style == 0">
          <el-slider v-model="form.icon_size" :min="20" :max="100" show-input input-size="mini"></el-slider>
        </el-form-item>

        <el-form-item label="左右边距">
          <el-radio-group v-model="form.is_padding">
            <el-radio-button label="0">无</el-radio-button>
            <el-radio-button label="1">有</el-radio-button>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="背景圆角">
          <el-radio-group v-model="form.is_circle">
            <el-radio-button label="0">直角</el-radio-button>
            <el-radio-button label="1">圆角</el-radio-button>
          </el-radio-group>
        </el-form-item>
      </div>

      <!-- 导航内容设置 -->
      <div class="config-section">
        <div class="section-title">
          <span>导航按钮 ({{ form.list.length }})</span>
          <span class="tip">拖拽可排序</span>
        </div>

        <draggable v-model="form.list" :options="{ animation: 200, handle: '.drag-handle' }">
          <div v-for="(item, index) in form.list" :key="index" class="nav-edit-item">

            <div class="item-header">
              <div class="drag-handle"><i class="el-icon-s-operation"></i> 按钮 {{ index + 1 }}</div>
              <div class="delete-btn" @click="handleDelete(index)"><i class="el-icon-delete"></i></div>
            </div>

            <div class="item-body">
              <div class="flex-row">
                <!-- 图标 (仅在 style=0 时显示) -->
                <div class="icon-upload" v-if="form.style == 0">
                  <single-image-selector v-model="item.pic_url" width="50px" height="50px"></single-image-selector>
                </div>

                <!-- 内容输入 -->
                <div class="content-input">
                  <el-input v-model="item.name" placeholder="导航名称" size="small" style="margin-bottom: 5px"></el-input>
                  <link-picker v-model="item.url"></link-picker>
                </div>
              </div>
            </div>

          </div>
        </draggable>

        <el-button type="primary" plain icon="el-icon-plus" style="width: 100%; margin-top: 10px;"
          @click="handleAdd">添加导航按钮</el-button>
      </div>

    </el-form>
  </div>
</template>

<script>
import draggable from 'vuedraggable'
// 确保 single-image-selector 和 link-picker 已注册

export default {
  name: 'NavbarConfig',
  components: { draggable },
  props: {
    value: Object
  },
  data() {
    return {
      defaultConfig: {
        font_size: 14,
        icon_size: 44,
        is_padding: '0',
        is_circle: '0',
        style: 0, // 注意：原来是字符串'0'，建议统一用数字，或保持字符串
        background: '#ffffff',
        color: '#333333',
        columns: 4,
        list: [
          { pic_url: '', name: '导航1', url: '' },
          { pic_url: '', name: '导航2', url: '' },
          { pic_url: '', name: '导航3', url: '' },
          { pic_url: '', name: '导航4', url: '' }
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
        }
      },
      immediate: true
    }
  },
  methods: {
    handleAdd() {
      this.form.list.push({
        pic_url: '',
        name: '新导航',
        url: ''
      });
    },
    handleDelete(index) {
      this.form.list.splice(index, 1);
    }
  }
}
</script>

<style lang="scss" scoped>
.navbar-config {
  .config-section {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;

    .section-title {
      font-weight: bold;
      font-size: 13px;
      margin-bottom: 15px;
      color: #333;
      display: flex;
      justify-content: space-between;

      .tip {
        font-weight: normal;
        color: #999;
        font-size: 12px;
      }
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

.nav-edit-item {
  background: #f8f9fa;
  border: 1px solid #eee;
  border-radius: 4px;
  margin-bottom: 10px;

  .item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 10px;
    background: #fff;
    border-bottom: 1px solid #eee;
    border-radius: 4px 4px 0 0;

    .drag-handle {
      cursor: move;
      color: #606266;
      font-size: 12px;

      i {
        margin-right: 5px;
      }
    }

    .delete-btn {
      cursor: pointer;
      color: #F56C6C;

      &:hover {
        color: #ff0000;
      }
    }
  }

  .item-body {
    padding: 10px;

    .icon-upload {
      margin-right: 10px;
      flex-shrink: 0;
    }

    .content-input {
      flex: 1;
      display: flex;
      flex-direction: column;
    }
  }
}
</style>
