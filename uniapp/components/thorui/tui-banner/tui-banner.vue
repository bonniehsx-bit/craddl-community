<template>
	<view class="diy-banner" :style="{ padding: value.is_padding == 1 ? '0 20rpx' : '0' }">
		
		<view class="banner-wrap" :style="{ borderRadius: value.is_circle == 1 ? '20rpx' : '0' }">
			
			<swiper 
				class="banner-swiper"
				:indicator-dots="false"
				@change="onChange"
				:autoplay="value.autoPlay"
				:interval="Number(value.interval) || 3000"
				:duration="500"
				:circular="true"
				:style="{ height: (value.height || 350) + 'rpx' }"
			>
				<block v-for="(item, index) in value.list" :key="index">
					<swiper-item @click="handleItemClick(item)">
						<view class="image-wrap">
							<image 
								:src="item.pic_url" 
								class="slide-image" 
								mode="aspectFill" 
							/>
							
							<view class="title-overlay" v-if="value.is_show_title == 1 && item.title">
								<text class="title-text">{{ item.title }}</text>
							</view>
						</view>
					</swiper-item>
				</block>
			</swiper>
			
			<!-- 指示点:小程序原生的只能改颜色,改不了形状,所以自己画。样式 / 位置由后台装修配置 -->
			<view v-if="showDots" class="dots" :class="'dots-' + dotPos + ' dots-' + dotStyle">
				<view v-if="dotStyle === 'num'" class="dot-num">{{ current + 1 }} / {{ value.list.length }}</view>
				<block v-else>
					<view v-for="(item, i) in value.list" :key="i" class="dot" :class="i === current ? 'on' : ''"></view>
				</block>
			</view>

			<view v-if="!hasList" class="empty-banner" :style="{ height: (value.height || 350) + 'rpx' }">
				<text>暂无轮播图片</text>
			</view>
			
		</view>
	</view>
</template>

<script>
	export default {
		name: "diy-banner",
		props: {
			value: {
				type: Object,
				default: () => ({
					height: 350,
					is_padding: 0,
					is_circle: 0,
					autoPlay: true,
					interval: 3000,
					dots: true, // 默认显示指示点
					is_show_title: 0,
					list: []
				})
			}
		},
		data() {
			return { current: 0 };
		},
		computed: {
			// pill 胶囊(默认) / dot 圆点 / num 数字
			dotStyle() {
				return ['pill', 'dot', 'num'].indexOf(this.value.dot_style) !== -1 ? this.value.dot_style : 'pill';
			},
			// 开了标题时底部是标题条,点放右下角避开标题
			dotPos() {
				if (this.value.is_show_title == 1) return 'right';
				return this.value.dot_position === 'right' ? 'right' : 'center';
			},
			hasList() {
				return this.value.list && this.value.list.length > 0;
			},
			// 后台装修「指示点」开关;旧数据没有这个字段按显示处理。只有一张图时点没有意义,不显示
			showDots() {
				return this.value.dots !== false && !!this.value.list && this.value.list.length > 1;
			}
		},
		watch: {
			// 后台删减图片后当前序号可能越界
			'value.list'(list) {
				if (!list || this.current >= list.length) this.current = 0;
			}
		},
		methods: {
			onChange(e) {
				this.current = e.detail.current;
			},
			handleItemClick(item) {
				// 1. 可以在这里做埋点统计
				// console.log('Banner clicked:', item);
				
				// 2. 跳转逻辑
				if (item.url) {
					// 判断是否是 tabBar 页面，如果是需用 switchTab，或者统一封装一个路由方法
					// 这里使用通用的 navigateTo，根据实际项目封装替换
					uni.navigateTo({
						url: item.url,
						fail: (err) => {
							// 如果 navigateTo 失败（例如跳到了 tabBar），尝试 switchTab
							uni.switchTab({ url: item.url });
						}
					});
				}
			}
		}
	};
</script>

<style scoped>
	.diy-banner {
		width: 100%;
		box-sizing: border-box;
	}

	.banner-wrap {
		position: relative;
		width: 100%;
		overflow: hidden;
		/* 消除某些机型上的圆角溢出bug */
		transform: translateY(0); 
	}

	.banner-swiper {
		width: 100%;
		/* 高度由 style 动态控制，这里默认给个值防止塌陷 */
		min-height: 100rpx;
	}

	.image-wrap {
		width: 100%;
		height: 100%;
		position: relative;
	}

	.slide-image {
		width: 100%;
		height: 100%;
		display: block;
		/* 确保图片填充整个区域 */
	}

	/* 标题遮罩样式 */
	.title-overlay {
		position: absolute;
		bottom: 0;
		left: 0;
		width: 100%;
		height: 60rpx;
		background: linear-gradient(to top, rgba(0,0,0,0.6), rgba(0,0,0,0));
		display: flex;
		align-items: center;
		padding: 0 20rpx;
		box-sizing: border-box;
	}

	.title-text {
		max-width: calc(100% - 140rpx); /* 右侧留给指示点 */
		color: #fff;
		font-size: 26rpx;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}
	
	/* 指示点 */
	.dots {
		position: absolute;
		bottom: 18rpx;
		display: flex;
		align-items: center;
		z-index: 2;
		pointer-events: none;
	}
	.dots-center { left: 0; right: 0; justify-content: center; }
	.dots-right { right: 24rpx; }
	/* 开了标题时底部 60rpx 是标题条,点落在标题条右侧,垂直居中 */
	.dots-right.dots-pill, .dots-right.dots-dot { bottom: 24rpx; }
	.dot {
		width: 12rpx;
		height: 12rpx;
		border-radius: 6rpx;
		margin: 0 5rpx;
		background: rgba(255, 255, 255, 0.5);
		/* 浅色图片上也看得清 */
		box-shadow: 0 0 4rpx rgba(0, 0, 0, 0.25);
	}
	.dot.on { background: #ffffff; }
	.dots-pill .dot.on { width: 32rpx; }
	.dots-dot .dot { width: 12rpx; }
	.dot-num {
		padding: 4rpx 16rpx;
		border-radius: 20rpx;
		background: rgba(0, 0, 0, 0.35);
		color: #ffffff;
		font-size: 22rpx;
		line-height: 1.5;
		letter-spacing: 2rpx;
	}

	/* 空状态样式 */
	.empty-banner {
		width: 100%;
		background-color: #f5f5f5;
		display: flex;
		align-items: center;
		justify-content: center;
		color: #999;
		font-size: 28rpx;
	}
</style>