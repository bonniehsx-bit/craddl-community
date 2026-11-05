<template>
	<view class="massage-panel">
		<!-- 顶部：状态 + 预约时间 -->
		<view class="top-row">
			<view class="status-tag" :style="{ color: themeColor, borderColor: themeColor }">
				{{ data.status_label || '处理中' }}
			</view>
			<!-- 直约标识：派单前/中预约了具体技师 -->
			<view v-if="data.is_direct_book" class="direct-tag">直约技师</view>
			<view class="appoint" v-if="data.appoint_time">
				<tui-icon name="clock" :size="16" color="#666" style="margin-right:8rpx"></tui-icon>
				<text class="appoint-time">预约：{{ formatTime(data.appoint_time) }}</text>
			</view>
		</view>

		<!-- 用户到店签到：未到店出示码给门店扫，已到店显示提示 -->
		<view v-if="showArrived" class="arrived-tip">
			<tui-icon name="success" :size="16" color="#16A34A" style="margin-right:8rpx;margin-top:2rpx;"></tui-icon>
			<text>已到店，正在为您安排服务</text>
		</view>
		<view v-else-if="showArrivalCode" class="arrival-card">
			<view class="arrival-title">
				<tui-icon name="qrcode" :size="16" :color="themeColor" style="margin-right:8rpx"></tui-icon>
				<text>到店签到码</text>
			</view>
			<view class="arrival-qr">
				<qr-code-generator :text="String(data.arrival_code)" :size="280" :color="themeColor"></qr-code-generator>
			</view>
			<text class="arrival-tip">到店后请向门店出示此码完成签到</text>
		</view>

		<!-- 技师已确认但迟到未开始：温和告知有"超时自动全额退款"兜底 -->
		<view v-if="lateNotice" class="late-notice" :class="{ urgent: lateNotice.urgent }">
			<tui-icon name="time" :size="15" :color="lateNotice.urgent ? '#DC2626' : '#D97706'" style="margin-right:10rpx;margin-top:2rpx;"></tui-icon>
			<view class="ln-text">
				<text class="ln-title">{{ lateNotice.lateMin >= 1 ? '技师已迟到 ' + lateNotice.lateMin + ' 分钟' : '已到预约时间，技师尚未开始' }}</text>
				<text class="ln-sub">若超过 {{ lateNotice.threshold }} 分钟仍未开始服务（还剩 {{ lateNotice.remainMin }} 分钟），系统将自动取消并全额退还您的预约金。</text>
			</view>
		</view>

		<!-- 服务进行中：实时计时器 -->
		<view v-if="isServing" class="live-timer">
			<view class="timer-dot"></view>
			<text class="timer-label">服务进行中</text>
			<text class="timer-elapsed">已服务 {{ elapsedText }}</text>
		</view>

		<!-- 服务时间区间（已结束才展示完整范围） -->
		<view class="service-row" v-if="!isServing && (data.service_start_at || data.service_end_at)">
			<text class="service-label">服务：</text>
			<text class="service-val">
				{{ data.service_start_at ? formatTime(data.service_start_at) : '—' }}
				<text v-if="data.service_end_at"> ~ {{ formatTime(data.service_end_at) }}</text>
				<text v-if="data.real_duration" class="duration"> ({{ data.real_duration }}分钟)</text>
			</text>
		</view>

		<!-- 时长不足警示（实际 < 承诺 50% 红色 / 实际 < 承诺 黄色） -->
		<view v-if="data.is_short" :class="['duration-hint', data.is_abnormal ? 'abnormal' : '']">
			<text class="hint-icon">⚠️</text>
			<view class="hint-text">
				<text class="hint-title">{{ data.is_abnormal ? '本次服务时长严重不足' : '本次服务时长不足承诺时长' }}</text>
				<text class="hint-detail">实际 {{ data.real_duration }}分钟 / 承诺 {{ data.promise_duration }}分钟，可在评价中反馈</text>
			</view>
		</view>

		<!-- 门店信息 -->
		<MerchantLocationCard v-if="data.shop" :merchant="data.shop"></MerchantLocationCard>

		<!-- 技师信息：派单中已直约 → 突出"等待 [技师名] 确认"；已确认/服务中 → 普通展示 -->
		<view class="therapist-row" v-if="data.therapist">
			<image v-if="data.therapist.avatar" class="therapist-avatar" :src="data.therapist.avatar" />
			<view v-else class="therapist-avatar avatar-placeholder">
				<tui-icon name="people" :size="32" color="#bbb"></tui-icon>
			</view>
			<view class="therapist-info">
				<text class="therapist-label">{{ data.is_direct_book ? '已直约（等待确认）' : '服务技师' }}</text>
				<text class="therapist-name">{{ data.therapist.name }}</text>
			</view>
		</view>

		<!-- 备注 -->
		<view class="remark-block" v-if="data.remark">
			<tui-icon name="chat" :size="14" color="#999" style="margin-right:8rpx"></tui-icon>
			<text class="remark-text">{{ data.remark }}</text>
		</view>
	</view>
</template>

<script>
import MerchantLocationCard from '../shell/MerchantLocationCard.vue';
import QrCodeGenerator from '@/components/QrCodeGenerator/QrCodeGenerator.vue';

export default {
	name: 'MassageBookingPanel',
	components: { MerchantLocationCard, QrCodeGenerator },
	props: {
		data:  { type: Object, required: true },
		order: { type: Object, default: () => ({}) },
	},
	data() {
		return {
			now: Math.floor(Date.now() / 1000),
			_timer: null,
		};
	},
	computed: {
		themeColor() {
			// 缓存 key 是 theme_color（见 store/modules/mall.js），勿写成 themeColor
			return uni.getStorageSync('theme_color') || '#e8804e';
		},
		// 服务进行中：booking.status===4（STATUS_SERVING）
		isServing() {
			return Number(this.data.status) === 4 && this.data.service_start_at && !this.data.service_end_at;
		},
		// 到店签到：仅在已支付、服务开始前(待派单1/派单中2/技师已确认3)展示
		_beforeService() {
			return [1, 2, 3].includes(Number(this.data.status));
		},
		// 未到店 + 有签到码 → 展示二维码给门店扫
		showArrivalCode() {
			return this._beforeService && Number(this.data.arrived_at || 0) === 0 && !!this.data.arrival_code;
		},
		// 已到店 → 展示「已到店」提示
		showArrived() {
			return this._beforeService && Number(this.data.arrived_at || 0) > 0;
		},
		// 技师已确认(status=3)且已过预约时间、飞单判定开启 → C 端温和提示有"超时自动退款"兜底
		lateNotice() {
			if (Number(this.data.status) !== 3) return null;
			const threshold = Number(this.data.late_threshold || 0);
			if (threshold <= 0) return null;                 // 后台关闭飞单判定
			const appoint = Number(this.data.appoint_time || 0);
			if (appoint <= 0) return null;
			const lateSec = this.now - appoint;
			if (lateSec < 0) return null;                    // 还没到预约时间
			const lateMin = Math.floor(lateSec / 60);
			const remainMin = Math.max(0, threshold - lateMin);
			return { lateMin, remainMin, threshold, urgent: remainMin <= 5 };
		},
		// 已服务时长（实时计算）
		elapsedSecs() {
			const start = Number(this.data.service_start_at) || 0;
			if (!start) return 0;
			return Math.max(0, this.now - start);
		},
		elapsedText() {
			const s = this.elapsedSecs;
			const h = Math.floor(s / 3600);
			const m = Math.floor((s % 3600) / 60);
			const sec = s % 60;
			if (h > 0) return `${h}时${m}分${this.pad(sec)}秒`;
			if (m > 0) return `${m}分${this.pad(sec)}秒`;
			return `${sec}秒`;
		},
	},
	mounted() {
		this.startTimerIfNeeded();
	},
	beforeDestroy() {
		this.stopTimer();
	},
	watch: {
		// 后端刷新订单时如果 status 变化，自动开/停计时器
		'data.status'() {
			this.startTimerIfNeeded();
		},
	},
	methods: {
		pad(n) { return String(n).padStart(2, '0'); },
		// 需要实时推进 now 的场景：① 服务进行中(秒级计时) ② 已确认且开启飞单判定(分钟级倒计时)
		needTick() {
			if (this.isServing) return true;
			return Number(this.data.status) === 3
				&& Number(this.data.late_threshold || 0) > 0
				&& Number(this.data.appoint_time || 0) > 0;
		},
		startTimerIfNeeded() {
			this.stopTimer();
			if (this.needTick()) {
				this.now = Math.floor(Date.now() / 1000);
				this._timer = setInterval(() => {
					this.now = Math.floor(Date.now() / 1000);
				}, 1000);
			}
		},
		stopTimer() {
			if (this._timer) {
				clearInterval(this._timer);
				this._timer = null;
			}
		},
		formatTime(ts) {
			if (!ts) return '-';
			const d = new Date(Number(ts) * 1000);
			const m = (d.getMonth() + 1).toString().padStart(2, '0');
			const day = d.getDate().toString().padStart(2, '0');
			const h = d.getHours().toString().padStart(2, '0');
			const mi = d.getMinutes().toString().padStart(2, '0');
			return `${m}-${day} ${h}:${mi}`;
		},
	},
};
</script>

<style lang="scss" scoped>
.massage-panel { background: #fff; padding: 24rpx; border-radius: 16rpx; margin: 0 24rpx; }

.top-row { display: flex; align-items: center; flex-wrap: wrap; gap: 12rpx; margin-bottom: 16rpx; }

/* 到店签到 */
.arrived-tip {
	display: flex; align-items: flex-start;
	background: #F0FDF4; border: 1rpx solid #BBF7D0;
	border-radius: 12rpx; padding: 18rpx 20rpx; margin-bottom: 16rpx;
	font-size: 26rpx; color: #16A34A; font-weight: 600;
}
.arrival-card {
	display: flex; flex-direction: column; align-items: center;
	background: #FFFBF5; border: 1rpx dashed #F0C9A0;
	border-radius: 16rpx; padding: 28rpx 20rpx; margin-bottom: 16rpx;
}
.arrival-title { display: flex; align-items: center; font-size: 28rpx; font-weight: 600; color: #1F2937; margin-bottom: 20rpx; }
.arrival-qr { background: #fff; padding: 16rpx; border-radius: 12rpx; }
.arrival-tip { margin-top: 18rpx; font-size: 24rpx; color: #94A3B8; }
.status-tag { display: inline-block; padding: 4rpx 14rpx; border: 1rpx solid; border-radius: 16rpx; font-size: 24rpx; }
.direct-tag {
	display: inline-block; padding: 4rpx 14rpx;
	background: linear-gradient(90deg, #FF8917 0%, #FF6B35 100%);
	color: #fff; border-radius: 16rpx; font-size: 22rpx;
}
.appoint { display: flex; align-items: center; margin-left: auto; }
.appoint-time { font-size: 26rpx; color: #666; }

/* 技师迟到（未到飞单阈值）温和提示 */
.late-notice {
	display: flex; align-items: flex-start;
	background: #FFFBEB; border: 1rpx solid #FDE68A;
	margin: 8rpx 0 16rpx; padding: 18rpx 20rpx; border-radius: 12rpx;
}
.late-notice.urgent { background: #FEF2F2; border-color: #FECACA; }
.ln-text { flex: 1; display: flex; flex-direction: column; }
.ln-title { font-size: 26rpx; font-weight: 600; color: #D97706; margin-bottom: 4rpx; }
.late-notice.urgent .ln-title { color: #DC2626; }
.ln-sub { font-size: 22rpx; color: #92400E; line-height: 1.5; }
.late-notice.urgent .ln-sub { color: #B91C1C; }

/* 服务进行中实时计时器 */
.live-timer {
	display: flex; align-items: center;
	background: linear-gradient(135deg, #FFF6F0 0%, #FFEEDD 100%);
	padding: 18rpx 20rpx; border-radius: 12rpx;
	margin: 8rpx 0 16rpx;
}
.timer-dot {
	width: 16rpx; height: 16rpx; border-radius: 50%;
	background: #FF6B35; margin-right: 14rpx;
	box-shadow: 0 0 0 4rpx rgba(255, 107, 53, 0.2);
	animation: timerPulse 1.4s ease-in-out infinite;
}
@keyframes timerPulse {
	0%, 100% { box-shadow: 0 0 0 4rpx rgba(255, 107, 53, 0.2); }
	50% { box-shadow: 0 0 0 10rpx rgba(255, 107, 53, 0.0); }
}
.timer-label { font-size: 26rpx; color: #d65a00; font-weight: 600; }
.timer-elapsed { font-size: 28rpx; color: #d65a00; font-weight: 700; margin-left: auto; font-family: monospace; }

.service-row { padding: 12rpx 0; font-size: 26rpx; color: #444; }
.service-label { color: #999; }
.duration { color: #999; font-size: 22rpx; margin-left: 8rpx; }

/* 时长不足警示 */
.duration-hint {
	display: flex; align-items: flex-start;
	background: #FFF7E6; border: 1rpx solid #FFD591;
	margin: 12rpx 0; padding: 18rpx 20rpx; border-radius: 12rpx;
}
.duration-hint.abnormal { background: #FFF1F0; border-color: #FFA39E; }
.hint-icon { font-size: 36rpx; margin-right: 14rpx; line-height: 1.2; }
.hint-text { flex: 1; display: flex; flex-direction: column; }
.hint-title { font-size: 26rpx; font-weight: 600; color: #d46b08; margin-bottom: 4rpx; }
.duration-hint.abnormal .hint-title { color: #cf1322; }
.hint-detail { font-size: 22rpx; color: #888; }

.therapist-row { display: flex; align-items: center; padding: 16rpx 0; border-top: 1rpx solid #f5f5f5; margin-top: 16rpx; }
.therapist-avatar { width: 64rpx; height: 64rpx; border-radius: 50%; margin-right: 16rpx; background: #f5f5f5; }
.avatar-placeholder { display: flex; align-items: center; justify-content: center; }
.therapist-info { flex: 1; display: flex; flex-direction: column; }
.therapist-label { font-size: 22rpx; color: #999; }
.therapist-name { font-size: 28rpx; color: #333; font-weight: 600; }
.remark-block { display: flex; align-items: flex-start; margin-top: 16rpx; padding-top: 16rpx; border-top: 1rpx solid #f5f5f5; }
.remark-text { font-size: 24rpx; color: #666; line-height: 1.5; }
</style>
