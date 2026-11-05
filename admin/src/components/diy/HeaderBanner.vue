<!-- src/components/diy/HeaderBanner.vue -->
<template>
  <div class="diy-header-banner">

    <!-- 1. 动态背景层 -->
    <div class="header-bg" :style="{ backgroundColor: currentBgColor }"></div>

    <div class="content-wrap">

      <!-- 2. 定位行 -->
      <div class="top-row" v-if="safeData.show_location == '1'">
        <div class="location-pill">
          <i class="el-icon-location-outline"></i>
          <span>广州市</span>
        </div>
      </div>

      <!-- 3. 搜索行 -->
      <div class="search-row">
        <div class="search-bar">
          <i class="el-icon-search"></i>
          <span class="placeholder">{{ safeData.search_placeholder || '搜索...' }}</span>
          <div class="search-btn" :style="{ color: currentBgColor }">搜索</div>
        </div>
        <div class="msg-icon" v-if="safeData.show_message == '1'">
          <i class="el-icon-bell"></i>
          <div class="badge">2</div>
        </div>
      </div>

      <!-- 4. 轮播图区域 -->
      <div class="banner-box">
        <div class="banner-wrapper">
          <!-- 有图片时显示第一张图 (模拟预览) -->
          <div v-if="currentBannerImg" class="banner-img" :style="{ backgroundImage: `url(${currentBannerImg})` }">
          </div>

          <!-- 无图片时显示占位 -->
          <div v-else class="banner-empty">
            <i class="el-icon-picture"></i>
            <div class="text">建议尺寸 750x300</div>
          </div>
        </div>

        <!-- 指示点 -->
        <div class="dots" v-if="safeData.banners && safeData.banners.length > 0">
          <span v-for="(item, index) in safeData.banners" :key="index" :class="{ active: index === 0 }"></span>
        </div>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'HeaderBanner',
  props: {
    data: {
      type: Object,
      default: () => ({})
    }
  },
  computed: {
    // 安全获取数据，避免 undefined 报错
    safeData() {
      return this.data || {};
    },
    // 获取当前第一张图的 URL
    currentBannerImg() {
      if (this.safeData.banners && this.safeData.banners.length > 0) {
        return this.safeData.banners[0].pic_url;
      }
      return '';
    },
    // 获取当前第一张图的背景色，如果没有则用默认蓝色
    currentBgColor() {
      if (this.safeData.banners && this.safeData.banners.length > 0) {
        return this.safeData.banners[0].bg_color || '#AC1B26';
      }
      return '#AC1B26';
    }
  }
}
</script>

<style lang="scss" scoped>
.diy-header-banner {
  position: relative;
  overflow: hidden;
  /* 确保背景色能够延伸 */
  min-height: 240px;
  background-color: #f5f5f5;
}

.header-bg {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 180px;
  /* 背景色块高度 */
  border-bottom-left-radius: 30px;
  border-bottom-right-radius: 30px;
  z-index: 0;
  transition: background-color 0.4s ease;
}

.content-wrap {
  position: relative;
  z-index: 1;
  padding: 10px 15px;
}

.top-row {
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  height: 24px;

  .location-pill {
    display: inline-flex;
    align-items: center;
    color: #fff;
    font-size: 14px;
    font-weight: 500;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);

    i {
      margin-right: 4px;
      font-size: 16px;
    }
  }
}

.search-row {
  display: flex;
  align-items: center;
  margin-bottom: 15px;

  .search-bar {
    flex: 1;
    height: 38px;
    background: #fff;
    border-radius: 38px;
    display: flex;
    align-items: center;
    padding: 0 4px 0 15px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);

    i {
      color: #999;
      font-size: 16px;
      margin-right: 8px;
    }

    .placeholder {
      flex: 1;
      font-size: 13px;
      color: #999;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .search-btn {
      padding: 6px 16px;
      border-radius: 20px;
      background: #f0f2f5;
      /* 实际可能会用背景色，这里为了对比度用浅灰 */
      font-size: 13px;
      font-weight: bold;
      margin-left: 5px;
    }
  }

  .msg-icon {
    margin-left: 12px;
    position: relative;
    color: #fff;

    i {
      font-size: 22px;
    }

    .badge {
      position: absolute;
      top: -4px;
      right: -6px;
      background: #ff4d4f;
      color: #fff;
      font-size: 10px;
      padding: 1px 4px;
      border-radius: 10px;
      border: 1px solid #fff;
    }
  }
}

.banner-box {
  width: 100%;
  height: 150px;
  border-radius: 12px;
  overflow: hidden;
  position: relative;
  background: #fff;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);

  .banner-wrapper {
    width: 100%;
    height: 100%;
  }

  .banner-img {
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
  }

  .banner-empty {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    background-color: #eee;
    color: #ccc;

    i {
      font-size: 32px;
      margin-bottom: 5px;
    }

    .text {
      font-size: 12px;
    }
  }

  .dots {
    position: absolute;
    bottom: 10px;
    left: 0;
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;

    span {
      width: 6px;
      height: 6px;
      background: rgba(255, 255, 255, 0.4);
      border-radius: 50%;
      margin: 0 4px;
      transition: all 0.3s;

      &.active {
        width: 12px;
        background: #fff;
        border-radius: 6px;
      }
    }
  }
}
</style>
