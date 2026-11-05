<template>
  <div class="theme-wrapper theme-silver">

    <!-- 1. 银光氛围背景 -->
    <div class="aurora-bg">
      <div class="orb orb-1" :style="{ background: silverColor1 }"></div>
      <div class="orb orb-2" :style="{ background: silverColor2 }"></div>
    </div>

    <!-- 模拟状态栏 (已在父级 user.vue 中统一展示，此处移除避免重复) -->
    <!-- <div class="status-bar-mock">...</div> -->

    <div class="mobile-body">
      <div class="content-area">

        <!-- A. 用户信息卡片 (玻璃态) -->
        <div class="glass-panel user-card animate-enter" @click="onEdit('bg')"
          :class="{ 'active-block': activeBlock === 'bg' }">
          <div class="card-inner">
            <div class="avatar-group">
              <el-avatar :size="65" :src="data.header.avatar || ''" icon="el-icon-user-solid"
                class="avatar-img"></el-avatar>
              <div class="level-badge">Lv. Platinum</div>
            </div>
            <div class="info-group">
              <div class="name-row">
                <span class="nickname">Alexander</span>
              </div>
              <div class="uid-row">
                <span class="uid-text">ID: 8888 7701</span>
                <i class="el-icon-document-copy" style="font-size:10px; margin-left:4px; color:#8E8E93;"></i>
              </div>
            </div>

          </div>

          <!-- A.2 头部数据统计 (收藏/足迹/订单数) -->
          <!-- 属于 header 模块 -->
          <div class="silver-header-stats" v-if="hasHeaderStats">
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

          <!-- B. 银色数据栏 (资产) -->
          <!-- 注意：增加了 .stop 防止冒泡 -->
          <div class="silver-stats" @click.stop="onEdit('finance')"
            :class="{ 'active-inner-block': activeBlock === 'finance' }" v-if="data.finance.is_show == 1">
            <div class="stat-item" v-if="data.finance.balance.is_show == 1">
              <span class="num" :style="data.finance.balance.color ? { color: data.finance.balance.color } : {}">0.00</span><span class="label">{{ data.finance.balance.name }}</span>
            </div>
            <div class="stat-item" v-if="data.finance.price && data.finance.price.is_show == 1">
              <span class="num silver-highlight" :style="data.finance.price.color ? { color: data.finance.price.color } : {}">88.5</span><span class="label">{{ data.finance.price.name }}</span>
            </div>
            <div class="stat-item" v-if="data.finance.integral.is_show == 1">
              <span class="num" :style="data.finance.integral.color ? { color: data.finance.integral.color } : {}">0</span><span class="label">{{ data.finance.integral.name }}</span>
            </div>
            <div class="stat-item" v-if="data.finance.coupon.is_show == 1">
              <span class="num" :style="data.finance.coupon.color ? { color: data.finance.coupon.color } : {}">0</span><span class="label">{{ data.finance.coupon.name }}</span>
            </div>
          </div>
        </div>

        <!-- C. 订单模块 -->
        <div class="glass-panel section-card animate-enter delay-1" @click="onEdit('order')" v-if="data.order && data.order.is_show != 0"
          :class="{ 'active-block': activeBlock === 'order' }">
          <div class="card-header">
            <span class="title">{{ data.order.title }}</span>
            <div class="more">{{ data.order.sub_title }} <i class="el-icon-arrow-right"></i></div>
          </div>
          <div class="grid-box">
            <div class="grid-item" v-if="data.order.status_0.is_show == 1">
              <div class="icon-box">
                <el-image :src="data.order.status_0.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-wallet"></i></div>
                </el-image>
                <div class="dot-badge"></div>
              </div>
              <span class="grid-label">{{ data.order.status_0.name }}</span>
            </div>

            <div class="grid-item" v-if="data.order.status_1.is_show == 1">
              <div class="icon-box">
                <el-image :src="data.order.status_1.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-box"></i></div>
                </el-image>
              </div>
              <span class="grid-label">{{ data.order.status_1.name }}</span>
            </div>

            <div class="grid-item" v-if="data.order.status_2.is_show == 1">
              <div class="icon-box">
                <el-image :src="data.order.status_2.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-truck"></i></div>
                </el-image>
              </div>
              <span class="grid-label">{{ data.order.status_2.name }}</span>
            </div>

            <div class="grid-item" v-if="data.order.status_3.is_show == 1">
              <div class="icon-box">
                <el-image :src="data.order.status_3.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-chat-line-square"></i></div>
                </el-image>
              </div>
              <span class="grid-label">{{ data.order.status_3.name }}</span>
            </div>

            <div class="grid-item" v-if="data.order.status_4.is_show == 1">
              <div class="icon-box">
                <el-image :src="data.order.status_4.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-service"></i></div>
                </el-image>
              </div>
              <span class="grid-label">{{ data.order.status_4.name }}</span>
            </div>
          </div>
        </div>

        <!-- D. 常用工具 1 -->
        <div class="glass-panel section-card animate-enter delay-2" @click="onEdit('menus')"
          v-if="data.menus.is_show == 1" :class="{ 'active-block': activeBlock === 'menus' }">
          <div class="card-header"><span class="title">{{ data.menus.name }}</span></div>

          <div class="grid-box tools-grid" v-if="data.menus.type == 0">
            <div class="grid-item" v-for="(item, i) in data.menus.list" :key="i">
              <div class="tool-icon-circle" style="background: #F2F2F7;">
                <el-image :src="item.icon" class="tool-icon-img" fit="contain">
                  <div slot="error"><i class="el-icon-menu"></i></div>
                </el-image>
              </div>
              <span class="grid-label">{{ item.name }}</span>
            </div>
          </div>
          <div class="list-layout" v-else>
            <div class="list-item" v-for="(item, i) in data.menus.list" :key="i">
              <span class="left"><el-image :src="item.icon" class="icon"></el-image>{{ item.name }}</span>
              <i class="el-icon-arrow-right"></i>
            </div>
          </div>
        </div>

        <!-- E. 常用工具 2 -->
        <div class="glass-panel section-card animate-enter delay-2" @click="onEdit('menus2')"
          v-if="data.menus2.is_show == 1" :class="{ 'active-block': activeBlock === 'menus2' }">
          <div class="card-header"><span class="title">{{ data.menus2.name }}</span></div>

          <div class="grid-box tools-grid" v-if="data.menus2.type == 0">
            <div class="grid-item" v-for="(item, i) in data.menus2.list" :key="i">
              <div class="tool-icon-circle" style="background: #F2F2F7;">
                <el-image :src="item.icon" class="tool-icon-img" fit="contain">
                  <div slot="error"><i class="el-icon-menu"></i></div>
                </el-image>
              </div>
              <span class="grid-label">{{ item.name }}</span>
            </div>
          </div>
          <div class="list-layout" v-else>
            <div class="list-item" v-for="(item, i) in data.menus2.list" :key="i">
              <span class="left"><el-image :src="item.icon" class="icon"></el-image>{{ item.name }}</span>
              <i class="el-icon-arrow-right"></i>
            </div>
          </div>
        </div>

        <!-- F. 底部版权 -->
        <div class="footer animate-enter delay-3" @click="onEdit('copyright')"
          :class="{ 'active-block': activeBlock === 'copyright' }" v-if="data.copyright && data.copyright.is_show == 1">
          <span class="brand">{{ data.copyright.text }}</span>
        </div>

        <div style="height: 20px;"></div>
      </div>
    </div>

    <!-- 底部 Tabbar -->
    <div class="mobile-tabbar" @click="onEdit('tabbar')" :class="{ 'active-block': activeBlock === 'tabbar' }">
      <div class="tab-item" v-for="(item, i) in data.tabbar.list" :key="i">
        <el-image class="icon" :src="i === 0 ? item.active_icon : item.icon" fit="contain"></el-image>
        <div class="text" :style="{ color: i === 0 ? data.tabbar.color : '#999' }">{{ item.name }}</div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'PreviewSilver',
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
    silverColor1() {
      return (this.data.colors && this.data.colors.header_from) || '#E0EAFF';
    },
    silverColor2() {
      return (this.data.colors && this.data.colors.header_to) || '#F3E5F5';
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
/* 变量定义 */
$bg-color: #F4F5F9;
$text-main: #1D1D1F;
$text-sub: #86868B;
$glass-white: rgba(255, 255, 255, 0.85);

.theme-silver {
  height: 100%;
  position: relative;
  background-color: $bg-color;
  font-family: -apple-system, Helvetica, sans-serif;

  /* 状态栏 */
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
    font-weight: bold;
    z-index: 99;
    color: #333;
  }

  .mobile-body {
    height: 100%;
    overflow-y: scroll;
    padding-bottom: 60px;

    &::-webkit-scrollbar {
      display: none;
    }
  }

  .content-area {
    position: relative;
    z-index: 10;
    padding: 50px 15px 20px;
  }

  /* 选中高亮 */
  .active-block {
    border: 2px solid var(--admin-accent-text) !important;
    box-shadow: 0 0 10px rgba(var(--admin-accent-rgb), 0.4) !important;
  }

  .active-inner-block {
    outline: 1px dashed var(--admin-accent-text);
    background: rgba(var(--admin-accent-rgb), 0.05);
  }

  /* 1. 银光氛围背景 */
  .aurora-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 400px;
    z-index: 0;
    overflow: hidden;

    .orb {
      position: absolute;
      border-radius: 50%;
      filter: blur(40px);
      opacity: 0.6;
    }

    .orb-1 {
      width: 200px;
      height: 200px;
      top: -50px;
      left: -50px;
    }

    .orb-2 {
      width: 250px;
      height: 250px;
      top: 25px;
      right: -75px;
    }
  }

  /* 玻璃拟态面板通用 */
  .glass-panel {
    background: $glass-white;
    backdrop-filter: blur(10px);
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 1);
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.04), 0 1px 5px rgba(0, 0, 0, 0.02);
    margin-bottom: 16px;
    cursor: pointer;
    border: 2px solid transparent;
    /* 预留给 active-block */
  }

  /* A. 用户卡片 */
  .user-card {
    padding: 20px 15px 5px;

    .card-inner {
      display: flex;
      align-items: center;
      margin-bottom: 20px;
    }

    .avatar-group {
      position: relative;
      margin-right: 15px;

      .avatar-img {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        background: #fff;
      }

      .level-badge {
        position: absolute;
        bottom: -3px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(90deg, #E0E0E0, #F5F5F5);
        padding: 2px 6px;
        border-radius: 10px;
        border: 1px solid #fff;
        white-space: nowrap;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        font-size: 9px;
        color: #333;
        font-weight: 600;
        text-transform: uppercase;
      }
    }

    .info-group {
      flex: 1;

      .nickname {
        font-size: 20px;
        font-weight: 700;
        color: $text-main;
        margin-bottom: 4px;
        display: block;
      }

      .uid-row {
        display: inline-flex;
        align-items: center;
        background: rgba(0, 0, 0, 0.03);
        padding: 3px 8px;
        border-radius: 6px;

        .uid-text {
          font-size: 11px;
          color: $text-sub;
        }
      }
    }



    /* 头部统计 */
    .silver-header-stats {
      display: flex;
      justify-content: space-around;
      padding-bottom: 15px;
      border-bottom: 1px dashed rgba(0, 0, 0, 0.05);

      .h-col {
        text-align: center;

        .n {
          display: block;
          font-weight: bold;
          color: #333;
        }

        .t {
          font-size: 12px;
          color: #999;
        }
      }
    }

    /* 资产栏 */
    .silver-stats {
      display: flex;
      justify-content: space-around;
      padding: 15px 0;

      .stat-item {
        text-align: center;

        .num {
          font-size: 18px;
          font-weight: 600;
          color: $text-main;
          display: block;
          font-family: 'DIN', sans-serif;
        }

        .silver-highlight {
          color: #5E5CE6;
        }

        .label {
          font-size: 11px;
          color: $text-sub;
        }
      }
    }
  }

  /* B/C/D. 通用卡片头部 & Grid */
  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 15px 10px;

    .title {
      font-size: 15px;
      font-weight: 600;
      color: $text-main;
    }

    .more {
      display: flex;
      align-items: center;
      font-size: 12px;
      color: $text-sub;
    }
  }

  .grid-box {
    display: flex;
    justify-content: space-between;
    padding: 10px 5px 20px;
    flex-wrap: wrap;

    .grid-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      width: 20%;

      .icon-box {
        position: relative;
        margin-bottom: 8px;
        width: 30px;
        height: 30px;
        display: flex;
        justify-content: center;
        align-items: center;

        .main-icon {
          width: 24px;
          height: 24px;
        }

        .dot-badge {
          position: absolute;
          top: 0;
          right: 0;
          width: 6px;
          height: 6px;
          background: #FF3B30;
          border-radius: 50%;
          border: 1px solid #fff;
        }
      }

      .grid-label {
        font-size: 11px;
        color: #555;
      }
    }
  }

  /* 工具栏特定样式 (4列) */
  .tools-grid {
    padding: 5px 0 15px;
    justify-content: flex-start;

    .grid-item {
      margin-bottom: 15px;
      width: 25%;

      .tool-icon-circle {
        width: 44px;
        height: 44px;
        border-radius: 15px;
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 6px;

        .tool-icon-img {
          width: 22px;
          height: 22px;
        }
      }
    }
  }

  /* 列表模式 */
  .list-layout {
    .list-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 15px;
      border-bottom: 1px solid #f5f5f5;

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
      }
    }
  }

  /* 底部品牌 */
  .footer {
    text-align: center;
    padding: 10px 0 30px;
    border: 2px solid transparent;

    .brand {
      font-size: 10px;
      color: #D1D1D6;
      letter-spacing: 2px;
      text-transform: uppercase;
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

  /* 动画 */
  .animate-enter {
    opacity: 0;
    transform: translateY(15px);
    animation: slideIn 0.5s ease-out forwards;
  }

  .delay-1 {
    animation-delay: 0.1s;
  }

  .delay-2 {
    animation-delay: 0.2s;
  }

  @keyframes slideIn {
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
}
</style>
