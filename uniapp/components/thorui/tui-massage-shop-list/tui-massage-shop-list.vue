<template>
	<view class="diy-massage-shop-list-outer" :style="outerStyle" v-if="list.length > 0">
	 <view class="diy-massage-shop-list" :style="styleVars">
		<!-- 标题栏 -->
		<view class="header" v-if="safeData.title || safeData.subtitle">
			<view class="title-wrap">
				<text class="title">{{ safeData.title }}</text>
				<text class="subtitle" v-if="safeData.subtitle">{{ safeData.subtitle }}</text>
			</view>
			<view class="more" v-if="safeData.show_more == 1" @click="goMore">
				<text>查看更多</text>
				<tui-icon name="arrowright" :size="20" unit="rpx"></tui-icon>
			</view>
		</view>

		<!-- 列表样式 -->
		<view v-if="safeData.style === 'list'" class="list-layout">
			<view class="shop-item" v-for="(shop, idx) in list" :key="idx" @click="goDetail(shop)">
				<image v-if="shop.cover" :src="shop.cover" mode="aspectFill" class="cover" />
				<view v-else class="cover cover-placeholder">
					<tui-icon name="shop" :size="40" color="#CBD5E1"></tui-icon>
				</view>
				<view class="info">
					<view class="name-row">
						<text class="name">{{ shop.name }}</text>
						<text v-if="shop.cert_tag" class="cert-tag">{{ shop.cert_tag }}</text>
					</view>
					<view class="meta-row" v-if="safeData.show_rating == 1 && shop.rating > 0">
						<tui-icon name="star-fill" :size="20" unit="rpx" color="#F59E0B"></tui-icon>
						<text>{{ shop.rating.toFixed(1) }}</text>
						<text class="dot">·</text>
						<text>{{ shop.review_count }} 条评价</text>
					</view>
					<view class="addr-row" v-if="shop.address">
						<tui-icon name="location" :size="20" unit="rpx" color="#94A3B8"></tui-icon>
						<text class="addr">{{ shop.address }}</text>
						<text v-if="safeData.show_distance == 1 && shop.distance_km !== null" class="distance">
							{{ formatDistance(shop.distance_km) }}
						</text>
					</view>
				</view>
			</view>
		</view>

		<!-- 网格样式 -->
		<view v-else class="grid-layout">
			<view class="shop-card" v-for="(shop, idx) in list" :key="idx" @click="goDetail(shop)">
				<view class="cover-wrap">
					<image v-if="shop.cover" :src="shop.cover" mode="aspectFill" class="cover" />
					<view v-else class="cover cover-placeholder">
						<tui-icon name="shop" :size="40" color="#CBD5E1"></tui-icon>
					</view>
					<text v-if="safeData.show_distance == 1 && shop.distance_km !== null" class="distance-tag">
						{{ formatDistance(shop.distance_km) }}
					</text>
				</view>
				<view class="card-body">
					<text class="name">{{ shop.name }}</text>
					<view class="meta" v-if="safeData.show_rating == 1 && shop.rating > 0">
						<tui-icon name="star-fill" :size="18" unit="rpx" color="#F59E0B"></tui-icon>
						<text>{{ shop.rating.toFixed(1) }} · {{ shop.review_count }}</text>
					</view>
				</view>
			</view>
		</view>
	 </view>
	</view>
</template>

<script>
export default {
	name: 'TuiMassageShopList',
	props: {
		value: { type: Object, default: () => ({}) },
	},
	data() {
		return {
			list: [],
			loading: true,
		}
	},
	computed: {
		safeData() {
			return Object.assign({
				title: '附近艾灸门店',
				subtitle: '',
				limit: 5,
				show_distance: 1,
				show_rating: 1,
				show_more: 1,
				style: 'list',
				is_padding: 1,
				bg_color: '',
				title_color: '',
				item_bg_color: '',
				accent_color: '',
			}, this.value || {})
		},
		outerStyle() {
			return `padding:${this.safeData.is_padding == 1 ? '0 20rpx' : '0'};`
		},
		styleVars() {
			const d = this.safeData
			const parts = []
			if (d.bg_color)      parts.push(`--c-bg:${d.bg_color}`)
			if (d.title_color)   parts.push(`--c-title:${d.title_color}`)
			if (d.item_bg_color) parts.push(`--c-item-bg:${d.item_bg_color}`)
			if (d.accent_color)  parts.push(`--c-accent:${d.accent_color}`)
			return parts.join(';')
		},
	},
	created() {
		this.fetchData()
	},
	methods: {
		fetchData() {
			const limit = parseInt(this.safeData.limit) || 5
			// 尝试拿到本地缓存的定位（首页/门店列表页通常已 getLocation 缓存）；
			// 没有也无所谓，后端会按评分降序兜底
			const loc = uni.getStorageSync('USER_LOCATION') || {}
			const data = { limit }
			if (loc.longitude && loc.latitude) {
				data.lng = loc.longitude
				data.lat = loc.latitude
			}
			this.$request({
				url: '/plugin/massage/api/index/diy-shop-list',
				data,
			}).then(res => {
				this.loading = false
				if (res.code === 0) {
					this.list = (res.data.list || []).slice(0, limit)
				}
			}).catch(() => {
				this.loading = false
			})
		},
		formatDistance(km) {
			if (km === null || km === undefined) return ''
			return km < 1 ? Math.round(km * 1000) + 'm' : km.toFixed(1) + 'km'
		},
		goDetail(shop) {
			uni.navigateTo({ url: `/plugins2/massage/shop-detail?id=${shop.id}` })
		},
		goMore() {
			uni.navigateTo({ url: '/plugins2/massage/shop-list' })
		},
	},
}
</script>

<style lang="scss" scoped>
.diy-massage-shop-list-outer {
	margin-bottom: 20rpx;
}
.diy-massage-shop-list {
	background: var(--c-bg, #fff);
	padding: 24rpx;
	border-radius: 20rpx;
}

.header {
	display: flex;
	justify-content: space-between;
	align-items: flex-end;
	margin-bottom: 24rpx;
	.title-wrap { flex: 1; min-width: 0; }
	.title { font-size: 30rpx; font-weight: 800; color: var(--c-title, #0F172A); display: block; }
	.subtitle { font-size: 22rpx; color: #94A3B8; margin-top: 6rpx; display: block; }
	.more {
		display: flex; align-items: center;
		font-size: 22rpx; color: #94A3B8;
		flex-shrink: 0; margin-left: 16rpx;
	}
}

/* 列表 */
.list-layout {
	.shop-item {
		display: flex;
		padding: 20rpx;
		background: var(--c-item-bg, #F8FAFC);
		border-radius: 16rpx;
		margin-bottom: 16rpx;
		&:last-child { margin-bottom: 0; }
		&:active { transform: scale(0.99); opacity: 0.96; }

		.cover {
			width: 160rpx;
			height: 160rpx;
			border-radius: 12rpx;
			background: #F1F5F9;
			flex-shrink: 0;
		}
		.cover-placeholder {
			display: flex; align-items: center; justify-content: center;
		}
		.info {
			flex: 1; min-width: 0;
			margin-left: 20rpx;
			display: flex; flex-direction: column; justify-content: space-between;
			padding: 4rpx 0;
		}
		.name-row {
			display: flex; align-items: center; gap: 10rpx;
			.name {
				font-size: 28rpx; font-weight: 700; color: var(--c-title, #0F172A);
				overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
				flex: 1; min-width: 0;
			}
			.cert-tag {
				font-size: 18rpx;
				color: #fff;
				background: var(--c-accent, linear-gradient(135deg, #F5B14A 0%, #E29028 100%));
				padding: 2rpx 10rpx;
				border-radius: 6rpx;
				flex-shrink: 0;
			}
		}
		.meta-row {
			display: flex; align-items: center;
			font-size: 22rpx; color: #94A3B8;
			gap: 4rpx;
			.dot { margin: 0 4rpx; }
		}
		.addr-row {
			display: flex; align-items: center;
			font-size: 22rpx; color: #94A3B8;
			gap: 4rpx;
			.addr {
				flex: 1; min-width: 0;
				overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
			}
			.distance {
				flex-shrink: 0;
				margin-left: 12rpx;
				color: var(--c-accent, var(--theme-color));
				font-weight: 700;
			}
		}
	}
}

/* 网格 */
.grid-layout {
	display: flex;
	flex-wrap: wrap;
	gap: 16rpx;

	.shop-card {
		width: calc(50% - 8rpx);
		background: var(--c-item-bg, #F8FAFC);
		border-radius: 16rpx;
		overflow: hidden;
		&:active { transform: scale(0.98); opacity: 0.96; }
	}
	.cover-wrap {
		position: relative;
		height: 220rpx;
		background: #F1F5F9;
	}
	.cover {
		width: 100%;
		height: 100%;
	}
	.cover-placeholder {
		display: flex; align-items: center; justify-content: center;
	}
	.distance-tag {
		position: absolute;
		top: 12rpx; right: 12rpx;
		background: rgba(0,0,0,0.55);
		color: #fff;
		font-size: 20rpx;
		padding: 4rpx 12rpx;
		border-radius: 8rpx;
	}
	.card-body { padding: 16rpx 20rpx; }
	.name {
		display: block;
		font-size: 26rpx; font-weight: 700; color: var(--c-title, #0F172A);
		overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
	}
	.meta {
		display: flex; align-items: center;
		font-size: 20rpx; color: #94A3B8;
		margin-top: 6rpx;
		gap: 4rpx;
	}
}
</style>
