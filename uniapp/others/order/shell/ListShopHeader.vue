<template>
	<view class="list-shop-header">
		<view class="header-row" @tap.stop="$emit('action', { type: 'mchDetail', order })">
			<view class="shop-wrapper">
				<image v-if="order.mch && order.mch.id > 0" :src="order.mch.logo" class="shop-logo" mode="aspectFill"></image>
				<view v-else class="platform-icon-box">
					<tui-icon name="shop" :size="14" :color="themeColor"></tui-icon>
				</view>
				<text class="shop-name">{{ order.mch ? order.mch.mch_name : '平台自营' }}</text>
				<tui-icon name="arrowright" :size="12" color="#ccc" style="margin-left:4rpx"></tui-icon>
			</view>
			<text class="status-text" :style="{ color: statusColor }">{{ statusText }}</text>
		</view>
		<text v-if="order.order_no" class="order-no">订单号：{{ order.order_no }}</text>
	</view>
</template>

<script>
import { getStatusColor } from './utils.js';

export default {
	name: 'ListShopHeader',
	props: {
		order: { type: Object, required: true }
		// themeColor 由全局 mixin 注入
	},
	computed: {
		statusText() {
			// 后端 mobile_actions.status_text 最优先；否则用 status_text
			const ma = this.order.mobile_actions;
			if (ma && ma.status_text) return ma.status_text;
			return this.order.status_text || '';
		},
		statusColor() {
			return getStatusColor(this.order, this.themeColor);
		}
	}
};
</script>

<style scoped lang="scss">
.list-shop-header {
	display: flex;
	flex-direction: column;
	padding: 20rpx 24rpx 12rpx;

	.header-row {
		display: flex;
		justify-content: space-between;
		align-items: center;
	}

	.order-no {
		margin-top: 8rpx;
		font-size: 22rpx;
		color: #999;
	}

	.shop-wrapper {
		display: flex;
		align-items: center;
		flex: 1;
		overflow: hidden;

		.shop-logo {
			width: 36rpx;
			height: 36rpx;
			border-radius: 50%;
			margin-right: 10rpx;
			background: #f5f5f5;
		}
		.platform-icon-box {
			width: 36rpx;
			height: 36rpx;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			background: rgba(230, 61, 61, 0.08);
			margin-right: 10rpx;
		}
		.shop-name {
			font-size: 26rpx;
			color: #333;
			font-weight: 500;
			max-width: 400rpx;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
	}
	.status-text {
		font-size: 24rpx;
		font-weight: 500;
		flex-shrink: 0;
		margin-left: 14rpx;
	}
}
</style>
