<template>
  <div class="theme-wrapper theme-night">
    <div class="mobile-body">

      <!-- A. 顶部(暗色,微光) -->
      <div class="top" @click="onEdit('bg')" :class="{ 'active-block': activeBlock === 'bg' }">
        <div class="glow" :style="{ background: accent }"></div>
        <div class="user">
          <div class="avatar-ring" :style="{ borderColor: accent }">
            <el-avatar :size="50" :src="data.header.avatar || ''" icon="el-icon-user-solid" class="avatar"></el-avatar>
          </div>
          <div class="info">
            <div class="name-row"><span class="name">Hi, User</span><span class="level" :style="{ background: accent }">LV.1</span></div>
            <div class="uid">ID: 888888 <i class="el-icon-document-copy"></i></div>
          </div>
          <div class="sign" style="display: none;" :style="{ background: accent }">签到</div>
        </div>
        <div class="stats" v-if="hasHeaderStats">
          <div class="stat" v-if="data.header.goods.is_show == 1"><span class="num">12</span><span class="lbl">{{ data.header.goods.name }}</span></div>
          <div class="stat" v-if="data.header.footmark.is_show == 1"><span class="num">58</span><span class="lbl">{{ data.header.footmark.name }}</span></div>
          <div class="stat" v-if="data.header.order && data.header.order.is_show == 1"><span class="num">5</span><span class="lbl">{{ data.header.order.name }}</span></div>
        </div>
      </div>

      <!-- B. 资产 -->
      <div class="panel" v-if="data.finance.is_show == 1" @click.stop="onEdit('finance')" :class="{ 'active-block': activeBlock === 'finance' }">
        <div class="head"><span class="title"><i class="bar" :style="{ background: accent }"></i>{{ data.finance.name }}</span><span class="more">资金管理 <i class="el-icon-arrow-right"></i></span></div>
        <div class="assets-row">
          <div class="a-item balance" v-if="data.finance.balance.is_show == 1"><span class="tint" :style="{ background: accent }"></span><span class="v" :style="data.finance.balance.color ? { color: data.finance.balance.color } : { color: accent }">0.00</span><span class="l">{{ data.finance.balance.name }}</span></div>
          <div class="a-item" v-if="data.finance.price && data.finance.price.is_show == 1"><span class="v" :style="data.finance.price.color ? { color: data.finance.price.color } : {}">88.50</span><span class="l">{{ data.finance.price.name }}</span></div>
          <div class="a-item" v-if="data.finance.integral.is_show == 1"><span class="v" :style="data.finance.integral.color ? { color: data.finance.integral.color } : {}">100</span><span class="l">{{ data.finance.integral.name }}</span></div>
          <div class="a-item" v-if="data.finance.coupon.is_show == 1"><span class="v" :style="data.finance.coupon.color ? { color: data.finance.coupon.color } : {}">2</span><span class="l">{{ data.finance.coupon.name }}</span></div>
        </div>
      </div>

      <!-- C. 订单 -->
      <div class="panel" @click="onEdit('order')" v-if="data.order && data.order.is_show != 0" :class="{ 'active-block': activeBlock === 'order' }">
        <div class="head"><span class="title"><i class="bar" :style="{ background: accent }"></i>{{ data.order.title }}</span><span class="more">{{ data.order.sub_title }} <i class="el-icon-arrow-right"></i></span></div>
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
        <div class="head"><span class="title"><i class="bar" :style="{ background: accent }"></i>{{ data[block].name }}</span></div>
        <div class="tool-grid" v-if="data[block].type == 0">
          <div class="tool" v-for="(item, i) in data[block].list" :key="i">
            <div class="tool-bg"><el-image :src="item.icon" class="tool-img" fit="contain"><div slot="error"><i class="el-icon-menu"></i></div></el-image></div>
            <span class="tool-t">{{ item.name }}</span>
          </div>
        </div>
        <div class="tool-list" v-else>
          <div class="row" v-for="(item, i) in data[block].list" :key="i"><span class="left"><el-image :src="item.icon" class="ri"></el-image>{{ item.name }}</span><i class="el-icon-arrow-right"></i></div>
        </div>
      </div>

      <div class="footer" @click="onEdit('copyright')" :class="{ 'active-block': activeBlock === 'copyright' }" v-if="data.copyright && data.copyright.is_show == 1">
        <span>{{ data.copyright.text }}</span>
      </div>
      <div style="height: 20px;"></div>
    </div>

    <div class="mobile-tabbar" @click="onEdit('tabbar')" :class="{ 'active-block': activeBlock === 'tabbar' }">
      <div class="tab-item" v-for="(item, i) in data.tabbar.list" :key="i">
        <el-image class="icon" :src="i === 0 ? item.active_icon : item.icon" fit="contain"><div slot="error"><i class="el-icon-s-home"></i></div></el-image>
        <div class="text" :style="{ color: i === 0 ? (data.tabbar.color || accent) : '#888' }">{{ item.name }}</div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'PreviewNight',
  props: {
    data: { type: Object, required: true },
    activeBlock: { type: String, default: '' }
  },
  data() {
    return { orderKeys: ['status_0', 'status_1', 'status_2', 'status_3', 'status_4'] }
  },
  computed: {
    accent() {
      return (this.data.colors && this.data.colors.header_from) || '#5B6CF0'
    },
    hasHeaderStats() {
      const h = this.data.header
      return (h.goods && h.goods.is_show == 1) || (h.footmark && h.footmark.is_show == 1) || (h.order && h.order.is_show == 1)
    }
  },
  methods: {
    onEdit(key) { this.$emit('edit', key) }
  }
}
</script>

<style lang="scss" scoped>
.theme-night {
  height: 100%;
  position: relative;
  background-color: #131417;
  font-family: -apple-system, sans-serif;
  overflow: hidden;

  .mobile-body { height: 100%; overflow-y: auto; padding-bottom: 60px; &::-webkit-scrollbar { display: none; } }
  .active-block { border: 2px solid var(--admin-accent-text) !important; box-shadow: 0 0 12px rgba(var(--admin-accent-rgb), 0.25) !important; }

  .top {
    position: relative;
    padding: 50px 20px 0;
    overflow: hidden;
    border: 2px solid transparent;
    cursor: pointer;

    .glow { position: absolute; top: -60px; right: -40px; width: 180px; height: 180px; border-radius: 50%; opacity: 0.30; filter: blur(40px); }

    .user {
      position: relative;
      z-index: 2;
      display: flex;
      align-items: center;

      .avatar-ring { padding: 2px; border-radius: 50%; border: 1px solid #5B6CF0; .avatar { display: block; background: #2a2c31; } }

      .info {
        flex: 1;
        margin-left: 12px;

        .name-row { display: flex; align-items: center; .name { font-size: 18px; font-weight: 600; color: #f2f3f5; } .level { margin-left: 8px; padding: 1px 8px; border-radius: 10px; color: #fff; font-size: 10px; } }
        .uid { margin-top: 4px; font-size: 11px; color: #8a8d93; }
      }

      .sign { color: #fff; padding: 5px 14px; border-radius: 14px; font-size: 12px; font-weight: 500; }
    }

    .stats {
      position: relative;
      z-index: 2;
      display: flex;
      margin-top: 18px;
      padding: 14px 0;
      border-top: 1px solid rgba(255, 255, 255, 0.06);
      border-bottom: 1px solid rgba(255, 255, 255, 0.06);

      .stat { flex: 1; text-align: center; .num { display: block; font-size: 16px; font-weight: 600; color: #f2f3f5; } .lbl { font-size: 11px; color: #8a8d93; } }
    }
  }

  .panel {
    margin: 12px 14px 0;
    background: #1d1f24;
    border-radius: 16px;
    padding: 16px 16px 4px;
    border: 2px solid transparent;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.05), 0 10px 26px rgba(0, 0, 0, 0.25);
    cursor: pointer;

    .head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 14px;

      .title { display: flex; align-items: center; font-size: 15px; font-weight: 600; color: #f2f3f5; .bar { width: 3px; height: 13px; border-radius: 2px; margin-right: 7px; } }
      .more { font-size: 11px; color: #6b6e76; }
    }
  }

  .assets-row { display: flex; padding-bottom: 12px; .a-item { position: relative; flex: 1; text-align: center; .tint { position: absolute; left: 3px; right: 3px; top: -5px; bottom: -5px; opacity: 0.16; border-radius: 9px; } .v { position: relative; display: block; font-size: 20px; font-weight: 700; letter-spacing: -0.3px; color: #f2f3f5; } .l { position: relative; font-size: 11px; color: #8a8d93; } } }

  .order-grid {
    display: flex;
    padding-bottom: 12px;
    .o-item { flex: 1; text-align: center; .o-icon { position: relative; display: inline-block; .img { width: 24px; height: 24px; } .badge { position: absolute; top: -4px; right: -8px; min-width: 14px; height: 14px; line-height: 14px; padding: 0 3px; border-radius: 7px; color: #fff; font-size: 9px; text-align: center; } } .o-t { display: block; font-size: 11px; color: #b9bcc2; margin-top: 5px; } }
  }

  .tool-grid { display: flex; flex-wrap: wrap; padding-bottom: 6px; .tool { width: 25%; text-align: center; margin-bottom: 12px; .tool-bg { width: 42px; height: 42px; border-radius: 12px; margin: 0 auto 6px; background: rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: center; .tool-img { width: 22px; height: 22px; } } .tool-t { font-size: 11px; color: #b9bcc2; } } }
  .tool-list { .row { display: flex; justify-content: space-between; align-items: center; padding: 11px 0; border-bottom: 1px solid rgba(255,255,255,0.05); &:last-child { border-bottom: none; } .left { display: flex; align-items: center; font-size: 13px; color: #d6d8dc; .ri { width: 20px; height: 20px; margin-right: 8px; } } i { color: #6b6e76; } } }

  .footer { text-align: center; padding: 14px 0; border: 2px solid transparent; cursor: pointer; span { font-size: 10px; color: #5a5d64; } }

  .mobile-tabbar {
    position: absolute; bottom: 0; left: 0; width: 100%; height: 50px; background: #1a1b1f; border-top: 1px solid rgba(255,255,255,0.06); display: flex; z-index: 99; cursor: pointer; border: 2px solid transparent;
    .tab-item { flex: 1; text-align: center; padding-top: 5px; .icon { width: 24px; height: 24px; display: block; margin: 0 auto; } .text { font-size: 10px; margin-top: 2px; } }
  }
}
</style>
