<template>
  <div class="com-image-upload">
    <!-- 状态1：已有图片 -->
    <div class="image-preview-box" v-if="pic_url">
      <el-image
        :src="pic_url"
        fit="cover"
        class="img-item"
        :preview-src-list="[pic_url]"
      ></el-image>

      <!-- 悬停遮罩 -->
      <div class="image-mask">
        <i class="el-icon-delete" @click.stop="deleteImage"></i>
      </div>
    </div>

    <!-- 状态2：未上传，显示上传按钮 -->
    <!-- [核心] 传入 :is-mch="isMch" 确保走商家接口 -->
    <file-picker
      v-else
      :multiple="false"
      :max="1"
      :is-mch="isMch"
      @selected="chooseImage"
    >
      <div class="upload-box">
        <i class="el-icon-plus upload-icon"></i>
        <div class="upload-text" v-if="size">{{ size }}</div>
      </div>
    </file-picker>
  </div>
</template>

<script>
export default {
  name: 'ComImageUpload',
  model: {
    prop: 'url',
    event: 'input'
  },
  props: {
    url: {
      type: String,
      default: ''
    },
    size: {
      type: String,
      default: '' // 如 '750*750'
    },
    // [核心新增] 是否为商家端
    isMch: {
      type: Boolean,
      default: true
    }
  },
  data() {
    return {
      pic_url: '',
    }
  },
  watch: {
    // 监听外部传入的值变化
    url: {
      handler(val) {
        this.pic_url = val;
      },
      immediate: true
    }
  },
  methods: {
    // 删除图片
    deleteImage() {
      this.pic_url = '';
      this.$emit('input', '');
    },
    // 选择图片回调
    chooseImage(e) {
      if (e && e.length > 0) {
        const url = e[0].url;
        this.pic_url = url;
        this.$emit('input', url);
      }
    },
  }
}
</script>

<style lang="scss" scoped>
.com-image-upload {
  display: inline-block;
  vertical-align: top;
}

// 图片预览容器
.image-preview-box {
  width: 100px;
  height: 100px;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  position: relative;
  overflow: hidden;
  cursor: pointer;

  .img-item {
    width: 100%;
    height: 100%;
    display: block;
  }

  // 遮罩层
  .image-mask {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
    color: #fff;
    font-size: 20px;
  }

  &:hover .image-mask {
    opacity: 1;
  }
}

// 上传按钮容器
.upload-box {
  width: 100px;
  height: 100px;
  border: 1px dashed #c0ccda;
  border-radius: 4px;
  background-color: #fbfdff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: border-color 0.3s;
  position: relative;

  &:hover {
    border-color: #409eff;
    .upload-icon, .upload-text {
      color: #409eff;
    }
  }

  .upload-icon {
    font-size: 24px;
    color: #8c939d;
  }

  .upload-text {
    font-size: 12px;
    color: #999;
    margin-top: 6px;
    transform: scale(0.9); // 稍微缩小字体
  }
}
</style>
