<template>
	<tui-page title="编辑店铺信息" backgroundColor="#F5F6FA">
		<view class="page-container" v-if="!loading" :style="themeStyle">

			<!-- 首单优惠信息卡片 (此部分仅为示例，根据实际业务保留或移除) -->
			<view class="info-card" v-if="form.is_first_order_discount_enabled == 1">
				<view class="info-card-header">
					<tui-icon name="gift" color="#007AFF" size="20"></tui-icon>
					<text class="info-card-title">首单优惠信息</text>
				</view>
				<view class="info-card-body">
                    <view class="info-item">
                        <text class="info-label">剩余优惠单数数</text>
                        <text class="info-value">{{ form.first_order_discount_total_stock }} 单</text>
                    </view>
                    <view class="info-item">
                        <text class="info-label">优惠结束时间</text>
                        <text class="info-value">{{ form.first_order_discount_end_time || '长期有效' }}</text>
                    </view>
				</view>
			</view>

			<!-- 基础信息卡片 -->
			<view class="form-card">
				<view class="form-group">
					<view class="label required">商家logo</view>
					<view class="field-content">
						<tui-upload-single-image v-model="form.logo" width="160rpx"
							height="160rpx"></tui-upload-single-image>
					</view>
				</view>

				<view class="form-group">
					<view class="label required">店铺图片 (Banner)</view>
					<view class="field-content">
						<tui-upload v-model="form.banner_images" :limit="5"></tui-upload>
					</view>
				</view>

				<view class="form-group">
					<view class="label required">店铺介绍</view>
					<view class="field-wrapper textarea-wrapper">
						<textarea class="textarea-input" v-model="form.description" placeholder="请填写店铺简介"
							placeholder-class="placeholder" />
					</view>
				</view>

				<!-- 仅本地生活门店 (type=2) 显示营业时间 -->
				<view class="form-group" v-if="form.type === 2">
					<view class="label required">营业时间</view>
					<view class="time-picker-wrapper">
						<picker class="time-picker-item" mode="time" @change="onTimeChange($event, 'start')">
							<view class="time-picker-display">
								<tui-icon name="clock" color="#666" size="18" class="time-icon"></tui-icon>
								{{ form.start_time || '开始时间' }}
							</view>
						</picker>
						<view class="time-separator">~</view>
						<picker class="time-picker-item" mode="time" @change="onTimeChange($event, 'end')">
							<view class="time-picker-display">
								<tui-icon name="clock" color="#666" size="18" class="time-icon"></tui-icon>
								{{ form.end_time || '结束时间' }}
							</view>
						</picker>
					</view>
				</view>

				<!-- 仅本地生活门店 (type=2) 显示详细地址 -->
				<view class="form-group no-border" v-if="form.type === 2">
					<view class="label required">详细地址</view>
					<view class="field-wrapper textarea-wrapper">
						<textarea class="textarea-input" v-model="form.address" placeholder="街道、楼牌号等"
							placeholder-class="placeholder" auto-height />
					</view>
				</view>
			</view>

			<!-- 售后信息卡片 -->
			<view class="form-card">
				<view class="form-group">
					<view class="label required">售后联系人</view>
					<view class="field-wrapper input-wrapper">
						<input class="input" v-model="form.refund_name" placeholder="请填写退货联系人姓名"
							placeholder-class="placeholder" />
					</view>
				</view>
				<view class="form-group">
					<view class="label required">售后联系电话</view>
					<view class="field-wrapper input-wrapper">
						<input class="input" type="number" v-model="form.refund_mobile" placeholder="请填写退货联系电话"
							placeholder-class="placeholder" />
					</view>
				</view>
				<view class="form-group no-border">
					<view class="label required">售后退货地址</view>
					<view class="field-wrapper textarea-wrapper">
						<textarea class="textarea-input" v-model="form.refund_address" placeholder="请填写详细的退货地址"
							placeholder-class="placeholder" />
					</view>
				</view>
			</view>

			<view class="submit-btn-wrapper">
				<button class="submit-btn" @click="submit">保存修改</button>
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
					type: 0, // 初始化商家类型，默认为线下, 会被接口数据覆盖
					// 基础信息
					logo: '',
					banner_images: [],
					description: '',
					opening_hours: '',
					address: '',
					// 售后信息
					refund_name: '',
					refund_mobile: '',
					refund_address: '',
					// 优惠信息 (只读)
					is_first_order_discount_enabled: 0,
					first_order_discount_total_stock: '',
					first_order_discount_end_time: '',
					// 辅助字段
					start_time: '',
					end_time: '',
				},
			}
		},
		onLoad() {
			this.loadCurrentMchData();
		},
		methods: {
			async loadCurrentMchData() {
				this.loading = true;
				try {
					const res = await this.$request({
						// 注意：请确保此接口会返回 type 字段
						url: '/plugin/mch/admin/mch/edit' 
					});
					if (res.code === 0) {
						const detail = res.data.detail;
						Object.assign(this.form, detail);

						// 本地生活门店(type=2)才有营业时间，回显时拆成起止
						if (this.form.type === 2 && this.form.opening_hours && this.form.opening_hours.includes('-')) {
							const [start, end] = this.form.opening_hours.split('-');
							this.form.start_time = start;
							this.form.end_time = end;
						}
					} else {
						this.$util.toast(res.msg);
					}
				} catch (e) {
					console.error("加载商户信息失败", e);
					this.$util.toast('信息加载失败');
				} finally {
					this.loading = false;
				}
			},

			onTimeChange(e, type) {
				if (type === 'start') {
					this.form.start_time = e.detail.value;
				} else {
					this.form.end_time = e.detail.value;
				}
				if (this.form.start_time && this.form.end_time) {
					this.form.opening_hours = `${this.form.start_time}-${this.form.end_time}`;
				}
			},

			validate() {
				// 基础信息校验
				if (!this.form.logo) return this.$util.toast('请上传商家logo');
				if (!this.form.banner_images || this.form.banner_images.length === 0) return this.$util.toast('请上传店铺图片');
				if (!this.form.description) return this.$util.toast('请填写店铺介绍');
				
				// -- 仅本地生活门店(type=2)需要校验地址和营业时间(与后端 MchEditForm required-when 对齐) --
				if (this.form.type === 2) {
					if (!this.form.opening_hours) return this.$util.toast('请选择营业时间');
					if (!this.form.address) return this.$util.toast('请填写详细地址');
				}

				// 售后信息校验 (所有类型商家都需要)
				if (!this.form.refund_name) return this.$util.toast('请填写售后联系人');
				if (!this.form.refund_mobile) return this.$util.toast('请填写售后联系电话');
				if (!/^1[3-9]\d{9}$/.test(this.form.refund_mobile)) return this.$util.toast('请填写正确的售后联系电话');
				if (!this.form.refund_address) return this.$util.toast('请填写售后退货地址');

				return true;
			},

			async submit() {
				if (!this.validate()) return;

				uni.showLoading({
					title: '正在保存...'
				});

				// -- 动态构建提交的数据 --
				const payload = {
					// 基础信息 (通用)
					logo: this.form.logo,
					banner_images: this.form.banner_images,
					description: this.form.description,
					// 售后信息 (通用)
					refund_name: this.form.refund_name,
					refund_mobile: this.form.refund_mobile,
					refund_address: this.form.refund_address,
				};

				// -- 本地生活门店(type=2)才提交营业时间/地址;后端对 type=2 这两项为必填 --
				if (this.form.type === 2) {
					payload.opening_hours = this.form.opening_hours;
					payload.address = this.form.address;
				}

				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/mch/edit',
						method: 'POST',
						data: payload // 使用构建好的 payload
					});

					uni.hideLoading();
					uni.showModal({
						title: '提示',
						content: res.msg,
						showCancel: false,
						success: () => {
							if (res.code === 0) {
								uni.navigateBack();
							}
						}
					});
				} catch (e) {
					uni.hideLoading();
					console.error("保存失败", e);
				}
			}
		}
	}
</script>

<style lang="scss" scoped>
	.page-container {
		padding: 24rpx;
		box-sizing: border-box;
	}

	.info-card {
		background-color: #EBF4FF;
		border-radius: 20rpx;
		margin-bottom: 24rpx;
		padding: 24rpx 32rpx;
		border: 1px solid #D6E8FF;
	}

	.info-card-header {
		display: flex;
		align-items: center;
		margin-bottom: 24rpx;
	}

	.info-card-title {
		font-size: 30rpx;
		font-weight: bold;
		color: #007AFF;
		margin-left: 12rpx;
	}

	.info-card-body {
		font-size: 28rpx;
	}

	.info-item {
		display: flex;
		justify-content: space-between;
		align-items: center;

		&:not(:last-child) {
			margin-bottom: 16rpx;
		}
	}

	.info-label {
		color: #666;
	}

	.info-value {
		color: #333;
		font-weight: 500;
	}
	
	.form-card {
		background-color: #fff;
		border-radius: 20rpx;
		padding: 16rpx 32rpx;
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);
		margin-bottom: 24rpx;
	}

	.form-group {
		padding: 32rpx 0;
		border-bottom: 1rpx solid #F0F0F0;

		&:last-child,
		&.no-border {
			border-bottom: none;
		}
	}

	.label {
		font-size: 30rpx;
		color: #333;
		margin-bottom: 24rpx;
		font-weight: bold;
	}

	.label.required::before {
		content: '*';
		color: #E43D3D;
		margin-right: 6rpx;
		font-weight: normal;
	}

	.field-content {
		padding-left: 4rpx;
	}
	
	.field-wrapper {
		background-color: #F7F7F7;
		border-radius: 12rpx;
		padding: 20rpx 24rpx;
		box-sizing: border-box;
		display: flex;
		align-items: center;
	}
	
	.input-wrapper {
		height: 88rpx;
		padding: 0 24rpx;
	}

	.input {
		width: 100%;
		font-size: 28rpx;
	}
	
	.textarea-wrapper {
		height: auto;
	}

	.textarea-input {
		width: 100%;
		font-size: 28rpx;
		line-height: 1.6;
		min-height: 150rpx;
	}

	.placeholder {
		color: #C0C4CC;
	}

	.time-picker-wrapper {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 16rpx;
	}
	
	.time-separator {
		color: #999;
	}

	.time-picker-item {
		flex: 1;
	}

	.time-picker-display {
		background-color: #F7F7F7;
		border-radius: 12rpx;
		height: 88rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 28rpx;
		color: #333;
	}
	
	.time-icon {
		margin-right: 10rpx;
	}

	.submit-btn-wrapper {
		padding: 40rpx 0;
		margin-top: 0;
	}

	.submit-btn {
		background: linear-gradient(90deg, var(--theme-color), var(--theme-color-light));
		color: #fff;
		height: 96rpx;
		line-height: 96rpx;
		border-radius: 48rpx;
		font-size: 32rpx;
		font-weight: bold;
		box-shadow: 0 8rpx 16rpx var(--theme-shadow);
		
		&::after {
			border: none;
		}
		
		&:active {
			opacity: 0.9;
		}
	}
</style>