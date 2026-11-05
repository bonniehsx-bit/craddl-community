<template>
	<view class="banner-container" v-if="goodsInfo">
		<swiper class="tui-banner-swiper" :autoplay="true" :interval="5000" :duration="150" :circular="true"
			:style="{ height: scrollH + 'px' }" @change="bannerChange">
			<swiper-item v-for="(item, index) in goodsInfo.pic_list" :key="index" :data-index="index"
				@tap.stop="previewImage">
				<image :src="item" mode="aspectFill" class="tui-slide-image" :style="{ height: scrollH + 'px' }" />
			</swiper-item>
		</swiper>
		<view class="banner-dots" v-if="goodsInfo.pic_list.length > 1">
			<view class="dot" :class="{ on: bannerIndex === index }" v-for="(item, index) in goodsInfo.pic_list"
				:key="index"></view>
		</view>

		<view class="banner-actions">
			<view class="action-icon-box" @tap="back">
				<tui-icon name="arrowleft" :size="28" color="#fff"></tui-icon>
			</view>
			<button class="action-icon-box" open-type="share">
				<tui-icon name="share" :size="20" color="#fff"></tui-icon>
			</button>
			<view class="action-icon-box" @tap="collecting">
				<tui-icon :name="collected ? 'like-fill' : 'like'" :color="collected ? themeColor : '#fff'"
					:size="20"></tui-icon>
			</view>
		</view>
	</view>
</template>

<script>
	export default {
		name: "goods-banner",
		props: {
			goodsInfo: {
				type: Object,
				default: null
			},
			scrollH: {
				type: Number,
				default: 0
			},
			collected: {
				type: Boolean,
				default: false
			}
		},
		data() {
			return {
				bannerIndex: 0
			}
		},
		methods: {
			bannerChange(e) {
				this.bannerIndex = e.detail.current;
			},
			previewImage(e) {
				let index = e.currentTarget.dataset.index;
				uni.previewImage({
					current: this.goodsInfo.pic_list[index],
					urls: this.goodsInfo.pic_list
				});
			},
			back() {
				// 调用父组件方法或直接返回
				uni.navigateBack();
			},
			collecting() {
				this.$emit('collecting');
			}
		}
	}
</script>

<style lang="scss" scoped>
	.banner-container {
		width: 100%;
		position: relative;

		.tui-banner-swiper,
		.tui-slide-image {
			width: 100%;
			display: block;
		}

		.banner-dots {
			position: absolute;
			left: 0;
			right: 0;
			bottom: 24rpx;
			display: flex;
			justify-content: center;

			.dot {
				width: 12rpx;
				height: 12rpx;
				border-radius: 6rpx;
				background-color: rgba(255, 255, 255, 0.55);
				margin: 0 5rpx;
				transition: width 0.2s;

				&.on {
					width: 28rpx;
					background-color: #fff;
				}
			}
		}

		/* 钉在屏幕上:滑到详情、评价都还能直接返回/分享/收藏。
		   刻意不监听滚动去切换样式 —— 之前那版吸顶锚点因为逐帧改状态,出过滑到底回弹的问题,
		   这里保持半透明深色底,主图上和白色内容上都看得清。 */
		.banner-actions {
			position: fixed;
			z-index: 20;
			top: calc(var(--status-bar-height, 0rpx) + 30rpx);
			left: 24rpx;
			display: flex;
			gap: 20rpx;

			.action-icon-box {
				display: flex;
				width: 72rpx;
				height: 72rpx;
				padding: 0;
				justify-content: center;
				align-items: center;
				flex-shrink: 0;
				/* 深一档:钉住之后会浮在白色的详情区上,0.3 的底在白底上偏灰,白图标看不清 */
				background: rgba(0, 0, 0, 0.45);
				border-radius: 9999px;
			}
		}

		.banner-actions button.action-icon-box {
			margin: 0;
			padding: 0;
			line-height: 1;
			border: none;

			&::after {
				border: none;
			}
		}
	}
</style>