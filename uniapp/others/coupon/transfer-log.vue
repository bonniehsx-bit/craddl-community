<template>
	<tui-page class="container">

		<view v-if="loading && list.length === 0" class="empty-state">
			<view class="skeleton-item" v-for="i in 5" :key="i"></view>
		</view>

		<view v-else-if="list.length === 0" class="empty-state">
			<image src="/static/icons/empty-box.png" class="empty-img" mode="aspectFit"></image>
			<text class="empty-text">暂无转赠记录</text>
		</view>

		<view v-else>
			<view class="log-item" v-for="item in list" :key="item.id">
				<!-- 左：方向标识 -->
				<view class="direction-badge" :class="item.type === 'sent' ? 'badge-sent' : 'badge-received'">
					<text class="badge-text">{{ item.type === 'sent' ? '转出' : '收到' }}</text>
				</view>

				<!-- 中：优惠券信息 -->
				<view class="coupon-info">
					<view class="coupon-name">{{ item.coupon_name }}</view>
					<view class="coupon-desc">{{ item.coupon_desc }}</view>
					<view class="counterpart-row">
						<image
							:src="item.counterpart.avatar_url || '/static/icons/avatar.png'"
							class="cp-avatar"
							mode="aspectFill"
						></image>
						<text class="cp-text">
							{{ item.type === 'sent' ? '转赠给' : '来自' }} {{ item.counterpart.nickname }}
						</text>
					</view>
				</view>

				<!-- 右：时间 -->
				<view class="time-col">
					<text class="time-text">{{ item.created_at }}</text>
				</view>
			</view>

			<view class="load-more" @click="loadMore" v-if="hasMore">
				<text>{{ loadingMore ? '加载中...' : '加载更多' }}</text>
			</view>
			<view class="no-more" v-else-if="list.length > 0">
				<text>— 已加载全部记录 —</text>
			</view>
		</view>

	</tui-page>
</template>

<script>
	export default {
		data() {
			return {
				list: [],
				page: 1,
				pageSize: 15,
				hasMore: false,
				loading: false,
				loadingMore: false,
			};
		},
		onLoad() {
			this.fetchLogs();
		},
		onPullDownRefresh() {
			this.page = 1;
			this.list = [];
			this.hasMore = false;
			this.fetchLogs().then(() => {
				uni.stopPullDownRefresh();
			});
		},
		methods: {
			fetchLogs() {
				if (this.page === 1) {
					this.loading = true;
				} else {
					this.loadingMore = true;
				}

				return this.$request({
					url: '/api/coupon/transfer-logs',
					data: { page: this.page, page_size: this.pageSize }
				}).then(res => {
					if (res.code === 0) {
						if (this.page === 1) {
							this.list = res.data.list;
						} else {
							this.list = this.list.concat(res.data.list);
						}
						this.hasMore = res.data.has_more;
					}
				}).finally(() => {
					this.loading = false;
					this.loadingMore = false;
				});
			},
			loadMore() {
				if (this.loadingMore || !this.hasMore) return;
				this.page++;
				this.fetchLogs();
			}
		}
	};
</script>

<style lang="scss" scoped>
	.container {
		padding: 20rpx 24rpx;
		background-color: #f7f7f7;
		min-height: 100vh;
	}

	/* 骨架屏 */
	.skeleton-item {
		height: 160rpx;
		background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
		border-radius: 16rpx;
		margin-bottom: 20rpx;
		animation: shimmer 1.4s infinite;
	}

	@keyframes shimmer {
		0%   { background-position: -400rpx 0; }
		100% { background-position: 400rpx 0; }
	}

	/* 空状态 */
	.empty-state {
		padding-top: 160rpx;
		display: flex;
		flex-direction: column;
		align-items: center;

		.empty-img {
			width: 240rpx;
			height: 240rpx;
			opacity: 0.5;
			margin-bottom: 24rpx;
		}
		.empty-text {
			font-size: 28rpx;
			color: #999;
		}
	}

	/* 记录条目 */
	.log-item {
		display: flex;
		align-items: center;
		background: #fff;
		border-radius: 16rpx;
		padding: 28rpx 24rpx;
		margin-bottom: 20rpx;
		box-shadow: 0 2rpx 10rpx rgba(0,0,0,0.04);
	}

	/* 方向徽标 */
	.direction-badge {
		width: 80rpx;
		height: 80rpx;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
		margin-right: 24rpx;

		&.badge-sent {
			background-color: #fff1f1;
			.badge-text { color: #E22729; }
		}
		&.badge-received {
			background-color: #f0fff4;
			.badge-text { color: #19BE6B; }
		}

		.badge-text {
			font-size: 22rpx;
			font-weight: bold;
		}
	}

	/* 优惠券信息 */
	.coupon-info {
		flex: 1;
		min-width: 0;

		.coupon-name {
			font-size: 28rpx;
			font-weight: bold;
			color: #333;
			margin-bottom: 6rpx;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}
		.coupon-desc {
			font-size: 22rpx;
			color: #E22729;
			margin-bottom: 10rpx;
		}
		.counterpart-row {
			display: flex;
			align-items: center;
			.cp-avatar {
				width: 36rpx;
				height: 36rpx;
				border-radius: 50%;
				margin-right: 10rpx;
				background-color: #eee;
			}
			.cp-text {
				font-size: 22rpx;
				color: #999;
			}
		}
	}

	/* 时间 */
	.time-col {
		flex-shrink: 0;
		margin-left: 16rpx;
		.time-text {
			font-size: 22rpx;
			color: #bbb;
		}
	}

	/* 加载更多 */
	.load-more, .no-more {
		text-align: center;
		padding: 30rpx 0 20rpx;
		font-size: 24rpx;
		color: #999;
	}
	.load-more {
		color: #E22729;
	}
</style>
