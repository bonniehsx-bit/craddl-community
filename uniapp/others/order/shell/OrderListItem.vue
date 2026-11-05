<template>
	<view class="order-list-item" @tap="forwardAction({ type: 'detail', order })">
		<ListShopHeader :order="order" @action="forwardAction"></ListShopHeader>

		<ListGoodsList :items="order.items || []" :category-tag="categoryTag"></ListGoodsList>

		<!-- 退款进度一句话(后端给,说法见 RefundProgressService::summary);点整张卡进详情看每一笔 -->
		<view class="refund-line" v-if="order.refund_summary">
			<text class="refund-tag" :class="'state-' + order.refund_summary.state">{{ order.refund_summary.text }}</text>
			<text class="refund-more">查看进度 ›</text>
		</view>

		<ListSummary :order="order"></ListSummary>

		<ListActionBar :order="order" :actions="footerActions" @action="forwardAction"></ListActionBar>
	</view>
</template>

<script>
import ListShopHeader from './ListShopHeader.vue';
import ListGoodsList from './ListGoodsList.vue';
import ListSummary from './ListSummary.vue';
import ListActionBar from './ListActionBar.vue';

/**
 * 按 plugin / type 决定商品名前的小标签
 * 对应老的 OrderXxxItem 组件里各自的 `.tag-xxx` 色块
 */
const CATEGORY_TAG_MAP = {
	hotel:     { label: '酒店预订', color: '#4facfe' },
	voucher:   { label: '权益包',   color: '#FF8917' },
	group:     { label: '到店/团购', color: '#FF5FA2' },
	lucky:     { label: '手气拼团', color: '#D6201F' },
	integral:  { label: '积分兑换', color: '#FF8917' },
	yuda:      { label: '拼单/防鸽金', color: '#8A2BE2' },
	emotion:   { label: '情感测算', color: '#9370DB' },
	massage:   { label: '艾灸到家', color: '#FF6B35' },
	dining:    { label: '门店点单', color: '#1F6F5C' },
	booking:   { label: '预约服务', color: '#3D6A8C' },
};

export default {
	name: 'OrderListItem',
	components: { ListShopHeader, ListGoodsList, ListSummary, ListActionBar },
	props: {
		order: { type: Object, required: true }
	},
	computed: {
		footerActions() {
			const ma = this.order.mobile_actions;
			return (ma && Array.isArray(ma.actions)) ? ma.actions : [];
		},
		/** 根据 plugin / type 给商品名前挂一个分类小标签 */
		categoryTag() {
			const key = this.order.plugin || this.order.type || '';
			const tag = CATEGORY_TAG_MAP[key] || null;
			// 预约的叫法随行业模板变(挂号 / 预约),标签跟着词库走
			if (key === 'booking' && tag) return { label: this.$noun('booking.book') === 'booking.book' ? tag.label : this.$noun('booking.book'), color: tag.color };
			return tag;
		}
	},
	methods: {
		forwardAction(e) {
			this.$emit('action', e);
		}
	}
};
</script>

<style scoped lang="scss">
.order-list-item {
	background: #fff;
	border-radius: 20rpx;
	margin-bottom: 20rpx;  /* 父容器 padding 控制左右间距 */
	overflow: hidden;
	box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.03);
	/* 不做入场动画:原来每张卡按序号晚 0.05s 淡入上移,订单一张张冒出来像在加载 */
	transition: transform 0.2s;

	&:active { transform: scale(0.995); }
}

.refund-line {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 0 24rpx 16rpx;

	.refund-tag {
		font-size: 22rpx;
		line-height: 1.6;
		padding: 0 12rpx;
		border-radius: 6rpx;
	}
	/* 退款状态:语义色,不跟商城主题色(与订单详情「退款进度」卡一致) */
	.state-processing { color: #1677FF; background: #EAF2FF; }
	.state-delayed { color: #D48806; background: #FFF7E6; }
	.state-done { color: #4A7C59; background: #EDF5EF; }
	.state-offline { color: #666; background: #F2F2F2; }

	.refund-more {
		font-size: 22rpx;
		color: #999;
	}
}
</style>
