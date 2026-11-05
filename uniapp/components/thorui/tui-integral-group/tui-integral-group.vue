<template>
	<view class="diy-integral-root" :style="outerStyle" v-if="list.length > 0">
		<view class="group-inner" :style="innerStyle">

			<view class="group-header" v-if="safeData.show_title != 0">
				<text class="title">{{ safeData.title || ($noun('integral')+'商城专区') }}</text>
				<view class="more" @click="goMore">更多 <tui-icon name="arrowright" :size="24" unit="rpx"></tui-icon>
				</view>
			</view>


			<!-- 0. 网格模式 -->
			<view class="grid-wrapper" :class="'columns-' + safeData.columns" v-if="safeData.type == 0">
				<view class="goods-item" v-for="(goods, index) in list" :key="index" @click="pageTo(goods)">
					<view class="goods-card">
						<image :src="goods.cover_pic" mode="aspectFill" class="cover-pic"></image>
						<view class="info">
							<view class="goods-title">{{ goods.name }}</view>
							<view class="goods-price" :style="{ color: safeData.price_color }"
								v-if="safeData.show_price == 1">
								{{ goods.integral }}{{ $noun('integral') }}<block v-if="goods.price > 0"> + ￥{{ goods.price }}</block>
							</view>
						</view>
					</view>
				</view>
			</view>

			<!-- 1. 列表模式 -->
			<view class="list-wrapper" v-if="safeData.type == 1">
				<view class="list-item" v-for="(goods, index) in list" :key="index" @click="pageTo(goods)">

					<image :src="goods.cover_pic" mode="aspectFill" class="cover-pic"></image>
					<view class="info">
						<view class="goods-title">{{ goods.name }}</view>
						<view class="bottom">
							<view class="goods-price" :style="{ color: safeData.price_color }"
								v-if="safeData.show_price == 1">
								{{ goods.integral }}{{ $noun('integral') }}<block v-if="goods.price > 0"> + ￥{{ goods.price }}</block>
							</view>
							<view class="buy-btn" :style="{ backgroundColor: safeData.price_color }">兑换</view>
						</view>
					</view>
				</view>
			</view>

		</view>
	</view>
</template>

<script>
	export default {
		name: 'TuiIntegralGroup',
		props: {
			value: {
				type: Object,
				default: () => ({})
			}
		},
		data() {
			return {
				list: [],
				loading: true
			}
		},
		computed: {
			safeData() {
				return this.value || {};
			},
			outerStyle() {
				const padding = this.safeData.is_padding == 1 ? '0 20rpx' : '0';
				return `padding:${padding};margin-bottom:20rpx`;
			},
			innerStyle() {
				const radius = this.safeData.is_circle == 1 ? '20rpx' : '0';
				const bg = this.safeData.background || '#ffffff';
				return `border-radius:${radius};background-color:${bg};padding:20rpx;overflow:hidden`;
			}
		},
		created() {
			this.fetchData();
		},
		methods: {
			fetchData() {
				const limit = parseInt(this.safeData.limit) || 4;
				this.$request({
					url: '/plugin/integral/api/goods/list',
					data: {
						page: 1,
						limit: limit
					}
				}).then(res => {
					this.loading = false;
					if (res.code == 0) {
						// 兼容返回格式可能是 res.data.list.data 或者是 res.data.list
						this.list = res.data.list.data ? res.data.list.data : res.data.list;
					}
				}).catch(() => {
					this.loading = false;
				});
			},
			pageTo(goods) {
				uni.navigateTo({
					url: `/plugins/integral/goods?id=${goods.id}`
				});
			},
			goMore() {
				uni.navigateTo({
					url: '/plugins/integral/index'
				});
			}
		}
	}
</script>

<style lang="scss" scoped>
	.diy-integral-root {
		box-sizing: border-box;
		width: 100%;
	}

	.group-inner {
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.02);
	}

	.group-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 20rpx;
		padding-bottom: 10rpx;
		border-bottom: 1rpx solid #f5f5f5;

		.title {
			font-size: 30rpx;
			font-weight: bold;
			color: #333;
		}

		.more {
			font-size: 24rpx;
			color: #999;
			display: flex;
			align-items: center;
		}
	}

	/* 网格布局 */
	.grid-wrapper {
		display: flex;
		flex-wrap: wrap;
		margin: 0 -10rpx;

		.goods-item {
			box-sizing: border-box;
			padding: 0 10rpx;
			margin-bottom: 20rpx;
		}

		&.columns-2 .goods-item {
			width: 50%;
		}

		&.columns-3 .goods-item {
			width: 33.33%;
		}

		.goods-card {
			background: #fff;
			border-radius: 12rpx;
			overflow: hidden;
			box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.02);
			height: 100%;
			display: flex;
			flex-direction: column;

			.cover-pic {
				width: 100%;
				height: 340rpx;
				background-color: #f7f7f7;
				display: block;
				border-radius: 12rpx 12rpx 0 0;
			}

			.info {
				padding: 16rpx;
				flex: 1;
				display: flex;
				flex-direction: column;
				justify-content: space-between;

				.goods-title {
					font-size: 26rpx;
					color: #333;
					line-height: 1.4;
					display: -webkit-box;
					-webkit-box-orient: vertical;
					-webkit-line-clamp: 2;
					line-clamp: 2;
					overflow: hidden;
					margin-bottom: 10rpx;
				}

				.goods-price {
					font-size: 28rpx;
					font-weight: bold;
					color: #ff4544; // 默认，会被行内样式覆盖
				}
			}
		}
	}

	/* 列表布局 */
	.list-wrapper {
		.list-item {
			display: flex;
			background: #fff;
			border-radius: 12rpx;
			padding: 16rpx;
			margin-bottom: 16rpx;
			box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.02);

			&:last-child {
				margin-bottom: 0;
			}

			.cover-pic {
				width: 180rpx;
				height: 180rpx;
				border-radius: 8rpx;
				flex-shrink: 0;
				margin-right: 20rpx;
				background-color: #f7f7f7;
			}

			.info {
				flex: 1;
				display: flex;
				flex-direction: column;
				justify-content: space-between;

				.goods-title {
					font-size: 28rpx;
					color: #333;
					line-height: 1.4;
					display: -webkit-box;
					-webkit-box-orient: vertical;
					-webkit-line-clamp: 2;
					line-clamp: 2;
					overflow: hidden;
				}

				.bottom {
					display: flex;
					justify-content: space-between;
					align-items: flex-end;

					.goods-price {
						font-size: 30rpx;
						font-weight: bold;
					}

					.buy-btn {
						font-size: 24rpx;
						color: #fff;
						padding: 6rpx 20rpx;
						border-radius: 30rpx;
					}
				}
			}
		}
	}
</style>