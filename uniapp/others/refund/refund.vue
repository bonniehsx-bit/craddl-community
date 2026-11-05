<template>
	<tui-page :isFixed="true" backgroundColor="#f7f8fa">
		<view class="container" :style="themeStyle">
			
			<!-- 1. 商品卡片 -->
			<view class="card goods-card">
				<view class="card-header">退款商品</view>
				<view class="goods-content" v-if="goods">
					<image :src="goods.cover_pic" class="goods-img" mode="aspectFill"></image>
					<view class="goods-info">
						<view class="goods-name">{{goods.name}}</view>
						<view class="goods-spec">{{goods.attr_text || '默认规格'}}</view>
					</view>
					<view class="goods-price-col">
						<!-- 这里展示的是实付单价，不是原价，避免误导用户 -->
						<view class="price">x{{goods.num}}</view>
					</view>
				</view>
			</view>

			<!-- 2. 核心表单区域 -->
			<view class="card form-card">
				
				<!-- 售后类型 -->
				<view class="form-item arrow-right" @click="openTypeSheet">
					<view class="label">售后类型</view>
					<view class="value" :class="{ placeholder: !form.type }">
						{{ currentTypeText || '请选择处理方式' }}
					</view>
					<tui-icon name="arrowright" size="24" color="#ccc"></tui-icon>
				</view>

				<!-- 退款原因 -->
				<view class="form-item arrow-right" @click="openReasonSheet">
					<view class="label">退款原因</view>
					<view class="value" :class="{ placeholder: !form.reason }">
						{{ form.reason || '请选择退款原因' }}
					</view>
					<tui-icon name="arrowright" size="24" color="#ccc"></tui-icon>
				</view>

				<!-- 退款金额:售后只能整件退,金额就是这件能退的全额,不能改 -->
				<view class="money-block">
					<view class="label">退款金额</view>
					<view class="input-row">
						<text class="symbol">¥</text>
						<text class="money-input">{{ form.refund_price }}</text>
					</view>
					<view class="money-tip">
						按整件退款
						<text v-if="refundExpressPrice > 0">，包含发货运费 ¥{{ refundExpressPrice }}</text>
						<text v-else>，不含运费</text>
					</view>
				</view>

			</view>

			<!-- 3. 补充信息 -->
			<view class="card extra-card">
				<view class="form-item-col">
					<view class="label">补充描述 <text class="sub-label">(选填)</text></view>
					<view class="textarea-box">
						<textarea 
							v-model="form.content" 
							placeholder="请详细描述您遇到的问题，有助于更快处理..." 
							placeholder-class="placeholder-text"
							maxlength="200" 
						/>
						<view class="counter">{{ form.content.length }}/200</view>
					</view>
				</view>

				<view class="form-item-col no-border">
					<view class="label">上传凭证 <text class="sub-label">(最多3张)</text></view>
					<view class="upload-box">
						<tui-upload 
							:max="3" 
							:serverUrl="uploadUrl" 
							@complete="uploadComplete"
							@remove="uploadRemove"
						></tui-upload>
					</view>
				</view>
			</view>

			<!-- 4. 提交按钮 -->
			<view class="footer-fixed">
				<view class="submit-btn" @click="submit" :class="{ disabled: isSubmitting }">
					{{ isSubmitting ? '正在提交...' : '提交申请' }}
				</view>
			</view>

		</view>
	</tui-page>
</template>

<script>
	export default {
		data() {
			return {
				order_detail_id: null,
				
				// 状态控制
				isSubmitting: false,
				uploadUrl: '/api/upload/upload', // 你的上传接口

				// 后端数据
				goods: null,
				refundExpressPrice: 0, // 后端若未单独返回，可通过计算得出，或后端增加字段
				refundTypeList: [],
				reasonList: [],

				// 表单数据
				form: {
					type: '',
					reason: '',
					refund_price: '',
					content: '',
					pic_list: []
				}
			};
		},
		computed: {
			// themeColor / themeStyle 由全局 mixin 注入，无需页面内重复 mapState
			currentTypeText() {
				const item = this.refundTypeList.find(i => i.value === this.form.type);
				return item ? item.name : '';
			}
		},
		onLoad(options) {
			if (options.od_id || options.id) {
				this.order_detail_id = options.od_id || options.id;
				this.loadData();
			} else {
				this.showToast('参数缺失');
				setTimeout(() => uni.navigateBack(), 1500);
			}
		},
		methods: {
			showToast(title) {
				uni.showToast({ title, icon: 'none' });
			},

			loadData() {
				uni.showLoading({ title: '加载中' });
				this.$request({
					url: '/api/order/refund',
					data: { order_detail_id: this.order_detail_id }
				}).then(res => {
					uni.hideLoading();
					if (res.code === 0) {
						const data = res.data;
						this.goods = data.goods;
						this.refundTypeList = data.refund_type_list;
						this.reasonList = data.reason_list;
						
						// 【逻辑优化】计算本次退款是否包含运费
						// 如果 后端返回了 express_price 字段最好，没有则可以通过比较得知
						// 这里假设后端在 data 里加一个 refund_express_price 字段标识本次包含多少运费
						this.refundExpressPrice = parseFloat(data.refund_express_price || 0);

						// 整件退款:金额就是这件能退的全额
						this.form.refund_price = data.max_refund_price;

						// 默认选中第一个类型
						if (this.refundTypeList.length > 0) {
							this.form.type = this.refundTypeList[0].value;
						}
					} else {
						uni.showModal({
							content: res.msg,
							showCancel: false,
							success: () => uni.navigateBack()
						});
					}
				}).catch(() => uni.hideLoading());
			},

			openTypeSheet() {
				if (!this.refundTypeList.length) return;
				uni.showActionSheet({
					itemList: this.refundTypeList.map(i => i.name),
					success: (res) => {
						this.form.type = this.refundTypeList[res.tapIndex].value;
					}
				});
			},

			openReasonSheet() {
				if (!this.reasonList.length) return;
				uni.showActionSheet({
					itemList: this.reasonList,
					success: (res) => {
						this.form.reason = this.reasonList[res.tapIndex];
					}
				});
			},

			uploadComplete(e) {
				this.form.pic_list = e.imgArr || [];
			},
			
			uploadRemove(e) {
				this.form.pic_list = e.imgArr || [];
			},

			submit() {
				if (this.isSubmitting) return;
				if (!this.form.type) return this.showToast('请选择售后类型');
				if (!this.form.reason) return this.showToast('请选择退款原因');

				this.isSubmitting = true;
				uni.showLoading({ title: '提交中' });

				this.$request({
					url: '/api/order/refund',
					method: 'POST',
					data: {
						order_detail_id: this.order_detail_id,
						...this.form
					}
				}).then(res => {
					uni.hideLoading();
					this.isSubmitting = false;
					if (res.code === 0) {
						uni.showToast({ title: '申请成功', icon: 'success' });
						setTimeout(() => {
							// 刷新上级页面并返回
							const pages = getCurrentPages();
							if (pages.length > 1) {
								const prevPage = pages[pages.length - 2];
								if (prevPage.$vm.refresh) prevPage.$vm.refresh();
							}
							uni.navigateBack();
						}, 1500);
					} else {
						this.showToast(res.msg);
					}
				}).catch(() => {
					uni.hideLoading();
					this.isSubmitting = false;
				});
			}
		}
	}
</script>

<style lang="scss" scoped>
	/* 变量定义 */
	$bg-color: #f7f8fa;
	$card-bg: #ffffff;
	$text-main: #333333;
	$text-sub: #999999;
	$border-color: #eeeeee;
	$theme-color: var(--theme-color);

	.container {
		padding: 24rpx;
		padding-bottom: 144rpx;
	}

	/* 通用卡片样式 */
	.card {
		background: $card-bg;
		border-radius: 24rpx;
		padding: 30rpx;
		margin-bottom: 24rpx;
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.03);
	}

	/* 1. 商品卡片 */
	.goods-card {
		.card-header {
			font-size: 30rpx;
			font-weight: 600;
			color: $text-main;
			margin-bottom: 24rpx;
		}
		.goods-content {
			display: flex;
			align-items: flex-start;
		}
		.goods-img {
			width: 140rpx;
			height: 140rpx;
			border-radius: 12rpx;
			background-color: #f5f5f5;
			flex-shrink: 0;
		}
		.goods-info {
			flex: 1;
			margin-left: 20rpx;
			margin-right: 20rpx;
			display: flex;
			flex-direction: column;
			justify-content: space-between;
			height: 140rpx;
		}
		.goods-name {
			font-size: 28rpx;
			color: $text-main;
			line-height: 1.4;
			display: -webkit-box;
			-webkit-box-orient: vertical;
			-webkit-line-clamp: 2;
			overflow: hidden;
		}
		.goods-spec {
			font-size: 24rpx;
			color: $text-sub;
			background: #f7f7f7;
			padding: 4rpx 10rpx;
			border-radius: 6rpx;
			align-self: flex-start;
		}
		.goods-price-col {
			display: flex;
			flex-direction: column;
			align-items: flex-end;
			.price {
				font-size: 26rpx;
				color: $text-sub;
			}
		}
	}

	/* 2. 表单样式 */
	.form-item {
		display: flex;
		align-items: center;
		padding: 28rpx 0;
		border-bottom: 1rpx solid #f5f5f5;
		
		&.arrow-right:active {
			background-color: #fafafa;
		}

		.label {
			width: 160rpx;
			font-size: 28rpx;
			color: $text-main;
			font-weight: 500;
		}
		.value {
			flex: 1;
			text-align: right;
			font-size: 28rpx;
			color: $text-main;
			margin-right: 10rpx;
			
			&.placeholder {
				color: #ccc;
			}
		}
	}

	/* 3. 金额输入区 - 重点设计 */
	.money-block {
		padding-top: 30rpx;
		
		.label {
			font-size: 28rpx;
			color: $text-main;
			font-weight: 500;
			margin-bottom: 20rpx;
		}
		
		.input-row {
			display: flex;
			align-items: baseline;
			border-bottom: 1rpx solid #eee;
			padding-bottom: 10rpx;
			
			.symbol {
				font-size: 40rpx;
				font-weight: bold;
				color: $text-main;
				margin-right: 10rpx;
			}
			
			.money-input {
				flex: 1;
				height: 80rpx;
				font-size: 56rpx;
				font-weight: bold;
				color: $text-main;
				/* uni-app input 特定样式 */
				caret-color: $theme-color; 
			}
		}
		
		.money-tip {
			margin-top: 16rpx;
			font-size: 24rpx;
			color: $text-sub;
			
			.highlight {
				color: $theme-color;
				font-weight: bold;
				margin: 0 4rpx;
			}
		}
	}

	/* 4. 补充信息样式 */
	.form-item-col {
		padding: 24rpx 0;
		border-bottom: 1rpx solid #f5f5f5;
		
		&.no-border {
			border-bottom: none;
			padding-bottom: 0;
		}

		.label {
			font-size: 28rpx;
			color: $text-main;
			font-weight: 500;
			margin-bottom: 20rpx;
			
			.sub-label {
				font-size: 24rpx;
				color: #ccc;
				font-weight: normal;
				margin-left: 10rpx;
			}
		}
		
		.textarea-box {
			position: relative;
			background: #f9f9f9;
			border-radius: 12rpx;
			padding: 20rpx;
			
			textarea {
				width: 100%;
				height: 180rpx;
				font-size: 28rpx;
				line-height: 1.5;
				color: $text-main;
			}
			
			.placeholder-text {
				color: #ccc;
			}
			
			.counter {
				text-align: right;
				font-size: 22rpx;
				color: #ccc;
				margin-top: 10rpx;
			}
		}
		
		.upload-box {
			margin-top: 10rpx;
		}
	}

	/* 5. 底部按钮 */
	/* 高度和 tui-tabbar 一致:固定 120rpx,不再额外加底部安全区(用户认定的高度,见 5fa9a32cf2) */
	.footer-fixed {
		position: fixed;
		bottom: 0;
		left: 0;
		width: 100%;
		height: 120rpx;
		background: #fff;
		padding: 16rpx 32rpx;
		box-shadow: 0 -4rpx 20rpx rgba(0,0,0,0.05);
		z-index: 99;
		box-sizing: border-box;
		
		.submit-btn {
			height: 88rpx;
			line-height: 88rpx;
			text-align: center;
			color: #fff;
			font-size: 32rpx;
			font-weight: 500;
			border-radius: 44rpx;
			/* 主题色渐变：终点为主题色，起点为 mixin 注入的浅色版 */
			background: linear-gradient(90deg, var(--theme-color-light), var(--theme-color));
			box-shadow: 0 6rpx 16rpx var(--theme-shadow);

			&:active {
				opacity: 0.9;
			}
			
			&.disabled {
				opacity: 0.6;
				background: #ccc !important;
				box-shadow: none;
			}
		}
	}
</style>