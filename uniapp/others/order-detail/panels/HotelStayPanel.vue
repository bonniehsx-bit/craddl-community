<template>
	<view class="hotel-stay-panel">
		<!-- 酒店定位卡（公共组件）-->
		<view class="card">
			<MerchantLocationCard :merchant="merchantInfo"></MerchantLocationCard>
		</view>

		<!-- 房型 + 日期轴 + 入住人 -->
		<view class="card stay-card">
			<view class="card-header-title">入住信息</view>

			<view class="room-intro">
				<image :src="data.room_image || '/static/images/default_goods.png'" class="room-thumb" mode="aspectFill"></image>
				<view class="room-details">
					<view class="r-name">{{ data.room_name || '未知房型' }}</view>
					<view class="r-tags">
						<text class="tag">{{ data.room_type || '标准房型' }}</text>
					</view>
				</view>
			</view>

			<view class="date-axis-box">
				<view class="node left">
					<text class="lbl">入住</text>
					<view class="val">{{ formatDate(data.start_date) }}</view>
					<text class="week">{{ getWeek(data.start_date) }}</text>
				</view>
				<view class="line-center">
					<view class="pill">{{ data.nights || '1晚' }}</view>
					<view class="dashed-line"></view>
				</view>
				<view class="node right">
					<text class="lbl">离店</text>
					<view class="val">{{ formatDate(data.end_date) }}</view>
					<text class="week">{{ getWeek(data.end_date) }}</text>
				</view>
			</view>

			<view class="divider"></view>

			<view class="guest-info">
				<view class="info-row align-start">
					<text class="label">入住人</text>
					<view class="value-list">
						<block v-if="guestList.length">
							<view class="guest-item" v-for="(n, i) in guestList" :key="i">
								<tui-icon name="people" :size="14" color="#666" style="margin-right:8rpx"></tui-icon>
								<text>{{ n }}</text>
							</view>
						</block>
						<view class="guest-item" v-else>
							<tui-icon name="people" :size="14" color="#666" style="margin-right:8rpx"></tui-icon>
							<text>{{ data.contact_name || '未填写' }}</text>
						</view>
					</view>
				</view>

				<view class="info-row">
					<text class="label">联系电话</text>
					<text class="value phone">{{ data.contact_phone || '--' }}</text>
				</view>
			</view>
		</view>
	</view>
</template>

<script>
import MerchantLocationCard from '../shell/MerchantLocationCard.vue';

export default {
	name: 'HotelStayPanel',
	components: { MerchantLocationCard },
	props: {
		/** 后端 getMobileDetailPanels 返回的 data 对象 */
		data: { type: Object, required: true },
		order: { type: Object, required: true },
		list: { type: Array, default: () => [] }
	},
	computed: {
		guestList() {
			return Array.isArray(this.data.guest_list) ? this.data.guest_list : [];
		},
		/** 把 Hotel data 转换为通用 MerchantLocationCard 的 merchant 结构 */
		merchantInfo() {
			return {
				name:      this.data.hotel_name,
				address:   this.data.hotel_address,
				latitude:  this.data.latitude,
				longitude: this.data.longitude,
				phone:     this.data.hotel_phone,
			};
		}
	},
	methods: {
		formatDate(s) {
			if (!s) return '--/--';
			try {
				const d = new Date(String(s).replace(/-/g, '/'));
				const pad = n => String(n).padStart(2, '0');
				return `${pad(d.getMonth() + 1)}/${pad(d.getDate())}`;
			} catch (e) { return '--/--'; }
		},
		getWeek(s) {
			if (!s) return '';
			try {
				const d = new Date(String(s).replace(/-/g, '/'));
				return ['周日','周一','周二','周三','周四','周五','周六'][d.getDay()];
			} catch (e) { return ''; }
		}
	}
};
</script>

<style scoped lang="scss">
.hotel-stay-panel {
	.card {
		background: #fff;
		border-radius: 20rpx;
		margin: 20rpx 24rpx 0;
		padding: 24rpx 28rpx;
	}
	.card-header-title {
		font-size: 30rpx;
		font-weight: 600;
		color: #1A1A1A;
		margin-bottom: 20rpx;
	}

	/* 入住卡 */
	.room-intro {
		display: flex;
		margin-bottom: 30rpx;

		.room-thumb {
			width: 160rpx;
			height: 120rpx;
			border-radius: 12rpx;
			background: #f5f5f5;
		}
		.room-details {
			flex: 1;
			margin-left: 20rpx;
			display: flex;
			flex-direction: column;
			justify-content: center;

			.r-name {
				font-size: 28rpx;
				color: #1A1A1A;
				font-weight: 500;
				margin-bottom: 10rpx;
			}
			.r-tags .tag {
				font-size: 22rpx;
				color: #FF8917;
				background: #FFF6E8;
				padding: 4rpx 14rpx;
				border-radius: 20rpx;
			}
		}
	}

	/* 日期轴 */
	.date-axis-box {
		display: flex;
		align-items: center;
		padding: 10rpx 0;

		.node {
			flex: 1;
			display: flex;
			flex-direction: column;

			.lbl { font-size: 22rpx; color: #999; margin-bottom: 6rpx; }
			.val { font-size: 36rpx; font-weight: 700; color: #1A1A1A; line-height: 1.2; }
			.week { font-size: 22rpx; color: #999; margin-top: 6rpx; }
		}
		.node.right {
			align-items: flex-end;
			text-align: right;
		}
		.line-center {
			flex: 1;
			display: flex;
			flex-direction: column;
			align-items: center;
			margin: 0 20rpx;

			.pill {
				font-size: 22rpx;
				color: var(--theme-color);
				background: rgba(230, 61, 61, 0.08);
				padding: 4rpx 14rpx;
				border-radius: 18rpx;
				margin-bottom: 8rpx;
			}
			.dashed-line {
				width: 100%;
				height: 2rpx;
				background: linear-gradient(to right, transparent 0, transparent 50%, #e5e5e5 50%, #e5e5e5 100%);
				background-size: 16rpx 2rpx;
			}
		}
	}

	.divider {
		height: 1rpx;
		background: #f5f5f5;
		margin: 28rpx 0;
	}

	/* 入住人 */
	.guest-info {
		.info-row {
			display: flex;
			align-items: center;
			margin-bottom: 14rpx;

			&.align-start { align-items: flex-start; }
			&:last-child { margin-bottom: 0; }

			.label { width: 140rpx; color: #999; font-size: 26rpx; flex-shrink: 0; }
			.value { flex: 1; color: #333; font-size: 26rpx; }
			.value.phone {
				font-family: 'DIN Alternate', sans-serif;
				color: #333;
			}
			.value-list {
				flex: 1;
				display: flex;
				flex-direction: column;
				gap: 10rpx;

				.guest-item {
					display: flex;
					align-items: center;
					font-size: 26rpx;
					color: #333;
				}
			}
		}
	}
}
</style>
