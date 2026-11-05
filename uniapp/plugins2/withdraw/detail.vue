<template>
	<tui-page :showBackBtn="true" :title="title">
		<view class="container" :style="themeStyle">
			<view class="list">
				<view class="row" v-for="(o, i) in list" :key="i">
					<view class="ico" :class="o.is_income == 1 ? 'in' : 'out'">
						<uni-icons :type="o.is_income == 1 ? 'arrow-down' : 'arrow-up'" size="18"
							:color="o.is_income == 1 ? '#67C23A' : '#F56C6C'"></uni-icons>
					</view>
					<view class="row-left">
						<text class="title">{{ o.title || '—' }}</text>
						<text class="remark" v-if="o.remark">{{ o.remark }}</text>
						<text class="time">{{ o.created_at }}</text>
					</view>
					<view class="row-right">
						<text class="amt" :style="'color:' + (o.is_income == 1 ? '#67C23A' : '#F56C6C')">{{ signed(o) }}</text>
						<text class="bal" v-if="o.balance_after !== ''">余 {{ o.balance_after }}</text>
					</view>
				</view>

				<list-empty v-if="!loading && list.length === 0" icon="list" title="暂无明细记录"
					desc="有收入或支出后,会按时间显示在这里"></list-empty>
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
			assetType: '',
			title: '',
			nameSet: false
		};
	},
	onLoad(options) {
		this.assetType = (options && options.asset_type) || '';
		const suffix = this.$noun('withdraw.detail_suffix');
		if (options && options.name) {
			this.title = decodeURIComponent(options.name) + suffix;
			this.nameSet = true;
		} else {
			this.title = suffix;
		}
		// 通用明细页:标题=钱包名(路由传入)+ 可配后缀词。原生标题同步(pages.json 静态标题取不了词)
		uni.setNavigationBarTitle({ title: this.title });
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
		// 接口金额可能已带正负号,没带的按收入/支出补上
		signed(o) {
			const a = String(o.amount);
			if (a.charAt(0) === '+' || a.charAt(0) === '-') return a;
			return (o.is_income == 1 ? '+' : '-') + a;
		},
		async getList(reset, done) {
			if (reset) {
				this.page = 1;
				this.noMore = false;
			}
			this.loading = true;
			try {
				const res = await this.$request({
					url: '/plugin/withdraw/api/wallet/logs',
					method: 'get',
					data: { asset_type: this.assetType, page: this.page, limit: this.limit }
				});
				if (res.code === 0) {
					const rows = res.data.list || [];
					this.list = reset ? rows : this.list.concat(rows);
					if (rows.length < this.limit) this.noMore = true;
					// 标题以接口返回的钱包名为准(未带 name 参数时)
					if (res.data.name && !this.nameSet) { this.title = res.data.name + this.$noun('withdraw.detail_suffix'); this.nameSet = true; }
				}
			} catch (e) {
				// 静默
			} finally {
				this.loading = false;
				done && done();
			}
		}
	}
};
</script>

<style lang="scss" scoped>
.container { min-height: 100vh; background: #f5f6f8; }
.list { padding: 24rpx; }
.row { background: #fff; border-radius: 20rpx; padding: 26rpx 28rpx; margin-bottom: 16rpx; display: flex; align-items: center; box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.02); }
.ico { width: 72rpx; height: 72rpx; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-right: 22rpx; }
.ico.in { background: rgba(103, 194, 58, 0.12); }
.ico.out { background: rgba(245, 108, 108, 0.12); }
.row-left { display: flex; flex-direction: column; flex: 1; min-width: 0; padding-right: 20rpx; }
.row-left .title { font-size: 28rpx; color: #333; font-weight: 500; }
.row-left .remark { font-size: 24rpx; color: #999; margin-top: 8rpx; }
.row-left .time { font-size: 22rpx; color: #bbb; margin-top: 8rpx; }
.row-right { display: flex; flex-direction: column; align-items: flex-end; flex-shrink: 0; }
.row-right .amt { font-size: 34rpx; font-weight: 700; font-family: 'DIN-Alternate-Bold', sans-serif; }
.row-right .bal { font-size: 22rpx; color: #bbb; margin-top: 8rpx; }
.loadmore { text-align: center; color: #bbb; font-size: 24rpx; padding: 20rpx 0; }
</style>
