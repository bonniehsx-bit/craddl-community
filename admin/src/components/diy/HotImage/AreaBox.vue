<template>
  <div class="areaBox" :style="{
    width: displayArea.areaWidth + 'px',
    height: displayArea.areaHeight + 'px',
    left: displayArea.starX + 'px',
    top: displayArea.starY + 'px',
  }" @dblclick="dialogVisible = true" @mousedown.left.stop="handleDragStart">
    <div class="content">
      <div class="name">{{ areaInit.name }}</div>
      <span v-if="!areaInit.name" class="promptText">双击设置</span>
    </div>
    <div class="del" @click.stop="del" title="删除" />
    <div class="shape" @mousedown.left.stop="handleResizeStart" title="缩放" />

    <el-dialog title="板块设置" :visible.sync="dialogVisible" width="40%" append-to-body>
      <el-form label-width="80px">
        <el-form-item label="板块名称">
          <el-input v-model="editableName" placeholder="板块名称" clearable />
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="dialogVisible = false">取 消</el-button>
        <el-button type="primary" @click="confirmEdit">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'AreaBox',
  props: {
    areaInit: {
      type: Object,
      required: true,
    },
    containerRect: {
      required: true,
      validator: v => v === null || typeof v === 'object',
    },
  },
  data() {
    return {
      dialogVisible: false,
      editableName: '',

      // 核心改动: 使用本地状态来处理交互，避免直接操作DOM
      isInteracting: false, // 标记是否正在拖动或缩放
      localArea: null, // 交互时使用的本地数据副本

      // 交互起始点信息
      interactionStart: {
        mouseX: 0,
        mouseY: 0,
        initialLeft: 0,
        initialTop: 0,
        initialWidth: 0,
        initialHeight: 0,
      },
    };
  },
  computed: {
    // 核心改动: 根据是否在交互，决定显示的数据源
    displayArea() {
      return this.isInteracting ? this.localArea : this.areaInit;
    },
  },
  watch: {
    dialogVisible(newVal) {
      if (newVal) {
        this.editableName = this.areaInit.name;
      }
    },
  },
  methods: {
    del() {
      this.$emit('del-area', this.areaInit.id);
    },
    confirmEdit() {
      const updatedArea = { ...this.areaInit, name: this.editableName };
      this.$emit('update-area', updatedArea);
      this.dialogVisible = false;
    },

    // --- 拖动逻辑 ---
    handleDragStart(e) {
      this.isInteracting = true;
      this.localArea = { ...this.areaInit }; // 创建本地副本

      this.interactionStart.mouseX = e.clientX;
      this.interactionStart.mouseY = e.clientY;
      this.interactionStart.initialLeft = this.areaInit.starX;
      this.interactionStart.initialTop = this.areaInit.starY;

      // 核心修复：动态计算缩放比例
      this.interactionStart.scale = this.containerRect ? (750 / this.containerRect.width) : 2;

      document.addEventListener('mousemove', this.handleDragging);
      document.addEventListener('mouseup', this.handleDragEnd);
    },
    handleDragging(e) {
      // 核心修复：使用动态比例
      const dx = (e.clientX - this.interactionStart.mouseX) * this.interactionStart.scale;
      const dy = (e.clientY - this.interactionStart.mouseY) * this.interactionStart.scale;

      let newLeft = this.interactionStart.initialLeft + dx;
      let newTop = this.interactionStart.initialTop + dy;

      if (this.containerRect) {
        const maxLeft = 750 - this.localArea.areaWidth;
        const maxTop = (this.containerRect.height * this.interactionStart.scale) - this.localArea.areaHeight;
        newLeft = Math.max(0, Math.min(newLeft, maxLeft));
        newTop = Math.max(0, Math.min(newTop, maxTop));
      }

      // 只更新本地数据，Vue会自动重绘
      this.localArea.starX = newLeft;
      this.localArea.starY = newTop;
    },
    handleDragEnd() {
      document.removeEventListener('mousemove', this.handleDragging);
      document.removeEventListener('mouseup', this.handleDragEnd);

      this.isInteracting = false;
      this.$emit('update-area', this.localArea); // 交互结束，通知父组件
      this.localArea = null; // 清理本地副本
    },

    // --- 缩放逻辑 ---
    handleResizeStart(e) {
      this.isInteracting = true;
      this.localArea = { ...this.areaInit }; // 创建本地副本

      this.interactionStart.mouseX = e.clientX;
      this.interactionStart.mouseY = e.clientY;
      this.interactionStart.initialWidth = this.areaInit.areaWidth;
      this.interactionStart.initialHeight = this.areaInit.areaHeight;

      // 核心修复：动态计算缩放比例
      this.interactionStart.scale = this.containerRect ? (750 / this.containerRect.width) : 2;

      document.addEventListener('mousemove', this.handleResizing);
      document.addEventListener('mouseup', this.handleResizeEnd);
    },
    handleResizing(e) {
      // 核心修复：使用动态比例
      const dx = (e.clientX - this.interactionStart.mouseX) * this.interactionStart.scale;
      const dy = (e.clientY - this.interactionStart.mouseY) * this.interactionStart.scale;

      let newWidth = this.interactionStart.initialWidth + dx;
      let newHeight = this.interactionStart.initialHeight + dy;

      if (this.containerRect) {
        const maxWidth = 750 - this.localArea.starX;
        newWidth = Math.max(40, Math.min(newWidth, maxWidth));
        const maxHeight = (this.containerRect.height * this.interactionStart.scale) - this.localArea.starY;
        newHeight = Math.max(40, Math.min(newHeight, maxHeight));
      }

      // 只更新本地数据
      this.localArea.areaWidth = newWidth;
    },
    handleResizeEnd() {
      document.removeEventListener('mousemove', this.handleResizing);
      document.removeEventListener('mouseup', this.handleResizeEnd);

      this.isInteracting = false;
      this.$emit('update-area', this.localArea); // 交互结束，通知父组件
      this.localArea = null; // 清理本地副本
    },
  },
};
</script>

<style scoped lang="scss">
/* 样式与之前版本相同，此处省略，您可以直接复用 */
.areaBox {
  position: absolute;
  /* 热区编辑覆盖层跟随后台强调色。 */
  background: rgba(var(--admin-accent-rgb), 0.5);
  border: 1px dashed #ffffff;
  box-sizing: border-box;
  color: #fff;
  /* 核心：字体放大，因为父级缩放 0.5，这里 24px 视觉上是 12px */
  font-size: 24px;
  cursor: move;
  user-select: none;
  display: flex;
  justify-content: center;
  align-items: center;
  transition: background-color 0.2s;

  &:hover {
    background: rgba(var(--admin-accent-rgb), 0.7);
  }

  .content {
    text-align: center;
    pointer-events: none;
    /* 内部文字不干扰鼠标事件 */
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 90%;

    .name {
      font-weight: bold;
    }

    .promptText {
      /* 核心：放大字号 */
      font-size: 20px;
      opacity: 0.8;
    }
  }

  /* 删除按钮放大 */
  .del {
    width: 32px;
    height: 32px;
    background-color: #e74c3c;
    border-radius: 50%;
    position: absolute;
    right: 0;
    top: 0;
    transform: translate(50%, -50%);
    cursor: pointer;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #fff;
    /* 增加边框使其更清晰 */

    &::before,
    &::after {
      content: '';
      position: absolute;
      width: 18px;
      /* 放大 */
      height: 4px;
      /* 放大 */
      background-color: white;
    }

    &::before {
      transform: rotate(45deg);
    }

    &::after {
      transform: rotate(-45deg);
    }

    &:hover {
      background-color: #c0392b;
    }
  }

  /* 缩放手柄放大 */
  .shape {
    position: absolute;
    width: 24px;
    /* 放大 */
    height: 24px;
    /* 放大 */
    background: #fff;
    border: 2px solid #34495e;
    /* 边框加粗 */
    right: 0;
    bottom: 0;
    transform: translate(50%, 50%);
    cursor: nwse-resize;
    z-index: 10;

    &:hover {
      background-color: #ecf0f1;
    }
  }
}
</style>
