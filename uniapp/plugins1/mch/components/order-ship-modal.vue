<template>
	<!-- [核心改造] 使用 tui-bottom-popup -->
	<tui-bottom-popup :show="popupShow" @close="close">
		<view class="tui-popup-box">
			<!-- 标题和关闭按钮 -->
			<view class="tui-popup-header">
				<view class="tui-title">订单发货</view>
				<view class="tui-close" @click="close">
					<tui-icon name="close-fill" color="#999" :size="20"></tui-icon>
				</view>
			</view>

			<!-- [风格对齐] 滚动区域，即使内容不多也保持结构一致 -->
			<scroll-view scroll-y class="tui-popup-scroll">
				<view class="tui-scrollview-box">
					<!-- [风格对齐] 收货信息预览 -->
					<view class="tui-bold tui-attr-title">收货信息</view>
					<view class="address-preview">
						<view class="address-line">
							<text class="label">收货人：</text>
							<text class="value">{{ order.name }} {{ order.mobile }}</text>
						</view>
						<view class="address-line">
							<text class="label">收货地址：</text>
							<text class="value address">{{ order.address }}</text>
						</view>
					</view>
					
					<!-- [风格对齐] 发货表单 -->
					<view class="tui-bold tui-attr-title" style="margin-top: 20rpx;">发货信息</view>
					<view class="form-section">
						<!-- 快递公司选择 -->
						<picker @change="onExpressChange" :value="expressIndex" :range="expressList" range-key="name">
							<view class="form-item">
								<view class="form-label required">快递公司</view>
								<view class="picker-view" :class="{'placeholder': expressIndex === -1}">
									{{ expressIndex === -1 ? '请选择快递公司' : expressList[expressIndex].name }}
								</view>
								<tui-icon name="arrowright" :size="20" color="#999"></tui-icon>
							</view>
						</picker>
						<!-- 快递单号输入 -->
						<view class="form-item">
							<view class="form-label required">快递单号</view>
							<input class="form-input" v-model="formData.express_no" placeholder="请输入快递单号" />
						</view>
					</view>
				</view>
			</scroll-view>

			<!-- [风格对齐] 底部操作按钮 -->
			<view class="tui-operation tui-operation-right tui-right-flex tui-popup-btn"
				:style="{height:is_ios?'172rpx':'110rpx'}">
				<view class="tui-flex-1">
					<tui-button height="72rpx" :size="28" type="danger" shape="circle" @click="submitShipment">
						确认发货
					</tui-button>
				</view>
			</view>
		</view>
	</tui-bottom-popup>
</template>

<script>
	export default {
		name: "ship-modal",
		data() {
			return {
				popupShow: false,
				order: {},
				expressList: [],
				expressIndex: -1,
				formData: {
					order_id: null,
					express_name: '',
					express_code: '',
					express_no: ''
				},
				is_ios: false,
			};
		},
		created() {
			uni.getSystemInfo({
				success: res => {
					if (res.platform == 'ios' || res.platform == 'devtools') {
						this.is_ios = true;
					}
				}
			});
			this.loadExpressList();
		},
		methods: {
			async loadExpressList() {
				// 逻辑与之前一致
				const res = await this.$request({
					url: '/common/express/express'
				});
				if (res.code === 0) {
					this.expressList = res.data.list;
				}
			},

			open(order) {
				this.order = order;
				this.formData.order_id = order.id;
				this.expressIndex = -1;
				this.formData.express_name = '';
				this.formData.express_code = '';
				this.formData.express_no = '';
				this.popupShow = true;
			},

			close() {
				this.popupShow = false;
			},
			
			onExpressChange(e) {
				this.expressIndex = e.detail.value;
				const selected = this.expressList[this.expressIndex];
				this.formData.express_name = selected.name;
				this.formData.express_code = selected.key;
			},

			async submitShipment() {
				// 表单验证逻辑与之前一致
				if (this.expressIndex === -1) {
					this.$tui.toast('请选择快递公司');
					return;
				}
				if (!this.formData.express_no.trim()) {
					this.$tui.toast('请输入快递单号');
					return;
				}
				
				// [风格对齐] 使用 handleAction 模式
				uni.showLoading({ title: '正在提交...' });
				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/order/express-ship',
						method: 'POST',
						data: this.formData
					});
					if (res.code === 0) {
						uni.showToast({ title: '发货成功', icon: 'success' });
						this.close();
						this.$emit('success');
					} else {
						this.$showModal(res.msg);
					}
				} finally {
					uni.hideLoading();
				}
			}
		}
	}
</script>

<style lang="scss" scoped>
	/* [核心改造] 样式完全参考您的 goods.vue */
	.tui-popup-box {
		position: relative;
		padding-top: 20rpx;
		/* 底部按钮会覆盖，所以主内容区不需要底部padding */
	}

	.tui-popup-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 0 30rpx 20rpx;
		.tui-title {
			font-size: 32rpx;
			font-weight: bold;
			flex: 1;
			text-align: center;
		}
		.tui-close {
			position: absolute;
			right: 30rpx;
			top: 24rpx;
		}
	}

	.tui-popup-scroll {
		/* 预留底部按钮区域的高度 */
		height: 900rpx; 
		font-size: 26rpx;
	}

	.tui-scrollview-box {
		padding: 0 30rpx;
	}

	.tui-bold {
		font-weight: bold;
	}

	.tui-attr-title {
		padding: 10rpx 0;
		color: #333;
		font-size: 28rpx;
	}
	
	.address-preview {
		background-color: #f7f7f7;
		border-radius: 16rpx;
		padding: 24rpx;
		font-size: 26rpx;
		.address-line {
			display: flex;
			line-height: 1.6;
			.label { color: #999; flex-shrink: 0; }
			.value { color: #333; }
			.address { word-break: break-all; }
		}
		.address-line + .address-line {
			margin-top: 10rpx;
		}
	}

	.form-section {
		.form-item {
			display: flex;
			align-items: center;
			padding: 30rpx 0;
			border-bottom: 1rpx solid #eaeef1;
			font-size: 28rpx;
			
			.form-label {
				width: 180rpx;
				color: #333;
				flex-shrink: 0;
				&.required::before {
					content: '*';
					color: $uni-red-color;
					margin-right: 4rpx;
				}
			}
			
			.picker-view {
				flex: 1;
				display: flex;
				justify-content: flex-end;
				align-items: center;
				&.placeholder { color: #999; }
			}
			
			.form-input {
				flex: 1;
				text-align: right;
			}
		}
		.form-item:last-child {
			border-bottom: none;
		}
	}
	
	/* [风格对齐] 底部操作按钮 */
	.tui-operation {
		width: 100%;
		height: 110rpx;
		background: rgba(255, 255, 255, 0.98);
		position: relative;
		display: flex;
		align-items: center;
		justify-content: space-between;
		z-index: 10;
		padding-bottom: env(safe-area-inset-bottom);
	}
	.tui-operation::before {
		content: '';
		position: absolute;
		top: 0;
		right: 0;
		left: 0;
		border-top: 1rpx solid #eaeef1;
		transform: scaleY(0.5);
	}
	.tui-popup-btn {
		width: 100%;
		position: absolute;
		left: 0;
		bottom: 0;
		padding: 0 16rpx;
		box-sizing: border-box;
	}
	.tui-right-flex {
		display: flex;
		align-items: center;
		justify-content: center;
	}
	.tui-flex-1 {
		flex: 1;
		padding: 0 16rpx;
	}
</style>