<template>
  <div class="diy-notice" :style="outerStyle">
    <div class="notice-inner" :style="innerStyle">

      <!-- 左侧图标 -->
      <div class="icon-box">
        <el-image :src="safeData.icon || defaultIcon" fit="contain" style="width: 100%; height: 100%;">
          <div slot="error" class="image-slot"><i class="el-icon-bell"></i></div>
        </el-image>
      </div>

      <!-- 滚动内容区 -->
      <div class="scroll-wrapper">
        <div class="scroll-content animation" :style="{ color: safeData.text_color }">
          {{ noticeContent }}
        </div>
      </div>

      <!-- 箭头 (可选，视设计稿而定，增加视觉完整性) -->
      <div class="right-arrow" :style="{ color: safeData.text_color }">
        <i class="el-icon-arrow-right"></i>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'Notice',
  props: {
    data: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      // 默认公告图标，如果没有配置图标，可以显示这个
      defaultIcon: ''
    }
  },
  computed: {
    safeData() {
      return this.data || {};
    },
    // 获取公告内容，默认只取第一条
    noticeContent() {
      if (this.safeData.list && this.safeData.list.length > 0) {
        return this.safeData.list[0].content || '请配置公告内容';
      }
      return '请配置公告内容';
    },
    // 外层样式：控制边距
    outerStyle() {
      const padding = this.safeData.is_padding == 1 ? '0 10px' : '0';
      return {
        padding: padding,
        backgroundColor: '#fff' // 默认背景
      };
    },
    // 内层样式：控制圆角、背景色
    innerStyle() {
      const radius = this.safeData.is_circle == 1 ? '10px' : '0';
      return {
        borderRadius: radius,
        backgroundColor: this.safeData.bg_color || '#fff8e1',
        overflow: 'hidden'
      };
    }
  }
}
</script>

<style lang="scss" scoped>
.diy-notice {
  overflow: hidden;
}

.notice-inner {
  height: 40px;
  display: flex;
  align-items: center;
  padding: 0 10px;
}

.icon-box {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  margin-right: 10px;

  .image-slot {
    width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
    color: #f56c6c; font-size: 16px;
  }
}

.scroll-wrapper {
  flex: 1;
  height: 100%;
  overflow: hidden;
  position: relative;
  display: flex;
  align-items: center;
}

.scroll-content {
  white-space: nowrap;
  font-size: 13px;
  line-height: 40px;
}

/* 简单的滚动动画模拟 */
.animation {
  animation: rolling 8s linear infinite;
  padding-left: 100%; /* 从最右侧开始 */
}

@keyframes rolling {
  0% { transform: translateX(0); }
  100% { transform: translateX(-200%); } /* 滚远一点 */
}

.right-arrow {
  margin-left: 5px;
  font-size: 12px;
  opacity: 0.6;
}
</style>
