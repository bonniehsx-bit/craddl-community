<template>
	<!-- 风格组件 · 卖点条:2–4 个「图标 + 短句」,如 正品保障 / 极速发货 -->
	<view v-if="items.length" :style="'padding:' + (d.is_padding == 1 ? '0 20rpx' : '0')">
		<view class="strip" :style="'background:' + d.bg_color + ';border-radius:' + (d.is_circle == 1 ? '16rpx' : '0')">
			<view v-for="(it, i) in items" :key="i" class="it" :style="'color:' + d.sub_color">
				<text class="ic" :style="'background:' + d.accent_color + ';color:' + d.btn_color">{{ it.icon }}</text>
				<text>{{ it.text }}</text>
			</view>
		</view>
	</view>
</template>

<script>
	export default {
		name: 'tui-feature-strip',
		props: {
			value: { type: Object, default: () => ({}) }
		},
		computed: {
			d() {
				return this.value || {};
			},
			items() {
				return (this.d.items || []).filter(it => it && it.text).slice(0, 4);
			}
		}
	};
</script>

<style scoped>
	.strip { display: flex; flex-direction: row; justify-content: space-around; padding: 20rpx 20rpx; }
	.it { display: flex; flex-direction: row; align-items: center; font-size: 22rpx; white-space: nowrap; }
	.ic { width: 32rpx; height: 32rpx; line-height: 32rpx; border-radius: 50%; font-size: 18rpx; text-align: center; margin-right: 8rpx; }
</style>
