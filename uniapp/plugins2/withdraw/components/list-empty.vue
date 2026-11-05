<template>
	<!-- 记录类页面共用的空状态:图标 + 一句话说明 + 可选的引导按钮 -->
	<view class="le" :style="themeStyle">
		<view class="le-ico">
			<uni-icons :type="icon" size="38" color="var(--theme-color)"></uni-icons>
		</view>
		<view class="le-title">{{ title }}</view>
		<view class="le-desc" v-if="desc">{{ desc }}</view>
		<view class="le-btn" v-if="btn" @click="$emit('click')">{{ btn }}</view>
	</view>
</template>

<script>
	export default {
		name: 'listEmpty',
		props: {
			icon: { type: String, default: 'list' },
			title: { type: String, default: '暂无记录' },
			desc: { type: String, default: '' },
			btn: { type: String, default: '' }
		}
	};
</script>

<style lang="scss" scoped>
	.le {
		display: flex;
		flex-direction: column;
		align-items: center;
		padding: 140rpx 60rpx 80rpx;
	}

	.le-ico {
		width: 144rpx;
		height: 144rpx;
		border-radius: 50%;
		background: var(--theme-color-bg);
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.le-title {
		margin-top: 32rpx;
		font-size: 30rpx;
		font-weight: 600;
		color: #333;
	}

	.le-desc {
		margin-top: 12rpx;
		font-size: 26rpx;
		color: #999;
		text-align: center;
		line-height: 1.6;
	}

	.le-btn {
		margin-top: 40rpx;
		padding: 0 56rpx;
		height: 80rpx;
		line-height: 80rpx;
		border-radius: 40rpx;
		font-size: 28rpx;
		font-weight: 600;
		color: #fff;
		background: var(--theme-color);
		box-shadow: 0 8rpx 20rpx var(--theme-shadow);
	}
</style>
