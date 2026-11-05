<template>
	<tui-page :showBackBtn="true" :title="$noun('withdraw.title_wallet')">
		<view class="container" :style="{ '--theme-color': themeColor }">
			<!-- 合计总额卡片 -->
			<view class="total-assets-card" v-if="list.length > 0">
				<view class="assets-label">
					<text class="label-text">合计总额 (元)</text>
				</view>
				<view class="assets-value">{{ totalBalance }}</view>
				<view class="assets-tip">汇集平台及各子系统插件钱包可用余额</view>
			</view>

			<!-- 宫格列表 -->
			<view class="grid-container" v-if="list.length > 0">
				<view class="wallet-grid-card" v-for="w in list" :key="w.asset_type" @click="goDetail(w)">
					<view class="card-top">
						<text class="name">{{ w.name }}</text>
						<text class="plugin-tag" :class="{ 'system-tag': !w.plugin_label }">
							{{ w.plugin_label || '平台通用' }}
						</text>
					</view>
					
					<view class="card-middle">
						<text class="currency" v-if="w.unit === '元'">¥</text>
						<text class="bal-val">{{ w.balance }}</text>
						<text class="unit-suffix" v-if="w.unit && w.unit !== '元'">{{ w.unit }}</text>
					</view>
					
					<view class="card-bottom">
						<view class="detail-btn-wrap" @click.stop="goDetail(w)">
							<uni-icons type="bars" size="12" color="#909399"></uni-icons>
							<text class="detail-text">明细</text>
						</view>
						<view class="withdraw-btn-wrap" v-if="w.withdrawable == 1" @click.stop="goWithdraw(w)">
							<text class="btn-text" :style="{ color: themeColor }">去提现</text>
							<uni-icons type="right" size="12" :color="themeColor"></uni-icons>
						</view>
						<text class="readonly-text" v-else>只读资产</text>
					</view>
				</view>
			</view>

			<view class="empty" v-if="!loading && list.length === 0">暂无钱包</view>
		</view>
	</tui-page>
</template>

<script>
import { mapState } from 'vuex';

export default {
	data() {
		return {
			loading: false,
			list: []
		};
	},
	computed: {
		...mapState('mall', {
			themeColor: state => state.themeColor || '#D71F1F'
		}),
		totalBalance() {
			let total = 0;
			this.list.forEach(w => {
				// 只累加「元」类钱包(in_total=1);积分等非「元」资产不计入合计总额(元)
				if (w.in_total == 1) total += parseFloat(w.balance) || 0;
			});
			return total.toFixed(2);
		}
	},
	onLoad() {
		// 标题走后台可配完整词库(pages.json 静态标题取不了词)
		uni.setNavigationBarTitle({ title: this.$noun('withdraw.title_wallet') });
	},
	onShow() {
		this.getList();
	},
	onPullDownRefresh() {
		this.getList(() => uni.stopPullDownRefresh());
	},
	methods: {
		async getList(done) {
			this.loading = true;
			try {
				const res = await this.$request({
					url: '/plugin/withdraw/api/wallet/overview',
					method: 'get'
				});
				if (res.code === 0) {
					this.list = res.data.list || [];
				}
			} catch (e) {
				// 静默
			} finally {
				this.loading = false;
				done && done();
			}
		},
		goDetail(w) {
			// 核心3(佣金/余额/积分)有更丰富的专属中心页 → 跳专属;扩展钱包(渠道专户/货款/...)走通用明细兜底
			const special = {
				price: '/plugins2/withdraw/price',
				balance: '/plugins2/withdraw/balance',
				integral: '/plugins2/withdraw/integral'
			};
			const url = special[w.asset_type]
				|| `/plugins2/withdraw/detail?asset_type=${encodeURIComponent(w.asset_type)}&name=${encodeURIComponent(w.name)}`;
			this.$T.go(url);
		},
		goWithdraw(w) {
			this.$T.go(`/plugins2/withdraw/withdraw?asset=${encodeURIComponent(w.asset_type)}`);
		}
	}
};
</script>

<style lang="scss" scoped>
.container { min-height: 100vh; background: #f5f6f8; }
.empty { text-align: center; color: #bbb; padding: 120rpx 0; font-size: 28rpx; }

.total-assets-card {
	background-color: var(--theme-color);
	background-image: linear-gradient(135deg, rgba(255, 255, 255, 0.12) 0%, rgba(0, 0, 0, 0.12) 100%);
	border-radius: 24rpx;
	padding: 40rpx 36rpx;
	margin: 24rpx;
	box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.05);
	color: #fff;
	display: flex;
	flex-direction: column;
	position: relative;
	overflow: hidden;

	.assets-label {
		font-size: 24rpx;
		color: rgba(255, 255, 255, 0.75);
		margin-bottom: 12rpx;
		display: flex;
		align-items: center;
	}

	.assets-value {
		font-size: 64rpx;
		font-weight: 800;
		color: #ffffff;
		line-height: 1.1;
		font-family: 'DIN-Alternate-Bold', sans-serif;
	}

	.assets-tip {
		font-size: 20rpx;
		color: rgba(255, 255, 255, 0.6);
		margin-top: 20rpx;
		border-top: 1rpx solid rgba(255, 255, 255, 0.15);
		padding-top: 12rpx;
	}
}

.grid-container {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 20rpx;
	padding: 0 24rpx 24rpx;
}

.wallet-grid-card {
	background: #ffffff;
	border-radius: 20rpx;
	padding: 24rpx;
	box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.015);
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	min-height: 190rpx;
	transition: all 0.2s;
	border: 1rpx solid #f0f2f5;

	&:active {
		transform: scale(0.98);
		background: #fafafa;
	}

	.card-top {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 16rpx;

		.name {
			font-size: 26rpx;
			color: #303133;
			font-weight: bold;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
			flex: 1;
		}

		.plugin-tag {
			font-size: 18rpx;
			color: #909399;
			background: #f4f6f8;
			padding: 2rpx 10rpx;
			border-radius: 4rpx;
			margin-left: 10rpx;
			flex-shrink: 0;

			&.system-tag {
				background: #E8F2FF;
				color: #1890FF;
			}
		}
	}

	.card-middle {
		display: flex;
		align-items: baseline;
		margin-bottom: 20rpx;

		.currency {
			font-size: 24rpx;
			color: #303133;
			font-weight: bold;
			margin-right: 4rpx;
		}

		.bal-val {
			font-size: 38rpx;
			font-weight: bold;
			color: #2D313A;
			font-family: 'DIN-Alternate-Bold', sans-serif;
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
		}

		.unit-suffix {
			font-size: 22rpx;
			color: #909399;
			margin-left: 6rpx;
		}
	}

	.card-bottom {
		border-top: 1rpx solid #f4f6f8;
		padding-top: 16rpx;
		display: flex;
		justify-content: space-between;
		align-items: center;

		.detail-btn-wrap {
			display: flex;
			align-items: center;
			gap: 4rpx;

			.detail-text {
				font-size: 22rpx;
				color: #909399;
			}
		}

		.withdraw-btn-wrap {
			display: flex;
			align-items: center;
			gap: 4rpx;

			.btn-text {
				font-size: 22rpx;
				font-weight: bold;
			}
		}

		.readonly-text {
			font-size: 22rpx;
			color: #C0C4CC;
		}
	}
}
</style>
