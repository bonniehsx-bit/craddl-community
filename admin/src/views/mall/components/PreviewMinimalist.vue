<template>
  <div class="theme-wrapper theme-minimalist">




    <div class="mobile-body">
      <div class="content-area">

        <!-- 1. 极简用户卡片 -->
        <div class="user-card-wrap animate-enter" @click="onEdit('bg')"
          :class="{ 'active-block': activeBlock === 'bg' }">
          <div class="user-main">
            <div class="avatar-group">
              <el-avatar :size="64" :src="data.header.avatar || ''" icon="el-icon-user-solid"
                class="avatar-img"></el-avatar>
              <div class="level-tag">LV.1</div>
            </div>
            <div class="info-group">
              <div class="nickname">Hi, User</div>
              <div class="uid-row">
                <span>ID: 888888</span>
                <i class="el-icon-document-copy clickable"></i>
              </div>
            </div>
            <!-- 签到按钮 -->
            <div class="check-btn" style="display: none;">
              <span>签到</span>
            </div>
          </div>

          <!-- A.2 头部数据 (收藏/足迹/订单数) -->
          <div class="header-stats-row" v-if="hasHeaderStats">
            <div class="h-stat-item" v-if="data.header.goods.is_show == 1">
              <span class="num">12</span>
              <span class="lbl">{{ data.header.goods.name }}</span>
            </div>
            <div class="h-stat-item" v-if="data.header.footmark.is_show == 1">
              <span class="num">58</span>
              <span class="lbl">{{ data.header.footmark.name }}</span>
            </div>
            <div class="h-stat-item" v-if="data.header.order && data.header.order.is_show == 1">
              <span class="num">5</span>
              <span class="lbl">{{ data.header.order.name }}</span>
            </div>
          </div>
        </div>

        <!-- 2. 资产模块 (我的钱包) -->
        <!-- 注意：增加了 .stop 防止冒泡 -->
        <div class="section-card animate-enter delay-1" v-if="data.finance.is_show == 1" @click.stop="onEdit('finance')"
          :class="{ 'active-block': activeBlock === 'finance' }">

          <div class="card-title">
            <span>{{ data.finance.name }}</span>
            <span class="more-link">查看明细 <i class="el-icon-arrow-right"></i></span>
          </div>

          <div class="finance-row">
            <!-- 余额 -->
            <div class="f-item" v-if="data.finance.balance.is_show == 1">
              <span class="amount" :style="data.finance.balance.color ? { color: data.finance.balance.color } : {}">0.00</span>
              <span class="label">{{ data.finance.balance.name }}</span>
            </div>
            <div class="f-item" v-if="data.finance.price && data.finance.price.is_show == 1">
              <span class="amount gold-text" :style="data.finance.price.color ? { color: data.finance.price.color } : {}">88.50</span>
              <span class="label">{{ data.finance.price.name }}</span>
            </div>
            <div class="f-item" v-if="data.finance.integral.is_show == 1">
              <span class="amount" :style="data.finance.integral.color ? { color: data.finance.integral.color } : {}">100</span>
              <span class="label">{{ data.finance.integral.name }}</span>
            </div>
            <div class="f-item" v-if="data.finance.coupon.is_show == 1">
              <span class="amount" :style="data.finance.coupon.color ? { color: data.finance.coupon.color } : {}">2</span>
              <span class="label">{{ data.finance.coupon.name }}</span>
            </div>
          </div>
        </div>

        <!-- 3. 订单模块 -->
        <div class="section-card animate-enter delay-1" @click="onEdit('order')" v-if="data.order && data.order.is_show != 0"
          :class="{ 'active-block': activeBlock === 'order' }">
          <div class="card-title">
            <span>{{ data.order.title }}</span>
            <span class="more-link">{{ data.order.sub_title }} <i class="el-icon-arrow-right"></i></span>
          </div>

          <div class="grid-box">
            <div class="grid-item" v-if="data.order.status_0.is_show == 1">
              <div class="icon-wrap">
                <el-image :src="data.order.status_0.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-wallet"></i></div>
                </el-image>
                <div class="dot-badge">1</div>
              </div>
              <span class="grid-text">{{ data.order.status_0.name }}</span>
            </div>

            <div class="grid-item" v-if="data.order.status_1.is_show == 1">
              <div class="icon-wrap">
                <el-image :src="data.order.status_1.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-box"></i></div>
                </el-image>
              </div>
              <span class="grid-text">{{ data.order.status_1.name }}</span>
            </div>

            <div class="grid-item" v-if="data.order.status_2.is_show == 1">
              <div class="icon-wrap">
                <el-image :src="data.order.status_2.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-truck"></i></div>
                </el-image>
              </div>
              <span class="grid-text">{{ data.order.status_2.name }}</span>
            </div>

            <div class="grid-item" v-if="data.order.status_3.is_show == 1">
              <div class="icon-wrap">
                <el-image :src="data.order.status_3.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-chat-line-square"></i></div>
                </el-image>
              </div>
              <span class="grid-text">{{ data.order.status_3.name }}</span>
            </div>

            <div class="grid-item" v-if="data.order.status_4.is_show == 1">
              <div class="icon-wrap">
                <el-image :src="data.order.status_4.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-service"></i></div>
                </el-image>
              </div>
              <span class="grid-text">{{ data.order.status_4.name }}</span>
            </div>
          </div>
        </div>

        <!-- 4. 常用工具 1 -->
        <div class="section-card animate-enter delay-2" @click="onEdit('menus')" v-if="data.menus.is_show == 1"
          :class="{ 'active-block': activeBlock === 'menus' }">
          <div class="card-title"><span>{{ data.menus.name }}</span></div>

          <div class="grid-box tools-grid" v-if="data.menus.type == 0">
            <div class="grid-item" v-for="(item, i) in data.menus.list" :key="i">
              <!-- 彩色底色逻辑 -->
              <div class="tool-icon-bg" :style="{ background: getMenuColor(i) }">
                <el-image :src="item.icon" class="tool-icon-img" fit="contain">
                  <div slot="error"><i class="el-icon-menu"></i></div>
                </el-image>
              </div>
              <span class="grid-text">{{ item.name }}</span>
            </div>
          </div>
          <div class="list-layout" v-else>
            <div class="list-item" v-for="(item, i) in data.menus.list" :key="i">
              <span class="left"><el-image :src="item.icon" class="icon"></el-image>{{ item.name }}</span>
              <i class="el-icon-arrow-right"></i>
            </div>
          </div>
        </div>

        <!-- 5. 常用工具 2 -->
        <div class="section-card animate-enter delay-2" @click="onEdit('menus2')" v-if="data.menus2.is_show == 1"
          :class="{ 'active-block': activeBlock === 'menus2' }">
          <div class="card-title"><span>{{ data.menus2.name }}</span></div>

          <div class="grid-box tools-grid" v-if="data.menus2.type == 0">
            <div class="grid-item" v-for="(item, i) in data.menus2.list" :key="i">
              <div class="tool-icon-bg" :style="{ background: getMenuColor(i + 2) }">
                <el-image :src="item.icon" class="tool-icon-img" fit="contain">
                  <div slot="error"><i class="el-icon-menu"></i></div>
                </el-image>
              </div>
              <span class="grid-text">{{ item.name }}</span>
            </div>
          </div>
          <div class="list-layout" v-else>
            <div class="list-item" v-for="(item, i) in data.menus2.list" :key="i">
              <span class="left"><el-image :src="item.icon" class="icon"></el-image>{{ item.name }}</span>
              <i class="el-icon-arrow-right"></i>
            </div>
          </div>
        </div>

        <!-- 6. 底部版权 -->
        <div class="footer animate-enter delay-3" @click="onEdit('copyright')"
          :class="{ 'active-block': activeBlock === 'copyright' }" v-if="data.copyright && data.copyright.is_show == 1">
          <span class="copyright">{{ data.copyright.text }}</span>
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
  name: 'PreviewMinimalist',
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
    }
  },
  data() {
    return {
      // 莫兰迪色系背景池，用于工具菜单
      menuColors: [
        'rgba(92, 124, 250, 0.08)', // 蓝
        'rgba(255, 146, 43, 0.08)', // 橙
        'rgba(32, 201, 151, 0.08)', // 绿
        'rgba(250, 82, 82, 0.08)',  // 红
        'rgba(250, 176, 5, 0.08)',  // 黄
        'rgba(134, 142, 150, 0.08)', // 灰
        'rgba(235, 87, 87, 0.08)', // 绯红
        'rgba(111, 66, 193, 0.08)'  // 紫
      ]
    }
  },
  methods: {
    onEdit(key) {
      this.$emit('edit', key)
    },
    getMenuColor(index) {
      return this.menuColors[index % this.menuColors.length]
    }
  }
}
</script>

<style lang="scss" scoped>
/* 极简风变量 */
$bg-color: #F7F8FA;
$card-white: #FFFFFF;
$text-main: #2C3E50;
$text-sub: #95A5A6;
$shadow-soft: 0 8px 24px rgba(0, 0, 0, 0.03);
/* 超柔和阴影 */

.theme-minimalist {
  height: 100%;
  position: relative;
  background-color: $bg-color;
  font-family: -apple-system, sans-serif;
  overflow: hidden;



  .mobile-body {
    height: 100%;
    overflow-y: auto;
    padding-bottom: 60px;

    &::-webkit-scrollbar {
      display: none;
    }
  }

  .content-area {
    position: relative;
    z-index: 10;
    padding: 50px 16px 20px;
  }

  /* 选中高亮 */
  .active-block {
    border: 2px solid var(--admin-accent-text) !important;
    box-shadow: 0 0 15px rgba(var(--admin-accent-rgb), 0.2) !important;
  }

  /* 1. 用户卡片 */
  .user-card-wrap {
    background: $card-white;
    border-radius: 16px;
    padding: 24px 20px 16px;
    box-shadow: $shadow-soft;
    margin-bottom: 16px;
    border: 2px solid transparent;
    cursor: pointer;

    .user-main {
      display: flex;
      align-items: center;
      margin-bottom: 24px;

      .avatar-group {
        position: relative;
        margin-right: 16px;

        .avatar-img {
          border: 1px solid #eee;
          background: #fff;
        }

        .level-tag {
          position: absolute;
          bottom: -2px;
          right: -4px;
          background: #333;
          color: #FFD700;
          font-size: 9px;
          padding: 1px 6px;
          border-radius: 8px;
          font-weight: bold;
          border: 1px solid #fff;
        }
      }

      .info-group {
        flex: 1;

        .nickname {
          font-size: 20px;
          font-weight: 800;
          color: $text-main;
          margin-bottom: 4px;
        }

        .uid-row {
          font-size: 11px;
          color: $text-sub;
          display: flex;
          align-items: center;

          i {
            margin-left: 4px;
          }
        }
      }

      .check-btn {
        background: #F2F3F5;
        color: $text-main;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
      }
    }

    .header-stats-row {
      display: flex;
      justify-content: space-around;
      border-top: 1px solid #F7F8FA;
      padding-top: 16px;

      .h-stat-item {
        text-align: center;

        .num {
          display: block;
          font-size: 18px;
          font-weight: 800;
          color: $text-main;
          font-family: 'DIN Alternate', sans-serif;
        }

        .lbl {
          font-size: 11px;
          color: $text-sub;
          margin-top: 2px;
        }
      }
    }
  }

  /* 2. 通用 Section 卡片 */
  .section-card {
    background: $card-white;
    border-radius: 16px;
    margin-bottom: 16px;
    padding: 20px;
    box-shadow: $shadow-soft;
    cursor: pointer;
    border: 2px solid transparent;

    .card-title {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;

      span {
        font-size: 16px;
        font-weight: 700;
        color: $text-main;
      }

      .more-link {
        font-size: 12px;
        color: #C0C4CC;
        font-weight: normal;
      }
    }
  }

  /* 资产行 */
  .finance-row {
    display: flex;
    justify-content: space-between;

    .f-item {
      display: flex;
      flex-direction: column;
      align-items: center;

      .amount {
        font-size: 18px;
        font-weight: 800;
        color: $text-main;
        margin-bottom: 4px;
        font-family: 'DIN Alternate', sans-serif;
      }

      .gold-text {
        color: #FF9F43;
      }

      /* 佣金高亮 */
      .label {
        font-size: 11px;
        color: $text-sub;
      }
    }
  }

  /* 网格布局 */
  .grid-box {
    display: flex;
    flex-wrap: wrap;

    .grid-item {
      width: 20%;
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 10px;

      .icon-wrap {
        position: relative;
        width: 40px;
        height: 40px;
        display: flex;
        justify-content: center;
        align-items: center;
        margin-bottom: 6px;

        .main-icon {
          width: 24px;
          height: 24px;
        }

        .dot-badge {
          position: absolute;
          top: 0;
          right: 0;
          background: #FF4757;
          color: #fff;
          font-size: 10px;
          width: 14px;
          height: 14px;
          text-align: center;
          line-height: 14px;
          border-radius: 50%;
          border: 1px solid #fff;
        }
      }

      .grid-text {
        font-size: 11px;
        color: $text-sub;
      }
    }
  }

  /* 工具栏定制 (4列) */
  .tools-grid .grid-item {
    width: 25%;
    margin-bottom: 15px;

    .tool-icon-bg {
      width: 44px;
      height: 44px;
      border-radius: 14px;
      display: flex;
      justify-content: center;
      align-items: center;
      margin-bottom: 8px;

      .tool-icon-img {
        width: 22px;
        height: 22px;
      }
    }

    .grid-text {
      font-size: 12px;
      color: $text-main;
    }
  }

  /* 列表模式 */
  .list-layout {
    .list-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 0;
      border-bottom: 1px solid #f9f9f9;

      &:last-child {
        border-bottom: none;
        padding-bottom: 0;
      }

      &:first-child {
        padding-top: 0;
      }

      .left {
        display: flex;
        align-items: center;
        font-size: 13px;
        color: $text-main;

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
  .footer {
    text-align: center;
    padding: 10px 0 20px;
    border: 2px solid transparent;

    .copyright {
      font-size: 10px;
      color: #DCDFE6;
      letter-spacing: 1px;
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

  /* 动效 */
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

  .delay-3 {
    animation-delay: 0.3s;
  }

  @keyframes slideIn {
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
}
</style>
