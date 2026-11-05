<template>
    <div class="app-container">
        <!-- 顶部说明：讲清这页是"用户下单时的收款方式"，按服务商分组 -->
        <el-alert type="info" :closable="false" show-icon class="pay-guide">
            <template slot="title">
                <span class="pay-guide__title">收款方式配置</span>
            </template>
            <div class="pay-guide__body">
                <p>
                    这里配置<b>用户下单付款时可用的收款通道</b>，按服务商分组——每个 tab 填该通道的密钥并开关。
                </p>
                <p>
                    用户端最终展示哪些方式，取决于<b>其所处平台</b>（微信小程序 / 公众号H5 / App）<b>与你开启的通道</b>，系统自动匹配，无需在此指定优先级。
                    （注：本页是“收钱”；给用户/代理“打钱”的提现配置在<b>「提现资金配置」</b>那一页。）
                </p>
            </div>
        </el-alert>

        <el-tabs v-model="activeName" type="border-card">
            <el-tab-pane label="微信官方" name="wechatAuth">
                <el-tabs tab-position="left">
                    <el-tab-pane label="小程序支付">
                        <mpwx />
                    </el-tab-pane>
                    <el-tab-pane label="公众号H5支付">
                        <wechat />
                    </el-tab-pane>
                    <el-tab-pane label="App原生支付">
                        <wechat-app />
                    </el-tab-pane>
                    <el-tab-pane label="企业微信支付">
                        <wxwork />
                    </el-tab-pane>
                </el-tabs>
            </el-tab-pane>

            <el-tab-pane label="快付通道(FastPay)" name="fastPay">
                <el-tabs tab-position="left">
                    <el-tab-pane label="快付小程序"><fast-mpwx /></el-tab-pane>
                    <el-tab-pane label="快付支付宝JS"><fast-ali-js /></el-tab-pane>
                    <el-tab-pane label="快付微信JS"><fast-wechat-js /></el-tab-pane>
                    <el-tab-pane label="银行卡快捷支付"><fast-quick /></el-tab-pane>
                </el-tabs>
            </el-tab-pane>


            <el-tab-pane label="汇聚通道" name="huijuPay">
                <el-tabs tab-position="left">
                    <el-tab-pane label="汇聚小程序">
                        <hjmpwx />
                    </el-tab-pane>
                </el-tabs>
            </el-tab-pane>

            <el-tab-pane label="基础 / 线下" name="base">
                <el-tabs tab-position="left">
                    <el-tab-pane label="余额支付">
                        <balance />
                    </el-tab-pane>
                    <el-tab-pane label="后台确认收款">
                        <system />
                    </el-tab-pane>
                </el-tabs>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script>
// 收款通道配置 —— 聚合模式单菜单入口，按服务商分组
// 注：本页是"收款"（用户下单付钱）；"打款/提现"配置在 fund/index.vue
// 1. 自动引入 components 目录下的组件
import mpwx from './components/mpwx.vue';
import wechat from './components/wechat.vue';
import wechatApp from './components/wechat-app.vue';
import wxwork from './components/wxwork.vue';
import balance from './components/balance.vue';
import system from './components/system.vue';
import fastMpwx from './components/fast-mpwx.vue';
import fastAliJs from './components/fastAliJs.vue';
import fastWechatJs from './components/fastWechatJs.vue';
import fastQuick from './components/fast-quick.vue';
import hjmpwx from './components/hjmpwx.vue';

export default {
    name: "PaymentIndex",
    components: {
        mpwx,
        wechat,
        wechatApp,
        wxwork,
        balance,
        system,
        fastMpwx,
        fastAliJs,
        fastWechatJs,
        fastQuick,
        hjmpwx
    },
    data() {
        return {
            activeName: 'wechatAuth' // 默认激活 tab
        }
    }
}
</script>

<style scoped>
.app-container {
    padding: 20px;
}

.pay-guide {
    margin-bottom: 16px;
    align-items: flex-start;
}

.pay-guide__title {
    font-weight: 600;
    font-size: 14px;
}

.pay-guide__body {
    margin-top: 4px;
    font-size: 13px;
    line-height: 1.7;
    color: #5a5e66;
}

.pay-guide__body p {
    margin: 2px 0;
}
</style>
