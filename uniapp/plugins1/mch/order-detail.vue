<template>
	<view class="page-container" v-if="!loading && order" :style="{ '--theme-color': themeColor || '#143A94' }">
		<view class="status-header-new">
			<view class="status-icon-wrapper">
				<uni-icons :type="getStatus(order).icon" color="#fff" size="30"></uni-icons>
			</view>
			<view class="status-info">
				<view class="status-text-new">{{ getStatus(order).text }}</view>
				<view class="status-desc-new" v-if="order.deliver_type === 'pickup' && order.status === 20">
					请在用户出示提货码后进行扫码核销
				</view>
				<view class="status-desc-new" v-else-if="order.deliver_type === 'city' && order.status < 40">配送时间
					{{ estimatedDeliveryTime }}
				</view>
			</view>
		</view>

		<view class="plugin-warning-banner" v-if="order.plugin_data && order.plugin_data.action_config && order.plugin_data.action_config.custom_tip">
			<uni-icons type="info" size="18" color="#E6A23C"></uni-icons>
			<text class="banner-text">{{ order.plugin_data.action_config.custom_tip }}</text>
		</view>

		<view class="card">
			<view class="card-title">订单信息</view>
			<view class="info-row">
				<text class="label">订单编号</text>
				<text class="value">{{ order.order_no }}</text>
			</view>
			<view class="info-row">
				<text class="label">下单时间</text>
				<text class="value">{{ order.created_time }}</text>
			</view>
			<view class="info-row">
				<text class="label">配送费</text>
				<text class="value red-text">¥{{ order.express_price }}</text>
			</view>
			<!-- 货款结算进度:文案与到期时间都由后端按 OrderSettleTask 的口径算好,
			     前端不自己拼时间,免得和真实结算时间对不上。text 为空表示这类订单
			     不由核心结算(如收银台),不显示这一行。 -->
			<view class="info-row" v-if="order.settle_info && order.settle_info.text">
				<text class="label">货款结算</text>
				<text class="value" :class="order.settle_info.status === 'settled' ? 'green-text' : 'gray-text'">
					{{ order.settle_info.text }}
				</text>
			</view>
		</view>

		<view class="card">
			<view class="goods-item" v-for="item in order.orderDetails" :key="item.id">
				<image :src="item.goods.cover_pic" class="goods-image"></image>
				<view class="goods-details">
					<view class="goods-name">{{ item.goods.name }}</view>
					<view class="goods-attrs" v-if="item.attr && item.attr.length > 0">
						{{ formatAttrs(item.attr) }}
					</view>
				</view>
				<view class="goods-price-section">
					<view class="price">¥{{ item.price }}</view>
					<view class="quantity">×{{ item.num }}</view>
				</view>
			</view>
		</view>

		<view class="card">
			<view class="card-title">收货信息</view>
			<view class="info-row">
				<text class="label">收货人</text>
				<text class="value">{{ order.name }}</text>
			</view>
			<view class="info-row">
				<text class="label">联系电话</text>
				<view class="value-interactive" @click="callPhone(order.mobile)">
					<uni-icons type="phone-filled" size="16" :color="themeColor || '#143A94'"></uni-icons>
					<text class="phone-number">{{ order.mobile }}</text>
				</view>
			</view>
			<view class="info-row address-row" v-if="order.deliver_type !== 'pickup'">
				<text class="label">收货地址</text>
				<view class="value-interactive" @click="openLocation">
					<text class="nav-text">{{ order.address }}</text>
				</view>
			</view>
			<!-- 买家填错地址是高频场景,发货前在手机上就能改,不必回电脑 -->
			<view class="info-row" v-if="order.deliver_type !== 'pickup' && canEditAddress">
				<text class="label"></text>
				<text class="link-btn" @click="openAddressEdit">修改收货信息</text>
			</view>
			<view class="info-row">
				<text class="label">买家备注</text>
				<text class="value">{{ order.remarks || '无' }}</text>
			</view>
			<view class="info-row">
				<text class="label">商家备注</text>
				<view class="value-interactive" @click="openRemarkEdit">
					<text class="value">{{ order.seller_remarks || '点击添加' }}</text>
				</view>
			</view>
			<view class="info-row" v-if="order.deliver_type !== 'pickup'">
				<text class="label">期望送达时间</text>
				<text class="value">{{ estimatedDeliveryTime }}</text>
			</view>
		</view>

		<view class="card" v-if="order.deliver_type === 'city' && order.city_delivery_info">
			<view class="card-title">配送详情</view>
			<view class="info-row">
				<text class="label">配送距离</text>
				<text class="value">{{ computedDistance }}</text>
			</view>
			<view class="info-row" v-if=" order.expected_arrival_time ">
				<text class="label">预计送达</text>
				<text class="value">{{ order.expected_arrival_time }}</text>
			</view>
			<view class="info-row" v-if="order.city_delivery_info.charge_detail">
				<text class="label">计费规则</text>
				<text
					class="value">{{ order.city_delivery_info.charge_detail.start_distance }}公里内{{ order.city_delivery_info.charge_detail.start_fee }}元，超出部分每公里{{ order.city_delivery_info.charge_detail.extra_fee_per_km }}元</text>
			</view>
			<view class="info-row" v-if="order.city_delivery_info.is_out_of_range">
				<text class="label">特别提示</text>
				<text class="value red-text">该地址超出基础配送范围</text>
			</view>
		</view>

		<view class="card">
			<view class="card-title">优惠信息</view>
			<view class="info-row">
				<text class="label">订单总金额</text>
				<text class="value">¥{{ order.goods_total_price }}</text>
			</view>
			<view class="info-row" v-if="order.coupon_cut_price > 0">
				<text class="label">优惠券减免</text>
				<text class="value red-text">-¥{{ order.coupon_cut_price }}</text>
			</view>
			<view class="info-row" v-if="order.platform_subsidy_random_minus > 0">
				<text class="label">随机减</text>
				<text class="value red-text">-¥{{ order.platform_subsidy_random_minus }}</text>
			</view>
			<view class="info-row" v-if="order.first_order_discount_price > 0">
				<text class="label">首单补贴</text>
				<text class="value red-text">-¥{{ order.first_order_discount_price }}</text>
			</view>
			<view class="info-row" v-if="order.pickup_discount_price > 0">
				<text class="label">核销优惠</text>
				<text class="value red-text">-¥{{ order.pickup_discount_price }}</text>
			</view>
			<view class="total-row">
				<text class="label">优惠后</text>
				<text class="value total-price">¥{{ order.pay_price }}</text>
			</view>
		</view>

		<view class="card" v-if="order.deliver_type === 'express' && order.expressLog">
			<view class="card-title">物流信息</view>
			<view class="info-row">
				<text class="label">快递公司</text>
				<text class="value">{{ order.expressLog.express_name }}</text>
			</view>
			<view class="info-row">
				<text class="label">快递单号</text>
				<view class="value-interactive" @click="copyExpressNo(order.expressLog.express_no)">
					<text class="address-text">{{ order.expressLog.express_no }}</text>
					<text class="nav-text" style="margin-left: 16rpx;">复制</text>
				</view>
			</view>
			<view class="info-row" v-if="canEditExpress">
				<text class="label">单号填错了？</text>
				<text class="link-btn" @click="openExpressEdit">修改物流</text>
			</view>
		</view>

		<view class="footer-bar">
			<view class="action-group">
				<!-- 联系买家 -->
				<button class="action-btn secondary" @click="callPhone(order.mobile)" v-if="order.mobile">
					<uni-icons type="phone-filled" size="16" color="#666"></uni-icons>
					<text>联系买家</text>
				</button>
				<!-- 主操作：根据订单状态显示最关键的动作 -->
				<button class="action-btn primary" v-if="primaryAction" @click="handlePrimaryAction">
					<uni-icons :type="primaryAction.icon" size="16" color="#fff"></uni-icons>
					<text>{{ primaryAction.text }}</text>
				</button>
				<!-- 更多操作 -->
				<button class="action-btn outline" @click="$refs.actionDrawer.open(order)">更多</button>
			</view>
		</view>

		<!-- [核心改造] 引入可复用的聚合弹窗组件 -->
		<action-drawer ref="actionDrawer" @success="getOrderDetail"></action-drawer>


		<!-- 修改收货信息 -->
		<view class="md-mask" v-if="addrVisible" @click="addrVisible=false">
			<view class="md-box" @click.stop>
				<view class="md-title">修改收货信息</view>
				<view class="md-tip">发货后不可再改，请在发货前核对。</view>
				<input class="md-input" v-model="addrForm.name" placeholder="收货人姓名" />
				<input class="md-input" v-model="addrForm.mobile" type="number" :maxlength="11" placeholder="联系电话" />
				<textarea class="md-textarea" v-model="addrForm.address" :maxlength="120" placeholder="详细地址" />
				<view class="md-actions">
					<view class="md-btn cancel" @click="addrVisible=false">取消</view>
					<view class="md-btn ok" @click="saveAddress">保存</view>
				</view>
			</view>
		</view>

		<!-- 修改物流 -->
		<view class="md-mask" v-if="expressVisible" @click="expressVisible=false">
			<view class="md-box" @click.stop>
				<view class="md-title">修改物流</view>
				<view class="md-tip">只改快递公司和单号，不会通知买家；买家收货后不能再改。</view>
				<picker :value="expressForm.index" :range="expressList" range-key="name" @change="onExpressPick">
					<view class="md-input md-picker" :class="{ placeholder: expressForm.index === -1 }">
						{{ expressForm.index === -1 ? '选择快递公司' : expressList[expressForm.index].name }}
					</view>
				</picker>
				<input class="md-input" v-model="expressForm.express_no" :maxlength="64" placeholder="正确的快递单号" />
				<view class="md-actions">
					<view class="md-btn cancel" @click="expressVisible=false">取消</view>
					<view class="md-btn ok" @click="saveExpress">保存</view>
				</view>
			</view>
		</view>

		<!-- 商家备注 -->
		<view class="md-mask" v-if="remarkVisible" @click="remarkVisible=false">
			<view class="md-box" @click.stop>
				<view class="md-title">商家备注</view>
				<view class="md-tip">仅商家可见，不会展示给买家；也不会覆盖买家自己的备注。</view>
				<textarea class="md-textarea" v-model="remarkText" :maxlength="200" placeholder="例如：客户要求周三送、已电话确认" />
				<view class="md-actions">
					<view class="md-btn cancel" @click="remarkVisible=false">取消</view>
					<view class="md-btn ok" @click="saveRemark">保存</view>
				</view>
			</view>
		</view>
	</view>
</template>

<script>
	import actionDrawer from './components/order-action-drawer.vue';
	import { mapState } from 'vuex';

	export default {
		components: { actionDrawer },
		data() {
			return {
				addrVisible: false,
				addrForm: { name: '', mobile: '', address: '' },
				remarkVisible: false,
				remarkText: '',
				expressVisible: false,
				expressList: [],
				expressForm: { index: -1, express_no: '' },
				orderId: null,
				order: null,
				loading: true,
			};
		},
		onLoad(options) {
			this.orderId = options.id;
			this.getOrderDetail();
		},
		computed: {
			// 发货后改地址没有意义(包裹已经在路上),只在待付款/待发货且一件都没发时开放(与后端同口径)。
			// 原来写的是 status === 1,订单状态里没有 1,入口从来不显示
			canEditAddress() {
				const o = this.order;
				return !!o && [10, 20].includes(Number(o.status)) && Number(o.delivery_status || 0) === 0;
			},
			// 发货后、买家收货前可改单号;电子面单的单号不能手改(与后端 updateExpress 同口径)
			canEditExpress() {
				const o = this.order;
				if (!o || !o.expressLog || Number(o.expressLog.eorder_detail_id) > 0) return false;
				return [20, 30].includes(Number(o.status)) && Number(o.receipt_status) !== 1;
			},
			...mapState('mall', ['themeColor']),
			estimatedDeliveryTime() {
				if (!this.order) return '--:--';
				if (this.order.status === 30 && this.order.deliver_type === 'city' && this.order.delivery_time) {
					return this.order.delivery_time;
				}
				return this.order.expected_delivery_text || '尽快送达';
			},
			computedDistance() {
				if (!this.order || !this.order.city_delivery_info || !this.order.city_delivery_info.distance) return '未知';
				let d = parseFloat(this.order.city_delivery_info.distance);
				return d < 1 ? `${(d * 1000).toFixed(0)}米` : `${d.toFixed(2)}公里`;
			},
			// 根据订单状态计算当前最关键的操作
			primaryAction() {
				if (!this.order) return null;
				const o = this.order;
				const ac = o.plugin_data && o.plugin_data.action_config;

				// 售后优先
				if (o.refund_status === 10) return { key: 'refund', text: '处理售后', icon: 'info-filled' };
				if (o.refund_status === 20) return { key: 'refund_pay', text: '确认退款', icon: 'wallet-filled' };
				// 售后单还在处理但订单标记没跟上时,待发货的也不给发货 / 核销 / 配送(与后端拦截同一个判断)
				if (o.status === 20 && o.after_sale_block_reason) return null;

				if (o.status === 20) {
					// 待发货：快递
					if (o.deliver_type === 'express') {
						if (!(ac && ac.show_ship === false)) return { key: 'ship', text: '去发货', icon: 'paperplane-filled' };
					}
					// 待核销：自提/无需发货
					if (o.deliver_type === 'pickup' || o.deliver_type === 'none' || (ac && ac.show_mch_force_verify)) {
						if (!(ac && ac.show_force_verify === false && !ac.show_mch_force_verify)) {
							return { key: 'verify', text: '核销订单', icon: 'checkbox-filled' };
						}
					}
					// 待配送：同城(商家自配送)
					if (o.deliver_type === 'city') return { key: 'ship_city', text: '开始配送', icon: 'paperplane-filled' };
				}

				if (o.status === 30) return { key: 'complete', text: '确认送达', icon: 'checkbox' };
				if (o.status === 10) return { key: 'cancel', text: '取消订单', icon: 'closeempty' };

				return null;
			}
		},
		methods: {
			openAddressEdit() {
				this.addrForm = {
					name: this.order.name || '',
					mobile: this.order.mobile || '',
					address: this.order.address || ''
				};
				this.addrVisible = true;
			},
			saveAddress() {
				const f = this.addrForm;
				if (!f.name.trim() || !f.mobile.trim() || !f.address.trim()) {
					return uni.showToast({ title: '姓名、电话、地址都要填', icon: 'none' });
				}
				uni.showLoading({ title: '保存中...' });
				this.$request({
					url: '/plugin/mch/admin/order/address-modify',
					method: 'POST',
					data: { order_id: this.orderId, name: f.name, mobile: f.mobile, address: f.address }
				}).then(e => {
					uni.showToast({ title: e.msg || '已保存', icon: e.code === 0 ? 'success' : 'none' });
					if (e.code === 0) { this.addrVisible = false; this.getOrderDetail(); }
				}).finally(() => uni.hideLoading());
			},
			async openExpressEdit() {
				if (!this.expressList.length) {
					const res = await this.$request({ url: '/common/express/express' });
					if (res.code === 0) this.expressList = res.data.list || [];
				}
				const log = this.order.expressLog;
				this.expressForm = {
					index: this.expressList.findIndex(item => item.name === log.express_name),
					express_no: log.express_no || ''
				};
				this.expressVisible = true;
			},
			onExpressPick(e) {
				this.expressForm.index = Number(e.detail.value);
			},
			saveExpress() {
				const f = this.expressForm;
				const no = (f.express_no || '').trim();
				if (f.index === -1) return uni.showToast({ title: '请选择快递公司', icon: 'none' });
				if (!no) return uni.showToast({ title: '请填写快递单号', icon: 'none' });
				const company = this.expressList[f.index];
				uni.showLoading({ title: '保存中...' });
				this.$request({
					url: '/plugin/mch/admin/order/express-update',
					method: 'POST',
					data: {
						order_id: this.orderId,
						express_log_id: this.order.expressLog.id,
						express_name: company.name,
						express_code: company.key,
						express_no: no
					}
				}).then(e => {
					uni.hideLoading();
					if (e.code !== 0) return uni.showToast({ title: e.msg || '修改失败', icon: 'none' });
					this.expressVisible = false;
					this.getOrderDetail();
					const wechat = (e.data && e.data.wechat) || {};
					if (wechat.status === 'failed') {
						// 单号已改好,只是微信没同步上:要让商家知道买家在微信里看到的可能还是旧单号
						uni.showModal({ title: '微信同步未成功', content: '物流已修改。' + wechat.message, showCancel: false });
					} else {
						uni.showToast({ title: wechat.status === 'synced' ? '已修改并同步微信' : '已修改', icon: 'success' });
					}
				}).catch(() => uni.hideLoading());
			},
			openRemarkEdit() {
				this.remarkText = this.order.seller_remarks || '';
				this.remarkVisible = true;
			},
			saveRemark() {
				uni.showLoading({ title: '保存中...' });
				this.$request({
					url: '/plugin/mch/admin/order/remarks',
					method: 'POST',
					data: { order_id: this.orderId, remarks: this.remarkText }
				}).then(e => {
					uni.showToast({ title: e.msg || '已保存', icon: e.code === 0 ? 'success' : 'none' });
					if (e.code === 0) { this.remarkVisible = false; this.getOrderDetail(); }
				}).finally(() => uni.hideLoading());
			},
			// 主操作按钮分发
			handlePrimaryAction() {
				const key = this.primaryAction && this.primaryAction.key;
				if (!key) return;
				// 复用 action-drawer 的能力，打开到对应子面板
				const drawer = this.$refs.actionDrawer;
				switch (key) {
					case 'ship':
						drawer.open(this.order);
						this.$nextTick(() => drawer.switchMode('ship'));
						break;
					case 'verify':
						uni.showModal({
							title: '确认核销',
							content: '确认要核销该订单吗？核销后将立即完成订单并结算。',
							success: (res) => {
								if (res.confirm) this.executeApi('/plugin/mch/admin/order/verify', { code: this.order.pickup_code }, '核销成功');
							}
						});
						break;
					case 'ship_city':
						uni.showModal({
							title: '开始配送',
							content: '确认由本店开始配送此订单吗？',
							success: (res) => {
								if (res.confirm) this.executeApi('/plugin/mch/admin/order/ship', { order_id: this.orderId }, '已开始配送');
							}
						});
						break;
					case 'complete':
						uni.showModal({
							title: '确认送达',
							content: '确认商品已送达买家？',
							success: (res) => {
								if (res.confirm) this.executeApi('/plugin/mch/admin/order/complete', { order_id: this.orderId }, '订单已完成');
							}
						});
						break;
					case 'cancel':
						drawer.open(this.order);
						this.$nextTick(() => drawer.switchMode('cancel'));
						break;
					case 'refund':
						drawer.open(this.order);
						this.$nextTick(() => drawer.switchMode('refund_action'));
						break;
					case 'refund_pay':
						drawer.open(this.order);
						this.$nextTick(() => drawer.switchMode('refund_pay'));
						break;
				}
			},
			async executeApi(url, data, successMsg) {
				uni.showLoading({ title: '处理中...' });
				try {
					const res = await this.$request({ url, method: 'POST', data });
					if (res.code === 0) {
						uni.showToast({ title: successMsg, icon: 'success' });
						this.getOrderDetail();
					} else {
						this.$showModal(res.msg);
					}
				} finally {
					uni.hideLoading();
				}
			},
			async getOrderDetail() {
				this.loading = true;
				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/order/detail',
						data: { order_id: this.orderId }
					});
					if (res.code === 0) {
						let orderData = res.data.order || res.data.detail;
						if (res.data.plugin_data) {
							orderData.plugin_data = res.data.plugin_data;
						}
						this.order = orderData;
					} else {
						this.$showModal(res.msg);
					}
				} finally {
					this.loading = false;
				}
			},
			formatAttrs(attrs) {
				return Array.isArray(attrs) ? attrs.map(attr => attr.value).join('; ') : '';
			},
			getStatus(order) {
				if (order.refund_status > 0) return { text: '售后中', color: '#E6A23C', icon: 'reload' };
				const map = {
					0: { text: '已关闭', color: '#909399', icon: 'close-filled' },
					10: { text: '待付款', color: '#FA5151', icon: 'wallet-filled' },
					30: order.deliver_type === 'city'
						? { text: '配送中', color: 'var(--theme-color)', icon: 'paperplane-filled' }
						: { text: '已发货', color: 'var(--theme-color)', icon: 'truck-filled' },
					40: { text: '已完成', color: '#2E8B57', icon: 'checkbox-filled' }
				};
				if (order.status === 20) {
					const textMap = { pickup: '待核销', none: '待核销', city: '待配送', express: '待发货' };
					return { text: textMap[order.deliver_type] || '待处理', color: '#FF9900', icon: 'gift-filled' };
				}
				return map[order.status] || { text: `未知(${order.status})`, color: '#303133', icon: 'info-filled' };
			},
			copyExpressNo(no) {
				uni.setClipboardData({ data: no, success: () => uni.showToast({ title: '复制成功', icon: 'none' }) });
			},
			callPhone(phone) {
				if (phone) uni.makePhoneCall({ phoneNumber: phone });
			},
			openLocation() {
				if (!this.order || !this.order.latitude || !this.order.longitude) return;
				uni.openLocation({
					latitude: parseFloat(this.order.latitude),
					longitude: parseFloat(this.order.longitude),
					name: this.order.address,
					address: this.order.address
				});
			}
		}
	}
</script>

<style lang="scss" scoped>
	page {
		background-color: #f7f8fa;
	}

	.page-container {
		padding-bottom: 160rpx;
	}

	.status-header-new {
		display: flex;
		align-items: center;
		padding: 40rpx 30rpx;
		background: var(--theme-color);
		color: #fff;
		position: relative;
		overflow: hidden;

		&::after {
			content: '';
			position: absolute;
			right: -80rpx;
			top: -80rpx;
			width: 250rpx;
			height: 250rpx;
			background-color: rgba(255, 255, 255, 0.1);
			border-radius: 50%;
			opacity: 0.5;
		}
	}

	.status-icon-wrapper {
		width: 80rpx;
		height: 80rpx;
		background-color: rgba(255, 255, 255, 0.2);
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		margin-right: 24rpx;
	}

	.status-info {
		.status-text-new {
			font-size: 36rpx;
			font-weight: bold;
		}

		.status-desc-new {
			font-size: 26rpx;
			opacity: 0.9;
			margin-top: 8rpx;
		}
	}

	.plugin-warning-banner {
		background-color: #fdf6ec;
		padding: 20rpx 30rpx;
		display: flex;
		align-items: flex-start;
		border-bottom: 1rpx solid #faecd8;

		.banner-text {
			color: #E6A23C;
			font-size: 26rpx;
			line-height: 1.5;
			margin-left: 12rpx;
			flex: 1;
		}
	}

	.card {
		background-color: #fff;
		border-radius: 16rpx;
		margin: 24rpx;
		padding: 30rpx;
	}

	.card-title {
		font-size: 32rpx;
		font-weight: 500;
		color: #333;
		margin-bottom: 30rpx;
	}

	.info-row {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		font-size: 28rpx;
		margin-bottom: 24rpx;

		&:last-child {
			margin-bottom: 0;
		}

		.label {
			color: #999;
			flex-shrink: 0;
			margin-right: 20rpx;
			white-space: nowrap;
		}

		.value {
			color: #333;
			text-align: right;
		}

		&.address-row .value {
			flex: 1;
		}
	}

	.red-text {
		color: var(--theme-color);
	}

	/* 结算状态用固定语义色,不跟主题色走:已结算=到账(绿),待结算=中性(灰)。
	   用主题色的话「已结算」和上面的金额同色,反而分不出是钱数还是状态。 */
	.green-text {
		color: #07C160;
	}

	.gray-text {
		color: #909399;
	}

	.goods-item {
		display: flex;
		align-items: center;
		padding: 20rpx 0;
		border-bottom: 1rpx solid #f0f0f0;

		&:first-child {
			padding-top: 0;
		}

		&:last-child {
			border-bottom: none;
			padding-bottom: 0;
		}
	}

	.goods-image {
		width: 120rpx;
		height: 120rpx;
		border-radius: 12rpx;
		flex-shrink: 0;
		margin-right: 20rpx;
	}

	.goods-details {
		flex: 1;
		min-width: 0;
	}

	.goods-name {
		font-size: 28rpx;
		color: #333;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		line-clamp: 2;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.goods-attrs {
		font-size: 24rpx;
		color: #999;
		margin-top: 8rpx;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.goods-price-section {
		text-align: right;
		font-size: 28rpx;
		margin-left: 20rpx;

		.price {
			color: #333;
			font-weight: 500;
		}

		.quantity {
			font-size: 24rpx;
			color: #999;
			margin-top: 8rpx;
		}
	}

	.value-interactive {
		display: flex;
		align-items: center;
		justify-content: flex-end;
		color: var(--theme-color);
		flex: 1;

		.phone-number {
			margin-left: 8rpx;
		}

		.address-text {
			color: #333;
			text-align: right;
		}

		.nav-icon {
			margin: 0 4rpx 0 16rpx;
		}

		.nav-text {
			text-align: end;
			color: var(--theme-color);
		}
	}

	.total-row {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-top: 30rpx;
		padding-top: 20rpx;
		border-top: 1rpx solid #f0f0f0;

		.label {
			font-size: 30rpx;
			color: #333;
		}

		.total-price {
			font-size: 36rpx;
			font-weight: bold;
			color: var(--theme-color);
		}
	}

	.footer-bar {
		position: fixed;
		bottom: 0;
		left: 0;
		width: 100%;
		background-color: #fff;
		padding: 20rpx 30rpx;
		box-sizing: border-box;
		box-shadow: 0 -4rpx 16rpx rgba(0, 0, 0, 0.06);
		padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
	}

	.action-group {
		display: flex;
		gap: 16rpx;
		align-items: center;
		justify-content: flex-end;
	}

	.action-btn {
		height: 76rpx;
		line-height: 76rpx;
		font-size: 28rpx;
		border-radius: 38rpx;
		border: none;
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 8rpx;
		padding: 0 36rpx;

		&::after { border: none; }

		&.primary {
			background: var(--theme-color);
			color: #fff;
			flex: 1;
		}

		&.secondary {
			background-color: #f5f5f5;
			color: #333;
		}

		&.outline {
			background-color: #fff;
			color: #666;
			border: 1rpx solid #dcdfe6;
		}
	}

.link-btn { color: var(--theme-color); font-size: 26rpx; }
.md-mask { position: fixed; left: 0; right: 0; top: 0; bottom: 0; background: rgba(0,0,0,.45);
  display: flex; align-items: center; justify-content: center; z-index: 99; }
.md-box { width: 620rpx; background: #fff; border-radius: 20rpx; padding: 36rpx 32rpx; }
.md-title { font-size: 32rpx; font-weight: 700; color: #1E293B; text-align: center; }
.md-tip { font-size: 22rpx; color: #94A3B8; text-align: center; margin-top: 10rpx; line-height: 1.6; }
.md-input { margin-top: 22rpx; height: 80rpx; background: #F1F5F9; border-radius: 12rpx;
  padding: 0 20rpx; font-size: 28rpx; }
.md-textarea { margin-top: 22rpx; height: 150rpx; width: 100%; box-sizing: border-box;
  background: #F1F5F9; border-radius: 12rpx; padding: 16rpx 20rpx; font-size: 26rpx; }
.md-actions { display: flex; gap: 20rpx; margin-top: 32rpx; }
.md-btn { flex: 1; height: 80rpx; line-height: 80rpx; text-align: center; border-radius: 40rpx; font-size: 28rpx; }
.md-btn.cancel { background: #F1F5F9; color: #64748B; }
.md-btn.ok { background: var(--theme-color); color: #fff; }
.md-picker { line-height: 80rpx; color: #1E293B; }
.md-picker.placeholder { color: #94A3B8; }
</style>