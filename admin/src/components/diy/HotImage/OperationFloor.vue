<template>
  <div class="operationFloor">
    <div class="imgBox">
      <!--
        核心改动1: 将 mousedown 事件监听器移到 container 上,
        这样既可以创建新热区, 也不会阻止点击已有的 AreaBox
      -->
      <div class="container" style="width: 750px" @mousedown.left.stop="handleMouseDown">
        <img ref="backgroundImg" :src="internalData.pic_url" style="width: 100%; display: block"
          ondragstart="return false;" oncontextmenu="return false;" alt="背景图片" />
        <!-- 绘制中的热区预览 -->
        <div v-if="isDrawing" class="area drawing-area" :style="{
          width: drawingArea.width + 'px',
          height: drawingArea.height + 'px',
          left: drawingArea.left + 'px',
          top: drawingArea.top + 'px',
        }" />
        <!-- 已有的热区 -->
        <AreaBox v-for="item in internalData.data" :key="item.id" :area-init="item" :container-rect="containerRect"
          @del-area="handleDelArea" @update-area="handleUpdateArea" />
      </div>
    </div>
  </div>
</template>

<script>
import AreaBox from './AreaBox';

export default {
  name: 'OperationFloor',
  components: {
    AreaBox,
  },
  props: {
    value: {
      type: Object,
      required: true,
    },
  },
  data() {
    return {
      internalData: { pic_url: '', data: [] },
      isDrawing: false,
      drawingArea: {
        left: 0,
        top: 0,
        width: 0,
        height: 0,
      },
      startPoint: { x: 0, y: 0 },
      containerRect: null,
    };
  },
  watch: {
    value: {
      handler(newVal) {
        this.internalData = JSON.parse(JSON.stringify(newVal));
        if (!this.internalData.data) {
          this.internalData.data = [];
        }
      },
      immediate: true,
      deep: true,
    },
  },
  mounted() {
    this.updateContainerRect();
    window.addEventListener('resize', this.updateContainerRect);
  },
  beforeDestroy() {
    window.removeEventListener('resize', this.updateContainerRect);
  },
  methods: {
    updateContainerRect() {
      if (this.$refs.backgroundImg) {
        this.containerRect = this.$refs.backgroundImg.getBoundingClientRect();
      }
    },
    handleMouseDown(e) {
      if (e.target.closest('.areaBox')) {
        return;
      }

      this.updateContainerRect();
      if (!this.containerRect) return;

      this.isDrawing = true;

      // 核心修复：计算动态缩放比例
      // containerRect.width 是屏幕上的实际宽度
      // 750 是内部逻辑宽度
      const scale = 750 / this.containerRect.width;

      this.startPoint.x = (e.clientX - this.containerRect.left) * scale;
      this.startPoint.y = (e.clientY - this.containerRect.top) * scale;

      this.drawingArea.left = this.startPoint.x;
      this.drawingArea.top = this.startPoint.y;
      this.drawingArea.width = 0;
      this.drawingArea.height = 0;

      document.addEventListener('mousemove', this.handleMouseMove);
      document.addEventListener('mouseup', this.handleMouseUp);
    },
    handleMouseMove(e) {
      if (!this.isDrawing || !this.containerRect) return;

      const scale = 750 / this.containerRect.width;
      const currentX = (e.clientX - this.containerRect.left) * scale;
      const currentY = (e.clientY - this.containerRect.top) * scale;

      this.drawingArea.left = Math.min(this.startPoint.x, currentX);
      this.drawingArea.top = Math.min(this.startPoint.y, currentY);
      this.drawingArea.width = Math.abs(currentX - this.startPoint.x);
      this.drawingArea.height = Math.abs(currentY - this.startPoint.y);
    },
    handleMouseUp() {
      document.removeEventListener('mousemove', this.handleMouseMove);
      document.removeEventListener('mouseup', this.handleMouseUp);

      if (!this.isDrawing) return;
      this.isDrawing = false;

      if (this.drawingArea.width > 10 && this.drawingArea.height > 10) {
        const newArea = {
          id: `area_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
          starX: this.drawingArea.left,
          starY: this.drawingArea.top,
          areaWidth: this.drawingArea.width,
          areaHeight: this.drawingArea.height,
          url: null,
          name: '新模块',
        };
        this.internalData.data.push(newArea);
        this.$emit('input', this.internalData);
      }

      this.drawingArea = { left: 0, top: 0, width: 0, height: 0 };
    },
    handleDelArea(areaId) {
      const index = this.internalData.data.findIndex((item) => item.id === areaId);
      if (index !== -1) {
        this.internalData.data.splice(index, 1);
        this.$emit('input', this.internalData);
      }
    },
    handleUpdateArea(updatedArea) {
      const index = this.internalData.data.findIndex((item) => item.id === updatedArea.id);
      if (index !== -1) {
        this.$set(this.internalData.data, index, updatedArea);
        this.$emit('input', this.internalData);
      }
    },
  },
};
</script>

<style scoped lang="scss">
.operationFloor {
  width: 100%;
  /* 核心：无需 flex 居中，因为外层已经约束了宽度 */

  .imgBox {
    /* 核心：保持内部 750px 渲染，但视觉缩放 0.5 以适配 375px 容器 */
    width: 750px;
    transform: scale(0.5);
    transform-origin: top left;

    .container {
      position: relative;
      user-select: none;
      cursor: crosshair;

      img {
        display: block;
        width: 100%;
      }

      .area {
        position: absolute;
        background: rgba(var(--admin-accent-rgb), 0.3);
        border: 1px dashed #34495e;
        box-sizing: border-box;
      }

      .drawing-area {
        background: rgba(#e74c3c, 0.4);
        border: 1px dashed #c0392b;
        z-index: 99;
      }
    }
  }
}
</style>
