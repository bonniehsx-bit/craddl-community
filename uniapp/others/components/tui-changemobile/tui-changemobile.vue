<template>
	<!--
		修改/绑定手机号弹窗
		- currentMobile 由父组件传入:
		    空 → 首次绑定 (mode = bind), 只需验证新手机号
		    非空 → 换绑 (mode = change), 必须先验证原手机号再验证新手机号
		- 单屏双验证码 UI: 两个独立倒计时, 两个独立 sendCode 调用
		- 成功后 emit('success'), 父组件负责刷新 user 数据. 不需要 logout (token 还有效)
		- 样式和 tui-paypwd / tui-loginpwd 一套;tui-modal 的内边距清零,只用本组件自己的
	-->
	<tui-modal :show="visible" :custom="true" :maskClosable="false" padding="0" radius="40rpx" width="86%">
		<view class="pp-box" :style="'--theme-color:' + themeColor + ';'">
			<view class="pp-close" @click="handleClose">
				<tui-icon name="close" :size="18" color="#A3A9B3"></tui-icon>
			</view>

			<!-- 头部:图标 + 标题 + 一句话说明 -->
			<view class="pp-head">
				<view class="pp-ic"><tui-icon name="voipphone" :size="22" color="#ffffff"></tui-icon></view>
				<text class="pp-title">{{ title }}</text>
				<text class="pp-sub">{{ hasCurrent ? '先验证原手机号，再验证新手机号' : '绑定后可以用手机号登录、找回密码' }}</text>
			</view>

			<view class="pp-body">
				<!-- 第 1 步:验证原手机号(只在换绑时出现) -->
				<block v-if="hasCurrent">
					<view class="pp-step">
						<text class="pp-step-n">1</text>
						<text class="pp-step-t">验证原手机号</text>
						<text class="pp-step-m">{{ maskedCurrent }}</text>
					</view>
					<view class="pp-field">
						<input class="pp-input" placeholder="原手机号收到的验证码" placeholder-class="pp-ph"
							v-model="form.oldCode" type="number" maxlength="6" />
						<view class="pp-code" :class="{ off: oldSending || !currentMobile }" @click="onOldCodeTap">
							{{ oldSending ? oldSec + 's 后重发' : '获取验证码' }}
						</view>
					</view>
				</block>

				<!-- 第 2 步(首次绑定时就是唯一一步):新手机号 + 验证码 -->
				<view class="pp-step" :class="{ gap: hasCurrent }">
					<text class="pp-step-n" v-if="hasCurrent">2</text>
					<text class="pp-step-t">{{ hasCurrent ? '验证新手机号' : '手机号' }}</text>
				</view>
				<view class="pp-field">
					<input class="pp-input" placeholder="11 位手机号" placeholder-class="pp-ph"
						v-model="form.newMobile" type="number" maxlength="11" />
					<view class="pp-code" :class="{ off: newSending }" @click="onNewCodeTap">
						{{ newSending ? newSec + 's 后重发' : '获取验证码' }}
					</view>
				</view>
				<view class="pp-field">
					<input class="pp-input" placeholder="新手机号收到的验证码" placeholder-class="pp-ph"
						v-model="form.newCode" type="number" maxlength="6" />
				</view>
			</view>

			<view class="pp-foot">
				<view class="pp-submit" :class="{ off: submitting }" @click="handleSubmit">
					{{ submitting ? '提交中...' : submitText }}
				</view>
			</view>
		</view>
	</tui-modal>
</template>

<script>
	import { mapState } from 'vuex';

	const MOBILE_REGEX = /^1[3-9]\d{9}$/;
	const COUNTDOWN_SEC = 60;

	export default {
		name: "tui-changemobile",
		props: {
			visible: {
				type: Boolean,
				default: false
			},
			// 当前手机号 (从父组件 user.mobile 透传), 空串代表首次绑定
			currentMobile: {
				type: String,
				default: ''
			}
		},
		data() {
			return {
				form: {
					oldCode: '',
					newMobile: '',
					newCode: ''
				},
				submitting: false,
				// 原手机号验证码倒计时
				oldSending: false,
				oldSec: COUNTDOWN_SEC,
				oldTimer: null,
				// 新手机号验证码倒计时
				newSending: false,
				newSec: COUNTDOWN_SEC,
				newTimer: null
			};
		},
		computed: {
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F'
			}),
			hasCurrent() {
				return !!this.currentMobile;
			},
			title() {
				return this.hasCurrent ? '换绑手机号' : '绑定手机号';
			},
			submitText() {
				return this.hasCurrent ? '确认更换' : '确认绑定';
			},
			maskedCurrent() {
				if (!this.currentMobile) return '';
				return this.currentMobile.replace(/(\d{3})\d{4}(\d{4})/, '$1****$2');
			}
		},
		watch: {
			visible(val) {
				if (val) {
					this.resetForm();
				} else {
					this.clearTimers();
				}
			}
		},
		methods: {
			// 发送原手机号验证码
			sendOldCode() {
				if (this.oldSending || !this.currentMobile) return;
				uni.showLoading({ title: '发送中...' });
				this.$request({
					url: "/api/login/sms-code",
					data: { mobile: this.currentMobile },
					method: 'post'
				}).then(res => {
					uni.hideLoading();
					if (res.code == 0) {
						uni.showToast({ title: '验证码已发送至原手机号' });
						this.startCountdown('old');
					} else {
						uni.showToast({ title: res.msg, icon: 'none' });
					}
				}).catch(() => uni.hideLoading());
			},
			// 发送新手机号验证码
			sendNewCode() {
				if (this.newSending) return;
				if (!this.form.newMobile) {
					return uni.showToast({ title: '请输入新手机号', icon: 'none' });
				}
				if (!MOBILE_REGEX.test(this.form.newMobile)) {
					return uni.showToast({ title: '手机号格式不正确', icon: 'none' });
				}
				if (this.hasCurrent && this.form.newMobile === this.currentMobile) {
					return uni.showToast({ title: '新手机号不能与原手机号相同', icon: 'none' });
				}
				uni.showLoading({ title: '发送中...' });
				this.$request({
					url: "/api/login/sms-code",
					data: { mobile: this.form.newMobile },
					method: 'post'
				}).then(res => {
					uni.hideLoading();
					if (res.code == 0) {
						uni.showToast({ title: '验证码已发送至新手机号' });
						this.startCountdown('new');
					} else {
						uni.showToast({ title: res.msg, icon: 'none' });
					}
				}).catch(() => uni.hideLoading());
			},
			// 启动指定通道的倒计时 (channel: 'old' | 'new')
			startCountdown(channel) {
				const sendingKey = channel + 'Sending';
				const secKey = channel + 'Sec';
				const timerKey = channel + 'Timer';

				this[sendingKey] = true;
				this[secKey] = COUNTDOWN_SEC;
				if (this[timerKey]) clearInterval(this[timerKey]);
				this[timerKey] = setInterval(() => {
					this[secKey]--;
					if (this[secKey] <= 0) {
						clearInterval(this[timerKey]);
						this[timerKey] = null;
						this[sendingKey] = false;
						this[secKey] = COUNTDOWN_SEC;
					}
				}, 1000);
			},
			// 倒计时中点了不动(原来是 tui-button 的 disabled)
			onOldCodeTap() {
				if (this.oldSending || !this.currentMobile) return;
				this.sendOldCode();
			},
			onNewCodeTap() {
				if (this.newSending) return;
				this.sendNewCode();
			},
			handleSubmit() {
				// 双击防抖
				if (this.submitting) return;

				if (this.hasCurrent && !this.form.oldCode) {
					return uni.showToast({ title: '请输入原手机号验证码', icon: 'none' });
				}
				if (!this.form.newMobile) {
					return uni.showToast({ title: '请输入新手机号', icon: 'none' });
				}
				if (!MOBILE_REGEX.test(this.form.newMobile)) {
					return uni.showToast({ title: '手机号格式不正确', icon: 'none' });
				}
				if (this.hasCurrent && this.form.newMobile === this.currentMobile) {
					return uni.showToast({ title: '新手机号不能与原手机号相同', icon: 'none' });
				}
				if (!this.form.newCode) {
					return uni.showToast({ title: '请输入新手机号验证码', icon: 'none' });
				}

				this.submitting = true;
				uni.showLoading({ title: '提交中...' });
				this.$request({
					url: "/api/user/change-mobile",
					method: 'post',
					data: {
						mobile: this.form.newMobile,
						code: this.form.newCode,
						old_code: this.form.oldCode
					}
				}).then(res => {
					uni.hideLoading();
					if (res.code == 0) {
						uni.showToast({
							title: this.hasCurrent ? '更换成功' : '绑定成功',
							icon: 'success'
						});
						setTimeout(() => {
							this.submitting = false;
							this.handleClose();
							this.$emit('success');
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
				this.form = { oldCode: '', newMobile: '', newCode: '' };
				this.submitting = false;
				this.clearTimers();
			},
			clearTimers() {
				if (this.oldTimer) {
					clearInterval(this.oldTimer);
					this.oldTimer = null;
				}
				if (this.newTimer) {
					clearInterval(this.newTimer);
					this.newTimer = null;
				}
				this.oldSending = false;
				this.newSending = false;
				this.oldSec = COUNTDOWN_SEC;
				this.newSec = COUNTDOWN_SEC;
			}
		},
		beforeDestroy() {
			this.clearTimers();
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
	/* 分步小标题 */
	.pp-step {
		display: flex;
		align-items: center;
		margin: 4rpx 4rpx 16rpx;

		&.gap { margin-top: 28rpx; }
	}
	.pp-step-n {
		width: 36rpx;
		height: 36rpx;
		line-height: 36rpx;
		margin-right: 12rpx;
		text-align: center;
		font-size: 22rpx;
		font-weight: 700;
		color: #fff;
		border-radius: 50%;
		background: var(--theme-color);
	}
	.pp-step-t {
		font-size: 26rpx;
		font-weight: 600;
		color: $ink;
	}
	.pp-step-m {
		margin-left: auto;
		font-size: 26rpx;
		font-weight: 700;
		color: $ink;
		font-family: Menlo, monospace;
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
