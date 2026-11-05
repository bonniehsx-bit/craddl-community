<template>
  <div class="theme-wrapper theme-aurora">
    <!-- 1. 极光背景动画 -->
    <div class="aurora-bg">
      <div class="aurora-item item-1" :style="{ background: auroraColor1 }"></div>
      <div class="aurora-item item-2" :style="{ background: auroraColor2 }"></div>
      <div class="aurora-item item-3"></div>
      <div class="white-mask"></div>
    </div>

    <div class="mobile-body">

      <div class="content-wrapper">
        <!-- 3. 用户卡片 (包含头像、头部统计、资产) -->
        <div class="glass-card animate-enter"
          :class="{ 'active-block': activeBlock === 'bg' || activeBlock === 'finance' }" @click.stop="onEdit('bg')">

          <!-- 3.1 个人信息 -->
          <div class="card-content">
            <div class="user-section">
              <el-avatar :size="60" :src="data.header.avatar || ''" icon="el-icon-user-solid"
                class="avatar"></el-avatar>
              <div class="info-wrap">
                <div class="nickname">用户昵称</div>
                <div class="id-row">ID: 888888 <span class="tag">VIP1</span></div>
              </div>
            </div>
            <div class="checkin-btn" style="display: none;">签到</div>
          </div>

          <!-- 3.2 头部统计数据 (收藏/订单数/足迹) - 属于 bg 模块 -->
          <div class="stats-row" v-if="hasHeaderStats">
            <div class="stat-item" v-if="data.header.goods && data.header.goods.is_show == 1">
              <span class="num">12</span>
              <span class="lbl">{{ data.header.goods.name }}</span>
            </div>
            <div class="stat-item" v-if="data.header.order && data.header.order.is_show == 1">
              <span class="num">5</span>
              <span class="lbl">{{ data.header.order.name }}</span>
            </div>
            <div class="stat-item" v-if="data.header.footmark && data.header.footmark.is_show == 1">
              <span class="num">108</span>
              <span class="lbl">{{ data.header.footmark.name }}</span>
            </div>
          </div>

          <!-- 3.3 资产栏 (余额/积分/优惠券/佣金) - 属于 finance 模块 -->
          <!-- 使用 .stop 阻止冒泡，点击这里单独切换到 finance 配置 -->
          <div class="assets-box" v-if="data.finance.is_show == 1" @click.stop="onEdit('finance')"
            :class="{ 'active-inner': activeBlock === 'finance' }">

            <div class="asset-col" v-if="data.finance.balance && data.finance.balance.is_show == 1">
              <span class="num" :style="data.finance.balance.color ? { color: data.finance.balance.color } : {}">0.00</span><span class="lbl">{{ data.finance.balance.name }}</span>
            </div>

            <div class="asset-col" v-if="data.finance.integral && data.finance.integral.is_show == 1">
              <span class="num" :style="data.finance.integral.color ? { color: data.finance.integral.color } : {}">100</span><span class="lbl">{{ data.finance.integral.name }}</span>
            </div>

            <div class="asset-col" v-if="data.finance.coupon && data.finance.coupon.is_show == 1">
              <span class="num" :style="data.finance.coupon.color ? { color: data.finance.coupon.color } : {}">2</span><span class="lbl">{{ data.finance.coupon.name }}</span>
            </div>

            <div class="asset-col" v-if="data.finance.price && data.finance.price.is_show == 1">
              <span class="num" :style="data.finance.price.color ? { color: data.finance.price.color } : {}">88.5</span><span class="lbl">{{ data.finance.price.name }}</span>
            </div>
          </div>
        </div>

        <!-- 4. 订单模块 -->
        <div class="panel-card animate-enter delay-1" @click="onEdit('order')" v-if="data.order && data.order.is_show != 0"
          :class="{ 'active-block': activeBlock === 'order' }">
          <div class="panel-header">
            <span class="title">{{ data.order.title }}</span>
            <span class="more">{{ data.order.sub_title }} <i class="el-icon-arrow-right"></i></span>
          </div>
          <div class="grid-layout">
            <!-- 循环显示配置的订单状态 -->
            <div class="grid-item" v-for="(key, index) in orderStatusList" :key="key"
              v-if="data.order[key] && data.order[key].is_show == 1">
              <div class="icon-container">
                <el-image :src="data.order[key].icon" class="main-icon" fit="contain">
                  <div slot="error" class="image-slot"><i class="el-icon-s-order"></i></div>
                </el-image>
                <!-- 呼吸灯光效 -->
                <div class="icon-breathing-glow" :style="{ background: getOrderColor(key) }"></div>
              </div>
              <span class="grid-label">{{ data.order[key].name }}</span>
            </div>

            <!-- 售后/退款单独处理 (status_4) -->
            <div class="grid-item" v-if="data.order.status_4 && data.order.status_4.is_show == 1">
              <div class="icon-container">
                <el-image :src="data.order.status_4.icon" class="main-icon" fit="contain">
                  <div slot="error" class="image-slot"><i class="el-icon-refresh-left"></i></div>
                </el-image>
                <div class="icon-breathing-glow" :style="{ background: getOrderColor('status_4') }"></div>
              </div>
              <span class="grid-label">{{ data.order.status_4.name }}</span>
            </div>
          </div>
        </div>

        <!-- 5. 常用工具栏 1 -->
        <div class="panel-card animate-enter delay-2" @click="onEdit('menus')" v-if="data.menus.is_show == 1"
          :class="{ 'active-block': activeBlock === 'menus' }">
          <div class="panel-header"><span class="title">{{ data.menus.name }}</span></div>

          <!-- 宫格布局 -->
          <div class="grid-layout col-4" v-if="data.menus.type == 0">
            <div class="grid-item" v-for="(item, i) in data.menus.list" :key="i">
              <el-image :src="item.icon" class="tool-img" fit="contain">
                <div slot="error"><i class="el-icon-menu"></i></div>
              </el-image>
              <span class="grid-label">{{ item.name }}</span>
            </div>
          </div>

          <!-- 列表布局 -->
          <div class="list-layout" v-else>
            <div class="list-item" v-for="(item, i) in data.menus.list" :key="i">
              <span class="left">
                <el-image :src="item.icon" class="icon">
                  <div slot="error"><i class="el-icon-menu"></i></div>
                </el-image>
                {{ item.name }}
              </span>
              <i class="el-icon-arrow-right"></i>
            </div>
          </div>
        </div>

        <!-- 6. 常用工具栏 2 -->
        <div class="panel-card animate-enter delay-2" @click="onEdit('menus2')" v-if="data.menus2.is_show == 1"
          :class="{ 'active-block': activeBlock === 'menus2' }">
          <div class="panel-header"><span class="title">{{ data.menus2.name }}</span></div>

          <div class="grid-layout col-4" v-if="data.menus2.type == 0">
            <div class="grid-item" v-for="(item, i) in data.menus2.list" :key="i">
              <el-image :src="item.icon" class="tool-img" fit="contain">
                <div slot="error"><i class="el-icon-menu"></i></div>
              </el-image>
              <span class="grid-label">{{ item.name }}</span>
            </div>
          </div>

          <div class="list-layout" v-else>
            <div class="list-item" v-for="(item, i) in data.menus2.list" :key="i">
              <span class="left">
                <el-image :src="item.icon" class="icon">
                  <div slot="error"><i class="el-icon-menu"></i></div>
                </el-image>
                {{ item.name }}
              </span>
              <i class="el-icon-arrow-right"></i>
            </div>
          </div>
        </div>

        <!-- 7. 底部版权 -->
        <div class="footer-copyright-preview" @click="onEdit('copyright')"
          :class="{ 'active-block': activeBlock === 'copyright' }" v-if="data.copyright && data.copyright.is_show == 1">
          <span>{{ data.copyright.text }}</span>
        </div>

      </div>
    </div>

    <!-- 8. 底部导航 Tabbar -->
    <div class="mobile-tabbar" @click="onEdit('tabbar')" :class="{ 'active-block': activeBlock === 'tabbar' }">
      <div class="tab-item" v-for="(item, i) in data.tabbar.list" :key="i">
        <el-image class="icon" :src="i === 0 ? item.active_icon : item.icon" fit="contain">
          <div slot="error" class="image-slot"><i class="el-icon-s-home"></i></div>
        </el-image>
        <div class="text" :style="{ color: i === 0 ? data.tabbar.color : '#999' }">{{ item.name }}</div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'PreviewAurora',
  props: {
    // 接收父组件的 form 数据
    data: {
      type: Object,
      required: true
    },
    // 当前选中的模块 key，用于高亮显示
    activeBlock: {
      type: String,
      default: ''
    }
  },
  computed: {
    hasHeaderStats() {
      const h = this.data.header;
      return (h.goods && h.goods.is_show == 1) ||
        (h.order && h.order.is_show == 1) ||
        (h.footmark && h.footmark.is_show == 1);
    },
    auroraColor1() {
      return (this.data.colors && this.data.colors.header_from) || '#a18cd1';
    },
    auroraColor2() {
      return (this.data.colors && this.data.colors.header_to) || '#fad0c4';
    }
  },
  data() {
    return {
      // 订单状态 key 列表 (对应 form.order 中的 key)
      // 注意：这里排除了售后(status_4)单独显示，取消(status_5)一般不显示在常用宫格中
      orderStatusList: ['status_0', 'status_1', 'status_2', 'status_3']
    };
  },
  methods: {
    // 点击触发父组件切换编辑项
    onEdit(key) {
      this.$emit('edit', key);
    },
    // 获取订单呼吸灯颜色
    getOrderColor(key) {
      const colors = {
        'status_0': '#FF9F43', // 待付款 - 橙
        'status_1': '#54A0FF', // 待发货 - 蓝
        'status_2': '#00D2D3', // 待收货 - 青
        'status_3': '#5F27CD', // 待评价 - 紫
        'status_4': '#FF6B6B', // 售后 - 红
        'status_5': '#999999'  // 取消 - 灰
      };
      return colors[key] || '#999';
    }
  }
}
</script>

<style lang="scss" scoped>
/* 变量定义 */
$aurora-text: #2C3E50;
$text-sub: #95A5A6;
$bg-color: #F8F9FB;

/* 根容器：确保占满父级 */
.theme-aurora {
  height: 100%;
  position: relative;
  background-color: $bg-color;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
  overflow: hidden;
}

/* 高亮选中样式 */
.active-block {
  border: 2px solid var(--admin-accent-text) !important;
  box-shadow: 0 0 10px rgba(var(--admin-accent-rgb), 0.4) !important;
  position: relative;
  z-index: 20;
}

/* 内部选中 (如资产栏) */
.active-inner {
  border: 1px dashed var(--admin-accent-text) !important;
  background: rgba(var(--admin-accent-rgb), 0.05);
}

/* 极光动画背景 */
.aurora-bg {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 350px;
  overflow: hidden;
  z-index: 0;
  background: #fff;

  .aurora-item {
    position: absolute;
    border-radius: 50%;
    filter: blur(40px);
    opacity: 0.6;
  }

  .item-1 {
    width: 250px;
    height: 250px;
    top: -50px;
    left: -50px;
    animation: move1 12s infinite;
  }

  .item-2 {
    width: 200px;
    height: 200px;
    top: 0;
    right: -25px;
    animation: move2 15s infinite;
  }

  .item-3 {
    width: 150px;
    height: 150px;
    background: #a1c4fd;
    bottom: 50px;
    left: 20%;
    opacity: 0.5;
    animation: move3 10s infinite;
  }

  .white-mask {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 100px;
    background: linear-gradient(to bottom, transparent, $bg-color);
    z-index: 1;
  }
}

@keyframes move1 {
  0% {
    transform: translate(0, 0) scale(1);
  }

  33% {
    transform: translate(25px, 15px) scale(1.1);
  }

  66% {
    transform: translate(-10px, 25px) scale(0.9);
  }

  100% {
    transform: translate(15px, -10px) scale(1);
  }
}

@keyframes move2 {
  0% {
    transform: translate(0, 0) scale(1);
  }

  50% {
    transform: translate(-20px, 20px) scale(1.2);
  }

  100% {
    transform: translate(10px, -15px) scale(1);
  }
}

@keyframes move3 {
  0% {
    transform: translate(0, 0);
    opacity: 0.4;
  }

  50% {
    transform: translate(15px, -15px);
    opacity: 0.6;
  }

  100% {
    transform: translate(-15px, 15px);
    opacity: 0.4;
  }
}


/* 滚动区域 */
.mobile-body {
  position: relative;
  height: 100%;
  overflow-y: auto;
  padding-bottom: 60px;

  /* Tabbar height */
  /* 隐藏滚动条 */
  &::-webkit-scrollbar {
    display: none;
  }
}

.content-wrapper {
  position: relative;
  z-index: 10;
  padding: 20px 16px;
}

/* 玻璃态卡片 */
.glass-card {
  background: rgba(255, 255, 255, 0.65);
  backdrop-filter: blur(12px);
  border-radius: 20px;
  padding: 20px;
  margin-bottom: 15px;
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.04);
  cursor: pointer;
  border: 1px solid rgba(255, 255, 255, 0.8);
  transition: all 0.3s;

  .card-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
  }

  .user-section {
    display: flex;
    align-items: center;
  }

  .avatar {
    margin-right: 12px;
    border: 2px solid #fff;
    background: #fff;
  }

  .nickname {
    font-size: 18px;
    font-weight: bold;
    color: $aurora-text;
  }

  .id-row {
    font-size: 12px;
    color: $text-sub;
    margin-top: 4px;

    .tag {
      background: #333;
      color: gold;
      padding: 1px 4px;
      border-radius: 4px;
      margin-left: 5px;
    }
  }

  .checkin-btn {
    background: #fff;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
  }

  /* 头部数据行 (收藏/足迹) */
  .stats-row {
    display: flex;
    justify-content: space-around;
    padding-bottom: 15px;

    .stat-item {
      text-align: center;

      .num {
        display: block;
        font-size: 16px;
        font-weight: bold;
        color: $aurora-text;
      }

      .lbl {
        font-size: 11px;
        color: #888;
        margin-top: 2px;
      }
    }
  }

  /* 资产行 */
  .assets-box {
    display: flex;
    padding-top: 15px;
    border-top: 1px solid rgba(0, 0, 0, 0.05);
    border-radius: 8px;
    padding-bottom: 5px;
    transition: background 0.2s;

    .asset-col {
      flex: 1;
      text-align: center;

      .num {
        display: block;
        font-weight: bold;
        font-size: 16px;
        color: $aurora-text;
      }

      .lbl {
        font-size: 11px;
        color: #999;
        margin-top: 4px;
      }
    }
  }
}

/* 面板卡片 */
.panel-card {
  background: #fff;
  border-radius: 16px;
  padding: 16px 10px;
  margin-bottom: 12px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
  cursor: pointer;
  border: 2px solid transparent;

  .panel-header {
    display: flex;
    justify-content: space-between;
    padding: 0 10px 10px;
    font-weight: bold;
    color: $aurora-text;

    .more {
      font-size: 12px;
      font-weight: normal;
      color: #999;
    }
  }
}

/* Grid 布局 */
.grid-layout {
  display: flex;
  flex-wrap: wrap;

  &.col-4 .grid-item {
    width: 25%;
  }

  .grid-item {
    width: 20%;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 10px 0;

    .icon-container {
      position: relative;
      width: 44px;
      height: 44px;
      display: flex;
      align-items: center;
      justify-content: center;

      .main-icon {
        width: 28px;
        height: 28px;
        z-index: 2;
      }

      .icon-breathing-glow {
        position: absolute;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        bottom: 6px;
        opacity: 0.3;
        filter: blur(6px);
        z-index: 1;
      }
    }

    .grid-label {
      font-size: 12px;
      color: #555;
      margin-top: 4px;
      text-align: center;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      width: 100%;
    }

    .tool-img {
      width: 28px;
      height: 28px;
      margin-bottom: 5px;
    }
  }
}

/* 列表布局 */
.list-layout {
  .list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 10px;
    border-bottom: 1px solid #f5f7fa;

    &:last-child {
      border-bottom: none;
    }

    .left {
      display: flex;
      align-items: center;
      font-size: 13px;
      color: $aurora-text;

      .icon {
        width: 20px;
        height: 20px;
        margin-right: 10px;
      }
    }

    i {
      color: #ccc;
    }
  }
}

/* 底部版权 */
.footer-copyright-preview {
  text-align: center;
  padding: 20px 0;
  font-size: 10px;
  letter-spacing: 1px;
  color: #ccc;
  cursor: pointer;
  border: 2px solid transparent;
}

/* 底部 Tabbar */
.mobile-tabbar {
  position: absolute;
  bottom: 0;
  left: 0;
  width: 100%;
  height: 50px;
  background: #fff;
  border-top: 1px solid #eee;
  display: flex;
  z-index: 99;
  cursor: pointer;
  border: 2px solid transparent;

  .tab-item {
    flex: 1;
    text-align: center;
    padding-top: 5px;

    .icon {
      width: 24px;
      height: 24px;
      display: block;
      margin: 0 auto;
    }

    .text {
      font-size: 10px;
      margin-top: 2px;
    }
  }
}

/* 动画类 */
.animate-enter {
  animation: fadeUp 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) forwards;
  opacity: 0;
  transform: translateY(30px);
}

.delay-1 {
  animation-delay: 0.1s;
}

.delay-2 {
  animation-delay: 0.2s;
}

@keyframes fadeUp {
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
