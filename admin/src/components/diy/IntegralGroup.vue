<template>
  <div class="diy-integral-group" :style="outerStyle">
    <div class="group-inner" :style="innerStyle">
      <!-- 预览模式静态展示占位符 -->
      <div class="preview-header" v-if="safeData.show_title != 0">
        <i class="el-icon-present"></i> {{ safeData.title || '积分商城专区' }}
      </div>
      
      <div class="grid-wrapper" v-if="safeData.type == 0">
        <div class="goods-item" v-for="i in Number(safeData.limit)" :key="i" :style="itemStyle">
          <div class="goods-card">
            <div class="img-placeholder">商品封面</div>
            <div class="info">
              <div class="title">测试积分商品标题...</div>
              <div class="price" :style="{ color: safeData.price_color }">
                <span v-if="safeData.show_price == 1">1000积分 + ￥10</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="list-wrapper" v-if="safeData.type == 1">
        <div class="list-item" v-for="i in Number(safeData.limit)" :key="i">
          <div class="img-placeholder">封面</div>
          <div class="info">
            <div class="title">测试积分商品标题...</div>
            <div class="price" :style="{ color: safeData.price_color }">
              <span v-if="safeData.show_price == 1">1000积分 + ￥10</span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'IntegralGroup',
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
    outerStyle() {
      const padding = this.safeData.is_padding == 1 ? '0 10px' : '0';
      return {
        padding: padding,
      };
    },
    innerStyle() {
      const radius = this.safeData.is_circle == 1 ? '10px' : '0';
      return {
        borderRadius: radius,
        backgroundColor: this.safeData.background || '#ffffff',
        overflow: 'hidden'
      };
    },
    itemStyle() {
      const columns = parseInt(this.safeData.columns) || 2;
      const widthPercent = (100 / columns);
      return {
        width: `${widthPercent}%`,
        padding: '5px'
      };
    }
  }
}
</script>

<style lang="scss" scoped>
.diy-integral-group {
  overflow: hidden;
  margin-bottom: 5px;
}
.group-inner {
  padding: 10px;
}
.preview-header {
  font-size: 14px;
  font-weight: bold;
  color: #333;
  margin-bottom: 10px;
  padding-bottom: 5px;
  border-bottom: 1px solid #f0f0f0;
  display: flex;
  align-items: center;
  i {
    margin-right: 5px;
    color: #ff4544;
  }
}

.grid-wrapper {
  display: flex;
  flex-wrap: wrap;
  margin: 0 -5px;
}
.goods-item {
  box-sizing: border-box;
}
.goods-card {
  background: #f8f8f8;
  border-radius: 4px;
  overflow: hidden;
}

.list-wrapper {
  display: flex;
  flex-direction: column;
}
.list-item {
  display: flex;
  background: #f8f8f8;
  border-radius: 4px;
  margin-bottom: 10px;
  padding: 8px;
}

.img-placeholder {
  width: 100%;
  height: 100px;
  background: #e0e0e0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #999;
  font-size: 12px;
}
.list-item .img-placeholder {
  width: 80px;
  height: 80px;
  flex-shrink: 0;
  margin-right: 10px;
}

.info {
  padding: 8px;
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}
.title {
  font-size: 12px;
  color: #333;
  line-height: 1.4;
  margin-bottom: 5px;
}
.price {
  font-size: 14px;
  font-weight: bold;
}
</style>
