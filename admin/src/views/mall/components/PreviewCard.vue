<template>
  <div class="theme-wrapper theme-card">
    <div class="mobile-body">

      <!-- A. 色头 -->
      <div class="hero" :style="heroStyle" @click="onEdit('bg')" :class="{ 'active-block': activeBlock === 'bg' }">
        <div class="deco deco-1"></div>
        <div class="deco deco-2"></div>
        <div class="hero-user">
          <el-avatar :size="54" :src="data.header.avatar || ''" icon="el-icon-user-solid" class="avatar"></el-avatar>
          <div class="info">
            <div class="name-row">
              <span class="name">Hi, User</span>
              <span class="level">LV.1</span>
            </div>
            <div class="uid">ID: 888888 <i class="el-icon-document-copy"></i></div>
          </div>
          <div class="sign" style="display: none;" :style="{ color: accent }">签到</div>
        </div>
        <div class="hero-stats" v-if="hasHeaderStats">
          <div class="stat" v-if="data.header.goods.is_show == 1"><span class="num">12</span><span class="lbl">{{ data.header.goods.name }}</span></div>
          <div class="stat" v-if="data.header.footmark.is_show == 1"><span class="num">58</span><span class="lbl">{{ data.header.footmark.name }}</span></div>
          <div class="stat" v-if="data.header.order && data.header.order.is_show == 1"><span class="num">5</span><span class="lbl">{{ data.header.order.name }}</span></div>
        </div>
      </div>

      <!-- B. 资产卡(叠头) -->
      <div class="panel assets" v-if="data.finance.is_show == 1" @click.stop="onEdit('finance')" :class="{ 'active-block': activeBlock === 'finance' }">
        <div class="panel-head"><span class="t">{{ data.finance.name }}</span><span class="more">资金管理 <i class="el-icon-arrow-right"></i></span></div>
        <div class="assets-row">
          <div class="a-item balance" v-if="data.finance.balance.is_show == 1"><span class="tint" :style="{ background: accent }"></span><span class="v" :style="data.finance.balance.color ? { color: data.finance.balance.color } : { color: accent }">0.00</span><span class="l">{{ data.finance.balance.name }}</span></div>
          <div class="a-item" v-if="data.finance.price && data.finance.price.is_show == 1"><span class="v" :style="data.finance.price.color ? { color: data.finance.price.color } : {}">88.50</span><span class="l">{{ data.finance.price.name }}</span></div>
          <div class="a-item" v-if="data.finance.integral.is_show == 1"><span class="v" :style="data.finance.integral.color ? { color: data.finance.integral.color } : {}">100</span><span class="l">{{ data.finance.integral.name }}</span></div>
          <div class="a-item" v-if="data.finance.coupon.is_show == 1"><span class="v" :style="data.finance.coupon.color ? { color: data.finance.coupon.color } : {}">2</span><span class="l">{{ data.finance.coupon.name }}</span></div>
        </div>
      </div>

      <!-- C. 订单卡 -->
      <div class="panel" @click="onEdit('order')" v-if="data.order && data.order.is_show != 0" :class="{ 'active-block': activeBlock === 'order' }">
        <div class="panel-head"><span class="t">{{ data.order.title }}</span><span class="more">{{ data.order.sub_title }} <i class="el-icon-arrow-right"></i></span></div>
        <div class="order-grid">
          <div class="o-item" v-for="key in orderKeys" :key="key" v-if="data.order[key] && data.order[key].is_show == 1">
            <div class="o-icon">
              <el-image :src="data.order[key].icon" class="img" fit="contain"><div slot="error"><i class="el-icon-goods"></i></div></el-image>
              <div class="badge" :style="{ background: accent }" v-if="key === 'status_0'">1</div>
            </div>
            <span class="o-t">{{ data.order[key].name }}</span>
          </div>
        </div>
      </div>

      <!-- D. 工具栏1/2 -->
      <div class="panel" v-for="block in ['menus','menus2']" :key="block" v-if="data[block] && data[block].is_show == 1"
        @click="onEdit(block)" :class="{ 'active-block': activeBlock === block }">
        <div class="panel-head"><span class="t">{{ data[block].name }}</span></div>
        <div class="tool-grid" v-if="data[block].type == 0">
          <div class="tool" v-for="(item, i) in data[block].list" :key="i">
            <div class="tool-bg" :style="{ background: getMenuColor(i) }"><el-image :src="item.icon" class="tool-img" fit="contain"><div slot="error"><i class="el-icon-menu"></i></div></el-image></div>
            <span class="tool-t">{{ item.name }}</span>
          </div>
        </div>
        <div class="tool-list" v-else>
          <div class="row" v-for="(item, i) in data[block].list" :key="i"><span class="left"><el-image :src="item.icon" class="ri"></el-image>{{ item.name }}</span><i class="el-icon-arrow-right"></i></div>
        </div>
      </div>

      <!-- E. 版权 -->
      <div class="footer" @click="onEdit('copyright')" :class="{ 'active-block': activeBlock === 'copyright' }" v-if="data.copyright && data.copyright.is_show == 1">
        <span>{{ data.copyright.text }}</span>
      </div>
      <div style="height: 20px;"></div>
    </div>

    <!-- Tabbar -->
    <div class="mobile-tabbar" @click="onEdit('tabbar')" :class="{ 'active-block': activeBlock === 'tabbar' }">
      <div class="tab-item" v-for="(item, i) in data.tabbar.list" :key="i">
        <el-image class="icon" :src="i === 0 ? item.active_icon : item.icon" fit="contain"><div slot="error"><i class="el-icon-s-home"></i></div></el-image>
        <div class="text" :style="{ color: i === 0 ? data.tabbar.color : '#999' }">{{ item.name }}</div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PreviewCard',
  props: {
    data: { type: Object, required: true },
    activeBlock: { type: String, default: '' }
  },
  data() {
    return {
      orderKeys: ['status_0', 'status_1', 'status_2', 'status_3', 'status_4'],
      menuColors: [
        'rgba(92, 124, 250, 0.08)', 'rgba(255, 146, 43, 0.08)', 'rgba(32, 201, 151, 0.08)',
        'rgba(250, 82, 82, 0.08)', 'rgba(250, 176, 5, 0.08)', 'rgba(134, 142, 150, 0.08)'
      ]
    }
  },
  computed: {
    accent() {
      return (this.data.colors && this.data.colors.header_from) || '#5B6CF0'
    },
    heroStyle() {
      const c = this.data.colors || {}
      const from = c.header_from || '#5B6CF0'
      const to = c.header_to || c.header_from || '#8A7CF0'
      return { background: `linear-gradient(135deg, ${from}, ${to})` }
    },
    hasHeaderStats() {
      const h = this.data.header
      return (h.goods && h.goods.is_show == 1) || (h.footmark && h.footmark.is_show == 1) || (h.order && h.order.is_show == 1)
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
.theme-card {
  height: 100%;
  position: relative;
  background-color: #f5f6f8;
  font-family: -apple-system, sans-serif;
  overflow: hidden;

  .mobile-body {
    height: 100%;
    overflow-y: auto;
    padding-bottom: 60px;

    &::-webkit-scrollbar { display: none; }
  }

  .active-block {
    border: 2px solid var(--admin-accent-text) !important;
    box-shadow: 0 0 12px rgba(var(--admin-accent-rgb), 0.2) !important;
  }

  .hero {
    position: relative;
    overflow: hidden;
    padding: 50px 18px 70px;
    border-radius: 0 0 22px 22px;
    border: 2px solid transparent;
    cursor: pointer;

    .deco { position: absolute; border-radius: 50%; background: rgba(255, 255, 255, 0.12); }
    .deco-1 { top: -70px; right: -45px; width: 180px; height: 180px; }
    .deco-2 { bottom: 6px; left: -55px; width: 130px; height: 130px; background: rgba(255, 255, 255, 0.08); }

    .hero-user {
      position: relative;
      z-index: 1;
      display: flex;
      align-items: center;

      .avatar {
        border: 2px solid rgba(255, 255, 255, 0.4);
        background: rgba(255, 255, 255, 0.2);
      }

      .info {
        flex: 1;
        margin-left: 12px;

        .name-row {
          display: flex;
          align-items: center;

          .name { font-size: 17px; font-weight: 600; color: #fff; }
          .level { margin-left: 8px; padding: 1px 8px; border-radius: 10px; background: rgba(255, 255, 255, 0.25); color: #fff; font-size: 10px; }
        }

        .uid { margin-top: 4px; font-size: 11px; color: rgba(255, 255, 255, 0.8); }
      }

      .sign { background: #fff; padding: 5px 14px; border-radius: 14px; font-size: 12px; font-weight: 500; }
    }

    .hero-stats {
      position: relative;
      z-index: 1;
      display: flex;
      margin-top: 18px;

      .stat {
        flex: 1;
        text-align: center;

        .num { display: block; font-size: 16px; font-weight: 600; color: #fff; }
        .lbl { font-size: 11px; color: rgba(255, 255, 255, 0.8); }
      }
    }
  }

  .panel {
    position: relative;
    margin: 12px 14px 0;
    background: #fff;
    border-radius: 14px;
    padding: 16px 16px 4px;
    border: 2px solid transparent;
    cursor: pointer;

    box-shadow: 0 8px 22px rgba(0, 0, 0, 0.05);

    &.assets { margin-top: -52px; box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08); }

    .panel-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 14px;

      .t { font-size: 15px; font-weight: 600; color: #1a1a1a; }
      .more { font-size: 11px; color: #b0b3b8; }
    }
  }

  .assets-row {
    display: flex;
    padding-bottom: 14px;

    .a-item {
      position: relative;
      flex: 1;
      text-align: center;

      .tint { position: absolute; left: 3px; right: 3px; top: -5px; bottom: -5px; opacity: 0.08; border-radius: 9px; }
      .v { position: relative; display: block; font-size: 20px; font-weight: 700; color: #1a1a1a; letter-spacing: -0.3px; }
      .l { position: relative; font-size: 11px; color: #9aa0a6; margin-top: 3px; }
    }
  }

  .order-grid {
    display: flex;
    padding-bottom: 12px;

    .o-item {
      flex: 1;
      text-align: center;

      .o-icon {
        position: relative;
        display: inline-block;

        .img { width: 24px; height: 24px; }
        .badge { position: absolute; top: -4px; right: -8px; min-width: 14px; height: 14px; line-height: 14px; padding: 0 3px; border-radius: 7px; color: #fff; font-size: 9px; text-align: center; }
      }

      .o-t { display: block; font-size: 11px; color: #5a5a5a; margin-top: 5px; }
    }
  }

  .tool-grid {
    display: flex;
    flex-wrap: wrap;
    padding-bottom: 6px;

    .tool {
      width: 25%;
      text-align: center;
      margin-bottom: 12px;

      .tool-bg { width: 42px; height: 42px; border-radius: 12px; margin: 0 auto 6px; display: flex; align-items: center; justify-content: center; .tool-img { width: 22px; height: 22px; } }
      .tool-t { font-size: 11px; color: #5a5a5a; }
    }
  }

  .tool-list {
    .row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 11px 0;
      border-bottom: 1px solid #f3f4f6;

      &:last-child { border-bottom: none; }

      .left { display: flex; align-items: center; font-size: 13px; color: #333; .ri { width: 20px; height: 20px; margin-right: 8px; } }
      i { color: #ccc; }
    }
  }

  .footer {
    text-align: center;
    padding: 14px 0;
    border: 2px solid transparent;
    cursor: pointer;

    span { font-size: 10px; color: #c8c9cc; }
  }

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

      .icon { width: 24px; height: 24px; display: block; margin: 0 auto; }
      .text { font-size: 10px; margin-top: 2px; }
    }
  }
}
</style>
