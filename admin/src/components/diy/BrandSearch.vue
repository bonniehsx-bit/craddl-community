<template>
  <!-- 顶栏搜索:整条底色 + 左侧店名 + 胶囊搜索框(和手机端 tui-brand-search 同一套字段) -->
  <div class="brand-search" :style="{ background: d.bg_color || '#0e1a2b' }">
    <div v-if="d.brand_title" class="brand">
      <div class="brand-title" :style="{ color: d.text_color }">{{ d.brand_title }}</div>
      <div v-if="d.brand_sub" class="brand-sub" :style="{ color: d.accent_color }">{{ d.brand_sub }}</div>
    </div>
    <div class="box" :style="boxStyle">
      <i class="el-icon-search" />
      <span class="tips">{{ d.tips || '搜索商品' }}</span>
    </div>
  </div>
</template>

<script>
export default {
  name: 'BrandSearch',
  props: { data: Object },
  computed: {
    d() {
      return this.data || {}
    },
    boxStyle() {
      return {
        background: this.d.box_bg,
        border: this.d.box_border ? '1px solid ' + this.d.box_border : 'none',
        color: this.d.tips_color
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.brand-search {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px 12px;
}

.brand {
  flex-shrink: 0;
  max-width: 40%;

  .brand-title {
    font-size: 15px;
    font-weight: 700;
    letter-spacing: .5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .brand-sub {
    font-size: 9px;
    letter-spacing: 2px;
    opacity: .85;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}

.box {
  flex: 1;
  min-width: 0;
  height: 32px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0 12px;
  box-sizing: border-box;
  font-size: 12px;

  .tips {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}
</style>
