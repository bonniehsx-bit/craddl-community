<template>
	<tui-page :backgroundColor="'#F7F8FA'">
		<view class="container">

			<!-- 顶部透明压在横幅上;滚过横幅(或没有订单可显示)时变成白底、深色返回键,标题居中 -->
			<view class="custom-navbar" :class="{ 'is-solid': navIsSolid }" :style="{ paddingTop: navBar.top + 'px', height: navBar.height + 'px' }">
				<view class="nav-btn glass-effect"
					:style="{ height: navBar.height + 'px', width: navBar.height + 'px' }" @tap="handleNavBack">
					<view class="icon-back" v-if="hasHistory"></view>
					<view class="icon-home" v-else>
						<view class="roof"></view>
						<view class="body"></view>
					</view>
				</view>
				<view class="nav-title" v-if="!navIsSolid" :style="{ lineHeight: navBar.height + 'px' }">订单详情</view>
				<view class="nav-center" v-else :style="'top:' + navBar.top + 'px;height:' + navBar.height + 'px;line-height:' + navBar.height + 'px'">订单详情</view>
				<!-- #ifndef MP -->
				<view class="nav-status" v-if="navSolid && order && navStatusText" :style="'top:' + navBar.top + 'px;height:' + navBar.height + 'px'">
					<text class="nav-status-pill" :style="navStatusStyle">{{ navStatusText }}</text>
				</view>
				<!-- #endif -->
			</view>

			<view class="content-container">
				<block v-if="order">
					<!-- ── 通用 Shell（Panel-Driven）
					     目前所有订单类型都走这里。如需 L3 整页接管，要在 template 里显式加
					     v-if="overrideTemplate === 'XxxName'" 的分支（微信小程序 WXML 不支持动态组件 <component :is>）
					     themeColor 由全局 mixin 自动注入各子组件 ── -->
					<OrderDetailShell
						:order="order"
						:list="list"
						:express-list="express_list"
						:plugin-data="plugin_data"
						:mobile-actions="mobile_actions"
						:nav-height="totalNavHeight"
						:page-theme-color="themeColor"
						@action="handleAction">
					</OrderDetailShell>
				</block>
				<!-- 订单打不开(不是当前账号的、已删除):给说法和出口,不再整页空白 -->
				<view v-else-if="loadError" class="load-error" :style="'padding-top:' + (totalNavHeight + 110) + 'px'">
					<view class="load-error-icon" :style="'background:' + themeSoftBg">
						<tui-icon name="order" :size="40" :color="themeColor"></tui-icon>
					</view>
					<text class="load-error-title">找不到这笔订单</text>
					<text class="load-error-desc">这笔订单可能不属于当前登录的账号，或者已经被删除。换个账号登录，或回到订单列表看看。</text>
					<view class="load-error-btns">
						<button class="le-btn" @tap="goHome">返回首页</button>
						<button class="le-btn primary" :style="'background:' + themeColor + ';border-color:' + themeColor" @tap="goOrderList">查看我的订单</button>
					</view>
				</view>
			</view>

			<tui-bottom-popup :show="showExpressModal" @close="showExpressModal = false" :radius="true">
				<view class="popup-container">
					<view class="popup-header">
						<view class="title-box">
							<text class="title">物流详情</text>
							<text class="subtitle" v-if="express_detail">{{ express_detail.express_name || '查询中'
							}}</text>
						</view>
						<view class="btn-close" @click="showExpressModal = false">
							<tui-icon name="close-fill" color="#CFD3DC" :size="24"></tui-icon>
						</view>
					</view>

					<scroll-view scroll-y class="popup-scroll">
						<view class="timeline-wrapper"
							v-if="express_detail && express_detail.list && express_detail.list.length > 0">
							<custom-timeline :items="express_detail.list" :themeColor="themeColor"></custom-timeline>
						</view>
						<view class="empty-state" v-else>
							<text class="empty-text">暂无物流轨迹信息</text>
						</view>
					</scroll-view>
				</view>
			</tui-bottom-popup>

			<!-- 支付弹窗(全局组件):详情页直接付款,付完原地刷新 -->
			<jx-pay-sheet ref="paySheet"></jx-pay-sheet>
		</view>
	</tui-page>
</template>

<script>
import { mapState } from 'vuex';
import OrderDetailShell from './shell/OrderDetailShell.vue';
import { fallbackStatusInfo, hexToRgba } from './shell/utils.js';
// ── L3 逃生舱口：保留现有 OrderDetailXxx.vue，逐步 Panel 化后可下线 ──
// (Normal 已迁移到 Shell，原 OrderDetailNormal.vue 已删除)
import CustomTimeline from '@/components/CustomTimeline/CustomTimeline.vue';

export default {
	components: {
		OrderDetailShell,
		CustomTimeline
	},
	data() {
		return {
			order_id: null,
			order: null,
			red_packet_info: null,
			list: [],
			express_list: [],
			express_detail: null,
			showExpressModal: false,

			// 后端下发的 2 种动态数据
			plugin_data: null,        // Panel-Driven 动态面板
			mobile_actions: null,     // 底部按钮 + 状态文案
			// 说明：override_template（L3 整页接管）字段保留在 response 里兼容未来扩展，
			// 当前所有订单类型都走 Shell，无需前端维护分发表。

			hasHistory: false,
			// 滚过横幅后导航栏变白底(见 onPageScroll)
			navSolid: false,
			// 订单打不开时的提示(接口报错),有值就显示空状态
			loadError: '',
			navBar: {
				top: 20,
				height: 32
			}
		}
	},
	computed: {
		...mapState('mall', {
			themeColor: state => state.themeColor || '#E63D3D'
		}),
		// 核心逻辑：计算导航栏总高度，传给子组件用于偏移状态栏文字
		totalNavHeight() {
			return this.navBar.top + this.navBar.height;
		},
		navIsSolid() {
			return this.navSolid || !this.order;
		},
		navStatusText() {
			if (!this.order) return '';
			if (this.mobile_actions && this.mobile_actions.status_text) return this.mobile_actions.status_text;
			return fallbackStatusInfo(this.order).text;
		},
		navStatusStyle() {
			const closed = this.order && Number(this.order.status) === 0;
			const c = closed ? '#4B5160' : this.themeColor;
			return 'color:' + c + ';background:' + hexToRgba(c, 0.1);
		},
		themeSoftBg() {
			return hexToRgba(this.themeColor, 0.1);
		}
	},
	onLoad(options) {
		this.initNavBar();
		if (!options.order_id) {
			this.$util.toast('缺少订单ID');
			setTimeout(() => uni.navigateBack(), 1500);
			return;
		}
		this.order_id = options.order_id;
	},
	onShow() {
		const pages = getCurrentPages();
		this.hasHistory = pages.length > 1;
		this.getDetail();
	},
	onHide() {
		this._stopPickupPoll();
	},
	// 横幅大约 120px 高:滚过一半就换成白底导航,免得白字标题压在白卡片上看不见
	onPageScroll(e) {
		const solid = e.scrollTop > 60;
		if (solid !== this.navSolid) this.navSolid = solid;
	},
	onUnload() {
		this._stopPickupPoll();
	},
	methods: {
		initNavBar() {
			const sysInfo = uni.getSystemInfoSync();
			// #ifdef MP
			const menuBtnInfo = uni.getMenuButtonBoundingClientRect();
			this.navBar = {
				top: menuBtnInfo.top,
				height: menuBtnInfo.height
			};
			// #endif
			// #ifndef MP
			this.navBar = {
				top: sysInfo.statusBarHeight || 20,
				height: 44
			};
			// #endif
		},
		handleNavBack() {
			if (this.hasHistory) {
				uni.navigateBack({ delta: 1 });
			} else {
				uni.switchTab({
					url: '/pages/index/index',
					fail: () => uni.reLaunch({ url: '/pages/index/index' })
				});
			}
		},
		async getDetail() {
			uni.showLoading({ title: '加载中...', mask: true });
			try {
				const res = await this.$request({
					url: '/api/order/detail',
					data: { order_id: this.order_id }
				});
				if (res.code === 0) {
					this.loadError = '';
					const prevStatus = this.order ? this.order.status : null;
					this.order = res.data.order;
					this.list = res.data.list;
					this.express_list = res.data.express_list;
					this.red_packet_info = res.data.red_packet_info;

					// 后端新增的 3 种动态数据
					this.plugin_data = res.data.plugin_data || null;
					this.mobile_actions = res.data.mobile_actions || null;
					// res.data.override_template 保留兼容字段但前端不处理

					// 核销轮询：展示核销码时自动开始，核销完成后自动停止
					this._checkPickupPoll(prevStatus);
				} else if (!this.order) {
					// 第一次就拿不到(不是本人的单、已删除):整页给空状态
					this.loadError = res.msg || '找不到这笔订单';
				} else {
					this.$util.toast(res.msg);
				}
			} finally {
				uni.hideLoading();
			}
		},
		goHome() {
			uni.switchTab({
				url: '/pages/index/index',
				fail: () => uni.reLaunch({ url: '/pages/index/index' })
			});
		},
		goOrderList() {
			uni.redirectTo({ url: '/others/order/order' });
		},

		/** 检查是否需要启动/停止核销轮询 */
		_checkPickupPoll() {
			if (!this.order) return;
			const needPoll = this.order.deliver_type === 'pickup' && this.order.status === 20;

			if (needPoll && !this._pickupTimer) {
				this._pickupTimer = setInterval(() => this._pollPickupStatus(), 5000);
			} else if (!needPoll) {
				this._stopPickupPoll();
			}
		},

		/** 轻量轮询：只查核销状态，不拉整个订单 */
		async _pollPickupStatus() {
			const res = await this.$request({
				url: '/api/order/pickup-status',
				data: { order_id: this.order_id }
			}).catch(() => null);

			if (!res || res.code !== 0) return;

			if (res.data.verified) {
				// 核销完成 → 停止轮询，重新拉一次完整订单刷新 UI
				this._stopPickupPoll();
				uni.showToast({ title: '已核销成功', icon: 'success' });
				setTimeout(() => this.getDetail(), 500);
			}
		},

		_stopPickupPoll() {
			if (this._pickupTimer) {
				clearInterval(this._pickupTimer);
				this._pickupTimer = null;
			}
		},
		handleAction(e) {
			const { type, data, rawAction } = e;
			switch (type) {
				// 插件面板发现状态变了要求整页刷新 —— 比如点单的「出餐即完成」
				// 会连交易状态一起改,只更新面板的话底部按钮还是旧的
				case 'refresh':
					this.getDetail();
					break;
				case 'pay':
					this.btnPay(data);
					break;
				case 'cancel':
					this.orderCancel(data, rawAction);
					break;
				case 'confirm':
					this.confirm(data);
					break;
				case 'express':
					this.getExpress(data);
					break;
				case 'goodsDetail':
					// 只有商城订单(type=normal)的商品行可点,拦在 OrderGoodsCard.canOpenGoods。
					// 所以走到这里的一定是普通商城商品,不必再按 plugin 分流。
					this.$util.go('/others/goods/goods?id=' + data.goods.id);
					break;
				case 'applyRefund':
					this.refund(data);
					break;
				case 'refundDetail':
					this.$util.go(`/others/refund-detail/refund-detail?od_id=${data.id}`);
					break;
				case 'comment':
					if (data.plugin === 'massage') {
						this.$util.go(`/plugins2/massage/review?order_id=${data.id}`);
					} else {
						this.$util.go(`/others/comment/comment?order_id=${data.id}`);
					}
					break;
				case 'mchDetail':
					this.$util.go('/plugins1/mch/index?id=' + data.id);
					break;
				case 'rebuy': {
					// 再次购买：跳订单里第一个商品的详情页
					const first = (this.list && this.list[0]) ? this.list[0] : null;
					if (first && first.goods && first.goods.id) {
						this.$util.go('/others/goods/goods?id=' + first.goods.id);
					} else {
						this.$util.toast('找不到商品');
					}
					break;
				}
				case 'rebook': {
					// massage 订单：携带 shop_id / service_item_id / 服务详情参数直跳 order-create
					if (this.order && this.order.plugin === 'massage' && rawAction && rawAction.goods_id) {
						const params = [
							`service_id=${rawAction.service_item_id || 0}`,
							`goods_id=${rawAction.goods_id || 0}`,
							`service_name=${encodeURIComponent(rawAction.service_name || '')}`,
							`price=${rawAction.service_price || 0}`,
							`duration=${rawAction.service_duration || 0}`,
							`deposit_rate=${rawAction.service_deposit_rate || 1}`,
						].join('&');
						this.$util.go('/plugins2/massage/order-create?' + params);
						break;
					}
					// 其他场景（酒店等）：goodsDetail 别名，指向首个商品
					const first = (this.list && this.list[0]) ? this.list[0] : null;
					if (first && first.goods && first.goods.id) {
						this.$util.go('/others/goods/goods?id=' + first.goods.id);
					} else {
						this.$util.toast('找不到房型');
					}
					break;
				}
				case 'enterPartyRoom': {
					// Yuda 拼单房间跳转。后端 action 对象带了 party_id
					// 从 plugin_data.display_panels 里找 YudaPartyPanel 的 party_id 作为兜底
					let partyId = (e && e.rawAction && e.rawAction.party_id) || null;
					if (!partyId && this.plugin_data && Array.isArray(this.plugin_data.display_panels)) {
						const yp = this.plugin_data.display_panels.find(p => p.component === 'YudaPartyPanel');
						if (yp && yp.data) partyId = yp.data.party_id;
					}
					if (partyId) {
						uni.redirectTo({ url: `/plugins3/yuda/detail?id=${partyId}` });
					} else {
						uni.redirectTo({ url: '/plugins3/yuda/index' });
					}
					break;
				}
				case 'viewLetter':
					// 有声来信:去「我的来信」看交付链接
					this.$util.go('/plugins3/aidb/letter/mine');
					break;
				case 'viewResult': {
					// Emotion 查看测算结果
					const oid = (data && data.id) || this.order_id;
					this.$util.go(`/plugins/emotion/result?order_id=${oid}`);
					break;
				}
				case 'activate':
					this.activateVoucher(data);
					break;
				case 'viewVoucherBenefit': {
					const oid = (data && data.id) || this.order_id;
					this.$util.go(`/plugins/voucher/voucher?order_id=${oid}`);
					break;
				}
				case 'luckyRecord': {
					// Lucky 查看战绩 —— 跳到该订单的抽奖战绩页
					const oid = (data && data.id) || this.order_id;
					this.$util.go(`/plugins3/lucky/record?order_id=${oid}`);
					break;
				}
				case 'status':
					// 纯展示状态占位（中间过渡态：技师确认中/服务中/申诉中等），点击无动作
					break;
				case 'dispute': {
					// 艾灸：技师已确认/服务中/已完成 用户都可发起申诉，跳申诉页。
					// fromStatus = booking.status（艾灸独立状态机 0~8），由后端 getMobileActions
					// 在 rawAction 里直接带过来，避免前端到处取
					const bookingId  = (e && e.rawAction && e.rawAction.booking_id) || 0;
					const fromStatus = (e && e.rawAction && e.rawAction.booking_status) || 0;
					if (!bookingId) {
						this.$util.toast('订单异常，请联系客服');
						break;
					}
					this.$util.go('/plugins2/massage/dispute?order_id=' + bookingId + '&status=' + fromStatus);
					break;
				}
				case 'disputeDetail':
					// 申诉已处理：跳"我的申诉"列表查看裁决结果
					this.$util.go('/plugins2/massage/dispute-list');
					break;
				default:
					// 未识别的 action 只做日志，不阻断；方便排查后端新增按钮时前端漏接
					console.warn('[order-detail] unhandled action type:', type, data);
			}
		},
		async getExpress(express_id = null) {
			uni.showLoading({ title: '查询中...' });
			try {
				let params = { order_id: this.order_id };
				if (express_id) {
					params.express_id = express_id;
				}
				const res = await this.$request({
					url: "/api/order/express",
					data: params
				});
				if (res.code === 0) {
					this.express_detail = res.data.express_detail;
					this.showExpressModal = true;
				}
			} finally {
				uni.hideLoading();
			}
		},
		// 去付款:原地弹出支付弹窗,付完 / 关掉 / 超时都重新拉一次详情,状态以服务器为准
		btnPay(order) {
			this.$refs.paySheet.open({ orderNo: order.order_no }).then(r => {
				if (r.status === 'paid') uni.showToast({ title: '付款成功', icon: 'success' });
				this.getDetail();
			});
		},
		// rawAction 来自 mobile_actions.actions[i]，可携带插件自定义 endpoint：
		//   cancel_url     —— 自定义取消接口（例如 /plugin/massage/api/order/cancel）
		//   cancel_payload —— 自定义请求体（例如 { order_id: booking.id, ... }）
		// 主商城订单 rawAction 不带这些字段，沿用默认 /api/order/cancel
		orderCancel(order, rawAction) {
			// 艾灸订单已支付时给出退款时间预期
			const target = order || this.order;
			const isMassagePaid = target && target.plugin === 'massage' && Number(target.pay_status) === 1;
			const content = isMassagePaid
				? '取消后将原路退回支付金额，预计 1-3 个工作日到账。确定要取消？'
				: '确定要取消该订单吗？';
			uni.showModal({
				title: '取消确认',
				content,
				confirmColor: this.themeColor,
				success: (res) => {
					if (!res.confirm) return;
					// 插件自定义取消接口（避免被主商城 OrderForm.cancel 拦截）
					if (rawAction && rawAction.cancel_url) {
						this.callPluginCancel(rawAction);
						return;
					}
					this.apiAction('/api/order/cancel', '订单已取消');
				}
			});
		},

		// 调用插件自定义取消接口（mobile_action.cancel_url 触发）
		async callPluginCancel(action) {
			uni.showLoading({ title: '处理中...', mask: true });
			try {
				const res = await this.$request({
					url: action.cancel_url,
					method: 'post',
					data: action.cancel_payload || {},
				});
				if (res.code === 0) {
					this.$util.toast(res.msg || '订单已取消');
					setTimeout(() => this.getDetail(), 800);
				} else {
					this.$util.toast(res.msg || '操作失败');
				}
			} catch (e) {
				this.$util.toast('网络异常');
			} finally {
				uni.hideLoading();
			}
		},
		confirm(order) {
			uni.showModal({
				title: '确认收货',
				content: '请收到货确认无误后再确认收货。',
				confirmColor: this.themeColor,
				success: (res) => {
					if (res.confirm) this.apiAction('/api/order/confirm', '已确认收货');
				}
			});
		},
		refund(item) {
			console.log(item);
			uni.navigateTo({ url: `/others/refund/refund?od_id=${item.id}&order_id=${this.order_id}` });
		},
		/** Voucher 激活：独立 async 方法，避开 handleAction 的同步上下文 */
		async activateVoucher(data) {
			uni.showLoading({ title: '激活中...' });
			try {
				const res = await this.$request({
					url: '/plugin/voucher/api/order/activate',
					method: 'POST',
					data: { order_id: (data && data.id) || this.order_id }
				});
				if (res.code === 0) {
					this.$util.toast('激活成功');
					setTimeout(() => this.getDetail(), 500);
				} else {
					this.$util.toast(res.msg || '激活失败');
				}
			} finally {
				uni.hideLoading();
			}
		},
		async apiAction(url, successMsg) {
			uni.showLoading({ title: '处理中...', mask: true });
			try {
				const res = await this.$request({
					url,
					method: 'post',
					data: { order_id: this.order_id }
				});
				if (res.code === 0) {
					this.$util.toast(successMsg);
					setTimeout(() => this.getDetail(), 1000);
				} else {
					this.$util.toast(res.msg);
				}
			} finally {
				uni.hideLoading();
			}
		}
	}
}
</script>

<style lang="scss" scoped>
.container {
	min-height: 100vh;
	position: relative;
}

/* 自定义导航栏 - 优化白色底风格下的可见度 */
.custom-navbar {
	position: fixed;
	left: 0;
	top: 0;
	width: 100%;
	z-index: 100;
	padding-left: 30rpx;
	display: flex;
	align-items: center;
	box-sizing: content-box;
	pointer-events: none;

	.nav-title {
		color: #fff;
		font-size: 34rpx;
		font-weight: 600;
		margin-left: 20rpx;
		text-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.2);
		pointer-events: auto;
	}

	.nav-btn {
		pointer-events: auto;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;

		/* 毛玻璃效果在亮色背景下更精致 */
		&.glass-effect {
			background: rgba(0, 0, 0, 0.12);
			backdrop-filter: blur(12px);
			border: 1rpx solid rgba(255, 255, 255, 0.25);
		}

		&:active {
			transform: scale(0.92);
			background: rgba(0, 0, 0, 0.2);
		}
	}

	/* CSS 图标 */
	.icon-back {
		width: 18rpx;
		height: 18rpx;
		border-left: 4rpx solid #fff;
		border-bottom: 4rpx solid #fff;
		transform: rotate(45deg);
		margin-left: 6rpx;
	}

	.icon-home {
		display: flex;
		flex-direction: column;
		align-items: center;
		margin-bottom: 2rpx;

		.roof {
			width: 0;
			height: 0;
			border-left: 14rpx solid transparent;
			border-right: 14rpx solid transparent;
			border-bottom: 12rpx solid #fff;
			margin-bottom: -2rpx;
		}

		.body {
			width: 18rpx;
			height: 14rpx;
			background: #fff;
			position: relative;

			&::after {
				content: '';
				width: 8rpx;
				height: 10rpx;
				background: rgba(0, 0, 0, 0.2);
				position: absolute;
				bottom: 0;
				left: 50%;
				transform: translateX(-50%);
				border-top-left-radius: 4rpx;
				border-top-right-radius: 4rpx;
			}
		}
	}
}

/* 滚过横幅后:白底、深色返回键和标题 */
.custom-navbar {
	/* 原来 width:100% 加左内边距会宽出屏幕,右侧状态标签被切;改成左右贴边自动算宽 */
	width: auto;
	right: 0;
	transition: background-color 0.2s;

	&.is-solid {
		background: #fff;
		box-shadow: 0 1rpx 0 #ECEEF1;
		pointer-events: auto;

		.nav-btn.glass-effect {
			background: transparent;
			border-color: transparent;
			backdrop-filter: none;
		}
		.icon-back { border-color: #1C1F24; }
		.icon-home .roof { border-bottom-color: #1C1F24; }
		.icon-home .body { background: #1C1F24; }
	}

	.nav-center {
		position: absolute;
		left: 0;
		right: 0;
		text-align: center;
		font-size: 34rpx;
		font-weight: 600;
		color: #1C1F24;
		pointer-events: none;
	}
	.nav-status {
		position: absolute;
		right: 30rpx;
		display: flex;
		align-items: center;
	}
	.nav-status-pill {
		font-size: 24rpx;
		font-weight: 600;
		line-height: 48rpx;
		padding: 0 20rpx;
		border-radius: 24rpx;
	}
}

/* 订单打不开 */
.load-error {
	display: flex;
	flex-direction: column;
	align-items: center;
	padding-left: 80rpx;
	padding-right: 80rpx;
	text-align: center;

	.load-error-icon {
		width: 192rpx;
		height: 192rpx;
		border-radius: 96rpx;
		display: flex;
		align-items: center;
		justify-content: center;
	}
	.load-error-title {
		margin-top: 40rpx;
		font-size: 36rpx;
		font-weight: 600;
		color: #1C1F24;
	}
	.load-error-desc {
		margin-top: 20rpx;
		font-size: 28rpx;
		line-height: 1.6;
		color: #6B7079;
	}
	.load-error-btns {
		margin-top: 56rpx;
		display: flex;
		gap: 24rpx;
	}
	.le-btn {
		margin: 0;
		height: 80rpx;
		line-height: 78rpx;
		padding: 0 44rpx;
		border-radius: 40rpx;
		font-size: 28rpx;
		background: #fff;
		color: #1C1F24;
		border: 1rpx solid #D5D8DD;

		&::after { border: none; }
		&.primary { color: #fff; }
	}
}

/* 内容容器 */
.content-container {
	width: 100%;
}

/* 弹窗样式 */
.popup-container {
	background: #fff;
	display: flex;
	flex-direction: column;
	max-height: 80vh;
}

.popup-header {
	padding: 30rpx;
	display: flex;
	align-items: center;
	justify-content: space-between;
	border-bottom: 1rpx solid #F5F6F7;

	.title-box {
		display: flex;
		flex-direction: column;

		.title {
			font-size: 32rpx;
			font-weight: bold;
			color: #333;
		}

		.subtitle {
			font-size: 24rpx;
			color: #999;
			margin-top: 4rpx;
		}
	}
}

.popup-scroll {
	height: 60vh;
	background-color: #fff;
}

.timeline-wrapper {
	padding: 30rpx 20rpx;
}

.empty-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	padding-top: 100rpx;

	.empty-text {
		color: #909399;
		font-size: 28rpx;
	}
}
</style>