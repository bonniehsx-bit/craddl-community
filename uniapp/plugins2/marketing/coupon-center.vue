<template>
	<tui-page title="领券中心" :is-pulldown="true" @pulldown="onPullDownRefresh">
		<view class="page-container" :style="themeStyle">
			<view class="topbar">
				<text class="topbar-title">精选{{ $noun('coupon') }}</text>
				<view class="entries">
					<view class="mine-entry" @click="goProgress">
						<text class="mine-text">奖励进度</text>
					</view>
					<view class="mine-entry" @click="goMine">
						<tui-icon name="ticket" :size="15" color="var(--theme-color)"></tui-icon>
						<text class="mine-text">我的{{ $noun('coupon') }}</text>
					</view>
				</view>
			</view>

			<view class="campaign-list">
				<view class="campaign-card" v-for="item in list" :key="item.campaign_id">
					<view class="card-head">
						<text class="c-name">{{ item.name }}</text>
						<text class="c-end" v-if="item.end_at">{{ item.end_at }} 截止</text>
					</view>
					<view class="coupon-row" v-for="(c,ci) in item.rows" :key="ci">
						<view class="c-left">
							<text class="c-desc">{{ c.title }}</text>
							<text class="c-sub" v-if="c.sub">{{ c.sub }}</text>
						</view>
						<text class="c-num" v-if="c.num > 1">×{{ c.num }}</text>
					</view>
					<view class="card-foot">
						<button v-if="item.can_claim" class="btn claim" @click="receive(item)">{{ item.claim_msg || '立即领取' }}</button>
						<button v-else class="btn disabled">{{ item.claim_msg }}</button>
					</view>
				</view>
			</view>

			<uni-load-more v-if="list.length > 0 || loading" :status="loadStatus" />
			<tui-no-data v-if="!list.length && !loading" :fixed="false">暂无可领取{{ $noun('coupon') }}</tui-no-data>
		</view>
	</tui-page>
</template>

<script>
	// themeColor / themeStyle 由全局 mixin(uniapp/mixins/index.js)提供,勿本地重定义
	export default {
		data() {
			return {
				loading: false,
				list: [],
				loadStatus: 'loadmore',
				claiming: false,
			}
		},
		onLoad() {
			this.loadData();
		},
		onPullDownRefresh() {
			this.loadData().finally(() => uni.stopPullDownRefresh());
		},
		methods: {
			async loadData() {
				this.loading = true;
				this.loadStatus = 'loading';
				try {
					const res = await this.$request({ url: '/plugin/marketing/api/center/list', data: {} });
					if (res.code === 0) {
						this.list = (res.data.list || []).map(item => Object.assign({}, item, { rows: this.rewardRows(item) }));
					} else {
						uni.showToast({ title: res.msg || '加载失败', icon: 'none' });
					}
				} finally {
					this.loading = false;
					this.loadStatus = 'nomore';
				}
			},
			async receive(item) {
				if (this.claiming) return;
				this.claiming = true;
				try {
					const res = await this.$request({
						url: '/plugin/marketing/api/center/receive',
						method: 'post',
						data: { campaign_id: item.campaign_id }
					});
					uni.showToast({ title: res.msg || (res.code === 0 ? '领取成功' : '领取失败'), icon: res.code === 0 ? 'success' : 'none' });
					if (res.code === 0) this.loadData();
				} finally {
					this.claiming = false;
				}
			},
			// 奖励行展示文案:新接口给 rewards(券/积分/余额),旧接口只有 coupons
			rewardRows(item) {
				const rewards = item.rewards || (item.coupons || []).map(c => Object.assign({ reward_type: 'coupon' }, c));
				return rewards.map(r => {
					if (r.reward_type === 'integral') {
						return { title: r.amount + this.$noun('integral'), sub: '', num: 1 };
					}
					if (r.reward_type === 'balance') {
						return { title: r.amount + '元' + this.$noun('balance'), sub: '', num: 1 };
					}
					return { title: r.name, sub: r.desc, num: r.num };
				});
			},
			goMine() {
				uni.navigateTo({ url: '/plugins2/marketing/my-coupons' });
			},
			goProgress() {
				uni.navigateTo({ url: '/plugins2/marketing/reward-progress' });
			}
		}
	}
</script>

<style lang="scss" scoped>
	.page-container { padding: 20rpx 24rpx 40rpx; min-height: 100vh; background: #f5f5f7; }
	.topbar { display: flex; align-items: center; justify-content: space-between; padding: 8rpx 6rpx 20rpx; }
	.topbar-title { font-size: 32rpx; font-weight: 600; color: #222; }
	.entries { display: flex; align-items: center; }
	.mine-entry { display: flex; align-items: center; margin-left: 28rpx; }
	.mine-text { font-size: 26rpx; color: var(--theme-color); margin-left: 6rpx; }

	.campaign-card { background: #fff; border-radius: 16rpx; padding: 24rpx; margin-bottom: 20rpx; box-shadow: 0 2rpx 12rpx rgba(0,0,0,.04); }
	.card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16rpx; }
	.c-name { font-size: 30rpx; font-weight: 600; color: #222; }
	.c-end { font-size: 22rpx; color: #999; }

	.coupon-row { display: flex; align-items: center; justify-content: space-between; padding: 14rpx 18rpx; margin-bottom: 12rpx;
		background: var(--theme-color); border-radius: 12rpx; opacity: .96; }
	.c-left { display: flex; flex-direction: column; }
	.c-desc { font-size: 28rpx; color: #fff; font-weight: 500; }
	.c-sub { font-size: 22rpx; color: rgba(255,255,255,.85); margin-top: 4rpx; }
	.c-num { font-size: 26rpx; color: #fff; }

	.card-foot { margin-top: 6rpx; }
	.btn { width: 100%; height: 72rpx; line-height: 72rpx; border-radius: 36rpx; font-size: 28rpx; border: none; }
	.btn.claim { background: var(--theme-color); color: #fff; }
	.btn.disabled { background: #e5e5e5; color: #999; }
</style>
