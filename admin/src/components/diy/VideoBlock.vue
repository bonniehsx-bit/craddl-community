<template>
  <div class="diy-video" :style="outerStyle">
    <div class="video-inner" :style="innerStyle">

      <!-- 标题区域 -->
      <div v-if="safeData.title" class="video-title" :style="{ color: safeData.title_color }">
        {{ safeData.title }}
      </div>

      <!-- 视频/封面区域 -->
      <div class="video-content" :style="{ height: heightVal + 'px' }">

        <!-- 封面图 -->
        <el-image v-if="safeData.cover_pic" :src="safeData.cover_pic" fit="cover"
          style="width: 100%; height: 100%; display: block;">
          <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
        </el-image>

        <!-- 空状态占位 -->
        <div v-else class="empty-video">
          <i class="el-icon-video-camera"></i>
        </div>

        <!-- 播放按钮遮罩 (仅视觉效果) -->
        <div class="play-mask">
          <div class="play-btn">
            <i class="el-icon-video-play"></i>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'VideoBlock',
  props: {
    data: {
      type: Object,
      default: () => ({})
    }
  },
  computed: {
    safeData() {
      return this.data || {};
    },
    heightVal() {
      // 默认高度 200px (原代码默认值较小，建议给一个合理的默认预览高度)
      return parseInt(this.safeData.height) || 200;
    },
    // 外层：控制边距
    outerStyle() {
      const padding = this.safeData.is_padding == 1 ? '0 10px' : '0';
      return {
        padding: padding,
        backgroundColor: 'transparent' // 默认背景透明
      };
    },
    // 内层：控制圆角和背景
    innerStyle() {
      const radius = this.safeData.is_circle == 1 ? '10px' : '0';
      return {
        borderRadius: radius,
        backgroundColor: this.safeData.background || '#ffffff',
        overflow: 'hidden' // 确保视频内容不溢出圆角
      };
    }
  }
}
</script>

<style lang="scss" scoped>
.diy-video {
  overflow: hidden;
}

.video-inner {
  display: flex;
  flex-direction: column;
}

.video-title {
  padding: 10px 10px 5px 10px;
  font-weight: bold;
  font-size: 15px;
  line-height: 1.4;
}

.video-content {
  width: 100%;
  position: relative;
  background: #000;

  .empty-video {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f4f4f4;
    color: #909399;
    font-size: 40px;
  }

  .image-slot {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f5f7fa;
    color: #909399;
  }

  .play-mask {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;

    .play-btn {
      font-size: 50px;
      color: rgba(255, 255, 255, 0.9);
      filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.5));
    }
  }
}
</style>
