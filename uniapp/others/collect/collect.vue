<template>
	<tui-page>
		<view class="favorite-page-container">
			<view class="tabs-container">
				<view v-for="(tab, index) in tabs" :key="index" class="tab-item"
					:class="{'is-active': activeTab === index}" @click="switchTab(index)">
					{{ tab.name }} ({{ tab.count }})
				</view>
			</view>

			<swiper class="swiper-container" :current="activeTab" @change="handleSwiperChange">
				<swiper-item>
					<scroll-view class="scroll-view-container" scroll-y @scrolltolower="onReachBottom"
						@refresherrefresh="onPullDownRefresh" :refresher-enabled="true"
						:refresher-triggered="isRefreshing">
						<view v-if="goodsList.length > 0" class="list-wrapper">
							<view class="favorite-card product-card" v-for="(item, index) in goodsList"
								:key="item.favorite_id">
								<view class="card-main" @click="navigateToProduct(item.id)">
									<view class="image-wrapper">
										<image :src="item.image_url" class="product-image" mode="aspectFill"></image>
									</view>
									<view class="info-container">
										<text class="name">{{ item.name }}</text>
										<view class="price-row">
											<text class="price-symbol">¥</text>
											<text class="price-current">{{ parseFloat(item.price_current)  }}</text>
											<text class="price-original">¥{{ parseFloat(item.price_original)  }}</text>
										</view>
									</view>
								</view>
								<view class="delete-icon" @click="handleDelete(item, 'goods', index)">
									<uni-icons type="trash" size="20" color="#999"></uni-icons>
								</view>
							</view>
						</view>
						<view class="status-container">
							<tui-nomore v-if="pagination.goods.noMore && goodsList.length > 0"
								text="没有更多了"></tui-nomore>
							<tui-no-data v-if="goodsList.length === 0 && !loading" :fixed="false">暂无商品收藏</tui-no-data>
							<tui-loadmore v-if="loading && activeTab === 0"></tui-loadmore>
						</view>
					</scroll-view>
				</swiper-item>

				<swiper-item>
					<scroll-view class="scroll-view-container" scroll-y @scrolltolower="onReachBottom"
						@refresherrefresh="onPullDownRefresh" :refresher-enabled="true"
						:refresher-triggered="isRefreshing">
						<!-- [OPTIMIZED] New Merchant List UI -->
						<view v-if="merchantList.length > 0" class="list-wrapper">
							<view class="merchant-card-new" v-for="(item, index) in merchantList"
								:key="item.favorite_id">
								<!-- "距您最近" Tag - Add v-if="item.is_nearest" when backend supports it -->
								<view class="nearest-tag" v-if="index === 0">距您最近</view>

								<view class="card-main-new" @click="navigateToMch(item.id)">
									<image :src="item.logo_url" class="logo-new" mode="aspectFill"></image>
									<view class="info-section-new">
										<view class="name-new">{{ item.name }}</view>
										<view class="category-new">商家分类: {{ item.category || '暂无分类' }}</view>
										<view class="rating-new">
											<uni-icons type="star-filled" color="#FFB800" size="16"></uni-icons>
											<text>{{ item.rating || '4.5' }}分</text>
										</view>
										<view class="address-row-new">
											<uni-icons type="location" size="14" color="#999"></uni-icons>
											<text class="address-text-new">{{ item.address || '暂无地址' }}</text>
											<text class="distance-new">距您{{ item.distance|| '1' }}</text>
										</view>
									</view>
								</view>
								<view class="delete-icon-new" @click="handleDelete(item, 'mch', index)">
									<uni-icons type="trash" size="22" color="#999"></uni-icons>
								</view>
							</view>
						</view>
						<view class="status-container">
							<tui-nomore v-if="pagination.merchant.noMore && merchantList.length > 0"
								text="没有更多了"></tui-nomore>
							<tui-no-data v-if="merchantList.length === 0 && !loading"
								:fixed="false">暂无商家收藏</tui-no-data>
							<tui-loadmore v-if="loading && activeTab === 1"></tui-loadmore>
						</view>
					</scroll-view>
				</swiper-item>
			</swiper>
		</view>

	</tui-page>
</template>

<script>
	export default {
		data() {
			return {
				activeTab: 0,
				tabs: [{
						name: '商品收藏',
						count: 0
					},
					{
						name: '商家收藏',
						count: 0
					}
				],
				goodsList: [],
				merchantList: [],
				loading: false,
				isRefreshing: false,
				type: 'goods',
				pagination: {
					goods: {
						page: 1,
						limit: 10,
						noMore: false
					},
					merchant: {
						page: 1,
						limit: 10,
						noMore: false
					}
				}
			};
		},
		onLoad() {

			this.loadDataForCurrentTab(true);
		},
		methods: {
			async onPullDownRefresh() {
				this.isRefreshing = true;
				await this.loadDataForCurrentTab(true);
				this.isRefreshing = false;
				uni.stopPullDownRefresh();
			},
			async loadDataForCurrentTab(isRefresh = false) {
				if (this.activeTab === 0) {
					this.type = 'goods';
					await this.getList('goods', isRefresh);

				} else {
					this.type = 'merchant';
					await this.getList('merchant', isRefresh);
				}
			},
			switchTab(index) {
				if (this.activeTab === index) return;
				this.activeTab = index;
			},
			handleSwiperChange(e) {
				const index = e.detail.current;
				this.activeTab = index;
				if (this.activeTab == 0) {
					this.type = 'goods';
				}
				if (this.activeTab == 1) {
					this.type = 'merchant';
				}
				const currentList = this.activeTab === 0 ? this.goodsList : this.merchantList;
				if (currentList.length === 0) {
					this.loadDataForCurrentTab(true);
				}
			},

			async getList(type, isRefresh = false) {
				const pg = this.pagination[this.type];

				if (isRefresh) {
					pg.page = 1;
					pg.noMore = false;
				}
				if (pg.noMore || this.loading) return;
				this.loading = true;

				let location = uni.getStorageSync('location')
				if (!location) {
					location = {}
				}

				try {
					const res = await this.$request({
						url: "/api/favorite/list",
						data: {

							tab_type: this.type,
							page: pg.page,
							limit: pg.limit,
							...location,
						}
					});

					if (res.code === 0) {
						this.tabs = res.data.tabs;
						const {
							list,
							total
						} = res.data;
						const targetListKey = this.type === 'goods' ? 'goodsList' : 'merchantList';

						if (isRefresh) {
							this[targetListKey] = list;
						} else {
							this[targetListKey] = this[targetListKey].concat(list);
						}

						pg.noMore = this[targetListKey].length >= total;
						if (!pg.noMore) pg.page++;
					} else {
						pg.noMore = true;
					}
				} finally {
					this.loading = false;
				}
			},

			handleDelete(item, type, index) {
				uni.showModal({
					title: '确认',
					content: `您确定要取消收藏“${item.name}”吗？`,
					confirmColor: '#E43D3D',
					success: async (res) => {
						if (res.confirm) {
							uni.showLoading({
								title: '操作中...'
							});
							try {
								const toggleUrl = type === 'goods' ? '/api/goods/favorite' :
									'/plugin/mch/api/user/favorite';
								const idKey = type === 'goods' ? 'goods_id' : 'mch_id';

								const apiRes = await this.$request({
									url: toggleUrl,
									method: 'post',
									data: {
										[idKey]: item.id
									}
								});

								if (apiRes.code === 0) {
									if (type === 'goods') {
										this.goodsList.splice(index, 1);
										this.tabs[0].count = Math.max(0, this.tabs[0].count - 1);
									} else {
										this.merchantList.splice(index, 1);
										this.tabs[1].count = Math.max(0, this.tabs[1].count - 1);
									}
									uni.showToast({
										title: '已取消收藏',
										icon: 'success'
									});
								} else {
									uni.showToast({
										title: apiRes.msg,
										icon: 'none'
									});
								}
							} finally {
								uni.hideLoading();
							}
						}
					}
				});
			},
			onReachBottom() {
				this.loadDataForCurrentTab(false);
			},
			navigateToProduct(goodsId) {
				this.$T.go(`/others/goods/goods?id=${goodsId}`);
			},
			navigateToMch(mchId) {
				this.$T.go(`/plugins1/mch/index?id=${mchId}`);
			}
		}
	}
</script>

<style lang="scss">
	page {
		height: 100%;
		background-color: #F5F6FA;
	}

	.favorite-page-container {
		display: flex;
		flex-direction: column;
		height: 100vh;
	}

	.tabs-container {
		position: sticky;
		top: 0;
		left: 0;
		right: 0;
		z-index: 99;
		display: flex;
		background-color: #fff;
		border-bottom: 1rpx solid #f5f5f5;
		/* #ifdef H5 */
		top: var(--window-top);
		/* #endif */

		.tab-item {
			flex: 1;
			text-align: center;
			padding: 24rpx 0;
			font-size: 30rpx;
			color: #666;
			position: relative;

			&.is-active {
				color: #D71F1F;
				font-weight: bold;

				&::after {
					content: '';
					position: absolute;
					bottom: 10rpx;
					left: 50%;
					transform: translateX(-50%);
					width: 60rpx;
					height: 6rpx;
					background-color: #D71F1F;
					border-radius: 4rpx;
				}
			}
		}
	}

	.swiper-container {
		flex: 1;
	}

	swiper-item,
	.scroll-view-container {
		height: 100%;
	}

	.list-wrapper {
		padding: 24rpx;
		height: 100vh;
	}

	.favorite-card {
		display: flex;
		align-items: center;
		background-color: #fff;
		border-radius: 16rpx;
		padding: 24rpx;
		margin-bottom: 20rpx;
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);

		.card-main {
			display: flex;
			align-items: center;
			flex-grow: 1;
			min-width: 0;
		}

		.info-container {
			flex: 1;
			margin: 0 20rpx;
			min-width: 0;

			.name {
				font-size: 28rpx;
				color: #333;
				font-weight: 500;
				display: -webkit-box;
				-webkit-box-orient: vertical;
				-webkit-line-clamp: 2;
				overflow: hidden;
				word-break: break-all;
			}
		}

		.delete-icon {
			padding: 10rpx;
			flex-shrink: 0;
		}
	}

	.product-card {
		.image-wrapper {
			flex-shrink: 0;

			.product-image {
				width: 180rpx;
				height: 180rpx;
				border-radius: 12rpx;
			}
		}

		.info-container {
			display: flex;
			flex-direction: column;
			justify-content: space-between;
			height: 180rpx;

			.price-row {
				margin-top: auto;

				.price-symbol {
					font-size: 24rpx;
					color: #E43D3D;
				}

				.price-current {
					font-size: 32rpx;
					color: #E43D3D;
					font-weight: bold;
				}

				.price-original {
					font-size: 24rpx;
					color: #999;
					text-decoration: line-through;
					margin-left: 12rpx;
				}
			}
		}
	}

	/* [OPTIMIZED] New Merchant Card Styles */
	.merchant-card-new {
		display: flex;
		background-color: #fff;
		border-radius: 16rpx;
		padding: 30rpx;
		margin-bottom: 24rpx;
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);
		position: relative;
		overflow: hidden;
	}

	.nearest-tag {
		position: absolute;
		top: -1rpx;
		left: -1rpx;
		background-color: #D71F1F;
		color: #fff;
		font-size: 20rpx;
		padding: 6rpx 12rpx 6rpx 20rpx;
		border-bottom-right-radius: 16rpx;
		border-top-left-radius: 16rpx;
	}

	.card-main-new {
		display: flex;
		flex: 1;
		min-width: 0;
	}

	.logo-new {
		width: 100rpx;
		height: 100rpx;
		border-radius: 50%;
		flex-shrink: 0;
		margin-right: 24rpx;
		border: 1rpx solid #eee;
	}

	.info-section-new {
		flex: 1;
		display: flex;
		flex-direction: column;
		gap: 8rpx;
		min-width: 0;
	}

	.name-new {
		font-size: 32rpx;
		font-weight: bold;
		color: #333;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.category-new {
		font-size: 24rpx;
		color: #666;
	}

	.rating-new {
		display: flex;
		align-items: center;
		font-size: 24rpx;
		color: #FFB800;
		font-weight: 500;

		text {
			margin-left: 8rpx;
		}
	}

	.address-row-new {
		display: flex;

		align-items: center;
		margin-top: 8rpx;
	}

	.address-text-new {
		font-size: 24rpx;
		color: #999;
		margin-left: 8rpx;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		padding-right: 20rpx;
	}

	.distance-new {
		font-size: 24rpx;
		color: #D71F1F;
		font-weight: 500;
		white-space: nowrap;
	}

	.delete-icon-new {
		padding: 10rpx;
		flex-shrink: 0;
		align-self: flex-start;
		margin-left: 20rpx;
	}


	.status-container {
		padding-bottom: 40rpx;
	}
</style>