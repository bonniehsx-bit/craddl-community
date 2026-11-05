<template>
	<tui-page>
		<view class="register-container" :style="containerStyle">

			<view class="custom-nav-bar" :style="{ height: navBarHeight + 'px', paddingTop: statusBarHeight + 'px' }">
				<view class="nav-left" :style="{ height: menuButtonHeight + 'px', top: menuButtonTop + 'px' }"
					@click="handleNavBack">
					<tui-icon v-if="canBack" name="arrowleft" :size="24" color="#000"></tui-icon>
					<tui-icon v-else name="home" :size="24" color="#000"></tui-icon>
				</view>
			</view>
			<view :style="{ height: navBarHeight + 'px' }"></view>
			<view class="ambient-bg">
				<view class="orb orb-1"></view>
				<view class="orb orb-2"></view>
			</view>

			<view class="main-content">

				<view class="header-section">
					<view class="brand-logo">
						<tui-icon name="edit" :size="22" :color="themeColor"></tui-icon>
						<text class="brand-text" :style="textThemeStyle">REGISTER</text>
					</view>
					<view class="title">创建新账号</view>
					<view class="subtitle">请完善以下信息以完成注册</view>
				</view>

				<view class="form-area">

					<!-- 推荐人:① 分享带入 / ② 后台默认 → 只读展示;③ 两者都没有 → 用户自己填 -->
					<view class="referrer-tag" v-if="pidSource !== 'manual' && form.pid" :style="referrerStyle">
						<tui-icon name="people" :size="14" :color="themeColor"></tui-icon>
						<text :style="textThemeStyle">{{ pidSource === 'default' ? '默认推荐人ID' : '推荐人ID' }}：{{ form.pid }}</text>
					</view>

					<view class="input-group" v-else-if="pidSource === 'manual'">
						<view class="input-icon"><tui-icon name="people" :size="20" color="#bbb"></tui-icon></view>
						<input class="input-field" type="number" v-model="form.pid" maxlength="10"
							placeholder="推荐人ID（选填）" placeholder-class="ph-style" />
					</view>

					<view class="input-group">
						<view class="input-icon"><tui-icon name="people" :size="20" color="#bbb"></tui-icon></view>
						<input class="input-field" type="nickname" v-model="form.nickName" @blur="nicknameInput"
							placeholder="请输入昵称" placeholder-class="ph-style" />
					</view>

					<view class="input-group">
						<view class="input-icon"><tui-icon name="mobile" :size="20" color="#bbb"></tui-icon></view>

						<input class="input-field" type="number" v-model="form.mobile" :disabled="form.type === 2"
							maxlength="11" placeholder="请输入手机号" placeholder-class="ph-style" />

						<!-- #ifdef MP-WEIXIN -->
						<button v-if="form.type === 2" class="mini-btn" :style="miniBtnStyle"
							open-type="getPhoneNumber|agreePrivacyAuthorization" @getphonenumber="getPhoneNumber">
							一键获取
						</button>
						<!-- #endif -->
					</view>

					<!-- 手机号快捷注册依赖 getPhoneNumber,仅小程序支持;H5/App 隐藏切换,只走手动输入+验证码 -->
					<!-- #ifdef MP-WEIXIN -->
					<view class="switch-row">
						<view v-if="form.type === 1" class="switch-btn" @click="useType(2)">
							<text class="active-text" :style="textThemeStyle">使用手机号快捷注册</text>
						</view>
						<view v-if="form.type === 2" class="switch-btn" @click="useType(1)">
							<text class="gray-text">手动输入手机号码</text>
						</view>
					</view>
					<!-- #endif -->
					<view class="input-group" v-if="form.type === 1 && regNeedCode">
						<view class="input-icon"><tui-icon name="shield" :size="20" color="#bbb"></tui-icon></view>
						<input class="input-field" type="number" v-model="form.code" maxlength="6" placeholder="请输入验证码"
							placeholder-class="ph-style" />

						<view class="code-btn" :class="{ 'disabled': is_send_code }" :style="codeBtnStyle"
							@click="getCode">
							{{ is_send_code ? `${sec}s` : '获取验证码' }}
						</view>
					</view>

					<view class="input-group">
						<view class="input-icon"><tui-icon name="pwd" :size="20" color="#bbb"></tui-icon></view>
						<input class="input-field" type="password" v-model="form.password" placeholder="设置登录密码 (6-20位)"
							placeholder-class="ph-style" />
					</view>

					<!-- 商城开启「注册需设置支付密码」时才出现;与 tui-paypwd 同约定:6 位数字 -->
					<view class="input-group" v-if="regNeedPayPwd">
						<view class="input-icon"><tui-icon name="attestation" :size="20" color="#bbb"></tui-icon></view>
						<input class="input-field" type="number" password v-model="form.pay_pwd" maxlength="6"
							placeholder="设置6位数字支付密码" placeholder-class="ph-style" />
					</view>

					<button class="btn-primary" hover-class="btn-hover" @click="handleRegister">
						立即注册
					</button>

				</view>

				<view class="footer-area">

					<view class="agreement-box" :class="{ 'shake-anim': isShake }" @click="toggleAgree">
						<view class="checkbox" :class="{ 'checked': is_agree }" :style="checkboxStyle">
							<tui-icon v-if="is_agree" name="check" :size="10" color="#fff"></tui-icon>
						</view>
						<view class="agree-text">
							我已阅读并同意 <text class="link" @click.stop="openProtocol">《用户协议》</text> 和 <text class="link"
								@click.stop="openPrivacy">《隐私条款》</text>
						</view>
					</view>

					<view class="login-link" @click="goToLogin">
						已有账号？<text class="highlight" :style="textThemeStyle">立即登录</text>
					</view>
				</view>

			</view>
		</view>
	</tui-page>
</template>

<script>
// --- 逻辑完全保留 ---
import { Base64 } from '@/jssdk/base64.min.js';
import { mapActions, mapState } from 'vuex';
import store from '@/store/index.js';
import wxAuth from '@/utils/wxAuth.js';

export default {
	data() {
		return {
			// 注册成功后回哪一页:登录页用 redirect 参数带过来,直接进注册页时取缓存
			backUrl: '',
			platform: '',
			is_loading: false,
			sec: 180,
			is_send_code: false,
			is_agree: false,
			isShake: false,
			pidSource: '', // 'share' 分享带入 | 'default' 后台默认 | 'manual' 用户手填
			form: {
				type: 1, // 1: 手动输入, 2: 微信获取
				phone_ticket: '', // type=2:手机号授权接口给的一次性凭证
				pid: '',
				mobile: '',
				password: '',
				pay_pwd: '',
				code: '',
				nickName: "",
				avatarUrl: '',
				openid: "",
				unionId: '',
			},
			statusBarHeight: 20,
			navBarHeight: 64,
			menuButtonHeight: 32,
			menuButtonTop: 24,
			canBack: false,
		};
	},
	computed: {
		...mapState('mall', {
			themeColor: state => state.themeColor || '#D71F1F'
		}),
		themeColorLight() {
			const color = this.themeColor || '#D71F1F';
			return this.hexToRgba(color, 0.08);
		},
		// --- [修复 Bad attr style] 将样式逻辑移入 computed ---
		containerStyle() {
			return `--theme-color: ${this.themeColor}; --theme-light: ${this.themeColorLight};`;
		},
		textThemeStyle() {
			return `color: ${this.themeColor};`;
		},
		referrerStyle() {
			return `background-color: ${this.themeColorLight};`;
		},
		miniBtnStyle() {
			return `color: ${this.themeColor}; background-color: ${this.themeColorLight};`;
		},
		codeBtnStyle() {
			return this.is_send_code ? 'color: #999;' : `color: ${this.themeColor};`;
		},
		checkboxStyle() {
			return this.is_agree ? `background-color: ${this.themeColor}; border-color: ${this.themeColor};` : '';
		},
		// 注册是否需要短信验证码(mall_setting.reg_need_sms_code,默认需要)。
		// 与 is_login_mobile 无关:那个开关只决定登录弹窗跳手机号登录页还是走微信一键授权,登录链路不用验证码。
		regNeedCode() {
			var v = this.$util.getSetting('reg_need_sms_code');
			return (v === '' || v === undefined || v === null) ? true : String(v) !== '0';
		},
		// 注册是否必须设置支付密码(mall_setting.reg_need_pay_password,默认否)
		regNeedPayPwd() {
			return String(this.$util.getSetting('reg_need_pay_password')) === '1';
		}
	},
	onLoad(options) {
		uni.hideLoading();
		this.backUrl = options.redirect ? decodeURIComponent(options.redirect) : (uni.getStorageSync('login_before_url') || '');
		uni.removeStorageSync('login_before_url');
		// #ifdef MP-WEIXIN
		this.platform = 'MP';
		this.initNavBar();
		// #endif

		// #ifdef H5
		this.platform = 'H5';
		if (this.$wechatSdk.isWechat()) {
			let wechatInfo = uni.getStorageSync('WECHAT_USER_INFO');
			if (!wechatInfo) {
				this.wechatLogin();
			}
		}
		// #endif

		// #ifdef APP-PLUS
		this.platform = 'APP';
		// #endif
	},
	onShow() {
		// 推荐人优先级(与后端 MobileSignInForm 一致):① 邀请来源 → ② 后台默认 → ③ 用户手填
		const pid = uni.getStorageSync("X-PARENT-ID");
		if (pid && Number(pid) > 0) {
			this.form.pid = pid;
			this.pidSource = 'share';
			return;
		}
		// 已进入手填态就别再覆盖用户已输入的内容
		if (this.pidSource === 'manual') return;

		const dp = this.$util.getSetting('default_parent_id');
		if (dp && Number(dp) > 0) {
			this.form.pid = String(dp);
			this.pidSource = 'default';
		} else {
			this.form.pid = '';
			this.pidSource = 'manual';
		}
	},
	methods: {
		...mapActions('user', ['loginSuccess']),

		initNavBar() {
			const sysInfo = uni.getSystemInfoSync();
			// || 20 是必须的:某些 Android 机型和 App 端 WebView 初始化早期会返回 undefined,
			// "undefinedpx" 让 paddingTop 整个失效 —— 返回按钮会顶进状态栏跟信号/时间重叠。
			// data 里那个默认 20 挡不住,因为这一行会把它覆盖掉。
			this.statusBarHeight = sysInfo.statusBarHeight || 20;
			try {
				const menuButtonInfo = uni.getMenuButtonBoundingClientRect();
				if (menuButtonInfo) {
					this.menuButtonHeight = menuButtonInfo.height;
					this.menuButtonTop = menuButtonInfo.top;
					const contentHeight = (menuButtonInfo.top - sysInfo.statusBarHeight) * 2 + menuButtonInfo.height;
					this.navBarHeight = sysInfo.statusBarHeight + contentHeight;
				}
			} catch (e) {
				this.navBarHeight = 64;
			}
			const pages = getCurrentPages();
			this.canBack = pages.length > 1;
		},

		handleNavBack() {
			if (this.canBack) {
				uni.navigateBack();
			} else {
				uni.reLaunch({
					url: '/pages/index/index'
				});
			}
		},

		toggleAgree() {
			this.is_agree = !this.is_agree;
		},

		checkAgreement() {
			if (!this.is_agree) {
				this.isShake = true;
				setTimeout(() => { this.isShake = false; }, 500);
				uni.showToast({ title: '请先同意用户协议', icon: 'none' });
				return false;
			}
			return true;
		},

		useType(e) {
			this.form.type = e;
			this.form.phone_ticket = '';
			if (this.form.type == 2) {
				this.form.mobile = '';
			}
		},

		getPhoneNumber(e) {
			if (e.detail.errMsg && e.detail.errMsg.indexOf("fail") !== -1) {
				uni.showToast({ title: '已取消授权', icon: 'none' });
				return;
			}
			uni.showLoading({ title: '获取中...' });
			this.$request({
				url: "/api/login/mpwx-phone",
				data: {
					code: e.detail.code,
					encryptedData: e.detail.encryptedData,
					iv: e.detail.iv
				},
				method: 'post'
			}).then(res => {
				uni.hideLoading();
				if (res.code == 0) {
					this.form.mobile = res.data.mobile;
					// 提交时服务端只认这张一次性凭证,不认上面这个号码(防止改成别人的手机号)
					this.form.phone_ticket = res.data.phone_ticket || '';
					uni.showToast({ title: '获取成功', icon: 'success' });
				} else {
					uni.showModal({ title: '提示', content: res.msg || '获取手机号失败' });
				}
			}).catch(() => {
				uni.hideLoading();
			});
		},

		getCode() {
			if (this.is_send_code) return;
			if (!this.form.mobile) return uni.showToast({ title: '请输入手机号', icon: 'none' });
			if (!this.$util.isMobile(this.form.mobile)) return uni.showToast({ title: '手机号格式错误', icon: 'none' });

			this.is_send_code = true;
			this.$request({
				url: "/api/login/sms-code",
				data: { mobile: this.form.mobile, type: 'register' },
				method: 'post'
			}).then(res => {
				if (res.code == 0) {
					uni.showToast({ title: '发送成功', icon: 'success' });
					let timer = setInterval(() => {
						this.sec -= 1;
						if (this.sec <= 0) {
							this.is_send_code = false;
							this.sec = 180;
							clearInterval(timer);
						}
					}, 1000);
				} else {
					this.is_send_code = false;
					uni.showToast({ title: res.msg, icon: 'none' });
				}
			});
		},

		nicknameInput(e) {
			this.form.nickName = e.detail.value;
		},

		handleRegister() {
			if (this.is_loading) return;
			if (!this.checkAgreement()) return;

			if (!this.form.nickName) return uni.showToast({ title: '请输入昵称', icon: 'none' });
			if (!this.form.mobile) return uni.showToast({ title: '请输入手机号', icon: 'none' });
			if (!this.form.password) return uni.showToast({ title: '请设置密码', icon: 'none' });
			if (this.form.password.length < 6) return uni.showToast({ title: '密码不能少于6位', icon: 'none' });

			if (this.form.type === 1 && this.regNeedCode && !this.form.code) {
				return uni.showToast({ title: '请输入验证码', icon: 'none' });
			}
			if (this.regNeedPayPwd && !/^\d{6}$/.test(this.form.pay_pwd || '')) {
				return uni.showToast({ title: '请设置6位数字支付密码', icon: 'none' });
			}
			this.signIn();
		},

		signIn() {
			this.is_loading = true;
			uni.showLoading({ title: '注册中...' });

			let wechatInfo = uni.getStorageSync('WECHAT_USER_INFO');
			if (wechatInfo) {
				this.form = Object.assign(this.form, wechatInfo);
					// openid/unionid 以 wxAuth 的【当前微信号】为准，覆盖可能过期的值
					const _fo = wxAuth.getOpenid();
					if (_fo) {
						this.form.openid = _fo;
						this.form.openId = _fo;
						const _fu = wxAuth.getUnionid();
						if (_fu) this.form.unionId = _fu;
					}
			}

			this.$request({
				url: "/api/login/sign",
				data: this.form,
				method: 'post'
			}).then(res => {
				uni.hideLoading();
				this.is_loading = false;
				this.is_loading = false;
				if (res.code == 0) {
					// 【优化】改用 loginSuccess 动作，触发 LOGIN_SUCCESS 广播，自动连接 IM
					this.loginSuccess({
						token: res.data.access_token
					});
					uni.showToast({ title: '注册成功', icon: 'success' });

					const url = this.backUrl;
					setTimeout(() => {
						if (url) {
							// H5 也走 reLaunch:url 是 /others/xxx?a=1 这种页面路径,H5 是 hash 路由,
							// 直接 location.href 会跳到域名根下的真实路径,404、参数也丢了
							uni.reLaunch({ url });
						} else {
							uni.reLaunch({ url: "/others/user/user" });
						}
					}, 1500);
				} else {
					uni.showModal({ title: '注册失败', content: res.msg, showCancel: false });
				}
			}).catch(err => {
				uni.hideLoading();
				this.is_loading = false;
				uni.showToast({ title: '网络请求失败', icon: 'none' });
			});
		},

		goToLogin() {
			uni.navigateBack();
		},

		openPrivacy() {
			let url = this.$util.getSetting('privacy_url') || getApp().globalData.domain + '/privacy.html';
			uni.setStorageSync('temp_url', url);
			this.$T.go('/others/web/web');
		},
		openProtocol() {
			let url = this.$util.getSetting('protocol_url') || getApp().globalData.domain + '/protocol.html';
			uni.setStorageSync('temp_url', url);
			this.$T.go('/others/web/web');
		},

		wechatLogin() {
			let auth_code = this.$http.getUrlParam('auth_code');
			if (!auth_code) {
				// 同 login.vue:授权整页刷新会丢内存里的回跳地址,先写回缓存
				if (this.backUrl) uni.setStorageSync('login_before_url', this.backUrl);
				let base64Url = Base64.encode(window.location.href);
				window.location.href = getApp().globalData.apiroot + '/wechat/auth/wechat&redirect_url=' + base64Url + "&mall_id=" + this.$http.getMallId();
			} else {
				this.$request({
					url: '/api/wechat/wechat-info',
					data: { auth_code: auth_code },
					method: 'post'
				}).then(res => {
					if (res.code == 0 && res.data.info) {
						uni.setStorageSync('WECHAT_USER_INFO', res.data.info);
						// 同步赋值给表单
						this.form.openId = res.data.info.openid;
						this.form.openid = res.data.info.openid;
						this.form.unionId = res.data.info.unionid;
					}
				}).catch(() => { });
			}
		}
	}
};
</script>

<style lang="scss" scoped>
/* --- 样式重置 --- */
.register-container {
	width: 100vw;
	min-height: 100vh;
	background-color: #ffffff;
	position: relative;
	overflow: hidden;
	display: flex;
	flex-direction: column;
	/* CSS 变量 */
	--theme-color: #D71F1F;
	--theme-light: rgba(215, 31, 31, 0.08);
}

.ph-style {
	color: #ccc;
	font-weight: 400;
}

/* 导航栏 */
.custom-nav-bar {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	z-index: 999;
	pointer-events: none;

	.nav-left {
		position: absolute;
		display: flex;
		align-items: center;
		justify-content: center;
		margin-left: 20rpx;
		border: 1px solid rgba(0, 0, 0, 0.1);
		border-radius: 32px;
		width: 60px;
		background: rgba(255, 255, 255, 0.8);
		pointer-events: auto;
	}
}

/* --- 1. 背景光效 (与登录页一致) --- */
.ambient-bg {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	z-index: 0;
	pointer-events: none;

	.orb {
		position: absolute;
		border-radius: 50%;
		filter: blur(100rpx);
		opacity: 0.5;
	}

	.orb-1 {
		width: 360rpx;
		height: 360rpx;
		background: var(--theme-light);
		top: -100rpx;
		right: -80rpx;
	}

	.orb-2 {
		width: 440rpx;
		height: 440rpx;
		background: var(--theme-light);
		top: 20%;
		left: -160rpx;
	}
}

/* --- 主内容区 --- */
.main-content {
	flex: 1;
	position: relative;
	z-index: 1;
	padding: 40rpx 60rpx;
	display: flex;
	flex-direction: column;
}

/* --- 2. 头部 Header --- */
.header-section {
	margin-top: 20rpx;
	margin-bottom: 40rpx;

	.brand-logo {
		display: flex;
		align-items: center;
		margin-bottom: 24rpx;

		.brand-text {
			font-size: 32rpx;
			font-weight: 900;
			letter-spacing: 2rpx;
			margin-left: 12rpx;
		}
	}

	.title {
		font-size: 60rpx;
		font-weight: 800;
		color: #1a1a1a;
		margin-bottom: 12rpx;
	}

	.subtitle {
		font-size: 28rpx;
		color: #86868b;
	}
}

/* --- 3. 表单区 --- */
.form-area {
	width: 100%;
	position: relative;
}

/* 推荐人胶囊 (极简) */
.referrer-tag {
	display: inline-flex;
	align-items: center;
	padding: 10rpx 24rpx;
	border-radius: 40rpx;
	margin-bottom: 30rpx;

	text {
		font-size: 24rpx;
		font-weight: 600;
		margin-left: 10rpx;
	}
}

/* 输入框 (无边框风格) */
.input-group {
	background: #F7F8FA;
	border-radius: 24rpx;
	height: 108rpx;
	display: flex;
	align-items: center;
	padding: 0 32rpx;
	margin-bottom: 24rpx;
	transition: all 0.3s;
	border: 1px solid transparent;

	.input-icon {
		margin-right: 20rpx;
		display: flex;
		align-items: center;
	}

	.input-field {
		flex: 1;
		height: 100%;
		font-size: 30rpx;
		color: #333;
	}

	/* 反馈：Active时变白 */
	&:active {
		background: #fff;
		border-color: transparent;
	}
}

/* 小按钮 (一键获取) */
.mini-btn {
	margin: 0;
	padding: 0 20rpx;
	height: 56rpx;
	line-height: 56rpx;
	font-size: 24rpx;
	font-weight: 600;
	border-radius: 28rpx;
	border: none;

	&::after {
		border: none;
	}
}

/* 验证码文字按钮 */
.code-btn {
	font-size: 28rpx;
	font-weight: 600;
	padding: 20rpx;
	white-space: nowrap;

	&.disabled {
		opacity: 0.5;
	}
}

/* 切换模式 (纯文字) */
.switch-row {
	display: flex;
	justify-content: flex-end;
	margin-top: -10rpx;
	margin-bottom: 30rpx;

	.switch-btn {
		display: flex;
		align-items: center;
		padding: 10rpx 0;

		.active-text {
			font-size: 26rpx;
			font-weight: 500;
			margin-left: 8rpx;
		}

		.gray-text {
			font-size: 26rpx;
			color: #999;
		}
	}
}

/* 注册大按钮 */
.btn-primary {
	width: 100%;
	height: 100rpx;
	background: var(--theme-color);
	color: #fff;
	border-radius: 50rpx;
	font-size: 32rpx;
	font-weight: 600;
	margin-top: 20rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	border: none;
	box-shadow: 0 16rpx 40rpx var(--theme-light);

	&::after {
		border: none;
	}
}

.btn-hover {
	opacity: 0.9;
	transform: scale(0.98);
}

/* --- 4. 底部 Footer --- */
.footer-area {
	margin-top: auto;
	padding-bottom: 40rpx;
	text-align: center;
}

.agreement-box {
	display: flex;
	align-items: flex-start;
	justify-content: center;
	line-height: 1.5;
	margin-bottom: 30rpx;
}

.checkbox {
	width: 32rpx;
	height: 32rpx;
	border: 2rpx solid #cccccc;
	border-radius: 50%;
	margin-right: 12rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	margin-top: 4rpx;
	transition: 0.2s;

	&.checked {
		border-color: transparent;
	}
}

.agree-text {
	font-size: 24rpx;
	color: #bbb;
	text-align: left;

	.link {
		color: #666;
	}
}

.login-link {
	font-size: 28rpx;
	color: #86868b;

	.highlight {
		margin-left: 10rpx;
		font-weight: 600;
	}
}

/* 抖动动画 */
.shake-anim {
	animation: shake 0.5s cubic-bezier(.36, .07, .19, .97) both;
}

@keyframes shake {

	10%,
	90% {
		transform: translateX(-2rpx);
	}

	20%,
	80% {
		transform: translateX(4rpx);
	}

	30%,
	50%,
	70% {
		transform: translateX(-8rpx);
	}

	40%,
	60% {
		transform: translateX(8rpx);
	}
}
</style>