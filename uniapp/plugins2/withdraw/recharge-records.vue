<template>
	<tui-page title="充值记录">
		<view class="container" :style="themeStyle">

			<view class="filter-bar">
				<view
					v-for="(f, i) in filters"
					:key="i"
					class="filter-item"
					:class="f.value === includePending ? 'active' : ''"
					:style="f.value === includePending ? 'color:var(--theme-color);' : ''"
					@click="switchFilter(f.value)">
					{{ f.label }}
					<view class="filter-line" v-if="f.value === includePending"
					      :style="'background:' + themeColor"></view>
				</view>
			</view>

			<view class="list" v-if="list.length">
				<view class="item" v-for="(r, i) in list" :key="r.id">
					<view class="item-head">
						<text class="item-title">{{ r.plan_title || '账户充值' }}</text>
						<text class="item-status"
						      :style="r.status === 1 ? 'color:var(--theme-color);background:var(--theme-color-bg)' : 'color:#999;background:#f3f3f3'">
							{{ r.status_text }}
						</text>
					</view>
					<view class="item-row">
						<text class="lbl">支付金额</text>
						<text class="val">¥{{ r.pay_amount }}</text>
					</view>
					<view class="item-row">
						<text class="lbl">到账</text>
						<text class="val">{{ r.total_gain }} {{ r.asset_label }}
							<text class="gift" v-if="Number(r.gift_amount) > 0">含赠送 {{ r.gift_amount }}</text>
						</text>
					</view>
					<view class="item-foot">
						<text>{{ r.created_at }}</text>
						<text v-if="r.order_no">单号 {{ r.order_no }}</text>
					</view>
				</view>
			</view>

			<list-empty v-else-if="!loading" icon="wallet"
				:title="includePending === 0 ? '还没有已到账的充值' : '还没有充值记录'"
				:desc="includePending === 0 ? '支付成功后,充值会显示在这里' : '充值后,记录会显示在这里'"
				btn="去充值" @click="goRecharge"></list-empty>

			<view class="load-more" v-if="list.length < count" @click="loadMore">加载更多</view>
		</view>
	</tui-page>
</template>

<script>
import listEmpty from './components/list-empty.vue';

export default {
	components: { listEmpty },
	data() {
		return {
			loading: true,
			list: [],
			count: 0,
			page: 1,
			includePending: 0,
			filters: [
				{ label: '已到账', value: 0 },
				{ label: '全部', value: 1 }
			]
		}
	},
	onLoad() {
		this.load();
	},
	onShow() {
		// 从支付页回来要能立刻看到新记录
		if (!this.loading) this.switchFilter(this.includePending);
	},
	methods: {
		goRecharge() {
			uni.navigateTo({ url: '/plugins2/withdraw/recharge' });
		},
		switchFilter(v) {
			this.includePending = v;
			this.page = 1;
			this.list = [];
			this.load();
		},
		load() {
			this.loading = true;
			this.$request({
				url: '/plugin/withdraw/api/recharge/records',
				data: { page: this.page, include_pending: this.includePending }
			}).then(res => {
				if (res.code !== 0) {
					return uni.showToast({ title: res.msg || '加载失败', icon: 'none' });
				}
				this.list = this.page === 1 ? (res.data.list || []) : this.list.concat(res.data.list || []);
				this.count = res.data.count || 0;
			}).finally(() => { this.loading = false; });
		},
		loadMore() {
			this.page++;
			this.load();
		}
	}
}
</script>

<style lang="scss" scoped>
.container { min-height: 100vh; background: #f5f6f8; }

.filter-bar { display: flex; background: #fff; padding: 0 24rpx; }
.filter-item {
	position: relative; padding: 28rpx 32rpx;
	font-size: 28rpx; color: #666;
}
.filter-item.active { font-weight: 600; }
.filter-line {
	position: absolute; left: 50%; bottom: 0; transform: translateX(-50%);
	width: 40rpx; height: 4rpx; border-radius: 2rpx;
}

.list { padding: 24rpx; }
.item {
	background: #fff; border-radius: 20rpx; padding: 28rpx; margin-bottom: 20rpx; box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.02);
	.item-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20rpx; }
	.item-title { font-size: 30rpx; font-weight: 600; color: #333; }
	.item-status { font-size: 22rpx; font-weight: 600; padding: 6rpx 18rpx; border-radius: 20rpx; }
	.item-row { display: flex; justify-content: space-between; font-size: 26rpx; margin-bottom: 12rpx; }
	.lbl { color: #999; }
	.val { color: #333; }
	.gift { font-size: 22rpx; color: #ff8c42; margin-left: 12rpx; }
	.item-foot {
		display: flex; justify-content: space-between;
		font-size: 22rpx; color: #bbb; margin-top: 16rpx;
		border-top: 1rpx solid #f2f2f2; padding-top: 16rpx;
	}
}

.load-more { text-align: center; font-size: 26rpx; color: #999; padding: 32rpx 0 60rpx; }
</style>
