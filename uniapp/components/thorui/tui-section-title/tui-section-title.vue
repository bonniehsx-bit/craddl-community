<template>
	<!-- 风格组件 · 区块标题:主标题 + 英文小字 + 右侧「查看全部」;衬线 / 黑体、左对齐 / 居中、竖条 / 细线装饰 -->
	<view class="st" :class="'align-' + (d.align || 'left')" :style="'background:' + d.bg_color" @click="go">
		<view class="left">
			<view v-if="d.decor === 'bar'" class="bar" :style="'background:' + d.accent_color"></view>
			<view v-if="d.decor === 'line'" class="line" :style="'background:' + d.accent_color"></view>
			<text class="title" :style="titleStyle">{{ d.title }}</text>
			<text v-if="d.en_title" class="en" :style="'color:' + d.sub_color">{{ d.en_title }}</text>
			<view v-if="d.decor === 'line'" class="line" :style="'background:' + d.accent_color"></view>
		</view>
		<text v-if="d.more_text && d.align !== 'center'" class="more" :style="'color:' + d.sub_color">{{ d.more_text }} ›</text>
	</view>
</template>

<script>
	import { FONT_FAMILY, goLink } from '../tui-style-kit/style-kit.js';

	export default {
		name: 'tui-section-title',
		props: {
			value: { type: Object, default: () => ({}) }
		},
		computed: {
			d() {
				return this.value || {};
			},
			titleStyle() {
				return 'color:' + this.d.text_color + ';font-family:' + (FONT_FAMILY[this.d.font] || FONT_FAMILY.sans)
					+ ';font-size:' + (Number(this.d.size) || 19) * 2 + 'rpx';
			}
		},
		methods: {
			go() {
				if (this.d.more_text) goLink(this.d.url);
			}
		}
	};
</script>

<style scoped>
	.st { display: flex; flex-direction: row; align-items: baseline; justify-content: space-between; padding: 36rpx 24rpx 18rpx; }
	.left { display: flex; flex-direction: row; align-items: baseline; min-width: 0; }
	.title { font-weight: 900; letter-spacing: 1rpx; white-space: nowrap; }
	.en { margin-left: 12rpx; font-size: 18rpx; letter-spacing: 4rpx; white-space: nowrap; }
	.more { font-size: 22rpx; white-space: nowrap; }
	.bar { width: 6rpx; height: 28rpx; border-radius: 4rpx; margin-right: 12rpx; align-self: center; }
	.line { width: 44rpx; height: 2rpx; margin: 0 14rpx; align-self: center; }
	.align-center { justify-content: center; }
	.align-center .left { flex-direction: column; align-items: center; }
	.align-center .en { margin-left: 0; margin-top: 4rpx; }
	.align-center .line { display: none; }
</style>
