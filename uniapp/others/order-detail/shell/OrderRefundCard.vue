<template>
	<!-- 退款进度:这个订单的每一笔退款到哪一步了。文案全由后端给(RefundProgressService),前端不自己拼 -->
	<view class="refund-card" v-if="items.length">
		<view class="card-title">退款进度</view>
		<!-- 一笔两行:状态 + 名目 + 金额;说明 · 时间 -->
		<view class="refund-item" v-for="item in items" :key="item.id">
			<view class="item-head">
				<text class="state-tag" :class="'state-' + item.state">{{ item.state_text }}</text>
				<text class="item-title">{{ item.title }}</text>
				<text class="item-amount">¥{{ item.amount }}</text>
			</view>
			<view class="item-sub">{{ item.desc }}<text v-if="item.time"> · {{ item.time_label }} {{ item.time }}</text></view>
		</view>
	</view>
</template>

<script>
export default {
	name: 'OrderRefundCard',
	props: {
		order: { type: Object, required: true }
	},
	computed: {
		items() {
			return Array.isArray(this.order.refund_progress) ? this.order.refund_progress : [];
		}
	}
};
</script>

<style scoped lang="scss">
.refund-card {
	background: #fff;
	border-radius: 20rpx;
	margin: 20rpx 24rpx 0;
	padding: 24rpx 28rpx;
}
.card-title {
	font-size: 28rpx;
	font-weight: 600;
	color: #333;
	margin-bottom: 4rpx;
}
.refund-item {
	padding: 16rpx 0;

	& + .refund-item {
		border-top: 1rpx solid #f5f5f5;
	}
}
.item-head {
	display: flex;
	align-items: center;
	font-size: 26rpx;
	color: #333;

	.item-title {
		flex: 1;
	}
	.item-amount {
		font-weight: 600;
	}
}
.item-sub {
	margin-top: 8rpx;
	font-size: 24rpx;
	color: #999;
	line-height: 1.5;
}
.state-tag {
	flex-shrink: 0;
	font-size: 22rpx;
	line-height: 40rpx;
	padding: 0 16rpx;
	border-radius: 20rpx;
	margin-right: 12rpx;
	font-weight: 500;
}
/* 退款状态:语义色,不跟商城主题色 */
.state-processing { color: #1677FF; background: #EAF2FF; }
.state-delayed { color: #D48806; background: #FFF7E6; }
.state-done { color: #4A7C59; background: #EDF5EF; }
.state-offline { color: #666; background: #F2F2F2; }
</style>
