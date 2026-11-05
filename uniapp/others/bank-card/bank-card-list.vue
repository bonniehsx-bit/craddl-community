<template>
	<tui-page :backgroundColor="'#F7F8FA'">
		<view class="cards-container" :style="{ '--theme-color': themeColor }">

			<!-- #ifndef H5 -->
			<view class="custom-navbar"
				:style="{ paddingTop: navBar.top + 'px', height: navBar.height + 'px' }">
				<view class="nav-btn"
					:style="{ height: navBar.height + 'px', width: navBar.height + 'px' }"
					@tap="handleNavBack">
					<view class="icon-back"></view>
				</view>
				<view class="nav-title" :style="{ height: navBar.height + 'px' }">我的银行卡</view>
			</view>
			<!-- #endif -->

			<view class="header-bg">
				<view class="header-content"
					:style="{ paddingTop: (navBar.top + navBar.height + 20) + 'px' }">
					<text class="page-title">我的银行卡</text>
					<text class="page-desc">持牌快捷支付通道 · 金融级加密存储</text>
				</view>
			</view>

			<view class="content-wrapper fade-in-up">
				<view v-if="list.length === 0 && !loading" class="empty">
					<view class="empty-icon" :style="{ borderColor: themeColor }">
						<text class="plus">+</text>
					</view>
					<text class="empty-title">还没有绑定银行卡</text>
					<text class="empty-desc">添加一张银行卡，享受快捷支付</text>
				</view>

				<view v-for="card in list" :key="card.id" class="bank-card"
					:style="cardBg">
					<view class="card-top">
						<text class="bank-name">{{ card.bank_name || '银行卡' }}</text>
						<text class="card-type-badge">{{ card.card_type_label }}</text>
					</view>
					<view class="card-number">
						<text>**** **** **** {{ card.bank_card_last4 }}</text>
					</view>
					<view class="card-bottom">
						<view class="card-holder">
							<text class="label">持卡人</text>
							<text class="value">{{ card.holder_name }}</text>
						</view>
						<view class="card-mobile">
							<text class="value">{{ card.mobile_mask }}</text>
						</view>
					</view>

					<view v-if="card.is_default === 1" class="default-tag">默认</view>

					<view class="card-actions">
						<view class="action-btn" v-if="card.is_default !== 1" @tap="setDefault(card)">
							<text>设为默认</text>
						</view>
						<view class="action-btn danger" @tap="unbind(card)">
							<text>解绑</text>
						</view>
					</view>
				</view>
			</view>

			<view class="footer-bar">
				<view class="footer-content">
					<button class="add-btn"
						:style="{ background: themeColor, boxShadow: btnShadow }"
						hover-class="btn-hover" @tap="goBind">
						<text>+ 添加银行卡</text>
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
				navBar: { top: 20, height: 44 },
				list: [],
				loading: false,
				// 支付网关类完整类名, 由 URL query 传入 (例: app\payments\FastPayQuickPayment)
				// 未来新增汇付/杉德等快捷支付网关, 调用方只需改 gateway 值, 本页面零改动
				gateway: '',
			};
		},
		computed: {
			...mapState('mall', {
				themeColor: state => state.themeColor || '#E63D3D'
			}),
			btnShadow() {
				return `0 16rpx 40rpx -10rpx ${this.hexToRgba(this.themeColor, 0.45)}`;
			},
			cardBg() {
				const base = this.themeColor;
				return {
					background: `linear-gradient(135deg, ${base} 0%, ${this.hexToRgba(base, 0.8)} 100%)`
				};
			}
		},
		onLoad(options) {
			this.initNavBar();
			this.gateway = decodeURIComponent(options.gateway || '');
			if (!this.gateway) {
				uni.showToast({ title: '缺少支付网关参数', icon: 'none' });
				setTimeout(() => uni.navigateBack(), 1500);
			}
		},
		onShow() {
			this.loadList();
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
			loadList() {
				if (!this.gateway) return;
				this.loading = true;
				this.$request({
					url: '/api/bank-card/list',
					method: 'get',
					data: { gateway: this.gateway },
				}).then(res => {
					this.loading = false;
					if (res.code === 0) {
						this.list = res.data || [];
					}
				}).catch(() => {
					this.loading = false;
				});
			},
			goBind() {
				uni.navigateTo({
					url: '/others/bank-card/bank-card-bind?gateway=' + encodeURIComponent(this.gateway)
				});
			},
			setDefault(card) {
				uni.showLoading({ title: '设置中', mask: true });
				this.$request({
					url: '/api/bank-card/set-default',
					method: 'post',
					data: { card_id: card.id }
				}).then(res => {
					uni.hideLoading();
					if (res.code === 0) {
						uni.showToast({ title: '设置成功', icon: 'success' });
						this.loadList();
					} else {
						uni.showToast({ title: res.msg, icon: 'none' });
					}
				}).catch(() => uni.hideLoading());
			},
			unbind(card) {
				uni.showModal({
					title: '提示',
					content: `确认解绑尾号 ${card.bank_card_last4} 的银行卡？`,
					success: r => {
						if (!r.confirm) return;
						uni.showLoading({ title: '解绑中', mask: true });
						this.$request({
							url: '/api/bank-card/unbind',
							method: 'post',
							data: { card_id: card.id }
						}).then(res => {
							uni.hideLoading();
							if (res.code === 0) {
								uni.showToast({ title: '已解绑', icon: 'success' });
								this.loadList();
							} else {
								uni.showToast({ title: res.msg, icon: 'none' });
							}
						}).catch(() => uni.hideLoading());
					}
				});
			}
		}
	};
</script>

<style lang="scss" scoped>
	.cards-container {
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
		height: 320rpx;
		background: linear-gradient(180deg, #FFFFFF 0%, #F7F8FA 100%);
		width: 100%;
		position: absolute;
		top: 0;
		left: 0;
		z-index: 0;

		.header-content {
			padding: 0 44rpx;

			.page-title {
				display: block;
				font-size: 44rpx;
				font-weight: 700;
				color: #1a1a1a;
				margin-bottom: 12rpx;
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
		padding: 220rpx 30rpx 220rpx;
	}

	.empty {
		display: flex;
		flex-direction: column;
		align-items: center;
		padding: 120rpx 0;

		.empty-icon {
			width: 140rpx;
			height: 140rpx;
			border-radius: 50%;
			border: 4rpx dashed currentColor;
			display: flex;
			align-items: center;
			justify-content: center;
			margin-bottom: 30rpx;

			.plus {
				font-size: 72rpx;
				font-weight: 300;
				color: inherit;
			}
		}

		.empty-title {
			font-size: 30rpx;
			color: #303133;
			font-weight: 600;
			margin-bottom: 12rpx;
		}

		.empty-desc {
			font-size: 24rpx;
			color: #909399;
		}
	}

	.bank-card {
		position: relative;
		padding: 40rpx 40rpx 30rpx;
		border-radius: 32rpx;
		color: #fff;
		margin-bottom: 28rpx;
		box-shadow: 0 20rpx 50rpx -20rpx rgba(0, 0, 0, 0.25);
		overflow: hidden;

		.card-top {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 50rpx;

			.bank-name {
				font-size: 30rpx;
				font-weight: 700;
				letter-spacing: 1px;
			}

			.card-type-badge {
				font-size: 22rpx;
				padding: 6rpx 16rpx;
				background: rgba(255, 255, 255, 0.2);
				border-radius: 100rpx;
			}
		}

		.card-number {
			font-size: 40rpx;
			font-weight: 600;
			letter-spacing: 4rpx;
			font-family: 'DIN', monospace;
			margin-bottom: 40rpx;
		}

		.card-bottom {
			display: flex;
			justify-content: space-between;
			align-items: flex-end;

			.card-holder {
				display: flex;
				flex-direction: column;

				.label {
					font-size: 20rpx;
					opacity: 0.7;
					margin-bottom: 4rpx;
				}

				.value {
					font-size: 28rpx;
					font-weight: 500;
				}
			}

			.card-mobile {
				font-size: 24rpx;
				opacity: 0.85;
				font-family: 'DIN', monospace;
			}
		}

		.default-tag {
			position: absolute;
			top: 30rpx;
			right: 0;
			background: rgba(255, 255, 255, 0.95);
			color: var(--theme-color);
			font-size: 22rpx;
			padding: 6rpx 20rpx;
			border-radius: 100rpx 0 0 100rpx;
			font-weight: 600;
		}

		.card-actions {
			display: flex;
			justify-content: flex-end;
			gap: 12rpx;
			margin-top: 30rpx;
			padding-top: 20rpx;
			border-top: 1rpx solid rgba(255, 255, 255, 0.15);

			.action-btn {
				padding: 10rpx 24rpx;
				background: rgba(255, 255, 255, 0.2);
				border-radius: 100rpx;
				font-size: 22rpx;

				&.danger {
					background: rgba(0, 0, 0, 0.2);
				}
			}
		}
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

		.add-btn {
			height: 104rpx;
			border-radius: 52rpx;
			border: none;
			padding: 0;
			color: #fff;
			font-size: 32rpx;
			font-weight: 600;
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
		animation: fadeInUp 0.5s cubic-bezier(0.23, 1, 0.32, 1) forwards;
	}

	@keyframes fadeInUp {
		from { opacity: 0; transform: translateY(40rpx); }
		to { opacity: 1; transform: translateY(0); }
	}
</style>
