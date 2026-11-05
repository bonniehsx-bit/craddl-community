<template>
	<view class="dining-panel" :style="themeStyle">

		<!--
			头部:凭证。
			顾客在店里要举给店员看的就是这个数字,所以给到最大字号。
			⛔ 这里**不再重复状态文字** —— 详情页顶部的状态条已经写着
			「待取餐 · A012」和一句说明,面板里再来一遍是三处说同一件事。
			状态由下面的进度条表达。
		-->
		<view class="dp-head">
			<view class="dp-left">
				<text class="dp-type">{{ data.dine_type_text }}</text>
				<text v-if="voucherLabel" class="dp-voucher-k">{{ voucherLabel }}</text>
			</view>
			<text v-if="voucherValue" class="dp-no">{{ voucherValue }}</text>
		</view>

		<!--
			出餐进度。只给文字标签的话,顾客看不出「走到哪一步、还剩几步」——
			而点单的状态几分钟就变一次,这正是最需要进度感的场景。
			退单(9)不画:那条线已经断了,画出来只会误导。
		-->
		<view v-if="showSteps" class="dp-steps">
			<view v-for="(st, i) in steps" :key="i" class="dp-step"
				:class="{ done: i <= stepIndex, cur: i === stepIndex }">
				<view v-if="i > 0" class="ds-line" :class="{ done: i <= stepIndex }"></view>
				<view class="ds-dot"></view>
				<text class="ds-label">{{ st }}</text>
			</view>
		</view>

		<!-- 预约单:顶部状态条说的是「待接单」,不说清会让顾客以为门店没理他 -->
		<view v-if="data.expect_at > 0 && kds === 0" class="dp-hint">
			已预约 {{ data.expect_text }} 取餐,门店会到点前开始制作
		</view>

		<!-- 点单专属明细。桌号已经在头部,这里不再重复 -->
		<view v-if="hasRows" class="dp-rows">
			<view v-if="data.guest_num > 0" class="dp-row">
				<text class="k">就餐人数</text><text class="v">{{ data.guest_num }} 位</text>
			</view>
			<view v-if="Number(data.tableware_fee) > 0" class="dp-row">
				<text class="k">餐位费</text><text class="v num">¥{{ data.tableware_fee }}</text>
			</view>
			<view v-if="Number(data.pack_fee) > 0" class="dp-row">
				<text class="k">打包费</text><text class="v num">¥{{ data.pack_fee }}</text>
			</view>
			<view v-if="Number(data.option_fee) > 0" class="dp-row">
				<text class="k">加料</text><text class="v num">¥{{ data.option_fee }}</text>
			</view>
			<view v-if="data.remark" class="dp-row">
				<text class="k">口味备注</text><text class="v remark">{{ data.remark }}</text>
			</view>
		</view>

		<!-- 堂食:回本桌账单继续加菜,省得再扫一次桌上的码 -->
		<view v-if="data.dine_type === 'dinein' && data.table_key" class="dp-action" @click="goSession">
			<text class="dp-action-text">查看本桌账单 · 继续加菜</text>
			<uni-icons type="right" size="12" color="var(--dc-text-4, #a3a29c)" />
		</view>
	</view>
</template>

<script>
export default {
	name: 'DiningOrderPanel',
	props: {
		data: { type: Object, default: () => ({}) },
		order: { type: Object, default: () => ({}) },
		list: { type: Array, default: () => [] }
	},
	data() {
		return {
			// 本地覆盖:轮询到的新状态优先于父组件传下来的那份(那份是打开页面时的快照)
			liveKds: null,
			timer: null,
			ticks: 0
		};
	},
	computed: {
		kds() {
			return this.liveKds === null ? Number(this.data.kds_status) : this.liveKds;
		},

		// 凭证:打包带走看取餐号,堂食看桌号。配送两个都没有(有地址)
		voucherLabel() {
			if (this.data.pickup_no) return '取餐号';
			if (this.data.table_name) return '桌号';
			return '';
		},
		voucherValue() {
			return this.data.pickup_no || this.data.table_name || '';
		},

		// 退单(9)不画进度条
		showSteps() {
			return this.kds >= 0 && this.kds <= 3;
		},
		steps() {
			// 配送的最后一格叫「已送达」而不是「已取餐」—— 顾客没去取,是送到的
			const isDelivery = this.data.dine_type === 'delivery';
			return ['已下单', '制作中', isDelivery ? '配送中' : '已出餐', isDelivery ? '已送达' : '已取餐'];
		},
		stepIndex() {
			return Math.max(0, Math.min(3, this.kds));
		},

		hasRows() {
			return this.data.guest_num > 0
				|| Number(this.data.tableware_fee) > 0
				|| Number(this.data.pack_fee) > 0
				|| Number(this.data.option_fee) > 0
				|| !!this.data.remark;
		}
	},
	mounted() {
		this.startPoll();
	},
	beforeDestroy() {
		this.stopPoll();
	},
	methods: {
		/**
		 * 自己轮询出餐进度。
		 *
		 * 详情页自带的那个轮询**只查核销状态**,kds_status 变了它不认 ——
		 * 顾客站在取餐台盯着手机,页面却一直停在「制作中」。
		 *
		 * ⚠ 必须有上限:小程序里忘了清的定时器会一直跑。
		 *   三重保险 —— 到「已取餐」就停、最多 240 次(20 分钟)、组件销毁时清。
		 */
		startPoll() {
			if (!this.data.order_id || this.kds >= 3) return;
			this.stopPoll();
			this.timer = setInterval(() => this.poll(), 5000);
		},
		stopPoll() {
			if (this.timer) {
				clearInterval(this.timer);
				this.timer = null;
			}
		},
		async poll() {
			this.ticks++;
			if (this.ticks > 240) {
				this.stopPoll();
				return;
			}
			try {
				const res = await this.$request({
					url: '/plugin/dining/api/shop/order-status',
					method: 'GET', data: { order_id: this.data.order_id }, hideLoading: true
				});
				if (res.code !== 0) { this.stopPoll(); return; }
				this.liveKds = Number(res.data.kds_status);
				if (res.data.finished) {
					this.stopPoll();
					// 交易状态也变了(如出餐即完成),让整页刷一次,底部按钮才跟着更新
					this.$emit('action', { type: 'refresh' });
				}
			} catch (e) {
				this.stopPoll();   // 网络异常就别再打了,顾客下拉刷新即可
			}
		},
		goSession() {
			if (!this.data.table_key) return;
			uni.navigateTo({ url: `/plugins3/dining/session?table_key=${this.data.table_key}` });
		}
	}
};
</script>

<style scoped lang="scss">
/*
	配色全走 --dc-* 变量(带原值 fallback),与 C 端点单页同一套色组。
	⛔ 主题色只出现在三处:价格、主按钮、选中态 —— 这里就是进度条的当前格与取餐号。
	其余一律中性色,否则整块会糊成一片彩色。
*/
.dining-panel {
	background: var(--dc-surface, #ffffff);
	border-radius: 16rpx;
	padding: 32rpx 28rpx;
}

/* ── 凭证 ── */
.dp-head { display: flex; align-items: center; justify-content: space-between; gap: 20rpx; }
.dp-left { display: flex; align-items: baseline; gap: 14rpx; min-width: 0; }
.dp-type {
	font-size: 24rpx; padding: 6rpx 18rpx; border-radius: 999rpx; flex-shrink: 0;
	color: var(--dc-text-2, #57564f); background: var(--dc-fill, #f4f3f0);
}
.dp-voucher-k { font-size: 22rpx; color: var(--dc-text-4, #a3a29c); }
.dp-no {
	font-size: 52rpx; font-weight: 700; line-height: 1; letter-spacing: -1rpx;
	color: var(--dc-primary, var(--theme-color));
	font-variant-numeric: tabular-nums;
}

/* ── 出餐进度 ── */
.dp-steps { display: flex; margin: 36rpx 0 8rpx; }
.dp-step {
	flex: 1; position: relative;
	display: flex; flex-direction: column; align-items: center;
}
/* 连线画在圆点左侧、往前一格延伸:这样最后一格右边不会多出半截线 */
.ds-line {
	position: absolute; top: 7rpx; right: 50%; width: 100%; height: 2rpx;
	background: var(--dc-line, #eeedea);
}
.ds-line.done { background: var(--dc-primary, var(--theme-color)); }
.ds-dot {
	width: 16rpx; height: 16rpx; border-radius: 50%; position: relative; z-index: 1;
	background: var(--dc-line, #eeedea);
}
.dp-step.done .ds-dot { background: var(--dc-primary, var(--theme-color)); }
/* 当前那一格加个光圈,一眼看出走到哪了 */
.dp-step.cur .ds-dot { box-shadow: 0 0 0 6rpx var(--theme-color-bg); }
.ds-label { font-size: 20rpx; color: var(--dc-text-4, #a3a29c); margin-top: 14rpx; }
.dp-step.done .ds-label { color: var(--dc-text-2, #57564f); }
.dp-step.cur .ds-label { color: var(--dc-primary, var(--theme-color)); font-weight: 600; }

/* ── 提示 ── */
.dp-hint {
	margin-top: 28rpx; padding: 18rpx 22rpx; border-radius: 12rpx;
	font-size: 22rpx; line-height: 1.6;
	color: var(--dc-text-2, #57564f); background: var(--dc-fill, #f4f3f0);
}

/* ── 明细 ── */
.dp-rows { margin-top: 28rpx; border-top: 1rpx solid var(--dc-line-2, #f7f6f4); padding-top: 8rpx; }
.dp-row { display: flex; align-items: baseline; justify-content: space-between; gap: 24rpx; padding: 12rpx 0; }
.dp-row .k { font-size: 24rpx; color: var(--dc-text-3, #8c8b87); flex-shrink: 0; }
.dp-row .v { font-size: 24rpx; color: var(--dc-text, #1a1a18); text-align: right; }
.dp-row .v.num { font-variant-numeric: tabular-nums; }
.dp-row .v.remark { color: var(--dc-text-2, #57564f); max-width: 420rpx; line-height: 1.5; }

/* ── 本桌账单入口 ──
   ⛔ 不用主题色填充块:那是主按钮的待遇,而这只是一个次要入口;
      整块铺满主题色会把注意力从取餐号上抢走 */
.dp-action {
	margin-top: 24rpx; padding-top: 24rpx;
	border-top: 1rpx solid var(--dc-line-2, #f7f6f4);
	display: flex; align-items: center; justify-content: center; gap: 8rpx;
}
.dp-action-text { font-size: 26rpx; color: var(--dc-text-2, #57564f); }
</style>
