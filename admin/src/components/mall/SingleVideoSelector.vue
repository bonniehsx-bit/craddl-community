<template>
  <div>
    <!-- 视频展示区 -->
    <div flex="main:center cross:center" class="add-video-btn flex-x-center flex-y-center" v-if="video_url" style="position: relative;">
      
      <!-- 静音+不自动播放的视频略缩窗，点击可以用新标签页打开原视频 -->
      <video :src="video_url" style="width: 100%;height: 100%; object-fit: cover;" controls></video>
      
      <!-- 移除按钮 -->
      <span class="flex-x-center flex-y-center pic-del" @click="deleteVideo">
        <i class="el-icon-close"></i>
      </span>
    </div>

    <!-- 唤出视频选择器 -->
    <file-picker type="video" :multiple='false' :max="1" @selected="chooseVideo" v-if="video_url == ''||!video_url">
      <div flex="main:center cross:center" style="position: relative;" class="add-video-btn flex-x-center flex-y-center">
        <i class="el-icon-video-camera" style="font-size: 30px; color: #909399;"></i>
        <div style="position: absolute; bottom: 10px; font-size: 12px; color: #909399; width: 100%; text-align: center;">
          添加视频
        </div>
        <span style="position: absolute;top: 2px;right: 4px;font-size: 10px;color: #909399;" v-if="size">{{ size }}</span>
      </div>
    </file-picker>
  </div>
</template>

<script>
export default {
  name: 'SingleVideoSelector',
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
      default: '限制 50MB'
    }
  },
  data() {
    return {
      video_url: '',
    }
  },
  mounted() {
    this.video_url = this.url;
  },
  watch: {
    url(newVal, oldVal) {
      this.video_url = newVal;
    },
    video_url(newVal, oldVal) {
      if (newVal !== oldVal) {
        this.video_url = newVal;
      }
      this.$emit('input', newVal);
    },
  },

  methods: {
    deleteVideo() {
      this.video_url = '';
    },
    chooseVideo(e) {
      if (e && e[0]) {
        this.video_url = e[0].url;
      }
    },
  }
}
</script>

<style scoped>
.add-video-btn {
  width: 120px;
  height: 120px;
  border: 1px dashed #d9d9d9;
  border-radius: 6px;
  cursor: pointer;
  background-color: #f7f9fa;
  overflow: hidden;
  transition: border-color .2s;
}

.add-video-btn:hover {
  border-color: var(--admin-accent-text);
}

.pic-del {
  position: absolute;
  top: 0;
  right: 0;
  width: 22px;
  height: 22px;
  background-color: rgba(0, 0, 0, 0.6);
  color: #fff;
  cursor: pointer;
  z-index: 2;
  border-bottom-left-radius: 6px;
  transition: background-color 0.2s;
}
.pic-del:hover {
  background-color: rgba(245, 108, 108, 0.9);
}
</style>
