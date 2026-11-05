<template>
	<view class="tui-divider" :style="{ height: height + 'rpx' }">
		<!-- [优化] background 绑定到计算属性 bgColor -->
		<view class="tui-divider-line" :style="{ width: width, background: bgColor }"></view>
		<view class="tui-divider-text" :style="{ color: color, fontSize: size + 'rpx', lineHeight: size + 'rpx', backgroundColor: backgroundColor, fontWeight: bold ? 'bold' : 'normal' }">
			<slot></slot>
		</view>
	</view>
</template>

<script>
	export default {
		name: 'tuiDivider',
		props: {
			height: {
				type: Number,
				default: 100
			},
			width: {
				type: String,
				default: '100%'
			},
			dividerColor: {
				type: String,
				default: '#b1b2b5'
			},
			color: {
				type: String,
				default: '#999'
			},
			size: {
				type: Number,
				default: 24
			},
			bold: {
				type: Boolean,
				default: false
			},
			backgroundColor: {
				type: String,
				default: '#fafafa'
			},
			gradual: {
				type: Boolean,
				default: false
			},
			gradualColor: {
				type: Array,
				default: function() {
					return ['#eee', '#000'];
				}
			}
		},
		// [优化] 使用 computed 替代 methods
		computed: {
			bgColor() {
				if (this.gradual) {
					// 构造渐变色
					return `linear-gradient(to right, ${this.gradualColor[0]}, ${this.gradualColor[1]}, ${this.gradualColor[1]}, ${this.gradualColor[0]})`;
				} else {
					return this.dividerColor;
				}
			}
		}
	};
</script>

<style scoped>
	.tui-divider {
		width: 100%;
		position: relative;
		text-align: center;
		display: flex;
		justify-content: center;
		align-items: center;
		box-sizing: border-box;
		overflow: hidden;
	}

	.tui-divider-line {
		position: absolute;
		height: 2rpx;
		top: 50%;
		left: 50%;
		/* [优化] 增加 -webkit- 前缀以增强兼容性 */
		-webkit-transform: scaleY(0.5) translateX(-50%);
		transform: scaleY(0.5) translateX(-50%);
	}

	.tui-divider-text {
		position: relative;
		text-align: center;
		padding: 0 18rpx;
		z-index: 1;
	}
</style>