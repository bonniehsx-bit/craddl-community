<template>
	<view style="padding: 0 10rpx;">
		<view class="rolling-search" :style="{backgroundColor:value.data.bg_color,color:value.data.text_color}">
			<image :src="value.data.icon" style="width: 20px;height: 20px;margin-right: 10rpx;" mode="aspectFill">
			</image>
			<swiper vertical autoplay circular :interval="3000" class="tui-swiper">
				<swiper-item v-for="(item,index) in list" :key="index" class="tui-swiper-item">
					<view class="news-item" :style="{color:value.data.text_color}">
						<view class="flex-y-center" style="font-weight: bold;">
							<view>
								<image :src="item.avatar_url" mode="aspectFill"
									style="width: 45rpx;height: 45rpx;border-radius: 50%;"></image>
							</view>
							<view class="tui-color-success" style="padding: 0 10rpx;color: #F2CB8E;">
								{{item.nickname}}
							</view>
							<view>刚刚购买了</view>
							<view class="color-main-danger" style="color: #F2CB8E;">
								{{item.goods_name}}
							</view>
						</view>
					</view>
				</swiper-item>
			</swiper>
		</view>
	</view>
</template>

<script>
	export default {
		props: {
			value: {
				type: Object,
				default: () => {
					return null
				}
			}
		},
		data() {
			return {
				animation: true,
				list: [],
			}
		},
		created: function(options) {
			this.getOrderNotice();
		},
		methods: {
			getOrderNotice(e) {
				this.$request({
					url: "/api/mall/order-notice"
				}).then(res => {
					if (res.code == 0) {
						this.list = res.data.list;
					}
				})
			}
		}
	}
</script>

<style>
	.container {
		padding-top: 120rpx;
	}

	.tui-notice-board {
		width: 100%;
		padding-right: 30rpx;
		box-sizing: border-box;
		font-size: 28rpx;
		height: 60rpx;
		display: flex;
		align-items: center;
		z-index: 999;
	}

	.tui-icon-bg {


		position: relative;
		z-index: 10;
	}

	.tui-icon-class {
		margin-right: 12rpx;
	}

	.tui-scorll-view {
		flex: 1;
		line-height: 1;
		white-space: nowrap;
		overflow: hidden;
		color: #f54f46;
	}

	.tui-notice {
		-webkit-backface-visibility: hidden;
		-webkit-perspective: 1000;
		transform: translate3d(100%, 0, 0);
	}

	.tui-animation {
		-webkit-animation: tui-rolling 12s linear infinite;
		animation: tui-rolling 12s linear infinite;
	}

	@-webkit-keyframes tui-rolling {
		0% {
			transform: translate3d(100%, 0, 0);
		}

		100% {
			transform: translate3d(-170%, 0, 0);
		}
	}

	@keyframes tui-rolling {
		0% {
			transform: translate3d(100%, 0, 0);
		}

		100% {
			transform: translate3d(-170%, 0, 0);
		}
	}

	.tui-subject {
		padding: 100rpx 30rpx 30rpx 30rpx;
		box-sizing: border-box;
		font-size: 32rpx;
		font-weight: bold;
	}

	.tui-rolling-news {
		width: 100%;
		padding: 12rpx 30rpx;
		box-sizing: border-box;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-wrap: nowrap;
	}

	.tui-swiper {
		font-size: 28rpx;
		height: 50rpx;
		flex: 1;
	}

	.tui-swiper-item {
		display: flex;
		align-items: center
	}

	.news-item {
		 
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.tui-searchbox {
		padding: 60rpx 80rpx;
		box-sizing: border-box;
	}

	.rolling-search {
		width: 100%;
		height: 80rpx;
		padding: 0 20rpx;
		border-radius: 10rpx;
		box-sizing: border-box;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-wrap: nowrap;
		color: #999;
	}
</style>