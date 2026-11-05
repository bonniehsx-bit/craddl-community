<template>
	<view>
		<view class="footer-placeholder"></view>
		<view class="footer-bar">
			<!-- 左：客服 + 电话
			     · 商家订单 (order.mch 存在): 客服路由到商家客服池 + 电话拨商家
			     · 平台订单 (mch_id=0): 客服回退到平台客服池（CustomerServiceMatcher 已自动处理），电话隐藏
			-->
			<view class="footer-left">
				<tui-cs-wrapper
					:goods="firstGoodsForCs"
					:currentUrl="currentOrderUrl"
					class="left-action">
					<view style="display:flex;flex-direction:column;align-items:center;">
						<tui-icon name="kefu" :size="20" color="#666"></tui-icon>
						<text>客服</text>
					</view>
				</tui-cs-wrapper>
				<view class="left-action" v-if="hasMerchantPhone" @tap="callShop">
					<tui-icon name="voipphone" :size="20" color="#666"></tui-icon>
					<text>电话</text>
				</view>
			</view>

			<!-- 右：后端下发的 actions（无则走默认 fallback）-->
			<view class="footer-right">
				<block v-if="actions && actions.length">
					<button
						v-for="(btn, idx) in actions"
						:key="idx"
						class="btn"
						:class="btn.style === 'primary' ? 'primary' : 'plain'"
						:style="btn.style === 'primary' ? primaryStyle : ''"
						@tap="onActionClick(btn)">
						{{ btn.label }}
					</button>
				</block>
			</view>
		</view>
	</view>
</template>

<script>
import { resolveActionData, hexToRgba } from './utils.js';
import TuiCsWrapper from '@/components/thorui/tui-cs-wrapper/tui-cs-wrapper.vue';

export default {
	name: 'OrderFooterBar',
	components: { TuiCsWrapper },
	props: {
		order: { type: Object, required: true },
		list: { type: Array, default: () => [] },
		/** 后端 mobile_actions.actions 数组；为空时不渲染右侧按钮区 */
		actions: { type: Array, default: () => [] },
		// 页面透传的主题色：全局 mixin 的 themeColor 在本层嵌套组件取不到，
		// 由页面 → Shell → 此处显式传入，确保 primary 按钮底色正确
		pageThemeColor: { type: String, default: '' },
	},
	computed: {
		firstGoodsForCs() {
			const first = (this.list && this.list[0]) ? this.list[0] : null;
			if (!first || !first.goods) return null;
			// 平台订单 mch_id=0 会让 CustomerServiceMatcher 自动回退到平台客服池
			return {
				...first.goods,
				mch_id: (this.order && this.order.mch && this.order.mch.id) || first.goods.mch_id || 0
			};
		},
		hasMerchantPhone() {
			const mch = this.order && this.order.mch;
			return !!(mch && (mch.mobile || mch.tel));
		},
		currentOrderUrl() {
			return this.order && this.order.id
				? `/others/order-detail/order-detail?order_id=${this.order.id}`
				: '';
		},
		primaryStyle() {
			// 优先用页面透传的 pageThemeColor（全局 mixin 的 themeColor 在本层取不到）；
			// 再退本地缓存（key 是 theme_color，见 store/modules/mall.js）。
			const color = this.pageThemeColor
				|| this.themeColor
				|| uni.getStorageSync('theme_color')
				|| '';
			// 取不到主题色 → 返回空，走 .primary 的白底黑字兜底样式
			if (!color) return '';
			return {
				background: color,
				color: '#fff',
				boxShadow: `0 8rpx 20rpx -4rpx ${hexToRgba(color, 0.45)}`
			};
		}
	},
	methods: {
		onActionClick(btn) {
			const data = resolveActionData(btn, { order: this.order, list: this.list });
			// 附带原始 action 对象（rawAction）给父组件，便于读取 btn 上的额外字段（如 party_id）
			this.$emit('action', { type: btn.type, data, rawAction: btn });
		},
		callShop() {
			const phone = (this.order.mch && (this.order.mch.mobile || this.order.mch.tel));
			if (phone) uni.makePhoneCall({ phoneNumber: phone });
			else uni.showToast({ title: '暂无联系方式', icon: 'none' });
		}
	}
};
</script>

<style scoped lang="scss">
/* 高度和 tui-tabbar / 商品详情 / 购物车底栏一致:固定 120rpx,不再额外加底部安全区(用户认定的高度,见 5fa9a32cf2)。
   原来是上下各 16rpx + 安全区,iPhone 全面屏上底栏比别的页面高出一截。 */
.footer-placeholder {
	height: 120rpx;
}
.footer-bar {
	position: fixed;
	box-sizing: border-box;
	bottom: 0;
	left: 0;
	width: 100%;
	background: #fff;
	box-shadow: 0 -4rpx 20rpx rgba(0, 0, 0, 0.04);
	z-index: 99;
	height: 120rpx;
	padding: 0 30rpx;
	display: flex;
	justify-content: space-between;
	align-items: center;
}
.footer-left {
	display: flex;
	gap: 16rpx;

	.left-action {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		font-size: 22rpx;
		color: #666;
		min-width: 80rpx;

		&:active { opacity: 0.7; }
	}
}
.footer-right {
	display: flex;
	gap: 20rpx;
	align-items: center;

	.btn {
		margin: 0;
		padding: 0 36rpx;
		height: 64rpx;
		line-height: 64rpx;
		font-size: 26rpx;
		border-radius: 36rpx;
		font-weight: 500;

		&::after { border: none; }
		&:active { transform: scale(0.96); opacity: 0.9; }

		&.plain {
			background: #fff;
			border: 1rpx solid #dcdfe6;
			color: #606266;
		}
		&.primary {
			/* 兜底样式：取不到主题色时为白底黑字；
			   有主题色时由内联 primaryStyle 覆盖为「主题色底 + 白字」 */
			background: #fff;
			color: #333;
			border: 1rpx solid #dcdfe6;
		}
	}
}
</style>
