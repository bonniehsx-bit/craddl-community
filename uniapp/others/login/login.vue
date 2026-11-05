<template>
	<tui-page>
		<view class="login-container" :style="containerStyle">

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
						<tui-icon name="fire-fill" :size="20" :color="themeColor"></tui-icon>
						<text class="brand-text" :style="{ color: themeColor }">{{ name || 'BRAND' }}</text>
					</view>
					<view class="title">欢迎登录</view>
					<view class="subtitle">登录以开启您的会员权益</view>
				</view>

				<view class="form-area">

					<view v-if="loginMethod === 'buttons' && isWechat" class="mode-box fade-in">

						<view class="status-card">
							<view class="avatar-box">
								<tui-icon name="people" :size="30" color="#ccc"></tui-icon>
							</view>
							<view class="user-text">
								<text class="h3">Hi，新朋友</text>
								<text class="p">当前未登录</text>
							</view>
						</view>

						<view class="btn-group">
							<button v-if="!is_agree" class="btn-primary" hover-class="btn-hover"
								@click="checkAgreement">
								<text>本机号码一键登录</text>
							</button>

							<button v-else class="btn-primary" hover-class="btn-hover" open-type="getPhoneNumber"
								@getphonenumber="oneClickLogin">
								<text>本机号码一键登录</text>
							</button>

							<button class="btn-secondary" hover-class="btn-hover-gray"
								@click="switchLoginMethod('password')">
								使用账号密码登录
							</button>
						</view>
					</view>
					<view v-if="loginMethod === 'password'" class="mode-box fade-in">
						<view class="input-group">
							<view class="input-row">
								<view class="icon-wrap"><tui-icon name="mobile" :size="20" color="#bbb"></tui-icon>
								</view>
								<input class="input-field" v-model="form.mobile" type="number" maxlength="11"
									placeholder="请输入手机号" placeholder-class="placeholder-style" />
							</view>
							<view class="input-row">
								<view class="icon-wrap"><tui-icon name="pwd" :size="20" color="#bbb"></tui-icon></view>
								<input class="input-field" v-model="form.password" type="password" placeholder="请输入密码"
									placeholder-class="placeholder-style" />
							</view>
						</view>

						<view class="forgot-link" @click="goToResetPassword">
							<text>忘记密码？</text>
						</view>

						<button class="btn-primary" hover-class="btn-hover" @click="submitPasswordLogin">
							登 录
						</button>

						<!-- #ifdef MP-WEIXIN -->

						<button class="btn-secondary" hover-class="btn-hover-gray"
							@click="switchLoginMethod('buttons')">
							返回一键登录
						</button>
						<!-- #endif -->
					</view>
				</view>

				<view class="footer-area">
					<view class="register-entry">
						<text>还没有账号？</text>
						<text class="reg-btn" :style="'color:' + themeColor" @click="goToRegister">立即注册</text>
					</view>

					<view class="agreement-box" :class="{ 'shake-anim': isShake }" @click="toggleAgree">
						<view class="checkbox" :class="{ 'checked': is_agree }" :style="checkboxStyle">
							<tui-icon v-if="is_agree" name="check" :size="10" color="#fff"></tui-icon>
						</view>
						<view class="agree-text">
							我已阅读并同意 <text class="link" @click.stop="openProtocol">《用户协议》</text> 和 <text class="link"
								@click.stop="openPrivacy">《隐私条款》</text>
						</view>
					</view>
				</view>
			</view>
		</view>
	</tui-page>
</template>

<script>
import {
	Base64
} from '@/jssdk/base64.min.js';
import { loginByMpPhone } from '@/utils/mpPhoneLogin.js';
import {
	mapActions,
	mapState
} from 'vuex';
import wxAuth from '@/utils/wxAuth.js';

export default {
	data() {
		return {
			// 登录成功后回哪一页。onLoad 时从缓存取出并立刻清掉,见 onLoad
			backUrl: '',
			name: '',
			is_agree: false,
			isShake: false,
			loginMethod: 'buttons',
			isWechat: false,
			form: {
				mobile: '',
				password: '',
				code: '',
				openId: '',
				openid: '',
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
		// [修复点 4] 新增计算属性：处理复选框样式
		checkboxStyle() {
			if (this.is_agree) {
				// 返回字符串形式的样式，兼容性最好
				return `background-color: ${this.themeColor}; border-color: ${this.themeColor};`;
			}
			return '';
		},
		// [修复点 5] 新增计算属性：处理根容器样式 (CSS变量)
		containerStyle() {
			// 将 CSS 变量以字符串形式注入，避免小程序解析对象失败
			return `--theme-color: ${this.themeColor}; --theme-light: ${this.themeColorLight};`;
		}
	},
	onLoad(options) {
		uni.hideLoading();
		// 进页面就把回跳地址从缓存里取走:放弃登录(返回、关掉)时它不会再残留,
		// 否则很久以后从「我的」等入口登录,会被甩回当初那个页面
		this.backUrl = uni.getStorageSync('login_before_url') || '';
		uni.removeStorageSync('login_before_url');
		this.name = this.$util.getSetting('name');
		// #ifdef MP-WEIXIN
		this.isWechat = true;
		this.initNavBar();
		// #endif

		// #ifdef H5
		if (this.$wechatSdk.isWechat()) {
			this.isWechat = true;
			this.loginMethod = 'password'; // H5一律密码登录:本机号码一键登录仅微信小程序支持
			const wechatInfo = uni.getStorageSync('WECHAT_USER_INFO');
			const auth_code = this.$http.getUrlParam('auth_code');
			if (auth_code && !wechatInfo) {
				this.exchangeAuthCodeForUserInfo(auth_code);
				return;
			} else if (!wechatInfo) {
				this.redirectToWechatAuth();
			}
		} else {
			this.isWechat = false;
			this.loginMethod = 'password';
		}
		// #endif

		// #ifdef APP-PLUS
		this.isWechat = false;
		this.loginMethod = 'password';
		// #endif
	},
	methods: {
		...mapActions('user', ['loginSuccess']),
		initNavBar() {
			const sysInfo = uni.getSystemInfoSync();
			// || 20 是必须的:某些 Android 机型和 App 端 WebView 初始化早期会返回 undefined,
			// "undefinedpx" 让 paddingTop 整个失效 —— 返回按钮会顶进状态栏跟信号/时间重叠。
			// data 里那个默认 20 挡不住,因为这一行会把它覆盖掉。
			this.statusBarHeight = sysInfo.statusBarHeight || 20;
			// 兼容处理：防止某些环境获取不到 menuButton
			try {
				const menuButtonInfo = uni.getMenuButtonBoundingClientRect();
				if (menuButtonInfo) {
					this.menuButtonHeight = menuButtonInfo.height;
					this.menuButtonTop = menuButtonInfo.top;
					const contentHeight = (menuButtonInfo.top - sysInfo.statusBarHeight) * 2 + menuButtonInfo.height;
					this.navBarHeight = sysInfo.statusBarHeight + contentHeight;
				}
			} catch (e) {
				this.navBarHeight = 64; // 降级处理
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
		goToResetPassword() {
			this.$T.go('/others/login/password-reset');
		},
		redirectToWechatAuth() {
			// 微信授权会整页刷新回到登录页,内存里的回跳地址会丢,先写回缓存让下一次 onLoad 再取
			if (this.backUrl) uni.setStorageSync('login_before_url', this.backUrl);
			const base64Url = Base64.encode(window.location.href);
			const wechatAuthUrl = getApp().globalData.apiroot + '/wechat/auth/wechat&redirect_url=' + base64Url +
				"&mall_id=" + this.$http.getMallId();
			window.location.href = wechatAuthUrl;
		},
		exchangeAuthCodeForUserInfo(auth_code) {
			// #ifdef H5
			this.$request({
				url: '/api/wechat/wechat-info',
				method: 'POST',
				data: {
					auth_code
				}
			}).then(res => {
				if (res.code == 0 && res.data.info) {
					// 将获取到的 H5 openid 信息死死存入本地缓存，供所有页面(如 pay.vue)优先静默读取
					uni.setStorageSync('WECHAT_USER_INFO', res.data.info);
					// 同步给当前页面的登录表单 (以防后续点击一键登录还要用到)
					this.form.openId = res.data.info.openid;
					this.form.openid = res.data.info.openid;
					this.form.unionId = res.data.info.unionid;
				}
			}).catch(() => { });
			// #endif
		},
		attachWechatInfo() {
			// #ifdef H5
			const wechatInfo = uni.getStorageSync('WECHAT_USER_INFO');
			if (wechatInfo) {
				this.form.openId = wechatInfo.openid;
				this.form.openid = wechatInfo.openid;
				this.form.unionId = wechatInfo.unionid;
			}
			// openid/unionid 以 wxAuth 的【当前微信号】为准，覆盖可能过期的 WECHAT_USER_INFO
			const freshOpenid = wxAuth.getOpenid();
			if (freshOpenid) {
				this.form.openId = freshOpenid;
				this.form.openid = freshOpenid;
				const u = wxAuth.getUnionid();
				if (u) this.form.unionId = u;
			}
			// #endif
		},
		switchLoginMethod(method) {
			this.loginMethod = method;
		},
		oneClickLogin(e) {
			if (!this.checkAgreement()) return;
			// #ifdef MP-WEIXIN
			// 和首页登录弹窗共用 utils/mpPhoneLogin.js,别再各写一遍
			uni.showLoading({ title: '正在登录' });
			loginByMpPhone(e.detail, this.$store, this.$request).then(() => {
				uni.hideLoading();
				uni.showToast({ title: '登录成功', icon: 'success' });
				setTimeout(() => this.goAfterLogin(), 1200);
			}).catch((err) => {
				uni.hideLoading();
				const msg = (err && err.message) || '登录失败';
				if (msg === '已取消授权') {
					uni.showToast({ title: msg, icon: 'none' });
				} else {
					uni.showModal({ title: '登录失败', content: msg, showCancel: false });
				}
			});
			// #endif
		},
		submitPasswordLogin() {
			if (!this.checkAgreement()) return;
			if (!this.form.mobile) return uni.showToast({
				title: '请输入手机号',
				icon: 'none'
			});
			if (!this.form.password) return uni.showToast({
				title: '请输入密码',
				icon: 'none'
			});

			uni.showLoading({
				title: '正在登录...'
			});
			this.attachWechatInfo();

			// #ifdef MP-WEIXIN
			uni.login({
				onlyAuthorize: true,
				provider: 'weixin',
				success: (e) => {
					this.form.code = e.code
					this.$request({
						url: "/api/login/mobile",
						method: 'POST',
						data: this.form,
					})
						.then(this.handleLoginResponse).catch(this.handleLoginError);
				},
				fail: () => {
					uni.hideLoading();
					uni.showToast({
						title: '微信环境异常',
						icon: 'none'
					});
				}
			})
			//#endif

			// #ifndef MP-WEIXIN
			this.$request({
				url: "/api/login/mobile",
				method: 'POST',
				data: this.form,
			})
				.then(this.handleLoginResponse).catch(this.handleLoginError);
			// #endif
		},
		handleLoginResponse(res) {
			uni.hideLoading();
			if (res.code == 0) {
				this.loginSuccess({
					token: res.data.access_token
				}).then(() => {
					uni.showToast({
						title: '登录成功',
						icon: 'success'
					});
					setTimeout(() => this.goAfterLogin(), 1500);
				});
			} else {
				uni.showModal({
					title: '登录失败',
					content: res.msg || '请稍后再试',
					showCancel: false
				});
			}
		},
		/**
		 * 登录成功后去哪。原来一律 reLaunch(回跳地址或首页):页面栈清空,
		 * 从「我的」、商品页等地方来登录的,登完被扔回首页。
		 * 现在:上一页就是要回去的那页(或者没指定回跳地址、但有上一页)→ 直接返回;
		 * 否则才 reLaunch 到回跳地址 / 首页。
		 */
		goAfterLogin() {
			const pages = getCurrentPages();
			const prev = pages.length > 1 ? pages[pages.length - 2] : null;
			const backPath = (this.backUrl || '').split('?')[0];
			if (prev && (!backPath || '/' + prev.route === backPath)) {
				uni.navigateBack();
				return;
			}
			uni.reLaunch({ url: this.backUrl || '/pages/index/index' });
		},
		handleLoginError(err) {
			uni.hideLoading();
			uni.showToast({
				title: '请求失败',
				icon: 'none'
			});
		},
		checkAgreement() {
			if (!this.is_agree) {
				this.isShake = true;
				setTimeout(() => {
					this.isShake = false;
				}, 500);
				uni.showToast({
					title: '请阅读并同意用户协议和隐私条款',
					icon: 'none',
					duration: 2000
				});
				return false;
			}
			return true;
		},
		goToRegister() {
			// 缓存已在 onLoad 清掉,回跳地址靠参数带给注册页
			this.$T.go('/others/login/sign' + (this.backUrl ? '?redirect=' + encodeURIComponent(this.backUrl) : ''));
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
		}
	}
};
</script>

<style lang="scss" scoped>
/* --- 样式重置与基础设置 --- */
.login-container {
	width: 100vw;
	min-height: 100vh;
	background-color: #ffffff;
	position: relative;
	overflow: hidden;
	display: flex;
	flex-direction: column;
	/* 默认值，会被 JS 注入覆盖 */
	--theme-color: #D71F1F;
	--theme-light: rgba(215, 31, 31, 0.08);
}

.placeholder-style {
	color: #cccccc;
	font-weight: 400;
}

/* 微信小程序自定义导航栏 */
.custom-nav-bar {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	z-index: 999;
	pointer-events: none;
	/* 让点击穿透，除了按钮 */

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
		/* 恢复按钮点击 */
	}
}

/* --- 1. 背景氛围光 --- */
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
	margin-top: 40rpx;
	margin-bottom: 60rpx;

	.brand-logo {
		display: flex;
		align-items: center;
		margin-bottom: 24rpx;

		.brand-text {
			font-size: 32rpx;
			font-weight: 900;
			letter-spacing: 2rpx;
			margin-left: 12rpx;
			text-transform: uppercase;
		}
	}

	.title {
		font-size: 60rpx;
		font-weight: 800;
		color: #1a1a1a;
		margin-bottom: 12rpx;
		line-height: 1.2;
	}

	.subtitle {
		font-size: 28rpx;
		color: #86868b;
	}
}

/* --- 3. 表单/交互区 --- */
.form-area {
	flex: 1;
	position: relative;
}

.mode-box {
	width: 100%;
}

/* 动画 */
.fade-in {
	animation: fadeIn 0.4s ease-out forwards;
}

@keyframes fadeIn {
	from {
		opacity: 0;
		transform: translateY(20rpx);
	}

	to {
		opacity: 1;
		transform: translateY(0);
	}
}

/* 状态卡片 */
.status-card {
	display: flex;
	align-items: center;
	background: rgba(255, 255, 255, 0.8);
	border: 1px solid rgba(0, 0, 0, 0.04);
	padding: 40rpx;
	border-radius: 32rpx;
	margin-bottom: 60rpx;
	box-shadow: 0 16rpx 48rpx rgba(0, 0, 0, 0.03);
	backdrop-filter: blur(10px);

	.avatar-box {
		width: 88rpx;
		height: 88rpx;
		background: #f2f2f2;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		margin-right: 28rpx;
	}

	.user-text {
		.h3 {
			font-size: 32rpx;
			font-weight: 700;
			color: #333;
			display: block;
		}

		.p {
			font-size: 24rpx;
			color: #999;
			margin-top: 4rpx;
			display: block;
		}
	}
}

/* 输入框样式 */
.input-group {
	margin-bottom: 30rpx;
}

.input-row {
	background: #F7F8FA;
	border-radius: 24rpx;
	height: 108rpx;
	display: flex;
	align-items: center;
	padding: 0 32rpx;
	margin-bottom: 24rpx;
	transition: all 0.3s;
	border: 1px solid transparent;

	.icon-wrap {
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

	/* 伪类在 UniApp 支持度良好 */
	&:active {
		background: #fff;
	}
}

/* Focus 态在 Vue 中通过动态 class 更稳，这里简化处理，依靠 active */

.forgot-link {
	text-align: right;
	margin-bottom: 50rpx;

	text {
		font-size: 26rpx;
		color: #666;
	}
}

/* 按钮组 */
.btn-group {
	margin-top: 20rpx;
}

.btn-primary {
	width: 100%;
	height: 100rpx;
	background: var(--theme-color);
	color: #fff;
	border-radius: 50rpx;
	font-size: 32rpx;
	font-weight: 600;
	display: flex;
	align-items: center;
	justify-content: center;
	box-shadow: 0 16rpx 40rpx var(--theme-light);
	margin-bottom: 32rpx;
	border: none;
	position: relative;

	&::after {
		border: none;
	}
}

.btn-secondary {
	width: 100%;
	height: 100rpx;
	background: transparent;
	color: #666;
	border: 1px solid #ddd;
	border-radius: 50rpx;
	font-size: 30rpx;
	font-weight: 500;
	display: flex;
	align-items: center;
	justify-content: center;

	&::after {
		border: none;
	}
}

.btn-hover {
	opacity: 0.9;
	transform: scale(0.98);
}

.btn-hover-gray {
	background-color: #f5f5f5;
}

/* --- 4. 底部 Footer --- */
.footer-area {
	margin-top: auto;
	padding-bottom: 40rpx;
	text-align: center;
}

.register-entry {
	margin-bottom: 40rpx;
	font-size: 28rpx;
	color: #86868b;

	.reg-btn {
		font-weight: 600;
		margin-left: 10rpx;
		padding: 10rpx;
	}
}

.agreement-box {
	display: flex;
	align-items: flex-start;
	justify-content: center;
	line-height: 1.5;
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
	transition: all 0.2s;
	flex-shrink: 0;
	margin-top: 4rpx;

	&.checked {
		border-color: transparent;
		/* 背景色由 inline-style 控制 */
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