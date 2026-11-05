<template>
	<view class="address-card" v-if="shouldShow">
		<view class="addr-left">
			<view class="icon-circle">
				<tui-icon :name="iconName" :size="22" color="#909399"></tui-icon>
			</view>
		</view>
		<view class="addr-right">
			<view class="user-row">
				<text class="name">{{ order.name || '--' }}</text>
				<text class="mobile">{{ order.mobile || '' }}</text>
				<view class="tag-outline" :style="{ color: themeColor, borderColor: themeColor }">
					{{ deliverTypeText }}
				</view>
			</view>
			<view class="addr-detail">{{ order.address || '暂无地址信息' }}</view>
		</view>
	</view>
</template>

<script>
export default {
	name: 'OrderAddressCard',
	props: {
		order: { type: Object, required: true },
		// themeColor 由全局 mixin 注入（mapState -> mall.themeColor），无需重复声明
	},
	computed: {
		/**
		 * 展示条件：
		 *   · 有 address 字段 (一般 express / city / pickup 都会写入地址或门店)
		 *   · deliver_type 不是 none（none = 无实体配送，不用显示地址）
		 */
		shouldShow() {
			if (!this.order) return false;
			const t = this.order.deliver_type;
			if (!t || t === 'none') return false;
			return !!(this.order.address || this.order.name || this.order.mobile);
		},
		deliverTypeText() {
			const map = {
				express: '快递配送',
				city:    '同城配送',
				pickup:  '到店自提',
			};
			return map[this.order.deliver_type] || '配送';
		},
		iconName() {
			if (this.order.deliver_type === 'pickup') return 'shop';
			if (this.order.deliver_type === 'city') return 'people';
			return 'position-fill';
		}
	}
};
</script>

<style scoped lang="scss">
.address-card {
	background: #fff;
	border-radius: 20rpx;
	margin: 20rpx 24rpx 0;
	padding: 26rpx 28rpx;
	display: flex;
	align-items: flex-start;
}
.addr-left {
	margin-right: 20rpx;

	.icon-circle {
		width: 72rpx;
		height: 72rpx;
		border-radius: 50%;
		background: #F2F3F5; /* 中性浅灰圆底,替代原三种渐变;图标用灰 #909399,类型靠图标形状+文字标签区分 */
		display: flex;
		align-items: center;
		justify-content: center;
	}
}
.addr-right {
	flex: 1;
	overflow: hidden;

	.user-row {
		display: flex;
		align-items: center;
		flex-wrap: wrap;
		gap: 16rpx;
		margin-bottom: 10rpx;

		.name {
			font-size: 30rpx;
			font-weight: 600;
			color: #1A1A1A;
		}
		.mobile {
			font-size: 26rpx;
			color: #606266;
			font-family: 'DIN Alternate', sans-serif;
		}
		.tag-outline {
			padding: 2rpx 12rpx;
			border: 1rpx solid currentColor;
			border-radius: 20rpx;
			font-size: 20rpx;
		}
	}
	.addr-detail {
		font-size: 26rpx;
		color: #666;
		line-height: 1.5;
		word-break: break-all;
	}
}
</style>
