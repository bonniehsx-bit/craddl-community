<template>
	<view class="letter-panel">
		<view class="head">
			<text class="title">有声来信</text>
			<text class="status" :class="'s' + data.status">{{ data.status_text }}</text>
		</view>

		<view class="rows">
			<view class="row"><text class="k">主题</text><text class="v">{{ data.theme_text }}</text></view>
			<view class="row"><text class="k">收信孩子</text><text class="v">{{ data.child_name }} · {{ data.child_age }} 岁</text></view>
			<view class="row" v-if="data.letter_date"><text class="k">信上日期</text><text class="v">{{ data.letter_date }}</text></view>
			<view class="row"><text class="k">中文字幕</text><text class="v">{{ data.subtitles ? '显示' : '不显示' }}</text></view>
		</view>

		<view class="deliver" v-if="data.delivery_url">
			<view class="deliver-tip">信已写好:复制链接,发到微信里打开收听</view>
			<view class="deliver-btn" :style="'background:var(--theme-color);'" @click="copy">复制链接</view>
		</view>
	</view>
</template>

<script>
export default {
	name: 'AidbLetterPanel',
	props: {
		data: { type: Object, required: true },
		order: { type: Object, default: () => ({}) },
		list: { type: Array, default: () => [] }
	},
	methods: {
		copy() {
			uni.setClipboardData({ data: this.data.delivery_url, success: () => uni.showToast({ title: '链接已复制', icon: 'none' }) });
		}
	}
};
</script>

<style lang="scss" scoped>
	.letter-panel { background: #fff; border-radius: 16rpx; padding: 24rpx; }
	.head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12rpx; }
	.title { font-size: 28rpx; font-weight: 600; color: #222; }
	.status { font-size: 24rpx; color: #e6a23c; }
	.status.s2 { color: var(--theme-color); }
	.row { display: flex; justify-content: space-between; padding: 10rpx 0; font-size: 26rpx; }
	.k { color: #999; }
	.v { color: #333; }
	.deliver { margin-top: 14rpx; padding-top: 16rpx; border-top: 1rpx dashed #eee; }
	.deliver-tip { font-size: 24rpx; color: #888; line-height: 1.6; margin-bottom: 14rpx; }
	.deliver-btn { display: inline-block; padding: 12rpx 40rpx; border-radius: 40rpx; color: #fff; font-size: 26rpx; }
</style>
