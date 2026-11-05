<template>
	<view :class="[dot ? 'tui-badge-dot' : 'tui-badge', !dot ? 'tui-badge-scale' : '']" :style="badgeStyle"
		@tap="handleClick">
		<slot></slot>
	</view>
</template>

<script>
export default {
	name: 'tuiBadge',
	props: {
		// [核心] 背景颜色
		// 如果不传，则默认使用红色(或主题色)
		bgColor: {
			type: String,
			default: ''
		},
		// [核心] 字体颜色
		color: {
			type: String,
			default: '#fff'
		},
		// 快捷类型 default/danger
		type: {
			type: String,
			default: ''
		},
		// 是否是圆点
		dot: {
			type: Boolean,
			default: false
		},
		margin: {
			type: String,
			default: '0'
		},
		// 是否绝对定位
		absolute: {
			type: Boolean,
			default: false
		},
		top: {
			type: String,
			default: '-8rpx'
		},
		right: {
			type: String,
			default: '0'
		},
		// 缩放比例
		scaleRatio: {
			type: Number,
			default: 1
		},
		// 水平方向移动距离
		translateX: {
			type: String,
			default: '0'
		}
	},
	computed: {
		badgeStyle() {
			// 1. 确定背景色
			// 微信小程序中绑定对象 style 时，对驼峰和 null 值的处理较为严格。
			// 建议统一转换为字符串拼接，或者确保键名合法。
			let background = this.bgColor || '#D71F1F';
			// 支持外部传入 type='danger' 等预设
			if (this.type === 'danger' && !this.bgColor) {
				background = '#ff4d4f';
			}

			let positionStr = this.absolute ? 'absolute' : 'static';

			// 修复 transform 可能被解析异常的问题
			let transformStr = ``;
			if (this.scaleRatio != 1 || this.translateX != '0') {
				transformStr = `transform: scale(${this.scaleRatio}) translateX(${this.translateX});`;
			}

			// 拼接为字符串形式返回，解决小程序平台对style对象解析丢失的问题
			return `background-color: ${background}; color: ${this.color}; position: ${positionStr}; top: ${this.top}; right: ${this.right}; margin: ${this.margin}; ${transformStr}`;
		}
	},
	methods: {
		handleClick() {
			this.$emit('click', {});
		}
	}
};
</script>

<style scoped>
/* badge start */
.tui-badge-dot {
	height: 8px;
	width: 8px;
	border-radius: 50%;
	/* 确保圆点也有 z-index */
	z-index: 10;
}

.tui-badge {
	font-size: 24rpx;
	line-height: 24rpx;
	height: 36rpx;
	min-width: 36rpx;
	padding: 0 10rpx;
	box-sizing: border-box;
	border-radius: 100rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 10;
}

.tui-badge-scale {
	transform-origin: center center;
}

/* badge end */
</style>