<template>
	<!-- 分销中心:版式和代理中心(plugins/dealer/index)同一套 —— 主题色导航 + 渐变头部,收益卡压在头部上 -->
	<tui-page title="分销中心" :navBarBgColor="themeColor" titleColor="#ffffff">
		<view class="dist-index" :style="themeStyle">
			<!-- 头部:头像、昵称、等级 -->
			<view class="hero">
				<view class="me">
					<image class="avatar" :src="summary.avatar_url || defaultAvatar" mode="aspectFill"></image>
					<view class="me-info">
						<view class="nickname">{{ summary.nickname || '' }}</view>
						<view v-if="isDistributor && summary.level_name" class="level-tag">
							<text class="star">★</text>{{ summary.level_name }}
						</view>
					</view>
				</view>
			</view>

			<!-- 没有推广资格 / 资格被取消:说清楚为什么这里是空的,给出路(问客服 / 先去逛逛),不展示收益 -->
			<view v-if="loaded && !isDistributor" class="card join">
				<view class="join-badge">
					<tui-icon name="member-fill" :size="30" color="#ffffff"></tui-icon>
				</view>
				<block v-if="isDisabled">
					<view class="join-title">推广资格已暂停</view>
					<!-- 口径见 DistributionListener / DistributionSettleService::canSettle:资格取消后新单不计、还没结算的到结算时作废 -->
					<view class="join-desc">暂停期间新订单不再计{{ $noun('commission') }}，还没结算的{{ $noun('commission') }}也不会再发放；已结算的不受影响。如有疑问请联系客服</view>
					<view class="join-link" @click="navTo('/plugins/distribution/order')">查看{{ $noun('commission') }}明细</view>
				</block>
				<block v-else>
					<view class="join-title">还不是推广员也没关系</view>
					<view class="join-desc">这里是分销中心，仅对已开通推广资格的用户开放</view>
					<view class="steps">
						<view class="steps-head">如何开通</view>
						<view v-for="(st, i) in steps" :key="i" class="step">
							<view class="step-no">{{ i + 1 }}</view>
							<view class="step-text">
								<view class="step-title">{{ st.title }}</view>
								<view class="step-sub">{{ st.sub }}</view>
							</view>
						</view>
					</view>
				</block>

				<tui-cs-wrapper class="join-cs" block>
					<view class="join-btn primary tall">
						<view class="join-btn-ico"><tui-icon name="kefu" :size="20" color="#ffffff"></tui-icon></view>
						<view class="join-btn-text">
							<view class="join-btn-title">联系客服</view>
							<view class="join-btn-sub">{{ isDisabled ? '咨询推广资格' : '了解如何开通' }}</view>
						</view>
					</view>
				</tui-cs-wrapper>
				<view class="join-btn ghost" @click="goHome">先去逛逛</view>
			</view>

			<block v-else-if="isDistributor">
				<!-- 收益卡:待结算最大,下面一行已结算 / 本月 / 我的客户 -->
				<view class="card money">
					<view class="money-head">
						<text class="money-label">待结算{{ $noun('commission') }}(元)</text>
						<view class="money-link" @click="navTo('/plugins/distribution/order')">
							<text>{{ $noun('commission') }}明细</text>
							<tui-icon name="arrowright" :size="13" color="var(--theme-color)"></tui-icon>
						</view>
					</view>
					<view class="money-big"><text class="cur">¥</text>{{ money(summary.unsettled_commission) }}</view>
					<view class="money-tip">{{ moneyTip }}</view>
					<view class="stats">
						<view class="stat">
							<view class="stat-num">{{ money(summary.settled_commission) }}</view>
							<view class="stat-label">已结算(元)</view>
						</view>
						<view class="stat">
							<view class="stat-num">{{ money(summary.month_commission) }}</view>
							<view class="stat-label">本月{{ $noun('commission') }}(元)</view>
						</view>
						<view class="stat" @click="navTo('/others/team/team')">
							<view class="stat-num">{{ summary.customer_count || 0 }}</view>
							<view class="stat-label">我的客户</view>
						</view>
					</view>
					<!-- 提现中心:提现插件开了这个账户的入口才显示。能提现的是已结算到账户里的,不是上面的「待结算」 -->
					<view v-if="withdrawOn" class="wd" @click="goWithdraw">
						<view class="wd-ico"><tui-icon name="wallet" :size="22" color="var(--theme-color)"></tui-icon></view>
						<view class="wd-text">
							<view class="wd-title">{{ $noun('withdraw') }}中心</view>
							<view class="wd-sub">查看已结算的{{ withdrawTarget }}，申请{{ $noun('withdraw') }}</view>
						</view>
						<view class="wd-btn">去{{ $noun('withdraw') }}</view>
					</view>
				</view>

				<!-- 主行动:推广海报 + 我的客户 -->
				<view class="cta">
					<view class="cta-main" @click="navTo('/others/share-poster/share-poster')">
						<view class="cta-icon"><tui-icon name="qrcode" :size="24" color="#ffffff"></tui-icon></view>
						<view>
							<view class="cta-title">推广海报</view>
							<view class="cta-sub">生成专属海报邀请好友</view>
						</view>
					</view>
					<view class="cta-side" @click="navTo('/others/team/team')">
						<view class="cta-icon light"><tui-icon name="people" :size="24" color="var(--theme-color)"></tui-icon></view>
						<view>
							<view class="cta-title dark">我的客户</view>
							<view class="cta-sub gray">{{ summary.customer_count || 0 }} 人</view>
						</view>
					</view>
				</view>

				<!-- 最近佣金 -->
				<view class="card recent">
					<view class="card-head">
						<text class="card-title">最近{{ $noun('commission') }}</text>
						<view class="card-more" @click="navTo('/plugins/distribution/order')">
							<text>查看全部</text>
							<tui-icon name="arrowright" :size="13" color="#86909c"></tui-icon>
						</view>
					</view>
					<view v-if="!recent.length" class="recent-empty">还没有记录，生成海报邀请好友试试吧</view>
					<view v-for="r in recent" :key="r.id" class="recent-item">
						<image class="goods-pic" :src="thumbSrc(r.goods_pic)" mode="aspectFill" @error="thumbFail(r.goods_pic)"></image>
						<view class="recent-info">
							<view class="goods-name">{{ r.goods_name || ('订单 ' + r.order_no) }}</view>
							<view class="recent-meta">{{ levelText(r.level) }} {{ maskName(r.user_nickname) }} · {{ shortTime(r.created_at) }}</view>
						</view>
						<view class="recent-right">
							<view class="amount" :class="{ invalid: r.status === 2 }">{{ r.status === 2 ? '' : '+' }}{{ money(r.commission) }}</view>
							<text class="pill" :class="'pill-' + r.status">{{ r.status_text }}</text>
						</view>
					</view>
				</view>

				<!-- 其它入口 -->
				<view class="card menu">
					<view class="menu-item" @click="navTo('/plugins/distribution/order')">
						<view class="menu-icon" style="background:#fff3e0;"><tui-icon name="order" :size="18" color="#f57c00"></tui-icon></view>
						<text class="menu-name">{{ $noun('commission') }}明细</text>
						<text class="menu-desc">按月查看全部记录</text>
						<tui-icon name="arrowright" :size="14" color="#c0c4cc"></tui-icon>
					</view>
					<view class="menu-item" @click="navTo('/others/team/team')">
						<view class="menu-icon" style="background:#e8f5e9;"><tui-icon name="people" :size="18" color="#388e3c"></tui-icon></view>
						<text class="menu-name">我的客户</text>
						<text class="menu-desc">{{ summary.customer_count || 0 }} 人</text>
						<tui-icon name="arrowright" :size="14" color="#c0c4cc"></tui-icon>
					</view>
					<view class="menu-item" @click="navTo('/others/share-poster/share-poster')">
						<view class="menu-icon" style="background:#f3e5f5;"><tui-icon name="qrcode" :size="18" color="#8e24aa"></tui-icon></view>
						<text class="menu-name">推广海报</text>
						<text class="menu-desc">商城通用海报</text>
						<tui-icon name="arrowright" :size="14" color="#c0c4cc"></tui-icon>
					</view>
				</view>
			</block>
		</view>
	</tui-page>
</template>

<script>
	import { thumbMixin } from '@/utils/thumb.js';

	export default {
		// 商品小图走缩略图,没有缩略图自动退回原图
		mixins: [thumbMixin],
		data() {
			return {
				summary: {},
				setting: {},
				recent: [], // 最近 3 笔
				loaded: false, // 接口回来前两块都不画,免得推广员先闪一下「还不是」
				withdrawOn: false, // 提现插件是否开了这个账户的提现入口(没装 / 没开 / 接口失败都不显示)
				defaultAvatar: '/static/icons/avatar.png'
			};
		},
		computed: {
			isDistributor() {
				return Number(this.summary.is_distributor) === 1;
			},
			isDisabled() {
				return Number(this.summary.is_disabled) === 1;
			},
			// 没资格的人看到的开通流程:只讲怎么开通,不讲收益
			steps() {
				return [
					{ title: '联系客服', sub: '点下方按钮，告诉客服你想开通推广资格' },
					{ title: '客服确认并开通', sub: '确认后会为你的账号开通推广资格' },
					{ title: '回到这里使用', sub: '开通后，这里就是你的分销中心' }
				];
			},
			// 佣金结算进哪个账户,提现中心就进哪个账户;扩展钱包等其它账户按佣金账户处理
			withdrawAsset() {
				const t = (this.setting || {}).payout_target;
				return t === 'balance' ? 'balance' : (t === 'integral' ? 'integral' : 'price');
			},
			withdrawTarget() {
				return { balance: this.$noun('balance'), integral: this.$noun('integral'), price: this.$noun('commission') }[this.withdrawAsset];
			},
			// 什么时候结算、结算到哪:按后台分销设置(结算时机 / 延迟天数 / 落账账户)
			moneyTip() {
				const s = this.setting || {};
				const accounts = { price: this.$noun('commission'), balance: this.$noun('balance'), integral: this.$noun('integral') };
				const to = accounts[s.payout_target] || '你的账户';
				if (Number(s.settle_delay_days) > 0) return '订单付款 ' + s.settle_delay_days + ' 天后自动结算到' + to;
				if (s.settlement_timing === 'payment') return '订单付款后立即结算到' + to;
				if (s.settlement_timing === 'manual') return '平台审核后结算到' + to;
				return '订单过了售后期自动结算到' + to;
			}
		},
		onShow() {
			this.loadData();
		},
		onPullDownRefresh() {
			this.loadData().finally(() => uni.stopPullDownRefresh());
		},
		methods: {
			// 汇总和最近 3 笔一个接口拿齐
			loadData() {
				return this.$request({
					url: '/plugin/distribution/api/distribution/index',
					data: { page: 1, limit: 3, status: -1 }
				}).then(res => {
					if (res.code !== 0) {
						if (res.code !== -1) this.$util.toast(res.msg);
						return;
					}
					const d = res.data || {};
					this.summary = d.summary || {};
					this.setting = d.setting || {};
					this.recent = d.list || [];
					this.loaded = true;
					if (this.isDistributor) this.loadWithdrawEntry();
				});
			},
			// 提现入口开关在提现插件里,这里只问「这个账户能不能提现」
			loadWithdrawEntry() {
				this.$request({ url: '/plugin/withdraw/api/withdraw/entry', data: { asset_type: this.withdrawAsset } }).then(res => {
					this.withdrawOn = !!(res && res.code === 0 && res.data && Number(res.data.enabled) === 1);
				}).catch(() => { this.withdrawOn = false; });
			},
			goWithdraw() {
				this.navTo('/plugins2/withdraw/withdraw?asset=' + this.withdrawAsset);
			},
			// 1 级 = 我直接邀请的客户,2 级起是客户再邀请的人
			levelText(level) {
				return Number(level) <= 1 ? '客户' : (level + '级客户');
			},
			money(v) {
				return Number(v || 0).toFixed(2);
			},
			// 客户昵称只露第一个字
			maskName(name) {
				const s = String(name || '');
				return s ? s.slice(0, 1) + '**' : '';
			},
			// 秒级时间戳 → 10-02 21:14
			shortTime(ts) {
				if (!ts) return '';
				const d = new Date(ts * 1000);
				const p = n => String(n).padStart(2, '0');
				return p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
			},
			goHome() {
				uni.reLaunch({ url: '/pages/index/index' });
			},
			navTo(url) {
				uni.navigateTo({ url });
			}
		}
	};
</script>

<style lang="scss" scoped>
	/* 版式与代理中心 plugins/dealer/index.vue 对齐;两边改样式记得同步 */
	.dist-index {
		min-height: 100vh;
		background: #f5f6f8;
		padding-bottom: 40rpx;
	}

	.hero {
		padding: 24rpx 36rpx 120rpx;
		background: linear-gradient(170deg, var(--theme-color) 0%, var(--theme-color) 35%, var(--theme-color-light) 100%);
		position: relative;
		overflow: hidden;
	}

	.hero::after {
		content: '';
		position: absolute;
		right: -120rpx;
		top: -40rpx;
		width: 420rpx;
		height: 420rpx;
		border-radius: 50%;
		background: radial-gradient(circle, rgba(255, 255, 255, 0.16), rgba(255, 255, 255, 0) 70%);
	}

	.me {
		position: relative;
		z-index: 1;
		display: flex;
		align-items: center;
	}

	.avatar {
		width: 104rpx;
		height: 104rpx;
		border-radius: 50%;
		border: 4rpx solid rgba(255, 255, 255, 0.85);
		background: #f2f2f2;
		flex-shrink: 0;
	}

	.me-info {
		margin-left: 24rpx;
		flex: 1;
		min-width: 0;
	}

	.nickname {
		font-size: 36rpx;
		font-weight: 700;
		color: #ffffff;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.level-tag {
		display: inline-flex;
		align-items: center;
		margin-top: 10rpx;
		padding: 4rpx 20rpx 4rpx 16rpx;
		border-radius: 24rpx;
		font-size: 24rpx;
		font-weight: 600;
		color: #6b4a12;
		background: linear-gradient(90deg, #fbe3a6, #f6d58e);
	}

	.star {
		margin-right: 6rpx;
	}

	.card {
		position: relative;
		z-index: 2;
		margin: 24rpx 28rpx 0;
		background: #ffffff;
		border-radius: 28rpx;
	}

	.money {
		margin-top: -96rpx;
		padding: 36rpx 36rpx 32rpx;
		box-shadow: 0 16rpx 48rpx var(--theme-shadow);
	}

	/* 没资格时的说明卡 */
	.join {
		margin-top: -96rpx;
		padding: 56rpx 40rpx 40rpx;
		text-align: center;
		box-shadow: 0 16rpx 48rpx var(--theme-shadow);
	}

	.join-badge {
		width: 112rpx;
		height: 112rpx;
		margin: 0 auto;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		background: linear-gradient(135deg, var(--theme-color), var(--theme-color-light));
		box-shadow: 0 12rpx 32rpx var(--theme-shadow);
	}

	.join-title {
		margin-top: 28rpx;
		font-size: 36rpx;
		font-weight: 700;
		color: #1f2329;
	}

	.join-desc {
		margin-top: 12rpx;
		font-size: 26rpx;
		line-height: 1.7;
		color: #86909c;
	}

	.join-link {
		margin-top: 20rpx;
		font-size: 26rpx;
		color: var(--theme-color);
	}

	.steps {
		margin-top: 36rpx;
		padding: 8rpx 28rpx 12rpx;
		border-radius: 24rpx;
		background: #f7f8fa;
		text-align: left;
	}

	.steps-head {
		padding: 20rpx 0 6rpx;
		font-size: 24rpx;
		color: #86909c;
		letter-spacing: 2rpx;
	}

	.step {
		position: relative;
		display: flex;
		align-items: flex-start;
		padding: 16rpx 0;
	}

	.step + .step::before {
		content: '';
		position: absolute;
		left: 21rpx;
		top: -14rpx;
		height: 28rpx;
		border-left: 2rpx dashed var(--theme-color-light);
	}

	.step-no {
		width: 44rpx;
		height: 44rpx;
		flex-shrink: 0;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 24rpx;
		font-weight: 700;
		color: #ffffff;
		background: var(--theme-color);
	}

	.step-text {
		flex: 1;
		min-width: 0;
		margin-left: 20rpx;
	}

	.step-title {
		font-size: 28rpx;
		font-weight: 600;
		line-height: 44rpx;
		color: #1f2329;
	}

	.step-sub {
		font-size: 24rpx;
		line-height: 1.5;
		color: #86909c;
	}

	.join-cs {
		display: block;
		margin-top: 36rpx;
	}

	.join-btn {
		height: 88rpx;
		border-radius: 44rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 30rpx;
		font-weight: 600;
	}

	.join-btn.primary {
		color: #ffffff;
		background: var(--theme-color);
		box-shadow: 0 12rpx 32rpx var(--theme-shadow);
	}

	.join-btn.tall {
		height: 112rpx;
		border-radius: 56rpx;
	}

	.join-btn-ico {
		width: 56rpx;
		height: 56rpx;
		margin-right: 18rpx;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		background: rgba(255, 255, 255, 0.22);
	}

	.join-btn-text {
		text-align: left;
	}

	.join-btn-title {
		font-size: 32rpx;
		line-height: 1.25;
	}

	.join-btn-sub {
		font-size: 22rpx;
		font-weight: 400;
		line-height: 1.3;
		opacity: 0.85;
	}

	.join-btn.ghost {
		margin-top: 20rpx;
		color: var(--theme-color);
		background: var(--theme-color-bg);
	}

	/* 收益卡 */
	.money-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.money-label {
		font-size: 26rpx;
		color: #86909c;
	}

	.money-link {
		display: flex;
		align-items: center;
		font-size: 24rpx;
		color: var(--theme-color);
	}

	.money-big {
		margin-top: 12rpx;
		font-size: 68rpx;
		font-weight: 700;
		color: #1f2329;
		font-family: "DIN Alternate", "Helvetica Neue", sans-serif;
	}

	.cur {
		font-size: 32rpx;
		margin-right: 4rpx;
	}

	.money-tip {
		margin-top: 4rpx;
		font-size: 22rpx;
		color: #86909c;
	}

	.stats {
		display: flex;
		margin-top: 28rpx;
		padding-top: 28rpx;
		border-top: 1rpx solid #f0f1f4;
	}

	.stat {
		flex: 1;
		text-align: center;
	}

	.stat + .stat {
		border-left: 1rpx solid #f0f1f4;
	}

	.stat-num {
		font-size: 36rpx;
		font-weight: 600;
		color: #1f2329;
		font-family: "DIN Alternate", "Helvetica Neue", sans-serif;
	}

	.stat-label {
		margin-top: 4rpx;
		font-size: 22rpx;
		color: #86909c;
	}

	.wd {
		display: flex;
		align-items: center;
		margin-top: 28rpx;
		padding: 22rpx 24rpx;
		border-radius: 22rpx;
		background: var(--theme-color-bg);
	}

	.wd-ico {
		width: 68rpx;
		height: 68rpx;
		border-radius: 20rpx;
		background: #fff;
		display: flex;
		align-items: center;
		justify-content: center;
		margin-right: 20rpx;
		flex-shrink: 0;
	}

	.wd-text {
		flex: 1;
		min-width: 0;
	}

	.wd-title {
		font-size: 29rpx;
		font-weight: 600;
		color: #1f2329;
	}

	.wd-sub {
		margin-top: 4rpx;
		font-size: 22rpx;
		color: #86909c;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.wd-btn {
		flex-shrink: 0;
		margin-left: 16rpx;
		padding: 12rpx 30rpx;
		border-radius: 34rpx;
		background: var(--theme-color);
		color: #fff;
		font-size: 25rpx;
		font-weight: 600;
		box-shadow: 0 6rpx 16rpx var(--theme-shadow);
	}

	/* 主行动 */
	.cta {
		display: flex;
		margin: 24rpx 28rpx 0;
	}

	.cta-main,
	.cta-side {
		display: flex;
		align-items: center;
		padding: 28rpx;
		border-radius: 28rpx;
	}

	.cta-main {
		flex: 1.4;
		background: linear-gradient(120deg, var(--theme-color), var(--theme-color-light));
		box-shadow: 0 12rpx 32rpx var(--theme-shadow);
	}

	.cta-side {
		flex: 1;
		margin-left: 20rpx;
		background: #ffffff;
	}

	.cta-icon {
		width: 76rpx;
		height: 76rpx;
		border-radius: 20rpx;
		margin-right: 20rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		background: rgba(255, 255, 255, 0.2);
		flex-shrink: 0;
	}

	.cta-icon.light {
		background: var(--theme-color-bg);
	}

	.cta-title {
		font-size: 30rpx;
		font-weight: 700;
		color: #ffffff;
	}

	.cta-title.dark {
		color: #1f2329;
	}

	.cta-sub {
		margin-top: 4rpx;
		font-size: 22rpx;
		color: rgba(255, 255, 255, 0.85);
	}

	.cta-sub.gray {
		color: #86909c;
	}

	/* 最近佣金 */
	.recent {
		padding: 28rpx 32rpx 8rpx;
	}

	.card-head {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 8rpx;
	}

	.card-title {
		font-size: 30rpx;
		font-weight: 700;
		color: #1f2329;
	}

	.card-more {
		display: flex;
		align-items: center;
		font-size: 24rpx;
		color: #86909c;
	}

	.recent-empty {
		padding: 40rpx 0 48rpx;
		text-align: center;
		font-size: 24rpx;
		color: #c0c4cc;
	}

	.recent-item {
		display: flex;
		align-items: center;
		padding: 22rpx 0;
		border-bottom: 1rpx solid #f0f1f4;
	}

	.recent-item:last-child {
		border-bottom: none;
	}

	.goods-pic {
		width: 80rpx;
		height: 80rpx;
		border-radius: 16rpx;
		background: #f2f3f5;
		flex-shrink: 0;
	}

	.recent-info {
		flex: 1;
		min-width: 0;
		margin: 0 20rpx;
	}

	.goods-name {
		font-size: 26rpx;
		color: #1f2329;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.recent-meta {
		margin-top: 6rpx;
		font-size: 22rpx;
		color: #86909c;
	}

	.recent-right {
		text-align: right;
		flex-shrink: 0;
	}

	.amount {
		font-size: 30rpx;
		font-weight: 600;
		color: var(--theme-color);
		font-family: "DIN Alternate", "Helvetica Neue", sans-serif;
	}

	.amount.invalid {
		color: #c0c4cc;
		text-decoration: line-through;
	}

	.pill {
		display: inline-block;
		margin-top: 4rpx;
		padding: 2rpx 12rpx;
		border-radius: 16rpx;
		font-size: 20rpx;
	}

	/* 0 待结算 / 1 已结算 / 2 已失效:状态色,不跟主题色 */
	.pill-0 {
		color: #b7791f;
		background: #fff7e6;
	}

	.pill-1 {
		color: #2e7d32;
		background: #eaf6ec;
	}

	.pill-2 {
		color: #909399;
		background: #f2f3f5;
	}

	/* 其它入口 */
	.menu {
		padding: 0 32rpx;
	}

	.menu-item {
		display: flex;
		align-items: center;
		padding: 28rpx 0;
		border-bottom: 1rpx solid #f0f1f4;
	}

	.menu-item:last-child {
		border-bottom: none;
	}

	.menu-icon {
		width: 60rpx;
		height: 60rpx;
		border-radius: 16rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		margin-right: 24rpx;
	}

	.menu-name {
		font-size: 28rpx;
		color: #1f2329;
	}

	.menu-desc {
		margin-left: auto;
		margin-right: 8rpx;
		font-size: 24rpx;
		color: #86909c;
	}
</style>
