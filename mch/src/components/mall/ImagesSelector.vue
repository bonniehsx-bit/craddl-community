<template>
  <div class="com-attachment">
    <div class="image-list">
      <!-- 1. 已选图片列表 -->
      <draggable v-model="pic_list" class="flex-row" @end="emitInput" v-if="pic_list && pic_list.length">
        <div class="image-item" v-for="(item, index) in pic_list" :key="index">
          <el-image
            :src="item"
            fit="cover"
            class="img-preview"
            :preview-src-list="[item]"
          ></el-image>

          <!-- 删除遮罩 -->
          <div class="image-mask">
            <i class="el-icon-delete" @click.stop="deletePic(index)"></i>
          </div>
        </div>
      </draggable>

      <!-- 2. 上传按钮 -->
      <div class="upload-btn-wrap" v-if="pic_list.length < count">
        <file-picker
          :max="remainingCount"
          :multiple="true"
          :is-mch="isMch"
          @selected="chooseImage"
        >
          <div class="upload-box">
            <i class="el-icon-plus upload-icon"></i>
            <div class="upload-text" v-if="size">{{ size }}</div>
          </div>
        </file-picker>
      </div>
    </div>

    <!-- 提示文案 -->
    <div class="upload-tip" v-if="count > 0">
      已选 {{ pic_list.length }} / {{ count }} 张
    </div>
  </div>
</template>

<script>

import draggable from 'vuedraggable' // 可选：如果项目中安装了 vuedraggable，可以支持拖拽排序

export default {
  name: 'ComAttachment',
  components: {

    draggable // 如果没有安装 vuedraggable，请移除组件引用和 template 中的 <draggable> 标签，改回 div
  },
  model: {
    prop: 'list',
    event: 'input'
  },
  props: {
    // 最大上传数量
    count: {
      type: Number,
      default: 9
    },
    // 尺寸提示文字
    size: {
      type: String,
      default: ''
    },
    // 数据绑定
    list: {
      type: Array,
      default: () => [],
    },
    // [核心] 是否为商家端 (透传给 file-picker)
    isMch: {
      type: Boolean,
      default: true
    }
  },
  data() {
    return {
      pic_list: [],
    }
  },
  computed: {
    // 计算剩余可上传数量，传给 file-picker 限制选择
    remainingCount() {
      return this.count - this.pic_list.length;
    }
  },
  watch: {
    list: {
      handler(val) {
        // 确保是数组，防止父组件传 null 报错
        this.pic_list = Array.isArray(val) ? [...val] : [];
      },
      immediate: true,
      deep: true
    }
  },
  methods: {
    // 触发更新
    emitInput() {
      this.$emit('input', this.pic_list);
    },

    // 删除图片
    deletePic(index) {
      this.pic_list.splice(index, 1);
      this.emitInput();
    },

    // 选择图片回调
    chooseImage(files) {
      if (!files || !files.length) return;

      const newUrls = files.map(item => item.url);

      // [优化] 防止超选：截取剩余额度
      const canAddCount = this.count - this.pic_list.length;
      if (canAddCount <= 0) return;

      const toAdd = newUrls.slice(0, canAddCount);

      this.pic_list = [...this.pic_list, ...toAdd];
      this.emitInput();
    }
  }
}
</script>

<style lang="scss" scoped>
.com-attachment {
  width: 100%;
}

.image-list {
  display: flex;
  flex-wrap: wrap;
  gap: 10px; // 图片之间的间距
}

.image-item {
  width: 100px;
  height: 100px;
  position: relative;
  border-radius: 4px;
  overflow: hidden;
  border: 1px solid #e0e0e0;

  .img-preview {
    width: 100%;
    height: 100%;
    display: block;
  }

  // 悬停显示删除遮罩
  .image-mask {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
    cursor: pointer;

    i {
      color: #fff;
      font-size: 20px;
    }
  }

  &:hover .image-mask {
    opacity: 1;
  }
}

.upload-btn-wrap {
  width: 100px;
  height: 100px;
}

.upload-box {
  width: 100px;
  height: 100px;
  border: 1px dashed #c0ccda;
  border-radius: 4px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  background-color: #fbfdff;
  transition: border-color 0.3s;
  position: relative;

  &:hover {
    border-color: #409eff;
    color: #409eff;
  }

  .upload-icon {
    font-size: 24px;
    color: #8c939d;
  }

  .upload-text {
    font-size: 12px;
    color: #999;
    margin-top: 5px;
    transform: scale(0.9); // 缩小一点字体
  }
}

.upload-tip {
  font-size: 12px;
  color: #909399;
  margin-top: 5px;
}
</style>
