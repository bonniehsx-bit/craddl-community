<template>
	<!-- 风格组件 · 入口卡:左图标(表情或图片)+ 标题副标题 + 右按钮,整张卡可点 -->
	<view :style="'padding:' + (d.is_padding == 1 ? '12rpx 20rpx' : '0')">
		<view class="pc" :style="boxStyle" @click="go">
			<view v-if="d.glow == 1" class="glow" :style="'background:radial-gradient(ellipse,' + d.glow_color + ',transparent 65%)'"></view>
			<view class="face" :style="'box-shadow:0 0 0 5rpx ' + d.accent_color">
				<image v-if="d.icon_type === 'image' && d.pic_url" class="face-img" :src="d.pic_url" mode="aspectFill"></image>
				<text v-else class="face-emoji">{{ d.icon || '🎁' }}</text>
			</view>
			<view class="copy">
				<text class="t" :style="'color:' + d.text_color">{{ d.title }}</text>
				<text v-if="d.subtitle" class="s" :style="'color:' + d.sub_color">{{ d.subtitle }}</text>
			</view>
			<view v-if="d.btn_text" class="btn" :style="'background:' + d.btn_bg + ';color:' + d.btn_color">{{ d.btn_text }} ›</view>
		</view>
	</view>
</template>

<script>
	import { goLink } from '../tui-style-kit/style-kit.js';

	export default {
		name: 'tui-promo-card',
		props: {
			value: { type: Object, default: () => ({}) }
		},
		computed: {
			d() {
				return this.value || {};
			},
			boxStyle() {
				return 'background:linear-gradient(135deg,' + this.d.bg_color2 + ',' + this.d.bg_color + ');border-radius:'
					+ (this.d.is_circle == 1 ? '24rpx' : '0');
			}
		},
		methods: {
			go() {
				goLink(this.d.url);
			}
		}
	};
</script>

<style scoped>
	.pc { position: relative; overflow: hidden; display: flex; flex-direction: row; align-items: center; padding: 28rpx; }
	.glow { position: absolute; right: -50rpx; top: -70rpx; width: 340rpx; height: 220rpx; pointer-events: none; }
	.face { position: relative; width: 104rpx; height: 104rpx; border-radius: 50%; background: #ffffff; flex-shrink: 0; overflow: hidden;
		display: flex; align-items: center; justify-content: center; }
	.face-img { width: 100%; height: 100%; }
	.face-emoji { font-size: 60rpx; line-height: 1; }
	.copy { position: relative; flex: 1; min-width: 0; margin: 0 20rpx 0 24rpx; }
	.t { display: block; font-size: 30rpx; font-weight: 800; }
	.s { display: block; margin-top: 6rpx; font-size: 22rpx; }
	.btn { position: relative; height: 60rpx; line-height: 60rpx; padding: 0 24rpx; border-radius: 30rpx; font-size: 24rpx; font-weight: 700; white-space: nowrap; }
</style>
