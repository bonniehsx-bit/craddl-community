<template>
	<view class="price-card">
		<view class="row">
			<text class="label">商品总额</text>
			<text class="val">¥{{ fmtPrice(order.goods_total_price) }}</text>
		</view>
		<view class="row" v-if="Number(order.express_price) > 0">
			<text class="label">运费</text>
			<text class="val">¥{{ fmtPrice(order.express_price) }}</text>
		</view>
		<view class="row" v-if="Number(order.member_discount_price) > 0">
			<text class="label">会员优惠</text>
			<text class="val red">-¥{{ fmtPrice(order.member_discount_price) }}</text>
		</view>
		<view class="row" v-if="Number(order.coupon_cut_price) > 0">
			<text class="label">{{ $noun('coupon') }}</text>
			<text class="val red">-¥{{ fmtPrice(order.coupon_cut_price) }}</text>
		</view>
		<view class="row" v-if="Number(order.first_order_discount_price) > 0">
			<text class="label">首单立减</text>
			<text class="val red">-¥{{ fmtPrice(order.first_order_discount_price) }}</text>
		</view>
		<view class="row" v-if="Number(order.integral_cut_price) > 0">
			<text class="label">{{ $noun('integral') }}抵扣</text>
			<text class="val red">-¥{{ fmtPrice(order.integral_cut_price) }}</text>
		</view>
		<view class="row" v-if="Number(order.pickup_discount_price) > 0">
			<text class="label">自提优惠</text>
			<text class="val red">-¥{{ fmtPrice(order.pickup_discount_price) }}</text>
		</view>
		<view class="row" v-if="Number(order.platform_subsidy_random_minus) > 0">
			<text class="label">支付随机减</text>
			<text class="val red">-¥{{ fmtPrice(order.platform_subsidy_random_minus) }}</text>
		</view>
		<view class="row" v-if="Number(order.use_integral) > 0">
			<text class="label">使用{{ $noun('integral') }}</text>
			<text class="val integral">{{ parseInt(order.use_integral) }} 分</text>
		</view>

		<view class="divider"></view>

		<view class="total-row">
			<text class="total-label">{{ paymentLabel }}</text>
			<!-- 已关闭的单不再用主题色强调金额(主题色留给还在进行的订单) -->
			<view class="total-price" :style="'color:' + (Number(order.status) === 0 ? '#1C1F24' : themeColor)">
				<text class="sym">¥</text>
				<text class="num">{{ fmtPrice(order.pay_price) }}</text>
			</view>
		</view>
		<!-- 退过款单列一行:实付款还是原数,不写出来看不出退了多少 -->
		<view class="refund-row" v-if="Number(order.refund_price) > 0">
			<text>{{ Number(order.refund_price) >= Number(order.pay_price) ? '已全额退款' : '已退款' }}</text>
			<text>-¥{{ fmtPrice(order.refund_price) }}</text>
		</view>
	</view>
</template>

<script>
import { fmtPrice } from './utils.js';

export default {
	name: 'OrderPriceCard',
	props: {
		order: { type: Object, required: true },
		// themeColor 由全局 mixin 注入
	},
	computed: {
		paymentLabel() {
			// 退过款的订单也确实付过钱，仍保留实付款口径。
			if ([1, 2, 3].includes(Number(this.order.pay_status))) return '实付款';
			return Number(this.order.status) === 0 ? '订单金额' : '应付款';
		}
	},
	methods: { fmtPrice }
};
</script>

<style scoped lang="scss">
.price-card {
	background: #fff;
	border-radius: 20rpx;
	margin: 20rpx 24rpx 0;
	padding: 24rpx 28rpx;
}
.row {
	display: flex;
	justify-content: space-between;
	padding: 8rpx 0;
	font-size: 26rpx;
	line-height: 1.5;

	.label { color: #666; }
	.val { color: #333; }
	.val.red { color: #FF3B30; }
	.val.integral { color: #FF8917; font-family: 'DIN Alternate', sans-serif; font-weight: 600; }
}
.divider {
	height: 1rpx;
	background: #f5f5f5;
	margin: 12rpx 0;
}
.refund-row {
	display: flex;
	justify-content: space-between;
	padding-top: 12rpx;
	font-size: 26rpx;
	color: #177245;
}
.total-row {
	display: flex;
	justify-content: space-between;
	align-items: baseline;
	padding-top: 8rpx;

	.total-label {
		font-size: 28rpx;
		color: #1A1A1A;
	}
	.total-price {
		.sym {
			font-size: 24rpx;
			font-weight: 500;
		}
		.num {
			font-size: 40rpx;
			font-weight: 700;
			font-family: 'DIN Alternate', sans-serif;
			margin-left: 2rpx;
		}
	}
}
</style>
