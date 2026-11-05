<template>
	<!-- 风格组件 · 联系卡:头像 + 标题说明 + 按钮(复制微信号 / 打电话 / 跳页面) -->
	<view :style="'padding:' + (d.is_padding == 1 ? '12rpx 20rpx' : '0')">
		<view class="cc" :style="'background:' + d.bg_color2 + ';border-color:' + d.accent_color">
			<view class="ava" :style="'background:' + d.accent_color">
				<image v-if="d.avatar" class="ava-img" :src="d.avatar" mode="aspectFill"></image>
				<text v-else>{{ d.avatar_text || '客' }}</text>
			</view>
			<view class="copy">
				<text class="t" :style="'color:' + d.text_color">{{ d.title }}</text>
				<text v-if="d.desc" class="s" :style="'color:' + d.sub_color">{{ d.desc }}</text>
			</view>
			<view v-if="d.btn_text" class="btn" :style="'background:' + d.btn_bg + ';color:' + d.btn_color" @click="act">{{ d.btn_text }}</view>
		</view>
	</view>
</template>

<script>
	import { goLink } from '../tui-style-kit/style-kit.js';

	export default {
		name: 'tui-contact-card',
		props: {
			value: { type: Object, default: () => ({}) }
		},
		computed: {
			d() {
				return this.value || {};
			}
		},
		methods: {
			act() {
				const v = String(this.d.value || '').trim();
				if (this.d.action === 'link') return goLink(v);
				if (!v) return uni.showToast({ title: '商家还没填联系方式', icon: 'none' });
				if (this.d.action === 'phone') {
					uni.makePhoneCall({ phoneNumber: v });
					return;
				}
				uni.setClipboardData({
					data: v,
					success: () => uni.showToast({ title: '已复制,去微信粘贴', icon: 'none' })
				});
			}
		}
	};
</script>

<style scoped>
	.cc { display: flex; flex-direction: row; align-items: center; padding: 28rpx; border-radius: 20rpx; border: 2rpx dashed; }
	.ava { width: 84rpx; height: 84rpx; border-radius: 50%; flex-shrink: 0; overflow: hidden; color: #ffffff; font-weight: 800; font-size: 32rpx;
		display: flex; align-items: center; justify-content: center; }
	.ava-img { width: 100%; height: 100%; }
	.copy { flex: 1; min-width: 0; margin: 0 20rpx; }
	.t { display: block; font-size: 26rpx; font-weight: 800; }
	.s { display: block; margin-top: 4rpx; font-size: 22rpx; line-height: 1.5; }
	.btn { height: 56rpx; line-height: 56rpx; padding: 0 22rpx; border-radius: 28rpx; font-size: 22rpx; font-weight: 700; white-space: nowrap; }
</style>
