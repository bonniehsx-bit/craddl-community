<template>
	<view class="goods-card">
		<!-- 商家头条 -->
		<view class="shop-header" v-if="order.mch" @tap="emitAction('mchDetail', order.mch)">
			<tui-icon name="shop" :size="18" :color="themeColor"></tui-icon>
			<text class="shop-name">{{ order.mch.mch_name }}</text>
			<tui-icon name="arrowright" :size="14" color="#ccc"></tui-icon>
		</view>

		<!-- 商品列表 -->
		<view class="goods-list">
			<view class="goods-item" :class="{ 'is-voided': Number(item.status) === 2 }" v-for="(item, index) in list" :key="index"
				@tap="onGoodsTap(item)">
				<image :src="item.goods.cover_pic" class="g-img" mode="aspectFill"></image>
				<view class="g-info">
					<text class="g-name">
						<text v-if="Number(item.use_integral) > 0" class="tag-integral">{{ $noun('integral') }}兑换</text>
						{{ item.goods.name }}
					</text>
					<!-- 只有一个「默认」规格的不显示,那是没有信息量的占位 -->
					<view class="g-attr" v-if="attrText(item)">{{ attrText(item) }}</view>
					<view class="g-bottom">
						<!-- 积分兑换商品：X 积分 (+¥Y 可选) -->
						<view v-if="Number(item.use_integral) > 0" class="g-price integral" :style="{ color: themeColor }">
							<text class="integral-val">{{ parseInt(item.use_integral) }}</text>
							<text class="integral-unit">{{ $noun('integral') }}</text>
							<text v-if="Number(item.pay_price) > 0" class="extra">+ ¥{{ fmtPrice(item.pay_price) }}</text>
						</view>
						<text v-else class="g-price" :style="{ color: themeColor }">¥{{ fmtPrice(item.total_price) }}</text>
						<text class="g-num">x{{ item.num }}</text>
					</view>
					<!-- 售后状态:取消订单退款后明细也会标成「售后已完成」但没有售后单(has_after_sale=false),
					     这种不显示 —— 退款进度看上面的「退款进度」卡;老接口没这个字段时照旧显示 -->
					<view class="refund-line" v-if="item.refund_status > 0 && item.has_after_sale !== false">
						<text class="as-chip" :class="'as-' + afterSaleChip(item).tone">{{ afterSaleChip(item).text }}</text>
						<view class="mini-btn" @tap.stop="handleItemRefund(item, 'detail')">售后详情</view>
					</view>
					<!-- 最近一次售后被商家拒绝:明细已回到可申请状态,但要让顾客能点进去看拒绝原因 -->
					<view class="refund-line" v-else-if="Number(item.last_refund_status) === 2">
						<text class="as-chip as-gray">售后被拒绝</text>
						<view class="refund-btns">
							<view class="mini-btn" @tap.stop="handleItemRefund(item, 'detail')">查看原因</view>
							<view class="mini-btn" v-if="canItemApplyRefund(item)" @tap.stop="handleItemRefund(item, 'apply')">重新申请</view>
						</view>
					</view>
					<view class="refund-line" v-else-if="canItemApplyRefund(item)">
						<view class="mini-btn" @tap.stop="handleItemRefund(item, 'apply')">申请售后</view>
					</view>
				</view>
			</view>
		</view>
	</view>
</template>

<script>
import { fmtPrice } from './utils.js';

export default {
	name: 'OrderGoodsCard',
	props: {
		order: { type: Object, required: true },
		list: { type: Array, default: () => [] },
		// themeColor 由全局 mixin 注入
	},
	computed: {
		/**
		 * 商品能不能点进详情页 —— 只有商城订单能。
		 *
		 * 判 type 而不判 plugin:商城订单的 plugin 有 '' 和 'mall' 两种历史值,
		 * 只判 plugin 会把后者(库里 20 单)误当成插件订单。type 是核心常量
		 * Order::TYPE_NORMAL,判它同时也不需要认识任何插件名。
		 */
		canOpenGoods() {
			return !!this.order && this.order.type === 'normal';
		}
	},
	methods: {
		fmtPrice,
		/**
		 * 插件订单里的"商品"是服务 / 房型 / 券 / 权益,goods_id 落在插件自己的表上,
		 * 拿它去跳 /others/goods/goods 要么查无此商品,要么显示成另一个不相干的商品。
		 * 所以非商城订单直接吞掉点击。
		 */
		onGoodsTap(item) {
			if (!this.canOpenGoods) {
				return;
			}
			this.emitAction('goodsDetail', item);
		},
		emitAction(type, data) {
			this.$emit('action', { type, data });
		},
		handleItemRefund(item, mode) {
			this.emitAction(mode === 'detail' ? 'refundDetail' : 'applyRefund', item);
		},
		canItemApplyRefund(item) {
			if (Number(this.order.is_ban_cancel) === 1) return false; // 商城「禁止取消/退款」时,隐藏用户端申请售后入口
			// 不走通用售后的订单类型由插件声明(getOrderBehaviors.no_user_after_sale),后端下发;老接口没这个字段时照常显示
			if (this.order.allow_after_sale === false) return false;
			if (Number(item.refund_status) > 0) return false;
			// 不支持售后的商品:后端 RefundService::apply 一定拒绝,按钮出来也是点了报错。
			// 认下单时的快照 can_refund(商家后来改开关不影响已买的单);老接口没这个字段时退回看商品开关
			if (item.can_refund !== undefined) {
				if (!item.can_refund) return false;
			} else if (item.goods && !Number(item.goods.is_refund)) return false;
			return [20, 30, 40].includes(Number(this.order.status));
		},
		attrText(item) {
			const vals = (Array.isArray(item.attr) ? item.attr : [])
				.map(a => a && a.value)
				.filter(v => v && v !== '默认');
			return vals.join(' ');
		},
		// 售后状态小标签:退掉作废的(明细 status=2)叫「已退款」,换货完成没作废的叫「售后完成」
		afterSaleChip(item) {
			const s = Number(item.refund_status);
			if (s === 10 || s === 20) return { text: '售后中', tone: 'orange' };
			if (s === 30) return Number(item.status) === 2 ? { text: '已退款', tone: 'green' } : { text: '售后完成', tone: 'blue' };
			if (s === 40) return { text: '售后关闭', tone: 'gray' };
			return { text: '', tone: 'gray' };
		}
	}
};
</script>

<style scoped lang="scss">
.goods-card {
	background: #fff;
	border-radius: 20rpx;
	margin: 20rpx 24rpx 0;
	overflow: hidden;
}
.shop-header {
	display: flex;
	align-items: center;
	padding: 24rpx 28rpx;
	border-bottom: 1rpx solid #f5f5f5;

	.shop-name {
		flex: 1;
		margin-left: 10rpx;
		font-size: 28rpx;
		font-weight: 600;
		color: #1A1A1A;
	}
}
.goods-list {
	padding: 20rpx 28rpx;
}
.goods-item {
	display: flex;
	padding: 16rpx 0;
	border-bottom: 1rpx solid #fafafa;

	&:last-child { border-bottom: none; }

	/* 退掉作废的商品:整行变淡、价格划线,一眼看出不会再发 */
	&.is-voided {
		.g-img { opacity: 0.45; }
		.g-name, .g-attr { color: #9AA0A8; }
		.g-price { color: #9AA0A8 !important; text-decoration: line-through; font-weight: 400; }
	}

	.g-img {
		width: 150rpx;
		height: 150rpx;
		border-radius: 12rpx;
		background: #f5f5f5;
		flex-shrink: 0;
	}
	.g-info {
		flex: 1;
		margin-left: 20rpx;
		display: flex;
		flex-direction: column;
	}
	.g-name {
		font-size: 28rpx;
		color: #333;
		line-height: 1.4;
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 2;
		line-clamp: 2;
		overflow: hidden;
	}
	.g-attr {
		font-size: 22rpx;
		color: #999;
		margin-top: 8rpx;
	}
	.g-bottom {
		margin-top: auto;
		display: flex;
		justify-content: space-between;
		align-items: center;
	}
	.g-price {
		font-size: 28rpx;
		color: #FF3B30; /* 兜底色;实际由内联 :style 绑 themeColor,与「实付款」统一为主题色(取不到主题色时回退此红) */
		font-weight: 600;

		&.integral {
			display: flex;
			align-items: baseline;
			gap: 4rpx;

			.integral-val {
				font-size: 30rpx;
				font-weight: 700;
				font-family: 'DIN Alternate', sans-serif;
			}
			.integral-unit { font-size: 22rpx; }
			.extra { font-size: 22rpx; color: #999; margin-left: 6rpx; }
		}
	}
	.tag-integral {
		display: inline-block;
		font-size: 20rpx;
		color: #FF8917;
		background: #FFF6E8;
		padding: 2rpx 10rpx;
		border-radius: 8rpx;
		margin-right: 6rpx;
		vertical-align: middle;
		font-weight: normal;
	}
	.g-num {
		font-size: 24rpx;
		color: #999;
	}
	.refund-line {
		margin-top: 10rpx;
		display: flex;
		justify-content: space-between;
		align-items: center;
	}
	.as-chip {
		font-size: 22rpx;
		line-height: 40rpx;
		padding: 0 16rpx;
		border-radius: 20rpx;
		font-weight: 500;

		&.as-orange { background: #FFF4E5; color: #B45309; }
		&.as-green { background: #E6F5EC; color: #177245; }
		&.as-blue { background: #E8F1FD; color: #1D5FB8; }
		&.as-gray { background: #F1F2F4; color: #6B7079; }
	}
	.refund-btns {
		display: flex;
		gap: 12rpx;
	}
	.mini-btn {
		font-size: 22rpx;
		padding: 6rpx 16rpx;
		border: 1rpx solid #dcdfe6;
		border-radius: 20rpx;
		color: #606266;
	}
}
</style>
