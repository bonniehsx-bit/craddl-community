<template>
  <div class="diy-title-bar" :style="outerStyle">
    <div class="title-bar-inner" :style="innerStyle">

      <!-- 左侧部分（主标题） -->
      <div class="left-side" :class="{ 'is-center': safeData.style_type === 2 }">
        <!-- 左侧竖线 -->
        <div class="prefix-line" v-if="safeData.style_type === 1"
          :style="{ backgroundColor: safeData.prefix_color || '#ff4544' }"></div>

        <div class="main-title" :style="titleStyle">
          {{ safeData.title || '标题栏' }}
        </div>
      </div>

      <!-- 右侧部分（副标题） -->
      <div class="right-side" :style="{ color: safeData.sub_title_color || '#999999' }"
        v-if="safeData.style_type !== 2">
        <span class="sub-text" v-if="safeData.sub_title">{{ safeData.sub_title }}</span>
        <i class="el-icon-arrow-right icon"
          v-if="safeData.show_sub_icon === 1 || safeData.show_sub_icon === undefined"></i>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'TitleBar',
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
    // 外层：控制边距
    outerStyle() {
      const padding = this.safeData.is_padding == 1 ? '0 10px' : '0';
      return {
        padding: padding,
        backgroundColor: '#f5f7fa',
        boxSizing: 'border-box',
        width: '100%'
      };
    },
    // 内层：控制圆角、背景色、高度
    innerStyle() {
      const radius = this.safeData.is_circle == 1 ? '10px' : '0';
      return {
        borderRadius: radius,
        backgroundColor: this.safeData.background || '#ffffff',
      };
    },
    titleStyle() {
      return {
        color: this.safeData.title_color || '#000000',
        fontSize: (this.safeData.font_size || 16) + 'px',
        fontWeight: this.safeData.font_weight == 1 ? 'bold' : 'normal'
      };
    }
  }
}
</script>

<style lang="scss" scoped>
.diy-title-bar {
  overflow: hidden;
  box-sizing: border-box;
}

.title-bar-inner {
  height: 44px;
  /* 通常标题栏略高一点，设为 44px */
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0 12px;
  box-sizing: border-box;
}

.left-side {
  display: flex;
  align-items: center;
  flex: 1;

  &.is-center {
    justify-content: center;
  }

  .prefix-line {
    width: 3px;
    height: 16px;
    border-radius: 2px;
    margin-right: 8px;
  }

  .main-title {
    line-height: 1.2;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.right-side {
  display: flex;
  align-items: center;
  font-size: 13px;
  margin-left: 10px;
  flex-shrink: 0;

  .sub-text {
    margin-right: 4px;
  }

  .icon {
    font-size: 13px;
  }
}
</style>
