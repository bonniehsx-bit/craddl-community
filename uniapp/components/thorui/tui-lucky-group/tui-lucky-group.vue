<template>
	<view class="diy-lucky-root" :style="outerStyle" v-if="list.length > 0">
		<view class="group-inner" :style="innerStyle">

			<view class="group-header" v-if="safeData.show_title != 0">
				<text class="title">{{ safeData.title || '手气拼团专区' }}</text>
				<view class="more" @click="goMore">更多 <tui-icon name="arrowright" :size="24" unit="rpx"></tui-icon>
				</view>
			</view>


			<!-- 0. 网格模式 -->
			<view class="grid-wrapper" :class="'columns-' + safeData.columns" v-if="safeData.type == 0">
				<view class="goods-item" v-for="(goods, index) in list" :key="index" @click="pageTo(goods)">
					<view class="goods-card">
						<view class="cover-wrap">
							<image :src="goods.cover_pic" mode="aspectFill" class="cover-pic"></image>
							<view class="lucky-tag">{{ goods.min_people }}人团</view>
						</view>
						<view class="info">
							<view class="goods-title">{{ goods.name }}</view>
							<view class="goods-price" :style="{ color: safeData.price_color }"
								v-if="safeData.show_price == 1">
								<text class="symbol">￥</text>{{ goods.price }}
							</view>
						</view>
					</view>
				</view>
			</view>

			<!-- 1. 列表模式 -->
			<view class="list-wrapper" v-if="safeData.type == 1">
				<view class="list-item" v-for="(goods, index) in list" :key="index" @click="pageTo(goods)">

					<view class="cover-wrap1">
						<image :src="goods.cover_pic" mode="aspectFill" class="cover-pic1"></image>
						<view class="lucky-tag">{{ goods.min_people }}人团</view>
					</view>
					<view class="info">
						<view class="goods-title">{{ goods.name }}</view>
						<view class="bottom">
							<view class="goods-price" :style="{ color: safeData.price_color }"
								v-if="safeData.show_price == 1">
								<text class="symbol">￥</text>{{ goods.price }}
							</view>
							<view class="buy-btn" :style="{ backgroundColor: safeData.price_color }">拼团</view>
						</view>
					</view>
				</view>
			</view>

		</view>
	</view>
</template>

<script>
	export default {
		name: 'TuiLuckyGroup',
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
					url: '/plugin/lucky/api/goods/index',
					data: {
						page: 1,
						limit: limit
					}
				}).then(res => {
					this.loading = false;
					if (res.code == 0) {
						// lucky API returns directly res.data.list as an array
						this.list = res.data.list ? res.data.list.slice(0, limit) : [];
					}
				}).catch(() => {
					this.loading = false;
				});
			},
			pageTo(goods) {
				// lucky uses the internal Plugin ID for its detail page
				uni.navigateTo({
					url: `/plugins3/lucky/goods?id=${goods.id}`
				});
			},
			goMore() {
				uni.navigateTo({
					url: '/plugins3/lucky/index'
				});
			}
		}
	}
</script>

<style lang="scss" scoped>
	.diy-lucky-root {
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

	.lucky-tag {
		position: absolute;
		top: 10rpx;
		left: 10rpx;
		background: rgba(0, 0, 0, 0.5);
		color: #fff;
		font-size: 22rpx;
		padding: 4rpx 10rpx;
		border-radius: 6rpx;
		z-index: 10;
		backdrop-filter: blur(2px);
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

			.cover-wrap {
				position: relative;
				width: 100%;
				height: 340rpx;
				background-color: #f7f7f7;
				border-radius: 12rpx 12rpx 0 0;
			}
			.cover-pic {
				width: 100%;
				height: 100%;
				display: block;
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
					font-size: 32rpx;
					font-weight: bold;
					color: #ff4544; // 会被覆盖
					
					.symbol {
						font-size: 22rpx;
						margin-right: -4rpx;
					}
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

			.cover-wrap1 {
				position: relative;
				width: 180rpx;
				height: 180rpx;
				border-radius: 8rpx;
				flex-shrink: 0;
				margin-right: 20rpx;
				background-color: #f7f7f7;
			}
			.cover-pic1 {
				width: 100%;
				height: 100%;
				display: block;
				border-radius: 8rpx;
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
						font-size: 36rpx;
						font-weight: bold;
						
						.symbol {
							font-size: 24rpx;
							margin-right: -2rpx;
						}
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
