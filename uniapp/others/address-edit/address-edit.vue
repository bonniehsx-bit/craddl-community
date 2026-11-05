<template>
	<view class="page-container" :style="{ '--theme-color': themeColor }">
		
		<view class="form-card">
			<view class="form-item">
				<text class="label required">收货人</text>
				<input class="input" v-model="form.name" placeholder="请填写收货人姓名" placeholder-class="placeholder" />
			</view>

			<view class="form-item">
				<text class="label required">手机号码</text>
				<input class="input" v-model="form.mobile" placeholder="请填写收货人手机号" type="number" maxlength="11"
					placeholder-class="placeholder" />
			</view>

			<tui-address-picker @selected="onAddressConfirm">
				<view class="form-item arrow">
					<text class="label required">所在区域</text>
					<view class="input" :class="{'placeholder': !form.area}">{{ form.area || '省、市、区' }}</view>
				</view>
			</tui-address-picker>

			<picker class="picker-view" range-key="name" @change="townChange" :value="townIndex" :range="town_list"
				v-if="town_list.length > 0">
				<view class="form-item" :class="{'arrow': !form.town_name}">
					<text class="label">街道/镇</text>
					<view class="input" :class="{'placeholder': !form.town_name}">
						{{ form.town_name || '请选择街道/镇' }}
					</view>
					
					<view class="close-btn" v-if="form.town_name" @click.stop="clearTown">×</view>
				</view>
			</picker>

			<view class="form-item multi-line" v-if="locationEnabled" @click="chooseLocation">
				<text class="label">地理位置</text>
				<view class="input-group">
					<view class="input" :class="{'placeholder': !form.location}">{{ form.location || '选择地理位置方便配送到家' }}
					</view>
					<view class="hint">如需用于同城配送请务必选择此项</view>
				</view>
			</view>

			<view class="form-item">
				<text class="label required">详细地址</text>
				<input class="input" v-model="form.detail" placeholder="如xx小区xx号房" placeholder-class="placeholder" />
			</view>

			<view class="form-item">
				<text class="label">设为默认地址</text>
				<switch :checked="form.is_default == 1" @change="defaultChange" :color="themeColor"
					style="transform:scale(0.8)" />
			</view>
		</view>

		<view class="footer-bar">
			<button class="save-btn" @click="save" :style="{ backgroundColor: themeColor }">保存地址</button>
		</view>
	</view>
</template>

<script>
	import { chooseLocationWithTip } from "@/utils/permissionTip.js";
	// [核心] 引入 Vuex
	import { mapState } from 'vuex';

	export default {
		data() {
			return {
				form: {
					id: null,
					name: '',
					mobile: '',
					province_code: '',
					city_code: '',
					county_code: '',
					town_code: '',
					province_name: '',
					city_name: '',
					county_name: '',
					town_name: '',
					detail: '',
					is_default: 0,
					area: '',
					location: '',
					latitude: '',
					longitude: ''
				},
				town_list: [],
				townIndex: 0,
				// 从同城配送拦截处被引导来(?force_location=1)时置真,强制显示定位字段(绕过商城开关)
				forceLocation: false
			}
		},
		computed: {
			// [核心] 获取主题色，默认红色
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F',
					// 商城开关:后台「商城设置 → 功能开关 → 地址选择地理位置」
					mallLocationEnabled: state => Number((state.setting && state.setting.address_location_enabled) || 0) === 1
			}),
			// 是否显示「地理位置」字段:商城开关开启,或从同城配送拦截处被引导来(force_location)。
			// 即便商城日常关掉此项,被引导补坐标时也必须能选,避免同城配送死循环。
			locationEnabled() {
				return this.mallLocationEnabled || this.forceLocation;
			}
		},
		onLoad(options) {
			// [核心] 设置导航栏颜色
			this.setNavBarColor();
			
			// 从同城配送拦截「去设置定位」跳进来:强制显示定位字段(不受商城开关限制)
			if (options.force_location == 1) {
				this.forceLocation = true;
			}

			if (options.id) {
				this.form.id = options.id;
				this.getAddress();
			}
			uni.setNavigationBarTitle({
				title: options.id ? '编辑地址' : '新增地址'
			})
		},
		onShow() {
			this.setNavBarColor();
		},
		watch: {
			// [核心] 监听主题色变化
			themeColor(val) {
				if (val) this.setNavBarColor();
			}
		},
		methods: {
			// [核心] 动态设置导航栏背景色
			setNavBarColor() {
				if (this.themeColor) {
					uni.setNavigationBarColor({
						frontColor: '#ffffff',
						backgroundColor: this.themeColor,
						animation: { duration: 300, timingFunc: 'easeIn' }
					});
				}
			},

			chooseWxAddress() {
				uni.chooseAddress({
					success: (res) => {
						this.form.name = res.userName;
						this.form.mobile = res.telNumber;
						this.form.province_name = res.provinceName;
						this.form.city_name = res.cityName;
						this.form.county_name = res.detailInfo.split(' ')[0] || res.countyName;
						this.form.detail = res.detailInfo.split(' ')[1] || '';
						this.form.area = `${res.provinceName} ${res.cityName} ${this.form.county_name}`;
						this.parseAddressCodes(this.form.province_name, this.form.city_name, this.form
							.county_name);
					},
					fail: (e) => {
						console.log(e);
					}
				});
			},
			chooseLocation() {
				chooseLocationWithTip({
					success: (e) => {
						this.form.latitude = e.latitude;
						this.form.longitude = e.longitude;
						this.form.location = e.address;
					}
				})
			},
			async parseAddressCodes(province, city, county) {
				uni.showLoading({
					title: '地址解析中...'
				});
				try {
					const res = await this.$request({
						url: '/common/address/parse',
						data: {
							province,
							city,
							county
						}
					});
					if (res.code === 0) {
						this.form.province_code = res.data.province_code;
						this.form.city_code = res.data.city_code;
						this.form.county_code = res.data.county_code;
						this.getTownList(res.data.county_code);
					}
				} finally {
					uni.hideLoading();
				}
			},
			async getAddress() {
				const res = await this.$request({
					url: '/api/user/address-edit',
					data: {
						id: this.form.id
					}
				});
				if (res.code === 0) {
					this.form = res.data.address;
					this.form.area = `${this.form.province_name} ${this.form.city_name} ${this.form.county_name}`;
					if (this.form.county_code) {
						this.getTownList(this.form.county_code, true);
					}
				}
			},
			onAddressConfirm(e) {
				this.form.province_code = e.province_code;
				this.form.city_code = e.city_code;
				this.form.county_code = e.county_code;
				this.form.province_name = e.province_name;
				this.form.city_name = e.city_name;
				this.form.county_name = e.county_name;
				this.form.area = e.area;
				// 切换区域时，清空街道信息
				this.form.town_code = '';
				this.form.town_name = '';
				this.town_list = [];
				this.getTownList(e.county_code);
			},
			async getTownList(county_code, isEdit = false) {
				const res = await this.$request({
					url: '/common/address/town-list',
					data: {
						county_code
					}
				});
				if (res.code === 0) {
					this.town_list = res.data.list;
					// 仅在编辑模式且有值时回显，新增或切换区域不再默认选中第一个
					if (isEdit && this.form.town_code) {
						const index = this.town_list.findIndex(t => t.code === this.form.town_code);
						this.townIndex = index > -1 ? index : 0;
					} else {
						// 确保非编辑模式下，索引归零，但不赋值给form
						this.townIndex = 0;
					}
				}
			},
			townChange(e) {
				// 只有用户主动选择时才赋值
				this.townIndex = e.detail.value;
				const selectedTown = this.town_list[this.townIndex];
				if (selectedTown) {
					this.form.town_code = selectedTown.code;
					this.form.town_name = selectedTown.name;
				}
			},
			// [新增] 清除街道选择
			clearTown() {
				this.form.town_code = '';
				this.form.town_name = '';
				this.townIndex = 0;
			},
			defaultChange(e) {
				this.form.is_default = e.detail.value ? 1 : 0;
			},
			validate() {
				if (!this.form.name) {
					this.$util.toast("请填写收货人姓名");
					return false;
				}
				if (!/^1[3-9]\d{9}$/.test(this.form.mobile)) {
					this.$util.toast("请填写正确的手机号码");
					return false;
				}
				if (!this.form.county_code) {
					this.$util.toast("请选择所在区域");
					return false;
				}
				// 街道/镇非必填，已移除校验
				if (!this.form.detail) {
					this.$util.toast("请填写详细地址");
					return false;
				}
				return true;
			},
			async save() {
				if (!this.validate()) return;

				uni.showLoading({
					title: '正在保存...'
				});
				try {
					const res = await this.$request({
						url: '/api/user/address-edit',
						method: 'POST',
						data: this.form
					});
					if (res.code === 0) {
						uni.showToast({
							title: '保存成功',
							icon: 'success'
						});
						setTimeout(() => uni.navigateBack(), 1000);
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
	/* [核心] 定义 SCSS 变量指向 CSS 变量 */
	$theme-color: var(--theme-color);

	page {
		background-color: #F5F6FA;
	}

	.page-container {
		padding-bottom: 140rpx;
		min-height: 100vh;
	}

	.form-card {
		background-color: #fff;
		margin: 24rpx;
		border-radius: 16rpx;
		padding: 0 30rpx;
	}

	.form-item {
		display: flex;
		align-items: center;
		min-height: 100rpx;
		border-bottom: 1rpx solid #F0F0F0;
		position: relative;

		&:last-child {
			border-bottom: none;
		}

		&.arrow::after {
			content: '›';
			position: absolute;
			right: 0;
			top: 50%;
			transform: translateY(-50%);
			font-size: 36rpx;
			color: #ccc;
		}
	}

	/* 新增：关闭按钮样式 */
	.close-btn {
		height: 100rpx;
		width: 60rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		color: #999;
		font-size: 40rpx;
		z-index: 10;
		/* 确保在输入框右侧 */
		margin-left: 10rpx;
	}

	.label {
		font-size: 30rpx;
		color: #333;
		width: 180rpx;
		flex-shrink: 0;
		
		/* [新增] 必填项红星样式 */
		&.required::before {
			content: '*';
			color: #ff4d4f; /* 红色 */
			margin-right: 4rpx;
			font-size: 30rpx;
		}
	}

	.input {
		flex: 1;
		font-size: 30rpx;
		color: #333;
		text-align: right;
		padding-right: 30rpx;
	}
	
	/* 当显示关闭按钮时，input不需要右边的padding给箭头留位置了，但为了对齐可以保留或微调 */
	.form-item:not(.arrow) .input {
		padding-right: 0;
	}

	.picker-view .input {
		padding-right: 30rpx;
	}
	
	/* 修正 picker 内部 input 样式优先级 */
	.picker-view .form-item:not(.arrow) .input {
		padding-right: 0; 
	}

	.placeholder {
		color: #bbb;
	}

	.multi-line {
		align-items: flex-start;
		padding: 24rpx 0;

		.label {
			padding-top: 6rpx;
		}
	}

	.input-group {
		flex: 1;
		text-align: right;
		padding-right: 0;

		.input {
			padding-right: 0;
		}
	}

	.hint {
		font-size: 24rpx;
		/* [核心] 提示色跟随主题 */
		color: $theme-color;
		margin-top: 8rpx;
	}

	.footer-bar {
		box-sizing: border-box;
		position: fixed;
		bottom: 0;
		left: 0;
		width: 100%;
		background-color: #fff;
		padding: 20rpx 24rpx;
		padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
		box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.05);
	}

	.save-btn {
		/* [核心] 背景色已移至 template 动态绑定 */
		color: #fff;
		height: 88rpx;
		line-height: 88rpx;
		border-radius: 44rpx;
		font-size: 32rpx;

		&::after {
			border: none;
		}
	}
</style>