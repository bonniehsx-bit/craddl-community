<template>
	<tui-page :is-pulldown="true" @pulldown="onPullDownRefresh" @reach-bottom="onReachBottom">
		<view class="page-container">
			<view class="coupon-list">
				<view class="coupon-card" v-for="(item,index) in list" :key="item.id">
					
					<!-- 左侧：金额/折扣 -->
					<view class="card-left">
						<view class="price-wrapper">
							<block v-if="item.type == 0"> <text class="symbol">¥</text>
								<text class="price">{{ item.sub_price }}</text>
							</block>
							<block v-if="item.type == 1"> <text class="price">{{ item.discount }}</text>
								<text class="symbol">折</text>
							</block>
						</view>
						<view class="condition">{{ item.description }}</view>
					</view>

					<!-- 右侧：详情与操作 -->
					<view class="card-right">
						<view class="details">
							<view class="title-line">
								<view class="tag">{{ item.appoint_type === 'all' ? '全场通用' : '指定可用' }}</view>
								<view class="title">{{ item.name }}</view>
							</view>

							<view class="middle-content">
								<view class="mch-info" v-if="item.mch && item.mch.mch_name">
									<tui-icon name="shop" :size="14" color="#999"></tui-icon>
									<text class="mch-name-text">{{ item.mch.mch_name }}</text>
								</view>

								<view class="date">
									{{ item.time_type == 0 ? `领取后${item.day}天内有效` : `有效期至: ${ formatDate(item.end_at) }` }}
								</view>
							</view>
						</view>

						<!-- 操作按钮区 -->
						<view class="action-btn">
							<button v-if="item.can_claim_more" class="btn claim" @click="receive(item)">立即领取</button>
							<button v-else class="btn disabled">{{ item.is_claimed ? '已领取' : '已领完' }}</button>
						</view>
					</view>
				</view>
			</view>

			<uni-load-more v-if="list.length > 0 || loading" :status="loadStatus" />
			<tui-no-data v-if="!list.length && !loading" :fixed="false">暂无可领取{{ $noun('coupon') }}</tui-no-data>
		</view>
	</tui-page>
</template>

<script>
	export default {
		data() {
			return {
				loading: false,
				page: 1,
				limit: 15,
				list: [],
				loadStatus: 'loadmore',
				goods_id: null,
			}
		},
		onLoad(options) {
			if (options.goods_id) {
				this.goods_id = options.goods_id;
			}
			this.loadData(true);
		},
		onPullDownRefresh() {
			this.loadData(true).finally(() => uni.stopPullDownRefresh());
		},
		onReachBottom() {
			this.loadData();
		},
		methods: {
			async loadData(isRefresh = false) {
				if (isRefresh) {
					this.page = 1;
					this.list = [];
					this.loadStatus = 'loadmore';
				}

				if (this.loadStatus === 'nomore' || this.loading) {
					return;
				}

				this.loading = true;
				this.loadStatus = 'loading';

				const params = {
					page: this.page,
					limit: this.limit,
				};

				if (this.goods_id) {
					params.goods_id = this.goods_id;
				}

				try {
					const res = await this.$request({
						url: '/api/coupon/coupon-list',
						data: params
					});

					if (res.code === 0) {
						const newList = res.data.list || [];
						this.list = isRefresh ? newList : this.list.concat(newList);

						const pagination = res.data.pagination;
						if (!pagination || pagination.current_page >= pagination.page_count) {
							this.loadStatus = 'nomore';
						} else {
							this.loadStatus = 'loadmore';
							this.page++;
						}
					} else {
						this.$http.toast(res.msg);
						this.loadStatus = 'loadmore';
					}
				} catch (e) {
					this.loadStatus = 'loadmore';
				} finally {
					this.loading = false;
				}
			},

			async receive(item) {
				uni.showLoading({
					title: '正在领取...'
				});
				try {
					const res = await this.$request({
						url: '/api/coupon/coupon-receive',
						method: 'post',
						data: {
							coupon_id: item.id
						}
					});

					uni.hideLoading();

					if (res.code === 0) {
						uni.showToast({
							title: '领取成功',
							icon: 'success'
						});
						this.loadData(true);
					} else {
						this.$http.toast(res.msg);
					}
				} catch (e) {
					uni.hideLoading();
				}
			},

			formatDate(timestamp) {
				if (!timestamp) return '';
				const date = new Date(timestamp * 1000);
				const year = date.getFullYear();
				const month = ('0' + (date.getMonth() + 1)).slice(-2);
				const day = ('0' + date.getDate()).slice(-2);
				return `${year}.${month}.${day}`;
			}
		}
	}
</script>

<style lang="scss" scoped>
	page {
		background-color: #f7f8fa;
	}

	.page-container {
		display: flex;
		flex-direction: column;
	}

	.coupon-list {
		box-sizing: border-box;
		padding: 24rpx;
	}

	.coupon-card {
		display: flex;
		height: 200rpx;
		background-color: #fff;
		border-radius: 16rpx;
		margin-bottom: 24rpx;
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);
		position: relative;
		overflow: hidden;

		&::before {
			content: '';
			position: absolute;
			background-color: #f7f8fa;
			width: 30rpx;
			height: 30rpx;
			border-radius: 50%;
			top: 50%;
			transform: translateY(-50%);
			left: 215rpx;
			z-index: 2;
		}
	}

	.card-left {
		width: 230rpx;
		border-right: 1rpx dashed #e0e0e0;
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
		color: #E43D3D;
		transition: all 0.3s;
		background: #fff;

		.price-wrapper {
			display: flex;
			align-items: baseline;
		}

		.symbol {
			font-size: 32rpx;
			font-weight: bold;
		}

		.price {
			font-size: 64rpx;
			font-weight: bold;
			line-height: 1;
		}

		.condition {
			font-size: 24rpx;
			margin-top: 10rpx;
			color: #666;
			transition: color 0.3s;
		}
	}

	.card-right {
		flex: 1;
		padding: 20rpx;
		display: flex;
		justify-content: space-between;
		align-items: center;
		min-width: 0;
		position: relative;
		z-index: 1;
	}

	.details {
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		height: 100%;
		overflow: hidden;
		flex: 1;
		padding: 8rpx 0;
	}

	.title-line {
		display: flex;
		align-items: center;
	}

	.tag {
		background-color: #FEF0F0;
		color: #E43D3D;
		font-size: 20rpx;
		padding: 4rpx 10rpx;
		border-radius: 6rpx;
		margin-right: 10rpx;
		flex-shrink: 0;
		transition: all 0.3s;
	}

	.title {
		font-size: 28rpx;
		font-weight: 500;
		color: #333;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		transition: color 0.3s;
	}

	.mch-info {
		display: flex;
		align-items: center;
		font-size: 22rpx;
		color: #999;
		margin-top: 8rpx;
		margin-bottom: 8rpx;

		.mch-name-text {
			margin-left: 8rpx;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}
	}

	.date {
		font-size: 22rpx;
		color: #999;
	}

	.action-btn {
		flex-shrink: 0;
		margin-left: 10rpx;
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: flex-end;
		height: 100%;

		.btn {
			height: 52rpx;
			line-height: 52rpx;
			font-size: 24rpx;
			border-radius: 26rpx;
			margin: 8rpx 0;
			padding: 0 28rpx;
			border: none;
			min-width: 140rpx;

			&::after {
				border: none;
			}

			&.claim {
				background: linear-gradient(90deg, #FF7043, #E43D3D);
				color: #fff;
				box-shadow: 0 4rpx 10rpx rgba(228, 61, 61, 0.3);
			}

			&.disabled {
				background-color: #ccc;
				color: #fff;
			}
		}
	}
</style>
