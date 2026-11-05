<template>
	<view v-if="show || animating" class="tui-popup-wrapper" @touchmove.stop.prevent :style="{ zIndex: zIndex }">
		<!-- 遮罩层 -->
		<view
			class="tui-popup-mask"
			:class="{ 'tui-mask-show': showContent }"
			@tap="handleClose"
			v-if="mask"
		></view>
		<!-- 弹窗内容 -->
		<view
			class="tui-popup-content"
			:class="{ 'tui-content-show': showContent, 'tui-popup-radius': radius }"
			:style="contentStyle"
		>
			<view v-if="showClose" class="tui-popup-close" @tap.stop="handleClose">
				<text class="tui-close-icon">×</text>
			</view>
			<slot></slot>
		</view>
	</view>
</template>

<script>
export default {
	name: 'tuiBottomPopup',
	props: {
		show: {
			type: Boolean,
			default: false
		},
		mask: {
			type: Boolean,
			default: true
		},
		showClose: {
			type: Boolean,
			default: false
		},
		backgroundColor: {
			type: String,
			default: '#fff'
		},
		height: {
			type: Number,
			default: 0
		},
		radius: {
			type: [Boolean, Number, String],
			default: true
		},
			zIndex: {
			type: [Number, String],
			default: 999
		},
		maskZIndex: {
			type: [Number, String],
			default: 998
		},
		translateY: {
			type: String,
			default: '0'
		}
	},
	data() {
		return {
			showContent: false,
			animating: false
		}
	},
	watch: {
		show(val) {
			if (val) {
				this.animating = true;
				this.$nextTick(() => {
					setTimeout(() => { this.showContent = true; }, 30);
				});
			} else {
				this.showContent = false;
				setTimeout(() => { this.animating = false; }, 300);
			}
		}
	},
	computed: {
		contentStyle() {
			let style = {
				backgroundColor: this.backgroundColor,
			};
			if (this.height) {
				style.height = this.height + 'rpx';
			}
			if (this.radius !== true && this.radius !== false && this.radius !== 'true' && this.radius !== 'false' && this.radius !== '') {
				let val = Number(this.radius);
				if (!isNaN(val) && val > 0) {
					style.borderTopLeftRadius = val + 'rpx';
					style.borderTopRightRadius = val + 'rpx';
				}
			}
			return style;
		}
	},
	methods: {
		handleClose() {
			if (!this.show) return;
			this.$emit('close', {});
		}
	}
};
</script>

<style>
/* 不使用 scoped，避免小程序 data 属性选择器干扰层级 */

.tui-popup-wrapper {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	/* z-index 由 zIndex prop 通过 inline style 控制，默认 999 */
}

.tui-popup-mask {
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background-color: rgba(0, 0, 0, 0.5);
	opacity: 0;
	transition: opacity 0.3s;
}

.tui-mask-show {
	opacity: 1;
}

.tui-popup-content {
	position: absolute;
	left: 0;
	right: 0;
	bottom: 0;
	min-height: 20rpx;
	transform: translateY(100%);
	transition: transform 0.3s ease-out;
}

.tui-content-show {
	transform: translateY(0);
}

.tui-popup-radius {
	border-top-left-radius: 24rpx;
	border-top-right-radius: 24rpx;
	overflow: hidden;
}

.tui-popup-close {
	position: absolute;
	top: 15rpx;
	right: 25rpx;
	width: 60rpx;
	height: 60rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 10;
	padding: 10rpx;
}

.tui-close-icon {
	font-size: 40rpx;
	line-height: 1;
	color: #999;
	font-weight: bold;
}
</style>
