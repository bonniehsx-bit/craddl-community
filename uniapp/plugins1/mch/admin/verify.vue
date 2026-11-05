<template>
	<tui-page :useCustomNav="true" :transparentNav="true">
		<view class="container" :style="themeStyle">

			<view class="header-section">
				<!-- 抽象科技感背景装饰 -->
			<view class="deco-shape shape-1"></view>
			<view class="deco-shape shape-2"></view>
			<view class="deco-shape shape-3"></view>
				<view class="header-content">
					<view class="title">核销工作台</view>
					<view class="subtitle">
						<tui-icon name="shield" :size="15" :color="themeColor"
							style="margin-right: 8rpx; opacity: 0.8;"></tui-icon>
						<text>支持 订单 / 权益券 / 酒店 / 拼团</text>
					</view>
				</view>
			</view>

			<view class="action-card">

				<!-- 原来这里是「四角取景框 + 来回扫的激光线」。那是相机取景框的视觉语言,
					 可它只是个静态装饰 —— 摄像头得点了才开,店员会把顾客的码举上去干等。
					 改成一眼就是按钮的实心块:自己不假装在扫,只负责把摄像头叫起来。 -->
				<view class="scan-area">
					<view class="scan-btn" hover-class="scan-btn-hover" :hover-stay-time="60" @tap="handleScan">
						<tui-icon name="scan" :size="26" color="#fff"></tui-icon>
						<text class="scan-btn-text">扫码核销</text>
					</view>
					<text class="scan-tip">点击后打开摄像头,对准顾客的核销码</text>
				</view>

				<view class="divider">
					<text>或手动输码</text>
				</view>

				<view class="input-area">
					<view class="input-box">
						<tui-icon name="edit" :size="18" color="#999" class="input-icon"></tui-icon>
						<input type="text" v-model="inputCode" placeholder="请输入核销码" placeholder-class="ph-style"
							confirm-type="search" @confirm="handleQuery" />
						<view class="verify-btn"
							:style="{ color: inputCode ? themeColor : '#ccc', opacity: inputCode ? 1 : 0.6 }"
							@tap="handleQuery">
							核销
						</view>
					</view>
				</view>
			</view>

			<view class="footer-tips safe-area-inset-bottom">
				<tui-icon name="about" :size="14" color="#ccc"></tui-icon>
				<text>核销操作不可撤销，请仔细核对信息</text>
			</view>

			<tui-bottom-popup :show="showModal" @close="closeModal" radius>
				<view class="verify-modal safe-area-inset-bottom">
					<view class="modal-header">
						<text class="modal-title">核销确认</text>
						<view class="close-btn" @tap="closeModal">
							<tui-icon name="close-fill" color="#ddd" :size="24"></tui-icon>
						</view>
					</view>

					<scroll-view scroll-y class="modal-scroll-content" v-if="verifyData">

						<view class="status-banner" :class="verifyData.is_allow ? 'status-success' : 'status-fail'">
							<view class="status-icon">
								<tui-icon :name="verifyData.is_allow ? 'check' : 'close'" :size="28"
									color="#fff"></tui-icon>
							</view>
							<view class="status-text">
								<text class="main-msg">{{ verifyData.msg }}</text>
								<text class="sub-msg" v-if="!verifyData.is_allow">请检查订单状态或有效期</text>
							</view>
						</view>

						<view class="info-grid">
							<view class="grid-item">
								<text class="label">业务类型</text>
								<text class="value highlight">{{ getTypeName(verifyData.type) }}</text>
							</view>
							<view class="grid-item">
								<text class="label">顾客姓名</text>
								<text class="value">{{ verifyData.data.user_name || '匿名用户' }}</text>
							</view>
							<view class="grid-row">
								<text class="label">单据编号</text>
								<view class="value-row" @tap="copyText(verifyData.data.order_no)">
									<text>{{ verifyData.data.order_no }}</text>
									<tui-icon name="document" :size="14" color="#999"
										style="margin-left: 8rpx;"></tui-icon>
								</view>
							</view>
						</view>

						<view class="goods-list-wrapper">
							<view class="section-head">核销内容</view>
							<view class="goods-item" v-for="(item, idx) in verifyData.data.products" :key="idx">
								<image :src="item.image" class="g-img" mode="aspectFill"></image>
								<view class="g-content">
									<view class="g-name">{{ item.name }}</view>
									<view class="g-bottom">
										<text class="g-spec">{{ item.spec || '默认规格' }}</text>
										<text class="g-num">x{{ item.num || 1 }}</text>
									</view>
								</view>
							</view>
						</view>

					</scroll-view>

					<view class="modal-actions">
						<button class="action-btn cancel" @tap="closeModal">取消</button>
						<button class="action-btn confirm" :class="{ disabled: !verifyData || !verifyData.is_allow }"
							:style="{ background: (verifyData && verifyData.is_allow) ? themeColor : '#e0e0e0' }"
							:disabled="!verifyData || !verifyData.is_allow || isSubmitting" @tap="doVerify">
							{{ isSubmitting ? '处理中...' : '确认核销' }}
						</button>
					</view>
				</view>
			</tui-bottom-popup>

		</view>
	</tui-page>
</template>

<script>
	export default {
		data() {
			return {
				inputCode: '',
				showModal: false,
				isSubmitting: false,
				verifyData: null
			}
		},
		// themeColor / themeStyle 由全局 mixin 提供(取 store 的 mall 模块)。
		// 原来这里写 mapState('mch', ...) 取 themeColor —— 而 mch 模块压根没有这个 state,
		// 于是永远落到写死的 #D6201F,商城后台改主题色这一页纹丝不动。
		computed: {},
		methods: {
			async handleScan() {
				try {
					const code = this.parseCode(await this.$scan());
					if (code) {
						this.inputCode = code;
						this.queryDetail(code);
					} else {
						this.$util.toast('无效的二维码');
					}
				} catch (e) {
					if (!e || !e.cancel) this.$util.toast((e && e.msg) || '扫码失败，请重试');
				}
			},

			// 2. 手动查询
			handleQuery() {
				if (!this.inputCode) return this.$util.toast('请输入核销码');
				this.queryDetail(this.inputCode);
			},

			// 3. 查详情
			async queryDetail(code) {
				uni.showLoading({
					title: '查询中...',
					mask: true
				});
				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/order/verify',
						method: 'GET',
						data: {
							code: code
						}
					});
					if (res.code === 0) {
						this.verifyData = res.data;
						this.showModal = true;
						if (!res.data.is_allow) uni.vibrateLong();
					} else {
						this.$util.toast(res.msg);
					}
				} catch (e) {
					console.error(e);
				} finally {
					uni.hideLoading();
				}
			},

			// 4. 提交核销
			async doVerify() {
				if (this.isSubmitting || !this.verifyData || !this.verifyData.is_allow) return;
				this.isSubmitting = true;
				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/order/verify',
						method: 'POST',
						data: {
							code: this.inputCode
						}
					});
					if (res.code === 0) {
						uni.showToast({
							title: '核销成功',
							icon: 'success'
						});
						this.closeModal();
						this.inputCode = '';
						uni.vibrateShort();
					} else {
						this.$util.toast(res.msg);
					}
				} catch (e) {
					// err
				} finally {
					this.isSubmitting = false;
				}
			},

			closeModal() {
				this.showModal = false;
				setTimeout(() => {
					this.verifyData = null;
				}, 200);
			},

			copyText(text) {
				uni.setClipboardData({
					data: text
				});
			},

			parseCode(result) {
				if (/^\d+$/.test(result)) return result;
				try {
					if (result.includes('?')) {
						const params = result.split('?')[1];
						// 简单的解析，避免 URLSearchParams 兼容性问题
						const arr = params.split('&');
						for (let i = 0; i < arr.length; i++) {
							const pair = arr[i].split('=');
							if (pair[0] === 'code' || pair[0] === 'scene') return pair[1];
						}
					}
				} catch (e) {}
				return result;
			},

			getTypeName(type) {
				const map = {
					'normal': '自提订单',
					'voucher': '权益券',
					'hotel': '酒店入住',
					'pt': '拼团订单'
				};
				return map[type] || '通用订单';
			}
		}
	}
</script>

<style lang="scss" scoped>
	/* 关键：全局盒模型修正，解决小程序宽度溢出问题 */
	view,
	text,
	image,
	input,
	scroll-view,
	button {
		box-sizing: border-box;
	}

	.container {
		min-height: 100vh;
		position: relative;
		overflow: hidden;
		display: flex;
		flex-direction: column;
	}

	/* 1. 头部区域 - 清新风格 */
	.header-section {
		height: 420rpx;
		background: linear-gradient(180deg, #FFFFFF 0%, #F5F6FA 100%);
		position: relative;
		padding: 180rpx 40rpx 0;

		/* 抽象科技感背景装饰 */
		.deco-shape {
			position: absolute;
			border-radius: 50%;
			
			&.shape-1 {
				top: -80rpx;
				right: -60rpx;
				width: 340rpx;
				height: 340rpx;
				border: 6rpx solid var(--theme-color);
				opacity: 0.05;
			}
			&.shape-2 {
				top: 140rpx;
				right: 200rpx;
				width: 120rpx;
				height: 120rpx;
				border: 4rpx solid var(--theme-color);
				opacity: 0.04;
			}
			&.shape-3 {
				top: 40rpx;
				left: -60rpx;
				width: 200rpx;
				height: 200rpx;
				background: linear-gradient(135deg, var(--theme-color) 0%, transparent 100%);
				opacity: 0.03;
			}
		}

		.header-content {
			position: relative;
			z-index: 2;

			.title {
				font-size: 48rpx;
				font-weight: 800;
				color: #1A1A1A;
				margin-bottom: 16rpx;
				letter-spacing: 2rpx;
			}

			.subtitle {
				display: flex;
				align-items: center;
				color: #777;
				font-size: 26rpx;
			}
		}
	}

	/* 2. 核心操作卡片 (悬浮样式) */
	.action-card {
		margin: -80rpx 30rpx 0;
		background: #fff;
		border-radius: 32rpx;
		padding: 60rpx 40rpx;
		box-shadow: 0 16rpx 40rpx rgba(0, 0, 0, 0.04), 0 4rpx 12rpx rgba(0, 0, 0, 0.02);
		position: relative;
		z-index: 3;
		display: flex;
		flex-direction: column;
		align-items: center;
	}

	/* 扫码区域:一个实心主按钮 + 一句说明。
	   不做取景框/扫描线,别让静态装饰冒充正在工作的摄像头。 */
	.scan-area {
		display: flex;
		flex-direction: column;
		align-items: center;
		width: 100%;

		.scan-btn {
			width: 100%;
			height: 120rpx;
			border-radius: 24rpx;
			background: linear-gradient(135deg, var(--theme-color) 0%, var(--theme-color-light) 100%);
			box-shadow: 0 12rpx 28rpx -6rpx var(--theme-shadow);
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 16rpx;
			transition: transform .15s ease, box-shadow .15s ease;

			.scan-btn-text {
				font-size: 34rpx;
				font-weight: 700;
				color: #fff;
				letter-spacing: 2rpx;
			}
		}

		/* 用 hover-class 而不是 :active —— 小程序里 view 的 :active 不可靠 */
		.scan-btn-hover {
			transform: scale(0.97);
			box-shadow: 0 6rpx 16rpx -6rpx var(--theme-shadow);
		}

		.scan-tip {
			margin-top: 20rpx;
			font-size: 24rpx;
			color: #999;
		}
	}

	/* 分割线 */
	.divider {
		width: 100%;
		margin: 50rpx 0 40rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		position: relative;

		&::before {
			content: '';
			position: absolute;
			left: 0;
			right: 0;
			top: 50%;
			height: 1rpx;
			background: #eee;
			z-index: 1;
		}

		text {
			background: #fff;
			padding: 0 20rpx;
			font-size: 24rpx;
			color: #999;
			position: relative;
			z-index: 2;
		}
	}

	/* 输入区域 */
	.input-area {
		width: 100%;
	}

	.input-box {
		background: #F4F6F9;
		height: 100rpx;
		border-radius: 50rpx;
		display: flex;
		align-items: center;
		padding: 0 12rpx 0 36rpx;
		border: 2rpx solid transparent;
		transition: all 0.3s;

		&:focus-within {
			border-color: var(--theme-color);
			background: #fff;
			box-shadow: 0 4rpx 16rpx rgba(0,0,0,0.03);
		}

		.input-icon {
			margin-right: 20rpx;
		}

		input {
			flex: 1;
			height: 100%;
			font-size: 32rpx;
			color: #333;
		}

		.ph-style {
			color: #C0C4CC;
		}

		.verify-btn {
			padding: 0 36rpx;
			height: 80rpx;
			line-height: 80rpx;
			font-weight: bold;
			font-size: 28rpx;
			border-radius: 40rpx;
			transition: all 0.2s;
			
			&:active {
				transform: scale(0.96);
				opacity: 0.8 !important;
			}
		}
	}

	/* 3. 底部版权 */
	.footer-tips {
		margin-top: auto;
		padding: 40rpx 0 20rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 24rpx;
		color: #ccc;
		gap: 8rpx;
	}

	/* 4. 弹窗样式重构 */
	.verify-modal {
		background: #fff;
		display: flex;
		flex-direction: column;
		max-height: 80vh;
		width: 100%;
		border-radius: 30rpx 30rpx 0 0;
		overflow: hidden;
		/* 防止圆角溢出 */

		.modal-header {
			padding: 30rpx 40rpx;
			display: flex;
			justify-content: space-between;
			align-items: center;
			border-bottom: 1rpx solid #f5f5f5;

			.modal-title {
				font-size: 32rpx;
				font-weight: bold;
				color: #333;
			}

			.close-btn {
				padding: 10rpx;
				margin: -10rpx;
			}
		}

		.modal-scroll-content {
			padding: 30rpx 40rpx;
			width: 100%;
			max-height: 60vh;
			/* 限制高度，允许滚动 */
		}

		/* 状态Banner */
		.status-banner {
			display: flex;
			align-items: center;
			padding: 30rpx;
			border-radius: 16rpx;
			margin-bottom: 30rpx;

			.status-icon {
				width: 60rpx;
				height: 60rpx;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				margin-right: 24rpx;
				flex-shrink: 0;
			}

			.status-text {
				display: flex;
				flex-direction: column;

				.main-msg {
					font-size: 30rpx;
					font-weight: bold;
					color: #333;
				}

				.sub-msg {
					font-size: 22rpx;
					color: #666;
					margin-top: 4rpx;
				}
			}

			&.status-success {
				background: #ecfdf5;

				.status-icon {
					background: #10b981;
				}

				.main-msg {
					color: #059669;
				}
			}

			&.status-fail {
				background: #fff1f2;

				.status-icon {
					background: #f43f5e;
				}

				.main-msg {
					color: #e11d48;
				}
			}
		}

		/* 信息网格 */
		.info-grid {
			background: #f8f9fa;
			border-radius: 16rpx;
			padding: 24rpx;
			margin-bottom: 30rpx;
			display: flex;
			flex-wrap: wrap;

			.grid-item {
				width: 50%;
				margin-bottom: 20rpx;
				display: flex;
				flex-direction: column;
			}

			.grid-row {
				width: 100%;
				display: flex;
				flex-direction: column;
			}

			.label {
				font-size: 22rpx;
				color: #999;
				margin-bottom: 6rpx;
			}

			.value {
				font-size: 28rpx;
				color: #333;
				font-weight: 500;
			}

			.highlight {
				color: var(--theme-color);
			}

			.value-row {
				display: flex;
				align-items: center;
				font-size: 28rpx;
				color: #333;
				font-weight: 500;
			}
		}

		/* 商品列表 */
		.goods-list-wrapper {
			.section-head {
				font-size: 28rpx;
				font-weight: bold;
				color: #333;
				margin-bottom: 20rpx;
				padding-left: 16rpx;
				border-left: 6rpx solid var(--theme-color);
				line-height: 1;
			}

			.goods-item {
				display: flex;
				padding: 20rpx 0;
				border-bottom: 1rpx solid #f5f5f5;

				&:last-child {
					border-bottom: none;
				}

				.g-img {
					width: 100rpx;
					height: 100rpx;
					border-radius: 12rpx;
					background: #eee;
					margin-right: 20rpx;
					flex-shrink: 0;
				}

				.g-content {
					flex: 1;
					display: flex;
					flex-direction: column;
					justify-content: space-between;

					.g-name {
						font-size: 28rpx;
						color: #333;
						line-height: 1.4;
						overflow: hidden;
						text-overflow: ellipsis;
						display: -webkit-box;
						-webkit-line-clamp: 2;
						-webkit-box-orient: vertical;
					}

					.g-bottom {
						display: flex;
						justify-content: space-between;
						align-items: center;

						.g-spec {
							font-size: 22rpx;
							color: #999;
							background: #f5f5f5;
							padding: 2rpx 8rpx;
							border-radius: 4rpx;
						}

						.g-num {
							font-size: 26rpx;
							color: #666;
							font-weight: bold;
						}
					}
				}
			}
		}

		/* 底部按钮 */
		.modal-actions {
			padding: 20rpx 40rpx 40rpx;
			display: flex;
			gap: 24rpx;
			background: #fff;
			box-shadow: 0 -4rpx 20rpx rgba(0, 0, 0, 0.03);

			.action-btn {
				flex: 1;
				height: 80rpx;
				line-height: 80rpx;
				border-radius: 40rpx;
				font-size: 28rpx;
				margin: 0;
				font-weight: bold;

				&.cancel {
					background: #f5f5f5;
					color: #666;
				}

				&.confirm {
					color: #fff;
				}

				&.disabled {
					background: #ddd !important;
					color: #fff;
					opacity: 1;
				}
			}
		}
	}
</style>