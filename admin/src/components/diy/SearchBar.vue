<template>
  <!-- 外层：控制左右边距 -->
  <div class="diy-search-bar" :style="outerStyle">
    <!-- 内层：实际的搜索框主体 (背景、圆角) -->
    <div class="search-inner" :style="innerStyle">
      <div class="content-box">
        <i class="el-icon-search search-icon"></i>
        <span class="placeholder-text">{{ safeData.tips || '搜索' }}</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'SearchBar',
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
    // 外层样式：控制边距
    outerStyle() {
      const padding = this.safeData.is_padding == 1 ? '0 10px' : '0';
      return {
        padding: padding,
        backgroundColor: 'transparent' // 默认底色透明
      };
    },
    // 内层样式：控制背景色、圆角
    innerStyle() {
      const radius = this.safeData.is_circle == 1 ? '30px' : '0'; // 圆角设为高度的一半
      const bg = this.safeData.background || '#f4f4f4'; // 默认浅灰背景
      return {
        borderRadius: radius,
        backgroundColor: bg
      };
    }
  }
}
</script>

<style lang="scss" scoped>
.diy-search-bar {
  padding: 10px 0;
  /* 上下留一点间距 */
  overflow: hidden;
}

.search-inner {
  height: 40px;
  /* 搜索框标准高度 */
  display: flex;
  align-items: center;
  padding: 0 15px;
  /* 如果没有设置背景色，给一个默认边框让它可见 */
  // border: 1px solid transparent;
}

.content-box {
  display: flex;
  align-items: center;
  width: 100%;
  color: #999;

  .search-icon {
    font-size: 16px;
    margin-right: 8px;
  }

  .placeholder-text {
    font-size: 14px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}
</style>
