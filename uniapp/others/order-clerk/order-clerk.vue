<template>
	<view class="page-container" :style="{ '--theme-color': themeColor }">

		<view class="header-bg" :style="{ backgroundColor: themeColor }">
			<view class="header-content">
				<view class="title">核销确认</view>
				<view class="subtitle">请核对提货单信息后进行核销</view>
			</view>
		</view>

		<view class="content-box">
			<view class="card status-card">
				<uni-icons :type="isPending ? 'checkbox-filled' : 'info-filled'" size="60"
					:color="isPending ? themeColor : '#999'"></uni-icons>
				<view class="status-text">{{ orderInfo.status_text || '加载中...' }}</view>
				<view class="status-tips" v-if="isPending">等待核销</view>
				<view class="status-tips" v-if="isFinished">
					该订单已于 {{ orderInfo.confirm_at || orderInfo.send_at || '近期' }} 完成核销
				</view>
			</view>

			<view class="card info-card" v-if="orderInfo && orderInfo.id">
				<view class="info-row">
					<text class="label">订单类型</text>
					<text class="value">云仓提货单</text>
				</view>
				<view class="info-row">
					<text class="label">订单编号</text>
					<text class="value">{{ orderInfo.order_no }}</text>
				</view>
				<view class="info-row">
					<text class="label">下单时间</text>
					<text class="value">{{ orderInfo.created_at }}</text>
				</view>
				<view class="info-row">
					<text class="label">提货人</text>
					<text class="value">{{ orderInfo.name }} ({{ orderInfo.mobile }})</text>
				</view>
				<view class="info-row" v-if="orderInfo.pickup_code">
					<text class="label">核销码</text>
					<text class="value code">{{ orderInfo.pickup_code }}</text>
				</view>
				<view class="info-row" v-if="isFinished && orderInfo.clerk_name">
					<text class="label">核销人</text>
					<text class="value">{{ orderInfo.clerk_name }}</text>
				</view>
			</view>

			<view class="card goods-card" v-if="orderInfo && orderInfo.goods_list && orderInfo.goods_list.length">
				<view class="card-title">商品明细</view>
				<view class="goods-item" v-for="(item, index) in orderInfo.goods_list" :key="index">
					<image :src="item.cover_pic" class="goods-img" mode="aspectFill"></image>
					<view class="goods-info">
						<view class="goods-name">{{ item.goods_name }}</view>
						<view class="goods-attr" v-if="item.attr_info">{{ item.attr_info }}</view>
						<view class="goods-price-row">
							<text class="num">x {{ item.num }}</text>
						</view>
					</view>
				</view>
			</view>

			<view class="action-box">
				<button class="btn-confirm" :style="{ backgroundColor: themeColor }"
					:disabled="isProcessing || !isPending" @click="handleConfirm">
					{{ btnText }}
				</button>
				<button class="btn-back" @click="goHome">返回首页</button>
			</view>
		</view>

		<view class="loading-mask" v-if="isLoading">
			<uni-load-more status="loading" :content-text="{ contentrefresh: '正在读取订单...' }" />
		</view>

	</view>
</template>

<script>
	import { mapState } from 'vuex';

	export default {
		data() {
			return {
				bizType: '',
				orderId: '',
				pickupCode: '',
				orderInfo: {},
				isLoading: true,
				isProcessing: false,
			}
		},
		computed: {
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D6201F'
			}),
			isPending() {
				return parseInt(this.orderInfo.status) === 2;
			},
			isFinished() {
				const status = parseInt(this.orderInfo.status);
				return status === 3 || status === 4;
			},
			btnText() {
				if (this.isLoading) return '加载中...';
				if (this.isPending) return '确认核销';
				if (this.isFinished) return '该订单已核销';
				return '无法核销';
			}
		},
		onLoad(options) {
			// 二维码深链带 type,按插件分发调各自 C端核销接口
			this.bizType = options.type || '';
			this.orderId = options.order_id || '';
			this.pickupCode = options.pickup_code || '';

			if (!this.bizType) {
				this.fail('参数缺失,无法识别业务类型');
				return;
			}
			if (!this.orderId && !this.pickupCode) {
				this.fail('参数缺失,无法获取订单信息');
				return;
			}
			this.loadOrderInfo();
		},
		methods: {
			fail(msg) {
				uni.showModal({
					title: '错误',
					content: msg,
					showCancel: false,
					success: () => uni.reLaunch({ url: '/pages/index/index' })
				});
			},

			async loadOrderInfo() {
				this.isLoading = true;
				try {
					const res = await this.$request({
						url: `/plugin/${this.bizType}/api/clerk/info`,
						method: 'GET',
						data: {
							order_id: this.orderId,
							pickup_code: this.pickupCode,
						}
					});
					if (res.code === 0) {
						this.orderInfo = res.data;
					} else {
						this.fail(res.msg);
					}
				} catch (e) {
					uni.showToast({ title: '网络请求失败', icon: 'none' });
				} finally {
					this.isLoading = false;
				}
			},

			handleConfirm() {
				uni.showModal({
					title: '核销确认',
					content: '确定要核销该订单吗?操作不可撤销。',
					confirmColor: this.themeColor,
					success: (res) => {
						if (res.confirm) this.executeVerification();
					}
				});
			},

			async executeVerification() {
				this.isProcessing = true;
				uni.showLoading({ title: '核销中...' });
				try {
					const apiRes = await this.$request({
						url: `/plugin/${this.bizType}/api/clerk/verification`,
						method: 'POST',
						data: {
							order_id: this.orderInfo.id,
							pickup_code: this.orderInfo.pickup_code,
						}
					});
					if (apiRes.code === 0) {
						uni.showToast({ title: '核销成功', icon: 'success' });
						setTimeout(() => this.loadOrderInfo(), 1500);
					} else {
						uni.showModal({ title: '核销失败', content: apiRes.msg, showCancel: false });
					}
				} catch (e) {
					uni.showToast({ title: '请求出错', icon: 'none' });
				} finally {
					uni.hideLoading();
					this.isProcessing = false;
				}
			},

			goHome() {
				uni.reLaunch({ url: '/pages/index/index' });
			}
		}
	}
</script>

<style lang="scss" scoped>
	$theme-color: var(--theme-color);

	.page-container {
		min-height: 100vh;
		background-color: #f5f5f5;
		position: relative;
	}

	.header-bg {
		height: 300rpx;
		width: 100%;
		border-bottom-left-radius: 40rpx;
		border-bottom-right-radius: 40rpx;
		padding-top: 60rpx;
		box-sizing: border-box;

		.header-content {
			text-align: center;
			color: #fff;

			.title {
				font-size: 40rpx;
				font-weight: bold;
				margin-bottom: 16rpx;
			}

			.subtitle {
				font-size: 26rpx;
				opacity: 0.9;
			}
		}
	}

	.content-box {
		padding: 0 30rpx;
		margin-top: -100rpx;
		position: relative;
		z-index: 10;
		padding-bottom: 60rpx;
	}

	.card {
		background-color: #fff;
		border-radius: 20rpx;
		margin-bottom: 24rpx;
		padding: 30rpx;
		box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.04);
	}

	.status-card {
		display: flex;
		flex-direction: column;
		align-items: center;
		padding: 50rpx 0;

		.status-text {
			font-size: 36rpx;
			font-weight: bold;
			color: #333;
			margin-top: 20rpx;
		}

		.status-tips {
			font-size: 26rpx;
			color: #999;
			margin-top: 10rpx;
		}
	}

	.info-card {
		.info-row {
			display: flex;
			justify-content: space-between;
			margin-bottom: 20rpx;
			font-size: 28rpx;

			&:last-child {
				margin-bottom: 0;
			}

			.label {
				color: #999;
			}

			.value {
				color: #333;
				font-weight: 500;
				text-align: right;
				max-width: 70%;
			}

			.value.code {
				font-family: monospace;
				font-weight: bold;
				font-size: 32rpx;
				color: $theme-color;
				letter-spacing: 2rpx;
			}
		}
	}

	.goods-card {
		.card-title {
			font-size: 30rpx;
			font-weight: bold;
			margin-bottom: 24rpx;
			padding-bottom: 20rpx;
			border-bottom: 1rpx solid #f0f0f0;
		}

		.goods-item {
			display: flex;
			margin-bottom: 24rpx;

			&:last-child {
				margin-bottom: 0;
			}

			.goods-img {
				width: 120rpx;
				height: 120rpx;
				border-radius: 10rpx;
				background-color: #f9f9f9;
				margin-right: 20rpx;
				flex-shrink: 0;
			}

			.goods-info {
				flex: 1;
				display: flex;
				flex-direction: column;
				justify-content: space-between;

				.goods-name {
					font-size: 28rpx;
					color: #333;
					line-height: 1.4;
					overflow: hidden;
					text-overflow: ellipsis;
					display: -webkit-box;
					-webkit-line-clamp: 2;
					-webkit-box-orient: vertical;
				}

				.goods-attr {
					font-size: 24rpx;
					color: #999;
				}

				.goods-price-row {
					text-align: right;

					.num {
						font-size: 26rpx;
						color: #666;
					}
				}
			}
		}
	}

	.action-box {
		margin-top: 60rpx;

		.btn-confirm {
			height: 88rpx;
			line-height: 88rpx;
			border-radius: 44rpx;
			color: #fff;
			font-size: 32rpx;
			font-weight: bold;
			margin-bottom: 30rpx;
			box-shadow: 0 8rpx 16rpx rgba(0, 0, 0, 0.1);

			&[disabled] {
				opacity: 0.6;
				background-color: #ccc !important;
			}
		}

		.btn-back {
			height: 88rpx;
			line-height: 88rpx;
			border-radius: 44rpx;
			background-color: #fff;
			color: #666;
			font-size: 30rpx;
			border: 1rpx solid #ddd;
		}
	}

	.loading-mask {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background-color: #fff;
		z-index: 999;
		display: flex;
		justify-content: center;
		align-items: center;
	}
</style>
