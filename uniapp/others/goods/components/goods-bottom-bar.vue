<template>
	<!-- 底部操作栏 -->
	<view class="bottom-action-bar">

		<!-- 首页 -->
		<view class="action-item hover-effect" @click="handleAction('home')">
			<tui-icon name="home" color="#666666" :size="22"></tui-icon>
			<text class="action-text">首页</text>
		</view>

		<!-- 购物车 -->
		<view class="action-item hover-effect" @click="handleAction('cart')">
			<view class="icon-wrapper">
				<tui-icon name="cart" color="#666666" :size="22"></tui-icon>

				<!-- 自定义纯CSS角标 -->
				<view v-if="cartCount > 0" class="custom-badge" :style="{ backgroundColor: themeColor }">
					{{ displayCartCount }}
				</view>
			</view>
			<text class="action-text">购物车</text>
		</view>

		<!-- 客服 (通过 Wrapper 统一接管微信原生/插件IM 分发逻辑) -->
		<view class="action-item hover-effect">
			<tui-cs-wrapper :goods="goods" :current-url="currentUrl">
				<view style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; height: 100%;">
					<tui-icon name="kefu" :size="22" color="#666666"></tui-icon>
					<text class="action-text">客服</text>
				</view>
			</tui-cs-wrapper>
		</view>

		<!-- 右侧按钮组 -->
		<view class="buttons-group">
			<!-- 加入购物车：主题色淡版（次级按钮）—— 浅色填充 + 主题色文字 -->
			<view v-if="isShowAddCart == 1" class="action-btn cart-btn"
				:style="{ backgroundColor: hexToRgba(themeColor, 0.12), color: themeColor }"
				@click="showPopup('cart')">
				加入购物车
			</view>

			<!-- 立即购买：主题色渐变(主按钮) -->
			<view class="action-btn buy-btn"
				:style="{ background: 'linear-gradient(90deg,' + hexToRgba(themeColor, 0.75) + ',' + themeColor + ')', boxShadow: '0 4rpx 10rpx ' + hexToRgba(themeColor, 0.3) }"
				@click="showPopup('buy')">
				立即购买
			</view>
		</view>
	</view>
</template>

<script>
export default {
	name: "goods-bottom-bar",
	props: {
		cartCount: {
			type: Number,
			default: 0
		},
		isShowAddCart: {
			type: [Number, String],
			default: 1
		},
		goods: {
			type: Object,
			default: () => null
		},
		currentUrl: {
			type: String,
			default: ''
		}
	},
	data() {
		return {
			// IM configuration logic moved to tui-cs-wrapper
		}
	},
	computed: {
		displayCartCount() {
			if (this.cartCount > 99) {
				return '99+';
			}
			return this.cartCount;
		}
	},
	methods: {
		handleAction(type) {
			this.$emit('action', type);
		},
		showPopup(mode) {
			this.$emit('showPopup', mode);
		}
	}
}
</script>

<style lang="scss" scoped>
/* 变量定义
 * 高度和 tui-tabbar 保持一致:整体 120rpx,不再额外加底部安全区。
 * 原来是 100rpx + 安全区内边距(iPhone 约 34px),按钮下面白一大截;
 * tui-tabbar 的实际显示高度就是 120rpx,用户认定这个高度合适(见 5fa9a32cf2)。*/
$bar-height: 120rpx;
$btn-height: 72rpx;
$text-color: #666;
$text-size: 20rpx;

.bottom-action-bar {
	position: fixed;
	bottom: 0;
	left: 0;
	right: 0;
	height: $bar-height;
	background-color: #ffffff;
	display: flex;
	align-items: center;
	padding: 0 24rpx;
	border-top: 1rpx solid #f5f5f5;
	box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.03);

	box-sizing: border-box;
}

/* 左侧图标操作区 */
.action-item {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	width: 90rpx; // 稍微加大点击区域
	height: 100%;
	position: relative;

	/* 点击时的按压效果 */
	&.hover-effect:active {
		opacity: 0.7;
	}

	.action-text {
		font-size: $text-size;
		color: $text-color;
		margin-top: 4rpx;
		line-height: 1;
	}

	/* 图标包裹层，用于定位角标 */
	.icon-wrapper {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	/* 自定义角标核心样式 */
	.custom-badge {
		position: absolute;
		top: -8rpx;
		/* 向上偏移 */
		right: -12rpx;
		/* 向右偏移，根据图标大小微调 */
		height: 30rpx;
		min-width: 30rpx;
		/* 保证单数字是圆 */
		padding: 0 8rpx;
		/* 左右内边距，为了多数字时变成椭圆 */
		border-radius: 15rpx;
		/* 高度的一半 */
		color: #ffffff;
		font-size: 20rpx;
		line-height: 30rpx;
		text-align: center;
		box-sizing: border-box;
		z-index: 10;
		white-space: nowrap;
		border: 2rpx solid #fff;
		/* 增加白色描边，视觉上与图标分离，更精致 */
	}
}

/* 右侧按钮组 */
.buttons-group {
	flex: 1;
	display: flex;
	align-items: center;
	margin-left: 20rpx;
	gap: 16rpx; // 两个按钮之间的间距

	.action-btn {
		flex: 1;
		height: $btn-height;
		border-radius: 36rpx; // 半圆角
		font-size: 28rpx;
		font-weight: 600;
		display: flex;
		align-items: center;
		justify-content: center;
		color: #fff;
		transition: opacity 0.2s;

		&:active {
			opacity: 0.85; // 按钮点击反馈
		}

		&.cart-btn {
			// 背景色与文字色由 :style 动态控制（主题色 12% 淡填充 + 主题色文字）
		}

		&.buy-btn {
			// 背景色和阴影由 :style 动态控制（跟随主题色）
		}
	}
}
</style>