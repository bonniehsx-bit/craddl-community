<template>
	<view class="pickup-card" v-if="shouldShow">
		<view class="card-title">
			<tui-icon name="qrcode" :size="18" :color="themeColor" style="margin-right:10rpx"></tui-icon>
			<text>{{ cardTitle }}</text>
		</view>

		<view class="qr-wrapper">
			<qr-code-generator :text="String(order.pickup_code)" :size="300" :color="themeColor"></qr-code-generator>
		</view>

		<view class="code-text" @tap="copyText(order.pickup_code)">
			<text class="code-val">{{ order.pickup_code }}</text>
			<text class="copy-tip">点击复制</text>
		</view>

		<view class="tips">
			<text>{{ tipText }}</text>
			<text v-if="order.clerk_name" class="clerk">已绑定店员：{{ order.clerk_name }}</text>
		</view>
	</view>
</template>

<script>
import QrCodeGenerator from '@/components/QrCodeGenerator/QrCodeGenerator.vue';
import { copyText } from './utils.js';

export default {
	name: 'OrderPickupCodeCard',
	components: { QrCodeGenerator },
	props: {
		order: { type: Object, required: true }
	},
	computed: {
		/**
		 * 【通用核销码卡】凡是订单有 pickup_code 且处于可核销阶段都展示
		 * 适配场景：
		 *   · Normal 自提订单（deliver_type=pickup, status 20/30）
		 *   · 团购/代金券订单（deliver_type=none, status 20/30/40 → 核销前都可看码）
		 * 已核销/过期的状态由 order.status/refund_status 隐式控制（status=0/已取消 不显示）
		 */
		shouldShow() {
			if (!this.order || !this.order.pickup_code) return false;
			const s = Number(this.order.status);
			// 已支付待核销 / 已发货待核销 / 交易完成（方便查询旧码）
			return [20, 30, 40].includes(s);
		},
		/** 顶部标题：根据是否已完成动态切换 */
		cardTitle() {
			return Number(this.order.status) === 40 ? '核销码' : '到店核销码';
		},
		tipText() {
			if (Number(this.order.status) === 40) return '本订单已完成';
			return '请向店员出示此码以完成核销';
		}
	},
	methods: { copyText }
};
</script>

<style scoped lang="scss">
.pickup-card {
	background: #fff;
	border-radius: 20rpx;
	margin: 20rpx 24rpx 0;
	padding: 30rpx 28rpx;
	display: flex;
	flex-direction: column;
	align-items: center;

	.card-title {
		display: flex;
		align-items: center;
		width: 100%;
		font-size: 28rpx;
		font-weight: 600;
		color: #1A1A1A;
		margin-bottom: 24rpx;
	}

	.qr-wrapper {
		background: #fff;
		padding: 10rpx;
		border: 1rpx solid #f0f0f0;
		border-radius: 16rpx;
	}

	.code-text {
		margin-top: 20rpx;
		display: flex;
		align-items: center;
		gap: 12rpx;

		.code-val {
			font-size: 30rpx;
			font-weight: 600;
			letter-spacing: 4rpx;
			color: #1A1A1A;
			font-family: 'DIN Alternate', sans-serif;
		}
		.copy-tip {
			font-size: 22rpx;
			color: var(--theme-color, #E63D3D);
			padding: 2rpx 12rpx;
			border: 1rpx solid currentColor;
			border-radius: 20rpx;
		}
	}

	.tips {
		margin-top: 16rpx;
		font-size: 24rpx;
		color: #999;
		text-align: center;

		.clerk {
			display: block;
			margin-top: 6rpx;
			color: #666;
		}
	}
}
</style>
