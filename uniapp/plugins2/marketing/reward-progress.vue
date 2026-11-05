<template>
	<tui-page title="奖励进度" :is-pulldown="true" @pulldown="onPullDownRefresh">
		<view class="page-container" :style="themeStyle">
			<view class="notice">订单完成后计入进度;退款导致不再达标时,已发奖励会被收回。</view>

			<view class="card" v-for="card in cards" :key="card.campaign_id">
				<view class="card-head">
					<text class="name">{{ card.name }}</text>
					<text class="period">{{ card.periodText }}</text>
				</view>

				<view class="summary">
					<text class="value">{{ card.valueText }}</text>
					<text class="next">{{ card.nextText }}</text>
				</view>
				<view class="bar" v-if="card.showBar">
					<view class="bar-inner" :style="card.barStyle"></view>
				</view>
				<view class="desc" v-if="card.desc">{{ card.desc }}</view>

				<view class="tier" v-for="t in card.tiers" :key="t.no">
					<view class="tier-left">
						<text class="tier-label">{{ t.label }}</text>
						<text class="tier-reward">{{ t.rewardText }}</text>
					</view>
					<text class="tier-state" :class="t.state">{{ t.stateText }}</text>
				</view>

				<text class="end" v-if="card.end_at">活动截止 {{ card.end_at }}</text>
			</view>

			<tui-no-data v-if="!cards.length && !loading" :fixed="false">暂无进行中的奖励活动</tui-no-data>
		</view>
	</tui-page>
</template>

<script>
	// themeColor / themeStyle 由全局 mixin(uniapp/mixins/index.js)提供,勿本地重定义
	const PERIOD_TEXT = { campaign: '活动期内', month: '本月', week: '本周', lifetime: '累计' };

	export default {
		data() {
			return {
				loading: false,
				cards: [],
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
				try {
					const res = await this.$request({ url: '/plugin/marketing/api/center/progress', data: {} });
					if (res.code === 0) {
						this.cards = (res.data.list || []).map(item => this.buildCard(item));
					} else {
						uni.showToast({ title: res.msg || '加载失败', icon: 'none' });
					}
				} finally {
					this.loading = false;
				}
			},
			// 展示文案在脚本里算好,模板只取值(小程序模板里调方法兼容性差)
			buildCard(item) {
				if (item.kind === 'rebuy') return this.buildRebuy(item);
				if (item.kind === 'invite') return this.buildInvite(item);

				const unit = { order_count: '单', goods_qty: '件' }[item.metric];
				const fmt = v => (unit ? v + unit : '¥' + v);
				const doneWord = { order_count: '已完成 ', goods_qty: '已购 ' }[item.metric] || '已消费 ';
				const gapText = gap => {
					if (item.metric === 'order_count') return '再下 ' + gap + ' 单';
					if (item.metric === 'goods_qty') return '再买 ' + gap + ' 件';
					return '再消费 ¥' + gap;
				};
				const base = {
					periodText: PERIOD_TEXT[item.period] || '',
					valueText: doneWord + fmt(item.value),
					showBar: true,
					desc: '',
				};

				if (item.kind === 'repeat') {
					const step = item.step;
					const remainder = item.value - Math.floor(item.value / step + 1e-9) * step;
					const percent = item.next_gap === null ? 100 : Math.min(100, Math.round(remainder / step * 100));
					return Object.assign({}, item, base, {
						nextText: item.next_gap === null ? '本周期已达上限' : gapText(item.next_gap) + '再得一次',
						barStyle: 'width:' + percent + '%',
						desc: '每满 ' + fmt(step) + ' 得 ' + this.rewardText(item.rewards)
							+ (item.max_times > 0 ? ',最多 ' + item.max_times + ' 次' : '')
							+ ';已获得 ' + item.granted_times + ' 次',
						tiers: [],
					});
				}

				const tiers = item.tiers || [];
				const last = tiers.length ? tiers[tiers.length - 1].threshold : 0;
				const percent = last > 0 ? Math.min(100, Math.round(item.value / last * 100)) : 0;
				return Object.assign({}, item, base, {
					nextText: item.next ? gapText(item.next.gap) + '可得下一档奖励' : '已达成全部档位',
					barStyle: 'width:' + percent + '%',
					tiers: tiers.map(t => ({
						no: t.no,
						label: '满 ' + fmt(t.threshold),
						rewardText: this.rewardText(t.rewards),
						state: t.state,
						stateText: t.state === 'granted' ? '已发放' : (t.state === 'reached' ? '已达成' : '差 ' + fmt(t.gap)),
					})),
				});
			},
			buildRebuy(item) {
				const stateText = {
					no_first: '完成首单后,' + item.days + ' 天内再下一单',
					pending: '截止 ' + item.deadline + ' 前再下一单',
					reached: '第二单完成后发放',
					granted: '已获得奖励',
				}[item.state] || '';
				return Object.assign({}, item, {
					periodText: '新客复购',
					valueText: this.rewardText(item.rewards),
					nextText: stateText,
					showBar: false,
					barStyle: '',
					desc: '',
					tiers: [],
				});
			},
			buildInvite(item) {
				const inviteeText = item.invitee_rewards && item.invitee_rewards.length
					? ';好友得 ' + this.rewardText(item.invitee_rewards) : '';
				return Object.assign({}, item, {
					periodText: '邀请有礼',
					valueText: '已成功邀请 ' + item.invited_count + ' 人',
					nextText: item.max_per_inviter > 0 ? '最多 ' + item.max_per_inviter + ' 人' : '',
					showBar: false,
					barStyle: '',
					desc: '好友首单完成后,你得 ' + this.rewardText(item.rewards) + inviteeText,
					tiers: [],
				});
			},
			rewardText(rewards) {
				return (rewards || []).map(r => {
					if (r.reward_type === 'integral') return r.amount + this.$noun('integral');
					if (r.reward_type === 'balance') return r.amount + '元' + this.$noun('balance');
					return r.name + (r.num > 1 ? '×' + r.num : '');
				}).join(' + ');
			},
		}
	}
</script>

<style lang="scss" scoped>
	.page-container { padding: 20rpx 24rpx 40rpx; min-height: 100vh; background: #f5f5f7; }
	.notice { font-size: 24rpx; color: #999; padding: 4rpx 6rpx 20rpx; line-height: 1.6; }

	.card { background: #fff; border-radius: 16rpx; padding: 28rpx 24rpx; margin-bottom: 20rpx; box-shadow: 0 2rpx 12rpx rgba(0,0,0,.04); }
	.card-head { display: flex; align-items: center; justify-content: space-between; }
	.name { font-size: 30rpx; font-weight: 600; color: #222; }
	.period { font-size: 22rpx; color: var(--theme-color); background: var(--theme-color-bg); padding: 4rpx 14rpx; border-radius: 20rpx; }

	.summary { display: flex; align-items: baseline; justify-content: space-between; margin-top: 24rpx; }
	.value { font-size: 32rpx; font-weight: 600; color: var(--theme-color); }
	.next { font-size: 24rpx; color: #666; }
	.bar { height: 12rpx; background: #f0f0f0; border-radius: 6rpx; margin: 16rpx 0 24rpx; overflow: hidden; }
	.bar-inner { height: 100%; background: var(--theme-color); border-radius: 6rpx; }
	.desc { font-size: 24rpx; color: #666; line-height: 1.6; margin: 16rpx 0 8rpx; }

	.tier { display: flex; align-items: center; justify-content: space-between; padding: 18rpx 0; border-top: 1px solid #f3f3f3; }
	.tier-left { display: flex; flex-direction: column; flex: 1; min-width: 0; }
	.tier-label { font-size: 28rpx; color: #333; }
	.tier-reward { font-size: 24rpx; color: #999; margin-top: 6rpx; }
	.tier-state { font-size: 24rpx; color: #999; margin-left: 20rpx; flex-shrink: 0; }
	.tier-state.granted { color: var(--theme-color); }
	.tier-state.reached { color: #f59a23; }

	.end { display: block; font-size: 22rpx; color: #bbb; margin-top: 12rpx; }
</style>
