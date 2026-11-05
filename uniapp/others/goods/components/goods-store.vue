<template>
	<view class="store-card" v-if="recommendedMch" @click="goToMchPage(recommendedMch.id)">
		<view class="store-left">
			<image class="store-logo" :src="recommendedMch.logo" mode="aspectFill"></image>
		</view>
		<view class="store-center">
			<!-- 第一行：店名 + 配送标签内联 -->
			<view class="name-row">
				<text class="store-name">{{ recommendedMch.mch_name }}</text>
				<view class="tags-row" v-if="hasDeliveryTags">
					<text class="tag" v-if="recommendedMch.delivery_options && recommendedMch.delivery_options.includes('pickup')">自提</text>
					<text class="tag" v-if="recommendedMch.delivery_options && recommendedMch.delivery_options.includes('city')">配送</text>
				</view>
			</view>
			<!-- 第二行：地址 + 距离（距离改为右对齐 pill 样式） -->
			<view class="location-row">
				<tui-icon name="location" :size="11" color="#94a3b8"
					margin="0 6rpx 0 0"></tui-icon>
				<text class="address">{{ recommendedMch.address || '暂无地址信息' }}</text>
				<view class="distance-pill" v-if="recommendedMch.distance_text">
					<text class="distance-text">{{ recommendedMch.distance_text }}</text>
				</view>
				<!-- 未获取定位时不展示距离:提供可点击入口,由用户主动触发定位(符合应用市场审核) -->
				<view class="distance-pill locate-pill" v-else @click.stop="onLocate">
					<text class="distance-text">查看距离</text>
				</view>
			</view>
		</view>
		<view class="store-right">
			<view class="enter-btn">进店</view>
		</view>
	</view>
</template>

<script>
export default {
	name: "goods-store",
	props: {
		recommendedMch: {
			type: Object,
			default: null
		}
	},
	computed: {
		hasDeliveryTags() {
			const opts = this.recommendedMch && this.recommendedMch.delivery_options;
			if (!opts) return false;
			return opts.includes('pickup') || opts.includes('city');
		}
	},
	methods: {
		goToMchPage(id) {
			this.$emit('goMch', id);
		},
		// 用户点击「查看距离」→ 通知父页面申请定位授权(不在组件内直接调定位)
		onLocate() {
			this.$emit('locate');
		}
	}
}
</script>

<style lang="scss" scoped>
.store-card {
	display: flex;
	align-items: center;
	padding: 24rpx;
	background: #fff;
}

.enter-btn {
	height: 56rpx;
	line-height: 52rpx;
	padding: 0 24rpx;
	border-radius: 28rpx;
	border: 2rpx solid var(--theme-color);
	color: var(--theme-color);
	font-size: 24rpx;
	box-sizing: border-box;
}

/* 左：Logo */
.store-left {
	flex-shrink: 0;
	margin-right: 20rpx;

	.store-logo {
		width: 96rpx;
		height: 96rpx;
		border-radius: 16rpx;
		display: block;
		border: 1rpx solid #f0f0f0;
		background: #f5f5f5;
	}
}

/* 中：信息 */
.store-center {
	flex: 1;
	min-width: 0;
	display: flex;
	flex-direction: column;
	gap: 10rpx;
	min-height: 96rpx;
	justify-content: center;

	.name-row {
		display: flex;
		align-items: center;
		gap: 10rpx;
	}

	.store-name {
		font-size: 28rpx;
		color: #1a1a1a;
		font-weight: 700;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		flex: 1;
		min-width: 0;
	}

	.tags-row {
		display: flex;
		align-items: center;
		flex-shrink: 0;
		gap: 6rpx;

		/* 紧凑型 pill：主题色边框 + 浅色背景；line-height 控紧，无上下额外间距 */
		.tag {
			font-size: 18rpx;
			color: var(--theme-color, #FF4D4F);
			background: var(--theme-color-bg, rgba(255, 77, 79, 0.08));
			border: 1rpx solid var(--theme-color, #FF4D4F);
			border-radius: 6rpx;
			padding: 0 10rpx;
			line-height: 28rpx;
			height: 28rpx;
			font-weight: 600;
			display: inline-block;
		}
	}

	.location-row {
		display: flex;
		align-items: center;
		font-size: 22rpx;
		color: #94a3b8;
		line-height: 1.3;

		.address {
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
			flex: 1;
			min-width: 0;
		}

		/* 距离 pill：浅色背景 + 主题色文字 + 定位小图标 */
		.distance-pill {
			display: inline-flex;
			align-items: center;
			margin-left: 12rpx;
			flex-shrink: 0;
			padding: 2rpx 10rpx;
			background: var(--theme-color-bg, rgba(255, 77, 79, 0.08));
			border-radius: 999rpx;
			height: 28rpx;

			.distance-text {
				font-size: 20rpx;
				color: var(--theme-color, #FF4D4F);
				font-weight: 700;
				line-height: 1;
				font-family: 'DIN Alternate', sans-serif;
			}
		}
	}
}

/* 右：箭头 */
.store-right {
	flex-shrink: 0;
	margin-left: 16rpx;
	display: flex;
	align-items: center;
}
</style>
