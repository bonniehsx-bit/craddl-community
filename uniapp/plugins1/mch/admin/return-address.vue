<template>
	<tui-page title="退货地址管理" backgroundColor="#F5F6FA">
		<view class="page-container" v-if="!loading">
			<view class="form-card">
				<view class="form-group">
					<view class="label required">退货联系人</view>
					<view class="field-wrapper">
						<input class="input" v-model="form.return_name" placeholder="请填写收货人姓名"
							placeholder-class="placeholder" />
					</view>
				</view>

				<view class="form-group">
					<view class="label required">联系电话</view>
					<view class="field-wrapper">
						<input class="input" type="number" v-model="form.return_mobile" placeholder="请填写手机号码"
							placeholder-class="placeholder" />
					</view>
				</view>

				<view class="form-group no-border">
					<view class="label required">详细地址</view>
					<view class="field-wrapper textarea-wrapper">
						<textarea class="textarea-input" v-model="form.return_address" placeholder="请填写详细的退货地址"
							placeholder-class="placeholder" style="height: 150rpx;" />
					</view>
				</view>
			</view>

			<view class="submit-btn-wrapper">
				<button class="submit-btn" @click="submit">保存设置</button>
			</view>
		</view>

		<tui-loading v-if="loading" text="加载中..." :is-fixed="true"></tui-loading>
	</tui-page>
</template>

<script>
	export default {
		data() {
			return {
				loading: true,
				form: {
					return_name: '',
					return_mobile: '',
					return_address: ''
				}
			}
		},
		onLoad() {
			this.loadReturnAddress();
		},
		methods: {
			async loadReturnAddress() {
				this.loading = true;
				try {
					const res = await this.$request({
						// [核心] 调用我们新创建的 GET 接口
						url: '/plugin/mch/admin/setting/return-address',
						method: 'GET'
					});
					if (res.code === 0) {
						this.form = res.data;
					} else {
						this.$util.toast(res.msg);
					}
				} catch (e) {
					console.error("加载退货地址失败", e);
				} finally {
					this.loading = false;
				}
			},

			validate() {
				if (!this.form.return_name) {
					this.$util.toast('请填写退货联系人');
					return false;
				}
				if (!/^1[3-9]\d{9}$/.test(this.form.return_mobile)) {
					this.$util.toast('请填写正确的手机号码');
					return false;
				}
				if (!this.form.return_address) {
					this.$util.toast('请填写详细地址');
					return false;
				}
				return true;
			},

			async submit() {
				if (!this.validate()) return;

				uni.showLoading({
					title: '正在保存...',
					mask: true
				});

				try {
					const res = await this.$request({
						// [核心] 调用我们新创建的 POST 接口
						url: '/plugin/mch/admin/setting/return-address',
						method: 'POST',
						data: this.form
					});

					uni.hideLoading();
					this.$util.toast(res.msg);

					if (res.code === 0) {
						setTimeout(() => uni.navigateBack(), 1000);
					}

				} catch (e) {
					uni.hideLoading();
					console.error("保存退货地址失败", e);
				}
			}
		}
	}
</script>

<style lang="scss" scoped>
	/* 样式可以复用商家入驻页面的样式，保持风格统一 */
	.page-container {
		padding: 24rpx;
	}

	.form-card {
		background-color: #fff;
		border-radius: 16rpx;
		margin-bottom: 24rpx;
		padding: 0 32rpx;
	}

	.form-group {
		padding: 32rpx 0;
		border-bottom: 1rpx solid #EBEDF0;
	}

	.form-group.no-border {
		border-bottom: none;
	}

	.label {
		font-size: 28rpx;
		color: #333;
		margin-bottom: 20rpx;
		font-weight: 500;
	}

	.label.required::before {
		content: '*';
		color: #E43D3D;
		margin-right: 4rpx;
	}
	
	.field-wrapper {
		display: flex;
		align-items: center;
		border: 1rpx solid #EBEDF0;
		border-radius: 8rpx;
		padding: 0 24rpx;
		height: 88rpx;
		box-sizing: border-box;
	}

	.input {
		flex: 1;
		font-size: 28rpx;
		height: 100%;
	}

	.placeholder {
		color: #C0C4CC;
	}
	
	.textarea-wrapper {
		height: auto;
		padding: 20rpx 24rpx;
	}
	
	.textarea-input {
		width: 100%;
		font-size: 28rpx;
		line-height: 1.5;
	}

	.submit-btn-wrapper {
		padding: 40rpx 0;
	}

	.submit-btn {
		background-color: var(--theme-color);
		color: #fff;
		height: 96rpx;
		line-height: 96rpx;
		border-radius: 48rpx;
		font-size: 32rpx;
	}
</style>