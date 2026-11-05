<template>
	<view class="list-summary">
		<!--
			deliver_type_text 是核心按 deliver_type 出的文案。点单订单借用了 pickup/city
			两个枚举,于是堂食单会显示「到店核销」—— 顾客就坐在店里,这句是错的。
			而它的就餐方式已经由商品行的分类标签 +「待取餐 · A012」那行状态文案表达了,
			这里再来一个错的标签只是噪音,所以不显示。
			(彻底的做法是给核心加一个列表级文案钩子,但为一个小标签动核心不划算。)
		-->
		<view class="delivery-tag"
			v-if="order.deliver_type_text && order.plugin !== 'dining'"
			:style="{ color: themeColor, background: hexToRgba(themeColor, 0.08) }">
			{{ order.deliver_type_text }}
		</view>
		<view class="right">
			<text class="total-num">共{{ totalNum }}件商品</text>
			<text class="label">{{ paymentLabel }}</text>
			<view class="price" :style="{ color: themeColor }">
				<text class="sym">¥</text>
				<text class="int">{{ formatPricePart(order.pay_price, 'int') }}</text>
				<text class="dec">.{{ formatPricePart(order.pay_price, 'decimal') }}</text>
			</view>
		</view>
	</view>
</template>

<script>
import { formatPricePart } from './utils.js';

export default {
	name: 'ListSummary',
	props: {
		order: { type: Object, required: true }
		// themeColor / hexToRgba 由全局 mixin 注入
	},
	computed: {
		paymentLabel() {
			// 退过款的订单也确实付过钱，仍保留实付款口径。
			if ([1, 2, 3].includes(Number(this.order.pay_status))) return '实付款';
			return Number(this.order.status) === 0 ? '订单金额' : '应付款';
		},
		totalNum() {
			const items = this.order.items || [];
			if (!Array.isArray(items)) return 0;
			return items.reduce((sum, it) => sum + (parseInt(it.num) || 0), 0);
		}
	},
	methods: { formatPricePart }
};
</script>

<style scoped lang="scss">
.list-summary {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 18rpx 24rpx;
	border-top: 1rpx solid #f5f5f5;

	.delivery-tag {
		font-size: 22rpx;
		padding: 3rpx 14rpx;
		border-radius: 20rpx;
	}
	.right {
		display: flex;
		align-items: baseline;

		.total-num {
			font-size: 22rpx;
			color: #999;
			margin-right: 14rpx;
		}
		.label {
			font-size: 22rpx;
			color: #666;
			margin-right: 4rpx;
		}
		.price {
			font-family: 'DIN Alternate', sans-serif;

			.sym { font-size: 22rpx; }
			.int { font-size: 32rpx; font-weight: 700; }
			.dec { font-size: 22rpx; }
		}
	}
}
</style>
