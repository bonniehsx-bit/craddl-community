<template>
	<tui-page>
		<view class="container" :style="themeStyle">

			<!-- 1. 顶部 Tabs —— 6 个 tab 均分屏宽（100/6 ≈ 16.666%），:size 调小字号
			     避免「待发货/使用」5 个汉字换行 -->
			<tui-tabs :tabs="tabs" :currentTab="currentTab" @change="change"
				:selectedColor="themeColor" :sliderBgColor="themeColor"
				:itemWidth="tabItemWidth" :size="26" :padding="0">
			</tui-tabs>

			<!-- 2. 订单列表容器
			     所有订单类型统一走 OrderListItem；插件差异（积分兑换/权益包/团购/拼局 tag）
			     由 OrderListItem 内部按 order.plugin/type 自动识别展示 -->
			<view class="tui-order-list" @tap.stop>
				<block v-if="list.length > 0">
					<OrderListItem
						v-for="(order, index) in list"
						:key="order.id"
						:order="order"
						@action="handleOrderAction">
					</OrderListItem>
				</block>

				<!-- 3. 加载状态 / 无数据 -->
				<tui-loadmore v-if="loading" :index="3" :color="themeColor"></tui-loadmore>
				<tui-nomore v-if="!pullUpOn && list.length > 0" text="没有更多订单了"></tui-nomore>

				<block v-if="list.length === 0 && !loading">
					<tui-no-data :fixed="false" btnText="去逛逛" :backgroundColor="themeColor" @click="goHome">
						您还没有相关订单哦~
					</tui-no-data>
				</block>
			</view>

			<!-- 4. 物流追踪底部弹出抽屉 -->
			<tui-bottom-popup :show="showExpressModal" @close="showExpressModal = false" :radius="true">
				<view class="popup-container">
					<view class="popup-header">
						<view class="title-box">
							<text class="title">物流详情</text>
							<text class="subtitle" v-if="express_detail">{{ express_detail.express_name || '查询中' }}</text>
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

			<!-- 支付弹窗(全局组件):列表里直接付款,不再跳收银台页 -->
			<jx-pay-sheet ref="paySheet"></jx-pay-sheet>
		</view>
	</tui-page>
</template>

<script>
import {
	mapState
} from 'vuex';
// 所有订单类型统一走通用卡
import OrderListItem from './shell/OrderListItem.vue';
import CustomTimeline from '@/components/CustomTimeline/CustomTimeline.vue';
	import TuiTabs from '@/others/components/tui-tabs/tui-tabs.vue'

// 列表只留订单卡(shell/ 下 5 个组件)和下面按钮动作要用的字段,其余不进 data:
// 接口给的是整行订单(100 多个字段),小程序会把 data 整个 setData 给视图层,10 单 34KB,裁完 7KB。
// ⚠ 订单卡或按钮动作要用新字段时,先加到这里,否则读到的是 undefined
function pickListOrder(o) {
	return {
		id: o.id,
		order_no: o.order_no,
		status: o.status,
		status_text: o.status_text,
		mobile_actions: o.mobile_actions,   // 状态文案 + 按钮(插件挂的取消接口、预约号等都在按钮上,整块留)
		refund_summary: o.refund_summary,
		type: o.type,
		plugin: o.plugin,
		pay_price: o.pay_price,
		pay_status: o.pay_status,
		deliver_type_text: o.deliver_type_text,
		remarks: o.remarks,                 // 情感测算看结果要从这里取 record_id
		mch: o.mch ? { id: o.mch.id, logo: o.mch.logo, mch_name: o.mch.mch_name } : null,
		// 小程序确认收货要拉起微信的确认收货组件,只用到这一项
		commonPayOrder: o.commonPayOrder ? { push_wechat_params: o.commonPayOrder.push_wechat_params } : null,
		items: (o.items || []).map(it => ({
			id: it.id,                      // 申请售后按明细号跳
			goods: it.goods ? { id: it.goods.id, name: it.goods.name, cover_pic: it.goods.cover_pic } : null,
			price: it.price,
			pay_price: it.pay_price,
			use_integral: it.use_integral,
			num: it.num,
			is_presell: it.is_presell,
			attr_list: it.attr_list,
			attr: it.attr,
		})),
	};
}

export default {
	components: {
			TuiTabs,
		OrderListItem,
		CustomTimeline
	},
	data() {
		return {
			tabs: [],
			initStatus: 0,
			currentTab: 0,
			page: 1,
			loading: false, // 正在加载
			pullUpOn: true, // 是否有更多
			isSubmitting: false, // 防止重复提交
			list: [],
			showExpressModal: false, // 是否显示物流弹窗
			express_detail: null // 选中的物流详情
		}
	},
	computed: {
		...mapState('mall', {
			themeColor: state => state.themeColor || '#D6201F'
		}),
		/* tab 等分宽度:按当前 tab 数量均分屏宽 */
		tabItemWidth() {
			return (100 / (this.tabs.length || 1)).toFixed(3) + '%';
		}
	},
	onLoad(options) {
		// 深链 ?status= 先记下,等 tab 配置拉到后再映射到对应下标
		this.initStatus = (options && options.status) ? (parseInt(options.status, 10) || 0) : 0;
		this.fetchTabs();
	},
	onShow() {
		// 每次显示刷新(如从详情页返回);首次由 fetchTabs 拉到配置后触发
		if (this.tabs.length) this.refreshList();
	},
	onPullDownRefresh() {
		this.refreshList();
	},
	onReachBottom() {
		this.getList();
	},
	methods: {
		// --- 1. 列表数据获取 ---
		change(e) {
			if (this.loading) return;
			this.currentTab = e.index;
			this.refreshList();
		},

		// 拉取 tab 配置(后台「订单列表配置」驱动);失败回退默认 6 个
		fetchTabs() {
			this.$request({ url: '/api/order/list-config', method: 'get' }).then(res => {
				const tabs = (res && res.code === 0 && res.data && Array.isArray(res.data.tabs) && res.data.tabs.length)
					? res.data.tabs : this.defaultTabs();
				this.applyTabs(tabs);
			}).catch(() => {
				this.applyTabs(this.defaultTabs());
			});
		},
		applyTabs(tabs) {
			this.tabs = tabs;
			// 深链 status 值匹配对应 tab 下标,找不到回退 0
			const idx = tabs.findIndex(t => Number(t.status) === Number(this.initStatus));
			this.currentTab = idx >= 0 ? idx : 0;
			this.refreshList();
		},
		defaultTabs() {
			return [
				{ name: '全部', status: 0 }, { name: '待付款', status: 1 }, { name: '待发货/使用', status: 2 },
				{ name: '待收货', status: 3 }, { name: '待评价', status: 4 }, { name: '关闭', status: 5 }
			];
		},

		refreshList() {
			this.page = 1;
			this.list = [];
			this.pullUpOn = true;
			this.loading = false;
			this.$nextTick(() => {
				this.getList();
			});
		},

		async getList() {
			if (this.loading || !this.pullUpOn) return;
			this.loading = true;

			try {
				const res = await this.$request({
					url: '/api/order/list',
					method: 'get',
					data: {
						page: this.page,
						limit: 10,
						status: (this.tabs[this.currentTab] && Number(this.tabs[this.currentTab].status)) || 0
					}
				});

				if (res.code === 0) {
					let rawList = res.data.list || [];
					// 兼容处理：防止返回对象
					if (!Array.isArray(rawList) && typeof rawList === 'object') {
						rawList = Object.values(rawList);
					}
					rawList = rawList.map(pickListOrder);

					this.list = this.page === 1 ? rawList : this.list.concat(rawList);

					const pagination = res.data.pagination;
					if (!pagination || pagination.page_count <= this.page) {
						this.pullUpOn = false;
					} else {
						this.page++;
					}
				}
			} catch (error) {
				console.error("订单列表请求失败:", error);
			} finally {
				this.loading = false;
				uni.stopPullDownRefresh();
			}
		},

		// --- 2. [核心] 统一动作分发 ---
		handleOrderAction(e) {
			const {
				type,
				order,
				rawAction
			} = e;
			console.log('Handle Action:', type, order.order_no);

			switch (type) {
				case 'pay':
					this.pay(order);
					break;
				case 'cancel':
					this.cancel(order, rawAction);
					break;
				case 'confirm':
					this.confirm(order);
					break;
				case 'detail':
					this.detail(order.id);
					break;
				case 'comment':
					this.comment(order);
					break;
				case 'pickup':
					this.pickupVerify(order);
					break;
				case 'activate':
					this.goToActivate(order);
					break;
				case 'luckyRecord':
					this.goToLuckyRecord(order);
					break;
				case 'viewResult':
					this.goToEmotionResult(order);
					break;
				case 'express':
					this.getExpress(order); // 改为原生弹出
					break;
				case 'applyRefund': {
					// data 可能是 list[0] 的 order_detail，也可能是 order；按 order_detail.id 跳转
					const od = e.data || (order.items && order.items[0]);
					if (od && od.id) {
						uni.navigateTo({ url: `/others/refund/refund?od_id=${od.id}&order_id=${order.id}` });
					} else {
						uni.showToast({ title: '无可退商品', icon: 'none' });
					}
					break;
				}
				case 'rebuy':
				case 'rebook': {
					// massage 订单：携带 service 参数直跳 order-create
					if (order.plugin === 'massage' && rawAction && rawAction.goods_id) {
						const params = [
							`service_id=${rawAction.service_item_id || 0}`,
							`goods_id=${rawAction.goods_id || 0}`,
							`service_name=${encodeURIComponent(rawAction.service_name || '')}`,
							`price=${rawAction.service_price || 0}`,
							`duration=${rawAction.service_duration || 0}`,
							`deposit_rate=${rawAction.service_deposit_rate || 1}`,
						].join('&');
						uni.navigateTo({ url: '/plugins2/massage/order-create?' + params });
						break;
					}
					// 其他场景：跳到首个商品详情
					const first = (order.items && order.items[0]) ? order.items[0] : null;
					if (first && first.goods && first.goods.id) {
						uni.navigateTo({ url: '/others/goods/goods?id=' + first.goods.id });
					} else {
						uni.showToast({ title: '找不到商品', icon: 'none' });
					}
					break;
				}
				case 'mchDetail':
					if (order.mch && order.mch.id) {
						uni.navigateTo({ url: '/plugins1/mch/index?id=' + order.mch.id });
					}
					break;
				case 'status':
					// 纯展示状态占位，点击无动作
					break;
				case 'dispute': {
					// 艾灸：技师确认后用户不可自助取消，跳到申诉页
					const bookingId  = (rawAction && rawAction.booking_id) || 0;
					// 后端 getMobileActions 已在 rawAction 里带 booking_status，直接用即可
					const fromStatus = (rawAction && rawAction.booking_status) || 0;
					if (!bookingId) {
						uni.showToast({ title: '订单异常，请联系客服', icon: 'none' });
						break;
					}
					uni.navigateTo({ url: '/plugins2/massage/dispute?order_id=' + bookingId + '&status=' + fromStatus });
					break;
				}
				case 'disputeDetail':
					// 申诉已处理：跳"我的申诉"列表查看裁决结果
					uni.navigateTo({ url: '/plugins2/massage/dispute-list' });
					break;
				default:
					console.warn('未知操作类型:', type);
			}
		},

		// --- 3. 具体业务逻辑实现 ---

		// 物流弹窗查阅
		async getExpress(order) {
			uni.showLoading({ title: '查询中...' });
			try {
				const res = await this.$request({
					url: "/api/order/express",
					data: { order_id: order.id }
				});
				if (res.code === 0) {
					this.express_detail = res.data.express_detail;
					this.showExpressModal = true;
				} else {
					this.$util.toast(res.msg);
				}
			} finally {
				uni.hideLoading();
			}
		},

		// 去付款:在列表里弹出支付弹窗;付完刷新列表(这张单会从「待付款」里消失),关掉 / 超时也刷新一下状态
		pay(order) {
			this.$refs.paySheet.open({ orderNo: order.order_no }).then(r => {
				if (r.status === 'paid') uni.showToast({ title: '付款成功', icon: 'success' });
				this.refreshList();
			});
		},

		// 跳转详情
		detail(id) {
			uni.navigateTo({
				url: `/others/order-detail/order-detail?order_id=${id}`
			});
		},

		// 跳转评价
		comment(order) {
			if (order.plugin === 'massage') {
				uni.navigateTo({ url: `/plugins2/massage/review?order_id=${order.id}` });
			} else {
				uni.navigateTo({ url: `/others/comment/comment?order_id=${order.id}` });
			}
		},

		// 跳转核销/自提详情
		pickupVerify(order) {
			this.detail(order.id);
		},

		// 礼品券激活。原来这里跳的是 `/plugin/voucher/activate/activate` —— 那是个接口路径形状、
		// 不是页面，pages.json 里也没有，点了必然跳转失败（订单详情页的激活是好的，只有列表这条是坏的）。
		// 改为直接调激活接口，成功后跳已有的权益详情页，不新增任何页面路径。
		async goToActivate(order) {
			uni.showLoading({ title: '激活中...', mask: true });
			try {
				const res = await this.$request({
					url: '/plugin/voucher/api/order/activate',
					method: 'POST',
					data: { order_id: order.id }
				});
				if (res.code === 0) {
					this.$util.toast('激活成功');
					setTimeout(() => {
						this.$util.go(`/plugins/voucher/voucher?order_id=${order.id}`);
					}, 800);
				} else {
					this.$util.toast(res.msg || '激活失败');
				}
			} finally {
				uni.hideLoading();
			}
		},

		// [新增] 跳转手气拼团战绩
		goToLuckyRecord(order) {
			uni.navigateTo({ url: `/plugins3/lucky/record` });
		},
		goToEmotionResult(order) {
			try {
				const remarks = JSON.parse(order.remarks || '{}');
				const recordId = remarks.record_id || 0;
				if (recordId) {
					uni.navigateTo({ url: `/plugins1/emotion/result?record_id=${recordId}` });
				} else {
					uni.navigateTo({ url: '/plugins1/emotion/records' });
				}
			} catch {
				uni.navigateTo({ url: '/plugins1/emotion/records' });
			}
		},

		// 返回首页
		goHome() {
			uni.reLaunch({
				url: '/pages/index/index'
			});
		},

		// 取消订单
		// rawAction 来自 mobile_actions.actions[i]，可携带插件自定义 endpoint：
		//   cancel_url     —— 自定义取消接口（如 /plugin/massage/api/order/cancel）
		//   cancel_payload —— 自定义请求体（如 { order_id: booking.id, ... }）
		// 主商城订单 rawAction 不带这些字段，走默认 /api/order/cancel
		cancel(order, rawAction) {
			// 艾灸订单已支付时给出退款时间预期，避免用户误以为不退款
			const isMassagePaid = order.plugin === 'massage' && Number(order.pay_status) === 1;
			const content = isMassagePaid
				? '取消后将原路退回支付金额，预计 1-3 个工作日到账。确认取消？'
				: '确认取消该订单吗？';
			uni.showModal({
				title: '提示',
				content,
				confirmColor: this.themeColor,
				success: (res) => {
					if (!res.confirm) return;
					// 插件自定义取消接口（避免被主商城 OrderForm.cancel 硬拦截已付款订单）
					if (rawAction && rawAction.cancel_url) {
						this.callPluginCancel(rawAction);
						return;
					}
					// 主商城默认链路
					this.handleApiAction('/api/order/cancel', order.id, '订单已取消');
				}
			});
		},

		// 调用插件自定义取消接口（mobile_action 上声明了 cancel_url 时使用）
		async callPluginCancel(action) {
			uni.showLoading({ title: '请稍候...' });
			try {
				const res = await this.$request({
					url: action.cancel_url,
					method: 'post',
					data: action.cancel_payload || {},
				});
				if (res.code === 0) {
					uni.showToast({ title: res.msg || '订单已取消', icon: 'success' });
					setTimeout(() => {
						// 沿用 handleApiAction 后续行为：刷新列表
						this.page = 1;
						this.list = [];
						this.getList && this.getList();
					}, 800);
				} else {
					uni.showToast({ title: res.msg || '操作失败', icon: 'none' });
				}
			} catch (e) {
				uni.showToast({ title: '网络异常', icon: 'none' });
			} finally {
				uni.hideLoading();
			}
		},

		// 确认收货 (含微信交易组件逻辑)
		confirm(order) {
			if (this.isSubmitting) return;
			this.isSubmitting = true;

			uni.showModal({
				title: '确认收货',
				content: '确认收货后将自愿放弃7天无理由退货权益',
				confirmText: '确认收货',
				confirmColor: this.themeColor,
				cancelColor: '#999',
				success: async (res) => {
					if (res.confirm) {
						await this.executeConfirmLogic(order);
					} else {
						this.isSubmitting = false;
					}
				},
				fail: () => {
					this.isSubmitting = false;
				}
			});
		},

		async executeConfirmLogic(order) {
			// #ifdef MP-WEIXIN
			if (order?.commonPayOrder?.push_wechat_params) {
				if (!uni.openBusinessView) {
					this.handleApiAction('/api/order/confirm', order.id, '确认收货成功');
					return;
				}
				try {
					await this.openWechatConfirmView(order);
				} catch (error) {
					console.log('Wechat confirm failed', error);
				} finally {
					this.isSubmitting = false;
				}
				return;
			}
			// #endif

			// 普通逻辑
			try {
				await this.handleApiAction('/api/order/confirm', order.id, '确认收货成功');
			} catch (e) {
				// handleApiAction 已处理错误提示
			} finally {
				this.isSubmitting = false;
			}
		},

		openWechatConfirmView(order) {
			return new Promise((resolve, reject) => {
				const params = order.commonPayOrder.push_wechat_params;
				uni.openBusinessView({
					businessType: 'weappOrderConfirm',
					extraData: {
						merchant_id: params.order_key.mchid,
						merchant_trade_no: order.order_no,
						transaction_id: params.order_key.transaction_id
					},
					success: async (res) => {
						await this.handleApiAction('/api/order/confirm', order.id, '确认收货成功');
						resolve(res);
					},
					fail: (err) => {
						reject(err);
					}
				});
			});
		},

		// 通用 API 请求封装
		async handleApiAction(url, orderId, successMsg) {
			uni.showLoading({
				title: '请稍候...'
			});
			try {
				const res = await this.$request({
					url: url,
					method: 'post',
					data: {
						order_id: orderId
					}
				});

				if (res.code === 0) {
					uni.showToast({
						title: successMsg,
						icon: 'success'
					});
					setTimeout(() => {
						this.refreshList();
					}, 1000);
				} else {
					uni.showToast({
						title: res.msg,
						icon: 'none'
					});
				}
			} catch (e) {
				// 网络错误已由 request 拦截器处理或在此忽略
			} finally {
				uni.hideLoading();
			}
		}
	}
}
</script>

<style lang="scss" scoped>
.container {
	padding-bottom: env(safe-area-inset-bottom);
	background: #F7F8FA;  /* 列表底色，卡片上浮感 */
	min-height: 100vh;
}

.tui-order-list {
	padding: 24rpx 24rpx 40rpx;
	box-sizing: border-box;
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