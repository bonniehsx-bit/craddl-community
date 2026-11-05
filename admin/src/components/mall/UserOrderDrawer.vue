<template>
  <el-drawer title="用户专属订单库" :visible.sync="drawerVisible" size="80%" :destroy-on-close="true" append-to-body
    custom-class="user-order-drawer" @close="handleClose">
    <div class="drawer-content-body">
      <order-index :is-component="true" :user-id="userId" />
    </div>
  </el-drawer>
</template>

<script>
import OrderIndex from '@/views/order/index.vue'

export default {
  name: 'UserOrderDrawer',
  components: {
    OrderIndex
  },
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    userId: {
      type: [String, Number],
      default: ''
    }
  },
  computed: {
    drawerVisible: {
      get() {
        return this.visible;
      },
      set(val) {
        this.$emit('update:visible', val);
      }
    }
  },
  methods: {
    handleClose() {
      this.$emit('close');
    }
  }
}
</script>

<style lang="scss" scoped>
/* 保证抽屉内的内容区实现真正的自适应全高 */
::v-deep .user-order-drawer {
  .el-drawer__body {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    height: 100%;

    .drawer-content-body {
      flex: 1;
      padding: 0 15px 15px 15px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
  }
}
</style>
