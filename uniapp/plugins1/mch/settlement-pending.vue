<template>
	<view class="page-container" :style="themeStyle">
		<tui-navigation-bar title="待结算订单" :background-color="'var(--theme-color)'" color="#ffffff" :backgroundOpacity="1"></tui-navigation-bar>
		
		<view class="list-container">
			<view v-if="list.length > 0">
				<view class="order-card" v-for="(item, index) in list"  @click="$T.go('/plugins1/mch/order-detail?id='+item.id)">
					<view class="card-header">
						<view class="order-no">订单号: {{ item.order_no }}</view>
						<view class="status-tag">{{ item.status_text }}</view>
					</view>
					<view class="card-body">
						<view class="amount-section">
							<view class="amount-label">本单待结算金额</view>
							<view class="amount-value">¥{{ item.pending_amount }}</view>
						</view>
						<view class="time-section">
							<text class="time-label">下单时间:</text>
							<text class="time-value">{{ item.created_time }}</text>
						</view>
					</view>
				</view>
			</view>
			
			<tui-no-data v-if="list.length === 0 && loadStatus === 'nomore'" :fixed="false">暂无待结算订单</tui-no-data>
			<uni-load-more :status="loadStatus" />
		</view>
	</view>
</template>

<script>
	import TuiNavigationBar from '@/plugins1/components/tui-navigation-bar/tui-navigation-bar.vue'
	export default {
		components: { TuiNavigationBar },
		data() {
			return {
				list: [],
				page: 1,
				limit: 15,
				loadStatus: 'loadmore', // loadmore, loading, nomore
			};
		},
		onLoad() {
			this.fetchData(true);
		},
		onPullDownRefresh() {
			this.fetchData(true);
		},
		onReachBottom() {
			if (this.loadStatus === 'loadmore') {
				this.fetchData();
			}
		},
		methods: {
			async fetchData(isRefresh = false) {
				if (isRefresh) {
					this.page = 1;
					this.list = [];
					this.loadStatus = 'loadmore';
				}

				if (this.loadStatus === 'nomore') return;
				this.loadStatus = 'loading';

				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/mch/pending-settlement-list', // 您的新接口路由
						data: {
							page: this.page,
							limit: this.limit
						}
					});

					if (res.code === 0) {
						const { list, pagination } = res.data;
						this.list = isRefresh ? list : [...this.list, ...list];

						if (pagination.page >= pagination.page_count) {
							this.loadStatus = 'nomore';
						} else {
							this.loadStatus = 'loadmore';
							this.page++;
						}
					} else {
						this.loadStatus = 'nomore';
					}
				} catch (e) {
					this.loadStatus = 'nomore';
					console.error("fetchData error", e);
				} finally {
					uni.stopPullDownRefresh();
				}
			},
		}
	}
</script>

<style lang="scss" scoped>
	.page-container {
		background-color: #f7f8fa;
		min-height: 100vh;
	}
	
	.list-container {
		padding: 24rpx;
		box-sizing: border-box;
	}
	
	.order-card {
		background-color: #fff;
		border-radius: 16rpx;
		margin-bottom: 24rpx;
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);
		overflow: hidden;
	}
	
	.card-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 24rpx;
		border-bottom: 1rpx solid #f0f0f0;
		
		.order-no {
			font-size: 26rpx;
			color: #999;
		}
		
		.status-tag {
			background-color: #ecf5ff;
			color: #409eff;
			font-size: 24rpx;
			padding: 6rpx 16rpx;
			border-radius: 20rpx;
		}
	}
	
	.card-body {
		padding: 30rpx 24rpx;
		display: flex;
		justify-content: space-between;
		align-items: center;
	}
	
	.amount-section {
		.amount-label {
			font-size: 26rpx;
			color: #666;
		}
		.amount-value {
			font-size: 40rpx;
			font-weight: bold;
			color: var(--theme-color);
			margin-top: 10rpx;
		}
	}
	
	.time-section {
		text-align: right;
		.time-label {
			font-size: 24rpx;
			color: #999;
		}
		.time-value {
			font-size: 24rpx;
			color: #666;
			margin-left: 10rpx;
		}
	}
</style>