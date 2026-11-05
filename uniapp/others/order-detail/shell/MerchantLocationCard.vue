<template>
	<view class="merchant-loc-card" @tap="openLocation">
		<view class="loc-ic" :style="{ background: hexToRgba(themeColor, 0.1), color: themeColor }">
			<tui-icon name="shop" :size="22" :color="themeColor"></tui-icon>
		</view>
		<view class="loc-info">
			<text class="loc-name">{{ merchant.name || merchant.mch_name || '--' }}</text>
			<view class="loc-addr-row">
				<tui-icon name="position" :size="12" color="#999"></tui-icon>
				<text class="loc-addr">{{ merchant.address || '暂无地址' }}</text>
			</view>
		</view>
		<view class="loc-actions">
			<view class="loc-btn" v-if="phone" @tap.stop="callShop">
				<view class="btn-circle" :style="{ color: themeColor, background: hexToRgba(themeColor, 0.1) }">
					<tui-icon name="voipphone" :size="18" :color="themeColor"></tui-icon>
				</view>
				<text>联系</text>
			</view>
			<view class="loc-btn" v-if="hasLatLng" @tap.stop="openLocation">
				<view class="btn-circle" style="color:#007AFF;background:rgba(0,122,255,0.1);">
					<tui-icon name="position-fill" :size="18"></tui-icon>
				</view>
				<text>导航</text>
			</view>
		</view>
	</view>
</template>

<script>
/**
 * 通用"门店位置卡"
 * 用途：HotelStayPanel 酒店、YudaPartyPanel 拼单商家、以及其他任何需要展示"门店+导航+电话"的场景共用
 *
 * 接收参数：
 *   merchant: { name/mch_name, address, latitude, longitude, phone/mobile/tel }
 *
 * themeColor / hexToRgba 由全局 mixin 注入，不需要显式传
 */
export default {
	name: 'MerchantLocationCard',
	props: {
		merchant: { type: Object, required: true }
	},
	computed: {
		phone() {
			return this.merchant.phone || this.merchant.mobile || this.merchant.tel || '';
		},
		// 容错：插件传 latitude/longitude 或 lat/lng 都接受
		// （之前 massage 后端传 lat/lng，组件这里写死 latitude/longitude 导致点击提示"暂无位置信息"）
		lat() {
			const v = this.merchant.latitude != null ? this.merchant.latitude : this.merchant.lat;
			return Number(v) || 0;
		},
		lng() {
			const v = this.merchant.longitude != null ? this.merchant.longitude : this.merchant.lng;
			return Number(v) || 0;
		},
		hasLatLng() {
			return Math.abs(this.lat) > 0.0001 && Math.abs(this.lng) > 0.0001;
		}
	},
	methods: {
		openLocation() {
			if (!this.hasLatLng) {
				uni.showToast({ title: '暂无位置信息', icon: 'none' });
				return;
			}
			uni.openLocation({
				latitude: this.lat,
				longitude: this.lng,
				name: this.merchant.name || this.merchant.mch_name || '',
				address: this.merchant.address || ''
			});
		},
		callShop() {
			if (!this.phone) return;
			uni.makePhoneCall({ phoneNumber: String(this.phone) });
		}
	}
};
</script>

<style scoped lang="scss">
.merchant-loc-card {
	display: flex;
	align-items: center;
	padding: 18rpx;
	background: #FAFBFC;
	border-radius: 14rpx;

	.loc-ic {
		width: 72rpx;
		height: 72rpx;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
	}
	.loc-info {
		flex: 1;
		margin: 0 18rpx;
		overflow: hidden;

		.loc-name {
			display: block;
			font-size: 28rpx;
			color: #1A1A1A;
			font-weight: 600;
			margin-bottom: 6rpx;
		}
		.loc-addr-row {
			display: flex;
			align-items: center;
			font-size: 22rpx;
			color: #999;

			.loc-addr {
				margin-left: 4rpx;
				flex: 1;
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}
		}
	}
	.loc-actions {
		display: flex;
		gap: 18rpx;
		flex-shrink: 0;

		.loc-btn {
			display: flex;
			flex-direction: column;
			align-items: center;
			font-size: 22rpx;
			color: #666;

			.btn-circle {
				width: 60rpx;
				height: 60rpx;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				margin-bottom: 4rpx;
			}
			&:active { opacity: 0.7; }
		}
	}
}
</style>
