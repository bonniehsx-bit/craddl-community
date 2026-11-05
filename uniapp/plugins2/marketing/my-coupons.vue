<template>
	<tui-page :title="'我的' + $noun('coupon')" :is-pulldown="true" @pulldown="onPullDownRefresh" @reach-bottom="onReachBottom">
		<view class="page-container" :style="themeStyle">
			<view class="tabs">
				<view class="tab" :class="{active: status===0}" @click="switchTab(0)">未使用</view>
				<view class="tab" :class="{active: status===1}" @click="switchTab(1)">已使用</view>
				<view class="tab" :class="{active: status===2}" @click="switchTab(2)">已过期</view>
			</view>

			<view class="coupon-list">
				<view class="coupon-card" v-for="item in list" :key="item.id" :class="{gray: status!==0}">
					<view class="card-left">
						<view class="price-wrapper">
							<block v-if="item.type == 0"><text class="symbol">¥</text><text class="price">{{ item.sub_price }}</text></block>
							<block v-else><text class="price">{{ item.discount }}</text><text class="symbol">折</text></block>
						</view>
						<text class="condition">{{ item.min_price > 0 ? '满' + item.min_price + '可用' : '无门槛' }}</text>
					</view>
					<view class="card-right">
						<text class="title">{{ item.name }}</text>
						<text class="date">{{ item.start_time ? item.start_time + ' - ' : '' }}{{ item.end_time }}</text>
					</view>
					<view class="stamp" v-if="status!==0">{{ item.status_text }}</view>
				</view>
			</view>

			<uni-load-more v-if="list.length > 0 || loading" :status="loadStatus" />
			<tui-no-data v-if="!list.length && !loading" :fixed="false">暂无{{ $noun('coupon') }}</tui-no-data>
		</view>
	</tui-page>
</template>

<script>
	// themeColor / themeStyle 由全局 mixin(uniapp/mixins/index.js)提供,勿本地重定义
	export default {
		data() {
			return {
				loading: false,
				status: 0,
				page: 1,
				limit: 15,
				list: [],
				loadStatus: 'loadmore',
				noMore: false,
			}
		},
		onLoad(options) {
			if (options.status !== undefined) this.status = parseInt(options.status);
			this.loadData(true);
		},
		onPullDownRefresh() {
			this.loadData(true).finally(() => uni.stopPullDownRefresh());
		},
		onReachBottom() {
			this.loadData();
		},
		methods: {
			switchTab(s) {
				if (this.status === s) return;
				this.status = s;
				this.loadData(true);
			},
			async loadData(isRefresh = false) {
				if (this.loading) return;
				if (isRefresh) { this.page = 1; this.noMore = false; }
				if (this.noMore) return;
				this.loading = true;
				this.loadStatus = 'loading';
				try {
					const res = await this.$request({
						url: '/plugin/marketing/api/center/my-coupons',
						data: { status: this.status, page: this.page, limit: this.limit }
					});
					if (res.code === 0) {
						const rows = res.data.list || [];
						this.list = isRefresh ? rows : this.list.concat(rows);
						if (rows.length < this.limit) { this.noMore = true; this.loadStatus = 'nomore'; }
						else { this.page++; this.loadStatus = 'loadmore'; }
					} else {
						uni.showToast({ title: res.msg || '加载失败', icon: 'none' });
					}
				} finally {
					this.loading = false;
				}
			}
		}
	}
</script>

<style lang="scss" scoped>
	.page-container { min-height: 100vh; background: #f5f5f7; }
	.tabs { display: flex; background: #fff; position: sticky; top: 0; z-index: 2; }
	.tab { flex: 1; text-align: center; height: 84rpx; line-height: 84rpx; font-size: 28rpx; color: #666; position: relative; }
	.tab.active { color: var(--theme-color); font-weight: 600; }
	.tab.active::after { content: ''; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%); width: 48rpx; height: 6rpx; border-radius: 3rpx; background: var(--theme-color); }

	.coupon-list { padding: 20rpx 24rpx; }
	.coupon-card { display: flex; align-items: center; background: #fff; border-radius: 16rpx; padding: 28rpx 24rpx; margin-bottom: 20rpx; position: relative; overflow: hidden; }
	.coupon-card.gray { opacity: .6; }
	.card-left { width: 200rpx; display: flex; flex-direction: column; align-items: center; border-right: 1px dashed #eee; }
	.price-wrapper { color: var(--theme-color); display: flex; align-items: baseline; }
	.symbol { font-size: 28rpx; }
	.price { font-size: 56rpx; font-weight: 700; }
	.condition { font-size: 22rpx; color: #999; margin-top: 6rpx; }
	.card-right { flex: 1; padding-left: 24rpx; display: flex; flex-direction: column; }
	.title { font-size: 30rpx; color: #222; font-weight: 500; }
	.date { font-size: 22rpx; color: #999; margin-top: 10rpx; }
	.stamp { position: absolute; right: 20rpx; top: 20rpx; font-size: 22rpx; color: #bbb; border: 1px solid #ddd; border-radius: 20rpx; padding: 2rpx 14rpx; }
</style>
