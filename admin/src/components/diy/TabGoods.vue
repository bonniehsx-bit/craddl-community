<template>
  <div class="diy-tab-goods premium-goods-container" :style="outerStyle">
    <div class="tab-goods-inner" :style="innerStyle">

      <!-- 1. 高级感 Tabs -->
      <div class="premium-tabs" v-if="list.length">
        <div v-for="(item, index) in list" :key="index" class="tab-item" :class="{ active: activeTab === index }"
          @click.stop="activeTab = index">
          <span class="tab-text" :style="getTabStyle(index)">
            {{ item.name || '分类' }}
          </span>
          <!-- 选中时的下划线 -->
          <div class="tab-line" v-if="activeTab === index"
            :style="{ backgroundColor: safeData.tab_line_color || safeData.active_color || '#FF4A4A' }"></div>
        </div>
      </div>

      <!-- 2. 商品内容区 -->
      <div class="goods-content" :class="{ 'card-minimal': safeData.card_style === 'minimal' }">
        <!-- 空状态 -->
        <div v-if="!currentGoodsList || currentGoodsList.length === 0" class="empty-tip">
          <i class="el-icon-goods"></i>
          <div>暂无商品</div>
        </div>

        <!-- 滚动布局 (对应 App xc.type == 1 / admin goods_style == 1) -->
        <div v-else-if="layoutType === 1" class="scroll-row-view">
          <div v-for="(goods, index) in currentGoodsList" :key="index" class="goods-card scroll-item"
            :style="getScrollItemWidth()">
            <div class="img-wrap" :style="goodsHeight">
              <el-image :src="goods.cover_pic" fit="cover" class="img">
                <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
              </el-image>
            </div>
            <div class="info-wrap">
              <div class="title">{{ goods.name }}</div>
              <div class="bottom-action flex-x-between flex-y-center">
                <div class="flex-col">
                  <div class="price-box" :style="{ color: safeData.price_color || '#FF4A4A' }">
                    <span class="symbol" v-if="goods.is_negotiable != 1">￥</span>
                    <span class="amount">{{ goods.is_negotiable == 1 ? '面议' : shownPrice(goods.price || '99.00') }}</span>
                  </div>
                  <div class="sales-box" v-if="safeData.show_sales == 1" style="margin-bottom: 0; margin-top: 2px;">
                    <span class="sales-text">{{ safeData.sales_text || '已售' }} {{ goods.virtual_sales || 0 }}</span>
                  </div>
                </div>
                <!-- 购买按钮 -->
                <div class="btn-wrap" v-if="safeData.show_buy_btn != 0">
                  <img :src="safeData.cart_pic" v-if="safeData.cart_type == 1" class="buybtn-icon" />
                  <div v-if="safeData.cart_type == 0 || safeData.cart_type === undefined" class="text-btn" :style="{
                    backgroundColor: safeData.cart_color || '#FF4A4A',
                    color: '#fff',
                    borderRadius: safeData.is_circle == 1 ? '15px' : '4px'
                  }">
                    {{ safeData.cart_text || '购买' }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Banner布局 (对应 admin goods_style == 0 && columns == 0) 但是 admin没有columns配置，先预留匹配App的扩展 -->
        <div v-else-if="layoutType === 0 && safeData.columns === 0" class="layout-banner">
          <div v-for="(goods, index) in currentGoodsList" :key="index" class="goods-card banner-item">
            <el-image :src="goods.cover_pic" fit="cover" class="banner-img" :style="goodsHeight">
              <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
            </el-image>
            <div class="banner-overlay">
              <div class="banner-title">{{ goods.name }}</div>
              <div class="banner-bottom flex-x-between flex-y-center">
                <div class="flex-col">
                  <div class="price-box" :style="{ color: safeData.price_color || '#FF4A4A' }">
                    <span class="symbol" v-if="goods.is_negotiable != 1">￥</span>
                    <span class="amount">{{ goods.is_negotiable == 1 ? '面议' : shownPrice(goods.price || '99.00') }}</span>
                  </div>
                  <div class="sales-box banner-sales" v-if="safeData.show_sales == 1"
                    style="margin-bottom: 0; margin-top: 2px;">
                    <span class="sales-text">{{ safeData.sales_text || '已售' }} {{ goods.virtual_sales || 0 }}</span>
                  </div>
                </div>
                <div class="btn-wrap" v-if="safeData.show_buy_btn != 0">
                  <img :src="safeData.cart_pic" v-if="safeData.cart_type == 1" class="buybtn-icon" />
                  <div v-if="safeData.cart_type == 0 || safeData.cart_type === undefined" class="text-btn"
                    :style="{ backgroundColor: safeData.cart_color || '#FF4A4A', color: '#fff', borderRadius: safeData.is_circle == 1 ? '15px' : '4px' }">
                    {{ safeData.cart_text || '购买' }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 列表布局 (对应 admin goods_style == 2) -->
        <div v-else-if="layoutType === 2 || (layoutType === 0 && safeData.columns === 1)" class="layout-list">
          <div class="goods-card list-item flex-row" v-for="(goods, index) in currentGoodsList" :key="index">
            <div class="list-img-wrap" :style="goodsHeight">
              <el-image :src="goods.cover_pic" fit="cover" class="img">
                <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
              </el-image>
            </div>
            <div class="list-info-wrap flex-col flex-x-between">
              <div>
                <div class="title">{{ goods.name }}</div>
              </div>
              <div class="bottom-action flex-x-between flex-y-center">
                <div class="flex-col">
                  <div class="price-box" :style="{ color: safeData.price_color || '#FF4A4A' }">
                    <span class="symbol" v-if="goods.is_negotiable != 1">￥</span>
                    <span class="amount">{{ goods.is_negotiable == 1 ? '面议' : shownPrice(goods.price || '99.00') }}</span>
                  </div>
                  <div class="sales-box" v-if="safeData.show_sales == 1" style="margin-bottom: 0; margin-top: 2px;">
                    <span class="sales-text">{{ safeData.sales_text || '已售' }} {{ goods.virtual_sales || 0 }}</span>
                  </div>
                </div>
                <!-- 购买按钮 -->
                <div class="btn-wrap" v-if="safeData.show_buy_btn != 0">
                  <img :src="safeData.cart_pic" v-if="safeData.cart_type == 1" class="buybtn-icon" />
                  <div v-if="safeData.cart_type == 0 || safeData.cart_type === undefined" class="text-btn"
                    :style="{ backgroundColor: safeData.cart_color || '#FF4A4A', color: '#fff', borderRadius: safeData.is_circle == 1 ? '15px' : '4px' }">
                    {{ safeData.cart_text || '购买' }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 网格布局 (双排+多排默认 admin goods_style == 0) -->
        <div v-else class="layout-grid" :style="{ gridTemplateColumns: `repeat(${safeData.columns || 2}, 1fr)` }">
          <div class="goods-card grid-item" v-for="(goods, index) in currentGoodsList" :key="index">
            <div class="img-wrap" :style="goodsHeight">
              <el-image :src="goods.cover_pic" fit="cover" class="img">
                <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
              </el-image>
            </div>
            <div class="info-wrap">
              <div class="title">{{ goods.name }}</div>
              <div class="bottom-action flex-x-between flex-y-center">
                <div class="flex-col">
                  <div class="price-box" :style="{ color: safeData.price_color || '#FF4A4A' }">
                    <span class="symbol" v-if="goods.is_negotiable != 1">￥</span>
                    <span class="amount">{{ goods.is_negotiable == 1 ? '面议' : shownPrice(goods.price || '99.00') }}</span>
                  </div>
                  <div class="sales-box" v-if="safeData.show_sales == 1" style="margin-bottom: 0; margin-top: 2px;">
                    <span class="sales-text">{{ safeData.sales_text || '已售' }} {{ goods.virtual_sales || 0 }}</span>
                  </div>
                </div>
                <div class="btn-wrap" v-if="safeData.show_buy_btn != 0">
                  <img :src="safeData.cart_pic" v-if="safeData.cart_type == 1" class="buybtn-icon" />
                  <div v-if="safeData.cart_type == 0 || safeData.cart_type === undefined" class="text-btn"
                    :style="{ backgroundColor: safeData.cart_color || '#FF4A4A', color: '#fff', borderRadius: safeData.is_circle == 1 ? '15px' : '4px' }">
                    {{ safeData.cart_text || '购买' }}
                  </div>
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
  name: 'TabGoods',
  props: {
    data: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      activeTab: 0
    }
  },
  computed: {
    safeData() {
      return this.data || {};
    },
    list() {
      return this.safeData.list || [];
    },
    currentGoodsList() {
      if (this.list && this.list[this.activeTab]) {
        return this.list[this.activeTab].goods_list || [];
      }
      return [];
    },
    layoutType() {
      return parseInt(this.safeData.goods_style) || 0;
    },
    goodsHeight() {
      // 在 admin 中由于缩放比例不同，这里采用 px
      return `height: ${this.safeData.cover_pic_height ? (this.safeData.cover_pic_height / 2) : 170}px;`;
    },
    outerStyle() {
      const padding = this.safeData.is_padding == 1 ? '0 12px' : '0';
      return {
        padding: padding,
        backgroundColor: '#f5f7fa',
        boxSizing: 'border-box',
        width: '100%'
      };
    },
    innerStyle() {
      const radius = this.safeData.is_circle == 1 ? '10px' : '0';
      return {
        borderRadius: radius,
        backgroundColor: this.safeData.background || 'transparent',
        overflow: 'hidden'
      };
    }
  },
  methods: {
    // 简约卡片:整数价不带「.00」(和手机端同规则)
    shownPrice(price) {
      const s = String(price == null ? '' : price)
      return this.safeData.card_style === 'minimal' ? s.replace(/\.0+$/, '') : s
    },
    getTabStyle(index) {
      const isActive = this.activeTab === index;
      return {
        color: isActive ? (this.safeData.active_color || '#333333') : (this.safeData.inactive_color || '#999999')
      };
    },
    getScrollItemWidth() {
      const col = this.safeData.columns || 2;
      if (col == 1) return { width: '85%' };
      if (col == 2) return { width: '45%' };
      if (col == 3) return { width: '30%' };
      return { width: '45%' }; // default
    }
  }
}
</script>

<style lang="scss" scoped>
/* 隐藏滚动条 */
::-webkit-scrollbar {
  display: none;
  width: 0;
  height: 0;
  color: transparent;
}

.premium-goods-container {
  box-sizing: border-box;
  width: 100%;
}

/* ================= 1. 高级感 Tabs ================= */
.premium-tabs {
  white-space: nowrap;
  width: 100%;
  padding: 10px 0;
  overflow-x: auto;
  border-bottom: 1px solid #f0f0f0;

  .tab-item {
    display: inline-block;
    padding: 0 16px;
    position: relative;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;

    .tab-text {
      font-size: 14px;
      transition: all 0.3s;
    }

    &.active {
      .tab-text {
        font-size: 16px;
        font-weight: 600;
      }
    }

    .tab-line {
      position: absolute;
      bottom: -10px;
      left: 50%;
      transform: translateX(-50%);
      width: 20px;
      height: 3px;
      border-radius: 3px;
    }
  }
}

.goods-content {
  margin-top: 10px;
  padding-bottom: 10px;
}

.empty-tip {
  padding: 30px 0;
  text-align: center;
  color: #909399;
  font-size: 12px;

  i {
    font-size: 24px;
    margin-bottom: 5px;
  }
}

/* ================= 2. 通用商品卡片样式 ================= */
.goods-card {
  background-color: #FFFFFF;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
  box-sizing: border-box;

  .img-wrap {
    width: 100%;
    overflow: hidden;
    background-color: #f8f8f8;
    position: relative;

    .img {
      width: 100%;
      height: 100%;
      display: block;
    }

    .image-slot {
      display: flex;
      justify-content: center;
      align-items: center;
      width: 100%;
      height: 100%;
      background: #f5f7fa;
      color: #909399;
      font-size: 24px;
    }
  }

  .info-wrap {
    padding: 10px;
  }

  .title {
    font-size: 14px;
    color: #222222;
    line-height: 20px;
    height: 40px;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    margin-bottom: 6px;
  }

  .price-box {
    font-weight: bold;
    display: flex;
    align-items: baseline;

    .symbol {
      font-size: 12px;
      margin-right: 1px;
    }

    .amount {
      font-size: 16px;
    }
  }
}

/* 购物车按钮 */
.btn-wrap {
  .buybtn-icon {
    width: 26px;
    height: 26px;
    display: block;
  }

  .text-btn {
    padding: 3px 10px;
    font-size: 12px;
    font-weight: 500;
    white-space: nowrap;
  }
}

/* ================= 3. 各布局定制 ================= */

/* 横向滑动布局 */
.scroll-row-view {
  white-space: nowrap;
  width: 100%;
  padding-bottom: 5px;
  overflow-x: auto;

  .scroll-item {
    display: inline-block;
    vertical-align: top;
    margin-right: 10px;
    white-space: normal;
  }
}

/* 大图横幅布局 (Banner模式) */
.layout-banner {
  .banner-item {
    position: relative;
    margin-bottom: 12px;

    .banner-img {
      width: 100%;
      display: block;
    }

    .banner-overlay {
      position: absolute;
      bottom: 0;
      left: 0;
      width: 100%;
      background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0) 100%);
      padding: 20px 12px 12px;
      box-sizing: border-box;

      .banner-title {
        color: #FFFFFF;
        font-size: 15px;
        font-weight: bold;
        margin-bottom: 8px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
    }
  }
}

/* 单列列表布局 (左图右文) */
.layout-list {
  .list-item {
    margin-bottom: 12px;
    padding: 10px;

    .list-img-wrap {
      width: 110px;
      border-radius: 6px;
      overflow: hidden;
      flex-shrink: 0;

      .img {
        width: 100%;
        height: 100%;
      }
    }

    .list-info-wrap {
      flex: 1;
      padding-left: 12px;

      .title {
        height: auto;
        max-height: 40px;
      }
    }
  }
}

/* 双列/多列网格布局 */
.layout-grid {
  display: grid;
  grid-gap: 10px;

  .grid-item {
    width: 100%;
    margin-bottom: 0;
  }
}

/* Flex 辅助类 */
.flex-row {
  display: flex;
  flex-direction: row;
}

.flex-col {
  display: flex;
  flex-direction: column;
}

.flex-x-between {
  display: flex;
  justify-content: space-between;
}

.flex-y-center {
  display: flex;
  align-items: center;
}

/* ================= 4. 销量展示高级样式 ================= */
.sales-box {
  display: inline-flex;
  align-items: center;
  background-color: #F6F6F6;
  padding: 2px 7px;
  border-radius: 4px;
  margin-bottom: 8px;

  .sales-text {
    font-size: 11px;
    color: #A0A0A0;
    line-height: 1.2;
  }
}

.banner-sales {
  background-color: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(4px);
  border: 1px solid rgba(255, 255, 255, 0.25);
  border-radius: 4px;

  .sales-text {
    color: #FFFFFF;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
  }
}

/* 简约卡片:和手机端 tui-tab-goods 的 .card-minimal 同一口径 */
.card-minimal {
  .goods-card {
    border: none !important;
    box-shadow: 0 2px 8px rgba(42, 37, 34, 0.08) !important;
  }
  .title {
    font-size: 12px;
    line-height: 17px;
    color: #3a3532;
    font-weight: 400;
  }
  .price-box {
    font-family: "Avenir Next Condensed", "DIN Condensed", "DIN Alternate", "Helvetica Neue", sans-serif;
    font-weight: 800;
  }
}
</style>
