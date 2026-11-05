<template>
	<tui-page :useCustomNav="true" :transparentNav="true" :showBackBtn="true" :title="$noun('withdraw.balance_to_integral_title')">
		<view class="page" :style="themeStyle">
			<asset-hero :label="'当前可用' + $noun('balance') + ' (元)'" :amount="user ? user.money : '0.00'"></asset-hero>
			<view class="container">

			<feature-closed v-if="closed" title="余额转积分暂未开放" :desc="'如有疑问,请联系客服'"></feature-closed>
			<block v-else>
			<view class="form-card">
				<view class="section-title">{{ $noun('balance') }}兑换{{ $noun('integral') }}</view>
				<view class="amount-box">
					<text class="symbol">¥</text>
					<input
						class="amount-input"
						type="digit"
						v-model="balance"
						placeholder="0.00"
						placeholder-class="amount-ph"
						@input="calcIntegral"
					/>
				</view>
				<view class="balance-tip">
					<text>可用{{ $noun('balance') }} ¥{{ user ? user.money : '0.00' }}</text>
					<text class="all-btn" :style="'color:' + themeColor" @click="setAll">全部兑换</text>
				</view>

				<view class="convert-result" v-if="rate > 0">
					<text class="rate-line">兑换比例：1 元 = {{ rate }} {{ $noun('integral') }}</text>
					<view class="get-row">
						<text class="get-label">预计可得</text>
						<text class="get-val" :style="{ color: themeColor }">{{ gainIntegral.toFixed(2) }} {{ $noun('integral') }}</text>
					</view>
				</view>
			</view>

			<view class="footer-action">
				<button
					class="submit-btn"
					@click="submit"
					:loading="isSubmitting"
					:disabled="!canSubmit || isSubmitting"
					:style="'background:' + (canSubmit ? themeColor : '#E0E0E0') + ';color:' + (canSubmit ? '#fff' : '#999') + ';box-shadow:' + (canSubmit ? '0 10rpx 20rpx var(--theme-shadow)' : 'none')"
				>
					确认兑换
				</button>
				<view class="safe-tip">
					<tui-icon name="shield" :size="14" color="#999"></tui-icon>
					<text>兑换后无法撤回，请核对金额</text>
				</view>
			</view>

		</block>
			</view>
		</view>
	</tui-page>
</template>

<script>
	import { mapState } from 'vuex';
import assetHero from './components/asset-hero.vue';
import featureClosed from './components/feature-closed.vue';

	export default {
		components: { assetHero, featureClosed },
		data() {
			return {
				closed: false, // 后台没开这个功能:页面内提示,不再弹窗后自动返回
				user: null,
				balance: '',
				rate: 0,
				gainIntegral: 0,
				isSubmitting: false,
			};
		},
		computed: {
			...mapState('mall', {
				themeColor: state => state.themeColor || '#E43D3D'
			}),
			canSubmit() {
				return this.balance && parseFloat(this.balance) > 0 && this.rate > 0;
			}
		},
		onLoad() {
			this.getInfo();
			this.loadConfig();
			uni.setNavigationBarTitle({ title: this.$noun('withdraw.balance_to_integral_title') });
		},
		methods: {
			getInfo() {
				this.$request({ url: '/api/user/index' }).then(res => {
					if (res.code == 0) this.user = res.data.user;
				});
			},

			// 兑换配置：比例 + 开关（关闭则拦截返回）
			loadConfig() {
				this.$request({ url: '/plugin/withdraw/api/transfer/config', method: 'get' }).then(res => {
					if (res.code === 0) {
						this.rate = parseFloat(res.data.one_rmb_to_integral) || 0;
						if (res.data.balance_to_integral_enabled != 1) {
							this.closed = true;
						}
					}
				});
			},

			calcIntegral() {
				const val = parseFloat(this.balance);
				this.gainIntegral = (!isNaN(val) && val > 0 && this.rate > 0)
					? parseFloat((val * this.rate).toFixed(2))
					: 0;
			},

			setAll() {
				if (this.user) {
					this.balance = this.user.money;
					this.calcIntegral();
				}
			},

			submit() {
				if (!this.canSubmit) return;
				if (parseFloat(this.balance) > parseFloat(this.user.money)) {
					return uni.showToast({ title: this.$noun('balance') + '不足', icon: 'none' });
				}
				uni.showModal({
					title: '确认兑换',
					content: `确定用 ${this.balance} 元兑换 ${this.gainIntegral.toFixed(2)} ${this.$noun('integral')} 吗？`,
					confirmColor: this.themeColor,
					success: (res) => {
						if (res.confirm) this.doSubmit();
					}
				});
			},

			doSubmit() {
				this.isSubmitting = true;
				this.$request({
					url: '/plugin/withdraw/api/transfer/balance-to-integral',
					method: 'post',
					data: { balance: this.balance }
				}).then(res => {
					uni.showToast({ title: res.msg, icon: res.code == 0 ? 'success' : 'none' });
					if (res.code == 0) {
						this.getInfo();
						this.balance = '';
						this.gainIntegral = 0;
					}
				}).finally(() => {
					this.isSubmitting = false;
				});
			},
		}
	}
</script>

<style lang="scss" scoped>
	$bg-color: #F7F8FA;
	$text-main: #333;

	page {
		background-color: $bg-color;
	}

	.container {
		padding: 0 30rpx 30rpx;
	}

	.form-card {
		position: relative;
		z-index: 2;
		margin-top: -30rpx;
		background-color: #fff;
		border-radius: 24rpx;
		padding: 40rpx 30rpx;
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.02);

		.section-title {
			font-size: 28rpx;
			font-weight: bold;
			color: $text-main;
			margin-bottom: 24rpx;
		}
	}

	.amount-box {
		display: flex;
		align-items: center;
		border-bottom: 2rpx solid #EEE;
		padding-bottom: 20rpx;

		.symbol {
			font-size: 40rpx;
			font-weight: bold;
			color: $text-main;
			margin-right: 20rpx;
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

	.convert-result {
		margin-top: 30rpx;
		background: #F8FAFC;
		border-radius: 12rpx;
		padding: 24rpx;

		.rate-line {
			font-size: 24rpx;
			color: #94A3B8;
		}

		.get-row {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-top: 16rpx;

			.get-label {
				font-size: 26rpx;
				color: #64748B;
			}

			.get-val {
				font-size: 32rpx;
				font-weight: 700;
			}
		}
	}

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
</style>
