<template>
	<view class="spec-module" @tap="handleClick">
		<view class="spec-label">规格</view>
		<view class="spec-value">
			<text v-if="goods.is_attr == 0">已选: 默认规格</text>
			<text v-else-if="!currentSku">请选择: {{ unselectedSpecText }}</text>
			<text v-else>已选: {{ selectedSpecText }}</text>
		</view>
		<tui-icon name="arrowright" :size="18" color="#666"></tui-icon>
	</view>
</template>

<script>
	export default {
		name: "goods-spec-bar",
		props: {
			goods: {
				type: Object,
				default: () => ({})
			},
			currentSku: {
				type: Object,
				default: null
			},
			// 也就是 parent 中的 selectedSpecText
			selectedSpecText: {
				type: String,
				default: ''
			},
			// 也就是 parent 中的 unselectedSpecText
			unselectedSpecText: {
				type: String,
				default: ''
			}
		},
		methods: {
			handleClick() {
				this.$emit('showPopup');
			}
		}
	}
</script>

<style lang="scss" scoped>
	.spec-module {
		display: flex;
		align-items: center;
		height: 100rpx;
		box-sizing: border-box;
		padding: 0 24rpx; // 保持与页面一致的padding
		background-color: #fff;
		margin-top: 20rpx;

		.spec-label {
			font-size: 28rpx;
			color: #999;
			margin-right: 20rpx;
			font-weight: 500;
		}

		.spec-value {
			flex: 1;
			font-size: 28rpx;
			color: #333;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
			font-weight: 500;
		}
	}
</style>