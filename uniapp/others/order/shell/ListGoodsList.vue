<template>
	<view class="list-goods-list">
		<view class="goods-item" v-for="(item, i) in items" :key="i">
			<image :src="item.goods && item.goods.cover_pic" class="goods-img" mode="aspectFill"></image>
			<view class="goods-content">
				<view class="row">
					<view class="goods-name">
						<!-- 分类小标签（hotel/voucher/group/lucky/integral/yuda/emotion）-->
						<text v-if="categoryTag" class="cat-tag" :style="{ background: categoryTag.color }">
							{{ categoryTag.label }}
						</text>
						<text v-if="item.is_presell" class="tag-presell">预售</text>
						{{ item.goods && item.goods.name }}
					</view>
					<!-- 价格：积分兑换 → X 积分 (+¥Y)；否则 ¥Y -->
					<view v-if="Number(item.use_integral) > 0" class="goods-price integral">
						<text class="int">{{ parseInt(item.use_integral) }}</text>
						<text class="unit">{{ $noun('integral') }}</text>
						<text v-if="Number(item.pay_price) > 0" class="extra">+¥{{ formatPricePart(item.pay_price, 'int') }}</text>
					</view>
					<view v-else class="goods-price">
						<text class="sym">¥</text>
						<text class="int">{{ formatPricePart(item.price, 'int') }}</text>
						<text class="dec">.{{ formatPricePart(item.price, 'decimal') }}</text>
					</view>
				</view>
				<view class="row">
					<text class="spec">{{ getSpecString(item.attr_list || item.attr) }}</text>
					<text class="num">x{{ item.num }}</text>
				</view>
			</view>
		</view>
	</view>
</template>

<script>
import { formatPricePart, getSpecString } from './utils.js';

export default {
	name: 'ListGoodsList',
	props: {
		items: { type: Array, default: () => [] },
		/** 分类小标签 { label, color } */
		categoryTag: { type: Object, default: () => null }
	},
	methods: { formatPricePart, getSpecString }
};
</script>

<style scoped lang="scss">
.list-goods-list {
	padding: 0 24rpx;
}
.goods-item {
	display: flex;
	padding: 16rpx 0;

	& + .goods-item {
		border-top: 1rpx solid #f5f5f5;
	}

	.goods-img {
		width: 140rpx;
		height: 140rpx;
		border-radius: 12rpx;
		background: #f5f5f5;
		flex-shrink: 0;
	}
	.goods-content {
		flex: 1;
		margin-left: 20rpx;
		display: flex;
		flex-direction: column;
		justify-content: space-between;
	}
	.row {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;

		&:last-child {
			align-items: center;
			margin-top: 12rpx;
		}
	}
	.goods-name {
		flex: 1;
		font-size: 28rpx;
		color: #333;
		line-height: 1.4;
		margin-right: 16rpx;
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 2;
		line-clamp: 2;
		overflow: hidden;
	}
	.goods-price {
		color: #333;
		font-family: 'DIN Alternate', sans-serif;
		flex-shrink: 0;

		.sym { font-size: 22rpx; }
		.int { font-size: 30rpx; font-weight: 600; }
		.dec { font-size: 22rpx; }

		&.integral {
			color: #FF8917;
			display: flex;
			align-items: baseline;
			gap: 4rpx;

			.int { font-size: 30rpx; font-weight: 700; }
			.unit { font-size: 22rpx; }
			.extra { font-size: 22rpx; color: #999; margin-left: 4rpx; }
		}
	}
	.spec {
		font-size: 22rpx;
		color: #999;
		max-width: 400rpx;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.num {
		font-size: 22rpx;
		color: #999;
		margin-left: 14rpx;
	}
	.cat-tag {
		display: inline-block;
		font-size: 18rpx;
		color: #fff;
		padding: 2rpx 10rpx;
		border-radius: 8rpx;
		margin-right: 6rpx;
		vertical-align: middle;
		font-weight: 500;
		line-height: 1.4;
	}
	.tag-presell {
		display: inline-block;
		font-size: 20rpx;
		color: #FF8917;
		background: #FFF6E8;
		padding: 2rpx 10rpx;
		border-radius: 8rpx;
		margin-right: 6rpx;
		vertical-align: middle;
	}
}
</style>
