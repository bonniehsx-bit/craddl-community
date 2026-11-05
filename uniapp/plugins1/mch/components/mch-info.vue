<template>
	<scroll-view scroll-y class="mch-scroll-view">
		<view class="merchant-info-wrapper">

			<view class="info-card shadow-card" @click="$emit('location')">
				<view class="card-title">商家地址</view>
				<view class="address-row">
					<view class="icon-box">
						<tui-icon name="position-fill" :color="themeColor" size="24"></tui-icon>
					</view>
					<view class="address-text">{{ merchant.address }}</view>
					<view class="distance-text" v-if="merchant.distance">距您 {{ merchant.distance }}</view>
					<tui-icon name="arrowright" size="16" color="#ccc"></tui-icon>
				</view>
			</view>

			<view class="info-card shadow-card">
				<view class="card-title">商家信息</view>

				<view class="info-cell">
					<text class="label">营业时间</text>
					<text class="value">{{ merchant.opening_hours || '未设置' }}</text>
				</view>

				<view class="info-cell" @click="$emit('call')">
					<text class="label">联系电话</text>
					<text class="value link" :style="{ color: themeColor }">{{ merchant.mobile || merchant.tel || '未设置'
						}}</text>
				</view>

				<view class="info-cell">
					<text class="label">商家服务</text>
					<view class="tags-row">
						<view class="tag"
							v-if="merchant.delivery_options && merchant.delivery_options.includes('pickup')">到店自提</view>
						<view class="tag"
							v-if="merchant.delivery_options && merchant.delivery_options.includes('city')">同城配送</view>
					</view>
				</view>

				<view class="info-cell" @click="$emit('license')">
					<text class="label">营业执照</text>
					<view class="action-right">
						<text class="tip">查看</text>
						<tui-icon name="arrowright" size="14" color="#ccc"></tui-icon>
					</view>
				</view>

				<view class="info-cell" @click="$emit('qrcode')">
					<text class="label">店铺二维码</text>
					<tui-icon name="qrcode" :color="themeColor" size="20"></tui-icon>
				</view>
			</view>

			<view class="info-card shadow-card">
				<view class="card-title">商家简介</view>
				<view class="intro-text" :class="{ 'expanded': isExpanded }">
					{{ merchant.description || '暂无简介' }}
				</view>
				<view class="expand-btn" @click="isExpanded = !isExpanded" :style="{ color: themeColor }">
					{{ isExpanded ? '收起' : '展开全部' }}
					<tui-icon :name="isExpanded ? 'arrowup' : 'arrowdown'" :size="14" :color="themeColor"></tui-icon>
				</view>
			</view>

			<view class="bottom-spacer"></view>
		</view>
	</scroll-view>
</template>

<script>
export default {
	name: "mch-info",
	props: {
		merchant: {
			type: Object,
			default: () => ({})
		}
	},
	data() {
		return {
			isExpanded: false
		}
	},
	emits: ['location', 'call', 'license', 'qrcode']
}
</script>

<style lang="scss" scoped>
.mch-scroll-view {
	height: 100%;
	background-color: #f7f8fa;
}

.merchant-info-wrapper {
	padding: 24rpx;
}

.info-card {
	background: #fff;
	border-radius: 16rpx;
	padding: 30rpx;
	margin-bottom: 24rpx;
	box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.04);

	.card-title {
		font-size: 30rpx;
		font-weight: bold;
		margin-bottom: 24rpx;
		color: #333;
		padding-left: 16rpx;
		border-left: 6rpx solid #333;
		/* 标题装饰线 */
		line-height: 1;
	}

	.address-row {
		display: flex;
		align-items: center;
		font-size: 28rpx;
		color: #333;

		.icon-box {
			margin-right: 20rpx;
		}

		.address-text {
			flex: 1;
			line-height: 1.5;
		}

		.distance-text {
			font-size: 24rpx;
			color: #999;
			margin: 0 10rpx;
		}
	}

	.info-cell {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 24rpx 0;
		border-bottom: 1rpx solid #f9f9f9;
		font-size: 28rpx;

		&:last-child {
			border-bottom: none;
		}

		.label {
			color: #666;
			width: 160rpx;
		}

		.value {
			flex: 1;
			text-align: right;
			color: #333;
		}

		.link {
			text-decoration: underline;
		}

		.action-right {
			display: flex;
			align-items: center;

			.tip {
				font-size: 24rpx;
				color: #999;
				margin-right: 4rpx;
			}
		}

		.tags-row {
			display: flex;
			gap: 12rpx;
			justify-content: flex-end;

			.tag {
				font-size: 22rpx;
				color: #555;
				background: #f0f0f0;
				padding: 4rpx 12rpx;
				border-radius: 6rpx;
			}
		}
	}

	.intro-text {
		font-size: 26rpx;
		color: #666;
		line-height: 1.8;
		overflow: hidden;
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 3;
		transition: all 0.3s;

		&.expanded {
			-webkit-line-clamp: unset;
		}
	}

	.expand-btn {
		text-align: center;
		font-size: 24rpx;
		margin-top: 20rpx;
		padding-top: 20rpx;
		border-top: 1rpx dashed #eee;
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 6rpx;
	}
}

.bottom-spacer {
	height: calc(env(safe-area-inset-bottom) + 120rpx);
	width: 100%;
}
</style>