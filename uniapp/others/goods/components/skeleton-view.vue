<template>
	<view class="skeleton-wrapper">
		<view class="skeleton-banner"></view>
		<view class="skeleton-info-card">
			<view class="skeleton-line large"></view>
			<view class="skeleton-line medium"></view>
			<view class="skeleton-price"></view>
		</view>
		<view class="skeleton-spec-card"></view>
		<view class="skeleton-comment-card"></view>
		<view class="skeleton-detail-card"></view>
	</view>
</template>

<script>
	export default {
		name: "skeleton-view"
	}
</script>

<style lang="scss" scoped>
	$uni-spacing-col: 24rpx;

	@keyframes shimmer {
		0% {
			background-position: -100% 0;
		}

		100% {
			background-position: 100% 0;
		}
	}

	.skeleton-wrapper {
		width: 100%;
		overflow: hidden;
		/* 跟页面底色一致(goods.vue 的 tui-page backgroundColor)。
		   原来是 #fff,配上页面在骨架屏期间也临时用 #fff,加载完两边一起切成 #f7f7f7,整页跳一下色。
		   改灰底后卡片间那 20rpx 的缝会露出来 —— 正好跟真实布局(白卡片 + 灰缝)对上 */
		background-color: #f7f7f7;

		.skeleton-banner,
		.skeleton-line,
		.skeleton-price,
		.skeleton-spec-card,
		.skeleton-comment-card,
		.skeleton-detail-card {
			background-image: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
			background-size: 200% 100%;
			animation: shimmer 1.5s infinite linear;
		}

		.skeleton-banner {
			width: 100%;
			height: 750rpx;
		}

		.skeleton-info-card {
			background-color: #fff;
			padding: $uni-spacing-col;

			.skeleton-price {
				width: 40%;
				height: 48rpx;
				border-radius: 8rpx;
				margin-top: 24rpx;
			}

			.skeleton-line {
				height: 32rpx;
				border-radius: 8rpx;
			}

			.skeleton-line.large {
				width: 90%;
			}

			.skeleton-line.medium {
				width: 60%;
				margin-top: 20rpx;
			}
		}

		.skeleton-spec-card,
		.skeleton-comment-card,
		.skeleton-detail-card {
			height: 100rpx;
			background-color: #fff;
			margin-top: 20rpx;
		}

		.skeleton-comment-card {
			height: 250rpx;
		}

		.skeleton-detail-card {
			height: 400rpx;
		}
	}
</style>