<template>
	<view class="tui-pro-item" @click="$T.go('/plugins/integral/goods?id='+goods.id)">
		<image :src="goods.cover_pic" class="tui-pro-img" mode="aspectFill" />
		<view class="tui-pro-content">
			<view class="tui-pro-tit" style="height: 68rpx;">{{ goods.name }}</view>
			<view class="flex-x-between flex-y-center">
				<view class="tui-pro-price">
					<view class="tui-sale-price">{{ goods.integral }}{{ $noun('integral') }}</view>
				</view>
				<tui-button plain="true" type="danger" height="55rpx" width="120rpx" size="25">立即兑换</tui-button>
			</view>
		</view>
	</view>
</template>

<script>
	export default {
		name: "TuiGoodsItem",
		data() {
			return {

			}
		},
		props: {
			goods: {
				type: Object,
				default: null
			}
		},
		methods: {

		}
	}
</script>

<style>
	.tui-pro-item {
		width: 100%;
		margin-bottom: 10rpx;
		background: #fff;
		box-sizing: border-box;
		border-radius: 12rpx;
		overflow: hidden;
		transition: all 0.15s ease-in-out;
	}

	.tui-pro-img {
		width: 100%;
		display: block;
		height: 375rpx;
	}

	.tui-pro-content {
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		box-sizing: border-box;
		padding: 20rpx;
	}

	.tui-pro-tit {
		font-weight: bold;
		color: #2e2e2e;
		font-size: 26rpx;
		word-break: break-all;
		overflow: hidden;
		text-overflow: ellipsis;
		display: -webkit-box;
		height: 68rpx;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 2;
	}

	.tui-pro-price {
		 
	}

	.tui-sale-price {
		font-size: 11pt;
		font-weight: 500;
		font-weight: bold;
		color: #D71F1F !important;
	}

	.tui-factory-price {
		font-size: 24rpx;
		color: #a0a0a0;
		text-decoration: line-through;
		padding-left: 2rpx;

	}

	.tui-pro-pay {
		padding-top: 10rpx;
		font-size: 24rpx;
		color: #656565;
	}
</style>
