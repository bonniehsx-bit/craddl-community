<template>
	<scroll-view scroll-y class="mch-scroll-view">
		<view class="coupons-section">
			
			<view class="coupon-list">
				<tui-no-data v-if="!list.length" :fixed="false">暂无优惠券</tui-no-data>
				
				<view class="coupon-ticket" v-for="coupon in list" :key="coupon.id">
					<view class="ticket-left" :style="{background: themeColorLight}">
						<view class="price" :style="{color: themeColor}">
							<text class="symbol" v-if="coupon.type == 0">¥</text>
							<text class="num">{{ coupon.type == 0 ? coupon.sub_price : coupon.discount }}</text>
							<text class="unit" v-if="coupon.type == 1">折</text>
						</view>
						<view class="cond" :style="{color: themeColor}">满{{ coupon.min_price }}可用</view>
					</view>
					
					<view class="ticket-right">
						<view class="info">
							<view class="name">{{ coupon.name }}</view>
							<view class="time">{{ formatTime(coupon) }}</view>
						</view>
						<button class="btn" 
							:style="{background: coupon.can_claim_more ? themeColor : '#ccc'}"
							:disabled="!coupon.can_claim_more"
							@click="handleClaim(coupon)">
							{{ coupon.can_claim_more ? '领取' : '已领完' }}
						</button>
					</view>
					
					<view class="circle top"></view>
					<view class="circle bottom"></view>
				</view>
				
				<view class="bottom-spacer"></view>
			</view>
			
		</view>
	</scroll-view>
</template>

<script>
	export default {
		name: "mch-coupon",
		props: {
			list: {
				type: Array,
				default: () => []
			}
		},
		methods: {formatTime(coupon) {
				if (coupon.time_type == 0) return `领取后${coupon.day}天内有效`;
				
				// 格式化时间戳
				const formatDate = (ts) => {
					const d = new Date(ts * 1000);
					return `${d.getFullYear()}.${d.getMonth()+1}.${d.getDate()}`;
				};
				return `${formatDate(coupon.start_at)}-${formatDate(coupon.end_at)}`;
			},
			
			handleClaim(coupon) {
				this.$emit('claim', coupon);
			}
		},
		emits: ['claim']
	}
</script>

<style lang="scss" scoped>
	.mch-scroll-view {
		height: 100%;
		background-color: #f7f8fa;
	}
	
	.coupons-section {
		padding: 24rpx;
	}

	.coupon-ticket {
		display: flex;
		background: #fff;
		height: 180rpx;
		border-radius: 16rpx;
		overflow: hidden;
		position: relative;
		margin-bottom: 24rpx;
		box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.03);

		.ticket-left {
			width: 200rpx;
			display: flex;
			flex-direction: column;
			justify-content: center;
			align-items: center;
			position: relative;
			border-right: 2rpx dashed rgba(0,0,0,0.05);

			.price {
				font-weight: bold;
				display: flex;
				align-items: baseline;
				.symbol { font-size: 28rpx; margin-right: 4rpx; }
				.num { font-size: 56rpx; line-height: 1; }
				.unit { font-size: 28rpx; margin-left: 4rpx; }
			}
			.cond {
				font-size: 22rpx;
				margin-top: 8rpx;
				opacity: 0.9;
			}
		}

		.ticket-right {
			flex: 1;
			padding: 24rpx 30rpx;
			display: flex;
			justify-content: space-between;
			align-items: center;

			.info {
				flex: 1;
				margin-right: 20rpx;
				.name { font-size: 30rpx; font-weight: bold; color: #333; margin-bottom: 12rpx; }
				.time { font-size: 22rpx; color: #999; }
			}

			.btn {
				width: 130rpx;
				height: 56rpx;
				line-height: 56rpx;
				border-radius: 28rpx;
				font-size: 24rpx;
				color: #fff;
				text-align: center;
				padding: 0;
				margin: 0;
				
				&[disabled] {
					background: #eee !important;
					color: #999 !important;
				}
			}
		}

		/* 锯齿圆圈装饰 */
		.circle {
			position: absolute;
			left: 190rpx;
			width: 20rpx;
			height: 20rpx;
			background: #f7f8fa;
			border-radius: 50%;
			z-index: 2;
			
			&.top { top: -10rpx; }
			&.bottom { bottom: -10rpx; }
		}
	}

	.bottom-spacer {
		height: calc(env(safe-area-inset-bottom) + 120rpx);
		width: 100%;
	}
</style>