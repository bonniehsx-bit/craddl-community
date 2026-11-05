<template>
	<!--
		支付密码设置/修改弹窗
		- mobile: 必须由父组件传入, 本组件不再自己发 /api/user/index 请求, 避免"先开框再拿数据"的 race
		- mode:   set = 首次设置, edit = 已有密码修改, 用于切标题和按钮文案
		- tui-modal 自带 40rpx 64rpx 的内边距,再叠上本组件的内边距,内容区只剩一半宽 ——
		  「安全验证」标签就是这样被挤成两行的。所以这里把 modal 的内边距清零,只用本组件自己的。
	-->
	<tui-modal :show="visible" :custom="true" :maskClosable="false" padding="0" radius="40rpx" width="86%">
		<view class="pp-box" :style="'--theme-color:' + themeColor + ';'">
			<view class="pp-close" @click="handleClose">
				<tui-icon name="close" :size="18" color="#A3A9B3"></tui-icon>
			</view>

			<!-- 头部:图标 + 标题 + 一句话说明 -->
			<view class="pp-head">
				<view class="pp-ic"><tui-icon name="shield" :size="22" color="#ffffff"></tui-icon></view>
				<text class="pp-title">{{ title }}</text>
				<text class="pp-sub">{{ mode === 'set' ? '验证手机号后，设置 6 位数字密码' : '验证手机号后，输入新的 6 位数字密码' }}</text>
			</view>

			<view class="pp-body">
				<!-- 手机号展示区 -->
				<view class="pp-phone" v-if="mobile">
					<text class="pp-phone-l">验证码将发送至</text>
					<text class="pp-phone-num">{{ maskedMobile }}</text>
					<text class="pp-tag">安全验证</text>
				</view>
				<view class="pp-phone warning" v-else>
					<tui-icon name="about" :size="16" color="#E5484D"></tui-icon>
					<text class="pp-warn-t">未绑定手机号，无法操作</text>
				</view>

				<!-- 验证码输入:按钮放在输入框里面 -->
				<view class="pp-field">
					<input class="pp-input" placeholder="短信验证码" placeholder-class="pp-ph"
						v-model="form.code" type="number" maxlength="6" />
					<view class="pp-code" :class="{ off: is_send_code || !mobile }" @click="onCodeTap">
						{{ is_send_code ? sec + 's 后重发' : '获取验证码' }}
					</view>
				</view>

				<!-- 新密码 -->
				<view class="pp-field">
					<input class="pp-input" placeholder="设置 6 位数字支付密码" placeholder-class="pp-ph"
						v-model="form.pay_pwd" type="number" password maxlength="6" />
				</view>

				<!-- 确认密码 -->
				<view class="pp-field">
					<input class="pp-input" placeholder="再输入一次" placeholder-class="pp-ph"
						v-model="form.pay_pwd_confirm" type="number" password maxlength="6" />
				</view>

				<!-- 弱密码软提示 (不阻止提交, 仅给用户一个警告) -->
				<view v-if="weakPwdHint" class="pp-weak">{{ weakPwdHint }}</view>
			</view>

			<!-- 底部按钮 -->
			<view class="pp-foot">
				<view class="pp-submit" :class="{ off: submitting || !mobile }" @click="handleSubmit">
					{{ submitting ? '提交中...' : submitText }}
				</view>
				<text class="pp-note">支付密码用于{{ $noun('balance') }}付款和转赠，请不要告诉任何人</text>
			</view>
		</view>
	</tui-modal>
</template>

<script>
	import { mapState } from 'vuex';

	// 常见弱密码黑名单 (6 位数字范围内的 banal 组合)
	const WEAK_PWD_LIST = [
		'000000', '111111', '222222', '333333', '444444',
		'555555', '666666', '777777', '888888', '999999',
		'123456', '654321', '123123', '121212', '112233',
		'456789', '987654', '159753',
	];

	export default {
		name: "tui-paypwd",
		props: {
			visible: {
				type: Boolean,
				default: false
			},
			// 手机号由父组件同步传入, 消除原先"组件内自己再发一次 /api/user/index"的重复请求 + 时序 race
			mobile: {
				type: String,
				default: ''
			},
			// 'set' = 首次设置, 'edit' = 修改已有密码
			mode: {
				type: String,
				default: 'edit'
			}
		},
		data() {
			return {
				sec: 60,
				is_send_code: false,
				timer: null,
				submitting: false,
				form: {
					code: '',
					pay_pwd: '',
					pay_pwd_confirm: ''
				}
			};
		},
		computed: {
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F'
			}),
			title() {
				return this.mode === 'set' ? '设置支付密码' : '修改支付密码';
			},
			submitText() {
				return this.mode === 'set' ? '确认设置' : '确认修改';
			},
			maskedMobile() {
				if (!this.mobile) return '';
				return this.mobile.replace(/(\d{3})\d{4}(\d{4})/, '$1****$2');
			},
			// 弱密码软警告文案 (不阻止提交)
			weakPwdHint() {
				const p = this.form.pay_pwd;
				if (!p || p.length !== 6) return '';
				if (WEAK_PWD_LIST.indexOf(p) !== -1) {
					return '该密码过于简单，存在被猜到的风险';
				}
				if (this.mobile && this.mobile.length >= 6 && this.mobile.slice(-6) === p) {
					return '不建议使用手机号后六位作为支付密码';
				}
				return '';
			}
		},
		watch: {
			visible(val) {
				if (val) {
					this.resetForm();
				} else {
					this.clearTimer();
				}
			}
		},
		methods: {
			sendCode() {
				if (!this.mobile) return uni.showToast({ title: '未获取到手机号', icon: 'none' });

				uni.showLoading({ title: '发送中...' });
				this.$request({
					url: "/api/login/sms-code",
					data: { mobile: this.mobile },
					method: 'post'
				}).then(res => {
					uni.hideLoading();
					if (res.code == 0) {
						uni.showToast({ title: '验证码已发送' });
						this.is_send_code = true;
						this.sec = 60;
						this.timer = setInterval(() => {
							this.sec--;
							if (this.sec <= 0) {
								this.clearTimer();
							}
						}, 1000);
					} else {
						uni.showToast({ title: res.msg, icon: 'none' });
					}
				}).catch(() => {
					uni.hideLoading();
				});
			},
			// 倒计时中 / 没手机号时点了不动(原来是 tui-button 的 disabled)
			onCodeTap() {
				if (this.is_send_code || !this.mobile) return;
				this.sendCode();
			},
			handleSubmit() {
				// 双击防抖: 提交中直接忽略后续点击
				if (this.submitting) return;

				if (!this.mobile) return uni.showToast({ title: '未获取到手机号', icon: 'none' });
				if (!this.form.code) return uni.showToast({ title: '请输入验证码', icon: 'none' });
				if (!this.form.pay_pwd || this.form.pay_pwd.length !== 6) {
					return uni.showToast({ title: '请输入6位数字支付密码', icon: 'none' });
				}
				if (!/^\d{6}$/.test(this.form.pay_pwd)) {
					return uni.showToast({ title: '支付密码必须为6位数字', icon: 'none' });
				}
				if (this.form.pay_pwd !== this.form.pay_pwd_confirm) {
					return uni.showToast({ title: '两次输入的支付密码不一致', icon: 'none' });
				}

				this.submitting = true;
				uni.showLoading({ title: '提交中...' });
				this.$request({
					url: "/api/user/set-pay-pwd",
					data: {
						mobile: this.mobile,
						code: this.form.code,
						pay_pwd: this.form.pay_pwd
					},
					method: 'post'
				}).then(res => {
					uni.hideLoading();
					if (res.code == 0) {
						uni.showToast({
							title: this.mode === 'set' ? '设置成功' : '修改成功',
							icon: 'success'
						});
						// 延迟关闭弹窗, 让用户看到成功提示
						setTimeout(() => {
							this.submitting = false;
							this.handleClose();
							this.$emit('refresh');
						}, 1500);
					} else {
						this.submitting = false;
						uni.showToast({ title: res.msg, icon: 'none' });
					}
				}).catch(() => {
					this.submitting = false;
					uni.hideLoading();
				});
			},
			handleClose() {
				this.$emit('update:visible', false);
			},
			resetForm() {
				this.form = { code: '', pay_pwd: '', pay_pwd_confirm: '' };
				this.submitting = false;
				this.clearTimer();
			},
			clearTimer() {
				this.is_send_code = false;
				this.sec = 60;
				if (this.timer) {
					clearInterval(this.timer);
					this.timer = null;
				}
			}
		}
	}
</script>

<style scoped lang="scss">
	$ink: #16181D;
	$sub: #8A909B;

	view, text { box-sizing: border-box; }

	.pp-box {
		position: relative;
		width: 100%;
		padding: 48rpx 40rpx 36rpx;
		background: #fff;
		border-radius: 40rpx;
	}

	.pp-close {
		position: absolute;
		right: 20rpx;
		top: 20rpx;
		width: 64rpx;
		height: 64rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: 50%;
		&:active { background: #F3F4F6; }
	}

	/* 头部 */
	.pp-head {
		display: flex;
		flex-direction: column;
		align-items: center;
		text-align: center;
	}
	.pp-ic {
		width: 96rpx;
		height: 96rpx;
		border-radius: 32rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		background: var(--theme-color);
		box-shadow: 0 16rpx 32rpx rgba(0, 0, 0, 0.12);
	}
	.pp-title {
		margin-top: 24rpx;
		font-size: 36rpx;
		font-weight: 800;
		color: $ink;
	}
	.pp-sub {
		margin-top: 8rpx;
		font-size: 25rpx;
		color: $sub;
	}

	.pp-body { margin-top: 36rpx; }

	/* 手机号:一行放下,标签不收缩不换行 */
	.pp-phone {
		display: flex;
		align-items: center;
		padding: 22rpx 24rpx;
		margin-bottom: 20rpx;
		border-radius: 24rpx;
		background: #F7F8FA;

		&.warning { background: #FEF0F0; }
	}
	.pp-phone-l {
		flex-shrink: 0;
		font-size: 24rpx;
		color: $sub;
	}
	.pp-phone-num {
		flex: 1;
		min-width: 0;
		margin-left: 12rpx;
		font-size: 30rpx;
		font-weight: 700;
		color: $ink;
		font-family: Menlo, monospace;
		white-space: nowrap;
	}
	.pp-tag {
		flex-shrink: 0;
		white-space: nowrap;
		margin-left: 12rpx;
		padding: 4rpx 14rpx;
		font-size: 21rpx;
		color: #1A8A4A;
		border-radius: 12rpx;
		background: #E8F6EE;
	}
	.pp-warn-t {
		margin-left: 10rpx;
		font-size: 26rpx;
		color: #E5484D;
	}

	/* 输入框:浅灰底的整块,点哪都能聚焦 */
	.pp-field {
		display: flex;
		align-items: center;
		height: 96rpx;
		padding: 0 28rpx;
		margin-bottom: 20rpx;
		border-radius: 24rpx;
		background: #F3F4F6;
		border: 2rpx solid transparent;
		transition: border-color 0.2s, background 0.2s;

		&:focus-within {
			background: #fff;
			border-color: var(--theme-color);
		}
	}
	.pp-input {
		flex: 1;
		min-width: 0;
		height: 96rpx;
		font-size: 30rpx;
		color: $ink;
		letter-spacing: 2rpx;
	}
	.pp-code {
		flex-shrink: 0;
		margin-left: 16rpx;
		padding-left: 24rpx;
		border-left: 2rpx solid #E1E4E8;
		font-size: 26rpx;
		font-weight: 600;
		color: var(--theme-color);
		white-space: nowrap;

		&.off { color: #A3A9B3; font-weight: 400; }
	}

	/* 弱密码软提示 */
	.pp-weak {
		margin: -4rpx 4rpx 8rpx;
		font-size: 23rpx;
		line-height: 1.4;
		color: #D97706;
	}

	/* 底部 */
	.pp-foot { margin-top: 16rpx; }
	.pp-submit {
		height: 92rpx;
		line-height: 92rpx;
		text-align: center;
		font-size: 30rpx;
		font-weight: 700;
		color: #fff;
		border-radius: 46rpx;
		background: var(--theme-color);
		box-shadow: 0 16rpx 32rpx rgba(0, 0, 0, 0.14);

		&:active { opacity: 0.88; }
		&.off { opacity: 0.5; box-shadow: none; }
	}
	.pp-note {
		display: block;
		margin-top: 20rpx;
		text-align: center;
		font-size: 22rpx;
		color: #A3A9B3;
	}
</style>
<style lang="scss">
	/* placeholder 的样式类要放在非 scoped 里,小程序才认 */
	.pp-ph { color: #A3A9B3; font-size: 28rpx; }
</style>
