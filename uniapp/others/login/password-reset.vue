<template>
	<tui-page>
		<view class="reset-container" :style="containerStyle">
			
			<view class="custom-nav-bar" :style="{ height: navBarHeight + 'px', paddingTop: statusBarHeight + 'px' }">
				<view class="nav-left" :style="{ height: menuButtonHeight + 'px', top: menuButtonTop + 'px' }" @click="handleNavBack">
					<tui-icon name="arrowleft" :size="24" color="#000"></tui-icon>
				</view>
			</view>
			<view :style="{ height: navBarHeight + 'px' }"></view>
			<view class="ambient-bg">
				<view class="orb orb-1"></view>
				<view class="orb orb-2"></view>
			</view>
	
			<view class="main-content">
				
				<view class="header-section fade-in">
					<view class="brand-logo">
						<tui-icon name="shield" :size="22" :color="themeColor"></tui-icon>
						<text class="brand-text" :style="textThemeStyle">SECURITY</text>
					</view>
					<view class="title">重置密码</view>
					<view class="subtitle">验证身份以设置新的安全密码</view>
				</view>
	
				<view class="form-area fade-in delay-1">
					
					<view class="input-group">
						<view class="input-icon"><tui-icon name="mobile" :size="20" color="#bbb"></tui-icon></view>
						<input class="input-field" type="number" v-model="form.mobile" :disabled="form.type === 2" maxlength="11"
							placeholder="请输入手机号" placeholder-class="ph-style" />
						
						<button v-if="form.type === 2" class="mini-btn"
							:style="miniBtnStyle"
							open-type="getPhoneNumber|agreePrivacyAuthorization"
							@getphonenumber="getPhoneNumber">
							一键获取
						</button>
					</view>
	
					<!-- 「使用手机号验证」靠 getPhoneNumber,只有小程序有;原来 H5/App 也显示,点了没用 -->
					<!-- #ifdef MP-WEIXIN -->
					<view class="switch-row">
						<view v-if="form.type === 1" class="switch-btn" @click="useType(2)">
							<text class="active-text" :style="textThemeStyle">使用手机号验证</text>
						</view>
						<view v-if="form.type === 2" class="switch-btn" @click="useType(1)">
							<text class="gray-text">手动输入手机号码</text>
						</view>
					</view>
					<!-- #endif -->
					<view class="input-group" v-if="form.type === 1">
						<view class="input-icon"><tui-icon name="shield" :size="20" color="#bbb"></tui-icon></view>
						<input class="input-field" type="number" v-model="form.code" maxlength="6"
							placeholder="请输入验证码" placeholder-class="ph-style" />
						
						<view class="code-btn"
							:class="{'disabled': is_send_code}"
							:style="codeBtnStyle"
							@click="getCode">
							{{ is_send_code ? `${sec}s` : '获取验证码' }}
						</view>
					</view>

					<view class="input-group">
						<view class="input-icon"><tui-icon name="pwd" :size="20" color="#bbb"></tui-icon></view>
						<input class="input-field" type="password" v-model="form.password"
							placeholder="请设置新密码 (6-20位)" placeholder-class="ph-style" />
					</view>
	
					<button class="btn-primary" hover-class="btn-hover" @click="submit">
						确认修改
					</button>
	
				</view>

				<view class="footer-area fade-in delay-2">
					<view class="back-link" @click="handleNavBack">
						<tui-icon name="arrowleft" :size="14" color="#666"></tui-icon>
						<text>返回登录</text>
					</view>
				</view>
			</view>

		</view>
	</tui-page>
</template>

<script>
	import { mapState } from 'vuex';

	export default {
		data() {
			return {
				is_loading: false,
				sec: 180,
				is_send_code: false,
				setting: null,
				form: {
					mobile: '',
					password: '',
					code: '',
					type: 1, // 1: 手动输入, 2: 微信获取
					phone_ticket: '', // type=2:手机号授权接口给的一次性凭证
				},
				// 导航栏相关
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
				// 生成淡色背景 (RGBA)
				return this.hexToRgba(this.themeColor, 0.08);
			},
			
			// --- [样式兼容修复] ---
			// 将复杂的对象样式改为字符串返回，完美兼容小程序
			containerStyle() {
				return `--theme-color: ${this.themeColor}; --theme-light: ${this.themeColorLight};`;
			},
			textThemeStyle() {
				return `color: ${this.themeColor};`;
			},
			miniBtnStyle() {
				return `color: ${this.themeColor}; background-color: ${this.themeColorLight};`;
			},
			codeBtnStyle() {
				return this.is_send_code ? 'color: #999;' : `color: ${this.themeColor};`;
			}
		},
		onLoad(options) {
			uni.hideLoading();
			let setting = uni.getStorageSync('MALL_SETTING');
			if (setting) {
				this.setting = setting;
			}
			
			// #ifdef MP-WEIXIN
			this.initNavBar();
			// #endif
		},
		methods: {
			// 初始化导航栏
			initNavBar() {
				const sysInfo = uni.getSystemInfoSync();
				// || 20 是必须的:某些 Android 机型和 App 端 WebView 初始化早期会返回 undefined,
			// "undefinedpx" 让 paddingTop 整个失效 —— 返回按钮会顶进状态栏跟信号/时间重叠。
			// data 里那个默认 20 挡不住,因为这一行会把它覆盖掉。
			this.statusBarHeight = sysInfo.statusBarHeight || 20;
				try {
					const menuButtonInfo = uni.getMenuButtonBoundingClientRect();
					if(menuButtonInfo) {
						this.menuButtonHeight = menuButtonInfo.height;
						this.menuButtonTop = menuButtonInfo.top;
						const contentHeight = (menuButtonInfo.top - sysInfo.statusBarHeight) * 2 + menuButtonInfo.height;
						this.navBarHeight = sysInfo.statusBarHeight + contentHeight;
					}
				} catch(e) {
					this.navBarHeight = 64;
				}
				const pages = getCurrentPages();
				this.canBack = pages.length > 1;
			},
			
			// 返回逻辑
			handleNavBack() {
				const pages = getCurrentPages();
				if (pages.length > 1) {
					uni.navigateBack();
				} else {
					uni.reLaunch({
						url: '/others/login/login' 
					});
				}
			},

			// 发送验证码
			getCode() {
				if (this.is_send_code) return;
				if (!this.form.mobile) return uni.showToast({title: '请输入手机号', icon: 'none'});
				if (!this.$util.isMobile(this.form.mobile)) return uni.showToast({title: '手机号格式错误', icon: 'none'});
				
				this.is_send_code = true;
				if (this.is_loading) return;
				
				this.is_loading = true;
				this.$request({
					url: "/api/login/sms-code",
					data: { mobile: this.form.mobile, type: 'reset' }, 
					method: 'post'
				}).then(res => {
					this.is_loading = false;
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
				}).catch(() => {
					this.is_loading = false;
					this.is_send_code = false;
				});
			},

			useType(e) {
				this.form.type = e;
			this.form.phone_ticket = '';
				if (this.form.type == 2) {
					this.form.mobile = '';
				}
			},

			// 微信一键获取手机号
			getPhoneNumber(e) {
				if(e.detail.errMsg && e.detail.errMsg.indexOf("fail") !== -1) {
					uni.showToast({ title: '已取消授权', icon: 'none' });
					return;
				}
				uni.showLoading({ title:'获取中...' });
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
						uni.showModal({ title: '提示', content: '获取手机号失败' });
					}
				}).catch(() => {
					uni.hideLoading();
				});
			},

			// 提交修改
			submit() {
				if (this.is_loading) return;
				if (!this.form.mobile) return uni.showToast({title: '请输入手机号', icon: 'none'});
				if (!this.form.password) return uni.showToast({title: '请输入新密码', icon: 'none'});
				if (this.form.password.length < 6) return uni.showToast({ title: '密码不能少于6位', icon: 'none' });
				if (this.form.type === 1 && !this.form.code) return uni.showToast({title: '请输入验证码', icon: 'none'});

				this.is_loading = true;
				uni.showLoading({ title: '提交中...' });
				
				this.$request({
					url: "/api/login/password-reset",
					data: this.form,
					method: 'post'
				}).then(res => {
					uni.hideLoading();
					this.is_loading = false;
					if (res.code == 0) {
						uni.showToast({ title: '密码重置成功', icon: 'success' });
						setTimeout(() => {
							uni.navigateBack();
						}, 1500);
					} else {
						uni.showModal({ title: '提示', content: res.msg, showCancel: false });
					}
				}).catch(() => {
					uni.hideLoading();
					this.is_loading = false;
				});
			},
			
			// 16进制转RGBA
		}
	};
</script>

<style lang="scss" scoped>
	/* --- 样式重置 --- */
	.reset-container {
		width: 100vw;
		min-height: 100vh;
		background-color: #ffffff;
		position: relative;
		overflow: hidden;
		display: flex;
		flex-direction: column;
		/* CSS 变量默认值 */
		--theme-color: #D71F1F;
		--theme-light: rgba(215, 31, 31, 0.08);
	}
	
	.ph-style { color: #ccc; font-weight: 400; }

	/* 导航栏 */
	.custom-nav-bar {
		position: fixed; top: 0; left: 0; width: 100%; z-index: 999; pointer-events: none;
		.nav-left {
			position: absolute; display: flex; align-items: center; justify-content: center;
			margin-left: 20rpx; border: 1px solid rgba(0,0,0,0.1); border-radius: 32px;
			width: 60px; background: rgba(255,255,255,0.8); pointer-events: auto;
		}
	}

	/* --- 1. 背景光效 (统一风格) --- */
	.ambient-bg {
		position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; pointer-events: none;
		.orb { position: absolute; border-radius: 50%; filter: blur(100rpx); opacity: 0.5; }
		.orb-1 { width: 360rpx; height: 360rpx; background: var(--theme-light); top: -100rpx; right: -80rpx; }
		.orb-2 { width: 440rpx; height: 440rpx; background: var(--theme-light); top: 20%; left: -160rpx; }
	}

	/* --- 主内容区域 --- */
	.main-content {
		flex: 1; position: relative; z-index: 1; padding: 40rpx 60rpx;
		display: flex; flex-direction: column;
	}

	/* --- 2. 头部 Header --- */
	.header-section {
		margin-top: 20rpx; margin-bottom: 40rpx;
		.brand-logo {
			display: flex; align-items: center; margin-bottom: 24rpx;
			.brand-text { font-size: 32rpx; font-weight: 900; letter-spacing: 2rpx; margin-left: 12rpx; }
		}
		.title { font-size: 60rpx; font-weight: 800; color: #1a1a1a; margin-bottom: 12rpx; }
		.subtitle { font-size: 28rpx; color: #86868b; }
	}

	/* --- 3. 表单区域 --- */
	.form-area { width: 100%; position: relative; }

	/* 现代输入框 (无边框风格，对齐登录页) */
	.input-group {
		background: #F7F8FA; border-radius: 24rpx; height: 108rpx;
		display: flex; align-items: center; padding: 0 32rpx;
		margin-bottom: 24rpx; transition: all 0.3s;
		border: 1px solid transparent;

		.input-icon { margin-right: 20rpx; display: flex; align-items: center; }
		.input-field { flex: 1; height: 100%; font-size: 30rpx; color: #333; }
		
		/* 反馈 */
		&:active { background: #fff; border-color: transparent; }
	}

	/* 小按钮 (一键获取) */
	.mini-btn {
		margin: 0; padding: 0 20rpx; height: 56rpx; line-height: 56rpx;
		font-size: 24rpx; font-weight: 600; border-radius: 28rpx; border: none;
		&::after { border: none; }
	}

	/* 验证码文字按钮 */
	.code-btn {
		font-size: 28rpx; font-weight: 600; padding: 20rpx;
		white-space: nowrap;
		&.disabled { opacity: 0.5; }
	}

	/* 切换模式 (纯文字) */
	.switch-row {
		display: flex; justify-content: flex-end; margin-top: -10rpx; margin-bottom: 30rpx;
		.switch-btn {
			display: flex; align-items: center; padding: 10rpx 0;
			.active-text { font-size: 26rpx; font-weight: 500; margin-left: 8rpx; }
			.gray-text { font-size: 26rpx; color: #999; }
		}
	}

	/* 提交大按钮 */
	.btn-primary {
		width: 100%; height: 100rpx; background: var(--theme-color); color: #fff;
		border-radius: 50rpx; font-size: 32rpx; font-weight: 600; margin-top: 40rpx;
		display: flex; align-items: center; justify-content: center; border: none;
		box-shadow: 0 16rpx 40rpx var(--theme-light);
		&::after { border: none; }
	}
	.btn-hover { opacity: 0.9; transform: scale(0.98); }

	/* --- 4. 底部 Footer --- */
	.footer-area {
		margin-top: auto; padding-bottom: 40rpx; text-align: center;
	}

	.back-link {
		display: flex; align-items: center; justify-content: center;
		padding: 20rpx; opacity: 0.8;
		text { font-size: 28rpx; color: #666; margin-left: 8rpx; font-weight: 500; }
		&:active { opacity: 1; }
	}

	/* 动画 */
	.fade-in { animation: fadeIn 0.4s ease-out forwards; opacity: 0; }
	.delay-1 { animation-delay: 0.1s; }
	.delay-2 { animation-delay: 0.2s; }
	@keyframes fadeIn { from { opacity: 0; transform: translateY(20rpx); } to { opacity: 1; transform: translateY(0); } }
</style>