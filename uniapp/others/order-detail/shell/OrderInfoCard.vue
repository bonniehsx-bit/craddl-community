<template>
	<view class="info-card">
		<view class="info-item">
			<text class="label">订单编号</text>
			<view class="val copy-box">
				<text>{{ order.order_no }}</text>
				<text class="copy-txt" @tap="copyText(order.order_no)">复制</text>
			</view>
		</view>

		<!-- 付过款就显示:部分退款(2)、全额退款(3)后顾客也确实付过钱 -->
		<view class="info-item" v-if="[1, 2, 3].includes(Number(order.pay_status))">
			<text class="label">支付方式</text>
			<text class="val">{{ order.payment_name || getPayTypeText(order.pay_type) }}</text>
		</view>

		<view class="info-item">
			<text class="label">下单时间</text>
			<text class="val">{{ formatTime(order.created_at) }}</text>
		</view>

		<view class="info-item" v-if="hasTime(order.pay_at)">
			<text class="label">支付时间</text>
			<text class="val">{{ formatTime(order.pay_at) }}</text>
		</view>

		<view class="info-item" v-if="hasTime(order.send_at)">
			<text class="label">发货时间</text>
			<text class="val">{{ formatTime(order.send_at) }}</text>
		</view>

		<view class="info-item" v-if="hasTime(order.confirm_at)">
			<text class="label">成交时间</text>
			<text class="val">{{ formatTime(order.confirm_at) }}</text>
		</view>

		<view class="divider" v-if="order.remarks || order.seller_remarks"></view>

		<view class="info-item align-top" v-if="order.remarks">
			<text class="label">订单备注</text>
			<view class="val remark-content">{{ order.remarks }}</view>
		</view>

		<view class="info-item align-top" v-if="order.seller_remarks">
			<text class="label">商家留言</text>
			<view class="val remark-content">{{ order.seller_remarks }}</view>
		</view>
	</view>
</template>

<script>
import { formatTime, copyText } from './utils.js';

export default {
	name: 'OrderInfoCard',
	props: {
		order: { type: Object, required: true }
	},
	methods: {
		formatTime, copyText,
		/**
		 * 后端可能返回 0 / '' / 数字时间戳 / 格式化字符串，统一判定是否"有时间"
		 */
		hasTime(v) {
			if (v == null || v === '') return false;
			if (typeof v === 'number') return v > 0;
			// 字符串：非空且不是 "0" / "0000-00-00 00:00:00"
			const s = String(v).trim();
			if (!s || s === '0' || s.startsWith('0000-00-00')) return false;
			return true;
		},
		getPayTypeText(t) {
			const map = { 1: '微信支付', 2: '支付宝', 3: this.$noun('balance') + '支付', 4: this.$noun('integral') + '支付' };
			return map[Number(t)] || '在线支付';
		}
	}
};
</script>

<style scoped lang="scss">
.info-card {
	background: #fff;
	border-radius: 20rpx;
	margin: 20rpx 24rpx 0;
	padding: 24rpx 28rpx;
}
.info-item {
	display: flex;
	font-size: 26rpx;
	padding: 12rpx 0;
	line-height: 1.5;

	&.align-top { align-items: flex-start; }

	.label { width: 160rpx; color: #999; flex-shrink: 0; }
	.val { flex: 1; color: #333; word-break: break-all; }
	.copy-box {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}
	.copy-txt {
		color: #409EFF;
		font-size: 24rpx;
		padding: 2rpx 12rpx;
		border: 1rpx solid #409EFF;
		border-radius: 20rpx;
	}
	.remark-content { line-height: 1.6; }
}
.divider {
	height: 1rpx;
	background: #f5f5f5;
	margin: 8rpx 0;
}
</style>
