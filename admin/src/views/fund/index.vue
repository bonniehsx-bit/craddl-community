<template>
    <div class="app-container">
        <!-- 顶部说明：明确「两步走」的先后关系，避免把"能力配置"和"路由配置"混为一谈 -->
        <el-alert type="info" :closable="false" show-icon class="fund-guide">
            <template slot="title">
                <span class="fund-guide__title">代付渠道能力配置</span>
            </template>
            <div class="fund-guide__body">
                <p>
                    这里只配<b>「打款能力」</b>：把你实际要用的代付方式接好（填密钥、开开关）。
                </p>
                <p>
                    <b>用户提现页显示哪些方式、各自路由到哪个网关</b>，改在
                    <b>「提现中心 → 提现渠道」</b>里配置；这里没接好（开关未开）的网关，
                    那边的下拉里<b>选不到</b>。
                    <span style="color:#909399;">（原「提现路由」标签页对应的是已下线的老提现链路，已移除。）</span>
                </p>
            </div>
        </el-alert>

        <el-tabs v-model="activeName" type="border-card">
            <el-tab-pane label="代付渠道 · 官方" name="official">
                <el-tabs tab-position="left">
                    <el-tab-pane label="微信打款"><wechat /></el-tab-pane>
                    <el-tab-pane label="支付宝打款"><alipay /></el-tab-pane>
                    <el-tab-pane label="银行卡打款"><bank /></el-tab-pane>
                </el-tabs>
            </el-tab-pane>

            <el-tab-pane label="代付渠道 · 快付(FastPay)" name="fastpay">
                <el-tabs tab-position="left">
                    <el-tab-pane label="快付代付"><fastpay /></el-tab-pane>
                    <el-tab-pane label="灵活用工"><fastpay-flex /></el-tab-pane>
                </el-tabs>
            </el-tab-pane>


            <el-tab-pane label="代付渠道 · 基础" name="base">
                <el-tabs tab-position="left">
                    <el-tab-pane label="余额打款"><balance /></el-tab-pane>
                    <el-tab-pane label="手动打款"><manual /></el-tab-pane>
                </el-tabs>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script>
// 资金/代付通道配置 —— 仿 payment/index.vue 聚合模式，单菜单入口
// 结构：顶部「两步走」说明 → ②提现路由(channel) 提到最前 → ①各代付渠道按服务商分组
import wechat from './components/wechat.vue';
import alipay from './components/alipay.vue';
import bank from './components/bank.vue';
import fastpay from './components/fastpay.vue';
import fastpayFlex from './components/fastpay-flex.vue';
import balance from './components/balance.vue';
import manual from './components/manual.vue';

export default {
    name: "FundIndex",
    components: {
        wechat,
        alipay,
        bank,
        fastpay,
        'fastpay-flex': fastpayFlex,
        balance,
        manual,
    },
    data() {
        return {
            // 默认停在「提现路由」——这是最常调、最容易看不懂的一页
            activeName: 'official'
        }
    }
}
</script>

<style scoped>
.app-container {
    padding: 20px;
}

.fund-guide {
    margin-bottom: 16px;
    align-items: flex-start;
}

.fund-guide__title {
    font-weight: 600;
    font-size: 14px;
}

.fund-guide__body {
    margin-top: 4px;
    font-size: 13px;
    line-height: 1.7;
    color: #5a5e66;
}

.fund-guide__body p {
    margin: 2px 0;
}
</style>
