<template>
	<view class="search-bar-container">
		<!-- 左侧输入区域 -->
		<view class="search-input-wrapper">
			<uni-icons class="search-icon" type="search" size="20" color="#999999"></uni-icons>
			<input class="search-input" :value="inputValue" @input="onInput" @confirm="onConfirm" confirm-type="search"
				:placeholder="placeholder" placeholder-class="input-placeholder" />
		</view>

		<!-- 右侧Logo区域 -->
		<view class="right-logo-wrapper">
			<!-- 请将此处的 src 替换为您自己的Logo图片路径 -->
			<image class="right-logo" :src="$util.getAsset('/static/logo.png')" mode="aspectFit"></image>
		</view>
	</view>
</template>

<script>
	export default {
		name: "TuiSearch",
		props: {
			// 支持 v-model (Vue 2)
			value: {
				type: String,
				default: ''
			},
			// 支持 v-model (Vue 3)
			modelValue: {
				type: String,
				default: ''
			},
			// 输入框的提示文字
			placeholder: {
				type: String,
				default: '搜索品牌或车型'
			}
		},
		computed: {
			// 兼容 Vue 2 和 Vue 3 的 v-model
			inputValue() {
				return this.value || this.modelValue;
			}
		},
		methods: {
			// 处理输入事件，用于更新 v-model
			onInput(event) {
				const value = event.detail.value;
				this.$emit('input', value); // 兼容 Vue 2
				this.$emit('update:modelValue', value); // 兼容 Vue 3
			},
			// 处理确认事件
			onConfirm(event) {
				const value = event.detail.value;
				this.$emit('confirm', value);
			}
		}
	}
</script>

<style lang="scss" scoped>
	.search-bar-container {
		display: flex;
		align-items: center;
		background-color: #fff;
		padding: 20rpx 30rpx;
		border-radius: 50rpx; // 圆角搜索栏
		box-shadow: 0 4rpx 8rpx rgba(0, 0, 0, 0.05);
	}

	.search-input-wrapper {
		flex: 1; // 占据剩余空间
		display: flex;
		align-items: center;
		height: 100%;
	}

	.search-icon {
		margin-right: 16rpx;
	}

	.search-input {
		flex: 1;
		font-size: 28rpx;
		color: #333;
		height: 100%;
	}

	// placeholder 样式
	.input-placeholder {
		color: #999999;
		font-size: 28rpx;
	}

	.right-logo-wrapper {
		padding-left: 20rpx; // 与输入框保持一些间距
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.right-logo {
		width: 44rpx;
		height: 44rpx;
	}
</style>