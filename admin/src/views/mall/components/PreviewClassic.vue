<template>
  <div class="theme-wrapper theme-classic">

    <!-- 滚动区域：包含头部和所有内容 -->
    <div class="mobile-scroll-body">

      <!-- 1. 头部背景区域 (红色块) -->
      <div class="classic-header-bg"
        :style="{ background: headerBg }"
        @click="onEdit('tabbar')">


        <!-- 用户信息 (点击编辑 bg 模块) -->
        <div class="classic-user-info" @click.stop="onEdit('bg')" :class="{ 'active-block': activeBlock === 'bg' }">
          <el-avatar :size="55" :src="data.header.avatar || ''" icon="el-icon-user-solid" class="c-avatar"></el-avatar>
          <div class="c-info">
            <div class="c-name">
              用户昵称
              <span class="c-tag">VIP1</span>
            </div>
            <div class="c-id">ID: 888888</div>
          </div>
          <div class="c-check-btn" style="display: none;">
            <i class="el-icon-date"></i> 签到
          </div>
        </div>
      </div>

      <!-- 2. 内容区域 (向上偏移覆盖头部) -->
      <div class="content-offset-area">

        <!-- 资产卡片 (包含余额、积分、优惠券、佣金、收藏) -->
        <div class="white-card stats-card" @click="onEdit('finance')"
          :class="{ 'active-block': activeBlock === 'finance' }"
          v-if="(data.finance && data.finance.is_show == 1) || (data.header.goods && data.header.goods.is_show == 1)">

          <!-- 余额 -->
          <div class="stat-item" v-if="data.finance.balance && data.finance.balance.is_show == 1">
            <div class="num" :style="{ color: data.finance.balance.color || data.tabbar.color }">0.00</div>
            <div class="txt">{{ data.finance.balance.name }}</div>
          </div>

          <div class="stat-item" v-if="data.finance.price && data.finance.price.is_show == 1">
            <div class="num" :style="{ color: data.finance.price.color || data.tabbar.color }">0.00</div>
            <div class="txt">{{ data.finance.price.name }}</div>
          </div>

          <div class="stat-item" v-if="data.finance.integral && data.finance.integral.is_show == 1">
            <div class="num" :style="{ color: data.finance.integral.color || data.tabbar.color }">0</div>
            <div class="txt">{{ data.finance.integral.name }}</div>
          </div>

          <div class="stat-item" v-if="data.finance.coupon && data.finance.coupon.is_show == 1">
            <div class="num" :style="{ color: data.finance.coupon.color || data.tabbar.color }">0</div>
            <div class="txt">{{ data.finance.coupon.name }}</div>
          </div>

          <!-- 商品收藏 (属于header模块配置，但在经典风格中通常显示在资产栏) -->
          <div class="stat-item" v-if="data.header.goods && data.header.goods.is_show == 1" @click.stop="onEdit('bg')">
            <div class="num" :style="{ color: data.tabbar.color }">0</div>
            <div class="txt">{{ data.header.goods.name }}</div>
          </div>
        </div>

        <!-- 订单卡片 -->
        <div class="white-card order-card" @click="onEdit('order')" :class="{ 'active-block': activeBlock === 'order' }"
          v-if="data.order && data.order.is_show != 0">
          <div class="card-header border-bottom">
            <span class="title">{{ data.order.title }}</span>
            <span class="sub">{{ data.order.sub_title }} <i class="el-icon-arrow-right"></i></span>
          </div>

          <div class="classic-grid">
            <!-- 循环显示订单状态 -->
            <div class="c-grid-item" v-for="(key, index) in orderStatusList" :key="key"
              v-if="data.order[key] && data.order[key].is_show == 1">
              <div class="icon-wrap">
                <el-image :src="data.order[key].icon" class="c-icon" fit="contain">
                  <div slot="error"><i class="el-icon-s-order"></i></div>
                </el-image>
                <!-- 模拟角标 -->
                <div class="badge" v-if="index === 1 || index === 2" :style="{ background: data.tabbar.color }">1</div>
              </div>
              <span class="item-name">{{ data.order[key].name }}</span>
            </div>

            <!-- 售后单独显示 -->
            <div class="c-grid-item" v-if="data.order.status_4 && data.order.status_4.is_show == 1">
              <div class="icon-wrap">
                <el-image :src="data.order.status_4.icon" class="c-icon" fit="contain">
                  <div slot="error"><i class="el-icon-refresh-left"></i></div>
                </el-image>
              </div>
              <span class="item-name">{{ data.order.status_4.name }}</span>
            </div>
          </div>
        </div>

        <!-- 常用工具 1 -->
        <div class="white-card menus-card" @click="onEdit('menus')" v-if="data.menus && data.menus.is_show == 1"
          :class="{ 'active-block': activeBlock === 'menus' }">
          <div class="card-header border-bottom"><span class="title">{{ data.menus.name }}</span></div>

          <!-- 宫格布局 -->
          <div class="classic-grid col-4" v-if="data.menus.type == 0">
            <div class="c-grid-item" v-for="(item, i) in data.menus.list" :key="i">
              <div class="icon-wrap">
                <el-image :src="item.icon" class="c-icon" fit="contain">
                  <div slot="error"><i class="el-icon-menu"></i></div>
                </el-image>
              </div>
              <span class="item-name">{{ item.name }}</span>
            </div>
          </div>

          <!-- 列表布局 -->
          <div class="classic-list" v-else>
            <div class="cl-item" v-for="(item, i) in data.menus.list" :key="i">
              <div class="left-part">
                <el-image :src="item.icon" class="list-icon" fit="contain">
                  <div slot="error"><i class="el-icon-menu"></i></div>
                </el-image>
                <span class="list-txt">{{ item.name }}</span>
              </div>
              <i class="el-icon-arrow-right right-icon"></i>
            </div>
          </div>
        </div>

        <!-- 常用工具 2 -->
        <div class="white-card menus-card" @click="onEdit('menus2')" v-if="data.menus2 && data.menus2.is_show == 1"
          :class="{ 'active-block': activeBlock === 'menus2' }">
          <div class="card-header border-bottom"><span class="title">{{ data.menus2.name }}</span></div>

          <div class="classic-grid col-4" v-if="data.menus2.type == 0">
            <div class="c-grid-item" v-for="(item, i) in data.menus2.list" :key="i">
              <div class="icon-wrap">
                <el-image :src="item.icon" class="c-icon" fit="contain">
                  <div slot="error"><i class="el-icon-menu"></i></div>
                </el-image>
              </div>
              <span class="item-name">{{ item.name }}</span>
            </div>
          </div>

          <div class="classic-list" v-else>
            <div class="cl-item" v-for="(item, i) in data.menus2.list" :key="i">
              <div class="left-part">
                <el-image :src="item.icon" class="list-icon" fit="contain">
                  <div slot="error"><i class="el-icon-menu"></i></div>
                </el-image>
                <span class="list-txt">{{ item.name }}</span>
              </div>
              <i class="el-icon-arrow-right right-icon"></i>
            </div>
          </div>
        </div>

        <!-- 底部版权 -->
        <div class="footer-copyright-preview" @click="onEdit('copyright')"
          :class="{ 'active-block': activeBlock === 'copyright' }" v-if="data.copyright && data.copyright.is_show == 1">
          <span>{{ data.copyright.text }}</span>
        </div>

        <!-- 底部占位 -->
        <div style="height: 20px;"></div>
      </div>
    </div>

    <!-- 3. 底部 Tabbar (固定定位) -->
    <div class="mobile-tabbar" @click="onEdit('tabbar')" :class="{ 'active-block': activeBlock === 'tabbar' }"
      v-if="data.tabbar">
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
  name: 'PreviewClassic',
  props: {
    // 页面配置数据
    data: {
      type: Object,
      required: true,
      default: () => ({})
    },
    // 当前正在编辑的模块标识
    activeBlock: {
      type: String,
      default: ''
    }
  },
  computed: {
    headerBg() {
      const c = this.data.colors;
      if (c && c.header_from) {
        return c.header_to
          ? `linear-gradient(135deg, ${c.header_from}, ${c.header_to})`
          : c.header_from;
      }
      return (this.data.tabbar && this.data.tabbar.color) ? this.data.tabbar.color : '#E41F19';
    }
  },
  data() {
    return {
      // 订单状态 key 列表
      orderStatusList: ['status_0', 'status_1', 'status_2', 'status_3']
    }
  },
  methods: {
    onEdit(key) {
      this.$emit('edit', key)
    }
  }
}
</script>

<style lang="scss" scoped>
/* 根容器：Flex布局，tabbar 固定底部 */
.theme-wrapper {
  width: 100%;
  height: 100%;
  position: relative;
  background: #f5f5f5;
  font-family: -apple-system, Helvetica, sans-serif;
  display: flex;
  flex-direction: column;
  overflow: hidden;

  /* 隐藏滚动条 */
  ::-webkit-scrollbar {
    width: 0 !important;
    display: none;
  }
}

/* 滚动主体区域 */
.mobile-scroll-body {
  flex: 1;
  overflow-y: auto;
  position: relative;
  background: #f5f5f5;
  padding-bottom: 50px;
  /* 留出 tabbar 高度 */
}

/* 1. 头部红色背景区域 */
.classic-header-bg {
  height: 244px;
  /* 增加高度适配沉浸式状态栏 */
  background: #E41F19;
  /* 默认红 */
  border-bottom-left-radius: 20px;
  border-bottom-right-radius: 20px;
  position: relative;
  padding: 14px 16px 0;
  /* 顶部避开状态栏 */
  transition: background 0.3s;



  /* 用户信息条 */
  .classic-user-info {
    display: flex;
    align-items: center;
    color: #fff;
    cursor: pointer;
    border: 1px dashed transparent;
    border-radius: 12px;
    padding: 15px 5px;
    margin-top: 10px;
    transition: all 0.2s;

    &:hover,
    &.active-block {
      background: rgba(255, 255, 255, 0.1);
    }

    /* 选中高亮 */
    &.active-block {
      border-color: rgba(255, 255, 255, 0.8) !important;
      box-shadow: none !important;
      /* 覆盖默认蓝色阴影，使用白色 */
    }

    .c-avatar {
      border: 2px solid rgba(255, 255, 255, 0.5);
      background: #fff;
      color: #ccc;
      flex-shrink: 0;
    }

    .c-info {
      flex: 1;
      margin-left: 12px;
      overflow: hidden;

      .c-name {
        font-size: 18px;
        font-weight: bold;
        display: flex;
        align-items: center;

        .c-tag {
          font-size: 10px;
          background: #333;
          color: gold;
          padding: 1px 6px;
          border-radius: 10px;
          margin-left: 8px;
          font-weight: normal;
        }
      }

      .c-id {
        font-size: 12px;
        opacity: 0.8;
        margin-top: 4px;
      }
    }

    .c-check-btn {
      background: rgba(0, 0, 0, 0.15);
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      flex-shrink: 0;
    }
  }
}

/* 2. 向上偏移的内容区 */
.content-offset-area {
  padding: 0 12px;
  position: relative;
  z-index: 10;
}

/* 3. 通用白卡样式 */
.white-card {
  background: #fff;
  border-radius: 8px;
  margin-bottom: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
  cursor: pointer;
  border: 2px solid transparent;
  overflow: hidden;
  position: relative;

  /* 选中高亮 */
  &.active-block {
    border-color: var(--admin-accent-text) !important;
    box-shadow: 0 0 10px rgba(var(--admin-accent-rgb), 0.3) !important;
  }

  /* 资产卡片 (核心样式：负边距上移) */
  &.stats-card {
    display: flex;
    justify-content: space-around;
    padding: 20px 0;
    margin-top: -50px;
    /* 向上覆盖 Header */

    .stat-item {
      text-align: center;
      flex: 1;
      position: relative;

      /* 增加点击区域 */
      &::after {
        content: '';
        position: absolute;
        inset: -10px;
      }

      .num {
        font-size: 17px;
        font-weight: bold;
        margin-bottom: 6px;
        font-family: DIN, sans-serif;
      }

      .txt {
        font-size: 12px;
        color: #666;
      }
    }
  }

  /* 标题头 */
  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;

    &.border-bottom {
      border-bottom: 1px solid #f9f9f9;
    }

    .title {
      font-weight: bold;
      font-size: 15px;
      color: #333;
    }

    .sub {
      font-weight: normal;
      font-size: 12px;
      color: #999;

      i {
        margin-left: 2px;
      }
    }
  }

  /* Grid 网格 */
  .classic-grid {
    display: flex;
    flex-wrap: wrap;
    width: 100%;
    padding: 15px 0 5px;

    .c-grid-item {
      width: 20%;
      /* 默认 5 列 */
      display: flex;
      flex-direction: column;
      align-items: center;
      margin-bottom: 15px;

      .icon-wrap {
        position: relative;
        width: 32px;
        height: 32px;
        margin-bottom: 8px;
        display: flex;
        justify-content: center;
        align-items: center;

        .c-icon {
          width: 28px;
          height: 28px;
          display: block;
        }

        .badge {
          position: absolute;
          top: -6px;
          right: -8px;
          color: #fff;
          font-size: 10px;
          min-width: 16px;
          height: 16px;
          border-radius: 10px;
          text-align: center;
          line-height: 16px;
          padding: 0 4px;
          border: 1px solid #fff;
        }
      }

      .item-name {
        font-size: 12px;
        color: #666;
        text-align: center;
        width: 100%;
        padding: 0 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
    }

    /* 4列模式 */
    &.col-4 .c-grid-item {
      width: 25%;
    }
  }

  /* List 列表 */
  .classic-list {
    width: 100%;

    .cl-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 15px;
      border-bottom: 1px solid #fcfcfc;

      &:last-child {
        border-bottom: none;
      }

      .left-part {
        display: flex;
        align-items: center;
        flex: 1;
        overflow: hidden;

        .list-icon {
          width: 20px;
          height: 20px;
          margin-right: 12px;
          flex-shrink: 0;
        }

        .list-txt {
          font-size: 14px;
          color: #333;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
        }
      }

      .right-icon {
        color: #ccc;
        font-size: 14px;
        margin-left: 10px;
      }
    }
  }
}

/* 底部版权 */
.footer-copyright-preview {
  text-align: center;
  padding: 10px 0 20px;
  font-size: 11px;
  color: #ccc;
  cursor: pointer;
  border: 2px solid transparent;

  /* 选中高亮 */
  &.active-block {
    border: 1px dashed var(--admin-accent-text);
    background: rgba(var(--admin-accent-rgb), 0.05);
  }
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

  /* 选中高亮 */
  &.active-block {
    border-top: 2px solid var(--admin-accent-text) !important;
    background: rgba(var(--admin-accent-rgb), 0.05);
  }

  .tab-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;

    .icon {
      width: 24px;
      height: 24px;
      margin-bottom: 2px;
    }

    .text {
      font-size: 10px;
      line-height: 1;
    }
  }
}
</style>
