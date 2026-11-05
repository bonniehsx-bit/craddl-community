<template>
	<view class="order-detail-shell">
		<!-- ① 顶部状态 banner（所有订单共用）-->
		<OrderStatusBanner :order="order" :mobile-actions="mobileActions" :nav-height="navHeight">
		</OrderStatusBanner>

		<view class="shell-body">
			<!-- ①½ 退款进度(有退款才显示,所有订单共用) -->
			<OrderRefundCard :order="order"></OrderRefundCard>

			<!-- ② 通用核销/自提码卡 -->
			<OrderPickupCodeCard :order="order"></OrderPickupCodeCard>

			<!-- ③ 物流 / 同城配送卡 -->
			<OrderLogisticsCard :order="order" :express-list="expressList" @action="forwardAction">
			</OrderLogisticsCard>

			<!-- ④ 收货/自提地址卡 -->
			<OrderAddressCard :order="order"></OrderAddressCard>

			<!-- ⑤ 动态面板区 - 上半区（priority < 50）
			     微信小程序不支持 <component :is="...">，用显式 v-if 分支列出 -->
			<block v-for="(panel, idx) in panelsBeforeGoods">
				<HotelStayPanel v-if="panel.component === 'HotelStayPanel'" :data="panel.data" :order="order"
					:list="list" class="shell-panel" @action="forwardAction">
				</HotelStayPanel>
				<YudaPartyPanel v-else-if="panel.component === 'YudaPartyPanel'" :data="panel.data" :order="order"
					:list="list" class="shell-panel" @action="forwardAction">
				</YudaPartyPanel>
				<MassageBookingPanel v-else-if="panel.component === 'MassageBookingPanel'" :data="panel.data"
					:order="order" :list="list" class="shell-panel" @action="forwardAction">
				</MassageBookingPanel>
				<DiningOrderPanel v-else-if="panel.component === 'DiningOrderPanel'" :data="panel.data"
					:order="order" :list="list" class="shell-panel" @action="forwardAction">
				</DiningOrderPanel>
				<SeckillInfoPanel v-else-if="panel.component === 'SeckillInfoPanel'" :data="panel.data"
					:order="order" :list="list" class="shell-panel" @action="forwardAction">
				</SeckillInfoPanel>
				<BookingPanel v-else-if="panel.component === 'BookingPanel'" :data="panel.data"
					:order="order" :list="list" class="shell-panel" @action="forwardAction">
				</BookingPanel>
				<AidbLetterPanel v-else-if="panel.component === 'AidbLetterPanel'" :data="panel.data"
					:order="order" :list="list" class="shell-panel" @action="forwardAction">
				</AidbLetterPanel>
			</block>

			<!-- ⑥ 商品列表 -->
			<OrderGoodsCard :order="order" :list="list" @action="forwardAction">
			</OrderGoodsCard>

			<!-- ⑦ 动态面板区 - 下半区（priority ≥ 50）-->
			<block v-for="(panel, idx) in panelsAfterGoods">
				<GroupPackagePanel v-if="panel.component === 'GroupPackagePanel'" :data="panel.data" :order="order"
					:list="list" class="shell-panel" @action="forwardAction">
				</GroupPackagePanel>
			</block>

			<!-- ⑧ 价格明细 -->
			<OrderPriceCard :order="order"></OrderPriceCard>

			<!-- ⑨ 订单信息 -->
			<OrderInfoCard :order="order"></OrderInfoCard>
		</view>

		<!-- ⑩ 底部栏：客服 + 电话 + 动态按钮 -->
		<OrderFooterBar :order="order" :list="list" :actions="footerActions"
			:page-theme-color="pageThemeColor" @action="forwardAction">
		</OrderFooterBar>
	</view>
</template>

<script>
	import OrderStatusBanner from './OrderStatusBanner.vue';
	import OrderRefundCard from './OrderRefundCard.vue';
	import OrderPickupCodeCard from './OrderPickupCodeCard.vue';
	import OrderLogisticsCard from './OrderLogisticsCard.vue';
	import OrderAddressCard from './OrderAddressCard.vue';
	import OrderGoodsCard from './OrderGoodsCard.vue';
	import OrderPriceCard from './OrderPriceCard.vue';
	import OrderInfoCard from './OrderInfoCard.vue';
	import OrderFooterBar from './OrderFooterBar.vue';

	// 插件 Panel：静态 import + 显式 v-if 分支（微信小程序 WXML 限制，不支持 <component :is>）
	import HotelStayPanel from '../panels/HotelStayPanel.vue';
	import GroupPackagePanel from '../panels/GroupPackagePanel.vue';
	import YudaPartyPanel from '../panels/YudaPartyPanel.vue';
	import MassageBookingPanel from '../panels/MassageBookingPanel.vue';
	import DiningOrderPanel from '../panels/DiningOrderPanel.vue';
	import SeckillInfoPanel from '../panels/SeckillInfoPanel.vue';
	import BookingPanel from '../panels/BookingPanel.vue';
	import AidbLetterPanel from '../panels/AidbLetterPanel.vue';

	// 已注册的 panel component 白名单（前端过滤时用）
	const KNOWN_PANELS = ['HotelStayPanel', 'GroupPackagePanel', 'YudaPartyPanel', 'MassageBookingPanel', 'DiningOrderPanel', 'SeckillInfoPanel', 'BookingPanel', 'AidbLetterPanel'];

	export default {
		name: 'OrderDetailShell',
		components: {
			OrderStatusBanner,
			OrderRefundCard,
			OrderPickupCodeCard,
			OrderLogisticsCard,
			OrderAddressCard,
			OrderGoodsCard,
			OrderPriceCard,
			OrderInfoCard,
			OrderFooterBar,
			HotelStayPanel,
			GroupPackagePanel,
			YudaPartyPanel,
			MassageBookingPanel,
			DiningOrderPanel,
			SeckillInfoPanel,
			BookingPanel,
			AidbLetterPanel,
		},
		props: {
			order: {
				type: Object,
				required: true
			},
			list: {
				type: Array,
				default: () => []
			},
			expressList: {
				type: Array,
				default: () => []
			},
			pluginData: {
				type: Object,
				default: () => null
			},
			mobileActions: {
				type: Object,
				default: () => null
			},
			navHeight: {
				type: [Number, String],
				default: 64
			},
			// 页面级主题色：全局 mixin 在深层嵌套子组件里取不稳定，
			// 由页面（mixin 必然生效处）显式透传下来
			pageThemeColor: {
				type: String,
				default: ''
			}
		},
		computed: {
			sortedPanels() {
				const panels = (this.pluginData && Array.isArray(this.pluginData.display_panels)) ? [...this.pluginData
					.display_panels
				] : [];
				// 按 priority 升序（默认 50），只保留已注册的 Panel
				return panels
					.filter(p => p && p.component && KNOWN_PANELS.indexOf(p.component) !== -1)
					.sort((a, b) => (Number(a.priority || 50) - Number(b.priority || 50)));
			},
			panelsBeforeGoods() {
				return this.sortedPanels.filter(p => Number(p.priority || 50) < 50);
			},
			panelsAfterGoods() {
				return this.sortedPanels.filter(p => Number(p.priority || 50) >= 50);
			},
			footerActions() {
				// 优先使用后端提供的 actions（非空才直接返回）
				if (this.mobileActions && Array.isArray(this.mobileActions.actions) && this.mobileActions.actions.length >
					0) {
					return this.mobileActions.actions;
				}

				// Yuda 兜底：若 display_panels 中有活跃的拼局，合成"进入拼单房间"按钮
				// 防止 mobile_actions 因接口异常/历史数据缺失而返回空 actions，但拼局确实进行中
				if (this.order && this.order.plugin === 'yuda') {
					const yudaPanel = this.sortedPanels.find(function(p) {
						return p.component === 'YudaPartyPanel';
					});
					if (yudaPanel && yudaPanel.data) {
						// 10=大厅捞人 20=锁单中 30=待赴约 35=待B确认 40=核销完成/进行中
						var ACTIVE_STATUSES = [10, 20, 30, 35, 40];
						if (ACTIVE_STATUSES.indexOf(Number(yudaPanel.data.party_status)) !== -1) {
							return [{
								type: 'enterPartyRoom',
								label: '进入拼单房间',
								style: 'primary',
								visible: true,
								data_key: 'null',
								party_id: Number(yudaPanel.data.party_id)
							}];
						}
					}
				}

				return [];
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
	.order-detail-shell {
		min-height: 100vh;
		background: #F7F8FA;
	}

	.shell-body {
		position: relative;
		margin-top: -40rpx;
		padding-bottom: 40rpx;
	}

	.shell-panel {
		display: block;
	}
</style>