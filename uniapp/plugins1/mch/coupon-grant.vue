<template>
	<tui-page>
		<view class="container">
			<view class="form-card">
				<!-- 发放张数 -->
				<view class="form-group">
					<view class="form-label">发放张数</view>

					<!-- [核心UI修改] 使用自定义的 stepper 替代 tui-numberbox -->
					<view class="stepper-input-wrapper" style="margin-top: 30rpx;">
						<input class="input" type="number" v-model.number="form.num" placeholder="请输入发给每人的张数"
							placeholder-style="color:#C4C4C4;" />
						<view class="stepper-buttons">
							<view class="stepper-btn" @click="changeNum(-1)">-</view>
							<view class="stepper-btn" @click="changeNum(1)">+</view>
						</view>
					</view>
				</view>

				<!-- 发放对象 -->
				<view class="form-group" style="margin-top: 30rpx;">
					<view class="form-label">发放对象</view>
					<view class="textarea-wrapper" style="margin-top: 30rpx;">
						<textarea class="textarea-input" v-model="form.userIds"
							placeholder="请输入发放对象的会员ID, 多个请用英文状态的逗号隔开, 如1,2,3"
							placeholder-style="color:#C4C4C4; font-size: 28rpx;"></textarea>
					</view>
				</view>
			</view>
		</view>
		<!-- 底部确认按钮 -->
		<view class="footer-bar">
			<tui-button type="dark-blue" height="80rpx" :loading="loading" @click="submit">
				确认发放
			</tui-button>
		</view>
	</tui-page>
</template>

<script>
	export default {
		data() {
			return {
				couponId: null,
				form: {
					num: 1,
					userIds: ''
				},
				loading: false
			};
		},
		onLoad(options) {
			if (!options.id) {
				this.$tui.toast('缺少优惠券信息');
				setTimeout(() => uni.navigateBack(), 800);
				return;
			}
			this.couponId = options.id;
		},
		methods: {
			changeNum(step) {
				let currentNum = Number(this.form.num) || 0;
				let newNum = currentNum + step;
				if (newNum < 1) {
					newNum = 1;
				}
				this.form.num = newNum;
			},
			async submit() {
				if (this.form.num <= 0) {
					return this.$tui.toast('发放张数必须大于0');
				}
				if (!this.form.userIds.trim()) {
					return this.$tui.toast('请输入发放对象会员ID');
				}

				this.loading = true;
				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/coupon/send',
						method: 'POST',
						data: {
							coupon_id: this.couponId,
							num_per_user: this.form.num,
							user_ids_str: this.form.userIds
						}
					});

					if (res.code === 0) {
						uni.showToast({
							title: '发放成功',
							icon: 'success'
						});
						uni.$emit('couponListRefresh'); // 通知列表页刷新
						setTimeout(() => uni.navigateBack(), 800);
					} else {
						this.$showModal(res.msg);
					}
				} finally {
					this.loading = false;
				}
			}
		}
	}
</script>

<style lang="scss" scoped>
	// [核心] 使用您提供的颜色规范
	$page-bg: #F5F5F5;
	$card-bg: #FFFFFF;
	$primary-color: var(--theme-color);
	$text-main: #000000;
	$text-placeholder: #C4C4C4; // 这是一个近似色

	page {
		background-color: $page-bg;
	}

	.container {
		padding: 24rpx;
	}

	.form-card {
		background-color: $card-bg;
		border-radius: 16rpx;
		padding: 30rpx;
	}

	/* [核心UI修改] 新增 stepper 输入框样式 */
	.stepper-input-wrapper {
		display: flex;
		align-items: center;
		border: 1rpx solid #E5E7EB; // 设计图中的边框颜色
		border-radius: 12rpx;
		height: 88rpx;
		overflow: hidden; // 确保子元素圆角正确显示

		.input {
			flex: 1;
			height: 100%;
			padding: 0 24rpx;
			font-size: 28rpx;
		}

		.stepper-buttons {
			display: flex;
			height: 100%;
			flex-shrink: 0;

			.stepper-btn {
				width: 88rpx;
				height: 100%;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 40rpx;
				color: #6B7280; // 设计图中的 +/- 号颜色
				background-color: #FFFFFF;

				&:first-child {
					border-right: 1rpx solid #E5E7EB;
				}
			}
		}
	}

	.textarea-wrapper {
		background-color: #F5F5F5; // 设计图中的文本域背景色
		border: 1rpx solid #E5E7EB; // 设计图中的边框颜色
		border-radius: 12rpx;
		padding: 20rpx;
	}

	.textarea-input {
		background-color: transparent; // 确保 textarea 背景透明
		width: 100%;
		height: 200rpx;
		font-size: 28rpx;
		line-height: 1.6;
	}

	.footer-bar {
		position: fixed;
		bottom: 0;
		left: 0;
		right: 0;
		background: #fff;
		padding: 24rpx;
		padding-bottom: calc(24rpx + env(safe-area-inset-bottom));
		box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
	}
</style>