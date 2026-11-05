<template>
	<view class="page-container" :style="themeStyle">
	 

		<!-- 2. 表单区域 -->
		<scroll-view scroll-y class="form-scroll-view">
			<view class="form-content">
				<!-- 优惠券基础信息 -->
				<view class="form-section">
					<view class="section-title">基础信息</view>
					<view class="form-row">
						<text class="label required">优惠券名称</text>
						<input class="input" v-model="form.name" placeholder="请输入优惠券名称"
							placeholder-class="placeholder" />
					</view>
					<view class="form-row no-border">
						<text class="label">是否开启</text>
						<switch :checked="form.is_enabled == 1" @change="onSwitchChange('is_enabled', $event)"
							color="var(--theme-color)" />
					</view>
				</view>

				<!-- 使用条件 -->
				<view class="form-section">
					<view class="section-title">使用条件</view>
					<view class="form-row">
						<text class="label">订单金额满</text>
						<view class="input-with-unit">
							<input class="input txt-red" v-model="form.min_price" type="digit" placeholder="0.00"
								placeholder-class="placeholder" />
							<text class="unit">元</text>
						</view>
					</view>
					<view class="form-row no-border">
						<text class="label required">有效期</text>
						<view class="input-with-unit">
							<input class="input" v-model="form.day" type="number" placeholder="例如: 30"
								placeholder-class="placeholder" />
							<text class="unit">天 (领取后)</text>
						</view>
					</view>
				</view>

				<!-- 使用设置 -->
				<view class="form-section">
					<view class="section-title">使用设置</view>
					<view class="form-row">
						<text class="label required">立减金额</text>
						<view class="input-with-prefix">
							<text class="prefix txt-red">¥</text>
							<input class="input txt-red" v-model="form.sub_price" type="digit" placeholder="请输入优惠金额"
								placeholder-class="placeholder" />
						</view>
					</view>
					<picker :range="scopeOptions" @change="onScopeChange">
						<view class="form-row no-border">
							<text class="label">使用范围</text>
							<view class="picker-display">
								<text>{{ scopeText }}</text>
								<tui-icon name="arrowright" color="#c0c4cc" :size="16"></tui-icon>
							</view>
						</view>
					</picker>
				</view>

				<!-- 指定商品 (条件显示) -->
				<view class="form-section product-section" v-if="form.appoint_type === 'goods'">
					<view class="product-selection-header">
						<text class="title">已选择商品 ({{ (form.appoint_ids || []).length }})</text>
						<view class="add-btn" @click="openGoodsPicker">
							<tui-icon name="plus" color="var(--theme-color)" :size="14"></tui-icon>
							<text>添加</text>
						</view>
					</view>
					<view class="product-list" v-if="(form.appoint_ids || []).length > 0">
						<view class="selected-product-item" v-for="(product, index) in form.appoint_ids"
							:key="product.id">
							<image class="product-image" :src="product.cover_pic" mode="aspectFill"></image>
							<text class="product-name">{{ product.name }}</text>
							<view class="delete-btn" @click="removeProduct(index)">
								<tui-icon name="close" color="#ccc" :size="16"></tui-icon>
							</view>
						</view>
					</view>
					<view v-else class="product-placeholder" @click="openGoodsPicker">
						<tui-icon name="plus" color="#c0c4cc" :size="28"></tui-icon>
						<text class="placeholder-text">暂未选择商品, 点击添加</text>
					</view>
				</view>

				<!-- 领取发放 -->
				<view class="form-section">
					<view class="section-title">领取发放</view>
					<view class="form-row">
						<text class="label">用户可主动领取</text>
						<switch :checked="form.can_be_claimed == 1" @change="onSwitchChange('can_be_claimed', $event)"
							color="var(--theme-color)" />
					</view>
					<view class="form-row">
						<text class="label">每人限领</text>
						<view class="input-with-unit">
							<input class="input" v-model="form.limit_per_user" type="number" placeholder="0为不限制"
								placeholder-class="placeholder" />
							<text class="unit">张</text>
						</view>
					</view>
					<view class="form-row no-border">
						<text class="label">发放总数</text>
						<view class="input-with-unit">
							<input class="input" v-model="form.total_count" type="number" placeholder="-1为不限制"
								placeholder-class="placeholder" />
							<text class="unit">张</text>
						</view>
					</view>
				</view>
			</view>
		</scroll-view>

		<!-- 3. 底部确认按钮 -->
		<view class="footer-button">
			<tui-button type="dark-blue" shape="circle" @click="submitForm" :loading="loading">确认发布</tui-button>
		</view>

		<!-- 4. 组件 -->
		<goods-picker ref="goodsPicker" :initialSelection="form.appoint_ids" @confirm="onGoodsConfirm"></goods-picker>
	</view>
</template>

<script>
	import goodsPicker from './components/coupon-goods-picker.vue';

	export default {
		components: {
			goodsPicker
		},
		data() {
			return {
				loading: false,
				form: {
					id: null,
					name: '',
					is_enabled: 1,
					min_price: '',
					day: '',
					sub_price: '',
					appoint_type: 'all',
					appoint_ids: [],
					can_be_claimed: 1,
					limit_per_user: 0,
					total_count: -1,
				},
				scopeOptions: ['全店通用', '指定商品'],
			};
		},
		computed: {
			scopeText() {
				return this.form.appoint_type === 'all' ? '全店通用' : '指定商品';
			}
		},
		onLoad(options) {
			if (options.id) {
				this.form.id = options.id;
				this.getCouponDetail();
			}
		},
		methods: {
			goBack() {
				uni.navigateBack();
			},
			async getCouponDetail() {
				uni.showLoading({
					title: '加载中...'
				});
				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/coupon/detail',
						data: {
							id: this.form.id
						}
					});
					if (res.code === 0) {
						this.form = Object.assign(this.form, res.data.detail);
					} else {
						this.$util.toast(res.msg);
					}
				} finally {
					uni.hideLoading();
				}
			},
			onSwitchChange(key, event) {
				this.form[key] = event.detail.value ? 1 : 0;
			},
			onScopeChange(event) {
				const selectedIndex = event.detail.value;
				this.form.appoint_type = selectedIndex == 0 ? 'all' : 'goods';
			},
			openGoodsPicker() {
				this.$refs.goodsPicker.open();
			},
			onGoodsConfirm(selected) {
				this.form.appoint_ids = selected;
			},
			removeProduct(index) {
				this.form.appoint_ids.splice(index, 1);
			},
			async submitForm() {
				if (!this.form.name.trim()) return this.$util.toast('请输入优惠券名称');
				if (!this.form.sub_price || Number(this.form.sub_price) <= 0) return this.$util.toast('立减金额必须大于0');
				if (!this.form.day || Number(this.form.day) <= 0) return this.$util.toast('有效期天数必须大于0');

				this.loading = true;
				try {
					const params = {
						...this.form,
						appoint_ids: this.form.appoint_ids.map(item => item.id)
					};
					const res = await this.$request({
						url: '/plugin/mch/admin/coupon/edit',
						method: 'POST',
						data: params
					});
					if (res.code === 0) {
						uni.showToast({
							title: '保存成功',
							icon: 'success'
						});
						uni.$emit('couponSaved');
						setTimeout(() => uni.navigateBack(), 800);
					} else {
						this.$util.toast(res.msg);
					}
				} finally {
					this.loading = false;
				}
			}
		}
	}
</script>

<style lang="scss" scoped>
	.page-container {
		display: flex;
		flex-direction: column;
		height: 100vh;
		background-color: #f8f8f8;
	}

	.form-scroll-view {
		flex: 1;
		height: 0;
	}

	.form-content {
		padding: 24rpx;
		padding-bottom: calc(120rpx + 24rpx + env(safe-area-inset-bottom));
	}

	.form-section {
		background-color: #ffffff;
		border-radius: 16rpx;
		margin-bottom: 24rpx;
		padding: 0 32rpx;
	}

	.product-section {
		padding: 32rpx;
	}

	.section-title {
		font-size: 32rpx;
		font-weight: bold;
		color: #333;
		padding: 32rpx 0;
		border-bottom: 1rpx solid #f0f0f0;
	}

	.form-row {
		display: flex;
		justify-content: space-between;
		align-items: center;
		min-height: 100rpx;
		border-bottom: 1rpx solid #f0f0f0;

		&.no-border {
			border-bottom: none;
		}

		.label {
			font-size: 30rpx;
			color: #333;
			flex-shrink: 0;
			margin-right: 40rpx;

			&.required::before {
				content: '*';
				color: #fa3534;
				margin-right: 4rpx;
			}
		}

		.input {
			flex: 1;
			text-align: right;
			font-size: 30rpx;
			color: #333; // Add default input color
		}

		.txt-red {
			color: var(--theme-color) !important;
			font-weight: bold;
		}

		.placeholder {
			color: #c0c4cc;
			font-weight: normal;
		}
	}

	.input-with-unit,
	.input-with-prefix {
		display: flex;
		align-items: center;
		flex: 1;
		justify-content: flex-end;

		.unit,
		.prefix {
			font-size: 30rpx;
			color: #333;
		}

		.unit {
			margin-left: 16rpx;
		}

		.prefix {
			margin-right: 10rpx;
		}
	}

	.picker-display {
		display: flex;
		align-items: center;
		gap: 10rpx;
		font-size: 30rpx;
		color: #333;
	}

	.product-selection-header {
		display: flex;
		justify-content: space-between;
		align-items: center;

		.title {
			font-size: 30rpx;
			color: #333;
		}

		.add-btn {
			display: inline-flex;
			align-items: center;
			padding: 8rpx 16rpx;
			border-radius: 30rpx;
			border: 1rpx solid var(--theme-color);
			color: var(--theme-color);
			font-size: 24rpx;

			text {
				margin-left: 8rpx;
			}
		}
	}

	.product-list {
		margin-top: 20rpx;
	}

	.selected-product-item {
		display: flex;
		align-items: center;
		padding: 16rpx 0;
		border-bottom: 1rpx solid #f0f0f0;

		&:last-child {
			border-bottom: none;
		}

		.product-image {
			width: 80rpx;
			height: 80rpx;
			border-radius: 8rpx;
			margin-right: 20rpx;
			flex-shrink: 0;
		}

		.product-name {
			flex: 1;
			font-size: 28rpx;
			color: #333;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		.delete-btn {
			padding: 10rpx;
			margin-left: 20rpx;
			background: #f9f9f9;
			border-radius: 50%;
			width: 44rpx;
			height: 44rpx;
			display: flex;
			align-items: center;
			justify-content: center;
			transition: all 0.2s;
			
			&:active {
				background: #eee;
			}
		}
	}

	.product-placeholder {
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
		height: 200rpx;
		border: 1rpx dashed #dcdfe6;
		border-radius: 16rpx;
		margin-top: 20rpx;
		background-color: #fafafa;

		.placeholder-text {
			margin-top: 16rpx;
			font-size: 28rpx;
			color: #c0c4cc;
		}
	}

	.footer-button {
		position: fixed;
		bottom: 0;
		left: 0;
		right: 0;
		background-color: #ffffff;
		padding: 24rpx;
		padding-bottom: calc(24rpx + constant(safe-area-inset-bottom));
		padding-bottom: calc(24rpx + env(safe-area-inset-bottom));
		box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.05);
	}
</style>