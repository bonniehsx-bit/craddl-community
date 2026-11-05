<template>
	<tui-page :backgroundColor="'#F7F8FA'">
		<view class="bind-container" :style="{ '--theme-color': themeColor }">

			<!-- #ifndef H5 -->
			<view class="custom-navbar"
				:style="{ paddingTop: navBar.top + 'px', height: navBar.height + 'px' }">
				<view class="nav-btn"
					:style="{ height: navBar.height + 'px', width: navBar.height + 'px' }"
					@tap="handleNavBack">
					<view class="icon-back"></view>
				</view>
				<view class="nav-title" :style="{ height: navBar.height + 'px' }">
					{{ card_id ? '补充银行卡信息' : '添加银行卡' }}
				</view>
			</view>
			<!-- #endif -->

			<view class="header-bg">
				<view class="header-content" :style="{ paddingTop: (navBar.top + navBar.height + 20) + 'px' }">
					<view class="title-row">
						<text class="page-title">绑定银行卡</text>
						<view class="security-badge"
							:style="securityBadgeStyle">
							<text class="lock-icon" :style="{ borderColor: themeColor }"></text>
							<text>信息加密存储</text>
						</view>
					</view>
					<text class="page-desc">持牌快捷支付通道 · 资料金融级加密</text>
				</view>
			</view>

			<view class="content-wrapper fade-in-up">
				<view class="form-card">
					<view class="card-type-tabs">
						<view class="tab-item" :class="{ 'active': form.cDFlag === 'D' }"
							:style="form.cDFlag === 'D' ? tabActiveStyle : ''"
							@tap="form.cDFlag = 'D'">
							<text class="tab-label">储蓄卡</text>
						</view>
						<view class="tab-item" :class="{ 'active': form.cDFlag === 'C' }"
							:style="form.cDFlag === 'C' ? tabActiveStyle : ''"
							@tap="form.cDFlag = 'C'">
							<text class="tab-label">信用卡</text>
						</view>
					</view>

					<view class="form-row">
						<text class="label">持卡人姓名</text>
						<input class="input" v-model="form.userName" placeholder="请输入真实姓名" maxlength="20" />
					</view>
					<view class="form-row">
						<text class="label">身份证号</text>
						<input class="input" v-model="form.identityNo" placeholder="请输入身份证号" maxlength="18" />
					</view>
					<view class="form-row">
						<text class="label">银行卡号</text>
						<input class="input" type="number" v-model="form.bankCardNo" placeholder="请输入银行卡号" maxlength="20" />
					</view>
					<view class="form-row">
						<text class="label">预留手机号</text>
						<input class="input" type="number" v-model="form.mobile" placeholder="银行预留手机号" maxlength="11" />
					</view>

					<picker mode="date" :value="idBeginIso" :start="'1950-01-01'" :end="todayIso"
						@change="onIdBeginChange">
						<view class="form-row">
							<text class="label">证件开始日期</text>
							<text class="input value-text" :class="{ placeholder: !idBeginDisplay }">
								{{ idBeginDisplay || '请选择' }}
							</text>
							<text class="arrow">›</text>
						</view>
					</picker>
					<picker v-if="!isIdLong" mode="date" :value="idEndIso" :start="'2000-01-01'" :end="'2099-12-31'"
						@change="onIdEndChange">
						<view class="form-row">
							<text class="label">证件结束日期</text>
							<text class="input value-text" :class="{ placeholder: !idEndDisplay }">
								{{ idEndDisplay || '请选择' }}
							</text>
							<text class="arrow">›</text>
						</view>
					</picker>
					<view v-else class="form-row disabled">
						<text class="label">证件结束日期</text>
						<text class="input value-text">长期</text>
					</view>
					<view class="form-row id-long-row">
						<text class="label">证件长期有效</text>
						<view class="switch-wrap" @tap="toggleLong">
							<view class="switch-track"
								:style="{ background: isIdLong ? themeColor : '#DCDFE6' }">
								<view class="switch-dot" :class="{ on: isIdLong }"></view>
							</view>
						</view>
					</view>

					<template v-if="form.cDFlag === 'C'">
						<view class="form-row">
							<text class="label">有效期</text>
							<input class="input" type="number" v-model="form.validDate" placeholder="MMYY, 如 1228" maxlength="4" />
						</view>
						<view class="form-row">
							<text class="label">CVV2</text>
							<input class="input" type="number" password="true" v-model="form.cvv2" placeholder="卡背面末 3 位" maxlength="4" />
						</view>
					</template>

					<view class="form-row" v-if="smsSent">
						<text class="label">短信验证码</text>
						<input class="input" type="number" v-model="form.smsCode" placeholder="请输入验证码" maxlength="8" />
						<view class="sms-btn"
							:class="{ 'disabled': smsCountdown > 0 }"
							:style="smsCountdown === 0 ? smsBtnStyle : ''"
							@tap="smsCountdown === 0 && applyBind()">
							{{ smsCountdown > 0 ? (smsCountdown + 's') : '重新获取' }}
						</view>
					</view>
				</view>

				<view class="tips">
					<text>· 仅支持本人姓名的银行卡，且需为银行预留手机号</text>
					<text>· 卡号、身份证号、有效期等敏感信息通过加密通道上送</text>
				</view>
			</view>

			<view class="footer-bar">
				<view class="footer-content">
					<button v-if="!smsSent" class="submit-btn"
						:style="{ background: themeColor, boxShadow: btnShadow }"
						hover-class="btn-hover" @tap="applyBind">
						<text>获取短信验证码</text>
					</button>
					<button v-else class="submit-btn"
						:style="{ background: themeColor, boxShadow: btnShadow }"
						hover-class="btn-hover" @tap="verifyBind">
						<text>确认绑定</text>
					</button>
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
				redirect_url: '',
				// 支付网关类完整类名, 由 URL query 传入, 例: app\payments\FastPayQuickPayment
				gateway: '',
				navBar: { top: 20, height: 44 },
				form: {
					userName: '',
					identityNo: '',
					bankCardNo: '',
					mobile: '',
					cDFlag: 'D',
					validDate: '',
					cvv2: '',
					smsCode: '',
					idDocPeriodBegin: '', // yyyyMMdd
					idDocPeriodEnd: '',   // yyyyMMdd, 长期=29991231
				},
				isIdLong: false,
				card_id: 0, // 申请绑卡后返回的本地记录 ID
				smsSent: false,
				smsCountdown: 0,
				smsTimer: null,
				submitting: false,
			};
		},
		computed: {
			...mapState('mall', {
				themeColor: state => state.themeColor || '#E63D3D'
			}),
			btnShadow() {
				return `0 16rpx 40rpx -10rpx ${this.hexToRgba(this.themeColor, 0.45)}`;
			},
			securityBadgeStyle() {
				return `background: ${this.hexToRgba(this.themeColor, 0.08)}; color: ${this.themeColor};`;
			},
			tabActiveStyle() {
				return `color: ${this.themeColor}; border-color: ${this.themeColor};`;
			},
			smsBtnStyle() {
				return `color: ${this.themeColor};`;
			},
			idBeginDisplay() {
				return this.formatDateDisplay(this.form.idDocPeriodBegin);
			},
			idEndDisplay() {
				if (this.isIdLong) return '长期';
				return this.formatDateDisplay(this.form.idDocPeriodEnd);
			},
			idBeginIso() {
				return this.yyyymmddToIso(this.form.idDocPeriodBegin);
			},
			idEndIso() {
				return this.yyyymmddToIso(this.form.idDocPeriodEnd);
			},
			todayIso() {
				const d = new Date();
				const m = String(d.getMonth() + 1).padStart(2, '0');
				const day = String(d.getDate()).padStart(2, '0');
				return `${d.getFullYear()}-${m}-${day}`;
			},
		},
		onLoad(options) {
			this.initNavBar();
			this.gateway = decodeURIComponent(options.gateway || '');
			if (!this.gateway) {
				uni.showToast({ title: '缺少支付网关参数', icon: 'none' });
				setTimeout(() => uni.navigateBack(), 1500);
			}
			if (options.redirect) {
				this.redirect_url = decodeURIComponent(options.redirect);
			}
		},
		beforeDestroy() {
			if (this.smsTimer) clearInterval(this.smsTimer);
		},
		methods: {
			initNavBar() {
				const sysInfo = uni.getSystemInfoSync();
				// #ifdef MP
				const menuBtnInfo = uni.getMenuButtonBoundingClientRect();
				this.navBar = { top: menuBtnInfo.top, height: menuBtnInfo.height };
				// #endif
				// #ifndef MP
				this.navBar = { top: sysInfo.statusBarHeight || 20, height: 44 };
				// #endif
				// #ifdef H5
				this.navBar = { top: 0, height: 20 };
				// #endif
			},
			handleNavBack() {
				uni.navigateBack({ delta: 1, fail: () => uni.switchTab({ url: '/pages/index/index' }) });
			},
			hexToRgba(hex, opacity) {
				let c;
				if (/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)) {
					c = hex.substring(1).split('');
					if (c.length == 3) c = [c[0], c[0], c[1], c[1], c[2], c[2]];
					c = '0x' + c.join('');
					return 'rgba(' + [(c >> 16) & 255, (c >> 8) & 255, c & 255].join(',') + ',' + opacity + ')';
				}
				return 'rgba(0,0,0,0.2)';
			},
			validateBasic() {
				if (!this.form.userName) { uni.showToast({ title: '请输入持卡人姓名', icon: 'none' }); return false; }
				if (!/^\d{15}(\d{2}[0-9Xx])?$/.test(this.form.identityNo)) {
					uni.showToast({ title: '身份证号格式错误', icon: 'none' });
					return false;
				}
				if (!/^\d{12,19}$/.test(this.form.bankCardNo)) {
					uni.showToast({ title: '银行卡号格式错误', icon: 'none' });
					return false;
				}
				if (!/^1[3-9]\d{9}$/.test(this.form.mobile)) {
					uni.showToast({ title: '手机号格式错误', icon: 'none' });
					return false;
				}
				if (!/^\d{8}$/.test(this.form.idDocPeriodBegin)) {
					uni.showToast({ title: '请选择证件开始日期', icon: 'none' });
					return false;
				}
				if (!/^\d{8}$/.test(this.form.idDocPeriodEnd)) {
					uni.showToast({ title: '请选择证件结束日期', icon: 'none' });
					return false;
				}
				if (this.form.idDocPeriodEnd !== '29991231' && this.form.idDocPeriodEnd <= this.form.idDocPeriodBegin) {
					uni.showToast({ title: '证件结束日期必须晚于开始日期', icon: 'none' });
					return false;
				}
				if (this.form.cDFlag === 'C') {
					if (!/^(0[1-9]|1[0-2])\d{2}$/.test(this.form.validDate)) {
						uni.showToast({ title: '有效期格式应为 MMYY', icon: 'none' });
						return false;
					}
					if (!/^\d{3,4}$/.test(this.form.cvv2)) {
						uni.showToast({ title: 'CVV2 格式错误', icon: 'none' });
						return false;
					}
				}
				return true;
			},

			formatDateDisplay(yyyymmdd) {
				if (!yyyymmdd || yyyymmdd.length !== 8) return '';
				return `${yyyymmdd.substring(0, 4)}-${yyyymmdd.substring(4, 6)}-${yyyymmdd.substring(6, 8)}`;
			},
			yyyymmddToIso(yyyymmdd) {
				if (!yyyymmdd || yyyymmdd.length !== 8) return '';
				return `${yyyymmdd.substring(0, 4)}-${yyyymmdd.substring(4, 6)}-${yyyymmdd.substring(6, 8)}`;
			},
			isoToYyyymmdd(iso) {
				return (iso || '').replace(/-/g, '');
			},
			onIdBeginChange(e) {
				this.form.idDocPeriodBegin = this.isoToYyyymmdd(e.detail.value);
			},
			onIdEndChange(e) {
				this.form.idDocPeriodEnd = this.isoToYyyymmdd(e.detail.value);
			},
			toggleLong() {
				this.isIdLong = !this.isIdLong;
				if (this.isIdLong) {
					this.form.idDocPeriodEnd = '29991231';
				} else {
					this.form.idDocPeriodEnd = '';
				}
			},
			startCountdown() {
				this.smsCountdown = 60;
				this.smsTimer = setInterval(() => {
					this.smsCountdown--;
					if (this.smsCountdown <= 0) {
						clearInterval(this.smsTimer);
						this.smsTimer = null;
					}
				}, 1000);
			},
			applyBind() {
				if (this.submitting) return;
				if (!this.validateBasic()) return;
				this.submitting = true;
				uni.showLoading({ title: '发送中', mask: true });
				this.$request({
					url: '/api/bank-card/apply-bind',
					method: 'post',
					data: {
						gateway: this.gateway,
						userName: this.form.userName,
						identityNo: this.form.identityNo,
						identityType: '00',
						bankCardNo: this.form.bankCardNo,
						mobile: this.form.mobile,
						cDFlag: this.form.cDFlag,
						validDate: this.form.validDate,
						cvv2: this.form.cvv2,
						idDocPeriodBegin: this.form.idDocPeriodBegin,
						idDocPeriodEnd: this.form.idDocPeriodEnd,
					}
				}).then(res => {
					this.submitting = false;
					uni.hideLoading();
					if (res.code === 0) {
						this.card_id = res.data.card_id;
						this.smsSent = true;
						this.startCountdown();
						uni.showToast({ title: '验证码已发送', icon: 'none' });
					} else {
						uni.showModal({ title: '提示', content: res.msg, showCancel: false });
					}
				}).catch(() => {
					this.submitting = false;
					uni.hideLoading();
				});
			},
			verifyBind() {
				if (this.submitting) return;
				if (!this.form.smsCode) {
					uni.showToast({ title: '请输入验证码', icon: 'none' });
					return;
				}
				this.submitting = true;
				uni.showLoading({ title: '验证中', mask: true });
				this.$request({
					url: '/api/bank-card/verify-bind',
					method: 'post',
					data: {
						card_id: this.card_id,
						smsCode: this.form.smsCode,
					}
				}).then(res => {
					this.submitting = false;
					uni.hideLoading();
					if (res.code === 0) {
						uni.showToast({ title: '绑卡成功', icon: 'success' });
						setTimeout(() => {
							if (this.redirect_url) {
								uni.redirectTo({ url: this.redirect_url });
							} else {
								uni.navigateBack();
							}
						}, 1200);
					} else {
						uni.showModal({ title: '提示', content: res.msg, showCancel: false });
					}
				}).catch(() => {
					this.submitting = false;
					uni.hideLoading();
				});
			}
		}
	};
</script>

<style lang="scss" scoped>
	.bind-container {
		min-height: 100vh;
		position: relative;
	}

	.custom-navbar {
		position: fixed;
		left: 0;
		top: 0;
		width: 100%;
		z-index: 100;
		box-sizing: content-box;
		padding-left: 30rpx;
		display: flex;
		align-items: center;

		.nav-btn {
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			background: rgba(255, 255, 255, 0.6);
			border: 1rpx solid rgba(0, 0, 0, 0.05);
			backdrop-filter: blur(8px);

			&:active { transform: scale(0.9); }
		}

		.icon-back {
			width: 18rpx;
			height: 18rpx;
			border-left: 4rpx solid #1a1a1a;
			border-bottom: 4rpx solid #1a1a1a;
			transform: rotate(45deg);
			margin-left: 6rpx;
		}

		.nav-title {
			font-size: 32rpx;
			font-weight: 600;
			color: #1a1a1a;
			display: flex;
			align-items: center;
			margin-left: 30rpx;
		}
	}

	.header-bg {
		height: 360rpx;
		background: linear-gradient(180deg, #FFFFFF 0%, #F7F8FA 100%);
		width: 100%;
		position: absolute;
		top: 0;
		left: 0;
		z-index: 0;

		.header-content {
			padding: 0 44rpx;
			color: #333;

			.title-row {
				display: flex;
				align-items: center;
				margin-bottom: 12rpx;

				.page-title {
					font-size: 44rpx;
					font-weight: 700;
					color: #1a1a1a;
					margin-right: 20rpx;
				}

				.security-badge {
					display: flex;
					align-items: center;
					padding: 6rpx 16rpx;
					border-radius: 100rpx;
					font-size: 20rpx;
					font-weight: 500;

					.lock-icon {
						width: 10rpx;
						height: 8rpx;
						border: 2rpx solid currentColor;
						border-radius: 2rpx;
						margin-right: 8rpx;
						margin-top: 4rpx;
					}
				}
			}

			.page-desc {
				font-size: 26rpx;
				color: #909399;
			}
		}
	}

	.content-wrapper {
		position: relative;
		z-index: 1;
		padding: 240rpx 30rpx 240rpx;
	}

	.form-card {
		background: #fff;
		border-radius: 32rpx;
		box-shadow: 0 10rpx 40rpx rgba(0, 0, 0, 0.04), 0 4rpx 12rpx rgba(0, 0, 0, 0.02);
		padding: 10rpx 0 20rpx;
	}

	.card-type-tabs {
		display: flex;
		padding: 30rpx 40rpx 20rpx;
		gap: 24rpx;

		.tab-item {
			flex: 1;
			height: 72rpx;
			border: 2rpx solid #EBEEF5;
			border-radius: 16rpx;
			display: flex;
			align-items: center;
			justify-content: center;
			color: #909399;
			font-size: 28rpx;
			transition: all 0.2s;

			&.active {
				border-width: 2rpx;
				font-weight: 600;
				background: #FFF8F8;
			}
		}
	}

	.form-row {
		display: flex;
		align-items: center;
		padding: 30rpx 44rpx;
		border-bottom: 1rpx solid #F5F5F5;

		&:last-child { border-bottom: none; }

		&.disabled {
			opacity: 0.5;
		}

		.label {
			width: 180rpx;
			font-size: 28rpx;
			color: #303133;
			font-weight: 500;
		}

		.input {
			flex: 1;
			font-size: 28rpx;
			color: #1a1a1a;
		}

		.value-text {
			line-height: 1.6;
			&.placeholder { color: #C0C4CC; }
		}

		.arrow {
			color: #C0C4CC;
			font-size: 36rpx;
			padding-left: 10rpx;
		}

		.sms-btn {
			font-size: 26rpx;
			padding-left: 20rpx;
			white-space: nowrap;

			&.disabled {
				color: #C0C4CC;
			}
		}
	}

	.id-long-row {
		.switch-wrap {
			margin-left: auto;
		}

		.switch-track {
			position: relative;
			width: 80rpx;
			height: 44rpx;
			border-radius: 22rpx;
			transition: background 0.2s;

			.switch-dot {
				position: absolute;
				top: 4rpx;
				left: 4rpx;
				width: 36rpx;
				height: 36rpx;
				border-radius: 50%;
				background: #fff;
				box-shadow: 0 4rpx 8rpx rgba(0,0,0,0.15);
				transition: transform 0.2s;

				&.on {
					transform: translateX(36rpx);
				}
			}
		}
	}

	.tips {
		display: flex;
		flex-direction: column;
		margin-top: 40rpx;
		padding: 0 20rpx;
		font-size: 22rpx;
		color: #909399;
		line-height: 1.8;
	}

	.footer-bar {
		position: fixed;
		bottom: 0;
		left: 0;
		width: 100%;
		background: rgba(255, 255, 255, 0.95);
		backdrop-filter: blur(10px);
		padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
		z-index: 99;
		box-shadow: 0 -4rpx 20rpx rgba(0, 0, 0, 0.03);

		.footer-content {
			padding: 24rpx 40rpx 10rpx;
		}

		.submit-btn {
			height: 104rpx;
			border-radius: 52rpx;
			border: none;
			padding: 0;
			color: #fff;
			font-size: 32rpx;
			font-weight: 600;
			letter-spacing: 1px;
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.btn-hover {
			transform: scale(0.98);
			filter: brightness(0.9);
		}
	}

	.fade-in-up {
		animation: fadeInUp 0.6s cubic-bezier(0.23, 1, 0.32, 1) forwards;
	}

	@keyframes fadeInUp {
		from { opacity: 0; transform: translateY(40rpx); }
		to { opacity: 1; transform: translateY(0); }
	}
</style>
