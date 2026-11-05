<template>
  <el-drawer
    title="用户直推网络"
    :visible.sync="drawerVisible"
    size="1200px"
    :destroy-on-close="true"
    append-to-body
    custom-class="user-friends-drawer"
    @close="handleClose"
  >
    <div class="drawer-content-body">
      <!-- 传递 isComponent 和 parentId 给动态加载的 user-index 组件 -->
      <component :is="userIndexComponent" :is-component="true" :parent-id="parentId" />
    </div>
  </el-drawer>
</template>

<script>
export default {
  name: 'UserFriendsDrawer',
  components: {
    // 异步挂载 user-index 防止生命周期递归解析死循环
    componentUserIndex: () => import('@/views/user/index.vue')
  },
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    parentId: {
      type: [String, Number],
      default: ''
    }
  },
  data() {
    return {
      userIndexComponent: 'componentUserIndex'
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
::v-deep .user-friends-drawer {
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
