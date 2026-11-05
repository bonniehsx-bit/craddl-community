<template>
  <el-drawer 
    :title="goodsId ? '编辑商品' : (similarFrom ? '发布相似商品' : '新建商品')" 
    :visible.sync="drawerVisible" 
    :size="drawerSize"
    :before-close="handleClose" 
    :destroy-on-close="true" 
    custom-class="goods-edit-drawer" 
    :wrapperClosable="true"
  >
    <!-- 极简内核：当抽屉打开时懒加载注入底层组件，并实现了属性透传和插槽穿透！ -->
    <app-base-goods-panel 
      v-if="drawerVisible"
      :goods-id="goodsId" 
      :similar-from="similarFrom"
      :goods-type="goodsType"
      :api-url="apiUrl"
      :hidden-tabs="hiddenTabs"
      :hidden-fields="hiddenFields"
      :disable-multi-spec="disableMultiSpec"
      :require-mch="requireMch"
      :is-drawer="true"
      @cancel="handleClose" 
      @success="handleSuccess" 
    >
      <!-- 将上层传下来的 slot 透传给 app-base-goods-panel -->
      <template #extra-tabs="{ form }">
        <slot name="extra-tabs" :form="form"></slot>
      </template>
    </app-base-goods-panel>
  </el-drawer>
</template>

<script>
import AppBaseGoodsPanel from './app-base-goods-panel.vue'

export default {
  name: 'GoodsEditDrawer',
  components: {
    AppBaseGoodsPanel
  },
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    // 传入商品ID，如果有ID代表编辑，无ID代表新建
    goodsId: {
      type: [String, Number],
      default: ''
    },
    // 以哪个商品为模板新建(发布相似商品);和 goodsId 二选一
    similarFrom: {
      type: [String, Number],
      default: ''
    },
    // 透传到底层的参数
    goodsType: {
      type: String,
      default: ''
    },
    apiUrl: {
      type: String,
      default: '/mall/goods/edit'
    },
    hiddenTabs: {
      type: Array,
      default: () => []
    },
    hiddenFields: {
      type: Array,
      default: () => []
    },
    // 是否禁用多规格按钮
    disableMultiSpec: {
      type: Boolean,
      default: false
    },
    // 是否必填商户
    requireMch: {
      type: Boolean,
      default: false
    },
    // 抽屉宽度覆盖。留空 = 默认 90%。
    size: {
      type: String,
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
    },
    // 默认 90%(商品表单字段多,80% 时两栏挤);调用方传了 size 就以它为准
    drawerSize() {
      return this.size || '90%';
    }
  },
  methods: {
    handleClose() {
      this.drawerVisible = false;
    },
    handleSuccess(data) {
      this.drawerVisible = false;
      this.$emit('success', data); // 通知外部刷新
    }
  }
}
</script>

<style lang="scss">
/* 抽屉样式已交回给 app-base-goods-panel 中响应 is-drawer 模式处理 */
.goods-edit-drawer {
  display: flex;
  flex-direction: column;

  .el-drawer__header {
    margin-bottom: 0;
    padding: 15px 20px;
    border-bottom: 1px solid #ebeef5;
    font-size: 16px;
    font-weight: 600;
    color: #303133;
  }

  .el-drawer__body {
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }
}
</style>
