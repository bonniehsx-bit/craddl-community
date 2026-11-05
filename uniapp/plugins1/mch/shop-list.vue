<template>
	<tui-page :is-pulldown="true" @pulldown="onPullDownRefresh" @reach-bottom="onReachBottom"
		:backgroundColor="'#F7F8FA'">
		<view class="container" :style="themeStyle">

			<view class="sticky-header">
				<view class="search-bar-wrapper">
					<view class="search-bar">
						<uni-icons type="search" :color="themeColor" size="18"></uni-icons>
						<input class="search-input" :placeholder="defaultKeyword || '搜索您喜欢的门店...'"
							placeholder-style="color:#A3A3A3" v-model="searchParams.keyword" confirm-type="search"
							@confirm="handleSearch" @input="onInput" />
					</view>
				</view>

				<view class="filter-tabs">
					<view class="tab-item scale-active" :class="{'is-active': searchParams.sort_key === 'composite'}"
						@click="handleSortChange('composite')">
						<tui-icon name="category" :size="14"
							:color="searchParams.sort_key === 'composite' ? themeColor : '#666'"></tui-icon>
						<text>综合排序</text>
					</view>
					<view class="tab-item scale-active" :class="{'is-active': searchParams.sort_key === 'distance'}"
						@click="handleSortChange('distance')">
						<tui-icon name="position" :size="14"
							:color="searchParams.sort_key === 'distance' ? themeColor : '#666'"></tui-icon>
						<text>距离最近</text>
					</view>
					<view class="tab-item scale-active" :class="{'is-active': searchParams.sort_key === 'sales'}"
						@click="handleSortChange('sales')">
						<tui-icon name="histogram" :size="14"
							:color="searchParams.sort_key === 'sales' ? themeColor : '#666'"></tui-icon>
						<text>销量最高</text>
					</view>
				</view>
			</view>

			<view class="store-list-container">
				<view class="store-list-item card-shadow fade-in-up scale-active" v-for="(store, index) in storeList"
					:key="store.id" :style="{animationDelay: index * 0.05 + 's', marginBottom: '24rpx', borderRadius: '20rpx'}" @click="navigateToMch(store.id)">
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

			<tui-loadmore v-if="loading" :index="3" type="primary" :color="themeColor"></tui-loadmore>
			<tui-nomore v-if="noMore && storeList.length > 0" text="没有更多商家了" backgroundColor="#F7F8FA"></tui-nomore>
			<tui-no-data v-if="storeList.length === 0 && !loading" :fixed="false">附近暂无商家</tui-no-data>
		</view>
	</tui-page>
</template>

<script>
	import { getLocationWithTip } from "@/utils/permissionTip.js";
	// 引入 Vuex mapState
	import {
		mapState
	} from 'vuex';

	export default {
		data() {
			return {
				storeList: [],
				loading: false,
				page: 1,
				noMore: false,
				defaultKeyword: "",
				userInputInteracted: false,

				searchParams: {
					repurchase_zone: '',
					keyword: '',
					cat_id: '',
					latitude: '',
					longitude: '',
					sort_key: 'composite',
					sort_order: 'desc',
				}
			};
		},
		computed: {
			// [核心] 获取全局主题色
			...mapState('mall', {
				globalThemeColor: state => state.themeColor
			}),
			themeColor() {
				return this.globalThemeColor || '#D71F1F'; // 默认红
			},
			themeStyle() {
				return {
					'--theme-color': this.themeColor,
					'--theme-bg-light': this.hexToRgba(this.themeColor, 0.08)
				}
			}
		},
		onLoad(options) {
			uni.setNavigationBarTitle({
				title: '商家列表'
			});
			if (options.keyword) {
				this.searchParams.keyword = options.keyword;
				this.userInputInteracted = true;
			}
			if (options.repurchase_zone) {
				this.searchParams.repurchase_zone = options.repurchase_zone;
			}
			if (options.cat_id && options.cat_id !== 'more') {
				this.searchParams.cat_id = options.cat_id;
			}

			this.defaultKeyword = this.$util.getSetting('mch_keyword');

			this.initPage(false);
		},
		onPullDownRefresh() {
			this.initPage(true).finally(() => uni.stopPullDownRefresh());
		},
		onReachBottom() {
			this.getStoreList(false);
		},
		methods: {
			async initPage(forceRefreshLocation = false) {
				await this.getUserLocation(forceRefreshLocation);
				this.getStoreList(true);
			},

			getUserLocation(refresh = false) {
				if (!refresh) {
					const location = uni.getStorageSync('location');
					if (location && location.latitude) {
						this.searchParams.latitude = location.latitude;
						this.searchParams.longitude = location.longitude;
						return Promise.resolve();
					}
				}

				return new Promise((resolve) => {
					getLocationWithTip({
						type: 'gcj02',
						isHighAccuracy: true,
						success: (res) => {
							const newLocation = {
								latitude: res.latitude,
								longitude: res.longitude
							};
							this.searchParams.latitude = newLocation.latitude;
							this.searchParams.longitude = newLocation.longitude;
							uni.setStorageSync('location', newLocation);
						},
						fail: () => {
							const location = uni.getStorageSync('location');
							if (location && location.latitude) {
								this.searchParams.latitude = location.latitude;
								this.searchParams.longitude = location.longitude;
							} else {
								uni.showToast({
									title: '获取位置失败',
									icon: 'none'
								});
							}
						},
						complete: () => {
							resolve();
						}
					});
				});
			},

			async getStoreList(isRefresh = false) {
				if (isRefresh) {
					this.page = 1;
					this.noMore = false;
				}
				if (this.noMore || this.loading) return;
				this.loading = true;

				try {
					const res = await this.$request({
						url: "/plugin/mch/api/index/nearby-list",
						data: {
							page: this.page,
							limit: 10,
							...this.searchParams
						}
					});

					if (res.code === 0) {
						const {
							list,
							pagination
						} = res.data;
						this.storeList = isRefresh ? list : this.storeList.concat(list);

						if (!pagination || this.page >= pagination.page_count) {
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
			onInput() {
				if (!this.userInputInteracted) {
					this.userInputInteracted = true;
				}
			},
			handleSearch() {
				let finalKeyword = this.searchParams.keyword.trim();
				if (!finalKeyword && !this.userInputInteracted) {
					finalKeyword = this.defaultKeyword;
				}
				this.searchParams.keyword = finalKeyword;
				this.getStoreList(true);
			},
			handleSortChange(key) {
				if (this.loading) return;
				if (this.searchParams.sort_key !== key) {
					this.searchParams.keyword = '';
					this.userInputInteracted = false;
				}
				this.searchParams.sort_key = key;
				this.getStoreList(true);
			},
			navigateToMch(mchId) {
				this.$T.go(`/plugins1/mch/index?id=${mchId}`);
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

<style lang="scss">
	/* 基础配置 */
	.container {
		width: 100%;
		min-height: 100vh;
		position: relative;
	}

	page {
		background-color: #F7F8FA;
	}

	.sticky-header {
		position: sticky;
		top: 0;
		z-index: 99;
		background-color: #fff;
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.03);
		/* #ifdef H5 */
		top: var(--window-top);
		/* #endif */
	}

	.search-bar-wrapper {
		padding: 20rpx 30rpx;

		.search-bar {
			background-color: #F5F6FA;
			border-radius: 40rpx;
			padding: 18rpx 30rpx;
			display: flex;
			align-items: center;

			.search-input {
				flex: 1;
				font-size: 28rpx;
				margin-left: 16rpx;
				color: #333;
			}
		}
	}

	.filter-tabs {
		display: flex;
		padding-bottom: 4rpx;

		.tab-item {
			flex: 1;
			display: flex;
			justify-content: center;
			align-items: center;
			gap: 8rpx;
			padding: 24rpx 0;
			font-size: 28rpx;
			color: #666;
			position: relative;
			transition: all 0.2s;

			&.is-active {
				color: var(--theme-color); // 使用主题色
				font-weight: bold;

				&::after {
					content: '';
					position: absolute;
					bottom: 0;
					left: 50%;
					transform: translateX(-50%);
					width: 40rpx;
					height: 6rpx;
					background: var(--theme-color);
					border-radius: 6rpx;
				}
			}
		}
	}

	.store-list-container {
		padding: 24rpx 30rpx;
	}

	.store-list-item {
		background: #fff;
		padding: 30rpx;
		display: flex;
		// 优化阴影
		box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.03);

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
			min-width: 0;

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
					overflow: hidden;
					white-space: nowrap;
					text-overflow: ellipsis;
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

	/* 动效 */
	.scale-active:active {
		transform: scale(0.98);
	}

	.fade-in-up {
		animation: fadeInUp 0.5s ease-out forwards;
		opacity: 0;
		transform: translateY(20rpx);
	}

	@keyframes fadeInUp {
		to {
			opacity: 1;
			transform: translateY(0);
		}
	}
</style>