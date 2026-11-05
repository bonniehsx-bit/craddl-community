<template>
	<view class="qr-code-generator">
		<!-- 二维码展示区 -->
		<view class="qr-code-wrapper" v-if="qrCodeBase64">
			<image class="qr-code-image" :src="qrCodeBase64" mode="aspectFit" show-menu-by-longpress></image>
			<text class="tip">长按二维码可识别</text>
		</view>
		<view class="qr-code-placeholder" v-else>
			<!-- [修改] 增加加载动画的class -->
			<uni-icons v-if="isLoading" type="spinner-cycle" size="50" color="#e0e0e0"
				class="uni-icons-spinner-cycle"></uni-icons>
			<uni-icons v-else type="qrcode" size="50" color="#e0e0e0"></uni-icons>
			<text class="tip">{{ loadingText }}</text>
		</view>
	</view>
</template>

<script>
	export default {
		name: "qr-code-generator",
		props: {
			text: {
				type: String,
				default: ''
			}
		},
		data() {
			return {
				qrCodeBase64: '',
				isLoading: false,
			};
		},
		computed: {
			loadingText() {
				if (this.isLoading) {
					return '正在生成...';
				}
				if (!this.text) {
					return '等待内容输入';
				}
				return '生成失败'; // 默认的失败提示
			}
		},
		watch: {
			text: {
				handler(newVal) {
					if (newVal) {
						this.generateQRCode(newVal);
					} else {
						this.qrCodeBase64 = '';
					}
				},
				immediate: true
			}
		},
		methods: {
			async generateQRCode(textToEncode) {
				this.isLoading = true;
				this.qrCodeBase64 = ''; // 开始生成前清空旧的二维码

				try {
					const res = await this.$request({
						url: '/api/qrcode/base64', // 请确保您的请求封装能正确处理这个URL
						method: 'POST',
						data: {
							content: textToEncode,
							output: 'base64'
						}
					});

					if (res.code === 0 && res.data.base64) {
						this.qrCodeBase64 = res.data.base64;
					} else {
						// 即使失败，也结束加载状态，UI会显示“生成失败”
						console.error(res.msg || '二维码生成失败');
					}
				} catch (e) {
					console.error('网络请求失败', e);
				} finally {
					this.isLoading = false;
				}
			}
		}
	}
</script>

<style lang="scss" scoped>
	.qr-code-generator {
		display: flex;
		justify-content: center;
		padding: 20rpx 0;
	}

	.qr-code-wrapper,
	.qr-code-placeholder {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		width: 400rpx;
		height: 400rpx;
		background-color: #fff; // [新增] 添加白色背景以突出
		border: 1rpx solid #f0f0f0; // [修改] 边框颜色变浅
		border-radius: 12rpx;
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05); // [新增] 添加细微阴影
	}

	.qr-code-image {
		width: 380rpx;
		height: 380rpx;
	}

	.tip {
		font-size: 24rpx;
		color: #999;
		margin-top: 16rpx;
	}

	.uni-icons-spinner-cycle {
		animation: spin 1s linear infinite;
	}

	@keyframes spin {
		from {
			transform: rotate(0deg);
		}

		to {
			transform: rotate(360deg);
		}
	}
</style>