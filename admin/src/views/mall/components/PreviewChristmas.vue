<template>
  <div class="theme-wrapper theme-christmas" :style="christmasBgStyle">

    <!-- 1. 沉浸式飘雪背景 -->
    <div class="christmas-bg">
      <div class="snow-container">
        <div v-for="(flake, index) in snowflakes" :key="index" class="snowflake" :style="flake.styleStr"></div>
      </div>
    </div>

    <!-- 模拟状态栏 (已在父级 user.vue 中统一展示，此处移除) -->
    <!-- <div class="status-bar-mock">...</div> -->



    <div class="mobile-body">
      <div class="content-area">

        <!-- A. 用户信息卡片 -->
        <div class="christmas-card user-card animate-enter" @click="onEdit('bg')"
          :class="{ 'active-block': activeBlock === 'bg' }">
          <!-- 装饰：挂饰 -->
          <div class="decoration-ball"></div>

          <div class="user-main">
            <div class="avatar-wrapper">
              <!-- 装饰：圣诞帽 -->
              <div class="santa-hat"></div>
              <el-avatar :size="64" :src="data.header.avatar || ''" icon="el-icon-user-solid"
                class="avatar-img"></el-avatar>
            </div>
            <div class="user-info">
              <div class="greeting">Merry Christmas! 🎄</div>
              <div class="nickname">用户昵称</div>
              <div class="vip-tag">
                <i class="el-icon-trophy"></i> 黄金会员
              </div>
            </div>
            <div class="check-in-btn" style="display: none;">签到</div>
          </div>

          <!-- A.2 头部数据统计 (收藏/足迹/订单数) -->
          <!-- 这些数据通常属于 header (bg) 模块 -->
          <div class="user-stats-row" v-if="hasHeaderStats">
            <div class="stat-col" v-if="data.header.goods && data.header.goods.is_show == 1">
              <span class="n">12</span>
              <span class="t">{{ data.header.goods.name }}</span>
            </div>
            <div class="stat-col" v-if="data.header.footmark && data.header.footmark.is_show == 1">
              <span class="n">108</span>
              <span class="t">{{ data.header.footmark.name }}</span>
            </div>
            <div class="stat-col" v-if="data.header.order && data.header.order.is_show == 1">
              <span class="n">5</span>
              <span class="t">{{ data.header.order.name }}</span>
            </div>
          </div>
        </div>

        <!-- B. 钱包资产 (包含佣金) -->
        <!-- 注意：这里增加了 .stop 修饰符，防止点击冒泡到 bg -->
        <div class="christmas-card finance-card animate-enter delay-1" @click.stop="onEdit('finance')"
          :class="{ 'active-block': activeBlock === 'finance' }" v-if="data.finance.is_show == 1">

          <div class="card-title-sm">我的钱包</div>

          <div class="finance-grid">
            <!-- 余额 -->
            <div class="f-item" v-if="data.finance.balance.is_show == 1">
              <span class="num" :style="data.finance.balance.color ? { color: data.finance.balance.color } : {}">0.00</span>
              <span class="lbl">{{ data.finance.balance.name }}</span>
            </div>
            <div class="f-item" v-if="data.finance.price && data.finance.price.is_show == 1">
              <span class="num gold-text" :style="data.finance.price.color ? { color: data.finance.price.color } : {}">88.5</span>
              <span class="lbl">{{ data.finance.price.name }}</span>
            </div>
            <div class="f-item" v-if="data.finance.integral.is_show == 1">
              <span class="num" :style="data.finance.integral.color ? { color: data.finance.integral.color } : {}">100</span>
              <span class="lbl">{{ data.finance.integral.name }}</span>
            </div>
            <div class="f-item" v-if="data.finance.coupon.is_show == 1">
              <span class="num" :style="data.finance.coupon.color ? { color: data.finance.coupon.color } : {}">2</span>
              <span class="lbl">{{ data.finance.coupon.name }}</span>
            </div>
          </div>
        </div>

        <!-- C. 订单模块 -->
        <div class="christmas-card animate-enter delay-2" @click="onEdit('order')" v-if="data.order && data.order.is_show != 0"
          :class="{ 'active-block': activeBlock === 'order' }">
          <div class="card-header">
            <span class="title">{{ data.order.title }}</span>
            <span class="more">{{ data.order.sub_title }} <i class="el-icon-arrow-right"></i></span>
          </div>
          <div class="order-grid">
            <!-- 待付款 (红) -->
            <div class="o-item" v-if="data.order.status_0.is_show == 1">
              <div class="icon-box bg-red">
                <el-image :src="data.order.status_0.icon" class="img" fit="contain">
                  <div slot="error"><i class="el-icon-wallet"></i></div>
                </el-image>
                <div class="badge">1</div>
              </div>
              <span class="name">{{ data.order.status_0.name }}</span>
            </div>
            <!-- 待发货 (绿) -->
            <div class="o-item" v-if="data.order.status_1.is_show == 1">
              <div class="icon-box bg-green">
                <el-image :src="data.order.status_1.icon" class="img" fit="contain">
                  <div slot="error"><i class="el-icon-box"></i></div>
                </el-image>
              </div>
              <span class="name">{{ data.order.status_1.name }}</span>
            </div>
            <!-- 待收货 (金) -->
            <div class="o-item" v-if="data.order.status_2.is_show == 1">
              <div class="icon-box bg-gold">
                <el-image :src="data.order.status_2.icon" class="img" fit="contain">
                  <div slot="error"><i class="el-icon-truck"></i></div>
                </el-image>
              </div>
              <span class="name">{{ data.order.status_2.name }}</span>
            </div>
            <!-- 待评价 (银) -->
            <div class="o-item" v-if="data.order.status_3.is_show == 1">
              <div class="icon-box bg-silver">
                <el-image :src="data.order.status_3.icon" class="img" fit="contain">
                  <div slot="error"><i class="el-icon-chat-line-square"></i></div>
                </el-image>
              </div>
              <span class="name">{{ data.order.status_3.name }}</span>
            </div>
            <!-- 售后 -->
            <div class="o-item" v-if="data.order.status_4.is_show == 1">
              <div class="icon-box bg-gray">
                <el-image :src="data.order.status_4.icon" class="img" fit="contain">
                  <div slot="error"><i class="el-icon-service"></i></div>
                </el-image>
              </div>
              <span class="name">{{ data.order.status_4.name }}</span>
            </div>
          </div>
        </div>

        <!-- D. 菜单栏 1 -->
        <div class="christmas-card animate-enter delay-3" @click="onEdit('menus')" v-if="data.menus.is_show == 1"
          :class="{ 'active-block': activeBlock === 'menus' }">
          <div class="card-header"><span class="title">{{ data.menus.name }}</span></div>
          <div class="menu-grid" v-if="data.menus.type == 0">
            <div class="m-item" v-for="(item, i) in data.menus.list" :key="i">
              <el-image :src="item.icon" class="menu-icon" fit="contain">
                <div slot="error"><i class="el-icon-menu"></i></div>
              </el-image>
              <span class="menu-name">{{ item.name }}</span>
            </div>
          </div>
          <div class="menu-list" v-else>
            <div class="ml-item" v-for="(item, i) in data.menus.list" :key="i">
              <div class="left"><el-image :src="item.icon" class="icon"></el-image> {{ item.name }}</div>
              <i class="el-icon-arrow-right"></i>
            </div>
          </div>
        </div>

        <!-- E. 菜单栏 2 -->
        <div class="christmas-card animate-enter delay-3" @click="onEdit('menus2')" v-if="data.menus2.is_show == 1"
          :class="{ 'active-block': activeBlock === 'menus2' }">
          <div class="card-header"><span class="title">{{ data.menus2.name }}</span></div>
          <div class="menu-grid" v-if="data.menus2.type == 0">
            <div class="m-item" v-for="(item, i) in data.menus2.list" :key="i">
              <el-image :src="item.icon" class="menu-icon" fit="contain">
                <div slot="error"><i class="el-icon-menu"></i></div>
              </el-image>
              <span class="menu-name">{{ item.name }}</span>
            </div>
          </div>
          <div class="menu-list" v-else>
            <div class="ml-item" v-for="(item, i) in data.menus2.list" :key="i">
              <div class="left"><el-image :src="item.icon" class="icon"></el-image> {{ item.name }}</div>
              <i class="el-icon-arrow-right"></i>
            </div>
          </div>
        </div>

        <!-- F. 底部版权 -->
        <div class="footer-copyright-preview" @click="onEdit('copyright')"
          :class="{ 'active-block': activeBlock === 'copyright' }" v-if="data.copyright && data.copyright.is_show == 1">
          <span>{{ data.copyright.text }}</span>
        </div>

        <div style="height: 20px;"></div>
      </div>
    </div>

    <!-- 底部 Tabbar -->
    <div class="mobile-tabbar" @click="onEdit('tabbar')" :class="{ 'active-block': activeBlock === 'tabbar' }">
      <div class="tab-item" v-for="(item, i) in data.tabbar.list" :key="i">
        <el-image class="icon" :src="i === 0 ? item.active_icon : item.icon" fit="contain">
          <div slot="error"><i class="el-icon-s-home"></i></div>
        </el-image>
        <div class="text" :style="{ color: i === 0 ? data.tabbar.color : '#999' }">{{ item.name }}</div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'PreviewChristmas',
  props: {
    data: {
      type: Object,
      required: true
    },
    activeBlock: {
      type: String,
      default: ''
    }
  },
  computed: {
    hasHeaderStats() {
      const h = this.data.header;
      return (h.goods && h.goods.is_show == 1) ||
        (h.footmark && h.footmark.is_show == 1) ||
        (h.order && h.order.is_show == 1);
    },
    christmasBgStyle() {
      const c = this.data.colors;
      const from = (c && c.header_from) || '#D32F2F';
      const to = (c && c.header_to) || '#B71C1C';
      return {
        background: `linear-gradient(180deg, ${from} 0%, ${to} 40%, #F5F5F5 40%, #F5F5F5 100%)`
      };
    }
  },
  data() {
    return {
      snowflakes: [],
    };
  },
  mounted() {
    this.createSnowflakes();
  },
  methods: {
    onEdit(key) {
      this.$emit('edit', key);
    },
    createSnowflakes() {
      // 生成随机雪花参数
      const list = [];
      const count = 35;
      for (let i = 0; i < count; i++) {
        const left = Math.random() * 100;
        const size = Math.random() * 4 + 2; // 大小 2-6px
        const duration = Math.random() * 5 + 5; // 速度 5-10s
        const delay = Math.random() * 5; // 延迟
        const opacity = Math.random() * 0.5 + 0.3;
        const styleStr = `left:${left}%; width:${size}px; height:${size}px; animation-duration:${duration}s; animation-delay:${delay}s; opacity:${opacity};`;
        list.push({ styleStr });
      }
      this.snowflakes = list;
    }
  }
}
</script>

<style lang="scss" scoped>
/* 圣诞配色定义 */
$red-primary: #C62828;
$red-dark: #8E0000;
$green-accent: #2E7D32;
$gold-accent: #FFC107;
$bg-gradient: linear-gradient(180deg, #D32F2F 0%, #B71C1C 40%, #F5F5F5 40%, #F5F5F5 100%);
$snow-white: #FFFFFF;

.theme-christmas {
  height: 100%;
  position: relative;
  font-family: -apple-system, sans-serif;
  overflow: hidden;

  /* 1. 雪花容器 */
  .christmas-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 350px;
    /* 只在红色区域下雪 */
    pointer-events: none;
    z-index: 1;
    overflow: hidden;
  }

  .snowflake {
    position: absolute;
    top: -10px;
    background: #fff;
    border-radius: 50%;
    animation: fall linear infinite;
    filter: blur(0.5px);
  }

  @keyframes fall {
    to {
      transform: translateY(360px);
    }
  }

  /* 2. 状态栏 */
  .status-bar-mock {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 30px;
    padding: 0 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: #fff;
    z-index: 20;
    font-weight: 600;
  }



  /* 4. 滚动区域 */
  .mobile-body {
    height: 100%;
    overflow-y: auto;
    padding-bottom: 60px;
    /* Tabbar高度 */
    position: relative;
    z-index: 10;

    &::-webkit-scrollbar {
      display: none;
    }
  }

  .content-area {
    padding: 50px 15px 20px;
    /* 避开顶部空间 */
  }

  /* 选中高亮: 金色光圈 */
  .active-block {
    border: 2px solid var(--admin-accent-text) !important;
    box-shadow: 0 0 12px rgba(var(--admin-accent-rgb), 0.6) !important;
    position: relative;
    z-index: 30;
  }

  /* --- 卡片通用样式 --- */
  .christmas-card {
    background: #fff;
    border-radius: 16px;
    margin-bottom: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
    border: 2px solid transparent;
    position: relative;
    overflow: hidden;

    /* 卡片顶部积雪装饰线 */
    &::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #fff, #eee, #fff);
      opacity: 0.5;
    }
  }

  /* --- A. 用户卡片 --- */
  .user-card {
    padding: 20px 20px 10px;
    background-image: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 20 L22 24 L18 24 Z' fill='%23f0f0f0' opacity='0.4'/%3E%3C/svg%3E");

    .decoration-ball {
      position: absolute;
      top: -10px;
      right: 30px;
      width: 20px;
      height: 40px;
      background: #FFD700;
      border-radius: 0 0 10px 10px;
      box-shadow: inset -2px -2px 6px rgba(0, 0, 0, 0.2);

      &::after {
        content: '';
        position: absolute;
        bottom: -5px;
        left: 5px;
        width: 10px;
        height: 10px;
        background: #C62828;
        border-radius: 50%;
      }
    }

    .user-main {
      display: flex;
      align-items: center;
      margin-bottom: 20px;

      .avatar-wrapper {
        position: relative;
        margin-right: 15px;

        .avatar-img {
          border: 2px solid $green-accent;
        }

        .santa-hat {
          position: absolute;
          top: -18px;
          left: -8px;
          width: 30px;
          height: 30px;
          z-index: 2;
          background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cpath fill='%23D32F2F' d='M10 90 Q50 10 90 90 Z'/%3E%3Ccircle fill='%23FFF' cx='90' cy='90' r='10'/%3E%3C/svg%3E");
          background-size: contain;
          background-repeat: no-repeat;
          transform: rotate(-15deg);
        }
      }

      .user-info {
        flex: 1;

        .greeting {
          font-size: 11px;
          color: $green-accent;
          font-weight: bold;
          letter-spacing: 0.5px;
        }

        .nickname {
          font-size: 18px;
          font-weight: bold;
          color: #333;
          margin: 2px 0 4px;
        }

        .vip-tag {
          display: inline-block;
          font-size: 10px;
          background: #333;
          color: $gold-accent;
          padding: 2px 8px;
          border-radius: 10px;
        }
      }

      .check-in-btn {
        background: linear-gradient(135deg, $red-primary, $red-dark);
        color: #fff;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 12px;
        box-shadow: 0 4px 8px rgba(198, 40, 40, 0.3);
      }
    }

    /* 头部统计行 */
    .user-stats-row {
      display: flex;
      justify-content: space-around;
      padding-top: 15px;
      border-top: 1px dashed #eee;

      .stat-col {
        text-align: center;

        .n {
          display: block;
          font-size: 16px;
          font-weight: bold;
          color: #333;
        }

        .t {
          font-size: 11px;
          color: #999;
        }
      }
    }
  }

  /* --- B. 钱包卡片 --- */
  .finance-card {
    padding: 15px;

    .card-title-sm {
      font-size: 13px;
      font-weight: bold;
      margin-bottom: 12px;
      color: #333;
    }

    .finance-grid {
      display: flex;
      justify-content: space-between;

      .f-item {
        display: flex;
        flex-direction: column;
        align-items: center;

        .num {
          font-size: 16px;
          font-weight: bold;
          color: #333;
          margin-bottom: 4px;
          font-family: 'DIN', sans-serif;
        }

        .lbl {
          font-size: 11px;
          color: #666;
        }

        .gold-text {
          color: #E65100;
        }

        /* 佣金高亮 */
      }
    }
  }

  /* --- C. 订单/菜单 通用 --- */
  .card-header {
    padding: 15px 15px 5px;
    display: flex;
    justify-content: space-between;
    align-items: center;

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

  .order-grid,
  .menu-grid {
    display: flex;
    flex-wrap: wrap;
    padding: 10px 0 15px;

    .o-item,
    .m-item {
      width: 20%;
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 10px;

      .name,
      .menu-name {
        font-size: 11px;
        color: #666;
        margin-top: 6px;
        text-align: center;
      }

      .icon-box {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        transition: transform 0.2s;

        .img {
          width: 22px;
          height: 22px;
        }

        .badge {
          position: absolute;
          top: -5px;
          right: -5px;
          background: $red-primary;
          color: #fff;
          font-size: 10px;
          padding: 0 4px;
          border-radius: 8px;
          border: 1px solid #fff;
        }

        /* 圣诞氛围色块 */
        &.bg-red {
          background: rgba(198, 40, 40, 0.08);
        }

        &.bg-green {
          background: rgba(46, 125, 50, 0.08);
        }

        &.bg-gold {
          background: rgba(255, 193, 7, 0.12);
        }

        &.bg-silver {
          background: rgba(96, 125, 139, 0.08);
        }

        &.bg-gray {
          background: #f5f5f5;
        }
      }
    }

    .m-item {
      width: 25%;
    }

    /* 菜单4列 */
    .menu-icon {
      width: 28px;
      height: 28px;
      margin-bottom: 4px;
    }
  }

  /* 列表模式菜单 */
  .menu-list {
    padding: 5px 0;

    .ml-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 15px;
      border-bottom: 1px solid #f9f9f9;

      &:last-child {
        border-bottom: none;
      }

      .left {
        display: flex;
        align-items: center;
        font-size: 13px;
        color: #333;

        .icon {
          width: 20px;
          height: 20px;
          margin-right: 10px;
        }
      }

      i {
        color: #ccc;
        font-size: 12px;
      }
    }
  }

  /* 底部版权 */
  .footer-copyright-preview {
    text-align: center;
    color: #ccc;
    font-size: 10px;
    padding: 10px 0 20px;

    /* 选中高亮适配 */
    &.active-block {
      border: 1px dashed $gold-accent;
      border-radius: 4px;
    }
  }

  /* Tabbar */
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

    .tab-item {
      flex: 1;
      text-align: center;
      padding-top: 6px;

      .icon {
        width: 24px;
        height: 24px;
        margin: 0 auto;
        display: block;
      }

      .text {
        font-size: 10px;
        margin-top: 3px;
      }
    }
  }

  /* 进场动画 */
  .animate-enter {
    animation: slideUp 0.5s ease-out forwards;
    opacity: 0;
    transform: translateY(20px);
  }

  .delay-1 {
    animation-delay: 0.1s;
  }

  .delay-2 {
    animation-delay: 0.2s;
  }

  .delay-3 {
    animation-delay: 0.3s;
  }

  @keyframes slideUp {
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
}
</style>
