<template>
	<!-- 功能没开时的页面内提示:替代原来「弹窗 → 点确定自动返回」的做法(弹窗一闪、页面又被退回,用户不知道发生了什么) -->
	<view class="fc">
		<view class="fc-ico">
			<uni-icons type="locked-filled" size="34" color="#c9ccd3"></uni-icons>
		</view>
		<view class="fc-title">{{ title }}</view>
		<view class="fc-desc">{{ desc }}</view>
		<view class="fc-btn" @click="goBack">返回</view>
	</view>
</template>

<script>
	export default {
		name: 'featureClosed',
		props: {
			title: { type: String, default: '该功能暂未开放' },
			desc: { type: String, default: '如有疑问,请联系客服' }
		},
		methods: {
			goBack() {
				const pages = getCurrentPages();
				if (pages.length > 1) uni.navigateBack();
				else uni.reLaunch({ url: '/pages/index/index' });
			}
		}
	};
</script>

<style lang="scss" scoped>
	.fc {
		margin: -30rpx 30rpx 0;
		position: relative;
		z-index: 2;
		padding: 70rpx 40rpx 56rpx;
		text-align: center;
		background: #fff;
		border-radius: 28rpx;
		box-shadow: 0 8rpx 26rpx rgba(0, 0, 0, 0.05);
	}

	.fc-ico {
		width: 120rpx;
		height: 120rpx;
		margin: 0 auto;
		border-radius: 50%;
		background: #f5f6f8;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.fc-title {
		margin-top: 28rpx;
		font-size: 32rpx;
		font-weight: 600;
		color: #333;
	}

	.fc-desc {
		margin-top: 12rpx;
		font-size: 25rpx;
		color: #999;
		line-height: 1.7;
	}

	.fc-btn {
		margin: 40rpx auto 0;
		width: 280rpx;
		height: 80rpx;
		line-height: 80rpx;
		border-radius: 40rpx;
		background: var(--theme-color);
		color: #fff;
		font-size: 28rpx;
		font-weight: 600;
		box-shadow: 0 8rpx 20rpx var(--theme-shadow);
	}
</style>
