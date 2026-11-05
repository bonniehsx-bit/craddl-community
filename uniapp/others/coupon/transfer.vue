<template>
	<tui-page class="container">

		<!-- 记录入口 -->
		<view class="log-entry" @click="goToLogs">
			<tui-icon name="order" :size="16" color="#E22729"></tui-icon>
			<text class="log-entry-text">转赠记录</text>
			<tui-icon name="right" :size="14" color="#ccc"></tui-icon>
		</view>

		<!-- 1. 当前优惠券信息卡片 -->
		<view class="coupon-card">
			<view class="coupon-content">
				<view class="left">
					<view class="price-box">
						<text class="symbol">¥</text>
						<text class="price">{{ couponInfo.sub_price }}</text>
					</view>
					<view class="condition" v-if="couponInfo.min_price > 0">满{{ couponInfo.min_price }}可用</view>
					<view class="condition" v-else>无门槛</view>
				</view>
				<view class="right">
					<view class="name">{{ couponInfo.name }}</view>
					<view class="type-tag">转赠中</view>
					<view class="tips">此券将转出给指定好友</view>
				</view>
			</view>
			<view class="coupon-line"></view>
		</view>

		<!-- 2. 接收人搜索/确认区域 -->
		<view class="form-card">
			<view class="card-title">接收人信息</view>
			
			<!-- 状态 A: 未锁定用户 (显示搜索框) -->
			<block v-if="!targetUser">
				<view class="form-item">
					<input 
						class="input" 
						type="text" 
						v-model="searchKeyword" 
						placeholder="请输入对方用户ID或手机号" 
						placeholder-class="ph-color"
						confirm-type="search"
						@confirm="handleSearch"
					/>
				</view>
				<view class="btn-box">
					<tui-button 
						type="danger" 
						shape="circle" 
						:disabled="!searchKeyword" 
						:loading="searching" 
						@click="handleSearch">
						查找用户
					</tui-button>
				</view>
			</block>

			<!-- 状态 B: 已锁定用户 (显示头像昵称) -->
			<block v-else>
				<view class="target-user-box">
					<view class="user-info">
						<image :src="targetUser.avatar_url" class="avatar"></image>
						<view class="info-right">
							<view class="nickname">{{ targetUser.nickname }}</view>
							<view class="uid">ID: {{ targetUser.id }}</view>
							<view class="mobile" v-if="targetUser.mobile">手机: {{ targetUser.mobile }}</view>
						</view>
					</view>
					<view class="close-btn" @click="resetTarget">
						<tui-icon name="close-fill" color="#ccc" :size="20"></tui-icon>
					</view>
				</view>

				<!-- 确认转赠按钮 -->
				<view class="submit-box">
					<tui-button 
						type="danger" 
						shape="circle" 
						shadow 
						:loading="submitting" 
						@click="handleSubmit">
						确认转赠
					</tui-button>
				</view>
			</block>
		</view>

		<!-- 3. 提示信息 -->
		<view class="tips-box">
			<view class="tips-title">转赠说明：</view>
			<view class="tips-item">1. 转赠后{{ $noun('coupon') }}将立即转移至对方账户，无法撤回。</view>
			<view class="tips-item">2. 请仔细核对接收人的头像和昵称。</view>
			<view class="tips-item">3. 过期或已使用的{{ $noun('coupon') }}无法转赠。</view>
		</view>

	</tui-page>
</template>

<script>
	export default {
		data() {
			return {
				// 页面参数接收
				couponInfo: {
					id: 0,        // user_coupon_id
					name: '',
					sub_price: '0',
					min_price: '0'
				},
				
				searchKeyword: '',
				targetUser: null, // 搜索到的目标用户对象
				
				searching: false,
				submitting: false
			}
		},
		onLoad(options) {
			// 从列表页跳转过来时携带的参数
			// 注意：小程序不会自动解码 URL 参数，H5 会；所以中文名称必须显式 decodeURIComponent
			if (options.id) {
				this.couponInfo = {
					id: options.id,
					name: options.name ? decodeURIComponent(options.name) : this.$noun('coupon'),
					sub_price: options.sub_price || '0',
					min_price: options.min_price || '0'
				};
			} else {
				this.$util.toast('参数错误');
				setTimeout(() => { uni.navigateBack() }, 1000);
			}
		},
		methods: {
			// 1. 搜索用户
			handleSearch() {
				if (!this.searchKeyword) return;
				
				this.searching = true;
				// 调用后端 API: actionSearchTarget
				this.$request({
					url: '/api/coupon/search-target', 
					data: { keyword: this.searchKeyword }
				}).then(res => {
					if (res.code === 0) {
						this.targetUser = res.data.user;
					} else {
						this.$util.toast(res.msg);
					}
				}).finally(() => {
					this.searching = false;
				});
			},

			// 2. 重置搜索 (点击 X 号)
			resetTarget() {
				this.targetUser = null;
				this.searchKeyword = '';
			},

			// 3. 提交转赠
			handleSubmit() {
				if (!this.targetUser) return;

				uni.showModal({
					title: '确认转赠',
					content: `确定将这张“${this.couponInfo.name}”转赠给用户“${this.targetUser.nickname}”吗？`,
					confirmColor: '#E22729',
					success: (res) => {
						if (res.confirm) {
							this.doTransfer();
						}
					}
				});
			},

			goToLogs() {
				uni.navigateTo({ url: '/others/coupon/transfer-log' });
			},

			// 4. 执行 API 请求
			doTransfer() {
				this.submitting = true;
				// 调用后端 API: actionTransfer
				this.$request({
					url: '/api/coupon/transfer',
					method: 'POST',
					data: {
						user_coupon_id: this.couponInfo.id,
						target_user_id: this.targetUser.id
					}
				}).then(res => {
					if (res.code === 0) {
						this.$util.toast('转赠成功', 2000, true);
						// 延迟返回上一页，并触发上一页刷新(如果需要)
						setTimeout(() => {
							uni.navigateBack();
						}, 1500);
					} else {
						this.$util.toast(res.msg);
					}
				}).finally(() => {
					this.submitting = false;
				});
			}
		}
	}
</script>

<style lang="scss" scoped>
	.container {
		padding: 24rpx;
		background-color: #f7f7f7;
		min-height: 100vh;
	}

	/* 记录入口条 */
	.log-entry {
		display: flex;
		align-items: center;
		background: #fff;
		border-radius: 12rpx;
		padding: 22rpx 24rpx;
		margin-bottom: 24rpx;

		.log-entry-text {
			flex: 1;
			font-size: 26rpx;
			color: #333;
			margin-left: 12rpx;
		}
	}

	/* 优惠券票据样式 */
	.coupon-card {
		background-color: #fff;
		border-radius: 16rpx;
		overflow: hidden;
		margin-bottom: 30rpx;
		box-shadow: 0 4rpx 16rpx rgba(0,0,0,0.05);
		position: relative;

		.coupon-content {
			display: flex;
			padding: 40rpx 30rpx;
			background: linear-gradient(135deg, #fff5f5 0%, #fff 100%);

			.left {
				width: 180rpx;
				text-align: center;
				border-right: 2rpx dashed #ffcccc;
				margin-right: 24rpx;
				display: flex;
				flex-direction: column;
				justify-content: center;

				.price-box {
					color: #E22729;
					.symbol { font-size: 28rpx; }
					.price { font-size: 56rpx; font-weight: bold; }
				}
				.condition {
					font-size: 22rpx;
					color: #999;
					margin-top: 8rpx;
				}
			}

			.right {
				flex: 1;
				display: flex;
				flex-direction: column;
				justify-content: center;
				.name {
					font-size: 30rpx;
					font-weight: bold;
					color: #333;
					margin-bottom: 12rpx;
				}
				.type-tag {
					display: inline-block;
					background-color: #ffeaea;
					color: #E22729;
					font-size: 20rpx;
					padding: 4rpx 12rpx;
					border-radius: 6rpx;
					width: fit-content;
					margin-bottom: 12rpx;
				}
				.tips {
					font-size: 24rpx;
					color: #999;
				}
			}
		}
		
		/* 顶部装饰条 */
		&::before {
			content: '';
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			height: 6rpx;
			background: repeating-linear-gradient(45deg, #E22729, #E22729 20rpx, #fff 20rpx, #fff 40rpx);
		}
	}

	/* 表单卡片 */
	.form-card {
		background-color: #fff;
		border-radius: 16rpx;
		padding: 30rpx;
		margin-bottom: 40rpx;

		.card-title {
			font-size: 28rpx;
			font-weight: bold;
			color: #333;
			margin-bottom: 30rpx;
			border-left: 6rpx solid #E22729;
			padding-left: 16rpx;
			line-height: 1;
		}

		.form-item {
			margin-bottom: 30rpx;
			.input {
				background-color: #f8f8f8;
				height: 88rpx;
				border-radius: 44rpx;
				padding: 0 30rpx;
				font-size: 28rpx;
				color: #333;
			}
			.ph-color { color: #ccc; }
		}

		/* 搜索到的用户信息 */
		.target-user-box {
			background-color: #fcfcfc;
			border: 2rpx solid #f0f0f0;
			border-radius: 16rpx;
			padding: 24rpx;
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-bottom: 40rpx;

			.user-info {
				display: flex;
				align-items: center;
				.avatar {
					width: 90rpx;
					height: 90rpx;
					border-radius: 50%;
					margin-right: 24rpx;
					background-color: #eee;
				}
				.info-right {
					.nickname { font-size: 30rpx; font-weight: bold; color: #333; }
					.uid { font-size: 24rpx; color: #999; margin-top: 4rpx; }
					.mobile { font-size: 24rpx; color: #999; margin-top: 2rpx; }
				}
			}
			.close-btn {
				padding: 20rpx;
			}
		}

		.submit-box {
			margin-top: 20rpx;
		}
	}

	/* 温馨提示 */
	.tips-box {
		padding: 0 10rpx;
		.tips-title {
			font-size: 26rpx;
			color: #666;
			margin-bottom: 12rpx;
		}
		.tips-item {
			font-size: 24rpx;
			color: #999;
			line-height: 1.6;
			margin-bottom: 8rpx;
		}
	}
</style>