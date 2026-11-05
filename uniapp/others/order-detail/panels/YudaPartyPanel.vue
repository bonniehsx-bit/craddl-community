<template>
	<view class="yuda-party-panel">
		<!-- 拼局角色 + 时间 -->
		<view class="top-row">
			<view class="role-tag" :style="{ color: themeColor, borderColor: themeColor }">
				{{ roleLabel }}
			</view>
			<view class="appoint">
				<tui-icon name="clock" :size="16" color="#666" style="margin-right:8rpx"></tui-icon>
				<text class="appoint-time">{{ data.appoint_time || '时间待定' }}</text>
			</view>
		</view>

		<!-- 门店信息（统一组件）-->
		<MerchantLocationCard v-if="data.shop" :merchant="data.shop"></MerchantLocationCard>

		<!-- 组局寄语 -->
		<view class="remark-block" v-if="data.remark">
			<tui-icon name="chat" :size="14" color="#999" style="margin-right:8rpx"></tui-icon>
			<text class="remark-text">{{ data.remark }}</text>
		</view>
	</view>
</template>

<script>
import MerchantLocationCard from '../shell/MerchantLocationCard.vue';

export default {
	name: 'YudaPartyPanel',
	components: { MerchantLocationCard },
	props: {
		data: { type: Object, required: true },
		order: { type: Object, default: () => ({}) }
	},
	computed: {
		roleLabel() {
			if (this.data.role === 'A') return '局长 · 发起人';
			if (this.data.role === 'B') return '参与人';
			return '拼单局';
		}
	}
};
</script>

<style scoped lang="scss">
.yuda-party-panel {
	background: #fff;
	border-radius: 20rpx;
	margin: 20rpx 24rpx 0;
	padding: 26rpx 28rpx;
}

.top-row {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 22rpx;

	.role-tag {
		font-size: 22rpx;
		padding: 4rpx 16rpx;
		border: 1rpx solid currentColor;
		border-radius: 20rpx;
		font-weight: 500;
	}
	.appoint {
		display: flex;
		align-items: center;

		.appoint-time {
			font-size: 26rpx;
			color: #333;
			font-weight: 500;
		}
	}
}

.remark-block {
	display: flex;
	align-items: flex-start;
	margin-top: 18rpx;
	padding: 14rpx 18rpx;
	background: #FFF6E8;
	border-radius: 10rpx;

	.remark-text {
		flex: 1;
		font-size: 24rpx;
		color: #666;
		line-height: 1.5;
	}
}
</style>
