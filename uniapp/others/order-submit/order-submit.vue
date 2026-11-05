<template>
	<tui-page>
		<view class="container" :style="themeStyle">

			<view class="polling-mask" v-if="isPolling">
				<view class="polling-card">
					<tui-loading :text="pollingText" :size="32" :color="themeColor"></tui-loading>
					<view class="polling-subtext" v-if="pollingStatus === 'pending'">正在处理您的订单...</view>
				</view>
			</view>

			<view v-if="pageLoaded" class="main-content">

				<view class="delivery-tabs-wrapper" v-if="availableDeliveryOptions.length > 1">
					<view class="delivery-tabs">
						<view class="tab-item" :class="{'active': deliveryType === option.key}"
							v-for="option in availableDeliveryOptions" :key="option.key"
							@click="switchDeliveryType(option.key)"
							:style="{ color: deliveryType === option.key ? themeColor : '#666' }">
							<text class="tab-text">{{ option.name }}</text>
							<view class="active-indicator" v-if="deliveryType === option.key"
								:style="{background: themeColor}"></view>
						</view>
					</view>
				</view>

				<view class="card-section address-card" v-if="deliveryType === 'express' || pickupNeedsAddress" @click="chooseAddr">
					<view class="address-content">
						<block v-if="orderInfo.default_address">
							<view class="addr-icon-box" :style="{background: hexToRgba(themeColor, 0.08)}">
								<tui-icon name="position-fill" :size="22" :color="themeColor"></tui-icon>
							</view>
							<view class="addr-info">
								<view class="addr-detail">
									<text class="tag"
										v-if="orderInfo.default_address.tag">{{orderInfo.default_address.tag}}</text>
									{{orderInfo.default_address.address}}
								</view>
								<view class="addr-user">
									<text class="name">{{orderInfo.default_address.name}}</text>
									<text class="mobile">{{orderInfo.default_address.mobile}}</text>
								</view>
							</view>
							<tui-icon name="arrowright" :size="14" color="#ccc"></tui-icon>
						</block>
						<block v-else>
							<view class="empty-address">
								<view class="add-btn-icon" :style="{background: themeColor}">
									<tui-icon name="plus" :size="18" color="#fff"></tui-icon>
								</view>
								<text>请选择收货地址</text>
							</view>
						</block>
					</view>
					<view class="addr-decoration"></view>
				</view>

				<!-- 「用户选收货地址」模式下整块不渲染:取货点与提货人都由上方的地址卡片承载 -->
				<view class="card-section" v-if="deliveryType === 'pickup' && !pickupNeedsAddress">
					<view class="pickup-store-box" v-if="orderInfo && orderInfo.mch">
						<view class="section-header">
							<text class="title">取货门店</text>
						</view>
						<view class="store-info-card">
							<view class="store-top">
								<text class="name">{{ orderInfo.mch.mch_name || orderInfo.mch.name }}</text>
								<view class="distance-tag" v-if="orderInfo.mch.distance">
									距您 {{orderInfo.mch.distance}}
								</view>
							</view>
							<view class="store-bottom" @click="openLocation">
								<tui-icon name="position" :size="14" color="#666"></tui-icon>
								<text class="text">{{ orderInfo.mch.address }}</text>
								<tui-icon name="arrowright" :size="12" color="#999"></tui-icon>
							</view>
						</view>
					</view>

					<view class="pickup-store-box" v-else-if="orderInfo && orderInfo.pickup_point">
						<view class="section-header">
							<text class="title">自提点</text>
						</view>
						<view class="store-info-card">
							<view class="store-top">
								<text class="name">{{ orderInfo.pickup_point.name || '自提点' }}</text>
							</view>
							<view class="store-bottom">
								<tui-icon name="position" :size="14" color="#666"></tui-icon>
								<text class="text">{{ orderInfo.pickup_point.address || '暂未配置自提点地址' }}</text>
							</view>
						</view>
					</view>

					<view class="divider-line"></view>

					<view class="form-group">
						<view class="form-item">
							<text class="label">提货人</text>
							<input class="input" v-model="submitParams.name" placeholder="请输入姓名"
								placeholder-class="ph" />
						</view>
						<view class="form-item">
							<text class="label">手机号</text>
							<input class="input" type="number" v-model="submitParams.mobile" placeholder="请输入手机号"
								maxlength="11" placeholder-class="ph" />
						</view>
					</view>
				</view>

				<view v-if="deliveryType === 'city'">
					<view class="card-section address-card" @click="chooseAddr">
						<view class="address-content">
							<block v-if="orderInfo.default_address">
								<view class="addr-icon-box" :style="{background: hexToRgba(themeColor, 0.08)}">
									<tui-icon name="position-fill" :size="22" :color="themeColor"></tui-icon>
								</view>
								<view class="addr-info">
									<view class="addr-detail">
										{{orderInfo.default_address.address}}
									</view>
									<view class="addr-user">
										<text class="name">{{orderInfo.default_address.name}}</text>
										<text class="mobile">{{orderInfo.default_address.mobile}}</text>
									</view>
									<view class="error-tips"
										v-if="is_address_error || (city_delivery_info && city_delivery_info.is_out_of_range)">
										<tui-icon name="about-fill" :size="12" color="#F12F2F"></tui-icon>
										<text>超出配送范围</text>
									</view>
								</view>
								<tui-icon name="arrowright" :size="14" color="#ccc"></tui-icon>
							</block>
							<block v-else>
								<view class="empty-address">
									<tui-icon name="plus-circle-fill" :size="36" :color="themeColor"></tui-icon>
									<text>请选择收货地址</text>
								</view>
							</block>
						</view>
					</view>

					<view class="city-block-tip" v-if="cityBlockReason">
						<tui-icon name="about-fill" :size="14" color="#F12F2F"></tui-icon>
						<text class="city-block-text">{{ cityBlockReason }}</text>
						<text v-if="city_delivery_info && city_delivery_info.is_location_missing" class="city-block-action"
							@click="goSetLocation">去设置定位 ›</text>
					</view>

					<view class="card-section time-card">
						<view class="card-header">
							<text class="title">配送时间</text>
						</view>
						<view class="time-selector">
							<view class="time-btn" :class="{'active': submitParams.is_immediately === 1}"
								:style="{ borderColor: submitParams.is_immediately === 1 ? themeColor : 'transparent', color: submitParams.is_immediately === 1 ? themeColor : '#333', background: submitParams.is_immediately === 1 ? hexToRgba(themeColor, 0.05) : '#f8f8f8' }"
								@click="selectDeliveryTime(1)">
								<text class="main-text">立即送出</text>
							</view>

							<picker class="time-picker" mode="multiSelector" :range="deliveryTimeRange"
								@change="onDeliveryTimeChange" @columnchange="onDeliveryTimeColumnChange"
								:disabled="availableTimesData.length === 0">
								<view class="time-btn" :class="{'active': submitParams.is_immediately === 0}"
									:style="{ borderColor: submitParams.is_immediately === 0 ? themeColor : 'transparent', color: submitParams.is_immediately === 0 ? themeColor : '#333', background: submitParams.is_immediately === 0 ? hexToRgba(themeColor, 0.05) : '#f8f8f8' }">
									<view class="col-center">
										<text class="main-text">预约时间</text>
										<text class="sub-text"
											v-if="submitParams.delivery_time">{{submitParams.delivery_time}}</text>
									</view>
								</view>
							</picker>
						</view>
					</view>
				</view>

				<view class="card-section goods-card">
					<view class="card-header">
						<tui-icon name="shop-fill" :size="16" color="#333"></tui-icon>
						<text
							class="shop-name">{{ orderInfo.mch ? (orderInfo.mch.mch_name || orderInfo.mch.name) : '平台自营' }}</text>
					</view>

					<view class="goods-list">
						<block v-for="(item,index) in orderInfo.goods_list" :key="index">
							<view class="goods-item">
								<image :src="item.cover_pic" class="goods-img" mode="aspectFill"></image>
								<view class="goods-content">
									<view class="goods-title">{{item.name}}</view>
									<view class="goods-specs">
										<text v-if="item.specs_data" v-for="(spec, si) in item.specs_data"
											:key="si">{{spec.value}} </text>
										<text v-else>默认规格</text>
									</view>
									<view class="goods-price-row">
										<view class="price" :style="{color: themeColor}">
											<text class="sym">¥</text>{{item.price}}
										</view>
										<view class="num">x{{item.num}}</view>
									</view>
								</view>
							</view>
						</block>
					</view>

					<view class="divider-line"></view>

					<view class="form-row">
						<text class="label">买家留言</text>
						<input class="input" v-model="submitParams.remarks" placeholder="如有特殊需求请填写"
							placeholder-class="ph" />
					</view>
				</view>

				<!-- 插件扩展区：由各插件通过 plugin_extensions 钩子注入，零侵入 -->
				<order-plugin-extensions
					:extensions="pluginExtensions"
					@change="val => submitParams.plugin_params = val"
				></order-plugin-extensions>

				<view class="card-section cost-card">
					<view class="cost-row">
						<text class="label">商品总额</text>
						<text class="value">¥{{orderInfo.goods_total_price}}</text>
					</view>
					<view class="cost-row">
						<text class="label">运费</text>
						<text class="value">+ ¥{{orderInfo.express_price}}</text>
					</view>

					<view class="cost-row clickable" @click="openCouponPopup"
						v-if="orderInfo.coupon_list && orderInfo.coupon_list.length > 0">
						<text class="label">优惠券</text>
						<view class="right-col">
							<view class="coupon-tag" v-if="Number(orderInfo.coupon_cut_price) > 0"
								:style="{color: themeColor, borderColor: hexToRgba(themeColor, 0.3), background: hexToRgba(themeColor, 0.05)}">
								<text class="minus">-</text>
								<text class="sym">¥</text>
								<text class="num">{{orderInfo.coupon_cut_price}}</text>
							</view>
							<text class="gray-text" v-else>{{ orderInfo.coupon_list.length }}张可用</text>
							<tui-icon name="arrowright" :size="12" color="#ccc" style="margin-left: 6rpx;"></tui-icon>
						</view>
					</view>

					<view class="cost-row" v-if="orderInfo.can_use_integral && orderInfo.user_integral > 0">
						<view class="row-left">
							<text class="label">{{ $noun('integral') }}抵扣</text>
							<text class="tips">共{{orderInfo.user_integral}}{{ $noun('integral') }}</text>
						</view>
						<view class="right-col">
							<view class="discount-val" v-if="submitParams.is_use_integral" :style="{color: themeColor}">
								<text class="minus">-</text>¥{{orderInfo.integral_cut_price}}
							</view>
							<switch :checked="!!submitParams.is_use_integral" :color="themeColor"
								style="transform:scale(0.7); margin-left: 10rpx;" @change="toggleIntegral" />
						</view>
					</view>

					<view class="cost-row" v-if="Number(orderInfo.pickup_discount_price) > 0">
						<text class="label">自提优惠</text>
						<view class="discount-val" :style="{color: themeColor}">
							<text class="minus">-</text>¥{{orderInfo.pickup_discount_price}}
						</view>
					</view>

					<view class="cost-row" v-if="Number(orderInfo.first_order_discount_price) > 0">
						<text class="label">首单优惠</text>
						<view class="discount-val" :style="{color: themeColor}">
							<text class="minus">-</text>¥{{orderInfo.first_order_discount_price}}
						</view>
					</view>

					<view class="cost-row" v-if="Number(orderInfo.random_minus_price) > 0">
						<text class="label">支付随机减</text>
						<view class="discount-val" :style="{color: themeColor}">
							<text class="minus">-</text>¥{{orderInfo.random_minus_price}}
						</view>
					</view>

					<view class="total-divider"></view>

					<view class="final-total">
						<text class="label">合计</text>
						<view class="price-box" :style="{color: themeColor}">
							<text class="sym">¥</text>
							<text class="num">{{orderInfo.total_price}}</text>
						</view>
					</view>
				</view>

			</view>

			<view class="footer-bar" v-if="pageLoaded">
				<view class="footer-content">
					<view class="price-container">
						<text class="label">实付</text>
						<view class="price-num" :style="{color: themeColor}">
							<text class="sym">¥</text>
							<text class="num">{{orderInfo.total_price}}</text>
						</view>
					</view>
					<button class="submit-btn" :class="{ 'is-blocked': !!cityBlockReason && !createdPay }" :style="{background: themeColor, boxShadow: '0 6rpx 16rpx ' + hexToRgba(themeColor, 0.3)}" @click="submit"
						:disabled="isSubmitting || (!!cityBlockReason && !createdPay)" :loading="isSubmitting">
						{{ createdPay ? '继续付款' : (isSubmitting ? '提交中' : (cityBlockReason ? '无法提交' : '提交订单')) }}
					</button>
				</view>
			</view>

			<!-- 支付弹窗(全局组件 components/jx-pay-sheet):提交订单后直接在本页付款,付完去哪由本页决定 -->
			<jx-pay-sheet ref="paySheet"></jx-pay-sheet>

			<tui-bottom-popup :show="popupShow" @close="popupShow = false" :zIndex="1002">
				<view class="popup-wrap">
					<view class="popup-header">
						<text>可用优惠券</text>
						<view class="close-btn" @click="popupShow = false">
							<tui-icon name="close-fill" color="#ccc" :size="20"></tui-icon>
						</view>
					</view>
					<scroll-view scroll-y class="popup-scroll">
						<view class="no-use-cell" @click="selectCoupon(-1)">
							<text>不使用优惠券</text>
							<view class="radio-circle" :class="{active: submitParams.user_coupon_id == -1}"
								:style="{borderColor: submitParams.user_coupon_id == -1 ? themeColor : '#ccc'}">
								<view class="dot" :style="{background: themeColor}"
									v-if="submitParams.user_coupon_id == -1"></view>
							</view>
						</view>

						<view class="coupon-card" v-for="(item, index) in orderInfo.coupon_list" :key="index"
							@click="selectCoupon(item.id)">
							<view class="card-left" :style="{background: hexToRgba(themeColor, 0.05)}">
								<view class="price" :style="{color: themeColor}">
									<text class="sym" v-if="item.type == 0">¥</text>
									<text class="num">{{ item.type == 0 ? item.sub_price : item.discount }}</text>
									<text class="sym" v-if="item.type == 1">折</text>
								</view>
								<view class="cond" :style="{color: themeColor}">满{{ item.min_price }}可用</view>
							</view>
							<view class="card-right">
								<view class="cp-info">
									<view class="cp-name">{{ item.name }}</view>
									<view class="cp-date">{{ item.date }}</view>
								</view>
								<view class="radio-circle" :class="{active: submitParams.user_coupon_id == item.id}"
									:style="{borderColor: submitParams.user_coupon_id == item.id ? themeColor : '#ccc'}">
									<view class="dot" :style="{background: themeColor}"
										v-if="submitParams.user_coupon_id == item.id"></view>
								</view>
							</view>
						</view>
					</scroll-view>
				</view>
			</tui-bottom-popup>
		</view>
	</tui-page>
</template>

<script>
	import {
		mapState
	} from 'vuex';
	import OrderPluginExtensions from '@/components/jxmall/order-plugin-extensions/order-plugin-extensions.vue';

	export default {
		components: { OrderPluginExtensions },

		data() {
			return {
				pageLoaded: false,
				originalOrderData: null,
				plugin: '',
				type: '',
				pluginExtensions: [],
				submitParams: {
					address_id: 0,
					user_coupon_id: 0,
					is_use_integral: false,
					// 随机减锁定值:''=尚未锁定(首次预览让后端现摇);拿到预览返回的 random_minus_price 后回填锁住
					locked_random_minus_price: '',
					is_immediately: 1,
					delivery_time: '',
					name: '',
					mobile: '',
					remarks: '',
					plugin_params: {}
				},
				orderInfo: {
					total_price: '0.00',
					goods_total_price: '0.00',
					coupon_cut_price: '0.00',
					integral_cut_price: '0.00',
					express_price: '0.00',
					can_use_integral: false,
					goods_list: []
				},
				deliveryType: 'express',
				availableDeliveryOptions: [],
				isSubmitting: false,
				popupShow: false,
				is_address_error: false,
				city_delivery_info: null,
				availableTimesData: [],
				deliveryTimeRange: [
					[],
					[]
				],
				isPolling: false,
				pollingStatus: 'pending',
				pollingText: '正在提交...',
				queueId: null,
				// 订单已建好的支付单 { payOrderId }:有了它再点按钮只重新打开弹窗,不会重复下单
				createdPay: null,
				pollingTimer: null,
				pollingCount: 0
			}
		},
		computed: {
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F'
			}),
			// 平台自营自提 + 后台配置为「用户默认收货地址」时,与快递一样必须选一个收货地址
			pickupNeedsAddress() {
				return this.deliveryType === 'pickup'
					&& !!this.orderInfo
					&& !this.orderInfo.mch
					&& this.orderInfo.pickup_address_type === 'user_address';
			},
			// 同城配送:超范围 / 未达起送金额 时给出拦截原因(空串=可提交)。与后端创建阶段拦截同口径。
			cityBlockReason() {
				if (this.deliveryType !== 'city' || !this.city_delivery_info) return '';
				if (this.city_delivery_info.is_location_missing) return '该地址未设置定位信息,无法同城配送,请更换地址或选择地理位置';
					if (this.city_delivery_info.is_out_of_range) return '收货地址超出商家配送范围,请更换地址';
				const min = Number(this.city_delivery_info.min_order_amount || 0);
				const goods = Number((this.orderInfo && this.orderInfo.goods_total_price) || 0);
				if (min > 0 && goods < min) {
					return `未达起送金额,还需 ¥${(min - goods).toFixed(2)}(起送 ¥${min.toFixed(2)})`;
				}
				return '';
			}
		},
		onLoad(options) {
			const orderData = uni.getStorageSync('ORDER_SUBMIT_DATA');
			if (!orderData) {
				uni.showToast({
					title: '参数错误',
					icon: 'none'
				});
				setTimeout(() => uni.navigateBack(), 1500);
				return;
			}
			this.originalOrderData = orderData;
			this.plugin = options.plugin || (orderData.extra ? orderData.extra.plugin : '') || '';
			this.type = options.type || (orderData.extra ? orderData.extra.type : '') || '';
			this.submitParams.name = uni.getStorageSync('pickup_name') || '';
			this.submitParams.mobile = uni.getStorageSync('pickup_mobile') || '';
			this.getOrderPreview();
		},
		onShow() {
			const selectedAddress = uni.getStorageSync('SELECT_ADDRESS');
			if (selectedAddress) {
				this.submitParams.address_id = selectedAddress.id;
				uni.removeStorageSync('SELECT_ADDRESS');
				this.getOrderPreview();
			} else if (this._awaitLocation) {
				// 从「去设置定位」返回:重新拉预览,刷新同城拦截状态(坐标补上后应放行)
				this._awaitLocation = false;
				this.getOrderPreview();
			}
		},
		onUnload() {
			this.stopPolling();
		},
		methods: {buildRequestPayload() {
				const businessParams = {
					mch_id: this.originalOrderData.mch_id,
					goods_list: this.originalOrderData.goods_list,
					deliver_type: this.deliveryType,
					address_id: this.submitParams.address_id,
					user_coupon_id: this.submitParams.user_coupon_id,
					is_use_integral: this.submitParams.is_use_integral ? 1 : 0,
					remarks: this.submitParams.remarks,
					locked_random_minus_price: this.submitParams.locked_random_minus_price,
					name: this.submitParams.name,
					mobile: this.submitParams.mobile,
					is_immediately: this.submitParams.is_immediately,
					delivery_time: this.submitParams.delivery_time,
					plugin_params: this.submitParams.plugin_params,
					...(this.originalOrderData.extra || {})
				};
				return {
					plugin: this.plugin,
					type: this.type,
					form_data: JSON.stringify(businessParams)
				};
			},
			async getOrderPreview() {
				uni.showLoading({
					title: '计算中...',
					mask: true
				});
				try {
					const postData = this.buildRequestPayload();
					const res = await this.$request({
						url: '/api/order/order-preview',
						method: 'post',
						data: postData
					});
					if (res.code === 0) {
						const data = res.data;
						this.orderInfo = data;
						// 随机减:首次预览后端现摇出金额 → 前端锁住它;之后预览/提交回传同一值(保证"看到多少付多少")
						if (this.submitParams.locked_random_minus_price === '' && data.random_minus_price !== undefined && data.random_minus_price !== null) {
							this.submitParams.locked_random_minus_price = data.random_minus_price;
						}
						this.pluginExtensions = data.plugin_extensions || [];
						if (this.submitParams.user_coupon_id === 0 && data.user_coupon_id > 0) {
							this.submitParams.user_coupon_id = data.user_coupon_id;
						}
						this.city_delivery_info = data.city_delivery_info;
						if (data.default_address && !this.submitParams.address_id) {
							this.submitParams.address_id = data.default_address.id;
						}
						if (this.availableDeliveryOptions.length === 0) {
							this.initDeliveryOptions(data);
						}
						if (this.deliveryType === 'city') {
							this.initCityTimes(data);
						}
						this.pageLoaded = true;
					} else if (res.code === -1) {
						// 未登录。注意这里才是未登录用户真正会撞到的地方 ——
						// 商品页「立即购买」和购物车结算都不判登录，直接写缓存跳过来，
						// 于是 onLoad 的预览请求第一个被拦下，用户压根走不到「提交订单」。
						// 原来这里和其他错误一样弹「无法下单」+ 单个「我知道了」，
						// 用户被弹回上一页，既不知道为什么，也没有去登录的入口。
						uni.hideLoading();
						this.$http.promptLogin({
							content: '登录后才能下单，现在去登录吗？',
							// 预览失败时页面是空白的：返回=退出，去登录=顶掉这张废页
							pageUnusable: true
						});
						return;
					} else {
						// 后端拒绝下单（如商品下架、用户等级不足等）
						// 关键：必须用 modal 强制用户确认，并点完返回上一页 —— 不能停留在空白页面
						uni.hideLoading();
						uni.showModal({
							title: '无法下单',
							content: res.msg || '订单预览失败，请稍后再试',
							showCancel: false,
							confirmText: '我知道了',
							success: () => {
								uni.navigateBack({
									fail: () => uni.switchTab({ url: '/pages/index/index' }),
								});
							},
						});
						return;
					}
				} catch (e) {
					// 网络异常同样必须弹窗 + 返回，避免空白页
					uni.hideLoading();
					uni.showModal({
						title: '网络异常',
						content: '订单预览请求失败，请检查网络后重试',
						showCancel: false,
						confirmText: '返回',
						success: () => {
							uni.navigateBack({
								fail: () => uni.switchTab({ url: '/pages/index/index' }),
							});
						},
					});
					return;
				} finally {
					uni.hideLoading();
				}
			},
			initDeliveryOptions(data) {
				let keys = ['express'];
				if (data.mch && data.mch.delivery_options) {
					try {
						keys = JSON.parse(data.mch.delivery_options);
					} catch (e) {}
				} else if (data.support_pickup == 1) {
					// 平台自营:开了「支持自提」则在快递之外加到店自提
					keys = ['express', 'pickup'];
				}
				const map = {
					express: {
						key: 'express',
						name: '快递配送'
					},
					pickup: {
						key: 'pickup',
						name: '到店自提'
					},
					city: {
						key: 'city',
						name: '同城配送'
					},
					cashier: {
						key: 'cashier',
						name: '收银台'
					}
				};
				this.availableDeliveryOptions = keys.map(k => map[k]).filter(Boolean);
				if (!this.availableDeliveryOptions.find(o => o.key === this.deliveryType)) {
					if (this.availableDeliveryOptions.length > 0) {
						this.deliveryType = this.availableDeliveryOptions[0].key;
						this.$nextTick(() => {
							this.getOrderPreview();
						});
					}
				}
			},
			initCityTimes(data) {
				if (data.available_delivery_times && data.available_delivery_times.length > 0) {
					this.availableTimesData = data.available_delivery_times;
					this.deliveryTimeRange = [
						this.availableTimesData.map(d => d.label),
						this.availableTimesData[0].slots.map(s => s.label)
					];
					if (!this.submitParams.delivery_time && this.submitParams.is_immediately === 0) {
						this.submitParams.delivery_time =
							`${this.availableTimesData[0].label} ${this.availableTimesData[0].slots[0].label}`;
					}
				} else {
					this.availableTimesData = [];
				}
			},
			switchDeliveryType(type) {
				if (this.deliveryType === type) return;
				this.deliveryType = type;
				this.submitParams.is_immediately = 1;
				this.getOrderPreview();
			},
			toggleIntegral(e) {
				this.submitParams.is_use_integral = e.detail.value;
				this.getOrderPreview();
			},
			chooseAddr() {
				uni.navigateTo({
					url: "/others/address/address?type=SELECT"
				});
			},
			// 同城配送「地址缺定位」拦截 → 就地补坐标:跳地址编辑并强制显示定位字段(绕过商城开关),
			// 回来后 onShow 重新拉预览刷新拦截状态,避免死循环
			goSetLocation() {
				if (!this.submitParams.address_id) {
					return uni.showToast({ title: '请先选择收货地址', icon: 'none' });
				}
				this._awaitLocation = true;
				uni.navigateTo({
					url: `/others/address-edit/address-edit?id=${this.submitParams.address_id}&force_location=1`
				});
			},
			openCouponPopup() {
				this.popupShow = true;
			},
			selectCoupon(id) {
				this.submitParams.user_coupon_id = id;
				this.popupShow = false;
				this.getOrderPreview();
			},
			async submit() {
				// 同城配送:超范围/未达起送金额直接拦(与后端创建阶段同口径,防按钮被绕过)
				if (this.cityBlockReason) {
					return uni.showToast({ title: this.cityBlockReason, icon: 'none' });
				}
				// 快递/同城必须有地址;自提在「用户默认收货地址」模式下同样必须选地址(与快递一致,不做兜底)
				if ((this.deliveryType !== 'pickup' || this.pickupNeedsAddress) && !this.submitParams.address_id) {
					return uni.showToast({
						title: '请选择收货地址',
						icon: 'none'
					});
				}
				// 「用户选收货地址」模式下提货人由后端取自该地址,前端不填也不校验
				if (this.deliveryType === 'pickup' && !this.pickupNeedsAddress) {
					if (!this.submitParams.name || !this.submitParams.mobile) {
						return uni.showToast({
							title: '请填写提货人信息',
							icon: 'none'
						});
					}
					uni.setStorageSync('pickup_name', this.submitParams.name);
					uni.setStorageSync('pickup_mobile', this.submitParams.mobile);
				}
				// 订单已经建好(用户关过支付弹窗):只重新打开弹窗付同一张单,绝不再下一次单
				if (this.createdPay) {
					this.openPay(this.createdPay);
					return;
				}
				if (this.isSubmitting) return;
				this.isSubmitting = true;
				// 先把支付弹窗弹出来显示「正在创建订单」,建好后把支付单填进去 —— 用户感觉不到排队
				this.waitPay(this.$refs.paySheet.open({ pending: true }));
				try {
					const postData = this.buildRequestPayload();
					const res = await this.$request({
						url: '/api/order/submit',
						method: 'post',
						data: postData
					});
					if (res.code === 0) {
						if (res.data.pay_order_id && !res.data.queue_id) {
							this.onOrderCreated(res.data.pay_order_id);
						} else if (res.data.queue_id) {
							this.queueId = res.data.queue_id;
							this.startPolling();
						}
					} else if (res.code === -1) {
						// 未登录:走到结算页才发现没登录,只 toast 一句"请先登录"等于把人晾在这儿。
						// 给「返回 / 去登录」两个出口,并记住当前页,登录完能直接回来接着下单。
						this.$refs.paySheet.close();
						this.$http.promptLogin('登录后才能提交订单，现在去登录吗？');
						this.isSubmitting = false;
					} else {
						this.$refs.paySheet.fail(res.msg);
						this.isSubmitting = false;
					}
				} catch (e) {
					this.$refs.paySheet.fail('提交失败，请重试');
					this.isSubmitting = false;
				}
			},
			// 订单建好了:记下支付单(之后按钮变「继续付款」),把支付单交给弹窗
			onOrderCreated(payOrderId) {
				this.createdPay = { payOrderId };
				this.isSubmitting = false;
				this.$refs.paySheet.setPayOrder({ payOrderId });
			},
			openPay(pay) {
				this.waitPay(this.$refs.paySheet.open({ payOrderId: pay.payOrderId }));
			},
			// 弹窗只回结果不跳转,付完 / 关掉之后去哪都在这里定
			waitPay(promise) {
				promise.then(r => {
					if (r.status === 'paid') {
						const url = r.orderId
							? '/others/order-detail/order-detail?order_id=' + r.orderId
							: '/others/order/order?status=2';
						uni.redirectTo({ url, fail: () => uni.reLaunch({ url }) });
						return;
					}
					// 订单还没建出来就关了(下单失败 / 未登录):什么都不用做
					if (!this.createdPay) return;
					if (r.status === 'expired') {
						uni.redirectTo({ url: '/others/order/order' });
						return;
					}
					uni.showModal({
						title: '订单已保留',
						content: '可以稍后在「我的订单 · 待付款」里继续付款，超过付款时间订单会自动取消',
						confirmText: '继续付款',
						cancelText: '查看订单',
						success: m => {
							if (m.confirm) {
								this.openPay(this.createdPay);
							} else if (r.orderId) {
								uni.redirectTo({ url: '/others/order-detail/order-detail?order_id=' + r.orderId });
							} else {
								uni.redirectTo({ url: '/others/order/order?status=1' });
							}
						}
					});
				});
			},
			startPolling() {
				// 进度由支付弹窗的「正在创建订单」显示,不再盖全屏遮罩
				this.isPolling = false;
				this.pollingCount = 0;
				this.pollingText = '正在提交...';
				this.pollingStatus = 'pending';
				this.pollingTimer = setInterval(() => {
					this.pollingCount++;
					if (this.pollingCount > 20) {
						this.stopPolling();
						this.$refs.paySheet.close();
						uni.showToast({
							title: '处理超时，请稍后查看订单列表',
							icon: 'none'
						});
						// 原先跳的 /pages/order/index 不存在,订单列表在 others/order/order
						setTimeout(() => {
							uni.redirectTo({
								url: '/others/order/order'
							});
						}, 1500);
						return;
					}
					this.checkStatus();
				}, 1000);
			},
			stopPolling() {
				if (this.pollingTimer) clearInterval(this.pollingTimer);
				this.isPolling = false;
				this.isSubmitting = false;
			},
			async checkStatus() {
				try {
					const res = await this.$request({
						url: '/api/order/submit-status',
						data: {
							queue_id: this.queueId
						},
						hideLoading: true
					});
					if (res.code === 0) {
						if (res.data.status === 'success') {
							this.stopPolling();
							this.onOrderCreated(res.data.pay_order_id);
						} else if (res.data.status === 'fail') {
							this.stopPolling();
							this.$refs.paySheet.fail(res.data.error_msg || '下单失败');
						}
					} else if (res.code === -1) {
						// 轮询期间 token 失效。窗口很短但确实会发生,
						// 落到下面那个 toast 就是「请先登录!」一闪而过,订单到底成没成用户完全不知道
						this.stopPolling();
						this.$refs.paySheet.close();
						this.$http.promptLogin('登录状态已失效，请重新登录后在订单列表查看结果。');
					} else {
						this.stopPolling();
						this.$refs.paySheet.fail(res.msg);
					}
				} catch (e) {}
			},
			openLocation() {
				if (!this.orderInfo.mch || !this.orderInfo.mch.latitude) {
					uni.showToast({
						title: '位置信息不全',
						icon: 'none'
					});
					return;
				}
				uni.openLocation({
					latitude: parseFloat(this.orderInfo.mch.latitude),
					longitude: parseFloat(this.orderInfo.mch.longitude),
					name: this.orderInfo.mch.store_name || '商家地址',
					address: this.orderInfo.mch.address
				});
			},
			onDeliveryTimeChange(e) {
				const selectedIndexes = e.detail.value;
				const day = this.availableTimesData[selectedIndexes[0] || 0];
				if (day && day.slots[selectedIndexes[1] || 0]) {
					this.submitParams.delivery_time = `${day.label} ${day.slots[selectedIndexes[1] || 0].label}`;
					this.submitParams.is_immediately = 0;
				} else {
					this.submitParams.delivery_time = '';
					this.submitParams.is_immediately = 1;
				}
			},
			onDeliveryTimeColumnChange(e) {
				if (e.detail.column === 0) {
					const day = this.availableTimesData[e.detail.value];
					this.deliveryTimeRange.splice(1, 1, day && day.slots ? day.slots.map(s => s.label) : []);
				}
			},
			selectDeliveryTime(type) {
				this.submitParams.is_immediately = type;
				if (type === 1) this.submitParams.delivery_time = '';
			}
		}
	}
</script>

<style lang="scss">
	/* --- 全局修复：盒模型 --- */
	/* 关键：防止 padding 撑开宽度导致横向滚动 */
	view,
	text,
	image,
	input,
	scroll-view,
	button {
		box-sizing: border-box;
	}

	page {
		background-color: #F7F8FA;
	}

	.container {
		/* 底部留出空间给 footer，使用 padding-bottom */
		padding-bottom: 140rpx;
	}

	.main-content {
		padding-top: 20rpx;
	}

	/* 通用卡片 */
	.card-section {
		background: #fff;
		margin: 24rpx;
		/* 关键：宽度自适应，不使用 100% 避免溢出 */
		width: auto;
		border-radius: 20rpx;
		overflow: hidden;
		box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.02);
	}

	/* 配送方式TAB */
	.delivery-tabs-wrapper {
		padding: 0 24rpx;
		margin-bottom: 20rpx;
	}

	.delivery-tabs {
		display: flex;
		background: #fff;
		border-radius: 16rpx;
		padding: 8rpx;
		box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.02);
	}

	.tab-item {
		flex: 1;
		height: 72rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		position: relative;
		font-size: 28rpx;
		font-weight: 500;
		transition: all 0.3s;

		&.active {
			font-weight: bold;
		}

		.active-indicator {
			position: absolute;
			bottom: 6rpx;
			width: 28rpx;
			height: 4rpx;
			border-radius: 4rpx;
		}
	}

	/* 地址卡片 */
	.address-card {
		position: relative;

		&:active {
			background-color: #fcfcfc;
		}

		.address-content {
			padding: 30rpx;
			display: flex;
			align-items: center;
		}

		.addr-icon-box {
			width: 72rpx;
			height: 72rpx;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			margin-right: 24rpx;
			flex-shrink: 0;
		}

		.addr-info {
			flex: 1;
			margin-right: 20rpx;
		}

		.addr-detail {
			font-size: 30rpx;
			font-weight: bold;
			color: #333;
			line-height: 1.4;
			margin-bottom: 10rpx;
			display: -webkit-box;
			-webkit-box-orient: vertical;
			-webkit-line-clamp: 2;
			overflow: hidden;

			.tag {
				display: inline-block;
				font-size: 20rpx;
				background: #333;
				color: #fff;
				padding: 2rpx 8rpx;
				border-radius: 6rpx;
				margin-right: 8rpx;
				font-weight: normal;
				vertical-align: middle;
			}
		}

		.addr-user {
			font-size: 26rpx;
			color: #999;

			.name {
				margin-right: 20rpx;
			}
		}

		.addr-decoration {
			height: 6rpx;
			width: 100%;
			background-image: repeating-linear-gradient(45deg, #e4e7ed 0, #e4e7ed 20rpx, #fff 0, #fff 40rpx, #f56c6c 0, #f56c6c 60rpx, #fff 0, #fff 80rpx, #409eff 0, #409eff 100rpx, #fff 0, #fff 120rpx);
			background-size: 100% 100%;
			opacity: 0.6;
		}

		.empty-address {
			display: flex;
			align-items: center;
			width: 100%;

			.add-btn-icon {
				width: 48rpx;
				height: 48rpx;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				margin-right: 20rpx;
			}

			text {
				font-size: 28rpx;
				color: #333;
				font-weight: 500;
			}
		}
	}

	/* 自提 */
	.pickup-store-box {
		padding: 30rpx;

		.section-header {
			margin-bottom: 20rpx;

			.title {
				font-size: 28rpx;
				font-weight: bold;
				color: #333;
			}
		}

		.store-info-card {
			background: #f9f9f9;
			padding: 20rpx;
			border-radius: 12rpx;
		}

		.store-top {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-bottom: 12rpx;

			.name {
				font-size: 28rpx;
				font-weight: bold;
				color: #333;
			}

			.distance-tag {
				font-size: 22rpx;
				color: #666;
			}
		}

		.store-bottom {
			display: flex;
			align-items: center;

			.text {
				flex: 1;
				font-size: 24rpx;
				color: #666;
				margin: 0 10rpx;
			}
		}
	}

	.form-group {
		padding: 0 30rpx;
	}

	.form-item {
		display: flex;
		align-items: center;
		height: 96rpx;
		border-bottom: 1rpx solid #f5f5f5;

		&:last-child {
			border-bottom: none;
		}

		.label {
			width: 140rpx;
			font-size: 28rpx;
			color: #333;
		}

		.input {
			flex: 1;
			text-align: right;
			font-size: 28rpx;
			color: #333;
		}
	}

	/* 同城时间 */
	.time-card {
		padding: 30rpx;

		.card-header {
			margin-bottom: 20rpx;

			.title {
				font-size: 28rpx;
				font-weight: bold;
				color: #333;
			}
		}

		.time-selector {
			display: flex;
			gap: 20rpx;
		}

		.time-btn {
			flex: 1;
			height: 80rpx;
			border-radius: 12rpx;
			display: flex;
			align-items: center;
			justify-content: center;
			border: 2rpx solid transparent;
			transition: all 0.2s;

			.col-center {
				display: flex;
				flex-direction: column;
				align-items: center;
				line-height: 1.2;
			}

			.main-text {
				font-size: 26rpx;
				font-weight: 500;
			}

			.sub-text {
				font-size: 20rpx;
				margin-top: 4rpx;
			}
		}

		.time-picker {
			flex: 1;
		}
	}

	/* 商品卡片 */
	.goods-card {
		padding: 30rpx;

		.card-header {
			display: flex;
			align-items: center;
			padding-bottom: 20rpx;
			border-bottom: 1rpx solid #f5f5f5;

			.shop-name {
				font-size: 28rpx;
				font-weight: bold;
				margin-left: 10rpx;
				color: #333;
			}
		}

		.goods-item {
			display: flex;
			padding: 24rpx 0;

			.goods-img {
				width: 140rpx;
				height: 140rpx;
				border-radius: 10rpx;
				background: #f5f5f5;
				margin-right: 20rpx;
				flex-shrink: 0;
			}

			.goods-content {
				flex: 1;
				display: flex;
				flex-direction: column;
				justify-content: space-between;

				.goods-title {
					font-size: 26rpx;
					color: #333;
					line-height: 1.4;
					display: -webkit-box;
					-webkit-box-orient: vertical;
					-webkit-line-clamp: 2;
					overflow: hidden;
				}

				.goods-specs {
					font-size: 22rpx;
					color: #999;
					background: #f8f8f8;
					padding: 4rpx 10rpx;
					border-radius: 4rpx;
					align-self: flex-start;
				}

				.goods-price-row {
					display: flex;
					justify-content: space-between;
					align-items: flex-end;

					.price {
						font-size: 30rpx;
						font-weight: bold;

						.sym {
							font-size: 22rpx;
						}
					}

					.num {
						font-size: 24rpx;
						color: #999;
					}
				}
			}
		}

		.form-row {
			display: flex;
			align-items: center;
			padding-top: 20rpx;

			.label {
				font-size: 28rpx;
				color: #333;
				margin-right: 20rpx;
			}

			.input {
				flex: 1;
				font-size: 28rpx;
			}
		}
	}

	/* 费用明细 */
	.cost-card {
		padding: 30rpx;

		.cost-row {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 20rpx;
			font-size: 28rpx;
			color: #333;

			&.clickable:active {
				opacity: 0.7;
			}

			.value {
				font-weight: 500;
				font-family: sans-serif;
			}

			.right-col {
				display: flex;
				align-items: center;
				justify-content: flex-end;
				flex: 1;
			}

			.gray-text {
				color: #999;
				font-size: 26rpx;
			}

			/* 解决换行问题 Flex + nowrap */
			.coupon-tag,
			.discount-val {
				display: flex;
				align-items: baseline;
				white-space: nowrap;
				font-weight: 500;
			}

			.coupon-tag {
				padding: 2rpx 10rpx;
				border-radius: 6rpx;
				border: 1rpx solid;

				.minus {
					margin-right: 2rpx;
					font-size: 24rpx;
				}

				.sym {
					font-size: 22rpx;
				}

				.num {
					font-size: 26rpx;
				}
			}

			.discount-val {
				.minus {
					margin-right: 2rpx;
				}
			}

			.row-left {
				display: flex;
				flex-direction: column;

				.tips {
					font-size: 22rpx;
					color: #999;
					margin-top: 4rpx;
				}
			}
		}

		.total-divider {
			height: 1rpx;
			background: #f0f0f0;
			margin: 24rpx 0;
		}

		.final-total {
			display: flex;
			justify-content: flex-end;
			align-items: baseline;

			.label {
				font-size: 28rpx;
				color: #333;
				margin-right: 12rpx;
			}

			.price-box {
				font-weight: bold;

				.sym {
					font-size: 28rpx;
				}

				.num {
					font-size: 38rpx;
					font-family: sans-serif;
				}
			}
		}
	}

	/* 底部栏 (优化版) */
	.footer-bar {
		position: fixed;
		bottom: 0;
		left: 0;
		width: 100%;
		background: #fff;
		/* 顶部微阴影 */
		box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.03);
		z-index: 999;
	}

	/* 高度和 tui-tabbar 一致:固定 120rpx,不再额外加底部安全区(用户认定的高度,见 5fa9a32cf2) */
	.footer-content {
		height: 120rpx;
		/* 固定高度，去空隙 */
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 0 30rpx;
	}

	.price-container {
		display: flex;
		align-items: baseline;

		.label {
			font-size: 26rpx;
			color: #333;
		}

		.price-num {
			margin-left: 10rpx;
			font-weight: bold;

			.sym {
				font-size: 28rpx;
			}

			.num {
				font-size: 44rpx;
				font-family: sans-serif;
			}
		}
	}

	.submit-btn {
		margin: 0;
		padding: 0 40rpx;
		height: 76rpx;
		line-height: 76rpx;
		border-radius: 38rpx;
		color: #fff;
		font-size: 28rpx;
		font-weight: bold;
		/* 按钮阴影由 :style 跟随主题色控制(原硬编码灰阴影不随主题变) */

		&:active {
			opacity: 0.9;
			transform: scale(0.98);
		}

		&[disabled] {
			opacity: 0.6;
			background: #ccc !important;
		}
	}

	/* 同城配送拦截提示(超范围 / 未达起送),语义 danger 色 */
	.city-block-tip {
		display: flex;
		align-items: center;
		gap: 8rpx;
		margin: 16rpx 24rpx 0;
		padding: 16rpx 20rpx;
		background: #FEF0F0;
		border-radius: 12rpx;
		font-size: 24rpx;
		color: #F12F2F;
	}

	.city-block-tip .city-block-text {
		flex: 1;
	}

	/* 「去设置定位」可点入口:同城缺坐标时就地补坐标,避免死循环 */
	.city-block-tip .city-block-action {
		flex-shrink: 0;
		padding: 4rpx 16rpx;
		border: 1rpx solid #F12F2F;
		border-radius: 24rpx;
		font-weight: 600;
		white-space: nowrap;
	}

	/* 弹窗样式 */
	.popup-wrap {
		background: #f7f8fa;
		border-radius: 24rpx 24rpx 0 0;
		overflow: hidden;

		.popup-header {
			background: #fff;
			padding: 30rpx;
			text-align: center;
			font-size: 30rpx;
			font-weight: bold;
			position: relative;

			.close-btn {
				position: absolute;
				right: 30rpx;
				top: 30rpx;
			}
		}

		.popup-scroll {
			height: 600rpx;
			padding: 24rpx;
			box-sizing: border-box;
		}

		.no-use-cell {
			background: #fff;
			border-radius: 16rpx;
			padding: 24rpx 30rpx;
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 24rpx;
			font-size: 28rpx;
		}

		.coupon-card {
			display: flex;
			background: #fff;
			border-radius: 16rpx;
			overflow: hidden;
			margin-bottom: 24rpx;
			box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.02);

			.card-left {
				width: 180rpx;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				position: relative;

				&::after {
					content: '';
					position: absolute;
					right: -10rpx;
					top: 50%;
					transform: translateY(-50%);
					height: 20rpx;
					width: 20rpx;
					background: #f7f8fa;
					border-radius: 50%;
				}

				.price {
					font-weight: bold;
					font-size: 30rpx;

					.num {
						font-size: 48rpx;
						font-family: sans-serif;
					}

					.sym {
						font-size: 24rpx;
						margin: 0 4rpx;
					}
				}

				.cond {
					font-size: 20rpx;
					opacity: 0.8;
				}
			}

			.card-right {
				flex: 1;
				padding: 24rpx 30rpx;
				display: flex;
				justify-content: space-between;
				align-items: center;

				.cp-name {
					font-size: 28rpx;
					font-weight: bold;
					color: #333;
					margin-bottom: 8rpx;
				}

				.cp-date {
					font-size: 22rpx;
					color: #999;
				}
			}
		}

		.radio-circle {
			width: 36rpx;
			height: 36rpx;
			border: 2rpx solid #ccc;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			flex-shrink: 0;

			&.active {
				border-color: currentColor;
			}

			.dot {
				width: 20rpx;
				height: 20rpx;
				border-radius: 50%;
			}
		}
	}

	.divider-line {
		height: 1rpx;
		background: #f5f5f5;
		margin: 20rpx 0;
	}

	.ph {
		color: #ccc;
	}

	.polling-mask {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background: rgba(0, 0, 0, 0.4);
		z-index: 9999;
		display: flex;
		align-items: center;
		justify-content: center;

		.polling-card {
			background: #fff;
			padding: 40rpx 60rpx;
			border-radius: 20rpx;
			display: flex;
			flex-direction: column;
			align-items: center;

			.polling-subtext {
				margin-top: 24rpx;
				font-size: 26rpx;
				color: #666;
			}
		}
	}
</style>