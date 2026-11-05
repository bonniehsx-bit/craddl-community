<template>
  <div class="diy-hot-image" :style="outerStyle">
    <div class="hot-image-inner" :style="innerStyle">

      <!-- 图片展示 -->
      <div class="image-box">
        <el-image v-if="safeData.pic_url" :src="safeData.pic_url" fit="cover" style="width: 100%; display: block;">
          <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
        </el-image>

        <!-- 空状态 -->
        <div v-else class="empty-box">
          <i class="el-icon-edit"></i>
          <span>请选择热区图</span>
        </div>

        <!-- 热区覆盖层 (仅用于展示位置，实际点击逻辑由前端渲染决定) -->
        <div v-for="(item, index) in list" :key="index" class="hotspot-area" :style="getHotspotStyle(item)">
          <div class="hotspot-label">{{ item.name }}</div>
        </div>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'HotImage',
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
    list() {
      return this.safeData.list || [];
    },
    // 外层：控制边距
    outerStyle() {
      const padding = this.safeData.is_padding == 1 ? '0 10px' : '0';
      return {
        padding: padding,
        backgroundColor: 'transparent'
      };
    },
    // 内层：控制圆角
    innerStyle() {
      const radius = this.safeData.is_circle == 1 ? '10px' : '0';
      return {
        borderRadius: radius,
        overflow: 'hidden'
      };
    }
  },
  methods: {
    // 计算热区位置样式
    // 假设后端/编辑器保存的数据是基于 750px 宽度的，预览区是 375px，所以乘以 0.5
    getHotspotStyle(item) {
      const ratio = 0.5;
      return {
        left: (item.starX * ratio) + 'px',
        top: (item.starY * ratio) + 'px',
        width: (item.areaWidth * ratio) + 'px',
        height: (item.areaHeight * ratio) + 'px'
      };
    }
  }
}
</script>

<style lang="scss" scoped>
.diy-hot-image {
  overflow: hidden;
}

.hot-image-inner {
  position: relative;
  background: #fff;
}

.image-box {
  position: relative;
  width: 100%;
  min-height: 100px;

  .empty-box {
    height: 150px;
    background: #f4f6f8;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #909399;
    font-size: 12px;

    i {
      font-size: 28px;
      margin-bottom: 5px;
    }
  }
}

.hotspot-area {
  position: absolute;
  border: 1px dashed rgba(255, 255, 255, 0.8);
  background: rgba(var(--admin-accent-rgb), 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 10px;
  overflow: hidden;
  z-index: 2;
  box-shadow: 0 0 2px rgba(0, 0, 0, 0.2);

  .hotspot-label {
    text-shadow: 0 0 2px rgba(0, 0, 0, 0.5);
    padding: 2px;
    text-align: center;
    line-height: 1.2;
  }
}
</style>
