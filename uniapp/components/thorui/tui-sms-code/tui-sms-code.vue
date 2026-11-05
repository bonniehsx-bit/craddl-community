<template>
	<tui-modal :show="visible" :custom="true" title="获取验证码" @cancel="handleCancel">
		<view class="flex-x-center" style="font-weight: bold;">请输入验证码</view>
		<view class="code-input-modal">
			<view class="flex-row flex-y-center">
				<view class="input-wrap">
					<input v-model="code" type="number" maxlength="6"
						placeholder="请输入验证码" style="text-align:center;" />
				</view>
				<view class="flex-x-center flex-y-center">
					<tui-button type="danger" shape="circle" width="200rpx" height="70rpx" size="25" :disabled="countdown > 0"
						@click="sendCode">
						{{ countdown > 0 ? `${countdown}s后重新发送` : '获取验证码' }}
					</tui-button>
				</view>
			</view>
			<view class="flex-x-between" style="margin-top: 20rpx;">
				<tui-button shape="circle" width="200rpx" height="70rpx" size="25" @click="handleCancel">取消</tui-button>
				<tui-button shape="circle" width="200rpx" height="70rpx" size="25" type="danger"
					:disabled="!code || code.length !== 6" @click="handleConfirm">确定</tui-button>
			</view>
		</view>
	</tui-modal>
</template>

<script>
	export default {
		name: 'TuiSmsCode',
		props: {
			visible: Boolean,
			// 可选参数：验证码有效期(秒)
			countdownTime: {
				type: Number,
				default: 60
			}
		},
		data() {
			return {
				code: '',
				countdown: 0,
				timer: null
			}
		},
		watch: {
			visible(val) {
				// 组件显示时重置状态
				if (val) {
					this.reset();
				} else {
					this.clearTimer();
				}
			}
		},
		methods: {
			// 发送验证码
			sendCode() {
				if (this.countdown > 0) return;

				// 实际项目中应调用后端接口发送验证码
				this.$request({
					url: "/api/user/sms-code"
				}).then(res => {
					if (res.code == 0) {
						// 模拟发送验证码请求
						this.countdown = this.countdownTime;
						this.timer = setInterval(() => {
							this.countdown--;
							if (this.countdown <= 0) {
								this.clearTimer();
							}
						}, 1000);

						// 触发发送事件
						this.$emit('send');
					} else {
						uni.showModal({
							title: '提示',
							content: res.msg
						})
					}

				})
			},

			// 清除定时器
			clearTimer() {
				if (this.timer) {
					clearInterval(this.timer);
					this.timer = null;
				}
			},

			// 重置组件状态
			reset() {
				this.code = '';
				this.clearTimer();
				this.countdown = 0;
			},

			// 取消操作
			handleCancel() {
				this.reset();
				this.$emit('update:visible', false);
				this.$emit('cancel');
			},

			// 确认操作
			handleConfirm() {
				if (!this.code || this.code.length !== 6) {
					uni.showToast({
						title: '请输入6位验证码',
						icon: 'none'
					});
					return;
				}

				this.$emit('update:visible', false);
				this.$emit('confirm', this.code);
				this.reset();
			}
		},
		beforeDestroy() {
			this.clearTimer();
		}
	}
</script>

<style scoped>
	.code-input-modal {
		padding: 40rpx 20rpx 10rpx;
	}

	.input-wrap {
		display: flex;
		justify-content: center;

		height: 80rpx;
	}

	.btn-group {
		display: flex;
		justify-content: space-between;
	}

	.cancel-btn,
	.confirm-btn {
		width: 45%;
		padding: 20rpx 0;
		border-radius: 10rpx;
		font-size: 30rpx;
	}

	.cancel-btn {
		background-color: #f2f2f2;
		color: #666;
	}

	.confirm-btn {
		background-color: #007aff;
		color: #fff;
	}
</style>