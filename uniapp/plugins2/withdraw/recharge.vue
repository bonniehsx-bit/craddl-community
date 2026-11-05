<template>
	<tui-page title="账户充值">
		<view class="container" :style="themeStyle">

			<!-- 顶部：当前余额概览 -->
			<view class="asset-card">
				<view class="asset-content">
					<view class="asset-label">当前可用{{ currentAssetLabel }}</view>
					<view class="asset-value">{{ currentBalance }}</view>
				</view>
				<view class="decor-circle"></view>
			</view>

			<!-- 充值商品切换：配了多个充值商品（如「余额充值」「积分充值」）时才出现 -->
			<view class="asset-tabs" v-if="goodsList.length > 1">
				<view
					v-for="(g, i) in goodsList"
					:key="g.goods_id"
					class="asset-tab"
					:class="g.goods_id === currentGoodsId ? 'active' : ''"
					:style="g.goods_id === currentGoodsId ? 'background:var(--theme-color-bg);color:var(--theme-color);' : ''"
					@click="switchGoods(g.goods_id)">
					{{ g.name }}
				</view>
			</view>

			<!-- 档位:横向滑动。竖排网格放不下"分期"标记,而分期正是这次要突出的卖点 -->
			<scroll-view class="plan-scroll" scroll-x v-if="visiblePlans.length" :show-scrollbar="false">
				<view class="plan-row">
					<view
						v-for="(p, i) in visiblePlans"
						:key="p.id"
						class="plan-item"
						:class="selectedId === p.id ? 'selected' : ''"
						:style="selectedId === p.id ? 'border-color:var(--theme-color);background:var(--theme-color-bg);' : ''"
						@click="selectedId = p.id">
						<view class="plan-gain" :style="selectedId === p.id ? 'color:var(--theme-color)' : ''">
							{{ p.gain_amount }}<text class="plan-unit">{{ currentGoods.asset_label }}</text>
						</view>
						<view class="plan-gift" v-if="p.has_gift || (p.gift_periods && p.gift_periods.length)">
							<text class="fq-tag" v-if="p.gift_periods && p.gift_periods.length">分期</text>
							<text v-if="p.has_gift">送 {{ p.gift_amount }}</text>
							<text v-else>有赠品</text>
						</view>
						<view class="plan-pay">¥{{ p.pay_amount }}</view>
						<view class="plan-remark" v-if="p.title">{{ p.title }}</view>
						<view class="plan-tick" v-if="selectedId === p.id"
							:style="'background:' + themeColor">✓</view>
					</view>
				</view>
			</scroll-view>

			<!-- 分期赠品明细:选中档位后展开。这是分期玩法的核心展示 —— 不展开的话
			     用户只知道"送得少了",感受不到后面还有 -->
			<view class="gift-box" v-if="selectedPeriods.length">
				<view class="gift-arrow"></view>
				<view class="gift-title">赠品分 {{ selectedPeriods.length }} 期</view>
				<scroll-view scroll-x :show-scrollbar="false">
					<view class="gift-row">
						<view class="gift-item" v-for="(g, gi) in selectedPeriods" :key="gi">
							<view class="gift-content">{{ g.content }}<text class="gift-unit" v-if="g.unit">{{ g.unit }}</text></view>
							<view class="gift-timing" :class="g.is_now ? 'now' : ''">{{ g.timing }}</view>
						</view>
					</view>
				</scroll-view>
				<view class="gift-note">到账时间从充值成功当天算起，每期到账都会通知你</view>
			</view>

			<!-- 空态文案由后端下发:前端拿到的只是个空数组,判不出是「还没配置」
			     还是「配了但当前身份买不了」。后端不回时退回原来那两句。 -->
			<view class="empty" v-else-if="!loading">
				<text class="empty-icon">{{ emptyReason === 'identity' ? '🔒' : '📭' }}</text>
				<text class="empty-text">{{ emptyTitle }}</text>
				<text class="empty-sub">{{ emptySub }}</text>
			</view>

			<!-- 说明 -->
			<view class="notice" v-if="visiblePlans.length">
				<view class="notice-title">充值说明</view>
				<view class="notice-line" v-if="currentGoods.remark">· {{ currentGoods.remark }}</view>
				<view class="notice-line">· 支付成功后额度立即到账，可在「充值记录」中查看</view>
				<view class="notice-line" v-if="selectedPeriods.length">· 赠品按上方计划分期到账，可在「充值记录」中查看每期状态</view>
				<view class="notice-line">· 充值到账的额度不支持原路退回，请按需购买</view>
			</view>

			<!-- 底部下单 -->
			<view class="footer-action" v-if="visiblePlans.length">
				<view class="pay-summary" v-if="selectedPlan">
					<text>应付</text>
					<text class="pay-amount" :style="'color:' + themeColor">¥{{ selectedPlan.pay_amount }}</text>
					<text class="pay-gain">到账 {{ selectedPlan.total_gain }} {{ currentGoods.asset_label }}</text>
				</view>
				<button
					class="submit-btn"
					:loading="paying"
					:disabled="!canPay || paying"
					:style="'background:' + (canPay ? themeColor : '#E0E0E0') + ';color:' + (canPay ? '#fff' : '#999')"
					@click="submit">
					立即充值
				</button>
				<view class="records-link" @click="goRecords">查看充值记录 ></view>
			</view>

		</view>
		<!-- 支付弹窗(全局组件):在本页直接付款,不跳收银台页 -->
		<jx-pay-sheet ref="paySheet"></jx-pay-sheet>
	</tui-page>
</template>

<script>
import paySheet from '@/utils/paySheet.js';
export default {
	data() {
		return {
			loading: true,
			paying: false,
			// 充值商品列表，每个商品下挂它自己的档位
			// （一个商品 = 一种到账资产，如「余额充值」「积分充值」）
			goodsList: [],
			// 链接里带来的期望商品(?goods_id=),0 = 没指定
			wantGoodsId: 0,
			currentGoodsId: 0,
			selectedId: 0,
			user: null,
			// 列表为空时后端给的原因与文案(empty_reason / empty_title / empty_sub)
			emptyReason: '',
			emptyTitle: '暂无可用的充值档位',
			emptySub: '请联系管理员在后台配置'
		}
	},
	computed: {
		currentGoods() {
			return this.goodsList.find(g => g.goods_id === this.currentGoodsId) || {};
		},
		visiblePlans() {
			return (this.currentGoods.plans) || [];
		},
		// 当前档位的分期赠品。老后端没下发时是空数组,整块不显示
		selectedPeriods() {
			const p = this.selectedPlan;
			return (p && Array.isArray(p.gift_periods)) ? p.gift_periods : [];
		},
		selectedPlan() {
			return this.visiblePlans.find(p => p.id === this.selectedId) || null;
		},
		currentAssetLabel() {
			return this.currentGoods.asset_label || '余额';
		},
		currentBalance() {
			if (!this.user) return '0.00';
			const map = { balance: this.user.money, price: this.user.price, integral: this.user.integral };
			const v = map[this.currentGoods.asset_type];
			// 扩展钱包的余额不在 user 上，这里不猜，留白让用户去钱包页看
			return v === undefined || v === null ? '--' : v;
		},
		canPay() {
			return !!this.selectedPlan && !!this.currentGoodsId;
		}
	},
	onLoad(options) {
		// 支持外部链接直达指定充值商品(如 ?goods_id=12),见 Plugin.php getLinks。
		// 只记下期望值,能不能选中要等商品列表回来再判断 —— 商品可能已下架或被删。
		this.wantGoodsId = (options && options.goods_id) ? Number(options.goods_id) : 0;
		this.loadUser();
		this.loadGoods();
	},
	onShow() {
		// 支付回来要刷新余额
		if (!this.loading) this.loadUser();
	},
	methods: {
		loadUser() {
			this.$request({ url: '/api/user/index' }).then(res => {
				if (res.code === 0) this.user = res.data.user || res.data;
			}).catch(() => {});
		},
		loadGoods() {
			this.loading = true;
			this.$request({ url: '/plugin/withdraw/api/recharge/plans' }).then(res => {
				if (res.code !== 0) {
					return uni.showToast({ title: res.msg || '加载失败', icon: 'none' });
				}
				this.goodsList = res.data.list || [];
				this.emptyReason = res.data.empty_reason || '';
				if (res.data.empty_title) this.emptyTitle = res.data.empty_title;
				if (res.data.empty_sub) this.emptySub = res.data.empty_sub;
				if (this.goodsList.length) {
					// 链接指定的商品在列表里才用它;下架/删掉/不属于本商城时退回第一个,
					// 不能让用户对着一个空页面发愣
					const wanted = this.goodsList.find(g => Number(g.goods_id) === this.wantGoodsId);
					this.switchGoods(wanted ? wanted.goods_id : this.goodsList[0].goods_id);
				}
			}).finally(() => { this.loading = false; });
		},
		switchGoods(goodsId) {
			this.currentGoodsId = goodsId;
			const plans = this.visiblePlans;
			this.selectedId = plans.length ? plans[0].id : 0;
		},
		submit() {
			if (!this.canPay || this.paying) return;
			this.paying = true;
			const backUrl = encodeURIComponent('/plugins2/withdraw/recharge');
			this.$request({
				url: '/api/order/submit',
				method: 'post',
				data: {
					plugin: 'withdraw',
					type: 'withdraw_recharge',
					form_data: JSON.stringify({
						// goods_id 与 plan_id 都要传：后端会校验档位确实属于这个商品，
						// 否则可以拿 A 商品下单、传 B 商品的高额档位（越权口子）
						goods_list: [{ goods_id: this.currentGoodsId, num: 1 }],
						plan_id: this.selectedId
					})
				}
			}).then(res => {
				if (res.code !== 0) {
					this.paying = false;
					return uni.showModal({ content: res.msg, showCancel: false });
				}
				if (res.data.queue_id) {
					this.pollOrder(res.data.queue_id, backUrl);
				} else if (res.data.pay_order_id || res.data.order_no) {
					this.paying = false;
					paySheet(this, { payOrderId: res.data.pay_order_id, orderNo: res.data.order_no, onPaid: () => this.loadUser() });
				} else {
					this.paying = false;
					uni.showToast({ title: '下单返回异常', icon: 'none' });
				}
			}).catch(() => {
				this.paying = false;
				uni.showToast({ title: '网络异常', icon: 'none' });
			});
		},
		// 下单是异步入队的，轮询拿真实订单号再去支付
		pollOrder(queueId, backUrl, count = 0) {
			if (count > 20) {
				this.paying = false;
				return uni.showToast({ title: '系统繁忙，请稍后重试', icon: 'none' });
			}
			this.$request({ url: '/api/order/submit-status', data: { queue_id: queueId } }).then(res => {
				if (res.code === 0 && res.data && (res.data.pay_order_id || res.data.order_no)) {
					this.paying = false;
					return paySheet(this, { payOrderId: res.data.pay_order_id, orderNo: res.data.order_no, onPaid: () => this.loadUser() });
				}
				if (res.code !== 0) {
					this.paying = false;
					return uni.showModal({ content: res.msg || '下单失败', showCancel: false });
				}
				setTimeout(() => this.pollOrder(queueId, backUrl, count + 1), 500);
			}).catch(() => {
				this.paying = false;
				uni.showToast({ title: '网络异常', icon: 'none' });
			});
		},
		goRecords() {
			uni.navigateTo({ url: '/plugins2/withdraw/recharge-records' });
		}
	}
}
</script>

<style lang="scss" scoped>
.container { min-height: 100vh; background: #f5f6f8; padding: 24rpx; }

.asset-card {
	position: relative; overflow: hidden;
	background: linear-gradient(135deg, var(--theme-color), var(--theme-color-light));
	border-radius: 24rpx; padding: 40rpx 32rpx; color: #fff; margin-bottom: 24rpx;
	.asset-label { font-size: 24rpx; opacity: .9; }
	.asset-value { font-size: 56rpx; font-weight: 700; margin-top: 12rpx; }
	.decor-circle {
		position: absolute; right: -40rpx; top: -40rpx;
		width: 200rpx; height: 200rpx; border-radius: 50%;
		background: rgba(255, 255, 255, .12);
	}
}

.asset-tabs { display: flex; gap: 16rpx; margin-bottom: 24rpx; }
.asset-tab {
	padding: 12rpx 32rpx; border-radius: 999rpx; font-size: 26rpx;
	background: #fff; color: #666;
}

/* 档位横向滑动:竖排网格放不下「分期」标记,而分期正是这次要突出的卖点 */
.plan-scroll { width: 100%; white-space: nowrap; margin-bottom: 20rpx; }
.plan-row { display: inline-flex; gap: 20rpx; padding: 8rpx 4rpx 4rpx; }
.plan-scroll .plan-item { flex: 0 0 210rpx; width: 210rpx; position: relative; overflow: hidden; }
.fq-tag {
	display: inline-block; background: #FFE6CC; color: #C2410C;
	font-size: 18rpx; padding: 2rpx 8rpx; border-radius: 6rpx; margin-right: 8rpx;
}
.plan-tick {
	position: absolute; right: 0; bottom: 0; width: 40rpx; height: 40rpx;
	line-height: 40rpx; text-align: center; color: #fff; font-size: 24rpx;
	border-radius: 20rpx 0 12rpx 0;
}

/* 分期赠品明细:选中档位后展开 —— 不展开的话用户只知道「送得少了」,感受不到后面还有 */
.gift-box {
	position: relative; background: #FFF3E4; border-radius: 20rpx;
	padding: 24rpx 20rpx 20rpx; margin-bottom: 24rpx;
}
.gift-arrow {
	position: absolute; top: -14rpx; left: 60rpx; width: 0; height: 0;
	border-left: 14rpx solid transparent; border-right: 14rpx solid transparent;
	border-bottom: 14rpx solid #FFF3E4;
}
.gift-title { font-size: 28rpx; font-weight: bold; color: #C2410C; margin-bottom: 18rpx; }
.gift-row { display: inline-flex; gap: 16rpx; padding-bottom: 4rpx; }
.gift-item {
	flex: 0 0 auto; min-width: 170rpx; background: #fff;
	border: 2rpx dashed #F0C89A; border-radius: 14rpx; padding: 18rpx 16rpx; text-align: center;
}
.gift-content { font-size: 28rpx; font-weight: bold; color: #C2410C; }
.gift-unit { font-size: 22rpx; font-weight: normal; margin-left: 4rpx; }
.gift-timing { font-size: 20rpx; color: #A16207; margin-top: 8rpx; }
.gift-timing.now { color: #C2410C; font-weight: bold; }
.gift-note { font-size: 20rpx; color: #B8860B; margin-top: 16rpx; }

.plan-grid { display: flex; flex-wrap: wrap; gap: 20rpx; }
.plan-item {
	width: calc((100% - 40rpx) / 3);
	box-sizing: border-box;
	background: #fff; border-radius: 20rpx; padding: 28rpx 12rpx;
	text-align: center; border: 2rpx solid transparent;
	.plan-gain { font-size: 36rpx; font-weight: 700; color: #333; }
	.plan-unit { font-size: 22rpx; font-weight: 400; margin-left: 4rpx; }
	.plan-gift {
		display: inline-block; margin-top: 8rpx; padding: 2rpx 12rpx;
		font-size: 20rpx; color: #ff8c42; background: #fff3e6; border-radius: 8rpx;
	}
	.plan-pay { font-size: 26rpx; color: #999; margin-top: 12rpx; }
	.plan-remark { font-size: 20rpx; color: #bbb; margin-top: 6rpx; }
}

.empty { text-align: center; padding: 120rpx 0; }
.empty-icon { display: block; font-size: 64rpx; line-height: 1; margin-bottom: 24rpx; }
.empty-text { display: block; font-size: 28rpx; color: #999; }
.empty-sub { display: block; font-size: 24rpx; color: #ccc; margin-top: 12rpx; }

.notice {
	margin-top: 32rpx; background: #fff; border-radius: 20rpx; padding: 28rpx;
	.notice-title { font-size: 26rpx; font-weight: 600; color: #333; margin-bottom: 12rpx; }
	.notice-line { font-size: 22rpx; color: #999; line-height: 1.9; }
}

.footer-action { margin-top: 40rpx; padding-bottom: 40rpx; }
.pay-summary {
	display: flex; align-items: baseline; gap: 12rpx;
	font-size: 26rpx; color: #666; margin-bottom: 20rpx;
	.pay-amount { font-size: 40rpx; font-weight: 700; }
	.pay-gain { font-size: 22rpx; color: #999; margin-left: auto; }
}
.submit-btn {
	width: 100%; height: 88rpx; line-height: 88rpx;
	border-radius: 44rpx; font-size: 30rpx; border: none;
}
.records-link { text-align: center; font-size: 24rpx; color: #999; margin-top: 24rpx; }
</style>
