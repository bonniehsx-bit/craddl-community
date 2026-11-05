<template>
  <div class="diy-custom-goods" :style="outerStyle">

    <!-- 空状态 -->
    <div v-if="!list || list.length === 0" class="empty-tip">
      <i class="el-icon-goods"></i>
      <span>请添加模拟商品</span>
    </div>

    <!-- 商品列表容器 -->
    <div v-else class="goods-wrapper" :class="layoutClass">
      <div
        v-for="(item, index) in list"
        :key="index"
        class="goods-item"
        :style="itemStyle"
      >
        <div class="goods-card">
          <!-- 图片 -->
          <div class="img-box" :style="imageBoxStyle">
            <el-image
              :src="item.cover_pic"
              fit="cover"
              style="width: 100%; height: 100%; display: block;"
            >
              <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
            </el-image>
          </div>

          <!-- 信息 -->
          <div class="info-box">
            <div class="goods-title">{{ item.name || '商品名称' }}</div>

            <div class="goods-bottom">
              <!-- 价格 -->
              <div class="price-box" v-if="safeData.show_price == 1" :style="{ color: safeData.price_color }">
                <span class="symbol">￥</span>{{ item.price || '0.00' }}
              </div>

              <!-- 购买按钮 -->
              <div class="buy-btn-wrapper" v-if="safeData.show_buy_btn == 1">
                <!-- 图标模式 -->
                <div v-if="safeData.cart_type == 1" class="icon-btn">
                  <el-image :src="safeData.cart_pic" fit="contain" style="width: 100%; height: 100%;">
                    <div slot="error" class="icon-slot"><i class="el-icon-shopping-cart-2"></i></div>
                  </el-image>
                </div>
                <!-- 按钮模式 -->
                <div v-else class="text-btn" :style="textBtnStyle">
                  {{ safeData.cart_text || '购买' }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'CustomGoods',
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
    // 外层：控制背景色（通常是透明或灰色背景，以便卡片显示）
    outerStyle() {
      return {
        backgroundColor: '#f5f7fa',
        padding: '10px 5px',
        overflow: 'hidden'
      };
    },
    // 布局模式 class
    layoutClass() {
      // type: 0-正常(网格), 1-滚动
      return this.safeData.type == 1 ? 'layout-scroll' : 'layout-grid';
    },
    // 商品项宽度
    itemStyle() {
      const type = parseInt(this.safeData.type);
      const columns = parseInt(this.safeData.columns) || 2;

      // 滚动模式
      if (type === 1) {
        if (columns === 1) return { width: '80%' };
        if (columns === 2) return { width: '45%' };
        return { width: '35%' };
      }

      // 网格模式
      return { width: `${100 / columns}%` };
    },
    // 图片高度样式
    imageBoxStyle() {
      const height = parseInt(this.safeData.cover_pic_height) || 375;
      return {
        height: height / 2 + 'px' // 简单换算 rpx -> px
      };
    },
    // 购买按钮样式
    textBtnStyle() {
      const color = this.safeData.cart_color || '#FF3C29';
      const isCircle = this.safeData.is_circle == 1;
      return {
        color: color,
        borderColor: color,
        borderRadius: isCircle ? '12px' : '4px'
      };
    }
  }
}
</script>

<style lang="scss" scoped>
.diy-custom-goods {
  box-sizing: border-box;
}

.empty-tip {
  padding: 40px 0;
  text-align: center;
  color: #909399;
  font-size: 12px;
  i { font-size: 24px; margin-bottom: 5px; }
}

.goods-wrapper {
  display: flex;

  &.layout-grid {
    flex-wrap: wrap;
  }

  &.layout-scroll {
    overflow-x: auto;
    flex-wrap: nowrap;
    &::-webkit-scrollbar { display: none; }
  }
}

.goods-item {
  box-sizing: border-box;
  padding: 0 5px;
  margin-bottom: 10px;
  flex-shrink: 0;

  .goods-card {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;

    .img-box {
      width: 100%;
      background: #f4f4f4;

      .image-slot {
        width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
        color: #c0c4cc; font-size: 20px;
      }
    }

    .info-box {
      padding: 8px;
      flex: 1;
      display: flex;
      flex-direction: column;

      .goods-title {
        font-size: 13px;
        color: #333;
        line-height: 1.4;
        height: 36px;
        overflow: hidden;
        margin-bottom: 8px;
      }

      .goods-bottom {
        margin-top: auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        height: 24px;

        .price-box {
          font-weight: bold;
          font-size: 14px;
          .symbol { font-size: 12px; }
        }

        .buy-btn-wrapper {
          .icon-btn {
            width: 24px; height: 24px;
            .icon-slot { font-size: 16px; color: #FF3C29; }
          }
          .text-btn {
            font-size: 12px;
            padding: 1px 6px;
            border: 1px solid;
          }
        }
      }
    }
  }
}
</style>
