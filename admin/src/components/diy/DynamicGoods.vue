<template>
  <div class="diy-dynamic-goods" :style="outerStyle">

    <!-- 空状态 -->
    <div v-if="!list || list.length === 0" class="empty-tip">
      <i class="el-icon-s-goods"></i>
      <div>推荐商品组（自动获取）</div>
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
            <div class="goods-title">{{ item.name }}</div>

            <div class="goods-bottom">
              <div class="price-row">
                <!-- 价格 -->
                <div class="price-box" v-if="safeData.show_price == 1" :style="{ color: safeData.price_color }">
                  <span v-if="item.is_negotiable == 1">面议</span>
                  <span v-else><span class="symbol">￥</span>{{ item.price }}</span>
                </div>
                <!-- 销量 -->
                <div class="sales-box" v-if="safeData.show_sales == 1">
                  {{ safeData.sales_text }} <span :style="{ color: safeData.price_color }">99+</span>
                </div>
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
  name: 'DynamicGoods',
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
    // 外层样式
    outerStyle() {
      const padding = this.safeData.is_padding == 1 ? '0 10px' : '0';
      return {
        padding: padding,
        backgroundColor: '#f5f7fa',
        overflow: 'hidden'
      };
    },
    // 布局模式 class
    layoutClass() {
      const type = parseInt(this.safeData.type) || 0;
      if (type === 1) return 'layout-scroll';
      if (type === 2) return 'layout-list';
      return 'layout-grid';
    },
    // 商品项宽度
    itemStyle() {
      const type = parseInt(this.safeData.type) || 0;
      const columns = parseInt(this.safeData.columns) || 2;

      if (type === 2) return { width: '100%' }; // 列表
      if (type === 1) { // 滚动
        if (columns === 1) return { width: '80%' };
        if (columns === 2) return { width: '45%' };
        return { width: '35%' };
      }
      return { width: `${100 / columns}%` }; // 网格
    },
    // 图片高度样式
    imageBoxStyle() {
      if (parseInt(this.safeData.type) === 2) {
        return { width: '100px', height: '100px', flexShrink: 0 };
      }
      const height = parseInt(this.safeData.cover_pic_height) || 370;
      return { height: height / 2 + 'px' };
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
.diy-dynamic-goods {
  box-sizing: border-box;
}

.empty-tip {
  height: 200px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: #909399;
  font-size: 12px;
  i { font-size: 32px; margin-bottom: 10px; }
}

.goods-wrapper {
  display: flex;
  &.layout-grid { flex-wrap: wrap; }
  &.layout-scroll { overflow-x: auto; flex-wrap: nowrap; &::-webkit-scrollbar { display: none; } }
  &.layout-list { flex-direction: column; }
}

.goods-item {
  box-sizing: border-box;
  padding: 5px;
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
      .image-slot { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #c0c4cc; font-size: 20px; }
    }

    .info-box {
      padding: 8px;
      flex: 1;
      display: flex;
      flex-direction: column;

      .goods-title {
        font-size: 13px; color: #333; line-height: 1.4; height: 36px; overflow: hidden; margin-bottom: 4px;
      }

      .goods-bottom {
        margin-top: auto;
        display: flex; justify-content: space-between; align-items: flex-end;

        .price-box { font-weight: bold; font-size: 14px; .symbol { font-size: 12px; } }
        .sales-box { font-size: 10px; color: #999; margin-left: 5px; }

        .buy-btn-wrapper {
          .icon-btn { width: 24px; height: 24px; .icon-slot { font-size: 16px; color: #FF3C29; } }
          .text-btn { font-size: 12px; padding: 1px 6px; border: 1px solid; }
        }
      }
    }
  }
}

/* 列表模式特殊样式 */
.layout-list .goods-item .goods-card {
  flex-direction: row; padding: 10px;
  .img-box { margin-right: 10px; border-radius: 4px; overflow: hidden; }
  .info-box { padding: 0; justify-content: space-between; }
}
</style>
