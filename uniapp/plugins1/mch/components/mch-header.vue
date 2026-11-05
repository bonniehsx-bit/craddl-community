<template>
	<view class="header-section">
		<!-- 背景模糊层 -->
		<view class="header-bg">
			<image class="bg-img" :src="bannerImages[0]" mode="aspectFill"></image>
			<view class="bg-mask"></view>
		</view>

		<view class="header-actions" :style="{paddingTop: (statusBarHeight + 10) + 'px'}">
			<view class="action-btn blur-bg" @click.stop="$emit('back')">
				<tui-icon name="arrowleft" color="#fff" :size="20"></tui-icon>
			</view>
			<view class="action-btn blur-bg" @click.stop="$emit('favorite')">
				<tui-icon :name="isFavorite ? 'star-fill' : 'star'"
					:color="isFavorite ? '#FFC107' : '#fff'" :size="20"></tui-icon>
			</view>
		</view>

		<view class="merchant-info-card shadow-card">
			<view class="card-top">
				<image class="avatar" :src="merchant.logo" mode="aspectFill"></image>
				<view class="details">
					<view class="name u-line-1">{{ merchant.mch_name }}</view>
					
					<view class="rating-row">
						<view class="stars">
							<tui-icon name="star-fill" size="12" color="#FFC107" v-for="i in 5" :key="i"></tui-icon>
							<text class="rating-score">{{ merchant.rating || '5.0' }}</text>
						</view>
						<view class="time-tag" v-if="merchant.opening_hours">
							<tui-icon name="clock" :size="10" color="#3B82F6" style="margin-right: 4rpx;"></tui-icon>
							{{ merchant.opening_hours.split('-')[1] ? '营业至 ' + merchant.opening_hours.split('-')[1] : merchant.opening_hours }}
						</view>
					</view>

					<view class="tags">
						<view class="tag service" v-for="(service, index) in formattedServices" :key="index">
							{{ service }}
						</view>
						<view class="tag delivery" v-if="merchant.delivery_time">{{ merchant.delivery_time }}分钟送达</view>
					</view>
					
					<view class="notice" v-if="merchant.description">
						<tui-icon name="notice" size="12" color="#FF8A00"></tui-icon>
						<text class="notice-text">{{ merchant.description }}</text>
					</view>
				</view>
			</view>

			<view class="card-bottom">
				<view class="location-row" @click.stop="$emit('location')">
					<tui-icon name="position" size="14" color="#8A8F99" style="flex-shrink: 0;"></tui-icon>
					<text class="address-text u-line-1">{{ merchant.address || merchant.location_address }}</text>
				</view>
				
				<view class="action-icons">
					<view class="icon-circle call" v-if="merchant.mobile || merchant.tel" @click.stop="$emit('call')">
						<tui-icon name="voipphone" size="16" color="#059669"></tui-icon>
					</view>
					<view class="icon-circle nav" v-if="merchant.latitude && merchant.longitude" @click.stop="$emit('location')">
						<tui-icon name="explore" size="16" color="#3B82F6"></tui-icon>
					</view>
				</view>
			</view>

		</view>
	</view>
</template>

<script>
	export default {
		name: "mch-header",
		props: {
			// 商家信息对象
			merchant: {
				type: Object,
				default: () => ({})
			},
			// 是否收藏
			isFavorite: {
				type: Boolean,
				default: false
			},
			// 状态栏高度（用于适配刘海屏）
			statusBarHeight: {
				type: Number,
				default: 20
			}
		},
		computed: {
			// 处理 Banner 图片逻辑
			bannerImages() {
				const banners = this.merchant.banner_images;
				if (banners && banners.length > 0) return banners;
				return ['/static/images/default-banner.png']; // 请确保有默认图
			},
			formattedServices() {
				const map = {
					'express': '快递发送',
					'pickup': '到店自提',
					'city': '同城配送',
				};
				let services = this.merchant.delivery_options || [];
				return services.map(s => map[s] || s);
			}
		},
		emits: ['back', 'favorite', 'cashier']
	}
</script>

<style lang="scss" scoped>
	/* 通用阴影 */
	.shadow-card {
		background: #fff;
		border-radius: 20rpx;
		box-shadow: 0 8rpx 30rpx rgba(0, 0, 0, 0.04);
	}

	.header-section {
		position: relative;
		flex-shrink: 0;
		z-index: 1;
		padding-bottom: 20rpx;

		.header-bg {
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			height: 380rpx;
			overflow: hidden;
			z-index: 1;

			.bg-img {
				width: 100%;
				height: 100%;
				transform: scale(1.1); /* 防止模糊边缘露底 */
				filter: blur(20px);
			}

			.bg-mask {
				position: absolute;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				background: linear-gradient(to bottom, rgba(0,0,0,0.4) 0%, rgba(247,248,250,1) 100%);
			}
		}

		.header-actions {
			position: relative;
			padding-left: 24rpx;
			padding-bottom: 30rpx;
			z-index: 20;
			display: flex;
			gap: 24rpx;

			.action-btn {
				width: 64rpx;
				height: 64rpx;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;

				&.blur-bg {
					background: rgba(0, 0, 0, 0.25);
					backdrop-filter: blur(4px);
					border: 1rpx solid rgba(255, 255, 255, 0.1);
				}
			}
		}

		.merchant-info-card {
			position: relative;
			background: #fff;
			margin: 0 24rpx;
			padding: 30rpx;
			border-radius: 24rpx;
			box-shadow: 0 8rpx 30rpx rgba(0, 0, 0, 0.06);
			z-index: 15;

			.card-top {
				display: flex;
				align-items: flex-start;
				margin-bottom: 20rpx;
			}

			.avatar {
				width: 120rpx;
				height: 120rpx;
				border-radius: 16rpx;
				margin-right: 24rpx;
				border: 1rpx solid #f2f2f2;
				flex-shrink: 0;
			}

			.details {
				flex: 1;
				min-width: 0;

				.name {
					font-size: 34rpx;
					font-weight: bold;
					color: #2B2F3A;
					margin-bottom: 8rpx;
					line-height: 1.3;
				}

				.rating-row {
					display: flex;
					align-items: center;
					justify-content: space-between;
					margin-bottom: 12rpx;

					.stars {
						display: flex;
						align-items: center;

						.rating-score {
							font-size: 22rpx;
							color: #FFC107;
							margin-left: 8rpx;
							font-weight: bold;
						}
					}

					.time-tag {
						font-size: 20rpx;
						color: #3B82F6;
						background-color: #EFF6FF;
						padding: 4rpx 12rpx;
						border-radius: 6rpx;
						display: flex;
						align-items: center;
					}
				}

				.tags {
					display: flex;
					flex-wrap: wrap;
					gap: 12rpx;
					margin-bottom: 10rpx;

					.tag {
						font-size: 20rpx;
						color: #64748B;
						background: #F1F5F9;
						padding: 4rpx 12rpx;
						border-radius: 6rpx;

						&.delivery {
							color: #FF8A00;
							background: #FFF6ED;
						}
					}
				}

				.notice {
					display: flex;
					align-items: center;
					gap: 8rpx;

					.notice-text {
						font-size: 22rpx;
						color: #FF8A00;
						flex: 1;
						white-space: nowrap;
						overflow: hidden;
						text-overflow: ellipsis;
					}
				}
			}

			.card-bottom {
				display: flex;
				align-items: center;
				justify-content: space-between;
				padding-top: 20rpx;
				border-top: 1rpx dashed #eee;

				.location-row {
					flex: 1;
					display: flex;
					align-items: center;
					background: #F8FAFC;
					padding: 12rpx 16rpx;
					border-radius: 12rpx;
					margin-right: 20rpx;
					transition: background 0.2s;
					
					&:active {
						background: #F1F5F9;
					}

					.address-text {
						font-size: 24rpx;
						color: #64748B;
						margin-left: 8rpx;
						flex: 1;
					}
				}

				.action-icons {
					display: flex;
					gap: 16rpx;

					.icon-circle {
						width: 56rpx;
						height: 56rpx;
						border-radius: 50%;
						display: flex;
						align-items: center;
						justify-content: center;
						transition: transform 0.2s;

						&:active {
							transform: scale(0.9);
						}

						&.call {
							background: #D1FAE5;
						}

						&.nav {
							background: #DBEAFE;
						}
					}
				}
			}
		}
	}
</style>