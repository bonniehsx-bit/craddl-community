<template>
	<tui-page ref="page" class="mp-pay-container">
		<!-- 默认显示加载状态 -->
		<view v-if="!resultMessage" class="state-view">
			<tui-loading :is-fixed="false"></tui-loading>
			<view class="state-text">正在安全拉起微信支付...</view>
		</view>

		<!-- 显示最终结果 -->
		<view v-else class="state-view">
			<tui-icon :name="isSuccess ? 'check-circle' : 'close-circle'" :color="isSuccess ? '#19be6b' : '#fa3534'"
				:size="64"></tui-icon>
			<view class="state-text result">{{ resultMessage }}</view>
			<view class="button-wrapper">
				<!-- open-type="launchApp" 可以让用户在支付完成后，点击按钮方便地返回APP -->
				<tui-button open-type="launchApp" app-parameter="from=mp-pay" width="400rpx" height="80rpx"
					:type="isSuccess ? 'green' : 'red'" shape="circle" shadow>返回APP
				</tui-button>
			</view>
		</view>
	</tui-page>
</template>

<script>
	import payment from '@/utils/payment.js'; // 确保路径正确

	export default {
		data() {
			return {
				resultMessage: '', // 用于显示最终结果的消息
				isSuccess: false, // 支付是否成功
			}
		},
		onLoad: function(options) {
			if (!options.key) {
				this.isSuccess = false;
				this.resultMessage = '支付参数错误，请返回APP重试';
				return;
			}
			this.launchPayment(options.key);
		},
		methods: {
			/**
			 * [已重构] 使用 async/await 优化支付流程
			 */
			async launchPayment(key) {
				try {
					// 1. uni.login (将其 Promise 化以支持 await)
					const loginRes = await new Promise((resolve, reject) => {
						uni.login({
							provider: "weixin",
							scopes: 'auth_base',
							success: resolve,
							fail: reject
						});
					});

					const code = loginRes.code;

					// 2. 请求后端获取支付参数和token (使用 await)
					const payRes = await this.$request({
						url: '/api/pay/mp-launch-pay',
						data: {
							key: key,
							code: code,
							res: loginRes // 你原来有传 res，这里保留
						}
					});

					// 3. 检查后端返回
					if (payRes.code !== 0) {
						// 如果后端返回错误，直接抛出，会被外层 catch 捕获
						throw new Error(payRes.msg || '获取支付参数失败');
					}

					// 注:本中转页只负责拉起支付+显示结果+返回APP,不需要登录态。
					// 后端已不再下发 access_token(下发会改写并失效 App 端登录态),故此处不再 setToken。

					// 4. 拉起微信支付 (使用 try...catch 区分支付取消和支付失败)
					try {
						await payment.pay(payRes.data.pay_data, payRes.data.pay_data.payment);

						// --- 支付成功 ---
						this.isSuccess = true;
						this.resultMessage = '支付成功';

					} catch (paymentError) {
						// --- 支付失败或取消 ---
						console.log('支付失败或取消:', paymentError);
						this.isSuccess = false;

						if (paymentError && paymentError.errMsg && paymentError.errMsg.includes('cancel')) {
							this.resultMessage = '支付已取消';
						} else {
							this.resultMessage = '支付失败，请重试';
						}
					}

				} catch (error) {
					// --- 捕获所有上游错误 (login失败, $request失败, 后端code!=0) ---
					console.error('支付流程出错:', error);
					this.isSuccess = false;
					this.resultMessage = error.message || '支付初始化失败，请重试';
				}
			}
		}
	}
</script>
<style lang="scss">
	.mp-pay-container {
		display: flex;
		align-items: center;
		justify-content: center;
		height: 100vh;
		background-color: #FFFFFF;
	}

	.state-view {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
	}

	.state-text {
		color: #999;
		font-size: 30rpx;
		margin-top: 20rpx;

		&.result {
			color: #333;
			font-size: 36rpx;
			font-weight: bold;
		}
	}

	.button-wrapper {
		margin-top: 80rpx;
	}
</style>