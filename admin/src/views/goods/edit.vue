<template>
  <div class="app-container">
    <el-card class="box-card" v-loading="is_loading">
      <div slot="header" class="clearfix">
        <el-button type="text" icon="el-icon-arrow-left" @click="$go.back()"></el-button>
        <span>商品编辑</span>
      </div>

      <!-- 核心包裹：彻底调用统一的商品池面板 -->
      <app-base-goods-panel 
        :goods-id="goodsId" 
        @cancel="$go.back()" 
        @success="handleSuccess" 
      />

    </el-card>
  </div>
</template>

<script>
import AppBaseGoodsPanel from '@/components/goods/app-base-goods-panel.vue'

export default {
  name: 'goods-edit',
  components: {
    AppBaseGoodsPanel
  },
  data() {
    return {
      is_loading: false,
      goodsId: ''
    }
  },
  mounted() {
    this.goodsId = this.$route.query.id || '';
  },
  methods: {
    handleSuccess() {
      // 传统页面模式保存成功后退回列表
      setTimeout(() => {
        this.$go.back();
      }, 500);
    }
  }
}
</script>

<style lang="scss">
/* 页面内特殊样式已归拢至底层，如果外壳需要留边距可写此 */
</style>
