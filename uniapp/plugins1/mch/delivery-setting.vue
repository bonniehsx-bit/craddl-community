<template>
	<tui-page title="配送方式设置" v-if="!loading">
		<view class="form-container" :style="themeStyle">
			<!-- 配送方式选择 -->
			<view class="form-group">
				<view class="group-title">选择开启的配送方式</view>
				<view class="custom-checkbox-group">
					<!-- [核心修改] v-for 循环现在使用计算属性 availableOptions -->
					<view class="checkbox-item" :class="{ active: delivery_options.includes(item.value) }"
						v-for="item in availableOptions" :key="item.value"
						@click="toggleCheckbox(item.value)">
						<view class="cell-label">{{ item.name }}</view>
						<view class="checkbox-icon-wrapper">
							<uni-icons :type="delivery_options.includes(item.value) ? 'checkbox-filled' : 'circle'"
								:color="delivery_options.includes(item.value) ? themeColor : '#d1d1d1'" size="24">
							</uni-icons>
						</view>
					</view>
					<view v-if="availableOptions.length === 0" class="no-options-tip">
						当前商家类型无需配置配送方式
					</view>
				</view>
			</view>

			<!-- 上门自提设置 (条件渲染) -->
			<view class="form-group" v-if="delivery_options.includes('pickup')">
				<view class="group-title">上门自提设置</view>
				<view class="setting-card">
					<tui-list-cell :hover="false" :arrow="true">
						<view class="flex-x-between">
							<view class="cell-label">自提优惠折扣 (%)</view>
							<input class="input" type="digit" v-model="pickup_discount_rate" placeholder="例如: 5" />
						</view>
					</tui-list-cell>
					<tui-list-cell :hover="false" :arrow="true">
						<view class="flex-x-between">
							<view class="cell-label">最高折扣金额 (元)</view>
							<input class="input" type="digit" v-model="pickup_discount_max_amount"
								placeholder="例如: 5" />
						</view>
					</tui-list-cell>
				</view>
			</view>

			<!-- 同城配送设置 (条件渲染) -->
			<view class="form-group" v-if="delivery_options.includes('city')">
				<view class="group-title">同城配送设置</view>
				<view class="setting-card">
					<tui-list-cell :hover="false" :arrow="true">
						<view class="flex-x-between">
							<view class="cell-label">最大配送半径 (公里)</view>
							<input class="input" type="digit" v-model="city_config.delivery_radius" />
						</view>
					</tui-list-cell>
					<tui-list-cell :hover="false" :arrow="true">
						<view class="flex-x-between">
							<view class="cell-label">最低消费金额 (元)</view>
							<input class="input" type="digit" v-model="city_config.order_amount_limit.min_order_amount"
								placeholder="0为不限制" />
						</view>
					</tui-list-cell>
					<tui-list-cell :hover="false" :arrow="true">
						<view class="flex-x-between">
							<view class="cell-label">起步距离 (公里)</view>
							<input class="input" type="digit" v-model="city_config.charge_rules.start_distance" />
						</view>
					</tui-list-cell>
					<tui-list-cell :hover="false" :arrow="true">
						<view class="flex-x-between">
							<view class="cell-label">起步价 (元)</view>
							<input class="input" type="digit" v-model="city_config.charge_rules.start_fee" />
						</view>
					</tui-list-cell>
					<tui-list-cell :hover="false" :arrow="true" :unlined="true">
						<view class="flex-x-between">
							<view class="cell-label">超出后每公里加价 (元)</view>
							<input class="input" type="digit" v-model="city_config.charge_rules.extra_fee_per_km" />
						</view>
					</tui-list-cell>
				</view>
			</view>

			<!-- 原来写的是 type="dark-blue",而 tui-button 只有 blue/primary/danger/black/… 这些样式类,
				 拼出来的 .tui-btn-dark-blue 是个不存在的空类 → 按钮没有底色,白底白字等于看不见。
				 改成走 background 传主题色(组件里 background 一传就自动接管底色/字色/边框)。 -->
			<view class="save-btn-wrapper">
				<tui-button shape="circle" :background="themeColor" @click="handleSave">保 存</tui-button>
			</view>
		</view>

	</tui-page>
</template>

<script>
	export default {
		data() {
			return {
				loading: true,
				mch_type: null, // [核心新增] 用于存储商家类型: 1=供应商, 2=门店
				delivery_options: [],
				city_config: null,
				pickup_discount_rate: 0,
				pickup_discount_max_amount: '',
			}
		},
		// [核心修改] 新增计算属性
		computed: {
			availableOptions() {
				if (this.mch_type === 1) { // 供应商
					return [{
						name: '快递发货',
						value: 'express'
					}];
				}
				if (this.mch_type === 2) { // 门店
					return [{
							name: '快递发货',
							value: 'express'
						},
						{
							name: '上门自提',
							value: 'pickup'
						},
						{
							name: '同城配送',
							value: 'city'
						}
					];
				}
				return []; // 如果类型未知，则不显示任何选项
			}
		},
		onLoad() {
			this.getConfig();
		},
		methods: {
			async getConfig() {
				this.loading = true;
				try {
					const res = await this.$request({
						url: "/plugin/mch/admin/mch/delivery-config"
					});
					if (res.code === 0) {
						// [核心修改] 存储商家类型
						this.mch_type = res.data.mch_type;

						this.delivery_options = res.data.delivery_options;
						this.city_config = res.data.city_config;
						this.pickup_discount_rate = res.data.pickup_discount_rate;
						this.pickup_discount_max_amount = res.data.pickup_discount_max_amount;
						this.loading = false;
					} else {
						this.$util.toast(res.msg);
						this.loading = false;
					}
				} catch (e) {
					this.loading = false;
				}
			},
			toggleCheckbox(value) {
				// ... 此方法无需修改
				const index = this.delivery_options.indexOf(value);
				if (index > -1) {
					this.delivery_options.splice(index, 1);
				} else {
					this.delivery_options.push(value);
				}
			},
			async handleSave() {
				// ... 此方法无需修改，它会提交当前勾选的 delivery_options
				uni.showLoading({
					title: '保存中...'
				});
				try {
					const res = await this.$request({
						url: "/plugin/mch/admin/mch/delivery-config",
						method: "POST",
						data: {
							delivery_options: this.delivery_options,
							city_config: this.city_config,
							pickup_discount_rate: this.pickup_discount_rate,
							pickup_discount_max_amount: this.pickup_discount_max_amount
						}
					});
					this.$util.toast(res.msg);
					if (res.code === 0) {
						setTimeout(() => uni.navigateBack(), 1000);
					}
				} finally {
					uni.hideLoading();
				}
			}
		}
	}
</script>

<style lang="scss" scoped>
	.form-container {
		padding: 24rpx 24rpx 60rpx;
		background: #f5f6f8;
		min-height: 100vh;
		box-sizing: border-box;
	}

	.form-group {
		margin-bottom: 32rpx;
	}

	// 标题前加一条主题色竖杠,分组边界比纯灰字清楚
	.group-title {
		display: flex;
		align-items: center;
		font-size: 28rpx;
		color: #666;
		font-weight: 600;
		padding: 0 4rpx 18rpx;

		&::before {
			content: '';
			width: 6rpx;
			height: 26rpx;
			border-radius: 3rpx;
			background: var(--theme-color);
			margin-right: 12rpx;
		}
	}

	.custom-checkbox-group {
		background-color: #fff;
		border-radius: 16rpx;
		overflow: hidden;
		box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, .04);
	}

	.checkbox-item {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 28rpx 30rpx;
		border-bottom: 1rpx solid #f0f2f5;
		transition: background-color .2s;

		&:last-child {
			border-bottom: none;
		}

		// 选中的配送方式整行浅色高亮 —— 只看图标太小,一眼扫不出开了哪几种
		&.active {
			background: var(--theme-color-bg);

			.cell-label {
				color: var(--theme-color);
				font-weight: 600;
			}
		}
	}

	.no-options-tip {
		padding: 40rpx 30rpx;
		font-size: 28rpx;
		color: #999;
		text-align: center;
	}

	.cell-label {
		font-size: 30rpx;
		color: #333;
		min-width: 300rpx;
	}

	.setting-card {
		background-color: #fff;
		border-radius: 16rpx;
		overflow: hidden;
		box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, .04);
	}

	.input {
		flex: 1;
		text-align: right;
		font-size: 30rpx;
		color: #333;
		padding: 0 20rpx 0 0;
	}

	.save-btn-wrapper {
		padding: 50rpx 20rpx calc(40rpx + env(safe-area-inset-bottom));
	}

	.picker-display {
		font-size: 30rpx;
		color: #333;
		/* 修改颜色使其更通用 */
		text-align: right;
		padding-right: 30rpx;
	}
</style>