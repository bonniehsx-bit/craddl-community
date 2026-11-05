<template>
	<tui-page :showBackBtn="true" :title="$noun('withdraw.title_records')">
		<view class="container" :style="themeStyle">
			<!-- 状态筛选 -->
			<scroll-view class="filter" scroll-x :show-scrollbar="false">
				<view class="filter-item" v-for="f in filters" :key="f.value" :class="{ active: status === f.value }"
					@click="changeStatus(f.value)">{{ f.label }}</view>
			</scroll-view>

			<view class="list">
				<view class="row" v-for="o in list" :key="o.order_no" @click="openDetail(o)">
					<view class="row-top">
						<text class="ch">{{ o.channel_name }}</text>
						<text class="st" :style="'color:' + statusColor(o.status) + ';background:' + statusColor(o.status) + '1a'">{{ o.status_text }}</text>
					</view>
					<view class="row-mid">
						<text class="amt">{{ fmt(o, o.amount) }}</text>
						<text class="unit">{{ o.asset_label }}</text>
					</view>
					<view class="row-sub">
						<text class="actual">到账 {{ fmt(o, o.actual_amount) }}</text>
						<text class="fee" v-if="feeOf(o) > 0">手续费 {{ feeText(o) }}</text>
					</view>
					<view class="row-bot">
						<text class="time">{{ o.created_at }}</text>
						<text class="no">{{ o.order_no }}</text>
					</view>
				</view>

				<list-empty v-if="!loading && list.length === 0" icon="wallet"
					:title="status === '' ? '还没有' + $noun('withdraw') + '记录' : '该状态下暂无记录'"
					:desc="status === '' ? '申请' + $noun('withdraw') + '后,处理进度会显示在这里' : '换个状态看看'"
					:btn="status === '' ? '去' + $noun('withdraw') : ''" @click="goWithdraw"></list-empty>
				<view class="loadmore" v-if="list.length > 0">{{ noMore ? '没有更多了' : '加载中...' }}</view>
			</view>
		</view>
	</tui-page>
</template>

<script>
import listEmpty from './components/list-empty.vue';

export default {
	components: { listEmpty },
	data() {
		return {
			loading: false,
			list: [],
			page: 1,
			limit: 20,
			noMore: false,
			status: '',
			filters: [
				{ label: '全部', value: '' },
				{ label: '待审核', value: 0 },
				{ label: '待打款', value: 1 },
				{ label: '打款中', value: 2 },
				{ label: '已到账', value: 3 },
				{ label: '已驳回', value: 4 },
				{ label: '打款失败', value: 5 },
				{ label: '已退回', value: 6 }
			]
		};
	},
	onLoad(options) {
		// 标题走后台可配完整词库(pages.json 静态标题取不了词)
		uni.setNavigationBarTitle({ title: this.$noun('withdraw.title_records') });
		// 支持外部链接直达指定状态 tab（如 ?status=2 打款中），见 Plugin.php getLinks
		if (options && options.status !== undefined && options.status !== '') {
			const n = Number(options.status);
			if (this.filters.some(f => f.value === n)) {
				this.status = n;
			}
		}
		this.getList(true);
	},
	onPullDownRefresh() {
		this.getList(true, () => uni.stopPullDownRefresh());
	},
	onReachBottom() {
		if (!this.noMore && !this.loading) {
			this.page++;
			this.getList(false);
		}
	},
	methods: {
		feeOf(o) {
			return Number(o.amount || 0) - Number(o.actual_amount || 0);
		},
		feeText(o) {
			return this.fmt(o, this.feeOf(o));
		},
		goWithdraw() {
			uni.navigateTo({ url: '/plugins2/withdraw/withdraw' });
		},
		fmt(o, v) {
			const p = o.asset_type === 'integral' ? 0 : 2;
			return Number(v || 0).toFixed(p);
		},
		statusColor(s) {
			return { 0: '#909399', 1: '#409EFF', 2: '#E6A23C', 3: '#67C23A', 4: '#F56C6C', 5: '#F56C6C', 6: '#909399' }[s] || '#909399';
		},
		changeStatus(v) {
			this.status = v;
			this.getList(true);
		},
		async getList(reset, done) {
			if (reset) {
				this.page = 1;
				this.noMore = false;
			}
			this.loading = true;
			try {
				const res = await this.$request({
					url: '/plugin/withdraw/api/withdraw/records',
					method: 'get',
					data: { page: this.page, limit: this.limit, status: this.status }
				});
				if (res.code === 0) {
					const rows = res.data.list || [];
					this.list = reset ? rows : this.list.concat(rows);
					if (rows.length < this.limit) this.noMore = true;
				}
			} catch (e) {
				// 静默
			} finally {
				this.loading = false;
				done && done();
			}
		},
		openDetail(o) {
			// 详情走 toast 概要，复杂详情可后续做独立页
			let msg = `${o.status_text}\n申请 ${this.fmt(o, o.amount)}，到账 ${this.fmt(o, o.actual_amount)}`;
			uni.showModal({ title: o.order_no, content: msg, showCancel: false });
		}
	}
};
</script>

<style lang="scss" scoped>
.container { min-height: 100vh; background: #f5f6f8; }
.filter { white-space: nowrap; background: #fff; padding: 20rpx 20rpx 24rpx; }
.filter-item { display: inline-block; padding: 12rpx 30rpx; margin-right: 16rpx; font-size: 26rpx; color: #666; background: #f5f6f8; border-radius: 32rpx; }
.filter-item.active { font-weight: 600; color: var(--theme-color); background: var(--theme-color-bg); }
.list { padding: 24rpx; }
.row { background: #fff; border-radius: 20rpx; padding: 28rpx; margin-bottom: 20rpx; box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.02); }
.row-top { display: flex; justify-content: space-between; align-items: center; }
.row-top .ch { font-size: 28rpx; color: #666; }
.row-top .st { font-size: 22rpx; font-weight: 600; padding: 6rpx 18rpx; border-radius: 20rpx; }
.row-mid { display: flex; align-items: baseline; margin-top: 18rpx; }
.row-mid .amt { font-size: 52rpx; font-weight: 700; color: #222; font-family: 'DIN-Alternate-Bold', sans-serif; }
.row-mid .unit { margin-left: 10rpx; font-size: 24rpx; color: #999; }
.row-sub { display: flex; align-items: center; margin-top: 8rpx; font-size: 24rpx; }
.row-sub .actual { color: #67C23A; }
.row-sub .fee { margin-left: 24rpx; color: #999; }
.row-bot { display: flex; justify-content: space-between; margin-top: 22rpx; padding-top: 18rpx; border-top: 1rpx solid #f2f2f2; font-size: 22rpx; color: #bbb; }
.loadmore { text-align: center; color: #bbb; font-size: 24rpx; padding: 20rpx 0; }

/* H5 里横向 scroll-view 会露出系统滚动条 */
/* #ifdef H5 */
.filter ::v-deep .uni-scroll-view::-webkit-scrollbar,
.filter ::v-deep .uni-scroll-view-content::-webkit-scrollbar { display: none; width: 0; height: 0; }
.filter ::v-deep .uni-scroll-view { scrollbar-width: none; }
/* #endif */
</style>
