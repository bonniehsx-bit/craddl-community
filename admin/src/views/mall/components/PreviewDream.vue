<template>
  <div class="theme-wrapper theme-dream">

    <!-- 1. 动态弥散背景 -->
    <div class="dream-bg">
      <div class="blob blob-1" :style="{ background: dreamColor1 }"></div>
      <div class="blob blob-2" :style="{ background: dreamColor2 }"></div>
      <div class="blob blob-3"></div>
      <div class="glass-overlay"></div>
    </div>


    <div class="mobile-body">
      <div class="content-area">

        <!-- A. 用户卡片 -->
        <div class="dream-card user-info-card animate-enter" :class="{ 'active-block': activeBlock === 'bg' }"
          @click="onEdit('bg')">

          <div class="card-glass-shine"></div>
          <div class="card-body">
            <div class="avatar-section">
              <div class="avatar-ring">
                <el-avatar :size="60" :src="data.header.avatar || ''" icon="el-icon-user-solid"
                  class="avatar-comp"></el-avatar>
              </div>
              <div class="level-pill"><span>LV.8</span></div>
            </div>
            <div class="text-section">
              <div class="nick-box"><span class="nickname">演示用户</span></div>
              <div class="uid-box"><span class="uid-num">UID: 88888888</span></div>
            </div>
            <div class="check-btn" style="display: none;">签到</div>
          </div>

          <!-- A.2 头部数据 (收藏/足迹) - 属于 header 配置 -->
          <div class="header-stats" v-if="hasHeaderStats">
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

        </div>

        <!-- A.3 资产卡片 (独立出来，增加佣金显示) -->
        <div class="dream-card finance-card animate-enter delay-1" @click.stop="onEdit('finance')"
          :class="{ 'active-block': activeBlock === 'finance' }" v-if="data.finance.is_show == 1">
          <div class="dream-stats">
            <div class="stat-col" v-if="data.finance.balance.is_show == 1">
              <span class="num" :style="data.finance.balance.color ? { color: data.finance.balance.color } : {}">0.00</span><span class="lbl">{{ data.finance.balance.name }}</span>
            </div>
            <div class="stat-col" v-if="data.finance.price && data.finance.price.is_show == 1">
              <span class="num highlight" :style="data.finance.price.color ? { color: data.finance.price.color } : {}">88.5</span><span class="lbl">{{ data.finance.price.name }}</span>
            </div>
            <div class="stat-col" v-if="data.finance.integral.is_show == 1">
              <span class="num" :style="data.finance.integral.color ? { color: data.finance.integral.color } : {}">100</span><span class="lbl">{{ data.finance.integral.name }}</span>
            </div>
            <div class="stat-col" v-if="data.finance.coupon.is_show == 1">
              <span class="num" :style="data.finance.coupon.color ? { color: data.finance.coupon.color } : {}">2</span><span class="lbl">{{ data.finance.coupon.name }}</span>
            </div>
          </div>
        </div>

        <!-- B. 订单模块 -->
        <div class="dream-card section-card animate-enter delay-1" :class="{ 'active-block': activeBlock === 'order' }"
          @click="onEdit('order')" v-if="data.order && data.order.is_show !== 0"> <!-- 注意这里可能没有is_show字段，默认为显示 -->
          <div class="card-header">
            <span class="header-title">{{ data.order.title }}</span>
            <div class="header-more"><span>{{ data.order.sub_title }}</span> <i class="el-icon-arrow-right"></i></div>
          </div>
          <div class="dream-grid-box">
            <!-- 待付款 -->
            <div class="dream-grid-item" v-if="data.order.status_0.is_show == 1">
              <div class="icon-wrap">
                <div class="icon-glow glow-blue"></div>
                <el-image :src="data.order.status_0.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-s-goods"></i></div>
                </el-image>
                <div class="float-badge">1</div>
              </div>
              <span class="item-name">{{ data.order.status_0.name }}</span>
            </div>
            <!-- 待发货 -->
            <div class="dream-grid-item" v-if="data.order.status_1.is_show == 1">
              <div class="icon-wrap">
                <div class="icon-glow glow-purple"></div>
                <el-image :src="data.order.status_1.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-s-goods"></i></div>
                </el-image>
              </div>
              <span class="item-name">{{ data.order.status_1.name }}</span>
            </div>
            <!-- 待收货 -->
            <div class="dream-grid-item" v-if="data.order.status_2.is_show == 1">
              <div class="icon-wrap">
                <div class="icon-glow glow-pink"></div>
                <el-image :src="data.order.status_2.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-s-goods"></i></div>
                </el-image>
              </div>
              <span class="item-name">{{ data.order.status_2.name }}</span>
            </div>
            <!-- 待评价 -->
            <div class="dream-grid-item" v-if="data.order.status_3.is_show == 1">
              <div class="icon-wrap">
                <div class="icon-glow glow-green"></div>
                <el-image :src="data.order.status_3.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-chat-dot-square"></i></div>
                </el-image>
              </div>
              <span class="item-name">{{ data.order.status_3.name }}</span>
            </div>
            <!-- 售后 -->
            <div class="dream-grid-item" v-if="data.order.status_4.is_show == 1">
              <div class="icon-wrap">
                <div class="icon-glow glow-orange"></div>
                <el-image :src="data.order.status_4.icon" class="main-icon" fit="contain">
                  <div slot="error"><i class="el-icon-refresh-left"></i></div>
                </el-image>
              </div>
              <span class="item-name">{{ data.order.status_4.name }}</span>
            </div>
          </div>
        </div>

        <!-- C. 常用工具 1 -->
        <div class="dream-card section-card animate-enter delay-2" :class="{ 'active-block': activeBlock === 'menus' }"
          @click="onEdit('menus')" v-if="data.menus.is_show == 1">
          <div class="card-header"><span class="header-title">{{ data.menus.name }}</span></div>

          <div class="dream-grid-box tools-layout" v-if="data.menus.type == 0">
            <div class="dream-grid-item" v-for="(item, index) in data.menus.list" :key="index">
              <div class="tool-icon-box">
                <el-image :src="item.icon" class="tool-img" fit="contain">
                  <div slot="error"><i class="el-icon-menu"></i></div>
                </el-image>
              </div>
              <span class="item-name">{{ item.name }}</span>
            </div>
          </div>
          <div class="dream-list-layout" v-else>
            <div class="list-item" v-for="(item, index) in data.menus.list" :key="index">
              <div class="left"><el-image :src="item.icon" class="icon"></el-image>{{ item.name }}</div>
              <i class="el-icon-arrow-right"></i>
            </div>
          </div>
        </div>

        <!-- D. 常用工具 2 -->
        <div class="dream-card section-card animate-enter delay-2" :class="{ 'active-block': activeBlock === 'menus2' }"
          @click="onEdit('menus2')" v-if="data.menus2.is_show == 1">
          <div class="card-header"><span class="header-title">{{ data.menus2.name }}</span></div>

          <div class="dream-grid-box tools-layout" v-if="data.menus2.type == 0">
            <div class="dream-grid-item" v-for="(item, index) in data.menus2.list" :key="index">
              <div class="tool-icon-box">
                <el-image :src="item.icon" class="tool-img" fit="contain">
                  <div slot="error"><i class="el-icon-menu"></i></div>
                </el-image>
              </div>
              <span class="item-name">{{ item.name }}</span>
            </div>
          </div>
          <div class="dream-list-layout" v-else>
            <div class="list-item" v-for="(item, index) in data.menus2.list" :key="index">
              <div class="left"><el-image :src="item.icon" class="icon"></el-image>{{ item.name }}</div>
              <i class="el-icon-arrow-right"></i>
            </div>
          </div>
        </div>

        <!-- E. 猜你喜欢 (模拟瀑布流) -->
        <div class="recommend-box animate-enter delay-3">
          <div class="dream-title">
            <span class="en">DISCOVER</span>
            <span class="zh">猜你喜欢</span>
          </div>
          <div class="dream-waterfall">
            <!-- 模拟两个商品卡片 -->
            <div class="goods-item">
              <div class="img-placeholder">Product A</div>
              <div class="info">
                <div class="name">示例商品标题</div>
                <div class="price">¥ 99.00</div>
              </div>
            </div>
            <div class="goods-item">
              <div class="img-placeholder">Product B</div>
              <div class="info">
                <div class="name">热门推荐商品</div>
                <div class="price">¥ 199.00</div>
              </div>
            </div>
          </div>
        </div>

        <!-- 版权 -->
        <div class="dream-footer animate-enter delay-3" :class="{ 'active-block': activeBlock === 'copyright' }"
          @click="onEdit('copyright')" v-if="data.copyright && data.copyright.is_show == 1">
          <span>{{ data.copyright.text }}</span>
        </div>

      </div>
    </div>

    <!-- 底部 Tabbar -->
    <div class="mobile-tabbar" :class="{ 'active-block': activeBlock === 'tabbar' }" @click="onEdit('tabbar')">
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
  name: 'PreviewDream',
  props: {
    // 父组件传入的配置数据
    data: {
      type: Object,
      default: () => ({})
    },
    // 当前选中的编辑模块
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
    dreamColor1() {
      return (this.data.colors && this.data.colors.header_from) || '#A0C4FF';
    },
    dreamColor2() {
      return (this.data.colors && this.data.colors.header_to) || '#FFC6FF';
    }
  },
  methods: {
    // 触发父组件切换右侧编辑表单
    onEdit(key) {
      this.$emit('edit', key);
    }
  }
}
</script>

<style lang="scss" scoped>
/*
   PC端模拟样式
   注意：px 值大约是 rpx 的一半
*/
.theme-dream {
  height: 100%;
  position: relative;
  background-color: #F3F5FA;
  font-family: -apple-system, sans-serif;
  overflow: hidden;

  /* 高亮边框 (后台专用) */
  .active-block {
    border: 2px solid var(--admin-accent-text) !important;
    box-shadow: 0 0 10px rgba(var(--admin-accent-rgb), 0.2);
    cursor: pointer;
  }

  /* 1. 背景 */
  .dream-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 500px;
    z-index: 0;
    overflow: hidden;

    .glass-overlay {
      position: absolute;
      width: 100%;
      height: 100%;
      background: rgba(255, 255, 255, 0.4);
      backdrop-filter: blur(30px);
      z-index: 2;
    }

    .blob {
      position: absolute;
      border-radius: 50%;
      filter: blur(40px);
      opacity: 0.8;
      z-index: 1;
      animation: floatBlob 10s infinite alternate;
    }

    .blob-1 {
      width: 300px;
      height: 300px;
      top: -100px;
      left: -100px;
    }

    .blob-2 {
      width: 250px;
      height: 250px;
      top: 0;
      right: -50px;
      animation-delay: -5s;
    }

    .blob-3 {
      width: 200px;
      height: 200px;
      background: #BDB2FF;
      top: 150px;
      left: 20%;
      opacity: 0.6;
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
    padding: 50px 16px 20px;
  }

  /* 避开顶部空间 */



  /* 卡片通用 */
  .dream-card {
    background: rgba(255, 255, 255, 0.65);
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 1);
    box-shadow: 0 5px 15px rgba(160, 196, 255, 0.1);
    margin-bottom: 15px;
    position: relative;
    overflow: hidden;
    border: 2px solid transparent;
    /* 预留给 active-block */
  }

  /* A. 用户信息 */
  .user-info-card {
    padding: 20px 15px 15px;

    .card-glass-shine {
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.4), transparent);
      transform: rotate(30deg);
      pointer-events: none;
    }

    .card-body {
      display: flex;
      align-items: center;
      margin-bottom: 15px;

      .avatar-section {
        position: relative;
        margin-right: 15px;

        .avatar-ring {
          width: 65px;
          height: 65px;
          border-radius: 50%;
          border: 2px solid #fff;
          box-shadow: 0 4px 10px rgba(160, 196, 255, 0.3);
          background: #EAF4FF;
          display: flex;
          justify-content: center;
          align-items: center;
          overflow: hidden;
        }

        .level-pill {
          position: absolute;
          bottom: -5px;
          left: 50%;
          transform: translateX(-50%);
          background: linear-gradient(90deg, #FFC6FF, #BDB2FF);
          padding: 1px 7px;
          border-radius: 10px;
          border: 1px solid #fff;

          span {
            font-size: 10px;
            color: #fff;
            font-weight: 700;
          }
        }
      }

      .text-section {
        flex: 1;

        .nick-box .nickname {
          font-size: 20px;
          font-weight: 800;
          color: #333;
        }

        .uid-box {
          display: inline-flex;
          align-items: center;
          background: rgba(255, 255, 255, 0.5);
          padding: 2px 8px;
          border-radius: 15px;
          border: 1px solid rgba(255, 255, 255, 0.8);
          margin-top: 4px;

          .uid-num {
            font-size: 11px;
            color: #666;
          }
        }
      }

      .check-btn {
        background: #fff;
        border: 1px solid #BDB2FF;
        color: #BDB2FF;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
      }
    }

    .header-stats {
      display: flex;
      justify-content: space-around;
      padding-top: 15px;
      border-top: 1px dashed rgba(0, 0, 0, 0.05);

      .h-col {
        text-align: center;

        .n {
          font-weight: bold;
          margin-right: 4px;
          color: #333;
        }

        .t {
          font-size: 12px;
          color: #999;
        }
      }
    }
  }

  /* 资产卡片 */
  .finance-card {
    padding: 15px 0;

    .dream-stats {
      display: flex;
      justify-content: space-around;

      .stat-col {
        display: flex;
        flex-direction: column;
        align-items: center;

        .num {
          font-size: 18px;
          font-weight: 800;
          color: #333;
          margin-bottom: 2px;
        }

        .highlight {
          color: #FF75C3;
        }

        .lbl {
          font-size: 12px;
          color: #888;
        }
      }
    }
  }

  /* B/C 通用 Section */
  .section-card {
    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px 15px 10px;

      .header-title {
        font-size: 16px;
        font-weight: 700;
        color: #333;
      }

      .header-more {
        display: flex;
        align-items: center;
        font-size: 12px;
        color: #999;
      }
    }
  }

  /* Grid */
  .dream-grid-box {
    display: flex;
    justify-content: space-between;
    padding: 10px 5px 20px;
    flex-wrap: wrap;

    &.tools-layout {
      justify-content: flex-start;

      .dream-grid-item {
        flex: 0 0 25%;
        margin-bottom: 10px;
      }
    }

    .dream-grid-item {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      min-width: 20%;

      .icon-wrap,
      .tool-icon-box {
        position: relative;
        width: 44px;
        height: 44px;
        margin-bottom: 6px;
        display: flex;
        justify-content: center;
        align-items: center;
      }

      .icon-wrap {
        .main-icon {
          width: 25px;
          height: 25px;
          z-index: 2;
        }

        .icon-glow {
          position: absolute;
          width: 100%;
          height: 100%;
          border-radius: 15px;
          opacity: 0.2;
          z-index: 1;
          filter: blur(5px);
        }

        .glow-blue {
          background: #A0C4FF;
        }

        .glow-purple {
          background: #BDB2FF;
        }

        .glow-pink {
          background: #FFC6FF;
        }

        .glow-orange {
          background: #FFD6A5;
        }

        .glow-green {
          background: #CAFFBF;
        }

        .float-badge {
          position: absolute;
          top: -3px;
          right: -3px;
          z-index: 3;
          background: #FF75C3;
          color: #fff;
          font-size: 10px;
          padding: 0 4px;
          border-radius: 10px;
          border: 1px solid #fff;
        }
      }

      .tool-icon-box {
        background: #F8F9FD;
        border-radius: 15px;

        .tool-img {
          width: 22px;
          height: 22px;
        }
      }

      .item-name {
        font-size: 12px;
        color: #555;
      }
    }
  }

  /* 列表模式 */
  .dream-list-layout {
    padding: 5px 0;

    .list-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 15px;
      border-bottom: 1px solid rgba(0, 0, 0, 0.03);

      &:last-child {
        border-bottom: none;
      }

      .left {
        display: flex;
        align-items: center;
        font-size: 14px;
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

  /* E. 猜你喜欢 */
  .recommend-box {
    margin-top: 20px;

    .dream-title {
      text-align: center;
      margin-bottom: 15px;

      .en {
        display: block;
        font-size: 10px;
        color: #BDB2FF;
        letter-spacing: 3px;
        font-weight: 800;
      }

      .zh {
        font-size: 16px;
        font-weight: 700;
        color: #333;
      }
    }

    .dream-waterfall {
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;

      .goods-item {
        width: 48%;
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 10px;
        box-shadow: 0 4px 12px rgba(160, 196, 255, 0.1);

        .img-placeholder {
          height: 120px;
          background: #f0f2f5;
          display: flex;
          align-items: center;
          justify-content: center;
          color: #ccc;
          font-size: 12px;
        }

        .info {
          padding: 8px;

          .name {
            font-size: 12px;
            margin-bottom: 4px;
            color: #333;
          }

          .price {
            color: #333;
            font-weight: bold;
            font-size: 14px;
          }
        }
      }
    }
  }

  /* 底部 */
  .dream-footer {
    text-align: center;
    padding: 15px 0 30px;

    span {
      font-size: 11px;
      color: #B0B0B0;
    }

    border: 2px solid transparent;
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
    transform: translateY(20px);
    animation: floatUp 0.6s forwards;
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

  @keyframes floatUp {
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  @keyframes floatBlob {
    0% {
      transform: translate(0, 0) scale(1);
    }

    100% {
      transform: translate(-10px, 10px) scale(0.9);
    }
  }
}
</style>
