<template>
  <div class="diy-lucky-group" :style="outerStyle">
    <div class="group-inner" :style="innerStyle">
      <div class="group-header" v-if="safeData.show_title != 0">
        <div class="title">{{ safeData.title || '手气拼团专区' }}</div>
        <div class="more">更多 <i class="el-icon-arrow-right"></i></div>
      </div>

      <!-- 网格模式 -->
      <div v-if="safeData.type == 0" class="grid-layout" :class="'columns-' + safeData.columns">
        <div
          v-for="i in safeData.limit"
          :key="'grid-' + i"
          class="goods-item"
        >
          <div class="goods-card">
            <div class="image-box">
              <i class="el-icon-picture-outline placeholder-icon"></i>
              <div class="lucky-badge">3人团</div>
            </div>
            <div class="info-box">
              <div class="goods-name">这是一个测试的拼团商品名称，超出两行自动隐藏显示省略号</div>
              <div class="price-box" v-if="safeData.show_price == 1">
                <span class="price-symbol" :style="{ color: safeData.price_color }">￥</span>
                <span class="price-text" :style="{ color: safeData.price_color }">99.00</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- 列表模式 -->
      <div v-if="safeData.type == 1" class="list-layout">
        <div
          v-for="i in safeData.limit"
          :key="'list-' + i"
          class="list-item"
        >
          <div class="image-box">
            <i class="el-icon-picture-outline placeholder-icon"></i>
            <div class="lucky-badge">5人团</div>
          </div>
          <div class="info-box">
            <div class="goods-name">这是一个测试的拼团商品名称，超出两行自动隐藏显示省略号</div>
            <div class="bottom-action">
              <div class="price-box" v-if="safeData.show_price == 1">
                <span class="price-symbol" :style="{ color: safeData.price_color }">￥</span>
                <span class="price-text" :style="{ color: safeData.price_color }">199.00</span>
              </div>
              <div class="buy-btn" :style="{ backgroundColor: safeData.price_color }">去拼团</div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'LuckyGroup',
  props: {
    data: {
      type: Object,
      default: () => ({})
    }
  },
  computed: {
    safeData() {
      // 提供默认值，防止渲染报错
      return Object.assign({
        limit: 4,
        type: 0,
        columns: 2,
        show_price: 1,
        price_color: '#ff4544',
        is_padding: 1,
        is_circle: 1,
        background: '#ffffff'
      }, this.data || {});
    },
    outerStyle() {
      const padding = this.safeData.is_padding == 1 ? '0 10px' : '0';
      return {
        padding: padding,
        marginBottom: '10px'
      };
    },
    innerStyle() {
      const radius = this.safeData.is_circle == 1 ? '10px' : '0';
      return {
        borderRadius: radius,
        backgroundColor: this.safeData.background,
        padding: '10px',
        overflow: 'hidden'
      };
    }
  }
}
</script>

<style lang="scss" scoped>
.diy-lucky-group {
  width: 100%;
  box-sizing: border-box;
}

.group-inner {
  width: 100%;
  box-sizing: border-box;
  box-shadow: 0 2px 10px rgba(0,0,0,0.02);
}

.group-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  padding-bottom: 8px;
  border-bottom: 1px solid #f5f5f5;

  .title {
    font-size: 15px;
    font-weight: bold;
    color: #333;
  }

  .more {
    font-size: 12px;
    color: #999;
  }
}

/* 网格布局 */
.grid-layout {
  display: flex;
  flex-wrap: wrap;
  margin: 0 -5px;

  .goods-item {
    box-sizing: border-box;
    padding: 0 5px;
    margin-bottom: 10px;
  }

  &.columns-2 .goods-item { width: 50%; }
  &.columns-3 .goods-item { width: 33.33%; }
  &.columns-4 .goods-item { width: 25%; }

  .goods-card {
    background: #fdfdfd;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 1px 5px rgba(0,0,0,0.03);
    border: 1px solid #f0f0f0;
    height: 100%;
    display: flex;
    flex-direction: column;

    .image-box {
      width: 100%;
      height: 140px;
      background: #f5f7fa;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;

      .placeholder-icon {
        font-size: 30px;
        color: #c0c4cc;
      }
      
      .lucky-badge {
        position: absolute;
        top: 6px;
        left: 6px;
        background: rgba(0,0,0,0.5);
        color: #fff;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 4px;
        backdrop-filter: blur(2px);
      }
    }

    .info-box {
      padding: 8px;
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;

      .goods-name {
        font-size: 13px;
        color: #333;
        line-height: 1.4;
        margin-bottom: 8px;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        overflow: hidden;
      }

      .price-box {
        .price-symbol { font-size: 11px; }
        .price-text { font-size: 15px; font-weight: bold; }
      }
    }
  }
}

/* 列表布局 */
.list-layout {
  .list-item {
    display: flex;
    padding: 10px;
    background: #fdfdfd;
    border-radius: 6px;
    margin-bottom: 10px;
    box-shadow: 0 1px 5px rgba(0,0,0,0.03);
    border: 1px solid #f0f0f0;

    &:last-child {
      margin-bottom: 0;
    }

    .image-box {
      width: 90px;
      height: 90px;
      background: #f5f7fa;
      border-radius: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      margin-right: 12px;
      position: relative;

      .placeholder-icon { font-size: 24px; color: #c0c4cc; }
      .lucky-badge {
        position: absolute;
        top: 4px;
        left: 4px;
        background: rgba(0,0,0,0.5);
        color: #fff;
        font-size: 10px;
        padding: 2px 4px;
        border-radius: 4px;
      }
    }

    .info-box {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;

      .goods-name {
        font-size: 14px;
        color: #333;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        overflow: hidden;
      }

      .bottom-action {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;

        .price-box {
          .price-symbol { font-size: 12px; }
          .price-text { font-size: 16px; font-weight: bold; }
        }

        .buy-btn {
          font-size: 12px;
          color: #fff;
          padding: 4px 12px;
          border-radius: 20px;
        }
      }
    }
  }
}
</style>
