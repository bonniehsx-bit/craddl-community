<template>
  <div class="theme-wrapper theme-premium">

    <!-- 1. 背景层：黑金流光 + 动态光斑 -->
    <div class="premium-bg" :style="premiumBgStyle">
      <div class="noise-texture"></div>
      <div class="gold-orb orb-1"></div>
      <div class="gold-orb orb-2"></div>
    </div>


    <div class="mobile-body">
      <div class="content-area">

        <!-- A. 尊享用户卡片 -->
        <div class="user-card-premium animate-enter" @click="onEdit('bg')"
          :class="{ 'active-block': activeBlock === 'bg' }">
          <div class="card-glass-layer">
            <div class="card-content">
              <div class="avatar-box">
                <div class="avatar-border">
                  <el-avatar :size="65" :src="data.header.avatar || ''" icon="el-icon-user-solid"
                    class="avatar-comp"></el-avatar>
                </div>
                <div class="crown-icon">👑</div>
              </div>
              <div class="info-box">
                <div class="nick-row">
                  <span class="nickname">用户昵称</span>
                  <div class="vip-badge">
                    <span class="vip-text">BLACK GOLD</span>
                    <div class="shine-effect"></div>
                  </div>
                </div>
                <div class="uid-row">
                  <span class="uid-text">ID: 888888</span>
                  <i class="el-icon-document-copy" style="font-size:10px; margin-left:4px; opacity:0.6;"></i>
                </div>
              </div>
            </div>

            <!-- A.2 头部数据 (收藏/足迹) -->
            <!-- 这些数据属于 header 模块 -->
            <div class="header-stats-row" v-if="hasHeaderStats">
              <div class="h-col" v-if="data.header.goods && data.header.goods.is_show == 1">
                <span class="n">12</span><span class="t">{{ data.header.goods.name }}</span>
              </div>
              <div class="h-col" v-if="data.header.footmark && data.header.footmark.is_show == 1">
                <span class="n">58</span><span class="t">{{ data.header.footmark.name }}</span>
              </div>
              <div class="h-col" v-if="data.header.order && data.header.order.is_show == 1">
                <span class="n">5</span><span class="t">{{ data.header.order.name }}</span>
              </div>
            </div>

            <!-- B. 资产数据 (融合在卡片内) -->
            <!-- 注意：增加了 .stop 防止冒泡 -->
            <div class="stats-row" @click.stop="onEdit('finance')"
              :class="{ 'active-inner-block': activeBlock === 'finance' }" v-if="data.finance.is_show == 1">

              <!-- 余额 -->
              <div class="stat-item" v-if="data.finance.balance.is_show == 1">
                <span class="num" :style="data.finance.balance.color ? { color: data.finance.balance.color } : {}">0.00</span><span class="lbl">{{ data.finance.balance.name }}</span>
              </div>

              <div class="stat-item" v-if="data.finance.price && data.finance.price.is_show == 1">
                <span class="num gold-text" :style="data.finance.price.color ? { color: data.finance.price.color } : {}">88.5</span><span class="lbl">{{ data.finance.price.name }}</span>
              </div>

              <div class="stat-item" v-if="data.finance.integral.is_show == 1">
                <span class="num" :style="data.finance.integral.color ? { color: data.finance.integral.color } : {}">100</span><span class="lbl">{{ data.finance.integral.name }}</span>
              </div>

              <div class="stat-item" v-if="data.finance.coupon.is_show == 1">
                <span class="num" :style="data.finance.coupon.color ? { color: data.finance.coupon.color } : {}">2</span><span class="lbl">{{ data.finance.coupon.name }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- C. 订单模块 (深色玻璃态) -->
        <div class="glass-section animate-enter delay-1" @click="onEdit('order')" v-if="data.order && data.order.is_show != 0"
          :class="{ 'active-block': activeBlock === 'order' }">
          <div class="section-header">
            <span class="title">{{ data.order.title }}</span>
            <div class="more">
              <span>{{ data.order.sub_title }}</span>
              <i class="el-icon-arrow-right" style="font-size: 12px;"></i>
            </div>
          </div>

          <div class="p-grid-wrap">
            <!-- 待付款 -->
            <div class="p-grid-item" v-if="data.order.status_0.is_show == 1">
              <div class="icon-box">
                <el-image :src="data.order.status_0.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-wallet"></i></div>
                </el-image>
                <div class="gold-badge">1</div>
              </div>
              <span class="grid-text">{{ data.order.status_0.name }}</span>
            </div>
            <!-- 待发货 -->
            <div class="p-grid-item" v-if="data.order.status_1.is_show == 1">
              <div class="icon-box">
                <el-image :src="data.order.status_1.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-box"></i></div>
                </el-image>
              </div>
              <span class="grid-text">{{ data.order.status_1.name }}</span>
            </div>
            <!-- 待收货 -->
            <div class="p-grid-item" v-if="data.order.status_2.is_show == 1">
              <div class="icon-box">
                <el-image :src="data.order.status_2.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-truck"></i></div>
                </el-image>
              </div>
              <span class="grid-text">{{ data.order.status_2.name }}</span>
            </div>
            <!-- 待评价 -->
            <div class="p-grid-item" v-if="data.order.status_3.is_show == 1">
              <div class="icon-box">
                <el-image :src="data.order.status_3.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-chat-dot-square"></i></div>
                </el-image>
              </div>
              <span class="grid-text">{{ data.order.status_3.name }}</span>
            </div>
            <!-- 售后 -->
            <div class="p-grid-item" v-if="data.order.status_4.is_show == 1">
              <div class="icon-box">
                <el-image :src="data.order.status_4.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-refresh-left"></i></div>
                </el-image>
              </div>
              <span class="grid-text">{{ data.order.status_4.name }}</span>
            </div>
          </div>
        </div>

        <!-- D. 常用工具 1 (列表模式) -->
        <div class="glass-section animate-enter delay-2" @click="onEdit('menus')"
          :class="{ 'active-block': activeBlock === 'menus' }" v-if="data.menus.is_show == 1">
          <div class="section-header"><span class="title">{{ data.menus.name }}</span></div>

          <div class="p-grid-wrap tools-grid" v-if="data.menus.type == 0">
            <div class="p-grid-item" v-for="(item, i) in data.menus.list" :key="i">
              <div class="dark-icon-bg">
                <el-image :src="item.icon" class="tool-img" fit="contain">
                  <div slot="error"><i class="el-icon-menu"></i></div>
                </el-image>
              </div>
              <span class="grid-text">{{ item.name }}</span>
            </div>
          </div>
          <div class="tools-list" v-else>
            <div class="tool-row" v-for="(item, i) in data.menus.list" :key="i">
              <div class="row-left">
                <div class="dark-icon-bg">
                  <el-image :src="item.icon" class="tool-img" fit="contain">
                    <div slot="error"><i class="el-icon-menu"></i></div>
                  </el-image>
                </div>
                <span class="tool-name">{{ item.name }}</span>
              </div>
              <i class="el-icon-arrow-right" style="color: rgba(255,255,255,0.2)"></i>
            </div>
          </div>
        </div>

        <!-- E. 常用工具 2 -->
        <div class="glass-section animate-enter delay-2" @click="onEdit('menus2')"
          :class="{ 'active-block': activeBlock === 'menus2' }" v-if="data.menus2.is_show == 1">
          <div class="section-header"><span class="title">{{ data.menus2.name }}</span></div>

          <div class="p-grid-wrap tools-grid" v-if="data.menus2.type == 0">
            <div class="p-grid-item" v-for="(item, i) in data.menus2.list" :key="i">
              <div class="dark-icon-bg">
                <el-image :src="item.icon" class="tool-img" fit="contain">
                  <div slot="error"><i class="el-icon-menu"></i></div>
                </el-image>
              </div>
              <span class="grid-text">{{ item.name }}</span>
            </div>
          </div>
          <div class="tools-list" v-else>
            <div class="tool-row" v-for="(item, i) in data.menus2.list" :key="i">
              <div class="row-left">
                <div class="dark-icon-bg">
                  <el-image :src="item.icon" class="tool-img" fit="contain">
                    <div slot="error"><i class="el-icon-menu"></i></div>
                  </el-image>
                </div>
                <span class="tool-name">{{ item.name }}</span>
              </div>
              <i class="el-icon-arrow-right" style="color: rgba(255,255,255,0.2)"></i>
            </div>
          </div>
        </div>

        <!-- F. 底部版权 -->
        <div class="footer animate-enter delay-3" @click="onEdit('copyright')"
          :class="{ 'active-block': activeBlock === 'copyright' }" v-if="data.copyright && data.copyright.is_show == 1">
          <span class="copyright">{{ data.copyright.text }}</span>
        </div>

      </div>
    </div>

    <!-- 底部 Tabbar (深色适配) -->
    <div class="mobile-tabbar dark-tabbar" @click="onEdit('tabbar')"
      :class="{ 'active-block': activeBlock === 'tabbar' }">
      <div class="tab-item" v-for="(item, i) in data.tabbar.list" :key="i">
        <el-image class="icon" :src="i === 0 ? item.active_icon : item.icon" fit="contain">
          <div slot="error"><i class="el-icon-s-home"></i></div>
        </el-image>
        <div class="text" :style="{ color: i === 0 ? '#E5C07B' : '#666' }">{{ item.name }}</div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'PreviewPremium',
  props: {
    data: {
      type: Object,
      default: () => ({})
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
    premiumBgStyle() {
      const c = this.data.colors;
      const from = (c && c.header_from) || '#2a2a2a';
      const to = (c && c.header_to) || '#121212';
      return { background: `radial-gradient(circle at 50% -20%, ${from} 0%, ${to} 80%)` };
    }
  },
  methods: {
    onEdit(key) {
      this.$emit('edit', key);
    }
  }
}
</script>

<style lang="scss" scoped>
/* --- 全局黑金配色变量 (Admin Scope) --- */
$bg-dark: #121212;
$gold-main: #E5C07B;
$gold-dark: #BFA15F;
$glass-dark: rgba(30, 30, 30, 0.75);
$glass-border: rgba(229, 192, 123, 0.2);
$text-white: #FFFFFF;
$text-gray: rgba(255, 255, 255, 0.6);

.theme-premium {
  height: 100%;
  position: relative;
  background-color: $bg-dark;
  font-family: -apple-system, sans-serif;
  color: $text-white;
  overflow: hidden;

  /* 高亮边框颜色调整为金色 */
  .active-block {
    border: 2px solid var(--admin-accent-text) !important;
    box-shadow: 0 0 15px rgba(var(--admin-accent-rgb), 0.3) !important;
    z-index: 99;
  }

  .active-inner-block {
    outline: 1px dashed var(--admin-accent-text);
    background: rgba(var(--admin-accent-rgb), 0.05);
  }

  /* 1. 背景层 */
  .premium-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 0;

    .noise-texture {
      position: absolute;
      width: 100%;
      height: 100%;
      opacity: 0.03;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
    }

    .gold-orb {
      position: absolute;
      border-radius: 50%;
      filter: blur(60px);
      opacity: 0.15;
      background: $gold-main;

      &.orb-1 {
        width: 200px;
        height: 200px;
        top: -50px;
        left: -50px;
        animation: float 10s ease-in-out infinite alternate;
      }

      &.orb-2 {
        width: 150px;
        height: 150px;
        bottom: 100px;
        right: -20px;
        animation: float 8s ease-in-out infinite alternate-reverse;
      }
    }
  }



  .mobile-body {
    height: 100%;
    overflow-y: scroll;
    padding-bottom: 60px;
    position: relative;
    z-index: 10;

    &::-webkit-scrollbar {
      display: none;
    }
  }

  .content-area {
    padding: 50px 16px 60px;
  }

  /* A. 尊享卡片 */
  .user-card-premium {
    margin-bottom: 20px;
    cursor: pointer;
    border: 2px solid transparent;
    border-radius: 16px;

    .card-glass-layer {
      background: linear-gradient(135deg, rgba(40, 40, 40, 0.9) 0%, rgba(20, 20, 20, 0.95) 100%);
      border-radius: 16px;
      padding: 20px 15px;
      border: 1px solid $glass-border;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
      overflow: hidden;
      position: relative;

      /* 扫光动画 */
      &::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 50%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(229, 192, 123, 0.1), transparent);
        transform: skewX(-20deg);
        animation: shine 4s infinite linear;
        pointer-events: none;
      }

      .card-content {
        display: flex;
        align-items: center;
        margin-bottom: 20px;

        .avatar-box {
          position: relative;
          margin-right: 15px;

          .avatar-border {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid $gold-main;
            box-shadow: 0 0 10px rgba(229, 192, 123, 0.2);
            background: #000;

            .avatar-comp {
              background: #333;
            }
          }

          .crown-icon {
            position: absolute;
            top: -8px;
            right: -5px;
            font-size: 16px;
            transform: rotate(15deg);
          }
        }

        .info-box {
          flex: 1;

          .nick-row {
            display: flex;
            align-items: center;
            margin-bottom: 6px;

            .nickname {
              font-size: 20px;
              font-weight: 700;
              color: $gold-main;
              margin-right: 8px;
            }

            .vip-badge {
              background: linear-gradient(90deg, $gold-dark, $gold-main);
              padding: 2px 6px;
              border-radius: 4px;
              position: relative;
              overflow: hidden;

              .vip-text {
                font-size: 10px;
                color: #000;
                font-weight: 800;
              }

              .shine-effect {
                position: absolute;
                top: 0;
                left: -100%;
                width: 50%;
                height: 100%;
                background: rgba(255, 255, 255, 0.4);
                transform: skewX(-20deg);
                animation: shine 2.5s infinite;
              }
            }
          }

          .uid-row {
            display: inline-flex;
            align-items: center;

            .uid-text {
              font-size: 12px;
              color: $text-gray;
              font-family: monospace;
            }
          }
        }
      }

      .header-stats-row {
        display: flex;
        justify-content: space-around;
        padding-bottom: 15px;
        border-bottom: 1px dashed rgba(255, 255, 255, 0.1);

        .h-col {
          text-align: center;

          .n {
            font-weight: bold;
            color: #fff;
          }

          .t {
            font-size: 11px;
            color: $text-gray;
          }
        }
      }

      .stats-row {
        display: flex;
        justify-content: space-around;
        padding-top: 15px;

        .stat-item {
          display: flex;
          flex-direction: column;
          align-items: center;

          .num {
            font-size: 18px;
            font-weight: 700;
            color: $text-white;
            margin-bottom: 3px;
            font-family: sans-serif;
          }

          .gold-text {
            color: $gold-main;
          }

          .lbl {
            font-size: 11px;
            color: $text-gray;
            text-transform: uppercase;
          }
        }
      }
    }
  }

  /* 通用深色玻璃模块 */
  .glass-section {
    background: $glass-dark;
    border-radius: 12px;
    margin-bottom: 15px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(5px);
    cursor: pointer;
    border: 2px solid transparent;
    /* 预留给active-block */

    .section-header {
      padding: 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);

      .title {
        font-size: 14px;
        font-weight: 600;
        color: $gold-main;
        letter-spacing: 1px;
        text-transform: uppercase;
      }

      .more {
        display: flex;
        align-items: center;

        span {
          font-size: 12px;
          color: $text-gray;
          margin-right: 3px;
        }

        i {
          color: $text-gray;
        }
      }
    }

    /* P-Grid 布局 */
    .p-grid-wrap {
      display: flex;
      padding: 15px 0;
      flex-wrap: wrap;

      &.tools-grid {
        justify-content: flex-start;

        .p-grid-item {
          flex: 0 0 25%;
          margin-bottom: 10px;
        }
      }

      .p-grid-item {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 20%;

        .icon-box,
        .dark-icon-bg {
          position: relative;
          margin-bottom: 8px;

          .main-icon {
            width: 24px;
            height: 24px;
            opacity: 0.9;
          }

          .gold-badge {
            position: absolute;
            top: -5px;
            right: -6px;
            background: $gold-main;
            color: #000;
            font-size: 10px;
            min-width: 15px;
            height: 15px;
            line-height: 15px;
            text-align: center;
            border-radius: 15px;
            font-weight: 700;
          }
        }

        .dark-icon-bg {
          width: 36px;
          height: 36px;
          border-radius: 8px;
          background: rgba(255, 255, 255, 0.05);
          display: flex;
          justify-content: center;
          align-items: center;

          .tool-img {
            width: 20px;
            height: 20px;
          }
        }

        .grid-text {
          font-size: 12px;
          color: $text-gray;
        }
      }
    }

    /* List 布局 */
    .tools-list {
      padding: 0 15px;

      .tool-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);

        &:last-child {
          border-bottom: none;
        }

        .row-left {
          display: flex;
          align-items: center;

          .dark-icon-bg {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 12px;

            .tool-img {
              width: 18px;
              height: 18px;
            }
          }

          .tool-name {
            font-size: 14px;
            color: $text-white;
          }
        }
      }
    }
  }

  /* 底部版权 */
  .footer {
    text-align: center;
    padding-bottom: 20px;
    cursor: pointer;
    border: 2px solid transparent;

    .copyright {
      font-size: 10px;
      color: rgba(255, 255, 255, 0.2);
      letter-spacing: 1px;
      text-transform: uppercase;
    }
  }

  /* Tabbar 深色适配 */
  .mobile-tabbar {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 50px;
    display: flex;
    z-index: 99;
    cursor: pointer;
    border: 2px solid transparent;
    background: #1A1A1A;
    border-top: 1px solid rgba(255, 255, 255, 0.1);

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

  /* 动画 */
  @keyframes float {
    0% {
      transform: translateY(0);
    }

    100% {
      transform: translateY(10px);
    }
  }

  @keyframes shine {
    0% {
      left: -100%;
    }

    100% {
      left: 200%;
    }
  }

  .animate-enter {
    animation: slideUp 0.6s forwards;
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
