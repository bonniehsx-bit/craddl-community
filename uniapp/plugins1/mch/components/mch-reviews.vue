<template>
	<scroll-view scroll-y class="mch-scroll-view">
		<view class="reviews-section">
			
			<view class="summary-card shadow-card">
				<view class="score-left">
					<view class="score" :style="{color: themeColor}">{{ merchant.rating || '5.0' }}</view>
					<view class="label">商家评分</view>
				</view>
				<view class="score-right">
					<view class="stars-row">
						<text>综合体验</text>
						<tui-rate disabled :current="Math.round(Number(merchant.rating || 0))" :active="themeColor" :quantity="5" :size="28"></tui-rate>
					</view>
					<view class="good-rate">
						好评率 <text :style="{color: themeColor, fontWeight: 'bold', marginLeft: '8rpx'}">{{ merchant.good_rating_rate || 100 }}%</text>
					</view>
				</view>
			</view>
			
			<view class="review-list">
				<tui-no-data v-if="!list.length" :fixed="false">暂无评价</tui-no-data>
				
				<view class="review-item" v-for="(review, index) in list" :key="index">
					<image class="user-avatar" :src="review.avatar" mode="aspectFill"></image>
					<view class="review-main">
						<view class="header-row">
							<view class="user-name">{{ review.user_name }}</view>
							<view class="date">{{ review.date }}</view>
						</view>
						
						<tui-rate disabled :current="review.grade_level" :size="24" :active="themeColor" :quantity="5"></tui-rate>
						
						<view class="content-text">{{ review.content }}</view>
						
						<view class="review-images" v-if="review.images && review.images.length > 0">
							<image class="review-img" 
								v-for="(img, imgIndex) in review.images" 
								:key="imgIndex" 
								:src="img" 
								mode="aspectFill" 
								@click.stop="previewImage(review.images, imgIndex)">
							</image>
						</view>
						
						<view class="purchased-tag" v-if="review.purchased">
							<tui-icon name="bag" size="12" color="#999"></tui-icon>
							<text>{{ review.purchased }}</text>
						</view>
					</view>
				</view>
				
				<tui-loadmore v-if="list.length > 0" :index="3" type="primary" :text="loadStatus === 'nomore' ? '没有更多了' : '加载中...'"></tui-loadmore>
				<view class="bottom-spacer"></view>
			</view>
		</view>
	</scroll-view>
</template>

<script>
	export default {
		name: "mch-reviews",
		props: {
			merchant: {
				type: Object,
				default: () => ({})
			},
			list: {
				type: Array,
				default: () => []
			},
			loadStatus: {
				type: String,
				default: 'loadmore'
			}
		},
		methods: {
			previewImage(urls, index) {
				uni.previewImage({
					current: urls[index],
					urls: urls
				});
			}
		}
	}
</script>

<style lang="scss" scoped>
	.mch-scroll-view {
		height: 100%;
		background-color: #f7f8fa;
	}

	.reviews-section {
		padding: 24rpx;
		
		.summary-card {
			background: #fff;
			border-radius: 16rpx;
			padding: 40rpx 30rpx;
			margin-bottom: 24rpx;
			display: flex;
			align-items: center;
			box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.04);
			
			.score-left {
				text-align: center;
				padding-right: 50rpx;
				border-right: 1rpx solid #eee;
				
				.score {
					font-size: 64rpx;
					font-weight: bold;
					line-height: 1;
				}
				.label {
					font-size: 24rpx;
					color: #999;
					margin-top: 12rpx;
				}
			}
			
			.score-right {
				padding-left: 50rpx;
				flex: 1;
				
				.stars-row {
					display: flex;
					align-items: center;
					gap: 20rpx;
					font-size: 28rpx;
					color: #333;
					margin-bottom: 16rpx;
				}
				.good-rate {
					font-size: 26rpx;
					color: #666;
				}
			}
		}
		
		.review-item {
			background: #fff;
			padding: 30rpx;
			border-radius: 16rpx;
			margin-bottom: 20rpx;
			display: flex;
			gap: 24rpx;
			
			.user-avatar {
				width: 80rpx;
				height: 80rpx;
				border-radius: 50%;
				flex-shrink: 0;
				background: #f0f0f0;
			}
			
			.review-main {
				flex: 1;
				min-width: 0;
				
				.header-row {
					display: flex;
					justify-content: space-between;
					align-items: center;
					margin-bottom: 8rpx;
					
					.user-name { font-size: 28rpx; color: #333; font-weight: 500; }
					.date { font-size: 22rpx; color: #ccc; }
				}
				
				.content-text {
					font-size: 28rpx;
					color: #333;
					line-height: 1.6;
					margin: 16rpx 0;
					word-break: break-all;
				}
				
				.review-images {
					display: flex;
					flex-wrap: wrap;
					gap: 12rpx;
					margin-bottom: 16rpx;
					
					.review-img {
						width: 160rpx;
						height: 160rpx;
						border-radius: 8rpx;
						background: #f5f5f5;
					}
				}
				
				.purchased-tag {
					display: inline-flex;
					align-items: center;
					gap: 8rpx;
					background: #f8f8f8;
					padding: 6rpx 16rpx;
					border-radius: 8rpx;
					font-size: 22rpx;
					color: #999;
				}
			}
		}

		.bottom-spacer {
			height: calc(env(safe-area-inset-bottom) + 120rpx);
			width: 100%;
		}
	}
</style>