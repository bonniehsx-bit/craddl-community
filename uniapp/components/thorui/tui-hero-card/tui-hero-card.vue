<template>
	<!-- 风格组件 · 主视觉卡:眉题 + 双行标题(第二行强调色)+ 副标题 + 自动倒计时 + 两个按钮 + 带相框的配图 -->
	<view class="hero-wrap" :style="wrapStyle">
		<view class="hero" :style="boxStyle">
			<view v-if="d.glow == 1" class="glow" :style="glowStyle"></view>
			<!-- 顶边淡入:主色 → 透明,盖住斜向渐变和极光的顶边,和上面同色的顶栏搜索等无缝衔接 -->
			<view v-if="d.bg_type !== 'image'" class="top-fade" :style="topFadeStyle"></view>
			<view class="inner">
				<view class="copy">
					<text v-if="d.eyebrow" class="eyebrow" :style="'color:' + d.accent_color">{{ d.eyebrow }}</text>
					<view class="title" :style="titleStyle">
						<text v-if="d.title" class="t-line">{{ d.title }}</text>
						<text v-if="d.title_hl" class="t-line" :style="'color:' + d.accent_color">{{ d.title_hl }}</text>
					</view>
					<view v-if="subLines.length" class="sub" :style="'color:' + d.sub_color">
						<text v-for="(l, i) in subLines" :key="i" class="sub-line">{{ l }}</text>
					</view>
					<view v-if="days !== null && days >= 0" class="countdown" :style="'color:' + d.accent_color + ';border-color:' + d.accent_color">
						<!-- 截止日当天不显示「0 天」,换成当天文案 -->
						<text v-if="days === 0" class="cd-today">{{ d.countdown_today || '今天截止' }}</text>
						<block v-else>
							<text>{{ d.countdown_prefix }}</text>
							<text class="cd-num">{{ days }}</text>
							<text>{{ d.countdown_suffix }}</text>
						</block>
					</view>
					<view v-if="d.btn1_text || d.btn2_text" class="btns">
						<view v-if="d.btn1_text" class="btn" :style="'background:' + d.btn_bg + ';color:' + d.btn_color"
							@click="go(d.btn1_url)">{{ d.btn1_text }}</view>
						<view v-if="d.btn2_text" class="btn ghost" :style="'color:' + d.text_color + ';border-color:' + d.sub_color"
							@click="go(d.btn2_url)">{{ d.btn2_text }}</view>
					</view>
				</view>
				<view v-if="d.image" class="art" :style="'transform:rotate(' + (d.image_rotate || 0) + 'deg)'" @click="go(d.image_url)">
					<view class="frame" :class="'frame-' + (d.image_frame || 'none')">
						<image class="frame-img" :src="d.image" mode="aspectFill"></image>
						<block v-if="d.image_frame === 'envelope'">
							<view class="env-line"></view>
							<view class="env-line short"></view>
						</block>
					</view>
					<view v-if="markLines.length" class="mark" :style="'color:' + d.accent_color + ';border-color:' + d.accent_color">
						<text v-for="(l, i) in markLines" :key="i" class="mark-line">{{ l }}</text>
					</view>
				</view>
			</view>
		</view>
	</view>
</template>

<script>
	import { FONT_FAMILY, daysLeft, lines, goLink, clearOf } from '../tui-style-kit/style-kit.js';

	export default {
		name: 'tui-hero-card',
		props: {
			value: { type: Object, default: () => ({}) }
		},
		computed: {
			d() {
				return this.value || {};
			},
			days() {
				return daysLeft(this.d.countdown_date);
			},
			subLines() {
				return lines(this.d.subtitle, 3);
			},
			markLines() {
				return lines(this.d.mark_text, 3);
			},
			wrapStyle() {
				return 'padding:' + (this.d.is_padding == 1 ? '0 20rpx' : '0');
			},
			boxStyle() {
				const d = this.d;
				let bg = 'background:' + (d.bg_color || '#0e1a2b');
				if (d.bg_type === 'gradient') bg = 'background:linear-gradient(135deg,' + d.bg_color + ',' + d.bg_color2 + ')';
				if (d.bg_type === 'image' && d.bg_image) bg = 'background:url(' + d.bg_image + ') center/cover no-repeat ' + d.bg_color;
				return bg + ';border-radius:' + (d.is_circle == 1 ? '20rpx' : '0');
			},
			topFadeStyle() {
				const c = this.d.bg_color || '#0e1a2b';
				return 'background:linear-gradient(to bottom,' + c + ',' + clearOf(c) + ')';
			},
			glowStyle() {
				return 'background:radial-gradient(ellipse at 30% 60%,' + this.d.glow_color + ',transparent 60%)';
			},
			titleStyle() {
				return 'color:' + this.d.text_color + ';font-family:' + (FONT_FAMILY[this.d.title_font] || FONT_FAMILY.sans);
			}
		},
		methods: {
			go(url) {
				goLink(url);
			}
		}
	};
</script>

<style scoped>
	.hero { position: relative; overflow: hidden; padding: 32rpx 32rpx 44rpx; }
	.glow { position: absolute; left: -80rpx; right: -80rpx; top: -60rpx; height: 300rpx; filter: blur(12rpx); pointer-events: none; }
	.top-fade { position: absolute; left: 0; right: 0; top: 0; height: 120rpx; pointer-events: none; }
	.inner { position: relative; display: flex; flex-direction: row; }
	.copy { flex: 1; min-width: 0; }
	.eyebrow { display: block; font-size: 20rpx; letter-spacing: 4rpx; }
	.title { margin-top: 14rpx; font-size: 52rpx; font-weight: 900; line-height: 1.25; letter-spacing: 2rpx; }
	.t-line { display: block; }
	.sub { margin-top: 14rpx; font-size: 24rpx; line-height: 1.6; }
	.sub-line { display: block; }
	.countdown { display: inline-flex; flex-direction: row; align-items: baseline; margin-top: 22rpx; padding: 6rpx 18rpx; font-size: 22rpx; border: 2rpx dashed; border-radius: 6rpx; }
	/* 数字两边用间距隔开:H5 会吞掉 text 首尾空格 */
	.cd-num { font-size: 34rpx; font-weight: 800; margin: 0 8rpx; }
	.cd-today { font-size: 26rpx; font-weight: 800; letter-spacing: 2rpx; }
	.btns { margin-top: 26rpx; display: flex; flex-direction: row; }
	.btn { height: 68rpx; line-height: 68rpx; padding: 0 30rpx; border-radius: 34rpx; font-size: 26rpx; font-weight: 700; white-space: nowrap; margin-right: 16rpx; }
	.btn.ghost { background: transparent; border: 2rpx solid; font-weight: 500; }
	.art { position: relative; width: 240rpx; flex-shrink: 0; margin-top: 20rpx; margin-left: 20rpx; }
	.frame { position: relative; }
	.frame-img { display: block; width: 100%; height: 228rpx; }
	.frame-envelope { background: #d8b98a; padding: 14rpx; border-radius: 8rpx; box-shadow: 0 16rpx 36rpx rgba(0, 0, 0, 0.4); }
	.frame-envelope .frame-img { border: 5rpx solid #ffffff; box-sizing: border-box; }
	.env-line { height: 7rpx; border-radius: 4rpx; background: rgba(42, 37, 34, 0.25); margin-top: 12rpx; }
	.env-line.short { width: 60%; }
	.frame-polaroid { background: #ffffff; padding: 12rpx 12rpx 40rpx; box-shadow: 0 16rpx 36rpx rgba(0, 0, 0, 0.3); }
	.frame-stamp { background: #ffffff; padding: 12rpx; box-shadow: 0 12rpx 28rpx rgba(0, 0, 0, 0.25);
		-webkit-mask: linear-gradient(#000 0 0) content-box, radial-gradient(circle 6rpx at 9rpx 9rpx, transparent 96%, #000) -9rpx -9rpx / 18rpx 18rpx; }
	/* 印章收在卡片内:卡片 overflow:hidden,伸太出去旋转后会被裁掉一角 */
	.mark { position: absolute; right: -10rpx; top: -26rpx; width: 112rpx; height: 112rpx; border-radius: 50%; border: 4rpx solid;
		background: rgba(255, 255, 255, 0.92); display: flex; flex-direction: column; align-items: center; justify-content: center;
		transform: rotate(-16deg); }
	.mark-line { display: block; font-size: 16rpx; font-weight: 700; line-height: 1.2; text-align: center; }
</style>
