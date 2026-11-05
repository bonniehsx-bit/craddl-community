<template>
  <div class="app-container coupon-manage">
    <el-menu :default-active="activeName" class="side-menu" @select="onSelect">
      <el-menu-item index="coupon">
        <i class="el-icon-tickets"></i>
        <span slot="title">{{ $noun('coupon') }}列表</span>
      </el-menu-item>
      <el-menu-item index="user">
        <i class="el-icon-user"></i>
        <span slot="title">用户{{ $noun('coupon') }}</span>
      </el-menu-item>
    </el-menu>
    <div class="main">
      <coupon-list v-if="activeName === 'coupon'"></coupon-list>
      <user-coupon-list v-if="activeName === 'user'"></user-coupon-list>
    </div>
  </div>
</template>

<script>
// 发券管理:左侧菜单切换 优惠券列表 / 用户优惠券(原为两个独立菜单)
import CouponList from './coupon-list.vue';
import UserCouponList from './user-coupon-list.vue';

const TABS = ['coupon', 'user'];

export default {
  name: 'MarketingCouponManage',
  components: { CouponList, UserCouponList },
  data() {
    const tab = this.$route.query.tab;
    return {
      activeName: TABS.includes(tab) ? tab : 'coupon',
    };
  },
  methods: {
    // 当前项记到地址栏,刷新后停在原处
    onSelect(name) {
      if (name === this.activeName) return;
      this.activeName = name;
      this.$router.replace({ query: Object.assign({}, this.$route.query, { tab: name }) });
    },
  },
};
</script>

<style scoped>
.coupon-manage { display: flex; align-items: flex-start; gap: 16px; }
.side-menu { width: 160px; flex-shrink: 0; border: 1px solid #EBEEF5; border-radius: 4px; }
.side-menu .el-menu-item { height: 46px; line-height: 46px; }
.main { flex: 1; min-width: 0; }
</style>
