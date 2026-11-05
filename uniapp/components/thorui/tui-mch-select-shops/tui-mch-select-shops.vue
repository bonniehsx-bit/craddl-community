<template>
	<view class="diy-mch-select-shops-outer" :style="outerStyle" v-if="list.length > 0">
	 <view class="diy-mch-select-shops" :style="styleVars">
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

		<!-- 网格 -->
		<view v-if="safeData.style === 'grid'" class="grid-layout">
			<view class="shop-card" v-for="(it, idx) in list" :key="idx" @click="goShop(it)">
				<view class="cover-wrap">
					<image v-if="it.logo" :src="it.logo" mode="aspectFill" class="cover" />
					<view v-else class="cover cover-placeholder"><tui-icon name="shop" :size="48" color="#94A3B8"></tui-icon></view>
				</view>
				<view class="card-body">
					<text class="name">{{ it.name }}</text>
					<view class="meta" v-if="safeData.show_rating == 1 && it.rating > 0">
						<tui-icon name="star-fill" :size="22" unit="rpx" color="#F59E0B"></tui-icon>
						<text class="score">{{ it.rating.toFixed(1) }}</text>
					</view>
					<text class="addr" v-if="safeData.show_address == 1 && it.address">{{ it.address }}</text>
				</view>
			</view>
		</view>

		<!-- 横滑 -->
		<scroll-view v-else-if="safeData.style === 'scroll'" scroll-x class="scroll-layout">
			<view class="scroll-track">
				<view class="shop-card scroll-card" v-for="(it, idx) in list" :key="idx" @click="goShop(it)">
					<view class="cover-wrap">
						<image v-if="it.logo" :src="it.logo" mode="aspectFill" class="cover" />
						<view v-else class="cover cover-placeholder"><tui-icon name="shop" :size="40" color="#94A3B8"></tui-icon></view>
					</view>
					<view class="card-body">
						<text class="name">{{ it.name }}</text>
						<view class="meta" v-if="safeData.show_rating == 1 && it.rating > 0">
							<tui-icon name="star-fill" :size="20" unit="rpx" color="#F59E0B"></tui-icon>
							<text class="score">{{ it.rating.toFixed(1) }}</text>
						</view>
					</view>
				</view>
			</view>
		</scroll-view>

		<!-- 列表大图 -->
		<view v-else class="list-layout">
			<view class="shop-row" v-for="(it, idx) in list" :key="idx" @click="goShop(it)">
				<image v-if="it.logo" :src="it.logo" mode="aspectFill" class="cover" />
				<view v-else class="cover cover-placeholder"><tui-icon name="shop" :size="48" color="#94A3B8"></tui-icon></view>
				<view class="info">
					<text class="name">{{ it.name }}</text>
					<view class="meta" v-if="safeData.show_rating == 1 && it.rating > 0">
						<tui-icon name="star-fill" :size="22" unit="rpx" color="#F59E0B"></tui-icon>
						<text class="score">{{ it.rating.toFixed(1) }} 分</text>
					</view>
					<text class="addr" v-if="safeData.show_address == 1 && it.address">{{ it.address }}</text>
				</view>
				<view class="enter-btn">进店</view>
			</view>
		</view>
	 </view>
	</view>
</template>

<script>
export default {
	name: 'TuiMchSelectShops',
	props: { value: { type: Object, default: () => ({}) } },
	data() { return { list: [], loading: true } },
	computed: {
		safeData() {
			return Object.assign({
				title: '精选商家',
				subtitle: '',
				mch_ids: [],
				show_rating: 1,
				show_address: 1,
				show_more: 0,
				style: 'grid',
				is_padding: 1,
				bg_color: '',
				title_color: '',
				item_bg_color: '',
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
			return parts.join(';')
		},
	},
	created() { this.fetchData() },
	methods: {
		fetchData() {
			const ids = Array.isArray(this.safeData.mch_ids) ? this.safeData.mch_ids : []
			if (!ids.length) { this.loading = false; this.list = []; return }
			this.$request({
				url: '/plugin/mch/api/index/diy-select-shops',
				data: { mch_ids: ids.join(',') },
			}).then(res => {
				this.loading = false
				if (res.code === 0) {
					this.list = res.data.list || []
				}
			}).catch(() => { this.loading = false })
		},
		goShop(it) {
			uni.navigateTo({ url: `/plugins1/mch/index?id=${it.id}` })
		},
		goMore() {
			uni.navigateTo({ url: '/plugins1/mch/shop-list' })
		},
	},
}
</script>

<style lang="scss" scoped>
.diy-mch-select-shops-outer { margin-bottom: 20rpx; }
.diy-mch-select-shops {
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

/* 网格 — CSS Grid 强制 2 列 */
.grid-layout {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 16rpx;
	.shop-card {
		min-width: 0;
		background: var(--c-item-bg, #F7F9FC);
		border-radius: 16rpx;
		overflow: hidden;
		&:active { transform: scale(0.98); opacity: 0.96; }
	}
	.cover-wrap {
		width: 100%;
		height: 200rpx;
		background: linear-gradient(135deg, #E0ECFC 0%, #C7E0FF 100%);
	}
	.cover { width: 100%; height: 100%; }
	.cover-placeholder { display: flex; align-items: center; justify-content: center; }
	.card-body { padding: 16rpx; overflow: hidden; }
	.name {
		display: block;
		font-size: 26rpx; font-weight: 700; color: var(--c-title, #0F172A);
		overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
	}
	.meta { display: flex; align-items: center; margin-top: 6rpx; .score { font-size: 22rpx; color: #F59E0B; margin-left: 4rpx; font-weight: 700; } }
	.addr {
		display: block;
		font-size: 20rpx; color: #94A3B8; margin-top: 4rpx;
		overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
	}
}

/* 横滑 */
.scroll-layout {
	white-space: nowrap;
	.scroll-track { display: inline-flex; gap: 16rpx; padding-bottom: 8rpx; }
	.scroll-card {
		display: inline-block;
		width: 220rpx;
		background: var(--c-item-bg, #F7F9FC);
		border-radius: 16rpx;
		overflow: hidden;
		.cover-wrap { height: 170rpx; background: linear-gradient(135deg, #E0ECFC 0%, #C7E0FF 100%); }
		.cover { width: 100%; height: 100%; }
		.cover-placeholder { display: flex; align-items: center; justify-content: center; }
		.card-body { padding: 12rpx 16rpx; }
		.name {
			display: block;
			font-size: 24rpx; font-weight: 700; color: var(--c-title, #0F172A);
			overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
		}
		.meta { display: flex; align-items: center; margin-top: 6rpx; .score { font-size: 20rpx; color: #F59E0B; margin-left: 4rpx; font-weight: 700; } }
	}
}

/* 列表 */
.list-layout {
	.shop-row {
		display: flex;
		align-items: center;
		padding: 20rpx;
		background: var(--c-item-bg, #F8FAFC);
		border-radius: 16rpx;
		margin-bottom: 16rpx;
		&:last-child { margin-bottom: 0; }
		&:active { transform: scale(0.99); opacity: 0.96; }

		.cover {
			width: 150rpx; height: 150rpx;
			background: linear-gradient(135deg, #E0ECFC 0%, #C7E0FF 100%);
			border-radius: 12rpx;
			flex-shrink: 0;
		}
		.cover-placeholder { display: flex; align-items: center; justify-content: center; }
		.info {
			flex: 1; min-width: 0;
			margin-left: 20rpx;
		}
		.name {
			font-size: 28rpx; font-weight: 700; color: var(--c-title, #0F172A);
			overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
		}
		.meta { display: flex; align-items: center; margin-top: 8rpx; .score { font-size: 22rpx; color: #F59E0B; margin-left: 4rpx; font-weight: 700; } }
		.addr {
			display: block;
			font-size: 22rpx; color: #94A3B8; margin-top: 6rpx;
			overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
		}
		.enter-btn {
			flex-shrink: 0; margin-left: 16rpx;
			background: linear-gradient(135deg, var(--theme-color) 0%, var(--theme-color-light, #3a6fd8) 100%);
			color: #fff;
			font-size: 24rpx;
			padding: 10rpx 26rpx;
			border-radius: 30rpx;
			font-weight: 700;
		}
	}
}
</style>
