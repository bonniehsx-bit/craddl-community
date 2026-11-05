<template>
	<tui-page>
		<view class="container">
			<view class="search-bar">
				<uni-search-bar @confirm="onSearch" placeholder="搜索会员" cancelButton="none" v-model="queryParams.keyword"></uni-search-bar>
			</view>
			
			<scroll-view scroll-y class="list-container" @scrolltolower="loadMore">
				<view v-if="loading && queryParams.page === 1" class="loading-placeholder">
					<uni-load-more status="loading"></uni-load-more>
				</view>
				<template v-else-if="list.length > 0">
					<view class="holder-card" v-for="item in list" :key="item.id">
						<view class="user-info">
							<image class="avatar" :src="item.avatar_url" mode="aspectFill"></image>
							<view class="details">
								<view class="nickname">{{ item.nickname }}</view>
								<view class="mobile">ID:{{ item.user_id }}</view>
							</view>
						</view>
						<view class="stats-grid">
							<view class="stat-item">
								<view class="value">{{ item.total_count || 0 }}</view>
								<view class="label">总获得 (张)</view>
							</view>
							<view class="stat-item">
								<view class="value">{{ item.used_count || 0 }}</view>
								<view class="label">已使用 (张)</view>
							</view>
							<view class="stat-item">
								<view class="value">{{ item.unused_count || 0 }}</view>
								<view class="label">还剩余 (张)</view>
							</view>
							<view class="stat-item">
								<view class="value">{{ item.expired_count || 0 }}</view>
								<view class="label">已过期</view>
							</view>
						</view>
					</view>
					<uni-load-more :status="loadingStatus"></uni-load-more>
				</template>
				<tui-no-data v-else :fixed="false" >
					暂无会员领取该优惠券
				</tui-no-data>
			</scroll-view>
		</view>
	</tui-page>
</template>

<script>
	export default {
		data() {
			return {
				queryParams: {
					coupon_id: null,
					keyword: '',
					page: 1,
					limit: 15,
				},
				list: [],
				loading: false,
				pagination: null,
				loadingStatus: 'more',
			};
		},
		onLoad(options) {
			if (!options.id) {
				this.$tui.toast('参数错误');
				uni.navigateBack();
				return;
			}
			this.queryParams.coupon_id = options.id;
			this.getList(true);
		},
		methods: {
			async getList(refresh = false) {
				if (this.loading && !refresh) return;
				if (refresh) {
					this.queryParams.page = 1;
				}
				this.loading = true;
				this.loadingStatus = 'loading';

				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/coupon/holders',
						data: this.queryParams
					});
					if (res.code === 0) {
						const dataList = res.data.list || [];
						this.list = refresh ? dataList : [...this.list, ...dataList];
						this.pagination = res.data.pagination;
						this.loadingStatus = this.list.length >= (this.pagination?.totalCount || 0) ? 'noMore' : 'more';
					} else {
						this.$showModal(res.msg);
						this.loadingStatus = 'more';
					}
				} finally {
					this.loading = false;
				}
			},
			onSearch() {
				this.getList(true);
			},
			loadMore() {
				if (this.loadingStatus === 'more') {
					this.queryParams.page++;
					this.getList();
				}
			},
		}
	}
</script>

<style lang="scss" scoped>
	// [核心] 使用您提供的颜色规范
	$page-bg: #F3F4F6;
	$card-bg: #FFFFFF;
	$text-main: #333333;
	$text-light: #9CA3AF;
	$text-highlight: #1890FF;

	page {
		background-color: $page-bg;
	}
	
	.container {
		display: flex;
		flex-direction: column;
		height: 100vh;
	}
	
	.search-bar {
		background-color: $card-bg;
		padding: 10rpx 20rpx;
	}
	
	.list-container {
		flex: 1;
		height: 0;
	}
	
	.holder-card {
		background-color: $card-bg;
		border-radius: 16rpx;
		margin: 24rpx;
		box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.05);
	}
	
	.user-info {
		display: flex;
		align-items: center;
		padding: 30rpx;
		.avatar {
			width: 100rpx;
			height: 100rpx;
			border-radius: 50%;
			margin-right: 24rpx;
			flex-shrink: 0;
		}
		.details {
			.nickname {
				font-size: 30rpx;
				font-weight: 500;
				color: $text-main;
			}
			.mobile {
				font-size: 26rpx;
				color: $text-light;
				margin-top: 8rpx;
			}
		}
	}
	
	.stats-grid {
		display: grid;
		grid-template-columns: repeat(4, 1fr);
		border-top: 1rpx solid #E5E7EB; // 分割线
		
		.stat-item {
			padding: 24rpx 0;
			text-align: center;
			border-right: 1rpx solid #E5E7EB;
			
			&:last-child {
				border-right: none;
			}
			
			.value {
				font-size: 32rpx;
				font-weight: bold;
				color: $text-main;
			}
			
			.label {
				font-size: 24rpx;
				color: $text-light;
				margin-top: 8rpx;
			}
		}
	}
</style>