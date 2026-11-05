<template>
	<view class="diy-header-banner" v-if="value">

		<!-- 1. 沉浸式背景层 -->
		<view class="header-bg" :style="{ backgroundColor: currentBgColor, height: (460 + statusBarHeight) + 'rpx' }">
			<view class="noise-overlay"></view>
		</view>

		<!-- 2. 内容主体 -->
		<view class="content-wrapper" :style="{ paddingTop: (menuButtonTop || statusBarHeight) + 'px' }">

			<!-- 2.1 顶部定位栏 -->
			<view class="top-row" :style="{ height: menuButtonHeight + 'px' }" v-if="value.show_location == 1">
				<!-- 点击触发内部定位逻辑 -->
				<view class="location-info glass-effect" @click="handleUpdateLocation">
					<view class="loc-icon-box">
						<tui-icon name="position-fill" :color="currentBgColor" :size="14"></tui-icon>
					</view>
					<text class="location-text">{{ currentAddress || '点击定位' }}</text>
					<tui-icon name="arrowright" color="rgba(255,255,255,0.7)" :size="12"></tui-icon>
				</view>
			</view>
			<view class="top-row-placeholder" :style="{ height: menuButtonHeight + 'px' }" v-else></view>

			<!-- 2.2 搜索栏 -->
			<view class="search-section">
				<view class="search-bar glass-effect" @click="handleSearchClick">
					<tui-icon name="search" color="#666" size="18"></tui-icon>
					<text class="placeholder-text">{{ value.search_placeholder || '搜索好物、店铺' }}</text>
					<view class="search-btn" :style="{ backgroundColor: currentBgColor }">搜索</view>
				</view>

				<!-- 消息图标 -->
				<view class="notification" v-if="value.show_message == 1" @click="handleMsgClick">
					<view class="notice-icon-box">
						<tui-icon name="notice" color="#fff" :size="24"></tui-icon>
					</view>
				</view>
			</view>

			<!-- 2.3 轮播图 -->
			<view class="banner-box" v-if="bannerList.length > 0">
				<view class="swiper-shadow-wrapper">
					<swiper class="swiper" autoplay circular indicator-dots indicator-color="rgba(255,255,255,0.4)"
						indicator-active-color="#fff" @change="onBannerChange">
						<swiper-item v-for="(item, index) in bannerList" :key="index" @click="handleBannerClick(item)">
							<image :src="item.pic_url" mode="aspectFill" class="banner-img"></image>
						</swiper-item>
					</swiper>
				</view>
			</view>
		</view>

		<!-- 3. 权限弹窗组件 (必须引入) -->
		<yk-authpup ref="authpup" type="top" @changeAuth="changeAuth" permissionID="ACCESS_FINE_LOCATION"></yk-authpup>
	</view>
</template>

<script>
	import { chooseLocationWithTip } from "@/utils/permissionTip.js";
// 引入权限弹窗组件 (请确保路径正确)
import ykAuthpup from "@/components/yk-authpup/yk-authpup";

export default {
	name: "tui-header-banner",
	components: {
		ykAuthpup
	},
	props: {
		value: {
			type: Object,
			default: () => null
		}
	},
	data() {
		return {
			// --- 状态栏适配 ---
			statusBarHeight: 20,
			menuButtonTop: 0,
			menuButtonHeight: 32,

			// --- 业务数据 ---
			currentBgColor: '#AC1B26',
			currentAddress: '点击定位',

			// --- 定位逻辑相关 ---
			location: null,
			_locationSuccessCallback: null,
		};
	},
	computed: {
		bannerList() {
			return (this.value && this.value.banners) ? this.value.banners : [];
		}
	},
	watch: {
		value: {
			handler(val) {
				if (val && val.banners && val.banners.length > 0) {
					this.currentBgColor = val.banners[0].bg_color;
				}
				// 初始化时读取缓存显示
				this.loadAddressFromCache();
			},
			immediate: true,
			deep: true
		}
	},
	created() {
		this.initSystemInfo();
		this.loadAddressFromCache();
	},
	methods: {
		// ======================= [系统初始化] =======================
		initSystemInfo() {
			const sysInfo = uni.getSystemInfoSync();
			this.statusBarHeight = sysInfo.statusBarHeight || 20;
			// #ifdef MP-WEIXIN
			const menuButton = uni.getMenuButtonBoundingClientRect();
			if (menuButton) {
				this.menuButtonTop = menuButton.top;
				this.menuButtonHeight = menuButton.height;
			}
			// #endif
			// #ifndef MP-WEIXIN
			this.menuButtonTop = this.statusBarHeight + 5;
			// #endif
		},

		loadAddressFromCache() {
			const cachedAddress = uni.getStorageSync('addressInfo');
			if (cachedAddress && cachedAddress.town) {
				this.currentAddress = cachedAddress.town;
			}
		},

		// ======================= [核心定位逻辑] =======================

		// 1. 点击触发
		handleUpdateLocation() {
			this.requestLocationPermission(this.executeLocationFetch);
		},

		// 2. 权限检测 (适配 Android App)
		requestLocationPermission(successCallback) {
			this._locationSuccessCallback = successCallback;
			// #ifdef APP-PLUS
			if (plus.os.name === 'Android') {
				const permissionID = 'ACCESS_FINE_LOCATION';
				const permissionString = 'android.permission.' + permissionID;
				plus.android.checkPermission(permissionString, (e) => {
					if (e.checkResult === 1) {
						if (typeof this._locationSuccessCallback === 'function') this
							._locationSuccessCallback();
					} else {
						// 唤起权限弹窗
						this.$refs.authpup.permissionID = permissionID;
						this.$refs.authpup.open();
					}
				});
				return;
			}
			// #endif
			// 小程序或iOS直接执行
			if (typeof successCallback === 'function') successCallback();
		},

		// 3. 权限弹窗回调
		changeAuth() {
			if (typeof this._locationSuccessCallback === 'function') {
				this._locationSuccessCallback();
			}
			this._locationSuccessCallback = null;
		},

		// 4. 执行定位和解析
		async executeLocationFetch() {
			try {
				// 4.1 打开地图选择位置
				const location = await this.getUserLocation();
				this.location = location;

				uni.showLoading({
					title: '地址解析中...'
				});

				// 4.2 调用后端接口解析经纬度
				const res = await this.$request({
					url: "/common/address/address-info",
					data: this.location
				});

				if (res.code === 0 && res.data.result) {
					const addressComponent = res.data.result.addressComponent;

					// 4.3 更新 UI 和 缓存
					this.currentAddress = addressComponent.town || addressComponent.district;
					uni.setStorageSync('addressInfo', addressComponent);
					uni.setStorageSync('location', this.location);

					uni.showToast({
						title: '已更新位置',
						icon: 'none'
					});

					// 4.4 【关键】通知父组件刷新数据（如商品列表）
					this.$emit('refresh', {
						location: this.location,
						address: addressComponent
					});
				} else {
					uni.showToast({
						title: '地址解析失败',
						icon: 'none'
					});
				}
			} catch (error) {
				if (error && error.errMsg && error.errMsg.indexOf('cancel') !== -1) {
					return; // 用户取消选择
				}
				console.error("定位失败:", error);
				uni.showToast({
					title: '获取位置失败',
					icon: 'none'
				});
			} finally {
				uni.hideLoading();
			}
		},

		// 5. 封装 uni.chooseLocation
		getUserLocation() {
			return new Promise((resolve, reject) => {
				chooseLocationWithTip({
					success: (res) => {
						const newLocation = {
							latitude: res.latitude,
							longitude: res.longitude,
							address: res.address,
							name: res.name
						};
						resolve(newLocation);
					},
					fail: (err) => {
						reject(err);
					}
				});
			});
		},

		// ======================= [其他交互] =======================
		onBannerChange(e) {
			const current = e.detail.current;
			const banners = this.bannerList;
			if (banners.length > 0 && banners[current] && banners[current].bg_color) {
				this.currentBgColor = banners[current].bg_color;
			}
		},
		handleBannerClick(item) {
			if (item.link) this.$T.go(item.link);
		},
		handleSearchClick() {
			this.$T.go('/plugins1/mch/spss/spss');
		},
		handleMsgClick() {
			this.$T.go('/plugins/message/list');
		}
	}
}
</script>

<style lang="scss" scoped>
/* 变量定义 */
$card-radius: 24rpx;

.diy-header-banner {
	position: relative;
	width: 100%;
	padding-bottom: 20rpx;
}

.header-bg {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	border-bottom-left-radius: 48rpx;
	border-bottom-right-radius: 48rpx;
	z-index: 0;
	transition: background-color 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
	overflow: hidden;

	.noise-overlay {
		position: absolute;
		width: 100%;
		height: 100%;
		background-image: linear-gradient(135deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.1) 100%);
		pointer-events: none;
	}
}

.content-wrapper {
	position: relative;
	z-index: 1;
	padding-left: 24rpx;
	padding-right: 24rpx;
}

/* 顶部定位 */
.top-row {
	display: flex;
	align-items: center;

	.location-info {
		display: flex;
		align-items: center;
		padding: 4rpx 20rpx 4rpx 6rpx;
		border-radius: 50rpx;
		background: rgba(255, 255, 255, 0.2);
		backdrop-filter: blur(10px);
		border: 1rpx solid rgba(255, 255, 255, 0.1);
		max-width: 60%;

		.loc-icon-box {
			width: 44rpx;
			height: 44rpx;
			background: #fff;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			margin-right: 12rpx;
			box-shadow: 0 2rpx 8rpx rgba(0, 0, 0, 0.1);
		}

		.location-text {
			color: #fff;
			font-size: 28rpx;
			font-weight: 600;
			margin-right: 8rpx;
			overflow: hidden;
			white-space: nowrap;
			text-overflow: ellipsis;
		}
	}
}

.top-row-placeholder {
	width: 100%;
}

/* 搜索栏 */
.search-section {
	display: flex;
	align-items: center;
	margin-top: 24rpx;
	margin-bottom: 24rpx;

	.search-bar {
		flex: 1;
		height: 76rpx;
		background: rgba(255, 255, 255, 0.95);
		backdrop-filter: blur(10px);
		border-radius: 38rpx;
		display: flex;
		align-items: center;
		padding: 0 8rpx 0 30rpx;
		box-shadow: 0 8rpx 20rpx rgba(0, 0, 0, 0.08);

		.placeholder-text {
			flex: 1;
			color: #999;
			font-size: 28rpx;
			margin-left: 14rpx;
			overflow: hidden;
			white-space: nowrap;
			text-overflow: ellipsis;
		}

		.search-btn {
			width: 130rpx;
			height: 60rpx;
			border-radius: 30rpx;
			color: #fff;
			font-size: 28rpx;
			display: flex;
			align-items: center;
			justify-content: center;
			font-weight: 500;
			transition: background-color 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
		}
	}

	.notification {
		margin-left: 24rpx;
		position: relative;
		flex-shrink: 0;

		.notice-icon-box {
			width: 60rpx;
			height: 60rpx;
			display: flex;
			align-items: center;
			justify-content: center;
		}
	}
}

/* 轮播图 */
.banner-box {
	width: 100%;

	.swiper-shadow-wrapper {
		border-radius: $card-radius;
		box-shadow: 0 16rpx 40rpx rgba(0, 0, 0, 0.15);
		overflow: hidden;
		transform: translateY(0);
	}

	.swiper {
		height: 300rpx;
		border-radius: $card-radius;
		overflow: hidden;
	}

	.banner-img {
		width: 100%;
		height: 100%;
		border-radius: $card-radius;
	}
}
</style>