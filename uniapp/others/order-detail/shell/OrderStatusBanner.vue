<template>
	<view class="status-banner" :style="'background:' + bannerColor + ';padding-top:' + statusPaddingTop">
		<view class="status-row">
			<tui-icon :name="statusIcon" color="#fff" :size="30" class="status-icon"></tui-icon>
			<text class="status-text">{{ statusText }}</text>
		</view>
		<view class="status-desc">{{ statusDesc }}</view>

		<!-- 进度:普通实物订单才画(插件订单各有流程,由各自面板说明)。已关闭的最后一步换成「已关闭」 -->
		<view class="steps" v-if="steps.length">
			<view class="step" v-for="(st, i) in steps" :key="i">
				<view class="step-track">
					<view class="seg" :class="{ 'is-hidden': i === 0, 'is-on': st.state !== 'todo' }"></view>
					<view class="dot" :class="'dot-' + st.state">
						<tui-icon v-if="st.state === 'done'" name="check" :size="12" :color="bannerColor"></tui-icon>
						<tui-icon v-else-if="st.state === 'closed'" name="close" :size="12" :color="bannerColor"></tui-icon>
						<view v-else-if="st.state === 'current'" class="dot-core"></view>
					</view>
					<view class="seg" :class="{ 'is-hidden': i === steps.length - 1, 'is-on': i < steps.length - 1 && steps[i + 1].state !== 'todo' }"></view>
				</view>
				<text class="step-label" :class="{ 'is-strong': st.state === 'current' || st.state === 'closed', 'is-dim': st.state === 'todo' }">{{ st.label }}</text>
				<text class="step-time" v-if="st.time">{{ st.time }}</text>
			</view>
		</view>
	</view>
</template>

<script>
import { fallbackStatusInfo, formatTime } from './utils.js';

// 已关闭的订单用中性深灰:主题色只留给还在进行中的订单
const CLOSED_COLOR = '#4B5160';

export default {
	name: 'OrderStatusBanner',
	props: {
		order: { type: Object, required: true },
		/** 可选：后端 mobile_actions 里带来的 status_text / status_desc / status_icon */
		mobileActions: { type: Object, default: () => null },
		// themeColor 由全局 mixin 注入（mapState -> mall.themeColor），无需重复声明
		navHeight: { type: [Number, String], default: 64 }
	},
	computed: {
		statusText() {
			if (this.mobileActions && this.mobileActions.status_text) return this.mobileActions.status_text;
			return fallbackStatusInfo(this.order).text;
		},
		statusDesc() {
			if (this.mobileActions && this.mobileActions.status_desc) return this.mobileActions.status_desc;
			return fallbackStatusInfo(this.order).desc;
		},
		statusIcon() {
			if (this.mobileActions && this.mobileActions.status_icon) return this.mobileActions.status_icon;
			const s = Number(this.order.status);
			const pickup = this.order.deliver_type === 'pickup';
			// 名字必须是 tui-icon 里有的:原来已完成用的 check-circle 不存在,横幅上空一块
			if (s === 10) return 'clock';
			if (s === 20) return pickup ? 'shop' : 'order';
			if (s === 30) return pickup ? 'shop' : 'transport';
			if (s === 40) return 'circle-selected';
			if (s === 0) return 'close';
			return 'about';
		},
		bannerColor() {
			return Number(this.order.status) === 0 ? CLOSED_COLOR : (this.themeColor || '#E63D3D');
		},
		statusPaddingTop() {
			const nav = parseFloat(this.navHeight) || 0;
			return `calc(${nav}px + 30rpx)`;
		},
		showSteps() {
			const o = this.order;
			return ['express', 'city', 'pickup'].includes(o.deliver_type)
				&& (!o.plugin || o.plugin === 'mall')
				&& [0, 10, 20, 30, 40].includes(Number(o.status));
		},
		steps() {
			if (!this.showSteps) return [];
			const o = this.order;
			const s = Number(o.status);
			const pickup = o.deliver_type === 'pickup';
			const names = pickup ? ['下单', '付款', '备货', '核销'] : ['下单', '付款', '发货', '收货'];
			const times = [o.created_at, o.pay_at, o.send_at, o.confirm_at];

			// 已关闭:走到哪步画到哪步,最后接「已关闭」
			if (s === 0) {
				const arr = [{ label: '下单', state: 'done', time: this.shortTime(o.created_at) }];
				if (this.hasTime(o.pay_at)) arr.push({ label: '付款', state: 'done', time: this.shortTime(o.pay_at) });
				if (this.hasTime(o.send_at)) arr.push({ label: names[2], state: 'done', time: this.shortTime(o.send_at) });
				arr.push({ label: '已关闭', state: 'closed', time: this.hasTime(o.cancel_at) ? this.shortTime(o.cancel_at) : '' });
				return arr;
			}

			const doneCount = { 10: 1, 20: 2, 30: 3, 40: 4 }[s];
			const currentHint = {
				10: '待付款',
				20: pickup ? '备货中' : '打包中',
				30: pickup ? '待核销' : '待确认'
			}[s] || '';
			return names.map((label, i) => {
				if (i < doneCount) return { label, state: 'done', time: this.shortTime(times[i]) };
				if (i === doneCount) return { label, state: 'current', time: currentHint };
				return { label, state: 'todo', time: '' };
			});
		}
	},
	methods: {
		hasTime(t) {
			return !!t && t !== '0' && t !== 0;
		},
		// 「2026-09-29 12:15:24」→「09-29 12:15」;纯时间戳先格式化
		shortTime(t) {
			if (!this.hasTime(t)) return '';
			const str = formatTime(t);
			return str.length >= 16 ? str.slice(5, 16) : str;
		}
	}
};
</script>

<style scoped lang="scss">
.status-banner {
	padding: 30rpx 40rpx 72rpx;
	color: #fff;

	.status-row {
		display: flex;
		align-items: center;

		.status-icon { margin-right: 16rpx; }
		.status-text {
			font-size: 44rpx;
			font-weight: 700;
			letter-spacing: 2rpx;
		}
	}
	.status-desc {
		margin-top: 12rpx;
		font-size: 26rpx;
		opacity: 0.9;
		line-height: 1.5;
	}
}

.steps {
	display: flex;
	margin: 40rpx -20rpx 0;
}
.step {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	min-width: 0;
}
.step-track {
	width: 100%;
	display: flex;
	align-items: center;
}
.seg {
	flex: 1;
	height: 4rpx;
	background: rgba(255, 255, 255, 0.35);

	&.is-on { background: #fff; }
	&.is-hidden { background: transparent; }
}
.dot {
	width: 40rpx;
	height: 40rpx;
	border-radius: 20rpx;
	box-sizing: border-box;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;

	&.dot-done, &.dot-closed { background: #fff; }
	&.dot-current { border: 4rpx solid #fff; }
	&.dot-todo { border: 4rpx solid rgba(255, 255, 255, 0.5); }
}
.dot-core {
	width: 16rpx;
	height: 16rpx;
	border-radius: 8rpx;
	background: #fff;
}
.step-label {
	margin-top: 12rpx;
	font-size: 24rpx;
	font-weight: 500;

	&.is-strong { font-weight: 700; }
	&.is-dim { opacity: 0.7; font-weight: 400; }
}
.step-time {
	margin-top: 4rpx;
	font-size: 22rpx;
	opacity: 0.8;
	white-space: nowrap;
}
</style>
