<template>
	<tui-page :is-pulldown="true" :backgroundColor="'#F7F8FA'">
		<view class="container" :style="themeStyle">

			<view class="header-bg"></view>

			<view class="sticky-header" :style="{ paddingTop: navPaddingTop }">
				<view class="custom-nav">
					<view class="location-wrapper scale-active" @click="handleLocation">
						<view class="loc-icon-bg">
							<uni-icons type="location-filled" :color="themeColor" size="16"></uni-icons>
						</view>
						<text class="location-text" :style="{ color: themeColor }">{{ addressComponent.town || '定位中...' }}</text>
						<uni-icons type="bottom" :color="themeColor" size="14" style="margin-left: 4rpx;"></uni-icons>
					</view>
				</view>
				<view class="search-bar-wrapper scale-active" @click="navigateToSearch">
					<view class="search-bar">
						<uni-icons type="search" :color="themeColor" size="18"></uni-icons>
						<input class="search-input" placeholder="搜索您喜欢的门店..." disabled
							placeholder-style="color:#A3A3A3" />
					</view>
				</view>
			</view>

			<view class="category-grid card-shadow fade-in-up">
				<view class="category-item scale-active" v-for="item in categories" :key="item.id"
					@click="navigateToCategory(item.id)">
					<view class="icon-wrapper">
						<image :src="item.cover_pic" class="icon-image" mode="aspectFit"></image>
					</view>
					<text class="category-name">{{ item.cat_name }}</text>
				</view>
			</view>

			<view class="recommendation-section fade-in-up" style="animation-delay: 0.1s;"
				v-if="recommend_mch && recommend_mch.id">
				<view class="section-header" @click="$T.go('/plugins1/mch/shop-list')">
					<view class="title-box">
						<view class="deco-line"></view>
						<text class="section-title">优选商家</text>
					</view>
					<view class="more-link">更多 <uni-icons type="right" size="12" color="#999"></uni-icons></view>
				</view>

				<view class="recommendation-card card-shadow scale-active">
					<view class="store-list-item" style="padding: 0; margin-bottom: 0; box-shadow: none; border: none; background: transparent;" @click="navigateToMch(recommend_mch.id)">
						<view class="image-wrapper">
							<image class="store-logo-new" :src="recommend_mch.logo" mode="aspectFill"></image>
							<view class="distance-badge">{{ recommend_mch.distance }}</view>
							<view class="recommend-tag">推荐</view>
						</view>

						<view class="info-section-new">
							<view class="store-header">
								<view class="name-new u-line-1">{{ recommend_mch.mch_name }}</view>
							</view>

							<view class="store-tags-box">
								<text class="tag-item category-tag" v-if="recommend_mch.category">{{ recommend_mch.category.cat_name || recommend_mch.category }}</text>
								<text class="tag-item service-tag" v-for="(service, sIndex) in recommend_mch.services" :key="sIndex">{{ service.text }}</text>
								<text class="tag-item time-tag" v-if="recommend_mch.opening_hours">
									<tui-icon name="clock" :size="10" color="#3B82F6" style="margin-right: 4rpx;"></tui-icon>
									{{ recommend_mch.opening_hours }}
								</text>
							</view>

							<view class="store-address-box" v-if="recommend_mch.address || recommend_mch.location_address" @click.stop="openMap(recommend_mch)">
								<tui-icon name="position" :size="14" color="#8A8F99" style="margin-right: 6rpx; flex-shrink: 0;"></tui-icon>
								<text class="address-text-new u-line-1">{{ recommend_mch.address || recommend_mch.location_address }}</text>
								<tui-icon name="arrowright" :size="12" color="#A0A5B2" style="margin-left: 4rpx;" v-if="recommend_mch.latitude && recommend_mch.longitude"></tui-icon>
							</view>

							<view class="store-meta">
								<!-- 阻止事件冒泡，点击拨打电话 -->
								<view class="action-circle-btn phone-btn" v-if="recommend_mch.mobile || recommend_mch.tel" @click.stop="makePhoneCall(recommend_mch.mobile || recommend_mch.tel)">
									<tui-icon name="voipphone" :size="17" color="#059669"></tui-icon>
								</view>
								<!-- 优化进店按钮 -->
								<view class="sleek-enter-btn" :style="{ background: themeColor, boxShadow: `0 6rpx 16rpx ${themeColor}40` }">
									进店
									<tui-icon name="arrowright" :size="12" color="#FFF" style="margin-left: 4rpx;"></tui-icon>
								</view>
							</view>
						</view>
					</view>

					<view class="divider"></view>
					<scroll-view class="products-scroll-view" scroll-x="true"
						v-if="recommend_mch.hotGoods && recommend_mch.hotGoods.length > 0">
						<view class="product-card" v-for="product in recommend_mch.hotGoods" :key="product.id"
							@click.stop="navigateToProduct(product.id)">
							<view class="img-wrap">
								<image :src="product.cover_pic" class="product-image" mode="aspectFill"></image>
							</view>
							<text class="product-name">{{ product.name }}</text>

							<view class="price-row">
								<view class="price">
									<text class="curr">¥</text>{{ parseFloat(product.price).toFixed(2) }}
								</view>
								<view class="add-btn" :style="{background: themeColor}"
									@click.stop="addToCart(product)">
									<tui-icon name="plus" size="12" color="#fff"></tui-icon>
								</view>
							</view>
						</view>
					</scroll-view>
				</view>
			</view>

			<view class="nearby-stores-section">
				<view class="section-header" @click="$T.go('/plugins1/mch/shop-list')">
					<view class="title-box">
						<view class="deco-line"></view>
						<text class="section-title">附近商家</text>
					</view>
					<view class="more-link">更多 <uni-icons type="right" size="12" color="#999"></uni-icons></view>
				</view>

				<view class="store-list-container">
					<view class="store-list-item card-shadow fade-in-up scale-active"
						v-for="(store, index) in nearbyStores" :key="store.id"
						:style="{animationDelay: (index * 0.05 + 0.2) + 's'}" @click="navigateToMch(store.id)">

						<view class="image-wrapper">
							<image class="store-logo-new" :src="store.logo" mode="aspectFill"></image>
							<view class="distance-badge">{{ store.distance }}</view>
						</view>

						<view class="info-section-new">
							<view class="store-header">
								<view class="name-new u-line-1">{{ store.mch_name }}</view>
							</view>

							<view class="store-tags-box">
								<text class="tag-item category-tag" v-if="store.category">{{ store.category }}</text>
								<text class="tag-item service-tag" v-for="(service, sIndex) in store.services" :key="sIndex">{{ service.text }}</text>
								<text class="tag-item time-tag" v-if="store.opening_hours">
									<tui-icon name="clock" :size="10" color="#3B82F6" style="margin-right: 4rpx;"></tui-icon>
									{{ store.opening_hours }}
								</text>
							</view>

							<view class="store-address-box" v-if="store.address || store.location_address" @click.stop="openMap(store)">
								<tui-icon name="position" :size="14" color="#8A8F99" style="margin-right: 6rpx; flex-shrink: 0;"></tui-icon>
								<text class="address-text-new u-line-1">{{ store.address || store.location_address }}</text>
								<tui-icon name="arrowright" :size="12" color="#A0A5B2" style="margin-left: 4rpx;" v-if="store.latitude && store.longitude"></tui-icon>
							</view>

							<view class="store-meta">
								<view class="action-circle-btn phone-btn" v-if="store.mobile || store.tel" @click.stop="makePhoneCall(store.mobile || store.tel)">
									<tui-icon name="voipphone" :size="17" color="#059669"></tui-icon>
								</view>
								<view class="sleek-enter-btn" :style="{ background: themeColor, boxShadow: `0 6rpx 16rpx ${themeColor}40` }">
									进店
									<tui-icon name="arrowright" :size="12" color="#FFF" style="margin-left: 4rpx;"></tui-icon>
								</view>
							</view>
						</view>
					</view>
				</view>

				<tui-loadmore v-if="loading" :index="3" type="primary"></tui-loadmore>
				<tui-nomore v-if="noMore && nearbyStores.length > 0" text="没有更多商家了"
					backgroundColor="#F7F8FA"></tui-nomore>
				<tui-no-data v-if="nearbyStores.length === 0 && !loading" :fixed="false">附近暂无商家</tui-no-data>
			</view>

			<view class="tabbar-placeholder"></view>
		</view>
	</tui-page>
</template>

<script>
	import { getLocationWithTip, chooseLocationWithTip } from "@/utils/permissionTip.js";
	// 假设你使用 Vuex 管理主题色，如果没有，可以删除 mapState 相关代码，使用 data 中的默认值
	import {
		mapState
	} from 'vuex';

	export default {
		data() {
			return {
				// --- 动态数据 ---
				categories: [],
				recommend_mch: null,
				nearbyStores: [],

				// --- 页面状态 ---
				page: 1,
				loading: false,
				noMore: false,
				addressComponent: {},

				location: {
					latitude: null,
					longitude: null,
					address: ''
				},
				navPaddingTop: '0px',
			};
		},
		computed: {
			// 如果你的项目有 Vuex 管理主题色
			...mapState('mall', {
				globalThemeColor: state => state.themeColor
			}),
			// 计算最终使用的主题色
			themeColor() {
				return this.globalThemeColor || '#4A6CFF'; // 默认使用一个更现代的蓝色
			},
			// 生成 CSS 变量样式
			themeStyle() {
				return {
					'--theme-color': this.themeColor,
					'--theme-shadow': `0 8rpx 20rpx -6rpx ${this.hexToRgba(this.themeColor, 0.3)}`,
					'--theme-bg-light': this.hexToRgba(this.themeColor, 0.08)
				}
			}
		},
		onLoad() {
			this.calcNavPadding();
			this.initPage();
		},
		onPullDownRefresh() {
			this.initPage(true).finally(() => uni.stopPullDownRefresh());
		},
		onReachBottom() {
			this.getNearbyStores(false);
		},
		methods: {
			calcNavPadding() {
				// #ifdef MP-WEIXIN
				try {
					const menuBtn = wx.getMenuButtonBoundingClientRect();
					// 胶囊顶部位置就是导航内容区的起始 y，让定位栏和胶囊垂直居中
					this.navPaddingTop = menuBtn.top + 'px';
				} catch (e) {}
				// #endif
				// #ifndef MP-WEIXIN
				const sysInfo = uni.getSystemInfoSync();
				this.navPaddingTop = (sysInfo.statusBarHeight || 20) + 8 + 'px';
				// #endif
			},

			async initPage(forceRefreshLocation = false) {
				await Promise.all([
					this.getCatList(),
					this.getUserLocationAndLoadData(forceRefreshLocation)
				]);
			},

			async getCatList() {
				try {
					const res = await this.$request({
						url: "/plugin/mch/api/index/cat-list"
					});
					if (res.code === 0) {
						this.categories = res.data.list;
					}
				} catch (e) {
					console.error("getCatList failed:", e);
				}
			},

			async getUserLocationAndLoadData(forceRefreshLocation = false) {
				try {
					const location = await this.getUserLocation(forceRefreshLocation);
					this.location = location;

					if (forceRefreshLocation) {
						uni.removeStorageSync('addressInfo');
					}

					let addressComponent = uni.getStorageSync('addressInfo');

					if (!addressComponent) {
						// 优先用后端逆地理接口，失败则 fallback 到小程序原生逆地理
						try {
							const res = await this.$request({
								url: "/common/address/address-info",
								data: this.location
							});
							if (res.code == 0 && res.data.result) {
								this.addressComponent = res.data.result.addressComponent;
								uni.setStorageSync('addressInfo', res.data.result.addressComponent);
							} else {
								await this.fallbackWxLocation();
							}
						} catch (e) {
							await this.fallbackWxLocation();
						}
					} else {
						this.addressComponent = addressComponent;
					}
				} catch (error) {
					console.warn("获取地理位置失败", error);
					// 定位失败也要兜底显示，不能卡在"定位中..."
					await this.fallbackWxLocation();
				} finally {
					await this.getNearbyStores(true);
					await this.getRecommend();
				}
			},

			getUserLocation(refresh = false) {
				if (!refresh) {
					let location = uni.getStorageSync('location');
					if (location && location.latitude) {
						return Promise.resolve(location);
					}
				}
				return new Promise((resolve, reject) => {
					getLocationWithTip({
						type: 'gcj02',
						isHighAccuracy: true,
						success: (res) => {
							const newLocation = {
								latitude: res.latitude,
								longitude: res.longitude,
								address: "我的位置"
							};
							uni.setStorageSync('location', newLocation);
							uni.removeStorageSync('addressInfo');
							resolve(newLocation);
						},
						fail: (err) => {
							reject(err);
						}
					});
				});
			},

			async getRecommend() {
				const res = await this.$request({
					url: "/plugin/mch/api/index/recommend-mch",
					data: {
						latitude: this.location.latitude,
						longitude: this.location.longitude,
					}
				});
				if (res.code === 0) {
					const {
						mch
					} = res.data;
					this.recommend_mch = mch;
				}
			},

			async getNearbyStores(isRefresh = false) {
				if (isRefresh) {
					this.page = 1;
					this.noMore = false;
				}
				if (this.noMore || this.loading) return;

				if (!this.location.latitude || !this.location.longitude) {
					if (isRefresh) {
						this.nearbyStores = [];
					}
					this.noMore = true;
					return;
				}

				this.loading = true;
				try {
					const res = await this.$request({
						url: "/plugin/mch/api/index/nearby-list",
						data: {
							page: this.page,
							limit: 10,

							latitude: this.location.latitude,
							longitude: this.location.longitude,
						}
					});

					if (res.code === 0) {
						const {
							list,
							pagination
						} = res.data;
						if (isRefresh) {
							this.nearbyStores = list;
						} else {
							this.nearbyStores = this.nearbyStores.concat(list);
						}
						if (this.page >= pagination.page_count) {
							this.noMore = true;
						} else {
							this.page++;
						}
					} else {
						this.noMore = true;
					}
				} finally {
					this.loading = false;
				}
			},

			/**
			 * 小程序原生逆地理 fallback：后端接口不可用时，
			 * 用 wx.getLocation 的 type='gcj02' + uni chooseLocation 缓存的 address，
			 * 或者调用微信内置的逆地理（需要开通腾讯位置服务插件）
			 */
			fallbackWxLocation() {
				return new Promise((resolve) => {
					// #ifdef MP-WEIXIN
					const qqmapsdk = wx.getLocation ? null : null;
					// 微信小程序可以在 getLocation 时指定 isHighAccuracy + altitude 触发逆地理
					// 但最稳的方式：直接用经纬度做简单展示
					// #endif

					// 通用 fallback：用 chooseLocation 缓存的地址，或直接显示"我的位置"
					const cached = uni.getStorageSync('location');
					if (cached && cached.address && cached.address !== '我的位置') {
						// chooseLocation 选过地址的，取最后一段作为 town
						const parts = cached.address.split(/省|市|区|县|镇|乡/);
						const town = parts[parts.length - 1] || cached.address;
						this.addressComponent = { town: town.substring(0, 10) };
						uni.setStorageSync('addressInfo', this.addressComponent);
					} else {
						// 最终兜底：显示"附近"
						this.addressComponent = { town: '附近' };
					}
					resolve();
				});
			},

			handleLocation() {
				this.chooseLocationOnMap();
			},

			async chooseLocationOnMap() {
				try {
					const res = await new Promise((resolve, reject) => {
						chooseLocationWithTip({
							success: resolve,
							fail: reject
						});
					});
					const newLocation = {
						latitude: res.latitude,
						longitude: res.longitude,
						address: res.address
					};
					uni.setStorageSync('location', newLocation);
					uni.removeStorageSync('addressInfo');
					this.location = newLocation;
					uni.showLoading({
						title: '加载中...'
					});
					await this.initPage(false);
				} catch (error) {
					console.warn("chooseLocationOnMap 失败或取消:", error);
				} finally {
					uni.hideLoading();
				}
			},

			navigateToSearch() {
				this.$T.go('/plugins1/mch/shop-list');
			},
			navigateToCategory(catId) {
				this.$T.go(`/plugins1/mch/shop-list?cat_id=${catId}`);
			},
			navigateToMchList() {
				this.$T.go('/plugins1/mch/shop-list');
			},
			navigateToMch(mchId) {
				this.$T.go(`/plugins1/mch/index?id=${mchId}`);
			},
			navigateToProduct(productId) {
				this.$T.go(`/others/goods/goods?id=${productId}`);
			},
			addToCart(product) {
				this.$T.go(`/others/goods/goods?id=${product.id}`);
			},
			openMap(item) {
				if (!item.latitude || !item.longitude) {
					return uni.showToast({ title: '商家未配置有效坐标', icon: 'none' });
				}
				uni.openLocation({
					latitude: Number(item.latitude),
					longitude: Number(item.longitude),
					name: item.mch_name,
					address: item.address || item.location_address || '',
					scale: 18
				});
			},
			makePhoneCall(phone) {
				if (!phone) return;
				uni.makePhoneCall({
					phoneNumber: phone
				});
			}
		}
	}
</script>

<style lang="scss" scoped>
	/* 基础配置 */
	.container {
		width: 100%;
		min-height: 100vh;
		position: relative;
		overflow-x: hidden;
	}

	/* 头部背景装饰 - 增加层次感 */
	.header-bg {
		position: absolute;
		top: 0;
		left: 0;
		width: 100%;
		height: 480rpx;
		background: linear-gradient(180deg, var(--theme-color) 0%, rgba(255, 255, 255, 0) 100%);
		z-index: 0;
		border-bottom-left-radius: 40rpx;
		border-bottom-right-radius: 40rpx;
	}

	/* 粘性头部 */
	.sticky-header {
		position: sticky;
		top: 0;
		z-index: 99;
		padding: 0 30rpx;
		/* padding-top 由 :style 动态设置，与小程序胶囊按钮垂直对齐 */
		/* #ifdef H5 */
		top: var(--window-top);
		/* #endif */

		.custom-nav {
			height: 80rpx;
			display: flex;
			align-items: center;
			margin-bottom: 20rpx;

			.location-wrapper {
				display: flex;
				align-items: center;
				background: rgba(255, 255, 255, 0.2);
				backdrop-filter: blur(5px);
				padding: 8rpx 20rpx 8rpx 10rpx;
				border-radius: 30rpx;

				.loc-icon-bg {
					width: 44rpx;
					height: 44rpx;
					background: #fff;
					border-radius: 50%;
					display: flex;
					align-items: center;
					justify-content: center;
					margin-right: 12rpx;
				}

				.location-text {
					font-size: 30rpx;
					color: white;
					font-weight: 600;
					max-width: 300rpx;
					overflow: hidden;
					white-space: nowrap;
					text-overflow: ellipsis;
				}
			}
		}

		.search-bar-wrapper {
			padding-bottom: 10rpx;

			.search-bar {
				background-color: #fff;
				border-radius: 40rpx;
				padding: 20rpx 30rpx;
				display: flex;
				align-items: center;
				box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.08);

				.search-input {
					flex: 1;
					font-size: 28rpx;
					margin-left: 16rpx;
					color: #333;
				}
			}
		}
	}

	/* 公共样式：卡片阴影与圆角 */
	.card-shadow {
		background: #fff;
		border-radius: 24rpx;
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.04);
	}

	/* 分类区 */
	.category-grid {
		margin: 24rpx 30rpx;
		padding: 36rpx 0;
		display: flex;
		flex-wrap: wrap;
		position: relative;
		z-index: 1;

		.category-item {
			width: 20%;
			display: flex;
			flex-direction: column;
			align-items: center;
			margin-bottom: 24rpx;

			.icon-wrapper {
				width: 96rpx;
				height: 96rpx;
				display: flex;
				justify-content: center;
				align-items: center;

				.icon-image {
					width: 100%;
					height: 100%;
				}

			}

			.category-name {
				font-size: 24rpx;
				color: #333;
				margin-top: 14rpx;
				font-weight: 500;
			}
		}
	}

	/* 推荐水站卡片 */
	.recommendation-section {
		margin: 30rpx;
		position: relative;
		z-index: 1;

		.section-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 20rpx;

			.title-box {
				display: flex;
				align-items: center;

				.deco-line {
					width: 8rpx;
					height: 32rpx;
					background: var(--theme-color);
					border-radius: 4rpx;
					margin-right: 12rpx;
				}

				.section-title {
					font-size: 34rpx;
					color: #333;
					font-weight: bold;
				}
			}

			.more-link {
				font-size: 26rpx;
				color: #999;
				display: flex;
				align-items: center;
			}
		}

		.recommendation-card {
			padding: 30rpx;

			.recommend-tag {
				position: absolute;
				top: -8rpx;
				right: -8rpx;
				background: #FF5E5E;
				color: #fff;
				font-size: 18rpx;
				padding: 2rpx 8rpx;
				border-radius: 8rpx 0 8rpx 0;
			}

			.divider {
				height: 1rpx;
				background: #f5f5f5;
				margin: 24rpx 0;
			}

			.products-scroll-view {
				white-space: nowrap;
				width: 100%;

				.product-card {
					display: inline-block;
					width: 200rpx;
					margin-right: 20rpx;
					vertical-align: top;

					.img-wrap {
						width: 200rpx;
						height: 200rpx;
						border-radius: 12rpx;
						overflow: hidden;
						position: relative;

						.product-image {
							width: 100%;
							height: 100%;
						}
					}

					.product-name {
						font-size: 26rpx;
						color: #333;
						margin: 12rpx 0 8rpx;
						white-space: nowrap;
						overflow: hidden;
						text-overflow: ellipsis;
						display: block;
					}

					.price-row {
						display: flex;
						justify-content: space-between;
						align-items: center;

						.price {
							color: #FF4D4F;
							font-size: 30rpx;
							font-weight: bold;

							.curr {
								font-size: 22rpx;
								margin-right: 2rpx;
							}
						}

						.add-btn {
							width: 44rpx;
							height: 44rpx;
							border-radius: 50%;
							display: flex;
							align-items: center;
							justify-content: center;
							box-shadow: 0 4rpx 10rpx rgba(0, 0, 0, 0.1);
						}
					}
				}
			}
		}
	}

	/* 附近商家 */
	.nearby-stores-section {
		margin: 30rpx;
		position: relative;
		z-index: 1;

		.section-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 24rpx;

			.title-box {
				display: flex;
				align-items: center;

				.deco-line {
					width: 8rpx;
					height: 32rpx;
					background: var(--theme-color);
					border-radius: 4rpx;
					margin-right: 12rpx;
				}

				.section-title {
					font-size: 34rpx;
					color: #333;
					font-weight: bold;
				}
			}

			.more-link {
				font-size: 26rpx;
				color: #999;
				display: flex;
				align-items: center;
			}
		}
	} // 结束 nearby-stores-section

	/* 将 store-list-item 提取为公共样式，供优选商家和附近商家共用 */
	.store-list-item {
		background: #fff;
			padding: 30rpx;
			margin-bottom: 24rpx;
			display: flex;

			.image-wrapper {
				position: relative;
				margin-right: 24rpx;
				width: 160rpx;
				height: 160rpx;
				flex-shrink: 0;

				.store-logo-new {
					width: 100%;
					height: 100%;
					border-radius: 16rpx;
					border: 1rpx solid #f5f5f5;
					display: block;
				}

				.distance-badge {
					position: absolute;
					bottom: 0;
					left: 0;
					width: 100%;
					background: rgba(0, 0, 0, 0.6);
					color: #fff;
					font-size: 20rpx;
					text-align: center;
					padding: 4rpx 0;
					border-bottom-left-radius: 16rpx;
					border-bottom-right-radius: 16rpx;
				}
			}

			.info-section-new {
				flex: 1;
				display: flex;
				flex-direction: column;
				justify-content: space-between;
				min-width: 0; /* Enable text truncation */

				.store-header {
					.name-new {
						font-size: 32rpx;
						font-weight: bold;
						color: #2B2F3A;
						line-height: 1.4;
					}
				}

				.store-tags-box {
					display: flex;
					flex-wrap: wrap;
					margin-top: 8rpx;

					.tag-item {
						font-size: 20rpx;
						padding: 4rpx 12rpx;
						border-radius: 6rpx;
						margin-right: 12rpx;
						margin-bottom: 8rpx;
						display: flex;
						align-items: center;
					}

					.category-tag {
						color: #FF8A00;
						background: #FFF6ED;
					}

					.service-tag {
						color: #64748B;
						background: #F1F5F9;
					}

					.time-tag {
						color: #3B82F6;
						background-color: #EFF6FF;
					}
				}

				.store-address-box {
					display: flex;
					align-items: center;
					background: #F8FAFC;
					padding: 8rpx 16rpx;
					border-radius: 8rpx;
					transition: background 0.2s;
					margin-top: 4rpx;
					
					&:active {
						background: #F1F5F9;
					}

					.address-text-new {
						font-size: 22rpx;
						color: #64748B;
						margin-left: 6rpx;
						flex: 1;
					}
				}

				.store-meta {
					display: flex;
					justify-content: flex-end;
					align-items: center;
					margin-top: 20rpx;

					.action-circle-btn {
						width: 56rpx;
						height: 56rpx;
						border-radius: 50%;
						display: flex;
						align-items: center;
						justify-content: center;
						margin-right: 18rpx;
						transition: transform 0.2s, background 0.2s;
						
						&:active {
							transform: scale(0.9);
						}
					}

					.phone-btn {
						background: #D1FAE5;
					}

					.sleek-enter-btn {
						font-size: 24rpx;
						color: #FFF;
						height: 56rpx;
						border-radius: 30rpx;
						font-weight: 600;
						display: flex;
						align-items: center;
						padding: 0 32rpx;
						transition: transform 0.2s;
						
						&:active {
							transform: scale(0.96);
						}
					}
				}
			}
		}

	.tabbar-placeholder {
		height: 120rpx;
	}

	/* 动画与交互效果 */
	.scale-active:active {
		transform: scale(0.98);
		transition: transform 0.1s ease;
	}

	@keyframes fadeInUp {
		from {
			opacity: 0;
			transform: translateY(20rpx);
		}

		to {
			opacity: 1;
			transform: translateY(0);
		}
	}

	.fade-in-up {
		animation: fadeInUp 0.5s ease-out forwards;
	}
</style>