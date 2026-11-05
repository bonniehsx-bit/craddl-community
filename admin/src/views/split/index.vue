<template>
    <div class="app-container">
        <el-tabs v-model="activeName" type="border-card">
            <el-tab-pane label="快付通道(FastPay)" name="fastpay">
                <el-tabs tab-position="left">
                    <el-tab-pane label="快付分账"><fastpay /></el-tab-pane>
                </el-tabs>
            </el-tab-pane>

            <!--
              未来扩展：在此追加新的分账渠道分组，例如
              <el-tab-pane label="XX通道" name="xxpay">
                  <el-tabs tab-position="left">
                      <el-tab-pane label="XX分账"><xx-split /></el-tab-pane>
                  </el-tabs>
              </el-tab-pane>
              对应组件放入 split/components/，在 components 中注册即可。
            -->
        </el-tabs>
    </div>
</template>

<script>
// 分账通道配置 —— 仿 payment/index.vue 聚合模式，单菜单入口，按渠道分组，便于未来扩展
import fastpay from './components/fastpay.vue';

export default {
    name: "SplitIndex",
    components: {
        fastpay,
    },
    data() {
        return {
            activeName: 'fastpay'
        }
    }
}
</script>

<style scoped>
.app-container {
    padding: 20px;
}
</style>
