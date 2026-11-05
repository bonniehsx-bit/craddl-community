<template>
  <div class="diy-blank" :style="boxStyle">
    <!-- 这是一个纯占位组件，通常不需要内容 -->
  </div>
</template>

<script>
export default {
  name: 'Blank',
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
    boxStyle() {
      const height = parseInt(this.safeData.height) || 10;
      // 增加背景色支持，默认为透明或配置的颜色
      const bg = this.safeData.background || 'transparent';

      return {
        height: height + 'px',
        backgroundColor: bg,
        width: '100%'
      };
    }
  }
}
</script>

<style lang="scss" scoped>
.diy-blank {
  width: 100%;
  /* 如果是透明背景，在编辑模式下给一个虚线框提示用户这里有东西（可选优化） */
  // min-height: 1px;
}
</style>
