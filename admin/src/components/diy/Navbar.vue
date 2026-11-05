<template>
  <div class="diy-navbar" :style="outerStyle">
    <div class="navbar-inner" :style="innerStyle">

      <!-- 空状态 -->
      <div v-if="!list || list.length === 0" class="empty-tip">
        <i class="el-icon-menu"></i>
        <span>请添加导航按钮</span>
      </div>

      <!-- 现代 CSS Grid 导航列表 -->
      <div v-else class="nav-grid" :style="gridStyle">
        <div class="nav-item" v-for="(item, index) in list" :key="index">
          <!-- 图标模式 -->
          <div v-if="safeData.style == 0" class="icon-wrapper"
            :style="{ width: iconSize + 'px', height: iconSize + 'px' }">
            <el-image :src="item.pic_url" fit="contain" style="width: 100%; height: 100%;">
              <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
            </el-image>
          </div>
          <!-- 文字 -->
          <div class="nav-text"
            :style="{ fontSize: (safeData.font_size || 14) + 'px', color: safeData.color || '#333333' }">
            {{ item.name }}
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'Navbar',
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
    iconSize() {
      return this.safeData.icon_size || 44; // 默认大图标尺寸
    },
    // 外层：控制边距
    outerStyle() {
      const padding = this.safeData.is_padding == 1 ? '0 12px' : '0';
      return {
        padding: padding,
        backgroundColor: '#f5f7fa', // 底色防穿透
        boxSizing: 'border-box',
        width: '100%'
      };
    },
    // 内层：控制圆角和背景色
    innerStyle() {
      const radius = this.safeData.is_circle == 1 ? '10px' : '0';
      return {
        borderRadius: radius,
        backgroundColor: this.safeData.background || '#ffffff',
        overflow: 'hidden'
      };
    },
    // CSS Grid 布局控制
    gridStyle() {
      const columns = parseInt(this.safeData.columns) || 4;
      return {
        display: 'grid',
        gridTemplateColumns: `repeat(${columns}, 1fr)`,
        rowGap: '12px',
        padding: '12px 0'
      };
    }
  }
}
</script>

<style lang="scss" scoped>
.diy-navbar {
  overflow: hidden;
  box-sizing: border-box;
}

.navbar-inner {
  width: 100%;
  min-height: 80px;
}

.nav-grid {
  width: 100%;
}

.nav-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;

  .icon-wrapper {
    margin-bottom: 8px;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-shrink: 0;

    ::v-deep .el-image {
      display: flex;
      justify-content: center;
      align-items: center;
    }
  }

  .nav-text {
    line-height: 1.3;
    text-align: center;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 90%;
    padding: 0 4px;
  }
}

.image-slot {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 100%;
  height: 100%;
  background: #f5f7fa;
  color: #c0c4cc;
  font-size: 20px;
}

.empty-tip {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 80px;
  color: #909399;
  font-size: 12px;

  i {
    font-size: 24px;
    margin-bottom: 5px;
  }
}
</style>
