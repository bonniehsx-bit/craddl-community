<template>
	<view class="mch-skeleton-wrap">
		<!-- 头部骨架区 -->
		<view class="skeleton-header skeleton-blink"></view>
		<view class="skeleton-header-info">
			<view class="skeleton-avatar skeleton-blink"></view>
			<view class="skeleton-title skeleton-blink"></view>
			<view class="skeleton-desc skeleton-blink"></view>
		</view>

		<!-- 选项卡骨架区 -->
		<view class="skeleton-tabs">
			<view class="skeleton-tab skeleton-blink" v-for="i in 4" :key="i"></view>
		</view>

		<!-- 内容骨架区 -->
		<view class="skeleton-content">
			<!-- 左侧分类栏 -->
			<view class="skeleton-sidebar">
				<view class="skeleton-cat skeleton-blink" v-for="i in 8"></view>
			</view>
			<!-- 右侧商品列表 -->
			<view class="skeleton-main">
				<view class="skeleton-product" v-for="j in 5">
					<view class="skeleton-img skeleton-blink"></view>
					<view class="skeleton-info">
						<view class="skeleton-line-1 skeleton-blink"></view>
						<view class="skeleton-line-2 skeleton-blink"></view>
						<view class="skeleton-line-3 skeleton-blink"></view>
					</view>
				</view>
			</view>
		</view>
	</view>
</template>

<script>
	export default {
		name: "mch-skeleton"
	}
</script>

<style lang="scss" scoped>
	.mch-skeleton-wrap {
		width: 100vw;
		height: 100vh;
		background: #f7f8fa;
		display: flex;
		flex-direction: column;
		overflow: hidden;
	}

	.skeleton-blink {
		animation: blink 1.5s infinite ease-in-out;
		background-color: #e8e8e8;
	}

	@keyframes blink {
		0% {
			opacity: 1;
		}

		50% {
			opacity: 0.5;
		}

		100% {
			opacity: 1;
		}
	}

	.skeleton-header {
		height: 200rpx;
		width: 100%;
	}

	.skeleton-header-info {
		padding: 30rpx 40rpx;
		background: #fff;
		border-radius: 20rpx 20rpx 0 0;
		position: relative;
		top: -30rpx;
		display: flex;
		flex-direction: column;
		align-items: center;

		.skeleton-avatar {
			width: 120rpx;
			height: 120rpx;
			border-radius: 50%;
			margin-top: -80rpx;
			margin-bottom: 20rpx;
			border: 6rpx solid #fff;
		}

		.skeleton-title {
			width: 300rpx;
			height: 40rpx;
			border-radius: 20rpx;
			margin-bottom: 20rpx;
		}

		.skeleton-desc {
			width: 400rpx;
			height: 24rpx;
			border-radius: 12rpx;
		}
	}

	.skeleton-tabs {
		display: flex;
		padding: 0 40rpx;
		background: #fff;
		justify-content: space-between;
		margin-top: -30rpx;
		padding-bottom: 20rpx;

		.skeleton-tab {
			width: 80rpx;
			height: 32rpx;
			border-radius: 16rpx;
		}
	}

	.skeleton-content {
		flex: 1;
		display: flex;
		background: #fff;
		margin-top: 20rpx;

		.skeleton-sidebar {
			width: 180rpx;
			background: #f7f8fa;
			padding-top: 20rpx;

			.skeleton-cat {
				width: 120rpx;
				height: 32rpx;
				border-radius: 16rpx;
				margin: 30rpx auto;
			}
		}

		.skeleton-main {
			flex: 1;
			padding: 24rpx;

			.skeleton-product {
				display: flex;
				margin-bottom: 40rpx;

				.skeleton-img {
					width: 160rpx;
					height: 160rpx;
					border-radius: 16rpx;
					margin-right: 20rpx;
					flex-shrink: 0;
				}

				.skeleton-info {
					flex: 1;
					display: flex;
					flex-direction: column;
					justify-content: space-around;

					.skeleton-line-1 {
						width: 80%;
						height: 32rpx;
						border-radius: 16rpx;
					}

					.skeleton-line-2 {
						width: 50%;
						height: 24rpx;
						border-radius: 12rpx;
					}

					.skeleton-line-3 {
						width: 30%;
						height: 40rpx;
						border-radius: 20rpx;
					}
				}
			}
		}
	}
</style>