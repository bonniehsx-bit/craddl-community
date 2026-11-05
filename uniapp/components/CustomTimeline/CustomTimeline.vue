<template>
	<view class="timeline-container">
		<view class="timeline-item" v-for="(item, index) in items" :key="index">
			<view class="timeline-left">
				<view class="timeline-node" :class="{'is-active': index === 0}"></view>
				<view class="timeline-line" v-if="index < items.length - 1"></view>
			</view>
			<view class="timeline-content">
				<view class="content-text" :class="{'is-active-text': index === 0}">{{ item.AcceptStation }}</view>
				<view class="content-time">{{ item.AcceptTime }}</view>
			</view>
		</view>
	</view>
</template>

<script>
	export default {
		name: "CustomTimeline",
		props: {
			// 接收一个包含物流轨迹的数组
			items: {
				type: Array,
				default: () => []
			}
		}
	}
</script>

<style lang="scss" scoped>
	.timeline-container {
		padding: 30rpx;
	}

	.timeline-item {
		display: flex;
		position: relative;
	}

	.timeline-left {
		display: flex;
		flex-direction: column;
		align-items: center;
		margin-right: 20rpx;
		flex-shrink: 0;
	}

	.timeline-node {
		width: 20rpx;
		height: 20rpx;
		border-radius: 50%;
		background-color: #ddd;
		z-index: 1;

		&.is-active {
			background-color: #D71F1F;
			// 增加一个外圈发光效果，突出显示
			box-shadow: 0 0 0 8rpx rgba(20, 58, 148, 0.1);
		}
	}

	.timeline-line {
		flex: 1;
		width: 2rpx;
		background-color: #e0e0e0;
	}

	.timeline-content {
		padding-bottom: 40rpx;
	}

	.content-text {
		font-size: 26rpx;
		color: #666;
		line-height: 1.5;

		&.is-active-text {
			color: #333;
			font-weight: 500;
		}
	}

	.content-time {
		font-size: 24rpx;
		color: #999;
		margin-top: 8rpx;
	}
</style>