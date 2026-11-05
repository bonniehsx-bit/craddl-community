<template>
	<view :style="{ padding: value.is_padding == 1 ? '0 20rpx' : '0' }">
		<view style="overflow: hidden;overflow: hidden;box-sizing: border-box;position: relative;"
			:style="{ borderRadius: value.is_circle == 1 ? '20rpx' : '0' }">
			<view :style="{ backgroundColor: value.background }" style="padding: 0 20rpx;padding-bottom: 20rpx;">
				<view style="height: 80rpx;font-weight: bold;" class="flex-y-center" v-if="value.title">
					{{ value.title }}
				</view>
				<view>
					<video :poster="value.cover_pic" objectFit="fill" id="video" :src="value.video_url" autoplay="true"
						:style="{ height: value.height + 'rpx' }" style="width: 100%;" :autoplay="false" controls></video>
				</view>
			</view>
		</view>
	</view>
</template>

<script>
export default {
	props: {
		value: {
			type: Object,
			default: () => {
				return null
			}
		}
	},
	data() {
		return {

			context: null,
			is_play: false,
			animation: true
		}
	},
	mounted: function (e) {
		this.context = uni.createVideoContext("video", this);;
	},
	created: function (e) {

	},
	methods: {
		play() {
			this.is_play = true;

			this.context.play();
		},
	}
}
</script>

<style>
.container {
	padding-top: 120rpx;
}

.tui-notice-board {
	width: 100%;
	padding-right: 30rpx;
	box-sizing: border-box;
	font-size: 28rpx;
	height: 60rpx;

	display: flex;
	align-items: center;


	z-index: 999;
}

.tui-icon-bg {

	padding-left: 30rpx;
	position: relative;
	z-index: 10;
}

.tui-icon-class {
	margin-right: 12rpx;
}

.tui-scorll-view {
	flex: 1;
	line-height: 1;
	white-space: nowrap;
	overflow: hidden;
	color: #f54f46;
}

.tui-notice {
	-webkit-backface-visibility: hidden;
	-webkit-perspective: 1000;
	transform: translate3d(100%, 0, 0);
}

.tui-animation {
	-webkit-animation: tui-rolling 12s linear infinite;
	animation: tui-rolling 12s linear infinite;
}

@-webkit-keyframes tui-rolling {
	0% {
		transform: translate3d(100%, 0, 0);
	}

	100% {
		transform: translate3d(-170%, 0, 0);
	}
}

@keyframes tui-rolling {
	0% {
		transform: translate3d(100%, 0, 0);
	}

	100% {
		transform: translate3d(-170%, 0, 0);
	}
}

.tui-subject {
	padding: 100rpx 30rpx 30rpx 30rpx;
	box-sizing: border-box;
	font-size: 32rpx;
	font-weight: bold;
}

.tui-rolling-news {
	width: 100%;
	padding: 12rpx 30rpx;
	box-sizing: border-box;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-wrap: nowrap;
}

.tui-swiper {
	font-size: 28rpx;
	height: 50rpx;
	flex: 1;
}

.tui-swiper-item {
	display: flex;
	align-items: center
}

.news-item {
	line-height: 28rpx;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.tui-searchbox {
	padding: 60rpx 80rpx;
	box-sizing: border-box;
}

.rolling-search {
	width: 100%;
	height: 70rpx;
	border-radius: 10rpx;
	padding: 0 40rpx 0 30rpx;
	box-sizing: border-box;
	background: #fff;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-wrap: nowrap;
	color: #999;
}
</style>