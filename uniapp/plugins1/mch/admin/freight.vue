<template>
	<view class="page-container" :style="themeStyle">
		<view class="freight-form">

			<view class="card-group">
				<view class="form-row"><text class="form-label">模板名称</text><view class="form-control">
					<input v-model="form.name" placeholder="请输入模板名称" class="freight-input" />
				</view></view>

				<view class="form-row"><text class="form-label">是否启用</text><view class="form-control">
					<view style="flex: 1; text-align: right;">
						<switch :checked="form.enabled === 1" @change="(e) => form.enabled = e.detail.value ? 1 : 0" color="var(--theme-color)" />
					</view>
				</view></view>

				<view class="form-row"><text class="form-label">计费方式</text><view class="form-control">
					<radio-group @change="(e) => { form.price_type = Number(e.detail.value); onPriceTypeChange(); }">
						<label v-for="(item, index) in priceTypeList" :key="index" style="margin-right:20rpx;display:inline-flex;align-items:center;">
							<radio :value="item.value" :checked="form.price_type === item.value" color="var(--theme-color)" />
							<text style="font-size:30rpx;">{{ item.name }}</text>
						</label>
					</radio-group>
				</view></view>
			</view>

			<view class="card-group">
				<view class="form-row"><text class="form-label">{{ priceTypeLabels.first_num }}</text><view class="form-control">
					<input v-model="form.first_num" type="number" placeholder="请输入数量" class="freight-input" />
				</view></view>

				<view class="form-row"><text class="form-label">{{ priceTypeLabels.first_price }}</text><view class="form-control">
					<input v-model="form.first_price" type="digit" placeholder="请输入金额(元)" class="freight-input" />
				</view></view>

				<view class="form-row"><text class="form-label">{{ priceTypeLabels.other_num }}</text><view class="form-control">
					<input v-model="form.other_num" type="number" placeholder="请输入数量" class="freight-input" />
				</view></view>

				<view class="form-row"><text class="form-label">{{ priceTypeLabels.other_price }}</text><view class="form-control">
					<input v-model="form.other_price" type="digit" placeholder="请输入金额(元)" class="freight-input" />
				</view></view>
			</view>
		</view>

		<view class="bottom-bar">
			<tui-button :background="themeColor" shape="circle" :loading="isLoading" @click="submit">保 存</tui-button>
		</view>

	</view>
</template>

<script>
	export default {
		data() {
			return {
				isLoading: false,
				form: {
					name: '商家默认模板',
					enabled: 1,
					price_type: 0,
					first_num: 1,
					first_price: 0.00,
					other_num: 1,
					other_price: 0.00,
				},
				priceTypeList: [{
						name: '按件计费',
						value: 0
					},
					{
						name: '按重计费',
						value: 1
					}
				],
				rules: {
					name: [{
						required: true,
						message: '请输入模板名称',
						trigger: ['blur', 'change']
					}],
					first_num: [{
							required: true,
							message: '首件/首重不能为空',
							trigger: ['blur', 'change']
						},
						{
							type: 'number',
							min: 1,
							message: '首件/首重必须大于0',
							trigger: ['blur', 'change']
						}
					],
					first_price: [{
							required: true,
							message: '首件/首重运费不能为空',
							trigger: ['blur', 'change']
						},
						{
							type: 'number',
							min: 0,
							message: '运费不能为负数',
							trigger: ['blur', 'change']
						}
					],
					other_num: [{
							required: true,
							message: '续件/续重不能为空',
							trigger: ['blur', 'change']
						},
						{
							type: 'number',
							min: 1,
							message: '续件/续重必须大于0',
							trigger: ['blur', 'change']
						}
					],
					other_price: [{
							required: true,
							message: '续件/续重运费不能为空',
							trigger: ['blur', 'change']
						},
						{
							type: 'number',
							min: 0,
							message: '运费不能为负数',
							trigger: ['blur', 'change']
						}
					]
				}
			};
		},
		computed: {
			priceTypeLabels() {
				if (this.form.price_type == 1) { // 按重
					return {
						first_num: '首重(克)',
						first_price: '首重运费(元)',
						other_num: '续重(克)',
						other_price: '续重运费(元)'
					}
				} else { // 按件
					return {
						first_num: '首件(个)',
						first_price: '首件运费(元)',
						other_num: '续件(个)',
						other_price: '续件运费(元)'
					}
				}
			}
		},
		onLoad() {
			this.fetchSetting();
		},
		// onReady setRules removed — uview-ui form replaced with native layout (no client-side validation)
		methods: {
			async fetchSetting() {
				uni.showLoading({
					title: '加载中...'
				});
				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/freight/setting',
						method: 'GET'
					});

					if (res.code === 0) {
						// 将加载到的数据转为正确的类型
						this.form = {
							...res.data,
							enabled: Number(res.data.enabled),
							price_type: Number(res.data.price_type),
							first_num: Number(res.data.first_num),
							first_price: Number(res.data.first_price),
							other_num: Number(res.data.other_num),
							other_price: Number(res.data.other_price),
						};
					} else {
						uni.showToast({ icon: 'none', title: res.msg });
					}
				} catch (e) {
					console.error(e);
				} finally {
					uni.hideLoading();
				}
			},

			onPriceTypeChange() {
				// 触发表单标签更新
				this.$forceUpdate();
			},

			// 移除 uview-ui validate；后端校验由 saveSetting 接口侧把关
			submit() {
				this.saveSetting();
			},

			async saveSetting() {
				this.isLoading = true;
				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/freight/setting',
						method: 'POST',
						data: {
							...this.form,
							// 确保提交的是数字类型
							enabled: Number(this.form.enabled),
							price_type: Number(this.form.price_type),
							first_num: Number(this.form.first_num),
							first_price: Number(this.form.first_price),
							other_num: Number(this.form.other_num),
							other_price: Number(this.form.other_price),
						}
					});

					uni.showToast({
						icon: res.code === 0 ? 'success' : 'none',
						title: res.msg,
					});

				} catch (e) {
					console.error(e);
				} finally {
					this.isLoading = false;
				}
			}
		}
	}
</script>

<style lang="scss">
	page {
		background-color: #f5f6fa;
	}

	.page-container {
		padding: 24rpx;
		padding-bottom: 140rpx; // 为底部按钮留出空间
	}

	.card-group {
		background-color: #ffffff;
		border-radius: 16rpx;
		padding: 0 30rpx;
		margin-bottom: 24rpx;

		.form-row {
			display: flex;
			align-items: center;
			min-height: 96rpx;
			padding: 20rpx 0;
			border-bottom: 1rpx solid #f0f0f0;

			&:last-child {
				border-bottom: none;
			}
		}
	}

	.form-label {
		width: 240rpx;
		flex-shrink: 0;
		white-space: nowrap;
		font-size: 30rpx;
		color: #333;
	}

	.form-control {
		flex: 1;
		display: flex;
		align-items: center;
		justify-content: flex-end;
		min-width: 0;
	}

	.freight-input {
		flex: 1;
		min-width: 0;
		text-align: right;
		font-size: 30rpx;
		color: #333;
	}

	.bottom-bar {
		position: fixed;
		bottom: 0;
		left: 0;
		right: 0;
		background-color: #ffffff;
		padding: 24rpx;
		box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.05);
		z-index: 10;
	}
</style>