<template>
	<view class="diy-title-bar" :style="[outerStyle]">
		<view class="title-bar-inner" :style="[innerStyle]" @click="pageTo">

			<!-- 左侧 / 居中 主标题 -->
			<view class="left-side" :class="{ 'is-center': xc.style_type === 2 }">
				<view class="prefix-line" v-if="xc.style_type === 1" :style="{ backgroundColor: xc.prefix_color }">
				</view>
				<text class="main-title" :style="[titleStyle]">{{ xc.title }}</text>
			</view>

			<!-- 右侧副标题 -->
			<view class="right-side" :style="{ color: xc.sub_title_color }" v-if="xc.style_type !== 2">
				<text class="sub-text" v-if="xc.sub_title">{{ xc.sub_title }}</text>
				<tui-icon name="arrowright" :size="26" :color="xc.sub_title_color" v-if="xc.show_sub_icon === 1"
					unit="rpx"></tui-icon>
			</view>

		</view>
	</view>
</template>

<script>
export default {
	name: 'tui-title-bar',
	props: {
		value: {
			type: Object,
			default: () => ({})
		}
	},
	computed: {
		xc() {
			let v = this.value || {};
			return {
				is_padding: v.is_padding !== undefined ? v.is_padding : '0',
				is_circle: v.is_circle !== undefined ? v.is_circle : '0',
				background: v.background || '#ffffff',
				title_color: v.title_color || '#000000',
				sub_title_color: v.sub_title_color || '#999999',
				title: v.title || '标题',
				sub_title: v.sub_title !== undefined ? v.sub_title : '查看更多',
				url: v.url || '',
				style_type: v.style_type !== undefined ? v.style_type : 0,
				font_size: v.font_size || 16,
				font_weight: v.font_weight !== undefined ? v.font_weight : 1,
				prefix_color: v.prefix_color || '#ff4544',
				show_sub_icon: v.show_sub_icon !== undefined ? v.show_sub_icon : 1
			};
		},
		outerStyle() {
			let padding = this.xc.is_padding == '1' ? '0 24rpx' : '0';
			return {
				padding: padding,
				backgroundColor: 'transparent',
				width: '100%',
				boxSizing: 'border-box'
			};
		},
		innerStyle() {
			let radius = this.xc.is_circle == '1' ? '20rpx' : '0';
			return {
				borderRadius: radius,
				backgroundColor: this.xc.background,
			};
		},
		titleStyle() {
			return {
				color: this.xc.title_color,
				fontSize: (this.xc.font_size * 2) + 'rpx',
				fontWeight: this.xc.font_weight == 1 ? 'bold' : 'normal'
			};
		}
	},
	methods: {
		pageTo() {
			if (!this.xc.url) return;
			// 兼容某些老旧配置里有 web 跳转需求
			if (this.value.type == 1 && this.xc.url.indexOf('/others/web/web') === -1) {
				this.$T.go('/others/web/web?url=' + this.xc.url)
			} else {
				this.$T.go(this.xc.url)
			}
		}
	}
}
</script>

<style scoped>
.diy-title-bar {
	width: 100%;
	box-sizing: border-box;
}

.title-bar-inner {
	height: 88rpx;
	padding: 0 24rpx;
	box-sizing: border-box;
	display: flex;
	flex-direction: row;
	align-items: center;
	justify-content: space-between;
}

.left-side {
	display: flex;
	flex-direction: row;
	align-items: center;
	flex: 1;
}

.left-side.is-center {
	justify-content: center;
}

.prefix-line {
	width: 6rpx;
	height: 32rpx;
	border-radius: 4rpx;
	margin-right: 16rpx;
}

.main-title {
	max-width: 440rpx;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
	line-height: 1.2;
}

.right-side {
	display: flex;
	flex-direction: row;
	align-items: center;
	flex-shrink: 0;
	margin-left: 20rpx;
}

.sub-text {
	font-size: 26rpx;
	margin-right: 6rpx;
}
</style>
