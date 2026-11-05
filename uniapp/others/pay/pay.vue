<template>
	<tui-page :backgroundColor="'#F7F8FA'">
		<view class="payment-container" :style="{ '--theme-color': themeColor }">

			<!-- #ifndef H5 -->
			<view class="custom-navbar" :class="{ 'dark-mode': true }"
				:style="{ paddingTop: navBar.top + 'px', height: navBar.height + 'px' }">
				<view class="nav-btn" :style="{ height: navBar.height + 'px', width: navBar.height + 'px' }"
					@tap="handleNavBack">
					<view class="icon-back" v-if="hasHistory"></view>
					<view class="icon-home" v-else>
						<view class="roof"></view>
						<view class="body"></view>
					</view>
				</view>
			</view>
			<!-- #endif -->

			<view class="header-bg">
				<view class="header-content" :style="{ paddingTop: (navBar.top + navBar.height + 12) + 'px' }">
					<view class="title-row">
						<text class="page-title">收银台</text>
						<view class="security-badge"
							:style="{ background: hexToRgba(themeColor, 0.1), color: themeColor }">
							<text class="lock-icon" :style="{ borderColor: themeColor }"></text>
							<text>加密传输</text>
						</view>
					</view>
					<text class="page-desc">金融级支付保障 · 资金即时结清</text>
				</view>
			</view>

			<view class="content-wrapper fade-in-up" :style="{ paddingTop: contentPaddingTop + 'px' }">
				<view class="order-card">
					<view class="card-top">
						<view class="amount-label">支付金额</view>
						<view class="amount-section" :style="{ color: themeColor }">
							<text class="currency">¥</text>
							<text class="amount">{{ formattedPrice[0] }}</text>
							<text class="decimal">.{{ formattedPrice[1] }}</text>
						</view>
					</view>

					<view class="ticket-divider">
						<view class="circle-notch left"></view>
						<view class="dashed-line"></view>
						<view class="circle-notch right"></view>
					</view>

					<view class="card-bottom">
						<view class="detail-row">
							<text class="label">订单编号</text>
							<text class="value">{{ order ? order.order_no : '---' }}</text>
						</view>
						<view class="detail-row">
							<text class="label">下单时间</text>
							<text class="value">{{ order ? order.created_time : '---' }}</text>
						</view>
					</view>
				</view>

				<view class="payment-group">
					<view class="group-header">
						<text class="group-title">选择支付方式</text>
					</view>

					<view class="method-list">
						<view class="method-item" v-for="(item, index) in paymentMethods" :key="index"
							:class="{ 'is-selected': selectedPayment === item.payment_type }"
							@tap="selectPay(item.payment_type)">

							<view class="method-left">
								<image :src="item.logo" mode="aspectFit" class="pay-icon"></image>
								<view class="pay-info">
									<view class="pay-name-row">
										<text class="pay-name">{{ item.name }}</text>
										<text class="pay-tag" v-if="index === 0">推荐</text>
									</view>
									<text v-if="item.payment_type.includes('BalancePayment') && userInfo"
										class="balance-text">
										可用{{ $noun('balance') }}: ¥{{ userInfo.money }}
									</text>
									<text v-if="item.quick_pay && selectedPayment === item.payment_type && selectedBankCardId"
										class="balance-text" @tap.stop="openBankCardModal">
										{{ currentBankCardLabel }} · 切换
									</text>
								</view>
							</view>

							<view class="radio-box" :style="{
								borderColor: selectedPayment === item.payment_type ? themeColor : '#E4E7ED',
								backgroundColor: selectedPayment === item.payment_type ? themeColor : 'transparent'
							}">
								<view class="check-mark" v-if="selectedPayment === item.payment_type"></view>
							</view>
						</view>
					</view>
				</view>

				<view class="security-tips">
					<text class="safe-shield"></text>
					<text>平台担保 · 支付安全无忧</text>
				</view>

				<view class="bottom-spacer"></view>
			</view>

			<view class="footer-bar">
				<view class="footer-inner">
					<button class="pay-btn" :style="{ background: themeColor, boxShadow: btnShadow }"
						@click="handlePayment" hover-class="btn-hover">
						<text>立即支付</text>
						<text class="btn-divider">|</text>
						<text class="btn-price">¥{{ order ? order.pay_price : '0.00' }}</text>
					</button>
				</view>
			</view>

			<view class="pwd-mask" v-if="showPasswordModal" @touchmove.stop.prevent>
				<view class="pwd-modal fade-in">
					<view class="modal-title">请输入支付密码</view>
					<view class="modal-content">
						<input class="pwd-input" type="number" password="true" maxlength="6" focus="true"
							placeholder="请输入6位数字密码" v-model="payPassword" />
					</view>
					<view class="modal-btns">
						<view class="btn cancel" @click="closePasswordModal">取消</view>
						<view class="btn confirm" :style="{ color: themeColor }" @click="confirmPasswordPay">确定</view>
					</view>
				</view>
			</view>

			<!-- 快捷支付短信验证弹窗 -->
			<view class="pwd-mask" v-if="showSmsModal" @touchmove.stop.prevent>
				<view class="pwd-modal fade-in">
					<view class="modal-title">请输入短信验证码</view>
					<view class="modal-content">
						<view style="font-size: 24rpx; color: #888; text-align: center; margin-bottom: 20rpx;">
							验证码已发送至 {{ smsMobileMask || '预留手机号' }}
						</view>
						<input class="pwd-input" type="number" maxlength="6" focus="true"
							placeholder="请输入短信验证码" v-model="smsCode" />
					</view>
					<view class="modal-btns">
						<view class="btn cancel" @click="closeSmsModal">取消</view>
						<view class="btn confirm" :style="{ color: themeColor }" @click="confirmPaySms">确定</view>
					</view>
				</view>
			</view>

			<!-- 银行卡快捷支付: 选卡弹窗 -->
			<view class="pwd-mask" v-if="showBankCardModal" @touchmove.stop.prevent>
				<view class="pwd-modal fade-in" style="width: 620rpx; padding-bottom: 16rpx;">
					<view class="modal-title">选择支付银行卡</view>
					<view class="modal-content" style="max-height: 560rpx; overflow-y: auto;">
						<view v-if="bankCardsLoading" style="text-align:center;padding:40rpx 0;color:#999;">加载中...</view>
						<view v-else-if="bankCards.length === 0" style="text-align:center;padding:40rpx 0;color:#999;">
							暂无可用银行卡
						</view>
						<view v-else>
							<view v-for="card in bankCards" :key="card.id"
								@tap="pickBankCard(card)"
								:style="{
									display:'flex', alignItems:'center', justifyContent:'space-between',
									padding:'24rpx 20rpx', marginBottom:'16rpx', borderRadius:'16rpx',
									border: '2rpx solid ' + (selectedBankCardId === card.id ? themeColor : '#EEE')
								}">
								<view style="flex:1;">
									<view style="font-size:28rpx;color:#222;font-weight:600;">
										{{ card.bank_name }} · {{ card.card_type_label }}
									</view>
									<view style="font-size:24rpx;color:#888;margin-top:8rpx;">
										尾号 {{ card.bank_card_last4 }} · {{ card.mobile_mask }}
									</view>
								</view>
								<text v-if="card.is_default === 1"
									:style="{ fontSize:'22rpx', color: themeColor, marginLeft:'16rpx' }">默认</text>
							</view>
						</view>
					</view>
					<view class="modal-btns">
						<view class="btn cancel" @click="closeBankCardModal">取消</view>
						<view class="btn confirm" :style="{ color: themeColor }" @click="goBindBankCard">绑定新卡</view>
					</view>
				</view>
			</view>

			<view class="result-mask" v-if="paymentResult.show" :class="{ show: paymentResult.show }"
				@touchmove.stop.prevent>
				<view class="result-card">
					<view class="icon-area" :class="paymentResult.status">
						<view class="circle-bg"></view>
						<view class="icon-check" v-if="paymentResult.status === 'success'"></view>
						<view class="icon-cross" v-if="paymentResult.status === 'error'">
							<view class="left"></view>
							<view class="right"></view>
						</view>
					</view>

					<view class="text-area">
						<view class="main-title">{{ paymentResult.title }}</view>
						<view class="sub-msg">{{ paymentResult.msg }}</view>
					</view>

					<button class="confirm-btn"
						:style="{ background: themeColor, boxShadow: '0 10rpx 20rpx ' + hexToRgba(themeColor, 0.3) }"
						@tap="handleResultConfirm">
						{{ paymentResult.btnText }}
					</button>
				</view>
			</view>

		</view>
	</tui-page>
</template>

<script>
import payment from '@/utils/payment.js';
import wxAuth from '@/utils/wxAuth.js';
import {
	mapState
} from 'vuex';

export default {
	data() {
		return {
			order: null,
			pay_order_id: '',
			order_no: '',
			redirect_url: '',
			selectedPayment: '',
			paymentMethods: [],
			hasHistory: false,
			navBar: {
				top: 20,
				height: 32
			},
			// 用户信息 (用于余额显示)
			userInfo: null,
			// 支付密码相关
			needPayPwd: false,
			showPasswordModal: false,
			payPassword: '',
			// 发起支付中：拦住连点。showLoading 的 mask 只是遮罩不是锁，
			// 重复发起会在后端反复重算 out_trade_no，生成多个都能付款的预支付单
			isPaying: false,
			// 支付结果展示数据
			paymentResult: {
				show: false,
				status: 'success', // success | error
				title: '',
				msg: '',
				btnText: '完成'
			},
			resultTimer: null,
			// [优化] 移除 wxCode 变量，改为实时获取，不再需要 data 中存储
			h5OpenId: '',
			// --- 银行卡快捷支付相关 ---
			// 选中的已绑卡记录 id (user_bank_card.id), 传给 /api/pay/pay-data 后端查对应 bindCardId
			selectedBankCardId: 0,
			bankCards: [],
			bankCardsLoading: false,
			showBankCardModal: false,
			// 快捷支付短信验证相关
			showSmsModal: false,
			smsCode: '',
			smsOrderNo: '',
			smsMobileMask: '',
			smsSubmitting: false,
		}
	},
	computed: {
		...mapState('mall', {
			themeColor: state => state.themeColor || '#E63D3D'
		}),
		formattedPrice() {
			const price = this.order ? String(this.order.pay_price) : '0.00';
			const parts = price.split('.');
			return [parts[0], parts[1] || '00'];
		},
		btnShadow() {
			return `0 12rpx 32rpx -8rpx ${this.hexToRgba(this.themeColor, 0.4)}`;
		},
		contentPaddingTop() {
			return this.navBar.top + this.navBar.height + 84;
		},
		currentBankCardLabel() {
			const card = this.bankCards.find(c => c.id === this.selectedBankCardId);
			if (!card) return '已选卡';
			return (card.bank_name || '银行卡') + '(' + (card.bank_card_last4 || '') + ')';
		}
	},
	onLoad(options) {
		this.initNavBar();
		if (options.pay_order_id) {
			this.pay_order_id = options.pay_order_id
		}
		if (options.order_no) {
			this.order_no = options.order_no
		}
		if (options.redirect_url) {
			// 小程序 onLoad options 不会自动解码 encodeURIComponent 过的 query 值（H5 会），
			// 导致 redirect_url 保留 %2F/%3F/%26 被 uni.redirectTo 当成相对路径。这里补一次 decode 兼容两端。
			let rawRedirect = options.redirect_url;
			try { rawRedirect = decodeURIComponent(rawRedirect); } catch (e) { /* 已解码则忽略 */ }
			this.redirect_url = rawRedirect;
		}

		// --- [H5 微信环境：openid 由 wxAuth 统一管理(App.vue 入口已复核)，这里只读结果，不触发跳转] ---
		// #ifdef H5
		wxAuth.ready().then(openid => {
			this.h5OpenId = openid || wxAuth.getOpenid();
		});
		// #endif
	},
	onShow() {
		const pages = getCurrentPages();
		this.hasHistory = pages.length > 1;

		// [优化] 移除 onShow 中的 getMpCode，改为点击支付时实时获取

		this.getOrder();
		this.getPayment();
		this.getUserInfo();
		this.getSetting();
	},
	methods: {
		// 微信 H5 openid 统一由 utils/wxAuth.js 管理（snsapi_base 静默 + 按 mall_id 缓存）

		// --- 基础配置方法 ---

		initNavBar() {
			const sysInfo = uni.getSystemInfoSync();
			// #ifdef MP
			try {
				const menuBtnInfo = uni.getMenuButtonBoundingClientRect();
				if (menuBtnInfo && menuBtnInfo.top) {
					this.navBar = {
						top: menuBtnInfo.top,
						height: menuBtnInfo.height
					};
				} else {
					this.navBar = {
						top: sysInfo.statusBarHeight || 20,
						height: 32
					};
				}
			} catch (e) {
				this.navBar = {
					top: sysInfo.statusBarHeight || 20,
					height: 32
				};
			}
			// #endif
			// #ifdef H5
			this.navBar = {
				top: 10,
				height: 0
			};
			// #endif
		},
		handleNavBack() {
			if (this.hasHistory) {
				uni.navigateBack({
					delta: 1
				});
			} else {
				uni.switchTab({
					url: '/pages/index/index',
					fail: () => uni.reLaunch({
						url: '/pages/index/index'
					})
				});
			}
		},
		hexToRgba(hex, opacity) {
			let c;
			if (/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)) {
				c = hex.substring(1).split('');
				if (c.length == 3) {
					c = [c[0], c[0], c[1], c[1], c[2], c[2]];
				}
				c = '0x' + c.join('');
				return 'rgba(' + [(c >> 16) & 255, (c >> 8) & 255, c & 255].join(',') + ',' + opacity + ')';
			}
			return 'rgba(0,0,0,0.1)';
		},
		_redirectToSuccessPage() {
			const url = this.redirect_url || "/others/user/user";
			uni.redirectTo({
				url: url,
				fail: (err) => {
					console.warn("[pay] redirectTo failed, fallback to reLaunch:", err);
					// 小程序 wx.redirectTo 对带 %XX 编码或复杂 URL 校验严格，失败时用 reLaunch 兜底
					uni.reLaunch({ url: url });
				}
			});
		},
		selectPay(type) {
			// 切换支付方式时清掉上一次的快捷支付选卡, 避免用 A 网关的卡走 B 网关
			if (this.selectedPayment !== type) {
				this.selectedBankCardId = 0;
				this.bankCards = [];
			}
			this.selectedPayment = type;
		},
		getUserInfo() {
			this.$request({
				url: '/api/user/index',
			}).then(res => {
				if (res.code === 0) {
					this.userInfo = res.data.user;
				}
			});
		},
		getSetting() {
			this.$request({
				url: '/api/mall/setting',
			}).then(res => {
				if (res.code === 0) {
					// 商城级"余额支付是否强制验密"开关, 由 admin/mall/index.vue 的 is_balance_pay_password 控制
				this.needPayPwd = res.data.setting.is_balance_pay_password == 1;
				}
			});
		},
		getOrder() {
			let reqData = {};
			if (this.pay_order_id) reqData.pay_order_id = this.pay_order_id;
			if (this.order_no) reqData.order_no = this.order_no;

			this.$request({
				url: "/api/pay/order",
				data: reqData
			}).then(res => {
				if (res.code == 0) {
					this.order = res.data.order;
				} else {
					if (res.data.is_pay == 1) {
						this.showPaymentResult('success', '已支付', '该订单已经支付成功');
					} else {
						uni.showModal({
							title: '提示',
							content: res.msg,
							showCancel: false,
							success: () => uni.navigateBack()
						})
					}
				}
			})
		},

		// --- 支付核心流程 ---

		handlePayment() {
			if (!this.selectedPayment) {
				uni.showToast({
					title: '请选择支付方式',
					icon: 'none'
				});
				return;
			}

			// 余额支付 + 商城开启验密: 先判"有没有支付密码", 没有 → 引导到设置中心
			// 有 → 弹输入密码弹窗; 关掉验密开关则走原流程(和之前一样, 不强制)
			if (this.selectedPayment.includes('BalancePayment') && this.needPayPwd) {
				if (this.userInfo && this.userInfo.has_pay_pwd === 0) {
					this.promptGoSetPayPwd();
					return;
				}
				this.openPasswordModal();
				return;
			}

			// 银行卡快捷支付: 必须先选一张已绑卡才能下单
			// 未选网关中的任何一张时, 拉列表并弹选卡框; 用户选了卡后再真正 requestPay
			const selectedItem = this.paymentMethods.find(p => p.payment_type === this.selectedPayment);
			if (selectedItem && selectedItem.quick_pay && !this.selectedBankCardId) {
				this.openBankCardModal();
				return;
			}

			this.requestPay();
		},

		// --- 银行卡快捷支付: 选卡弹窗 ---
		openBankCardModal() {
			this.showBankCardModal = true;
			this.loadBankCards();
		},
		closeBankCardModal() {
			this.showBankCardModal = false;
		},
		loadBankCards() {
			if (!this.selectedPayment) return;
			this.bankCardsLoading = true;
			this.$request({
				url: '/api/bank-card/list',
				method: 'get',
				data: { gateway: this.selectedPayment },
			}).then(res => {
				this.bankCardsLoading = false;
				if (res.code === 0) {
					this.bankCards = res.data || [];
					// 无卡可用 → 直接把用户送到绑卡页, 带 redirect 回本页
					if (this.bankCards.length === 0) {
						this.showBankCardModal = false;
						this.goBindBankCard();
						return;
					}
					// 用户当前已选中的卡是否仍然存在(例如刚才走换卡/解绑), 不存在则丢弃
					if (this.selectedBankCardId && !this.bankCards.find(c => c.id === this.selectedBankCardId)) {
						this.selectedBankCardId = 0;
					}
					// 仅当尚未选卡时自动预选默认卡, 避免"切换"二次打开模态框时
					// 把用户之前主动挑的非默认卡覆盖掉
					if (!this.selectedBankCardId) {
						const def = this.bankCards.find(c => c.is_default === 1);
						if (def) this.selectedBankCardId = def.id;
					}
				}
			}).catch(() => {
				this.bankCardsLoading = false;
			});
		},
		pickBankCard(card) {
			this.selectedBankCardId = card.id;
			this.showBankCardModal = false;
			// 用户主动选卡后直接继续支付流程
			this.requestPay();
		},
		goBindBankCard() {
			// 去绑卡之前清掉本地选卡状态: 用户回来时应该看到"刚绑的新卡",
			// 而不是沿用旧选择, 否则可能用旧卡支付
			this.selectedBankCardId = 0;
			this.bankCards = [];
			this.showBankCardModal = false;

			// 回跳 URL 保留当前订单号/pay_order_id, 绑卡成功后回到收银台继续支付
			const backQuery = [];
			if (this.order_no) backQuery.push('order_no=' + encodeURIComponent(this.order_no));
			if (this.pay_order_id) backQuery.push('pay_order_id=' + encodeURIComponent(this.pay_order_id));
			if (this.redirect_url) backQuery.push('redirect_url=' + encodeURIComponent(this.redirect_url));
			const backUrl = '/others/pay/pay' + (backQuery.length ? '?' + backQuery.join('&') : '');
			uni.navigateTo({
				url: '/others/bank-card/bank-card-bind'
					+ '?gateway=' + encodeURIComponent(this.selectedPayment)
					+ '&redirect=' + encodeURIComponent(backUrl)
			});
		},

		// --- 快捷支付短信验证 ---
		closeSmsModal() {
			this.showSmsModal = false;
			this.smsCode = '';
		},
		confirmPaySms() {
			if (this.smsSubmitting) return;
			if (!this.smsCode || this.smsCode.length < 4) {
				uni.showToast({ title: '请输入短信验证码', icon: 'none' });
				return;
			}
			this.smsSubmitting = true;
			uni.showLoading({ title: '验证中...', mask: true });
			this.$request({
				url: '/api/bank-card/verify-pay',
				method: 'post',
				data: {
					order_no: this.smsOrderNo,
					smsCode: this.smsCode,
				},
			}).then(res => {
				uni.hideLoading();
				this.smsSubmitting = false;
				if (res.code === 0) {
					if (res.data && res.data.is_pay === 1) {
						this.showSmsModal = false;
						this.showPaymentResult('success', '支付成功', '订单支付完成');
					} else {
						uni.showToast({ title: res.msg || '支付处理中', icon: 'none' });
					}
				} else {
					uni.showToast({ title: res.msg || '验证失败', icon: 'none' });
				}
			}).catch(() => {
				uni.hideLoading();
				this.smsSubmitting = false;
				uni.showToast({ title: '请求失败', icon: 'none' });
			});
		},
		openPasswordModal() {
			this.payPassword = '';
			this.showPasswordModal = true;
		},
		closePasswordModal() {
			this.showPasswordModal = false;
			this.payPassword = '';
		},
		confirmPasswordPay() {
			if (!this.payPassword || this.payPassword.length !== 6) {
				uni.showToast({
					title: '请输入6位支付密码',
					icon: 'none'
				});
				return;
			}
			this.requestPay(this.payPassword);
			this.closePasswordModal();
		},

		// 引导用户去设置支付密码 (未设置时触发)
		// setting.vue 接收 ?action=pay_pwd 会自动展开支付密码抽屉
		promptGoSetPayPwd() {
			uni.showModal({
				title: '请先设置支付密码',
				content: '您还未设置支付密码，完成设置后可返回本页继续支付',
				confirmText: '去设置',
				cancelText: '取消',
				success: (res) => {
					if (res.confirm) {
						uni.navigateTo({ url: '/others/setting/setting?action=pay_pwd' });
					}
				}
			});
		},

		showPaymentResult(status, title, msg) {
			this.paymentResult = {
				show: true,
				status: status,
				title: title,
				msg: msg,
				btnText: status === 'success' ? '完成' : '返回'
			};
			if (status === 'success') {
				this.resultTimer = setTimeout(() => {
					this.handleResultConfirm();
				}, 2500);
			}
		},
		handleResultConfirm() {
			if (this.resultTimer) clearTimeout(this.resultTimer);
			if (this.paymentResult.status === 'success') {
				this.paymentResult.show = false;
				setTimeout(() => {
					this._redirectToSuccessPage();
				}, 200);
			} else {
				this.paymentResult.show = false;
			}
		},

		// [核心修改] 发起支付请求
		requestPay(password = '') {
			// 连点闸门：每次发起后端都会重算 out_trade_no，并发进来会生成多个
			// 都能付款的预支付单（旧号回调对不上 + 有重复支付风险）。
			// 后端 PayForm::payData 也按支付单 ID 上了锁，这里是第一道。
			if (this.isPaying) return;
			this.isPaying = true;

			uni.showLoading({
				title: '正在支付...',
				mask: true
			})

			// 请求已落地（成功/失败都算），放开闸门让用户可以重试或换支付方式
			const endPaying = () => {
				this.isPaying = false;
			};

			// 定义实际发送请求的函数
			const sendRequest = (freshCode = '') => {
				// 构造支付参数
				let data = {
					payment_type: this.selectedPayment,
					pay_password: password
				};
				if (this.pay_order_id) data.pay_order_id = this.pay_order_id;
				if (this.order_no) data.order_no = this.order_no;
				// 银行卡快捷支付: 把用户在选卡弹窗里挑的 user_bank_card.id 透传给后端
				// 后端 PayForm 会按 $payment instanceof QuickPaymentInterface 分支处理
				if (this.selectedBankCardId) data.bind_card_id = this.selectedBankCardId;

				// 如果是小程序环境，使用传入的最新 code
				if (freshCode) {
					data.code = freshCode;
				}

				// #ifdef H5
				// 微信 JSAPI 类支付必须带【当前微信号】openid：缺失则不下单，触发复核后重试，
				// 杜绝后端兜底用 token 里的旧 openid（切号串号）。其它支付方式不受影响。
				if (/mpwx|jsapi|wechat/i.test(this.selectedPayment)) {
					if (!this.h5OpenId) {
						uni.hideLoading();
						endPaying();
						wxAuth.ensure(); // 微信内跳 snsapi_base 复核当前号；回到本页后 openid 就绪
						uni.showToast({ title: '正在准备微信支付，请重试', icon: 'none' });
						return;
					}
					data.openid = this.h5OpenId;
				}
				// #endif

				this.$request({
					url: "/api/pay/pay-data",
					data: data,
					method: "post"
				}).then(res => {
					uni.hideLoading();
					endPaying();
					if (res.code == 1) {
						uni.showModal({
							title: '支付提示',
							content: res.msg,
							showCancel: false
						});
						return;
					}
					if (res.code == 0) {
						if (res.data.is_pay == 1) {
							this.showPaymentResult('success', '支付成功', '订单支付完成');
							return;
						}
						// 后端兜底: 开了商城验密但用户尚未设置支付密码 (前端缓存可能过期或被绕过)
						if (res.data.need_set_pay_password === 1) {
							this.promptGoSetPayPwd();
							return;
						}
						// 快捷支付 DOING 状态: 后端约定返回 need_sms=1 + order_no + mobile_mask
						// 弹出短信验证码输入框, 用户提交后调 /api/bank-card/verify-pay 完成扣款
						// 必须排在 msg 分支之前, 否则 "短信验证码已下发" 提示会被当成 error 展示
						if (res.data.need_sms === 1) {
							this.smsOrderNo = res.data.order_no || '';
							this.smsMobileMask = res.data.mobile_mask || '';
							this.smsCode = '';
							this.showSmsModal = true;
							return;
						}
						// 后端同步失败(如余额不足、快付下单风控)仍然走 code=0 + data.msg 的约定
						// 必须在 payment.pay() SDK 分支之前拦截, 否则会退化成通用 "支付失败" 文案
						if (res.data.is_pay == 0 && res.data.msg) {
							this.showPaymentResult('error', '支付失败', res.data.msg);
							return;
						}
						if (res.data.is_app_call_mp === 1) {
							// #ifdef APP-PLUS
							const mpData = res.data.app_call_mp_data;
							plus.share.getServices(res => {
								let sweixin = res.find(i => i.id === 'weixin');
								if (sweixin) {
									sweixin.launchMiniProgram({
										id: mpData.userName,
										path: mpData.path,
										type: mpData.miniprogramType
									});
								}
							});
							// #endif
							return;
						}
						payment.pay(res.data, this.selectedPayment).then(() => {
							this.showPaymentResult('success', '支付成功', '交易已完成');
						}).catch(e => {
							const isCancel = e && e.errMsg && e.errMsg.includes('cancel');
							if (isCancel) {
								uni.showToast({
									title: '支付已取消',
									icon: 'none'
								});
							} else {
								this.showPaymentResult('error', '支付失败', '支付过程中遇到问题，请重试');
							}
						});
					}
				}).catch(() => {
					uni.hideLoading();
					endPaying();
					uni.showToast({
						title: '请求失败',
						icon: 'none'
					});
				})
			};

			// --- [关键修改] 如果是微信小程序，先获取最新 Code，再请求 ---
			// #ifdef MP-WEIXIN
			uni.login({
				provider: 'weixin',
				success: (loginRes) => {
					if (loginRes.code) {
						console.log('Refresh MP Code:', loginRes.code);
						sendRequest(loginRes.code); // 获取成功，带着新 Code 去请求
					} else {
						uni.hideLoading();
						endPaying();
						uni.showToast({
							title: '获取登录凭证失败',
							icon: 'none'
						});
					}
				},
				fail: (err) => {
					uni.hideLoading();
					endPaying();
					uni.showToast({
						title: '微信登录失败',
						icon: 'none'
					});
				}
			});
			// #endif

			// --- 非小程序环境 (H5, App)，直接请求 ---
			// #ifndef MP-WEIXIN
			sendRequest();
			// #endif
		},

		getPayment() {
			// 带上订单标识 —— 后端据此过滤掉「不接这类订单」的支付方式
			// (比如余额支付可以配成不接受余额充值单)。不传也能用,那时后端按不限处理,
			// 真正的把关在下单接口那一步。
			const data = {};
			if (this.pay_order_id) data.pay_order_id = this.pay_order_id;
			else if (this.order_no) data.order_no = this.order_no;

			this.$request({
				url: "/api/pay/payment",
				data: data
			}).then(res => {
				if (res.code == 0 && res.data && res.data.length > 0) {
					this.paymentMethods = res.data;
					this.selectedPayment = this.paymentMethods[0].payment_type;
				}
			})
		}
	}
}
</script>

<style lang="scss" scoped>
.payment-container {
	min-height: 100vh;
	position: relative;
	padding-bottom: env(safe-area-inset-bottom);
}

/* --- 导航栏 --- */
.custom-navbar {
	position: fixed;
	left: 0;
	top: 0;
	width: 100%;
	z-index: 999;
	padding-left: 30rpx;
	display: flex;
	align-items: center;

	.nav-btn {
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		background: rgba(255, 255, 255, 0.9);
		border: 1rpx solid rgba(0, 0, 0, 0.08);

		&:active {
			opacity: 0.7;
			transform: scale(0.95);
		}
	}

	.icon-back {
		width: 18rpx;
		height: 18rpx;
		border-left: 4rpx solid #333;
		border-bottom: 4rpx solid #333;
		transform: rotate(45deg);
		margin-left: 6rpx;
	}

	.icon-home {
		display: flex;
		flex-direction: column;
		align-items: center;

		.roof {
			width: 0;
			height: 0;
			border-left: 14rpx solid transparent;
			border-right: 14rpx solid transparent;
			border-bottom: 12rpx solid #333;
		}

		.body {
			width: 18rpx;
			height: 14rpx;
			background: #333;
			position: relative;

			&::after {
				content: '';
				width: 8rpx;
				height: 10rpx;
				background: #fff;
				position: absolute;
				bottom: 0;
				left: 50%;
				transform: translateX(-50%);
			}
		}
	}
}

/* --- 顶部背景 --- */
.header-bg {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 600rpx;
	background: linear-gradient(180deg, #FFFFFF 0%, #F5F7FA 100%);
	z-index: 0;

	.header-content {
		padding: 0 40rpx;

		.title-row {
			display: flex;
			align-items: center;
			margin-bottom: 8rpx;

			.page-title {
				font-size: 48rpx;
				font-weight: 700;
				color: #1A1A1A;
				letter-spacing: 1px;
				margin-right: 16rpx;
			}

			.security-badge {
				padding: 4rpx 12rpx;
				border-radius: 8rpx;
				font-size: 20rpx;
				font-weight: 500;
				display: flex;
				align-items: center;

				.lock-icon {
					width: 12rpx;
					height: 8rpx;
					border: 2rpx solid currentColor;
					border-radius: 2rpx;
					margin-right: 6rpx;
					position: relative;

					&::before {
						content: '';
						position: absolute;
						top: -6rpx;
						left: 1rpx;
						width: 6rpx;
						height: 4rpx;
						border: 2rpx solid currentColor;
						border-bottom: none;
						border-radius: 4rpx 4rpx 0 0;
					}
				}
			}
		}

		.page-desc {
			font-size: 26rpx;
			color: #909399;
			font-weight: 400;
		}
	}
}

/* --- 内容区域 --- */
.content-wrapper {
	position: relative;
	z-index: 1;
	padding-left: 30rpx;
	padding-right: 30rpx;
}

/* --- 订单卡片 --- */
.order-card {
	background: #ffffff;
	border-radius: 24rpx;
	box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.03);
	margin-bottom: 30rpx;
	overflow: hidden;

	.card-top {
		padding: 60rpx 0 50rpx;
		text-align: center;

		.amount-label {
			font-size: 26rpx;
			color: #909399;
			margin-bottom: 16rpx;
		}

		.amount-section {
			display: flex;
			align-items: baseline;
			justify-content: center;

			.currency {
				font-size: 44rpx;
				font-weight: 600;
				margin-right: 4rpx;
			}

			.amount {
				font-size: 80rpx;
				font-weight: 700;
				line-height: 1;
				font-family: 'DIN', sans-serif;
			}

			.decimal {
				font-size: 44rpx;
				font-weight: 600;
			}
		}
	}

	.ticket-divider {
		position: relative;
		height: 30rpx;
		display: flex;
		align-items: center;

		.dashed-line {
			flex: 1;
			border-top: 2rpx dashed #EBEEF5;
			margin: 0 24rpx;
		}

		.circle-notch {
			width: 30rpx;
			height: 30rpx;
			background-color: #F7F8FA;
			border-radius: 50%;
			position: absolute;

			&.left {
				left: -15rpx;
			}

			&.right {
				right: -15rpx;
			}
		}
	}

	.card-bottom {
		padding: 40rpx;

		.detail-row {
			display: flex;
			justify-content: space-between;
			font-size: 28rpx;
			margin-bottom: 24rpx;

			&:last-child {
				margin-bottom: 0;
			}

			.label {
				color: #909399;
			}

			.value {
				color: #303133;
				font-weight: 500;
				font-family: monospace;
			}
		}
	}
}

/* --- 支付方式列表 --- */
.payment-group {
	.group-header {
		padding: 10rpx 0 24rpx;

		.group-title {
			font-size: 30rpx;
			font-weight: 600;
			color: #303133;
		}
	}
}

.method-list {
	display: flex;
	flex-direction: column;
	gap: 20rpx;
}

.method-item {
	background: #ffffff;
	border-radius: 20rpx;
	padding: 30rpx;
	display: flex;
	align-items: center;
	justify-content: space-between;
	transition: all 0.2s;

	&:active {
		transform: scale(0.99);
	}

	.method-left {
		display: flex;
		align-items: center;
		flex: 1;

		.pay-icon {
			width: 56rpx;
			height: 56rpx;
			margin-right: 24rpx;
			flex-shrink: 0;
		}

		.pay-info {
			display: flex;
			flex-direction: column;

			.pay-name-row {
				display: flex;
				align-items: center;
			}

			.pay-name {
				font-size: 30rpx;
				color: #333;
				font-weight: 500;
			}

			.pay-tag {
				font-size: 20rpx;
				color: #FF5500;
				background: #FFF0E9;
				padding: 2rpx 8rpx;
				border-radius: 6rpx;
				margin-left: 12rpx;
			}

			// 余额文字样式
			.balance-text {
				font-size: 24rpx;
				color: #ff9900;
				margin-top: 6rpx;
			}
		}
	}

	.radio-box {
		width: 40rpx;
		height: 40rpx;
		border-radius: 50%;
		border: 2rpx solid #E4E7ED;
		display: flex;
		align-items: center;
		justify-content: center;
		transition: all 0.2s;
		flex-shrink: 0;

		.check-mark {
			width: 18rpx;
			height: 10rpx;
			border-left: 4rpx solid #fff;
			border-bottom: 4rpx solid #fff;
			transform: rotate(-45deg);
			margin-top: -4rpx;
		}
	}

	&.is-selected {
		background: #fff;
		box-shadow: 0 0 0 2rpx inset var(--theme-color, #E63D3D);
	}
}

.security-tips {
	margin-top: 60rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 24rpx;
	color: #C0C4CC;
	gap: 10rpx;

	.safe-shield {
		width: 24rpx;
		height: 24rpx;
		background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23C0C4CC'%3E%3Cpath d='M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z'/%3E%3C/svg%3E") no-repeat center/contain;
	}
}

.bottom-spacer {
	height: 180rpx;
}

/* --- 底部固定栏 --- */
.footer-bar {
	position: fixed;
	bottom: 0;
	left: 0;
	width: 100%;
	background: #ffffff;
	box-shadow: 0 -4rpx 16rpx rgba(0, 0, 0, 0.02);
	z-index: 100;

	/* 高度和 tui-tabbar 一致:固定 120rpx,不再额外加底部安全区(用户认定的高度,见 5fa9a32cf2) */
	.footer-inner {
		height: 120rpx;
		padding: 0 30rpx;
		display: flex;
		align-items: center;
	}

	.pay-btn {
		flex: 1;
		margin: 0;
		height: 88rpx;
		border-radius: 44rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		color: #fff;
		font-size: 32rpx;
		font-weight: 600;

		.btn-divider {
			margin: 0 20rpx;
			opacity: 0.4;
			font-size: 24rpx;
		}

		.btn-price {
			font-size: 36rpx;
			font-weight: 700;
			font-family: 'DIN', sans-serif;
		}
	}
}

/* --- 密码输入弹窗样式 --- */
.pwd-mask {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0, 0, 0, 0.6);
	z-index: 1001;
	display: flex;
	align-items: center;
	justify-content: center;
}

.pwd-modal {
	width: 600rpx;
	background-color: #fff;
	border-radius: 20rpx;
	overflow: hidden;

	.modal-title {
		text-align: center;
		padding: 40rpx 0 30rpx;
		font-size: 32rpx;
		font-weight: 600;
		color: #333;
	}

	.modal-content {
		padding: 0 40rpx 40rpx;

		.pwd-input {
			height: 90rpx;
			background-color: #F5F7FA;
			border-radius: 12rpx;
			text-align: center;
			font-size: 36rpx;
			color: #333;
		}
	}

	.modal-btns {
		display: flex;
		border-top: 1rpx solid #EEE;

		.btn {
			flex: 1;
			height: 100rpx;
			line-height: 100rpx;
			text-align: center;
			font-size: 30rpx;

			&:active {
				background-color: #FAFAFA;
			}
		}

		.cancel {
			color: #909399;
			border-right: 1rpx solid #EEE;
		}

		.confirm {
			font-weight: 600;
		}
	}
}

/* --- 支付结果页样式 --- */
.result-mask {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: rgba(255, 255, 255, 0.98);
	z-index: 2000;
	display: flex;
	align-items: center;
	justify-content: center;
	opacity: 0;
	pointer-events: none;
	transition: opacity 0.3s;

	&.show {
		opacity: 1;
		pointer-events: auto;
	}

	.result-card {
		width: 100%;
		padding: 0 80rpx;
		display: flex;
		flex-direction: column;
		align-items: center;
		animation: popUp 0.4s cubic-bezier(0.68, -0.55, 0.27, 1.55);

		.icon-area {
			width: 140rpx;
			height: 140rpx;
			position: relative;
			margin-bottom: 40rpx;
			display: flex;
			align-items: center;
			justify-content: center;

			.circle-bg {
				position: absolute;
				width: 100%;
				height: 100%;
				border-radius: 50%;
				opacity: 0.1;
			}

			&.success .circle-bg {
				background: #07c160;
			}

			&.success .icon-check {
				width: 70rpx;
				height: 36rpx;
				border-left: 8rpx solid #07c160;
				border-bottom: 8rpx solid #07c160;
				transform: rotate(-45deg);
				margin-top: -10rpx;
			}

			&.error .circle-bg {
				background: #ff4d4f;
			}

			&.error .icon-cross {
				position: relative;
				width: 60rpx;
				height: 60rpx;

				.left,
				.right {
					position: absolute;
					top: 50%;
					left: 50%;
					width: 60rpx;
					height: 8rpx;
					background: #ff4d4f;
					border-radius: 4rpx;
				}

				.left {
					transform: translate(-50%, -50%) rotate(45deg);
				}

				.right {
					transform: translate(-50%, -50%) rotate(-45deg);
				}
			}
		}

		.text-area {
			text-align: center;
			margin-bottom: 80rpx;

			.main-title {
				font-size: 40rpx;
				font-weight: bold;
				color: #333;
				margin-bottom: 16rpx;
			}

			.sub-msg {
				font-size: 28rpx;
				color: #999;
				line-height: 1.5;
			}
		}

		.confirm-btn {
			width: 400rpx;
			height: 88rpx;
			line-height: 88rpx;
			border-radius: 44rpx;
			font-size: 32rpx;
			font-weight: 500;
			color: #fff;
			border: none;
			transition: transform 0.1s;

			&:active {
				transform: scale(0.95);
			}
		}
	}
}

.fade-in {
	animation: fadeIn 0.2s ease-out forwards;
}

.fade-in-up {
	animation: fadeInUp 0.5s ease-out forwards;
}

@keyframes fadeInUp {
	from {
		opacity: 0;
		transform: translateY(20rpx);
	}

	to {
		opacity: 1;
		transform: translateY(0);
	}
}

@keyframes fadeIn {
	from {
		opacity: 0;
		transform: scale(0.9);
	}

	to {
		opacity: 1;
		transform: scale(1);
	}
}

@keyframes popUp {
	0% {
		transform: scale(0.8);
		opacity: 0;
	}

	100% {
		transform: scale(1);
		opacity: 1;
	}
}
</style>