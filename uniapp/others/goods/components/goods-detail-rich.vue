<template>
	<view class="detail-module">
		<view class="card-header">
			<view class="header-title">商品详情</view>
		</view>
		<view class="detail-content" v-if="hasDetail">
			<tui-rich-text :value="goodsInfo.detail"></tui-rich-text>
		</view>
		<view class="detail-empty" v-else>商家暂未填写图文详情</view>
	</view>
</template>

<script>
	export default {
		name: "goods-detail-rich",
		props: {
			goodsInfo: {
				type: Object,
				default: null
			}
		},
		computed: {
			hasDetail() {
				const detail = this.goodsInfo && this.goodsInfo.detail;
				return !!(detail && String(detail).trim());
			}
		}
	}
</script>

<style lang="scss" scoped>
	$uni-spacing-col: 24rpx;

	.detail-module {
		padding: $uni-spacing-col;
		background-color: #fff;

		.card-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding-bottom: 24rpx;

			.header-title {
				font-size: 32rpx;
				font-weight: bold;
				position: relative;
				padding-left: 20rpx;
			}

			.header-title::before {
				content: '';
				position: absolute;
				left: 0;
				top: 50%;
				transform: translateY(-50%);
				width: 6rpx;
				height: 28rpx;
				/* 跟随商城主题色。同目录 goods-store.vue 已证明 var(--theme-color) 能穿透到组件样式里
			   —— goods.vue 根节点绑了 themeStyle,变量顺着继承下来 */
			background: linear-gradient(to bottom, var(--theme-color, #D71F1F), var(--theme-color-light, #ffcccc));
				border-radius: 6rpx;
			}
		}

		.detail-empty {
			padding: 60rpx 0;
			text-align: center;
			font-size: 26rpx;
			color: #b4b8c2;
			background-color: #fafbfc;
			border-radius: 16rpx;
		}

		.detail-content {
			padding: 24rpx 0 0;
			font-size: 28rpx;
			line-height: 1.8;
			overflow: hidden;
		}

	}
</style>
