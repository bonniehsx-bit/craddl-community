<template>
	<!-- 风格组件 · 视频卡:先只显示封面 + 播放键 + 叠在画面上的标题,点了才挂 <video> 播放,不拖慢首页 -->
	<view v-if="d.video_url || d.cover_pic" :style="'padding:' + (d.is_padding == 1 ? '12rpx 20rpx' : '0')">
		<view class="vc" :style="boxStyle">
			<video v-if="playing" class="player" :src="d.video_url" :poster="d.cover_pic" autoplay controls
				object-fit="cover" @ended="playing = false"></video>
			<view v-else class="cover" @click="play">
				<image v-if="d.cover_pic" class="cover-img" :src="d.cover_pic" mode="aspectFill"></image>
				<view class="shade" :style="'background:linear-gradient(180deg,transparent 35%,' + d.bg_color + ')'"></view>
				<view v-if="d.video_url" class="play">
					<view class="tri" :style="'border-left-color:' + d.accent_color"></view>
				</view>
				<view class="copy" :style="'color:' + d.text_color">
					<text class="t">{{ d.title }}</text>
					<text v-if="d.subtitle" class="s">{{ d.subtitle }}</text>
				</view>
			</view>
		</view>
	</view>
</template>

<script>
	export default {
		name: 'tui-video-card',
		props: {
			value: { type: Object, default: () => ({}) }
		},
		data() {
			return { playing: false };
		},
		computed: {
			d() {
				return this.value || {};
			},
			boxStyle() {
				return 'height:' + (Number(this.d.height) || 380) + 'rpx;border-radius:' + (this.d.is_circle == 1 ? '24rpx' : '0');
			}
		},
		methods: {
			play() {
				if (this.d.video_url) this.playing = true;
			}
		}
	};
</script>

<style scoped>
	.vc { position: relative; overflow: hidden; background: #1f2329; }
	.player, .cover { width: 100%; height: 100%; }
	.cover { position: relative; }
	.cover-img { width: 100%; height: 100%; display: block; }
	.shade { position: absolute; left: 0; right: 0; top: 0; bottom: 0; }
	.play { position: absolute; left: 50%; top: 42%; transform: translate(-50%, -50%); width: 92rpx; height: 92rpx; border-radius: 50%; background: rgba(255, 255, 255, 0.92); }
	.tri { position: absolute; left: 36rpx; top: 28rpx; width: 0; height: 0; border-left: 28rpx solid; border-top: 18rpx solid transparent; border-bottom: 18rpx solid transparent; }
	.copy { position: absolute; left: 28rpx; right: 28rpx; bottom: 24rpx; }
	.t { display: block; font-size: 32rpx; font-weight: 800; }
	.s { display: block; margin-top: 6rpx; font-size: 22rpx; opacity: 0.8; }
</style>
