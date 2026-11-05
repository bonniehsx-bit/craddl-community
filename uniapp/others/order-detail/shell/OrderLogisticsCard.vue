<template>
	<view v-if="shouldShow">
		<!-- 快递物流：点击拉起物流详情 -->
		<block v-if="order.deliver_type === 'express' && isExpressValid">
			<view class="logistics-card" v-for="(exp, i) in expressList" :key="i" @tap="emitAction('express', exp.id)">
				<view class="log-left">
					<view class="icon-circle blue">
						<tui-icon name="transport" :size="22" color="#fff"></tui-icon>
					</view>
				</view>
				<view class="log-right">
					<view class="log-header">
						<text class="company">{{ exp.express_name }}</text>
						<text class="no">{{ exp.express_no }}</text>
						<view class="copy-btn" @tap.stop="copyText(exp.express_no)">复制</view>
					</view>
					<view class="log-status">{{ exp.status_text || '点击查看详细物流轨迹' }}</view>
					<view class="log-time" v-if="exp.time">{{ exp.time }}</view>
				</view>
				<tui-icon name="arrowright" :size="14" color="#ccc" class="arrow"></tui-icon>
			</view>
		</block>

		<!-- 同城配送：商家自配送状态 + 预计到达 -->
		<view class="logistics-card city-mode" v-else-if="order.deliver_type === 'city'">
			<view class="log-left">
				<view class="icon-circle" :style="{ background: hexToRgba(themeColor, 0.1), color: themeColor }">
					<tui-icon name="people" :size="22" :color="themeColor"></tui-icon>
				</view>
			</view>
			<view class="log-right">
				<view class="log-header">
					<text class="company">同城配送</text>
					<view class="status-tag" :style="{ color: themeColor, borderColor: themeColor }">
						{{ cityStatusText }}
					</view>
				</view>
				<view class="log-status">由商家配送</view>
				<view class="log-time highlight" v-if="order.expected_arrival_time">
					预计送达：{{ order.expected_arrival_time }}
				</view>
			</view>
		</view>

		<!-- 无需物流发货：后台填写的发货备注（send_type=2） -->
		<view class="logistics-card remark-mode" v-else-if="hasNoExpressRemark">
			<view class="log-left">
				<view class="icon-circle green">
					<tui-icon name="notice" :size="22" color="#fff"></tui-icon>
				</view>
			</view>
			<view class="log-right">
				<view class="log-header">
					<text class="company">发货说明</text>
					<view class="status-tag" style="color:#52C41A;border-color:#52C41A;">无需物流</view>
				</view>
				<view class="log-status">{{ order.no_express_remark }}</view>
			</view>
		</view>
	</view>
</template>

<script>
import { hexToRgba } from './utils.js';

export default {
	name: 'OrderLogisticsCard',
	props: {
		order: { type: Object, required: true },
		expressList: { type: Array, default: () => [] },
		// themeColor 由全局 mixin 注入
	},
	computed: {
		shouldShow() {
			if (!this.order) return false;
			// 有「无需物流」发货备注时(任何配送方式)都显示这张卡
			if (this.hasNoExpressRemark) return true;
			if (this.order.deliver_type === 'express') return this.isExpressValid;
			if (this.order.deliver_type === 'city') return true;
			return false;
		},
		// 后台「无需物流」发货(send_type=2)填写的备注,与买家备注 remarks 隔离
		hasNoExpressRemark() {
			return !!(this.order && this.order.no_express_remark && String(this.order.no_express_remark).trim());
		},
		isExpressValid() {
			// 有物流记录才显示；未发货订单（status<30）自然没有
			return Array.isArray(this.expressList) && this.expressList.length > 0;
		},
		cityStatusText() {
			// 简单配送:直接按订单状态显示(20待配送/30配送中/40已送达)
			const map = { 20: '待配送', 30: '配送中', 40: '已送达' };
			return map[this.order.status] || '待配送';
		}
	},
	methods: {
		hexToRgba,
		emitAction(type, data) { this.$emit('action', { type, data }); },
		copyText(text) {
			uni.setClipboardData({
				data: text,
				success: () => uni.showToast({ title: '复制成功', icon: 'none' })
			});
		}
	}
};
</script>

<style scoped lang="scss">
.logistics-card {
	background: #fff;
	border-radius: 20rpx;
	margin: 20rpx 24rpx 0;
	padding: 24rpx 28rpx;
	display: flex;
	align-items: center;

	.log-left {
		margin-right: 20rpx;

		.icon-circle {
			width: 60rpx;
			height: 60rpx;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;

			&.blue {
				background: linear-gradient(135deg, #69C0FF, #1890FF);
			}
			&.green {
				background: linear-gradient(135deg, #95DE64, #52C41A);
			}
		}
	}
	.log-right {
		flex: 1;
		overflow: hidden;

		.log-header {
			display: flex;
			align-items: center;
			gap: 16rpx;
			margin-bottom: 6rpx;

			.company {
				font-size: 26rpx;
				color: #1A1A1A;
				font-weight: 500;
			}
			.no {
				font-size: 22rpx;
				color: #666;
				font-family: 'DIN Alternate', sans-serif;
			}
			.copy-btn {
				font-size: 20rpx;
				padding: 2rpx 10rpx;
				border: 1rpx solid #dcdfe6;
				border-radius: 12rpx;
				color: #606266;
			}
			.status-tag {
				padding: 2rpx 10rpx;
				border: 1rpx solid currentColor;
				border-radius: 14rpx;
				font-size: 20rpx;
			}
		}
		.log-status {
			font-size: 24rpx;
			color: #333;
			line-height: 1.4;
		}
		.log-time {
			font-size: 22rpx;
			color: #999;
			margin-top: 4rpx;

			&.highlight {
				color: var(--theme-color, #E63D3D);
			}
		}
	}
	.arrow {
		margin-left: 10rpx;
	}
	.call-btn {
		margin-left: 12rpx;
		padding: 12rpx;
		border-radius: 50%;
		background: rgba(230,61,61,0.08);
	}
}
</style>
