<template>
	<view class="booking-panel">
		<view class="head">
			<text class="title">{{ (data.nouns && data.nouns.book) || '预约' }}信息</text>
			<text class="state" :style="'color:' + stateColor">{{ data.status_text }}</text>
		</view>

		<view class="time-row">
			<view class="time-main">
				<text class="time">{{ data.time_text }}</text>
				<text v-if="data.staff_name" class="sub">{{ (data.nouns && data.nouns.staff) || '人员' }}:{{ data.staff_name }}</text>
				<text v-if="data.profile_name" class="sub">{{ (data.nouns && data.nouns.profile) || '预约人' }}:{{ data.profile_name }}</text>
			</view>
			<view v-if="data.queue_no" class="queue" :style="'background:' + hexToRgba(themeColor, 0.1) + ';color:' + themeColor">
				<text class="queue-label">序号</text>
				<text class="queue-num">{{ data.queue_no }}</text>
			</view>
		</view>

		<MerchantLocationCard v-if="data.shop && data.shop.name" :merchant="data.shop"></MerchantLocationCard>

		<view class="go" :style="'color:' + themeColor" @tap="goDetail">
			<text>查看{{ (data.nouns && data.nouns.book) || '预约' }}凭证</text>
			<tui-icon name="arrowright" :size="14" :color="themeColor"></tui-icon>
		</view>
	</view>
</template>

<script>
import MerchantLocationCard from '../shell/MerchantLocationCard.vue';

/** 预约服务:付费预约订单的详情面板(后端 BookingOrderHandler::getMobileDetailPanels 下发) */
export default {
	name: 'BookingPanel',
	components: { MerchantLocationCard },
	props: {
		data: { type: Object, required: true },
		order: { type: Object, default: () => ({}) },
		list: { type: Array, default: () => [] }
	},
	computed: {
		stateColor() {
			return { 10: '#D48806', 20: this.themeColor, 40: '#4A7C59', 70: '#B8352F' }[this.data.status] || '#999999';
		}
	},
	methods: {
		goDetail() {
			uni.navigateTo({ url: '/plugins4/booking/detail?id=' + this.data.appointment_id });
		}
	}
};
</script>

<style lang="scss" scoped>
.booking-panel { background: #fff; border-radius: 16rpx; padding: 24rpx; display: flex; flex-direction: column; gap: 18rpx; }
.head { display: flex; align-items: center; justify-content: space-between; }
.title { font-size: 30rpx; font-weight: 600; color: #222; }
.state { font-size: 26rpx; font-weight: 600; }
.time-row { display: flex; align-items: center; gap: 20rpx; }
.time-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6rpx; }
.time { font-size: 32rpx; font-weight: 700; color: #1a1a1a; }
.sub { font-size: 24rpx; color: #888; }
.queue { min-width: 120rpx; padding: 10rpx 16rpx; border-radius: 14rpx; display: flex; flex-direction: column; align-items: center; }
.queue-label { font-size: 20rpx; }
.queue-num { font-size: 48rpx; font-weight: 700; line-height: 1.1; }
.go { display: flex; align-items: center; justify-content: center; gap: 6rpx; font-size: 26rpx; padding-top: 6rpx; }
</style>
