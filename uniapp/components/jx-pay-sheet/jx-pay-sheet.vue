<template>
	<!--
		支付弹窗(全局组件,放主包,任何分包 / 插件页都能用):页面里放一个 <jx-pay-sheet ref="paySheet" />,
		用 this.$refs.paySheet.open({ payOrderId }) 打开,返回 Promise:
		  { status: 'paid' | 'closed' | 'expired', orderId, payOrderId, orderNo }
		弹窗自己**不跳转**,付完去哪由调用页决定。页面里一般不直接调 open,用 utils/paySheet.js 包好的
		paySheet(this, { payOrderId, successUrl, replace, keepOrder })(付完 / 关掉之后的去处都在那儿统一处理)。
		下单走排队的,可以先 open({ pending: true }) 弹出「正在创建订单」,拿到支付单再 setPayOrder()。
		原收银台页 others/pay/pay 保留不动,只给扫码 / 外部链接这类只能打开页面的入口用。
	-->
	<view v-if="visible" class="jps" :style="themeStyle">
		<!-- 只在遮罩上拦 touchmove 防滚动穿透;挂在外层会连弹窗里的支付方式列表也滚不动 -->
		<view class="jps-mask" :class="{ in: shown }" @tap="onMaskTap" @touchmove.stop.prevent></view>

		<view class="jps-sheet" :class="{ in: shown }">
			<!-- 正在创建订单 / 加载中 -->
			<view v-if="step === 'loading'" class="jps-loading">
				<view class="jps-spinner"></view>
				<view class="jps-loading-text">{{ loadingText }}</view>
			</view>

			<!-- 选择支付方式 -->
			<block v-else-if="step === 'select'">
				<view class="jps-head">
					<text class="jps-title">确认付款</text>
					<view class="jps-x" @tap="close"><tui-icon name="close" :size="18" color="#a9aeb8"></tui-icon></view>
				</view>
				<view class="jps-amount">
					<text class="jps-cur">¥</text><text class="jps-num">{{ priceInt }}</text><text class="jps-dec">.{{ priceDec }}</text>
				</view>
				<view v-if="summaryText" class="jps-goods">{{ summaryText }}</view>
				<view v-if="remain > 0" class="jps-count">请在 {{ remainText }} 内付款，超时订单自动取消</view>

				<scroll-view scroll-y class="jps-methods">
					<view v-if="!paymentMethods.length && methodsLoaded" class="jps-empty">暂无可用的支付方式，请联系商家</view>
					<view v-for="(item, index) in paymentMethods" :key="index" class="jps-method"
						:class="{ on: selected === item.payment_type }" @tap="selectPay(item.payment_type)">
						<image class="jps-logo" :src="item.logo" mode="aspectFit"></image>
						<view class="jps-minfo">
							<view class="jps-mname">{{ item.name }}</view>
							<view v-if="isBalance(item.payment_type) && user" class="jps-msub">可用{{ $noun('balance') }} ¥{{ user.money }}</view>
							<view v-if="item.quick_pay && selected === item.payment_type && cardId" class="jps-msub link" @tap.stop="openCards">{{ cardLabel }} · 切换</view>
						</view>
						<view class="jps-radio" :class="{ on: selected === item.payment_type }">
							<tui-icon v-if="selected === item.payment_type" name="check" :size="12" color="#ffffff"></tui-icon>
						</view>
					</view>
				</scroll-view>

				<view class="jps-btn" :class="{ busy: paying }" @tap="handlePay">
					{{ paying ? '正在支付…' : '确认付款 ¥' + priceText }}
				</view>
			</block>

			<!-- 余额支付:输入 6 位支付密码(自带数字键盘,不弹系统键盘) -->
			<block v-else-if="step === 'password'">
				<view class="jps-head">
					<view class="jps-back" @tap="step = 'select'"><tui-icon name="arrowleft" :size="20" color="#4e5969"></tui-icon></view>
					<text class="jps-title">输入支付密码</text>
					<view class="jps-x" @tap="close"><tui-icon name="close" :size="18" color="#a9aeb8"></tui-icon></view>
				</view>
				<view class="jps-pwd-tip">{{ $noun('balance') }}支付 ¥{{ priceText }}</view>
				<view class="jps-pwd">
					<view v-for="i in 6" :key="i" class="jps-pwd-cell" :class="{ cur: pwd.length === i - 1 }">
						<view v-if="pwd.length >= i" class="jps-dot"></view>
					</view>
				</view>
				<view class="jps-forget" @tap="goSetPwd">忘记密码</view>
				<view class="jps-keys">
					<view v-for="k in keys" :key="k" class="jps-key" :class="{ blank: k === '', del: k === 'del' }" @tap="pressKey(k)">
						<tui-icon v-if="k === 'del'" name="deletekey" :size="22" color="#1f2329"></tui-icon>
						<text v-else>{{ k }}</text>
					</view>
				</view>
			</block>

			<!-- 银行卡快捷支付:选卡 -->
			<block v-else-if="step === 'cards'">
				<view class="jps-head">
					<view class="jps-back" @tap="step = 'select'"><tui-icon name="arrowleft" :size="20" color="#4e5969"></tui-icon></view>
					<text class="jps-title">选择支付银行卡</text>
					<view class="jps-x" @tap="close"><tui-icon name="close" :size="18" color="#a9aeb8"></tui-icon></view>
				</view>
				<scroll-view scroll-y class="jps-methods">
					<view v-if="cardsLoading" class="jps-empty">加载中…</view>
					<view v-for="card in cards" :key="card.id" class="jps-method" :class="{ on: cardId === card.id }" @tap="pickCard(card)">
						<view class="jps-minfo">
							<view class="jps-mname">{{ card.bank_name }} · {{ card.card_type_label }}</view>
							<view class="jps-msub">尾号 {{ card.bank_card_last4 }} · {{ card.mobile_mask }}<text v-if="card.is_default === 1"> · 默认</text></view>
						</view>
						<view class="jps-radio" :class="{ on: cardId === card.id }">
							<tui-icon v-if="cardId === card.id" name="check" :size="12" color="#ffffff"></tui-icon>
						</view>
					</view>
				</scroll-view>
				<view class="jps-btn ghost" @tap="goBindCard">绑定新卡</view>
			</block>

			<!-- 银行卡快捷支付:短信验证码 -->
			<block v-else-if="step === 'sms'">
				<view class="jps-head">
					<view class="jps-back" @tap="step = 'select'"><tui-icon name="arrowleft" :size="20" color="#4e5969"></tui-icon></view>
					<text class="jps-title">输入短信验证码</text>
					<view class="jps-x" @tap="close"><tui-icon name="close" :size="18" color="#a9aeb8"></tui-icon></view>
				</view>
				<view class="jps-pwd-tip">验证码已发送至 {{ smsMobile || '预留手机号' }}</view>
				<input class="jps-sms" type="number" maxlength="6" :focus="step === 'sms'" placeholder="6 位验证码" v-model="smsCode" />
				<view class="jps-btn" :class="{ busy: paying }" @tap="confirmSms">{{ paying ? '验证中…' : '确认付款' }}</view>
			</block>

			<!-- 结果 -->
			<view v-else-if="step === 'result'" class="jps-result">
				<view class="jps-ricon" :class="result.ok ? 'ok' : 'fail'">
					<tui-icon :name="result.ok ? 'check' : 'close'" :size="30" :color="result.ok ? '#00b42a' : '#f53f3f'"></tui-icon>
				</view>
				<view class="jps-rtitle">{{ result.title }}</view>
				<view class="jps-rmsg">{{ result.msg }}</view>
				<view class="jps-btn" @tap="onResultBtn">{{ result.btn }}</view>
				<view v-if="!result.ok && result.retry" class="jps-rlink" @tap="close">稍后再付</view>
			</view>
		</view>
	</view>
</template>

<script>
	import payment from '@/utils/payment.js';
	// #ifdef H5
	import wxAuth from '@/utils/wxAuth.js';
	// #endif

	export default {
		name: 'jx-pay-sheet',
		data() {
			return {
				visible: false,
				shown: false, // 控制进出场动画(只动 transform / opacity)
				step: 'loading', // loading | select | password | cards | sms | result
				loadingText: '正在加载…',
				payOrderId: '',
				orderNo: '',
				order: null,
				summary: {},
				paymentMethods: [],
				methodsLoaded: false,
				selected: '',
				user: null,
				needPwd: false,
				pwd: '',
				keys: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', 'del'],
				paying: false,
				remain: 0,
				timer: null,
				resultTimer: null,
				result: { ok: true, title: '', msg: '', btn: '', retry: false },
				h5OpenId: '',
				// 银行卡快捷支付
				cards: [],
				cardsLoading: false,
				cardId: 0,
				smsCode: '',
				smsOrderNo: '',
				smsMobile: '',
				resolver: null
			};
		},
		computed: {
			priceText() {
				return Number(this.order ? this.order.pay_price : 0).toFixed(2);
			},
			priceInt() {
				return this.priceText.split('.')[0];
			},
			priceDec() {
				return this.priceText.split('.')[1];
			},
			summaryText() {
				const s = this.summary || {};
				if (!s.goods_name) return '';
				return s.goods_count > 1 ? s.goods_name + ' 等 ' + s.goods_count + ' 件' : s.goods_name;
			},
			remainText() {
				const m = Math.floor(this.remain / 60);
				const s = this.remain % 60;
				return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
			},
			cardLabel() {
				const c = this.cards.find(x => x.id === this.cardId);
				return c ? (c.bank_name || '银行卡') + '(' + (c.bank_card_last4 || '') + ')' : '已选卡';
			}
		},
		beforeDestroy() {
			this.clearTimers();
		},
		methods: {
			/**
			 * 打开弹窗。
			 * @param {Object} opts { payOrderId, orderNo, pending }
			 *   pending=true:订单还在排队创建,先显示「正在创建订单」,拿到支付单后调 setPayOrder()
			 * @returns {Promise<{status, orderId, payOrderId, orderNo}>}
			 */
			open(opts = {}) {
				// 上一次没结束(比如重复点)就先按「关闭」结掉,别让旧的 Promise 永远挂着
				if (this.resolver) this.finish('closed');
				this.reset();
				this.visible = true;
				this.$nextTick(() => setTimeout(() => { this.shown = true; }, 20));
				const p = new Promise(resolve => { this.resolver = resolve; });
				// #ifdef H5
				wxAuth.ready().then(openid => { this.h5OpenId = openid || wxAuth.getOpenid(); });
				// #endif
				if (opts.pending) {
					this.step = 'loading';
					this.loadingText = '正在创建订单…';
				} else {
					this.setPayOrder(opts);
				}
				return p;
			},
			/** 排队下单拿到支付单后调用 */
			setPayOrder(opts = {}) {
				this.payOrderId = opts.payOrderId || '';
				this.orderNo = opts.orderNo || '';
				// 弹窗已经关了(用户在排队时关掉的):只记下支付单,别在后台发请求、跑倒计时
				if (!this.visible || !this.resolver) return;
				this.step = 'loading';
				this.loadingText = '正在加载…';
				this.loadOrder();
				this.loadPayment();
				this.loadUser();
				this.loadSetting();
			},
			/** 排队下单失败时调用:弹窗里直接显示原因 */
			fail(msg) {
				this.showResult(false, '下单失败', msg || '请稍后再试', '知道了', false);
			},
			close() {
				this.finish('closed');
			},
			isOpen() {
				return this.visible;
			},

			reset() {
				this.clearTimers();
				this.step = 'loading';
				this.order = null;
				this.summary = {};
				this.paymentMethods = [];
				this.methodsLoaded = false;
				this.selected = '';
				this.pwd = '';
				this.paying = false;
				this.remain = 0;
				this.cards = [];
				this.cardId = 0;
				this.smsCode = '';
			},
			clearTimers() {
				if (this.timer) clearInterval(this.timer);
				if (this.resultTimer) clearTimeout(this.resultTimer);
				this.timer = null;
				this.resultTimer = null;
			},
			// 收尾:先收起动画再卸载,把结果交给调用页
			finish(status) {
				const resolve = this.resolver;
				this.resolver = null;
				this.clearTimers();
				this.shown = false;
				setTimeout(() => { if (!this.resolver) this.visible = false; }, 220);
				if (resolve) {
					resolve({
						status,
						orderId: Number((this.summary && this.summary.order_id) || 0),
						payOrderId: this.payOrderId,
						orderNo: this.order ? this.order.order_no : this.orderNo
					});
				}
			},
			onMaskTap() {
				// 正在建单 / 正在发起支付 / 已付成功等跳转时,点遮罩不关,免得结果丢了
				if (this.step === 'loading' || this.paying || (this.step === 'result' && this.result.ok)) return;
				this.close();
			},
			orderQuery() {
				const d = {};
				if (this.payOrderId) d.pay_order_id = this.payOrderId;
				else if (this.orderNo) d.order_no = this.orderNo;
				return d;
			},

			// --- 数据 ---
			loadOrder() {
				this.$request({ url: '/api/pay/order', data: this.orderQuery() }).then(res => {
					if (res.code == 0) {
						this.order = res.data.order;
						this.summary = res.data.summary || {};
						this.step = 'select';
						this.startCountdown();
					} else if (res.data && res.data.is_pay == 1) {
						// 已经付过(0 元单下单即自动支付,或重复打开已付的单):按支付成功处理,自动交回调用页
						this.summary = res.data.summary || {};
						this.showResult(true, '订单已支付', '无需再付款', '查看订单');
						this.resultTimer = setTimeout(() => this.finish('paid'), 1500);
					} else {
						this.showResult(false, '无法付款', res.msg || '订单不存在或已关闭', '知道了', false);
					}
				}).catch(() => {
					this.showResult(false, '加载失败', '网络不太好，请稍后再试', '知道了', false);
				});
			},
			loadPayment() {
				this.$request({ url: '/api/pay/payment', data: this.orderQuery() }).then(res => {
					this.methodsLoaded = true;
					if (res.code == 0 && res.data && res.data.length) {
						this.paymentMethods = res.data;
						this.selected = res.data[0].payment_type;
					}
				}).catch(() => { this.methodsLoaded = true; });
			},
			loadUser() {
				this.$request({ url: '/api/user/index' }).then(res => {
					if (res.code === 0) this.user = res.data.user;
				});
			},
			loadSetting() {
				this.$request({ url: '/api/mall/setting' }).then(res => {
					// 商城级「余额支付是否验密」开关(后台商城设置 is_balance_pay_password)
					if (res.code === 0) this.needPwd = res.data.setting.is_balance_pay_password == 1;
				});
			},
			// 倒计时按服务器时间差算,不信手机本地时钟
			startCountdown() {
				const s = this.summary || {};
				if (!s.expire_at || !s.now) return;
				const end = Date.now() + (s.expire_at - s.now) * 1000;
				const tick = () => {
					this.remain = Math.max(0, Math.round((end - Date.now()) / 1000));
					if (this.remain <= 0) {
						clearInterval(this.timer);
						this.timer = null;
						if (this.step !== 'result') {
							this.showResult(false, '订单已超时', '已超过付款时间，订单状态请以订单页面为准', '知道了', false, 'expired');
						}
					}
				};
				tick();
				if (this.remain > 0) this.timer = setInterval(tick, 1000);
			},

			// --- 支付流程(逻辑与原收银台一致) ---
			isBalance(type) {
				return String(type || '').indexOf('BalancePayment') !== -1;
			},
			selectPay(type) {
				// 换支付方式时清掉快捷支付的选卡,避免用 A 网关的卡走 B 网关
				if (this.selected !== type) {
					this.cardId = 0;
					this.cards = [];
				}
				this.selected = type;
			},
			handlePay() {
				if (this.paying) return;
				if (!this.selected) {
					uni.showToast({ title: '请选择支付方式', icon: 'none' });
					return;
				}
				if (this.isBalance(this.selected) && this.needPwd) {
					if (this.user && this.user.has_pay_pwd === 0) {
						this.promptSetPwd();
						return;
					}
					this.pwd = '';
					this.step = 'password';
					return;
				}
				const item = this.paymentMethods.find(p => p.payment_type === this.selected);
				if (item && item.quick_pay && !this.cardId) {
					this.openCards();
					return;
				}
				this.requestPay();
			},
			pressKey(k) {
				if (k === '' || this.paying) return;
				if (k === 'del') {
					this.pwd = this.pwd.slice(0, -1);
					return;
				}
				if (this.pwd.length >= 6) return;
				this.pwd += k;
				// 输满 6 位直接付,不用再点确定
				if (this.pwd.length === 6) {
					const p = this.pwd;
					setTimeout(() => this.requestPay(p), 120);
				}
			},
			promptSetPwd() {
				uni.showModal({
					title: '请先设置支付密码',
					content: '设置完成后回到这里继续付款',
					confirmText: '去设置',
					success: res => {
						// navigateTo 保留当前页,设置完返回时弹窗还在
						if (res.confirm) uni.navigateTo({ url: '/others/setting/setting?action=pay_pwd' });
					}
				});
			},
			goSetPwd() {
				uni.navigateTo({ url: '/others/setting/setting?action=pay_pwd' });
			},
			openCards() {
				this.step = 'cards';
				this.cardsLoading = true;
				this.$request({ url: '/api/bank-card/list', method: 'get', data: { gateway: this.selected } }).then(res => {
					this.cardsLoading = false;
					if (res.code !== 0) return;
					this.cards = res.data || [];
					if (!this.cards.length) {
						this.goBindCard();
						return;
					}
					if (this.cardId && !this.cards.find(c => c.id === this.cardId)) this.cardId = 0;
					if (!this.cardId) {
						const def = this.cards.find(c => c.is_default === 1);
						if (def) this.cardId = def.id;
					}
				}).catch(() => { this.cardsLoading = false; });
			},
			pickCard(card) {
				this.cardId = card.id;
				this.step = 'select';
				this.requestPay();
			},
			// 去绑卡:不传 redirect,绑卡页绑完会 navigateBack 回到本页,弹窗还开着;
			// 回来再点「确认付款」会重新拉卡列表,新绑的卡就在里面
			goBindCard() {
				this.cardId = 0;
				this.cards = [];
				this.step = 'select';
				uni.navigateTo({
					url: '/others/bank-card/bank-card-bind?gateway=' + encodeURIComponent(this.selected)
				});
			},
			confirmSms() {
				if (this.paying) return;
				if (!this.smsCode || this.smsCode.length < 4) {
					uni.showToast({ title: '请输入短信验证码', icon: 'none' });
					return;
				}
				this.paying = true;
				this.$request({
					url: '/api/bank-card/verify-pay',
					method: 'post',
					data: { order_no: this.smsOrderNo, smsCode: this.smsCode }
				}).then(res => {
					this.paying = false;
					if (res.code === 0 && res.data && res.data.is_pay === 1) {
						this.paidOk();
					} else {
						uni.showToast({ title: res.msg || '验证失败', icon: 'none' });
					}
				}).catch(() => {
					this.paying = false;
					uni.showToast({ title: '请求失败', icon: 'none' });
				});
			},
			requestPay(password = '') {
				// 连点闸门:每次发起后端都会重算 out_trade_no,并发会生成多个都能付款的预支付单
				if (this.paying) return;
				this.paying = true;
				const done = () => { this.paying = false; };

				const send = (code = '') => {
					const data = Object.assign({ payment_type: this.selected, pay_password: password }, this.orderQuery());
					if (this.cardId) data.bind_card_id = this.cardId;
					if (code) data.code = code;
					// #ifdef H5
					// 微信 JSAPI 类支付必须带当前微信号的 openid,缺了就先去复核(会整页跳转,回来走收银台页)
					if (/mpwx|jsapi|wechat/i.test(this.selected)) {
						if (!this.h5OpenId) {
							done();
							wxAuth.ensure();
							uni.showToast({ title: '正在准备微信支付，请重试', icon: 'none' });
							return;
						}
						data.openid = this.h5OpenId;
					}
					// #endif
					this.$request({ url: '/api/pay/pay-data', data, method: 'post' }).then(res => {
						done();
						if (res.code == 1) {
							if (this.step === 'password') this.pwd = '';
							uni.showModal({ title: '支付提示', content: res.msg, showCancel: false });
							return;
						}
						if (res.code != 0) return;
						const d = res.data || {};
						if (d.is_pay == 1) { this.paidOk(); return; }
						if (d.need_set_pay_password === 1) { this.promptSetPwd(); return; }
						if (d.need_sms === 1) {
							this.smsOrderNo = d.order_no || '';
							this.smsMobile = d.mobile_mask || '';
							this.smsCode = '';
							this.step = 'sms';
							return;
						}
						if (d.is_pay == 0 && d.msg) {
							this.showResult(false, '支付失败', d.msg, '重新付款', true);
							return;
						}
						if (d.is_app_call_mp === 1) {
							// #ifdef APP-PLUS
							const mp = d.app_call_mp_data;
							plus.share.getServices(list => {
								const wx = list.find(i => i.id === 'weixin');
								if (wx) wx.launchMiniProgram({ id: mp.userName, path: mp.path, type: mp.miniprogramType });
							});
							// #endif
							return;
						}
						payment.pay(d, this.selected).then(() => {
							this.paidOk();
						}).catch(e => {
							const cancel = e && e.errMsg && e.errMsg.indexOf('cancel') !== -1;
							if (cancel) uni.showToast({ title: '已取消付款', icon: 'none' });
							else this.showResult(false, '支付失败', '付款没有完成，请重试', '重新付款', true);
						});
					}).catch(() => {
						done();
						uni.showToast({ title: '请求失败', icon: 'none' });
					});
				};

				// 微信小程序每次付款前现取登录 code
				// #ifdef MP-WEIXIN
				uni.login({
					provider: 'weixin',
					success: r => {
						if (r.code) send(r.code);
						else { done(); uni.showToast({ title: '获取登录凭证失败', icon: 'none' }); }
					},
					fail: () => { done(); uni.showToast({ title: '微信登录失败', icon: 'none' }); }
				});
				// #endif
				// #ifndef MP-WEIXIN
				send();
				// #endif
			},

			// --- 结果 ---
			paidOk() {
				this.showResult(true, '支付成功', '已支付 ¥' + this.priceText, '查看订单');
				// 停一下让用户看到结果,再把结果交回调用页(由调用页决定跳哪)
				this.resultTimer = setTimeout(() => this.finish('paid'), 1500);
			},
			showResult(ok, title, msg, btn, retry = false, endStatus = '') {
				this.clearTimers();
				this.result = { ok, title, msg, btn, retry, endStatus };
				this.step = 'result';
			},
			onResultBtn() {
				if (this.result.ok) { this.finish('paid'); return; }
				if (this.result.retry) { this.pwd = ''; this.step = 'select'; this.startCountdown(); return; }
				this.finish(this.result.endStatus || 'closed');
			}
		}
	};
</script>

<style lang="scss" scoped>
	/* 层级要压住各页面自己的遮罩(轮询遮罩最高见过 9999),否则拿到支付单时弹窗会被盖住 */
	.jps {
		position: fixed;
		left: 0;
		right: 0;
		top: 0;
		bottom: 0;
		z-index: 10100;
	}

	.jps-mask {
		position: absolute;
		left: 0;
		right: 0;
		top: 0;
		bottom: 0;
		background: rgba(0, 0, 0, 0.45);
		opacity: 0;
		transition: opacity 0.2s;
	}

	.jps-mask.in {
		opacity: 1;
	}

	.jps-sheet {
		position: absolute;
		left: 0;
		right: 0;
		bottom: 0;
		background: #ffffff;
		border-radius: 32rpx 32rpx 0 0;
		padding: 28rpx 32rpx calc(28rpx + env(safe-area-inset-bottom));
		transform: translateY(100%);
		transition: transform 0.22s ease-out;
	}

	.jps-sheet.in {
		transform: translateY(0);
	}

	.jps-head {
		position: relative;
		height: 56rpx;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.jps-title {
		font-size: 30rpx;
		font-weight: 600;
		color: #1f2329;
	}

	.jps-x,
	.jps-back {
		position: absolute;
		top: 0;
		width: 56rpx;
		height: 56rpx;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.jps-x {
		right: -8rpx;
	}

	.jps-back {
		left: -8rpx;
	}

	.jps-amount {
		margin-top: 28rpx;
		text-align: center;
		color: #1f2329;
		font-family: "DIN Alternate", "Helvetica Neue", sans-serif;
	}

	.jps-cur {
		font-size: 34rpx;
		font-weight: 600;
		margin-right: 4rpx;
	}

	.jps-num {
		font-size: 72rpx;
		font-weight: 700;
	}

	.jps-dec {
		font-size: 36rpx;
		font-weight: 600;
	}

	.jps-goods {
		margin-top: 8rpx;
		text-align: center;
		font-size: 24rpx;
		color: #86909c;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.jps-count {
		margin-top: 8rpx;
		text-align: center;
		font-size: 22rpx;
		color: #d46b08;
	}

	.jps-methods {
		margin-top: 28rpx;
		max-height: 520rpx;
	}

	.jps-empty {
		padding: 48rpx 0;
		text-align: center;
		font-size: 26rpx;
		color: #86909c;
	}

	.jps-method {
		display: flex;
		align-items: center;
		padding: 24rpx 24rpx;
		margin-bottom: 16rpx;
		border-radius: 20rpx;
		border: 2rpx solid #eef0f3;
	}

	.jps-method.on {
		border-color: var(--theme-color);
		background: var(--theme-color-bg);
	}

	.jps-logo {
		width: 52rpx;
		height: 52rpx;
		margin-right: 20rpx;
		flex-shrink: 0;
	}

	.jps-minfo {
		flex: 1;
		min-width: 0;
	}

	.jps-mname {
		font-size: 28rpx;
		color: #1f2329;
	}

	.jps-msub {
		margin-top: 4rpx;
		font-size: 22rpx;
		color: #86909c;
	}

	.jps-msub.link {
		color: var(--theme-color);
	}

	.jps-radio {
		width: 36rpx;
		height: 36rpx;
		border-radius: 50%;
		border: 2rpx solid #c9cdd4;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
	}

	.jps-radio.on {
		border-color: var(--theme-color);
		background: var(--theme-color);
	}

	.jps-btn {
		margin-top: 24rpx;
		height: 92rpx;
		border-radius: 46rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 30rpx;
		font-weight: 600;
		color: #ffffff;
		background: var(--theme-color);
		box-shadow: 0 10rpx 24rpx var(--theme-shadow);
	}

	.jps-btn.busy {
		opacity: 0.7;
	}

	.jps-btn.ghost {
		color: var(--theme-color);
		background: var(--theme-color-bg);
		box-shadow: none;
	}

	/* 支付密码 */
	.jps-pwd-tip {
		margin-top: 20rpx;
		text-align: center;
		font-size: 26rpx;
		color: #4e5969;
	}

	.jps-pwd {
		display: flex;
		justify-content: center;
		margin-top: 32rpx;
	}

	.jps-pwd-cell {
		width: 84rpx;
		height: 96rpx;
		margin: 0 8rpx;
		border-radius: 16rpx;
		border: 2rpx solid #e5e6eb;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.jps-pwd-cell.cur {
		border-color: var(--theme-color);
	}

	.jps-dot {
		width: 20rpx;
		height: 20rpx;
		border-radius: 50%;
		background: #1f2329;
	}

	.jps-forget {
		margin-top: 20rpx;
		text-align: center;
		font-size: 24rpx;
		color: var(--theme-color);
	}

	.jps-keys {
		display: flex;
		flex-wrap: wrap;
		margin: 28rpx -8rpx 0;
	}

	.jps-key {
		width: calc(33.333% - 16rpx);
		height: 96rpx;
		margin: 8rpx;
		border-radius: 16rpx;
		background: #f2f3f5;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 40rpx;
		color: #1f2329;
	}

	.jps-key.blank {
		background: transparent;
	}

	.jps-key.del {
		background: #e5e6eb;
	}

	.jps-sms {
		margin-top: 32rpx;
		height: 96rpx;
		border-radius: 16rpx;
		background: #f2f3f5;
		text-align: center;
		font-size: 36rpx;
		letter-spacing: 12rpx;
	}

	/* 加载 */
	.jps-loading {
		padding: 80rpx 0 72rpx;
		display: flex;
		flex-direction: column;
		align-items: center;
	}

	.jps-spinner {
		width: 56rpx;
		height: 56rpx;
		border-radius: 50%;
		border: 6rpx solid var(--theme-color-bg);
		border-top-color: var(--theme-color);
		animation: jps-spin 0.8s linear infinite;
	}

	@keyframes jps-spin {
		to {
			transform: rotate(360deg);
		}
	}

	.jps-loading-text {
		margin-top: 24rpx;
		font-size: 26rpx;
		color: #86909c;
	}

	/* 结果 */
	.jps-result {
		padding: 40rpx 0 8rpx;
		text-align: center;
	}

	.jps-ricon {
		width: 112rpx;
		height: 112rpx;
		margin: 0 auto;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.jps-ricon.ok {
		background: #e8ffea;
	}

	.jps-ricon.fail {
		background: #ffece8;
	}

	.jps-rtitle {
		margin-top: 24rpx;
		font-size: 32rpx;
		font-weight: 600;
		color: #1f2329;
	}

	.jps-rmsg {
		margin-top: 8rpx;
		font-size: 26rpx;
		color: #86909c;
	}

	.jps-rlink {
		margin-top: 20rpx;
		font-size: 24rpx;
		color: #86909c;
	}
</style>
