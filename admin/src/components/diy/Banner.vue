<template>
  <!-- 外层容器：控制左右边距 -->
  <div class="diy-banner" :style="outerStyle">
    <!-- 内层容器：控制圆角和溢出隐藏 -->
    <div class="banner-inner" :style="innerStyle">

      <!-- 轮播组件 -->
      <el-carousel :height="heightVal + 'px'" :interval="intervalVal" arrow="never" indicator-position="none" @change="current = $event">
        <el-carousel-item v-for="(item, index) in list" :key="index">
          <el-image :src="item.pic_url" fit="cover" style="width: 100%; height: 100%; display: block;">
            <div slot="error" class="image-slot">
              <i class="el-icon-picture-outline"></i>
              <span class="text">图片 {{ index + 1 }}</span>
            </div>
            <div slot="placeholder" class="image-slot">
              <i class="el-icon-loading"></i>
            </div>
          </el-image>
        </el-carousel-item>

        <!-- 空状态 -->
        <el-carousel-item v-if="list.length === 0">
          <div class="image-slot empty-banner">
            <i class="el-icon-picture"></i>
            <div>请在右侧添加轮播图片</div>
          </div>
        </el-carousel-item>
      </el-carousel>

      <!-- 自定义指示点:与小程序端 tui-banner 同一套样式 / 位置规则 -->
      <div class="custom-dots" :class="['dots-' + dotPos, 'dots-' + dotStyle]" v-if="list.length > 1 && safeData.dots !== false">
        <div v-if="dotStyle === 'num'" class="dot-num">{{ current + 1 }} / {{ list.length }}</div>
        <template v-else>
          <div v-for="(item, index) in list" :key="index" class="dot" :class="{ active: index === current }"></div>
        </template>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'Banner',
  props: {
    data: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return { current: 0 }
  },
  computed: {
    dotStyle() {
      return ['pill', 'dot', 'num'].indexOf(this.safeData.dot_style) !== -1 ? this.safeData.dot_style : 'pill'
    },
    // 开了标题时底部是标题条,点放右下角(与小程序端一致)
    dotPos() {
      if (this.safeData.is_show_title == 1) return 'right'
      return this.safeData.dot_position === 'right' ? 'right' : 'center'
    },
    safeData() {
      return this.data || {};
    },
    list() {
      return this.safeData.list || [];
    },
    // 【核心修改】：高度处理 (rpx 转 px)
    heightVal() {
      // 获取配置的高度 (假设是基于750设计稿的 rpx)
      const heightRpx = parseInt(this.safeData.height) || 350;
      // 预览区宽度是375px，所以高度需要除以2
      return heightRpx / 2;
    },
    intervalVal() {
      return parseInt(this.safeData.interval) || 3000;
    },
    outerStyle() {
      const padding = this.safeData.is_padding == 1 ? '0 10px' : '0';
      return {
        padding: padding,
        backgroundColor: 'transparent' // 核心修复：透明背景，让 padding 区域透出手机壳底色
      };
    },
    innerStyle() {
      const radius = this.safeData.is_circle == 1 ? '10px' : '0';
      return {
        borderRadius: radius,
        overflow: 'hidden',
        transform: 'translateZ(0)'
      };
    }
  }
}
</script>

<style lang="scss" scoped>
.diy-banner {
  position: relative;
  box-sizing: border-box;
}

.banner-inner {
  position: relative;
  width: 100%;
}

.image-slot {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  width: 100%;
  height: 100%;
  background: #f5f7fa;
  color: #909399;

  i {
    font-size: 28px;
    margin-bottom: 5px;
  }

  .text {
    font-size: 12px;
  }
}

.empty-banner {
  background: #eef1f6;
  border: 1px dashed #dcdfe6;
  box-sizing: border-box;
}

.custom-dots {
  position: absolute;
  bottom: 9px;
  display: flex;
  align-items: center;
  z-index: 2;
  pointer-events: none;

  &.dots-center { left: 0; right: 0; justify-content: center; }
  &.dots-right { right: 12px; }

  .dot {
    width: 6px;
    height: 6px;
    border-radius: 3px;
    margin: 0 2.5px;
    background: rgba(255, 255, 255, 0.5);
    box-shadow: 0 0 2px rgba(0, 0, 0, 0.25);

    &.active { background: #fff; }
  }
  &.dots-pill .dot.active { width: 16px; }

  .dot-num {
    padding: 2px 8px;
    border-radius: 10px;
    background: rgba(0, 0, 0, 0.35);
    color: #fff;
    font-size: 11px;
    line-height: 1.5;
    letter-spacing: 1px;
  }
}
</style>
