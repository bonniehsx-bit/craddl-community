<template>
	<view class="page-container" :style="themeStyle">
		<!-- 余额卡:一屏最重要的信息只有一个数,不再堆三个指标 -->
		<view class="balance-card">
			<view class="balance-label">{{ stats.settle_asset_name || '货款' }}余额</view>
			<view class="balance-value">
				<text class="unit">¥</text>{{ stats.balance || '0.00' }}
			</view>
			<button class="withdraw-btn" :class="{ 'is-disabled': !stats.withdrawable }"
				:disabled="!stats.withdrawable" @click="goToWithdraw">
				{{ stats.withdrawable ? '提现' : '暂不可提现' }}
			</button>
			<view class="balance-tip" v-if="!stats.withdrawable">提现入口由平台在提现设置中开启</view>
		</view>

		<view class="logs-section">
			<view class="title-wrapper">
				<view class="section-title">资金流水</view>
				<view class="tabs">
					<view class="tab-item" :class="{ 'active': currentTab === 0 }" @click="changeTab(0)">全部</view>
					<view class="tab-item" :class="{ 'active': currentTab === 1 }" @click="changeTab(1)">入账</view>
					<view class="tab-item" :class="{ 'active': currentTab === 2 }" @click="changeTab(2)">支出</view>
				</view>
			</view>

			<view v-if="logList.length > 0">
				<block v-for="(item, index) in logList" :key="index">
					<view class="log-item">
						<view class="log-icon">
							<uni-icons :type="item.log_type === 'in' ? 'plus-filled' : 'minus-filled'" size="30"
								:color="item.log_type === 'in' ? themeColor : '#C0C4CC'"></uni-icons>
						</view>
						<view class="log-content">
							<view class="log-title">{{ item.title || item.remark }}</view>
							<view class="log-desc" v-if="item.title && item.remark">{{ item.remark }}</view>
							<view class="log-time">{{ item.created_time }}</view>
						</view>
						<view class="log-right">
							<view class="log-amount" :class="item.log_type">{{ item.amount }}</view>
							<!-- 变动后余额:让商家能逐笔对账,加总应等于顶部「可提现」 -->
							<view class="log-balance" v-if="item.balance_after">余额 {{ item.balance_after }}</view>
						</view>
					</view>
				</block>
			</view>

			<tui-no-data v-if="logList.length === 0 && loadStatus === 'nomore'" :fixed="false">暂无记录</tui-no-data>

			<uni-load-more :status="loadStatus" />
		</view>
	</view>
</template>

<script>
	export default {
		data() {
			return {
				stats: {
					balance: '0.00',
					// 结算流向解析出的资产 key,兜底=历史默认(commission→price);提现入口按它直达 withdraw 插件
					settle_asset: 'price',
					settle_asset_name: '货款',
					withdrawable: 0 // 由后台提现设置决定,前端不自己判断
				},
				logList: [],
				page: 1,
				limit: 15,
				loadStatus: 'loadmore', // loadmore, loading, nomore
				currentTab: 0, // 0=全部 1=入账 2=支出
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
					this.logList = [];
					this.loadStatus = 'loadmore';
				}

				if (this.loadStatus === 'nomore') {
					return;
				}

				this.loadStatus = 'loading';

				// 与后端 log_type 对齐:''=全部 / in=入账 / out=支出(方向由各流水表的 type 字段判定)
				const logTypeMap = ['', 'in', 'out'];
				const logType = logTypeMap[this.currentTab] || '';

				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/mch/finance-dashboard',
						data: {
							page: this.page,
							limit: this.limit,
							log_type: logType
						}
					});

					if (res.code === 0) {
						const { stats, list, pagination } = res.data;


						// stats 只在第一页时更新
						if (this.page === 1) {
							this.stats = stats;
						}

						this.logList = isRefresh ? list : [...this.logList, ...list];

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
			changeTab(index) {
				if (this.currentTab === index) return;
				this.currentTab = index;
				this.fetchData(true);
			},
			// 旧版提现页只认识 price/balance/integral 三个核心账户，不认识扩展钱包(如 mch.funds)；
			// 商家的货款结算流向(mch_settle_target)可以配成任意扩展钱包，所以这里必须带着后端解析好的
			// settle_asset 直接进新版 withdraw 插件页面，不能再写死跳旧版页面的 type=commission 硬编码。
			goToWithdraw() {
				const asset = this.stats.settle_asset || 'price';
				uni.navigateTo({
					url: `/plugins2/withdraw/withdraw?asset=${asset}`
				});
			}
		}
	}
</script>

<style lang="scss" scoped>
	.page-container {
		background-color: #f7f8fa;
		min-height: 100vh;
	}

	/* 余额卡:主题色渐变。禁止硬编码色值 —— 走 themeStyle 下发的 CSS 变量(规范 §14.2.1),
	   否则商城换主题色时这里会突兀地留着旧红。 */
	.balance-card {
		margin: 24rpx;
		padding: 48rpx 40rpx 40rpx;
		border-radius: 24rpx;
		color: #fff;
		background: linear-gradient(135deg, var(--theme-color) 0%, var(--theme-color-light, var(--theme-color)) 100%);
		box-shadow: 0 12rpx 32rpx var(--theme-color-shadow, rgba(0, 0, 0, .12));
		text-align: center;
	}

	.balance-label {
		font-size: 28rpx;
		opacity: .85;
		letter-spacing: 2rpx;
	}

	.balance-value {
		font-size: 82rpx;
		font-weight: 600;
		line-height: 1.2;
		margin-top: 12rpx;
		/* 等宽数字:金额刷新时不会左右跳动 */
		font-variant-numeric: tabular-nums;

		.unit {
			font-size: 40rpx;
			font-weight: 500;
			margin-right: 6rpx;
			opacity: .9;
		}
	}

	.withdraw-btn {
		margin: 36rpx auto 0;
		width: 320rpx;
		height: 76rpx;
		line-height: 76rpx;
		border-radius: 38rpx;
		font-size: 30rpx;
		font-weight: 500;
		background: #fff;
		color: var(--theme-color);
		border: none;

		&::after { border: none; }

		&.is-disabled {
			background: rgba(255, 255, 255, .25);
			color: rgba(255, 255, 255, .75);
		}
	}

	.balance-tip {
		margin-top: 16rpx;
		font-size: 24rpx;
		opacity: .8;
	}

	.logs-section {
		margin: 24rpx;
	}

	.title-wrapper {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 24rpx;
	}

	.section-title {
		font-size: 32rpx;
		font-weight: bold;
		color: #333;
	}

	.tabs {
		display: flex;
		background-color: #f2f2f2;
		border-radius: 30rpx;
		padding: 6rpx;
		font-size: 26rpx;
	}

	.tab-item {
		padding: 10rpx 30rpx;
		color: #666;
		border-radius: 26rpx;
		transition: all 0.3s ease;

		&.active {
			background-color: #fff;
			color: var(--theme-color);
			font-weight: bold;
			box-shadow: 0 4rpx 10rpx rgba(0, 0, 0, 0.05);
		}
	}


	.log-desc {
		font-size: 24rpx;
		color: #909399;
		margin-top: 4rpx;
	}

	.log-balance {
		font-size: 22rpx;
		color: #C0C4CC;
		margin-top: 6rpx;
	}

	.log-amount {
		&.in { color: var(--theme-color); }
		&.out { color: #606266; }
	}

	.log-item {
		display: flex;
		align-items: center;
		background-color: #fff;
		padding: 30rpx;
		border-radius: 16rpx;
		margin-bottom: 20rpx;
	}

	.log-icon {
		margin-right: 24rpx;
	}

	.log-content {
		flex: 1;

		.log-title {
			font-size: 28rpx;
			color: #333;
			font-weight: 500;
		}

		.log-time {
			font-size: 24rpx;
			color: #999;
			margin-top: 8rpx;
		}
	}

	.log-amount {
		font-size: 32rpx;
		font-weight: bold;

		&.income {
			color: var(--theme-color);
		}

		&.expense {
			color: #333;
		}
	}

	.details-section {
		background-color: #f8f8f8;
		padding: 20rpx 30rpx;
		margin: -20rpx 0 20rpx 0;
		border-radius: 0 0 16rpx 16rpx;
		font-size: 24rpx;

		.detail-row {
			display: flex;
			justify-content: space-between;
			margin-bottom: 10rpx;

			&:last-child {
				margin-bottom: 0;
			}
		}

		.detail-label {
			color: #999;
		}

		.detail-value {
			color: #666;
		}
	}
</style>