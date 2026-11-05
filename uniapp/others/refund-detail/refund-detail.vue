<template>
	<view class="page-container" v-if="detail" :style="{ '--theme-color': themeColor }">
		
		<!-- 1. 状态头部 -->
		<view class="status-header">
			<view class="status-icon">
				<uni-icons :type="statusInfo.icon" color="#fff" size="34"></uni-icons>
			</view>
			<view class="status-content">
				<view class="status-text">{{ statusInfo.text }}</view>
				<view class="status-desc">{{ statusInfo.desc }}</view>
			</view>
		</view>

		<!-- 2. 【新增】进度时间轴 -->
		<view class="card process-card" v-if="detail.process_list && detail.process_list.length > 0">
			<view class="process-item" v-for="(item, index) in detail.process_list" :key="index" :class="{active: item.active}">
				<view class="process-dot"></view>
				<view class="process-info">
					<view class="process-title">{{ item.title }}</view>
					<view class="process-time" v-if="item.desc">{{ item.desc }}</view>
				</view>
			</view>
		</view>

		<!-- 3. 退货地址卡片 -->
		<!-- 显示条件：类型是退货退款(2)或换货(3) 且 状态是商家已同意(1)或用户已发货(5) -->
		<view class="card address-card" v-if="showReturnAddress">
			<view class="card-header">
				<uni-icons type="paperplane" size="20" :color="themeColor"></uni-icons>
				<text class="card-title">退货地址</text>
			</view>
			<view class="address-info">
				<view class="address-line">
					<text class="user-info">{{ detail.return_name }} {{ detail.return_mobile }}</text>
					<button class="copy-btn" size="mini"
						@click="copyText(detail.return_name + ' ' + detail.return_mobile + ' ' + detail.return_address)">复制</button>
				</view>
				<view class="address-text">{{ detail.return_address }}</view>
			</view>
			<view class="address-tip" v-if="detail.status == 1">商家已同意，请在7天内寄回并填写运单号</view>
		</view>

		<!-- 4. 退款金额 -->
		<view class="card refund-amount-card">
			<text class="label">退款金额</text>
			<text class="amount">¥{{ detail.refund_price }}</text>
		</view>

		<!-- 5. 商品信息 -->
		<view class="card goods-card">
			<image :src="detail.goods_pic" class="goods-image" mode="aspectFill"></image>
			<view class="goods-info">
				<view class="goods-name">{{ detail.goods_name }}</view>
				<view class="goods-specs">{{ detail.attr_text }}</view>
			</view>
			<view class="goods-quantity">×{{ detail.num }}</view>
		</view>

		<!-- 6. 售后详细信息 -->
		<view class="card info-card">
			<view class="info-row">
				<text class="info-label">服务类型</text>
				<!-- 1=仅退款, 2=退货退款, 3=换货 -->
				<text class="info-value">{{ detail.type == 1 ? '仅退款' : (detail.type == 2 ? '退货退款' : '换货') }}</text>
			</view>
			<view class="info-row">
				<text class="info-label">申请原因</text>
				<text class="info-value">{{ detail.reason || detail.content }}</text>
			</view>
			
			<!-- 如果有拒绝原因，醒目展示 -->
			<view class="info-row" v-if="detail.status == 2 && detail.remark">
				<text class="info-label" style="color: #ff4d4f;">拒绝原因</text>
				<text class="info-value" style="color: #ff4d4f;">{{ detail.remark }}</text>
			</view>

			<!-- 凭证图片 -->
			<view class="info-row multi-line" v-if="detail.pic_list && detail.pic_list.length > 0">
				<text class="info-label">补充凭证</text>
				<view class="image-grid">
					<view class="image-wrapper" v-for="(imgUrl, imgIndex) in detail.pic_list" :key="imgIndex"
						@click="previewImg(imgUrl, detail.pic_list)">
						<image :src="imgUrl" mode="aspectFill" class="grid-image"></image>
					</view>
				</view>
			</view>
			
			<view class="info-row">
				<text class="info-label">申请时间</text>
				<text class="info-value">{{ detail.created_at }}</text>
			</view>
			<view class="info-row">
				<text class="info-label">售后编号</text>
				<text class="info-value">{{ detail.order_no }}</text>
			</view>

			<!-- 用户填写的物流信息 -->
			<template v-if="detail.user_express">
				<view class="divider"></view>
				<view class="info-row">
					<text class="info-label">退货物流</text>
					<text class="info-value">{{ detail.user_express }}</text>
				</view>
				<view class="info-row">
					<text class="info-label">物流单号</text>
					<text class="info-value">{{ detail.user_express_no }}</text>
				</view>
				<view class="info-row">
					<text class="info-label">发货时间</text>
					<text class="info-value">{{ detail.user_express_at }}</text>
				</view>
			</template>
		</view>

		<!-- 7. 底部操作栏 -->
		<!-- 左边客服(按这单的商家找客服,平台单回落平台客服),右边按状态放按钮;与订单详情底栏同一布局 -->
		<view class="footer-bar">
			<tui-cs-wrapper :goods="csGoods" :currentUrl="currentUrl" class="footer-cs">
				<view class="footer-cs-inner">
					<tui-icon name="kefu" :size="20" color="#666"></tui-icon>
					<text>客服</text>
				</view>
			</tui-cs-wrapper>

			<view class="footer-actions">
				<!-- 允许撤销的条件：待审核(0) 或 商家已同意(1)且尚未发货/未打款 -->
				<button class="action-btn plain" v-if="detail.status == 0 || detail.status == 1" @click="cancelRefund">撤销申请</button>

				<!-- 填写单号条件：必须是退货退款(2) 且 商家已同意(1) -->
				<button class="action-btn primary"
					v-if="detail.type == 2 && detail.status == 1"
					:style="{ backgroundColor: themeColor }"
					@click="openExpressModal">填写退货单号</button>

				<!-- 被拒绝:这件商品可以再申请(订单详情里本来也会重新出现「申请售后」) -->
				<button class="action-btn primary" v-if="detail.status == 2"
					:style="{ backgroundColor: themeColor }"
					@click="reapply">重新申请</button>

				<!-- 其余状态(退货中/退款中/已完成/已关闭)没有要顾客做的事,给个回订单的入口,底栏不空着 -->
				<button class="action-btn plain" v-if="[3, 4, 5, 6].includes(Number(detail.status))" @click="goOrder">查看订单</button>
			</view>
		</view>

		<!-- 填写运单号弹窗 -->
		<uni-popup ref="expressPopup" type="bottom">
			<view class="popup-container">
				<view class="popup-header">
					<text>填写退货单号</text>
					<uni-icons type="closeempty" size="20" color="#999" @click="closeExpressModal"></uni-icons>
				</view>
				<view class="popup-body">
					<view class="form-item">
						<text class="form-label">快递公司</text>
						<input class="form-input" v-model="expressForm.company" placeholder="请输入快递公司名称" />
					</view>
					<view class="form-item">
						<text class="form-label">快递单号</text>
						<input class="form-input" v-model="expressForm.number" placeholder="请输入快递运单号" />
					</view>
				</view>
				<view class="popup-footer">
					<button class="submit-btn" 
						:style="{ backgroundColor: themeColor }"
						@click="submitExpressInfo">确认提交</button>
				</view>
			</view>
		</uni-popup>

	</view>
</template>

<script>
	import { mapState } from 'vuex';
	import TuiCsWrapper from '@/components/thorui/tui-cs-wrapper/tui-cs-wrapper.vue';

	export default {
		components: { TuiCsWrapper },
		data() {
			return {
				order_detail_id: null,
				detail: null,
				expressForm: {
					company: '', 
					number: '' 
				}
			};
		},
		computed: {
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F'
			}),

			// 给客服组件:按 mch_id 找这家店的客服,卡片带上这件商品
			csGoods() {
				if (!this.detail) return null;
				return {
					name: this.detail.goods_name,
					cover_pic: this.detail.goods_pic,
					price: this.detail.price,
					mch_id: this.detail.mch_id || 0
				};
			},
			currentUrl() {
				return this.order_detail_id ? `/others/refund-detail/refund-detail?od_id=${this.order_detail_id}` : '';
			},

			// 是否显示退货地址模块
			showReturnAddress() {
				if (!this.detail) return false;
				// 类型：2=退货退款, 3=换货
				const isReturn = this.detail.type == 2 || this.detail.type == 3;
				// 状态：1=同意, 5=用户已发货
				const isValidStatus = this.detail.status == 1 || this.detail.status == 5;
				return isReturn && isValidStatus;
			},

			// 状态文案核心逻辑
			statusInfo() {
				if (!this.detail) return {};
				
				const type = parseInt(this.detail.type); // 1:仅退款, 2:退货退款
				const status = parseInt(this.detail.status);

				// 退款中(6)、已完成(3):按这笔退款的实际进度说(后端给),不一律说「已原路退回」——
				// 人工登记、线下退的不是原路;退款遇到延迟时也要如实说
				const p = this.detail.refund_progress;
				if (p && (status === 6 || status === 3)) {
					return {
						icon: p.state === 'done' ? 'checkbox-filled' : 'info-filled',
						text: status === 3 ? '售后已完成' : p.state_text,
						desc: p.desc
					};
				}

				const statusMap = {
					0: { // 待审核
						icon: 'help-filled',
						text: '待商家处理',
						desc: '您的售后申请已提交，请耐心等待商家审核'
					},
					1: { // 已同意 - 需要区分类型
						icon: 'checkbox-filled',
						text: '商家已同意',
						// 【核心差异点】
						desc: type === 1 
							? '商家已同意退款，请等待系统打款' 
							: '请按下方地址寄回商品，并点击底部按钮填写单号'
					},
					2: { // 已拒绝
						icon: 'close-filled',
						text: '商家已拒绝',
						desc: '您的售后申请已被商家拒绝，如有疑问请联系客服'
					},
					3: { // 已完成
						icon: 'checkbox-filled',
						text: '售后已完成',
						desc: '退款金额 ¥' + this.detail.refund_price + ' 已原路退回'
					},
					4: { // 已撤销
						icon: 'info-filled',
						text: '售后已关闭',
						desc: '您已主动撤销本次售后申请'
					},
					5: { // 用户已发货 (仅退货退款有此状态)
						icon: 'paperplane-filled',
						text: '退货中',
						desc: '您已填写物流信息，请等待商家确认收货'
					},
					6: { // 商家已确认打款,钱由系统按原支付方式去退
						icon: 'info-filled',
						text: '退款中',
						desc: '商家已同意退款，正在按原支付方式退回，请耐心等待'
					},
				};
				
				return statusMap[status] || {
					icon: 'info-filled',
					text: '未知状态',
					desc: ''
				};
			}
		},
		onLoad(options) {
			if (!options.od_id) {
				uni.showModal({
					title: '错误',
					content: '缺少关键参数',
					showCancel: false,
					success: () => uni.navigateBack()
				});
				return;
			}
			this.order_detail_id = options.od_id;
			this.getDetail();
		},
		methods: {
			async getDetail() {
				uni.showLoading({
					title: '加载中...'
				});
				try {
					const res = await this.$request({
						url: '/api/order/refund-detail', 
						data: {
							order_detail_id: this.order_detail_id
						}
					});
					if (res.code === 0) {
						this.detail = res.data.detail;
					} else {
						// 使用通用弹窗或 uni.showModal
						uni.showModal({
							title: '提示',
							content: res.msg,
							showCancel: false,
							success: () => uni.navigateBack()
						});
					}
				} catch (e) {
					uni.showToast({ title: '网络请求失败', icon: 'none' });
				} finally {
					uni.hideLoading();
				}
			},
			previewImg(currentUrl, urls) {
				uni.previewImage({
					current: currentUrl,
					urls: urls
				});
			},
			// 重新申请:替换掉本页,提交后返回的是订单详情,不会退回到这张已拒绝的售后
			reapply() {
				uni.redirectTo({
					url: `/others/refund/refund?od_id=${this.order_detail_id}&order_id=${this.detail.order_id}`
				});
			},
			// 从订单详情点进来的就直接返回,免得页面栈里叠两个订单详情
			goOrder() {
				const pages = getCurrentPages();
				const prev = pages.length > 1 ? pages[pages.length - 2] : null;
				if (prev && prev.route && prev.route.indexOf('order-detail/order-detail') !== -1) {
					uni.navigateBack();
					return;
				}
				uni.navigateTo({ url: `/others/order-detail/order-detail?order_id=${this.detail.order_id}` });
			},
			cancelRefund() {
				uni.showModal({
					title: '提示',
					content: '确定要撤销本次售后申请吗？',
					success: async (res) => {
						if (res.confirm) {
							uni.showLoading({ title: '处理中...' });
							try {
								const apiRes = await this.$request({
									url: '/api/order/refund-cancel', 
									method: 'POST',
									data: {
										order_detail_id: this.order_detail_id
									}
								});
								if (apiRes.code === 0) {
									uni.showToast({
										title: '撤销成功',
										icon: 'success'
									});
									// 刷新详情，状态会变为 4(已关闭)
									this.getDetail();
								} else {
									uni.showToast({ title: apiRes.msg, icon: 'none' });
								}
							} finally {
								uni.hideLoading();
							}
						}
					}
				});
			},
			copyText(text) {
				uni.setClipboardData({
					data: text,
					success: () => {
						uni.showToast({
							title: '已复制',
							icon: 'none'
						});
					}
				});
			},
			openExpressModal() {
				this.expressForm.company = '';
				this.expressForm.number = '';
				this.$refs.expressPopup.open();
			},
			closeExpressModal() {
				this.$refs.expressPopup.close();
			},
			async submitExpressInfo() {
				if (!this.expressForm.company.trim()) {
					return uni.showToast({
						title: '请输入快递公司',
						icon: 'none'
					});
				}
				if (!this.expressForm.number.trim()) {
					return uni.showToast({
						title: '请输入快递单号',
						icon: 'none'
					});
				}

				uni.showLoading({ title: '提交中...' });
				try {
					const res = await this.$request({
						url: '/api/order/refund-fill-express', 
						method: 'POST',
						data: {
							order_detail_id: this.order_detail_id,
							express: this.expressForm.company,
							express_no: this.expressForm.number
						}
					});

					if (res.code === 0) {
						uni.showToast({
							title: '提交成功',
							icon: 'success'
						});
						this.closeExpressModal();
						this.getDetail(); 
					} else {
						uni.showToast({ title: res.msg, icon: 'none' });
					}
				} finally {
					uni.hideLoading();
				}
			}
		}
	}
</script>

<style lang="scss" scoped>
	/* 声明 CSS 变量 */
	$theme-color: var(--theme-color);

	.page-container {
		background-color: #f7f8fa;
		min-height: 100vh;
		padding: 24rpx;
		padding-bottom: 160rpx;
	}

	/* 状态头部 */
	.status-header {
		display: flex;
		align-items: center;
		background: linear-gradient(90deg, $theme-color, $theme-color); 
		opacity: 0.95;
		padding: 40rpx 30rpx;
		border-radius: 16rpx;
		color: #fff;
		margin-bottom: 24rpx;
		box-shadow: 0 4rpx 12rpx rgba($theme-color, 0.2);
	}

	.status-icon {
		margin-right: 24rpx;
	}

	.status-content {
		flex: 1;
		.status-text {
			font-size: 36rpx;
			font-weight: bold;
		}

		.status-desc {
			font-size: 26rpx;
			opacity: 0.9;
			margin-top: 8rpx;
		}
	}

	.card {
		background-color: #fff;
		border-radius: 16rpx;
		padding: 30rpx;
		margin-bottom: 24rpx;
	}

	/* 进度时间轴样式 */
	.process-card {
		padding-left: 50rpx;
	}
	.process-item {
		position: relative;
		padding-bottom: 40rpx;
		padding-left: 30rpx;
		border-left: 2rpx solid #eee;

		&:last-child {
			border-left: none;
			padding-bottom: 0;
		}

		.process-dot {
			position: absolute;
			left: -11rpx; /* (20rpx/2 + 1px border) */
			top: 0;
			width: 20rpx;
			height: 20rpx;
			border-radius: 50%;
			background-color: #ddd;
			border: 4rpx solid #fff;
			box-shadow: 0 0 0 1rpx #ddd;
		}

		&.active {
			.process-dot {
				background-color: $theme-color;
				box-shadow: 0 0 0 1rpx $theme-color;
			}
			.process-title {
				color: #333;
				font-weight: bold;
			}
		}

		.process-info {
			transform: translateY(-6rpx); /* 对齐圆点 */
		}

		.process-title {
			font-size: 28rpx;
			color: #999;
			margin-bottom: 6rpx;
		}

		.process-time {
			font-size: 24rpx;
			color: #ccc;
		}
	}

	/* 退款金额 */
	.refund-amount-card {
		display: flex;
		justify-content: space-between;
		align-items: baseline;

		.label {
			font-size: 28rpx;
			color: #333;
			font-weight: 500;
		}

		.amount {
			font-size: 40rpx;
			color: $theme-color;
			font-weight: bold;
		}
	}

	/* 商品卡片 */
	.goods-card {
		display: flex;
		align-items: flex-start;
	}

	.goods-image {
		width: 150rpx;
		height: 150rpx;
		border-radius: 12rpx;
		flex-shrink: 0;
		margin-right: 20rpx;
		background-color: #f5f5f5;
	}

	.goods-info {
		flex: 1;
		min-width: 0;
		height: 150rpx;
		display: flex;
		flex-direction: column;
		justify-content: space-between;
	}

	.goods-name {
		font-size: 28rpx;
		color: #333;
		line-height: 1.4;
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 2;
		overflow: hidden;
	}

	.goods-specs {
		font-size: 24rpx;
		color: #999;
		background-color: #f8f8f8;
		padding: 4rpx 10rpx;
		border-radius: 6rpx;
		align-self: flex-start;
		max-width: 100%;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.goods-quantity {
		font-size: 28rpx;
		color: #999;
		margin-left: 20rpx;
		padding-top: 10rpx;
	}

	/* 信息行 */
	.info-row {
		display: flex;
		justify-content: space-between;
		font-size: 28rpx;
		padding: 16rpx 0;
		line-height: 1.5;

		&.multi-line {
			flex-direction: column;
			align-items: flex-start;

			.info-label {
				margin-bottom: 20rpx;
			}
		}
	}

	.info-label {
		color: #666;
		flex-shrink: 0;
		margin-right: 30rpx;
		min-width: 120rpx;
	}

	.info-value {
		color: #333;
		text-align: right;
		flex: 1;
		word-break: break-all;
	}

	/* 图片宫格 */
	.image-grid {
		display: flex;
		flex-wrap: wrap;
		gap: 16rpx;
		width: 100%;
	}

	.image-wrapper {
		width: 140rpx;
		height: 140rpx;
		position: relative;
	}

	.grid-image {
		width: 100%;
		height: 100%;
		border-radius: 12rpx;
		background-color: #f5f5f5;
	}

	/* 底部固定栏 */
	/* 高度和 tui-tabbar 一致:固定 120rpx,不再额外加底部安全区(用户认定的高度,见 5fa9a32cf2) */
	.footer-bar {
		position: fixed;
		bottom: 0;
		left: 0;
		width: 100%;
		height: 120rpx;
		background-color: #fff;
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 0 30rpx;
		box-sizing: border-box;
		box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.05);
		gap: 20rpx;
	}

	.footer-cs-inner {
		display: flex;
		flex-direction: column;
		align-items: center;
		min-width: 80rpx;
		font-size: 22rpx;
		color: #666;

		&:active { opacity: 0.7; }
	}

	.footer-actions {
		display: flex;
		align-items: center;
		gap: 20rpx;
	}

	.action-btn {
		height: 68rpx;
		line-height: 66rpx;
		font-size: 26rpx;
		padding: 0 32rpx;
		border-radius: 34rpx;
		margin: 0;
		box-sizing: border-box;

		&.plain {
			background-color: #fff;
			color: #333;
			border: 1rpx solid #ddd;
		}

		&.primary {
			color: #fff;
			border: none;
		}

		&::after {
			border: none;
		}
	}

	/* 地址卡片 */
	.address-card {
		.card-header {
			display: flex;
			align-items: center;
			gap: 12rpx;
			font-size: 30rpx;
			font-weight: 500;
			color: #333;
			padding-bottom: 20rpx;
			border-bottom: 1rpx solid #f0f0f0;
			margin-bottom: 20rpx;
		}

		.address-info {
			.address-line {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 10rpx;

				.user-info {
					font-size: 28rpx;
					font-weight: bold;
					color: #333;
				}

				.copy-btn {
					background-color: #f7f8fa;
					color: #666;
					border: 1rpx solid #e5e5e5;
					height: 48rpx;
					line-height: 46rpx;
					font-size: 24rpx;
					padding: 0 20rpx;
					margin: 0;
				}
			}

			.address-text {
				font-size: 26rpx;
				color: #666;
				line-height: 1.5;
			}
		}

		.address-tip {
			margin-top: 20rpx;
			font-size: 24rpx;
			color: #ff976a;
			background-color: #fffbe8;
			padding: 16rpx;
			border-radius: 8rpx;
		}
	}

	.divider {
		height: 1rpx;
		background-color: #f0f0f0;
		margin: 20rpx 0;
	}

	/* 弹窗样式 */
	.popup-container {
		background-color: #fff;
		border-top-left-radius: 20rpx;
		border-top-right-radius: 20rpx;
		padding: 30rpx;
		padding-bottom: calc(env(safe-area-inset-bottom) + 30rpx);

		.popup-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			font-size: 32rpx;
			font-weight: 500;
			color: #333;
			margin-bottom: 40rpx;
		}

		.popup-body {
			.form-item {
				display: flex;
				align-items: center;
				padding: 24rpx 0;
				border-bottom: 1rpx solid #f0f0f0;

				.form-label {
					font-size: 28rpx;
					color: #333;
					width: 180rpx;
				}

				.form-input {
					flex: 1;
					font-size: 28rpx;
					color: #333;
				}
			}
		}

		.popup-footer {
			margin-top: 50rpx;

			.submit-btn {
				height: 88rpx;
				line-height: 88rpx;
				color: #fff;
				border-radius: 44rpx;
				font-size: 30rpx;

				&::after {
					border: none;
				}
			}
		}
	}
</style>