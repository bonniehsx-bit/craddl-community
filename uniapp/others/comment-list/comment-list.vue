<template>
	<tui-page :is-pulldown="true">
		<view class="page-container" :style="{ '--theme-color': themeColor }">
			<view class="summary-header">
				<view class="summary-title">商品评价 ({{ total }})</view>
				<view class="summary-rating">
					<text class="score" :style="{ color: themeColor }">{{ sum }}</text>
					<tui-rate disabled :current="Math.round(Number(sum || 0))" :active="themeColor" :quantity="5" :size="36"></tui-rate>
				</view>
			</view>

			<view class="comment-list" v-if="list.length > 0">
				<view class="comment-card" v-for="item in list" :key="item.id">
					<view class="user-info">
						<image :src="item.avatar_url" mode="aspectFill" class="avatar" />
						<view class="user-details">
							<view class="nickname">{{ item.nickname }}</view>
							<view class="meta-info">
								<tui-rate disabled :current="item.grade_level" :active="themeColor" :quantity="5" :size="28"></tui-rate>
								<text class="time">{{ item.created_time }}</text>
							</view>
						</view>
					</view>

					<view class="comment-content">
						<text class="content-text">{{ item.content || '该用户未填写评价内容' }}</text>
						<view class="image-grid" v-if="item.pic_list && item.pic_list.length > 0">
							<view class="image-wrapper" v-for="(imgUrl, imgIndex) in item.pic_list" :key="imgIndex">
								<image :src="imgUrl" mode="aspectFill" class="grid-image"
									@click="previewImg(imgUrl, item.pic_list)"></image>
							</view>
						</view>
					</view>

					<view class="follow-up-comment" v-if="item.second">
						<view class="follow-up-header">
							<view class="theme-tag" :style="{ color: themeColor, borderColor: themeColor }">追评</view>
							<text class="follow-up-time">{{ item.second.created_time }}</text>
						</view>
						<view class="comment-content">
							<text class="content-text">{{ item.second.content || '该用户未填写追评内容' }}</text>
							<view class="image-grid" v-if="item.second.pic_list && item.second.pic_list.length > 0">
								<view class="image-wrapper" v-for="(imgUrl, imgIndex) in item.second.pic_list"
									:key="imgIndex">
									<image :src="imgUrl" mode="aspectFill" class="grid-image"
										@click="previewImg(imgUrl, item.second.pic_list)"></image>
								</view>
							</view>
						</view>
					</view>
				</view>
			</view>

			<view class="no-lists" v-else-if="!loading">
				<tui-no-data :fixed="false">暂无任何评价</tui-no-data>
			</view>

			<tui-loadmore v-if="loading"></tui-loadmore>
			<tui-nomore v-if="is_no_more && list.length > 0"></tui-nomore>

		</view>
	</tui-page>
</template>

<script>
	import { mapState } from 'vuex';

	export default {
		data() {
			return {
				page: 1,
				list: [],
				goods_id: '',
				sum: 0,
				total: 0,
				is_no_more: false,
				loading: false
			}
		},
		computed: {
			// 获取全局主题色
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F'
			})
		},
		onLoad: function(options) {
			if (options.goods_id) {
				this.goods_id = options.goods_id;
			} else {
				uni.showModal({
					title: '提示',
					content: '商品不存在',
					showCancel: false,
					success: () => {
						uni.navigateBack({
							delta: 1
						});
					}
				})
				return;
			}
			this.getList(true);
		},
		onPullDownRefresh() {
			this.getList(true);
		},
		onReachBottom() {
			if (!this.is_no_more) {
				this.getList(false);
			}
		},
		methods: {
			previewImg(src, urls) {
				uni.previewImage({
					current: src,
					urls
				})
			},
			getList(isRefresh = false) {
				if (isRefresh) {
					this.page = 1;
					this.is_no_more = false;
				}
				if (this.is_no_more || this.loading) {
					if (isRefresh) uni.stopPullDownRefresh();
					return;
				}

				this.loading = true;

				this.$request({
					url: '/api/goods/comment-list',
					data: {
						goods_id: this.goods_id,
						page: this.page
					}
				}).then((res) => {
					if (res.code == 0) {
						this.sum = res.data.sum;
						this.total = res.data.total;

						const newList = res.data.list.map(item => {
							try {
								item.pic_list = JSON.parse(item.pic_list) || [];
							} catch (e) {
								item.pic_list = [];
							}
							if (item.second) {
								try {
									item.second.pic_list = JSON.parse(item.second.pic_list) || [];
								} catch (e) {
									item.second.pic_list = [];
								}
							}
							return item;
						});

						if (isRefresh) {
							this.list = newList;
						} else {
							this.list = this.list.concat(newList);
						}

						if (res.data.pagination.page_count > this.page) {
							this.page++;
						} else {
							this.is_no_more = true;
						}
					} else {
						this.is_no_more = true;
					}
				}).finally(() => {
					this.loading = false;
					if (isRefresh) uni.stopPullDownRefresh();
				});
			},
		}
	};
</script>

<style lang="scss" scoped>
	page {
		background-color: #F7F8FA;
	}

	.page-container {
		padding-bottom: 40rpx;
	}

	.summary-header {
		background-color: #FFFFFF;
		padding: 30rpx;
		margin-bottom: 24rpx;
		border-bottom: 1rpx solid #F0F0F0;

		.summary-title {
			font-size: 32rpx;
			font-weight: bold;
			color: #333;
		}

		.summary-rating {
			display: flex;
			align-items: center;
			margin-top: 20rpx;

			.score {
				font-size: 32rpx;
				font-weight: bold;
				margin-right: 16rpx;
			}
		}
	}

	.comment-list {
		padding: 0 24rpx;
	}

	.comment-card {
		background-color: #FFFFFF;
		border-radius: 16rpx;
		padding: 30rpx;
		margin-bottom: 24rpx;
	}

	.user-info {
		display: flex;
		align-items: center;

		.avatar {
			width: 80rpx;
			height: 80rpx;
			border-radius: 50%;
			flex-shrink: 0;
			background-color: #f5f5f5;
		}

		.user-details {
			margin-left: 20rpx;
			flex: 1;

			.nickname {
				font-size: 30rpx;
				font-weight: 500;
				color: #333;
			}

			.meta-info {
				display: flex;
				align-items: center;
				margin-top: 8rpx;

				.time {
					font-size: 24rpx;
					color: #999;
					margin-left: 16rpx;
				}
			}
		}
	}

	.comment-content {
		margin-top: 24rpx;

		.content-text {
			font-size: 28rpx;
			color: #333;
			line-height: 1.6;
			word-break: break-all;
		}
	}

	.image-grid {
		display: flex;
		flex-wrap: wrap;
		gap: 16rpx;
		margin-top: 20rpx;

		.image-wrapper {
			width: calc((100% - 32rpx) / 3);
			height: 0;
			padding-bottom: calc((100% - 32rpx) / 3);
			position: relative;

			.grid-image {
				position: absolute;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				border-radius: 12rpx;
				background-color: #f5f5f5;
			}
		}
	}

	.follow-up-comment {
		background-color: #F7F8FA;
		border-radius: 12rpx;
		padding: 24rpx;
		margin-top: 24rpx;

		.follow-up-header {
			display: flex;
			align-items: center;
			gap: 16rpx;

			/* 自定义追评标签样式 */
			.theme-tag {
				font-size: 20rpx;
				padding: 4rpx 14rpx;
				border-radius: 100rpx;
				border: 1rpx solid;
				background-color: rgba(255, 255, 255, 0.6);
			}

			.follow-up-time {
				font-size: 24rpx;
				color: #999;
			}
		}

		.comment-content {
			margin-top: 16rpx;
		}
	}

	.no-lists {
		padding-top: 20vh;
	}
</style>