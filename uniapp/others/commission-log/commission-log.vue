<template>
	<tui-page backgroundColor="#F5F6FA">
		<view class="container" :style="{ '--theme-color': themeColor }">

			<!-- 顶部统计卡 -->
			<view class="stats-card">
				<view class="stat-cell">
					<text class="stat-label">待结算</text>
					<text class="stat-value pending">¥{{ stats.pending || '0.00' }}</text>
				</view>
				<view class="stat-divider"></view>
				<view class="stat-cell">
					<text class="stat-label">已结算</text>
					<text class="stat-value settled">¥{{ stats.settled || '0.00' }}</text>
				</view>
				<view class="stat-divider"></view>
				<view class="stat-cell">
					<text class="stat-label">已失效</text>
					<text class="stat-value invalid">¥{{ stats.invalid || '0.00' }}</text>
				</view>
			</view>

			<!-- 状态 Tabs -->
			<tui-tabs
				:tabs="statusTabs"
				:currentTab="statusTabIdx"
				:selectedColor="themeColor"
				:sliderBgColor="themeColor"
				itemWidth="25%"
				@change="onStatusTabChange">
			</tui-tabs>

			<!-- 类型筛选（横向滑动胶囊） -->
			<scroll-view scroll-x class="type-scroll" v-if="typeOptions.length > 1">
				<view class="type-pill" :class="{ active: !selectedType }" @click="onTypeChange('')">
					<text>全部类型</text>
				</view>
				<view class="type-pill" v-for="(opt, i) in typeOptions" :key="i"
					:class="{ active: selectedType === opt.value }" @click="onTypeChange(opt.value)">
					<text>{{ opt.label }}</text>
				</view>
			</scroll-view>

			<!-- 列表 -->
			<view class="log-list-container">
				<block v-if="list.length > 0">
					<view class="log-item" v-for="(item, idx) in list" :key="item.id">
						<view class="item-head">
							<view class="item-head-left">
								<view class="type-tag" :class="'tag-' + (item.tag_type || 'default')">{{ item.type_text }}</view>
								<text v-if="item.level > 0" class="level-tag">L{{ item.level }}</text>
							</view>
							<view class="item-amount" :class="'amount-' + item.status">
								<text class="sign">{{ item.status === 1 ? '+' : (item.status === 2 ? '×' : '⏳') }}</text>
								<text class="money">{{ item.commission_amount }}</text>
								<text class="unit">元</text>
							</view>
						</view>
						<view class="item-body">
							<text v-if="item.remark" class="remark">{{ item.remark }}</text>
						</view>
						<view class="item-foot">
							<view class="foot-col">
								<text class="foot-label">买家：</text>
								<text class="foot-value">{{ item.buyer_nickname }}</text>
							</view>
							<view class="foot-col" v-if="item.order_no">
								<text class="foot-label">订单：</text>
								<text class="foot-value order">{{ item.order_no }}</text>
							</view>
						</view>
						<view class="item-foot-time">
							<text>{{ item.status === 1 && item.settled_at ? '到账：' + item.settled_at : '产生：' + item.created_at }}</text>
							<text class="status-text" :class="'status-' + item.status">{{ item.status_text }}</text>
						</view>
					</view>
				</block>

				<tui-no-data v-if="list.length === 0 && !loading" :fixed="false">
					暂无分润记录
				</tui-no-data>
			</view>

			<tui-loadmore v-if="loading" :index="3"></tui-loadmore>
			<tui-nomore v-if="is_no_more && list.length > 0" backgroundColor="#F5F6FA"></tui-nomore>
		</view>
	</tui-page>
</template>

<script>
import { mapState } from 'vuex';

	import TuiTabs from '@/others/components/tui-tabs/tui-tabs.vue'
export default {
		components: { TuiTabs },
	data() {
		return {
			statusTabs: [
				{ name: '全部', value: '' },
				{ name: '待结算', value: 0 },
				{ name: '已结算', value: 1 },
				{ name: '已失效', value: 2 },
			],
			statusTabIdx: 0,
			statusFilter: '',      // '' / 0 / 1 / 2
			selectedType: '',      // 选中的 type 值
			typeOptions: [],       // 后端下发
			stats: { pending: '0.00', settled: '0.00', invalid: '0.00' },
			loading: false,
			page: 1,
			is_no_more: false,
			list: [],
		}
	},
	computed: {
		...mapState('mall', {
			themeColor: state => state.themeColor || '#D71F1F'
		})
	},
	onLoad() {
		this.setNavBarColor();
		this.getList();
	},
	onShow() {
		this.setNavBarColor();
	},
	watch: {
		themeColor(v) { if (v) this.setNavBarColor(); }
	},
	onReachBottom() {
		this.getList();
	},
	methods: {
		setNavBarColor() {
			if (this.themeColor) {
				uni.setNavigationBarColor({
					frontColor: '#ffffff',
					backgroundColor: this.themeColor,
					animation: { duration: 300, timingFunc: 'easeIn' }
				});
			}
		},
		onStatusTabChange(e) {
			this.statusTabIdx = e.index;
			this.statusFilter = this.statusTabs[e.index].value;
			this.resetAndLoad();
		},
		onTypeChange(v) {
			this.selectedType = v;
			this.resetAndLoad();
		},
		resetAndLoad() {
			this.page = 1;
			this.is_no_more = false;
			this.list = [];
			this.getList();
		},
		async getList() {
			if (this.loading || this.is_no_more) return;
			this.loading = true;
			try {
				const params = { page: this.page, limit: 15 };
				if (this.statusFilter !== '') params.status = this.statusFilter;
				if (this.selectedType) params.type = this.selectedType;

				const res = await this.$request({
					url: '/api/finance/commission-log',
					data: params,
				});
				if (res.code === 0) {
					const d = res.data;
					this.list = this.page === 1 ? d.list : this.list.concat(d.list);
					this.stats = d.stats;
					// 仅首次或筛选重置时刷新 typeOptions
					if (this.page === 1) this.typeOptions = d.type_options || [];

					if (d.pagination && d.pagination.page_count > this.page) {
						this.page++;
					} else {
						this.is_no_more = true;
					}
				}
			} catch (e) {
				console.error('加载分润记录失败', e);
			} finally {
				this.loading = false;
			}
		},
	}
}
</script>

<style lang="scss" scoped>
$theme-color: var(--theme-color);

.container { min-height: 100vh; }

/* 统计卡 */
.stats-card {
	display: flex;
	align-items: center;
	background: #fff;
	margin: 20rpx 24rpx 0;
	padding: 30rpx 20rpx;
	border-radius: 16rpx;
	box-shadow: 0 4rpx 20rpx rgba(0,0,0,0.04);
	.stat-cell {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;
		.stat-label { font-size: 24rpx; color: #909399; }
		.stat-value {
			font-size: 34rpx;
			font-weight: 700;
			margin-top: 10rpx;
			font-family: DIN, Arial, sans-serif;
			&.pending  { color: #E6A23C; }
			&.settled  { color: $theme-color; }
			&.invalid  { color: #909399; }
		}
	}
	.stat-divider { width: 2rpx; height: 60rpx; background: #EBEEF5; }
}

/* 类型胶囊滚动 */
.type-scroll {
	white-space: nowrap;
	padding: 20rpx 24rpx 10rpx;
	.type-pill {
		display: inline-block;
		padding: 10rpx 24rpx;
		background: #fff;
		border-radius: 30rpx;
		font-size: 24rpx;
		color: #606266;
		margin-right: 16rpx;
		border: 1rpx solid #EBEEF5;
		&.active {
			background: $theme-color;
			color: #fff;
			border-color: $theme-color;
		}
	}
}

/* 列表 */
.log-list-container {
	padding: 20rpx 24rpx;
}
.log-item {
	background: #fff;
	border-radius: 16rpx;
	padding: 24rpx 28rpx;
	margin-bottom: 20rpx;
	box-shadow: 0 4rpx 16rpx rgba(0,0,0,0.04);
}
.item-head {
	display: flex;
	justify-content: space-between;
	align-items: center;
}
.item-head-left {
	display: flex;
	align-items: center;
	gap: 12rpx;
}
.type-tag {
	font-size: 22rpx;
	padding: 4rpx 14rpx;
	border-radius: 6rpx;
	line-height: 1.5;
	/* tag 颜色跟后端 tag_type 对齐 */
	&.tag-default { background: rgba(64,158,255,0.1); color: #409EFF; }
	&.tag-success { background: rgba(103,194,58,0.1); color: #67C23A; }
	&.tag-warning { background: rgba(230,162,60,0.1); color: #E6A23C; }
	&.tag-danger  { background: rgba(245,108,108,0.1); color: #F56C6C; }
	&.tag-info    { background: rgba(144,147,153,0.1); color: #909399; }
}
.level-tag {
	font-size: 22rpx;
	color: #E6A23C;
	background: rgba(230,162,60,0.08);
	padding: 2rpx 10rpx;
	border-radius: 4rpx;
}
.item-amount {
	display: flex;
	align-items: baseline;
	.sign { font-size: 28rpx; font-weight: 600; margin-right: 4rpx; }
	.money { font-size: 34rpx; font-weight: 700; font-family: DIN, Arial, sans-serif; }
	.unit { font-size: 22rpx; color: #909399; margin-left: 4rpx; }
	&.amount-0 { color: #E6A23C; }   /* 待结算黄 */
	&.amount-1 { color: $theme-color; } /* 已结算主题色 */
	&.amount-2 { color: #C0C4CC; text-decoration: line-through; } /* 已失效灰删 */
}
.item-body {
	margin-top: 14rpx;
	.remark { font-size: 26rpx; color: #606266; line-height: 1.5; }
}
.item-foot {
	display: flex;
	flex-wrap: wrap;
	gap: 20rpx 30rpx;
	margin-top: 14rpx;
	.foot-col { display: inline-flex; align-items: center; font-size: 24rpx; }
	.foot-label { color: #909399; }
	.foot-value { color: #606266; &.order { color: #409EFF; } }
}
.item-foot-time {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-top: 16rpx;
	padding-top: 14rpx;
	border-top: 1rpx dashed #EBEEF5;
	font-size: 22rpx;
	color: #C0C4CC;
	.status-text {
		padding: 2rpx 10rpx;
		border-radius: 4rpx;
		font-size: 20rpx;
		&.status-0 { background: rgba(230,162,60,0.1); color: #E6A23C; }
		&.status-1 { background: rgba(103,194,58,0.1); color: #67C23A; }
		&.status-2 { background: rgba(192,196,204,0.15); color: #909399; }
	}
}
</style>
