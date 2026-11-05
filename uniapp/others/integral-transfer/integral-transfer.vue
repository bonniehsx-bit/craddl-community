<template>
	<tui-page :title="$noun('integral') + '转赠'">
		<view class="container" :style="themeStyle">

			<view class="asset-card">
				<view class="asset-content">
					<view class="asset-label">当前可用{{ $noun('integral') }} (分)</view>
					<view class="asset-value">{{ user ? availableIntegral : '0.00' }}</view>
				</view>
				<view class="decor-circle"></view>
			</view>

			<view class="form-card">

				<view class="section-title">接收用户</view>

				<view v-if="!target" class="search-box">
					<view class="input-wrapper">
						<tui-icon name="search" :size="20" color="#999"></tui-icon>
						<input
							class="search-input"
							type="number"
							v-model.trim="target_id"
							placeholder="请输入对方的用户ID"
							placeholder-class="ph-color"
						/>
					</view>
					<view
						class="search-btn"
						@click="getTargetInfo"
						:style="'background:' + (target_id ? themeColor : '#F0F0F0') + ';color:' + (target_id ? '#fff' : '#999')"
					>
						{{ isSearching ? '查找中...' : '查找' }}
					</view>
				</view>

				<view v-if="target" class="user-result-card">
					<image :src="target.avatar_url || '/static/icons/avatar.png'" class="avatar" mode="aspectFill"></image>
					<view class="info">
						<view class="nickname">{{ target.nickname }}</view>
						<view class="uid">ID: {{ target.id }}</view>
					</view>
					<view class="close-icon" @click="changeTarget">
						<tui-icon name="close-fill" :size="20" color="#ccc"></tui-icon>
					</view>
				</view>

				<view class="section-title mt-40">转赠{{ $noun('integral') }}</view>
				<view class="amount-box" :class="{ 'disabled': !target }">
					<input
						class="amount-input"
						type="digit"
						v-model="amount"
						placeholder="0"
						placeholder-class="amount-ph"
						:disabled="!target"
						@input="calculateFee"
					/>
					<text class="symbol">{{ $noun('integral') }}</text>
				</view>
				<view class="balance-tip">
					<text>可转赠额度 {{ user ? availableIntegral : '--' }} {{ $noun('integral') }}</text>
					<text class="all-btn" :style="'color:' + (target ? themeColor : '#ccc')"
						@click="target && (amount = availableIntegral)">全部转出</text>
				</view>

				<view class="fee-section" v-if="target && serviceChargeRate > 0 && amount > 0">
					<view class="fee-row">
						<text class="fee-label">手续费 ({{ serviceChargeRate }}%)</text>
						<text class="fee-val">{{ fee.toFixed(2) }} 分</text>
					</view>
					<view class="fee-row">
						<text class="fee-label">总计扣除</text>
						<text class="fee-val total" :style="{ color: themeColor }">{{ totalDeduction.toFixed(2) }} 分</text>
					</view>
				</view>

			</view>

			<view class="footer-action">
				<button
					class="submit-btn"
					:class="{ disabled: !canSubmit }"
					@click="handleSubmit"
					:loading="isSubmitting"
					:disabled="!canSubmit || isSubmitting"
					:style="'background:' + (canSubmit ? themeColor : '#E0E0E0') + ';color:' + (canSubmit ? '#fff' : '#999') + ';box-shadow:' + (canSubmit ? '0 10rpx 20rpx var(--theme-shadow)' : 'none')"
				>
					确认转赠
				</button>
				<view class="safe-tip">
					<tui-icon name="shield" :size="14" color="#999"></tui-icon>
					<text>{{ $noun('integral') }}转赠后无法撤回，请核对接收用户信息</text>
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
						<view class="btn confirm" :style="'color:' + themeColor" @click="confirmPasswordPay">确定</view>
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
				user: null,
				target_id: '',
				target: null,
				amount: '',
				isSearching: false,
				isSubmitting: false,
				showPasswordModal: false,
				payPassword: '',
				serviceChargeRate: 0,
				fee: 0,
				totalDeduction: 0,
			};
		},
		computed: {
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F',
				setting: state => state.setting
			}),
			availableIntegral() {
				if (!this.user) return '0.00';
				const total = parseFloat(this.user.integral) || 0;
				const retained = parseFloat(this.user.retained_integral) || 0;
				return Math.max(0, total - retained).toFixed(2);
			},
			canSubmit() {
				return this.target && this.amount && parseFloat(this.amount) > 0;
			}
		},
		onLoad() {
			this.getInfo();
			this.loadSetting();
		},
		methods: {
			getInfo() {
				this.$request({ url: '/api/user/index' }).then(res => {
					if (res.code == 0) {
						this.user = res.data.user;
					}
				});
			},

			loadSetting() {
				this.$request({ url: '/api/integral/setting' }).then(res => {
					if (res.code === 0 && res.data.setting) {
						const transferSetting = res.data.setting.other || {};
						if (transferSetting.is_integral_transfer == 1) {
							this.serviceChargeRate = parseFloat(transferSetting.integral_transfer_service_price) || 0;
						}
					}
				});
			},

			getTargetInfo() {
				if (!this.target_id || this.isSearching) return;
				this.isSearching = true;
				this.$request({
					url: '/api/user/target-info',
					data: { target_id: this.target_id }
				}).then(res => {
					if (res.code == 0) {
						this.target = res.data.user;
					} else {
						uni.showToast({ title: res.msg, icon: 'none' });
					}
				}).finally(() => {
					this.isSearching = false;
				});
			},

			calculateFee() {
				const val = parseFloat(this.amount);
				if (isNaN(val) || val <= 0) {
					this.fee = 0;
					this.totalDeduction = 0;
					return;
				}
				this.fee = this.serviceChargeRate > 0
					? parseFloat((val * this.serviceChargeRate / 100).toFixed(2))
					: 0;
				this.totalDeduction = val + this.fee;
			},

			handleSubmit() {
				if (!this.canSubmit) return;

				this.calculateFee();
				const deduction = this.totalDeduction || parseFloat(this.amount);

				if (deduction > parseFloat(this.availableIntegral)) {
					return uni.showToast({ title: this.$noun('integral') + '不足', icon: 'none' });
				}

				const isPwdRequired = this.setting && this.setting.is_transfer_password == '1';

				uni.showModal({
					title: '安全确认',
					content: `您确定要向用户"${this.target.nickname}"\n转赠 ${this.amount} 分吗？`,
					confirmColor: this.themeColor,
					success: (res) => {
						if (res.confirm) {
							if (isPwdRequired) {
								this.payPassword = '';
								this.showPasswordModal = true;
							} else {
								this.submitTransfer();
							}
						}
					}
				});
			},

			closePasswordModal() {
				this.showPasswordModal = false;
				this.payPassword = '';
			},

			confirmPasswordPay() {
				if (!this.payPassword || this.payPassword.length !== 6) {
					return uni.showToast({ title: '请输入6位支付密码', icon: 'none' });
				}
				this.submitTransfer(this.payPassword);
				this.closePasswordModal();
			},

			submitTransfer(pay_password = '') {
				this.isSubmitting = true;
				this.$request({
					url: '/api/integral/transfer',
					method: 'POST',
					data: {
						amount: this.amount,
						recipient_id: this.target.id,
						pay_password: pay_password
					}
				}).then(res => {
					uni.showToast({ title: res.msg, icon: res.code == 0 ? 'success' : 'none' });
					if (res.code == 0) {
						this.getInfo();
						this.changeTarget();
						this.amount = '';
					}
				}).finally(() => {
					this.isSubmitting = false;
				});
			},

			changeTarget() {
				this.target = null;
				this.target_id = '';
				this.amount = '';
				this.fee = 0;
				this.totalDeduction = 0;
			},
		}
	}
</script>

<style lang="scss" scoped>
	$bg-color: #F7F8FA;
	$text-main: #333;
	$text-sub: #999;

	page {
		background-color: $bg-color;
	}

	.container {
		padding: 30rpx;
	}

	/* 顶部资产卡片 */
	.asset-card {
		position: relative;
		height: 240rpx;
		border-radius: 24rpx;
		overflow: hidden;
		margin-bottom: 30rpx;
		background: linear-gradient(135deg, var(--theme-color) 0%, var(--theme-color-light) 100%);
		box-shadow: 0 10rpx 30rpx var(--theme-shadow);

		.asset-content {
			position: relative;
			z-index: 2;
			padding: 50rpx 40rpx;
			color: #fff;

			.asset-label {
				font-size: 26rpx;
				opacity: 0.9;
				margin-bottom: 16rpx;
			}

			.asset-value {
				font-size: 64rpx;
				font-weight: bold;
				font-family: 'DIN', sans-serif;
				letter-spacing: 2rpx;
			}
		}

		.decor-circle {
			position: absolute;
			right: -20rpx;
			top: -40rpx;
			width: 200rpx;
			height: 200rpx;
			border-radius: 50%;
			z-index: 1;
			background: rgba(255, 255, 255, 0.1);
		}
	}

	/* 表单卡片 */
	.form-card {
		background-color: #fff;
		border-radius: 24rpx;
		padding: 40rpx 30rpx;
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.02);

		.section-title {
			font-size: 28rpx;
			font-weight: bold;
			color: $text-main;
			margin-bottom: 24rpx;

			&.mt-40 {
				margin-top: 40rpx;
			}
		}
	}

	/* 搜索框 */
	.search-box {
		display: flex;
		align-items: center;

		.input-wrapper {
			flex: 1;
			background-color: #F5F7FA;
			height: 88rpx;
			border-radius: 12rpx;
			display: flex;
			align-items: center;
			padding: 0 24rpx;
			margin-right: 20rpx;

			.search-input {
				flex: 1;
				font-size: 28rpx;
				color: $text-main;
				margin-left: 16rpx;
			}

			.ph-color { color: #BBB; }
		}

		.search-btn {
			width: 140rpx;
			height: 88rpx;
			line-height: 88rpx;
			font-size: 28rpx;
			text-align: center;
			border-radius: 12rpx;
			transition: all 0.3s;

			&:active { opacity: 0.9; }
		}
	}

	/* 用户结果卡片 */
	.user-result-card {
		display: flex;
		align-items: center;
		border: 2rpx solid var(--theme-color-bg);
		border-radius: 16rpx;
		padding: 24rpx;
		background: var(--theme-color-bg);

		.avatar {
			width: 90rpx;
			height: 90rpx;
			border-radius: 50%;
			margin-right: 24rpx;
			border: 4rpx solid #fff;
		}

		.info {
			flex: 1;

			.nickname {
				font-size: 30rpx;
				font-weight: bold;
				color: $text-main;
				margin-bottom: 6rpx;
			}

			.uid {
				font-size: 24rpx;
				color: #666;
			}
		}

		.close-icon {
			padding: 10rpx;
		}
	}

	/* 积分金额输入 */
	.amount-box {
		display: flex;
		align-items: center;
		border-bottom: 2rpx solid #EEE;
		padding-bottom: 20rpx;

		&.disabled {
			opacity: 0.5;
		}

		.amount-input {
			flex: 1;
			height: 80rpx;
			font-size: 56rpx;
			font-weight: bold;
			color: $text-main;
		}

		.amount-ph {
			font-size: 40rpx;
			color: #DDD;
			font-weight: normal;
		}

		.symbol {
			font-size: 28rpx;
			color: #94A3B8;
			margin-left: 16rpx;
			white-space: nowrap;
		}
	}

	.balance-tip {
		display: flex;
		justify-content: space-between;
		font-size: 24rpx;
		color: #999;
		margin-top: 20rpx;

		.all-btn {
			font-weight: 500;
		}
	}

	/* 手续费区域 */
	.fee-section {
		margin-top: 24rpx;
		background: #F8FAFC;
		border-radius: 12rpx;
		padding: 20rpx 24rpx;

		.fee-row {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 8rpx 0;

			.fee-label {
				font-size: 24rpx;
				color: #94A3B8;
			}

			.fee-val {
				font-size: 24rpx;
				color: #64748B;

				&.total {
					font-weight: 700;
					font-size: 26rpx;
				}
			}
		}
	}

	/* 底部按钮 */
	.footer-action {
		margin-top: 60rpx;

		.submit-btn {
			font-size: 32rpx;
			font-weight: bold;
			height: 96rpx;
			line-height: 96rpx;
			border-radius: 48rpx;
			transition: all 0.3s;

			&::after { border: none; }
			&:active { transform: scale(0.98); }
		}

		.safe-tip {
			display: flex;
			align-items: center;
			justify-content: center;
			margin-top: 30rpx;

			text {
				font-size: 24rpx;
				color: #999;
				margin-left: 8rpx;
			}
		}
	}

	/* 支付密码弹窗 */
	.pwd-mask {
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		bottom: 0;
		background: rgba(0, 0, 0, 0.6);
		z-index: 999;
		display: flex;
		align-items: center;
		justify-content: center;

		.pwd-modal {
			width: 600rpx;
			background: #fff;
			border-radius: 24rpx;
			overflow: hidden;

			.modal-title {
				text-align: center;
				font-size: 32rpx;
				font-weight: bold;
				padding: 40rpx 0 20rpx;
				color: #333;
			}

			.modal-content {
				padding: 30rpx 40rpx 50rpx;

				.pwd-input {
					background: #F5F7FA;
					height: 90rpx;
					border-radius: 12rpx;
					text-align: center;
					font-size: 36rpx;
					letter-spacing: 20rpx;
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
					font-size: 32rpx;

					&.cancel {
						color: #999;
						border-right: 1rpx solid #EEE;
					}

					&.confirm {
						font-weight: bold;
					}
				}
			}
		}
	}
</style>
