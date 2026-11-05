<template>
	<view class="diy-massage-hot-service-outer" :style="outerStyle" v-if="list.length > 0">
	 <view class="diy-massage-hot-service" :style="styleVars">
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
			<view class="svc-card" v-for="(it, idx) in list" :key="idx" @click="goDetail(it)">
				<view class="cover-wrap">
					<image v-if="it.cover" :src="it.cover" mode="aspectFill" class="cover" />
					<view v-else class="cover cover-placeholder"><tui-icon name="image" :size="40" color="#CBD5E1"></tui-icon></view>
					<view class="hot-badge" v-if="idx === 0">🔥 热卖</view>
				</view>
				<view class="card-body">
					<text class="name">{{ it.name }}</text>
					<text class="meta">{{ it.duration }} 分钟</text>
					<view class="bottom">
						<view class="price" v-if="safeData.show_price == 1">
							<text class="symbol">¥</text>
							<text class="num">{{ it.price.toFixed(0) }}</text><text v-if="depositRate > 0 && depositRate < 1" class="hs-deposit">预约金¥{{ depositOf(it.price) }}</text>
						</view>
						<text class="sales" v-if="safeData.show_sales == 1 && it.sales > 0">已售 {{ it.sales }}</text>
					</view>
				</view>
			</view>
		</view>

		<!-- 横滑 -->
		<scroll-view v-else-if="safeData.style === 'scroll'" scroll-x class="scroll-layout">
			<view class="scroll-track">
				<view class="svc-card scroll-card" v-for="(it, idx) in list" :key="idx" @click="goDetail(it)">
					<view class="cover-wrap">
						<image v-if="it.cover" :src="it.cover" mode="aspectFill" class="cover" />
						<view v-else class="cover cover-placeholder"><tui-icon name="image" :size="32" color="#CBD5E1"></tui-icon></view>
					</view>
					<view class="card-body">
						<text class="name">{{ it.name }}</text>
						<view class="bottom" v-if="safeData.show_price == 1">
							<view class="price">
								<text class="symbol">¥</text>
								<text class="num">{{ it.price.toFixed(0) }}</text><text v-if="depositRate > 0 && depositRate < 1" class="hs-deposit">预约金¥{{ depositOf(it.price) }}</text>
							</view>
						</view>
					</view>
				</view>
			</view>
		</scroll-view>

		<!-- 列表大图 -->
		<view v-else class="list-layout">
			<view class="svc-row" v-for="(it, idx) in list" :key="idx" @click="goDetail(it)">
				<image v-if="it.cover" :src="it.cover" mode="aspectFill" class="cover" />
				<view v-else class="cover cover-placeholder"><tui-icon name="image" :size="40" color="#CBD5E1"></tui-icon></view>
				<view class="info">
					<text class="name">{{ it.name }}</text>
					<view class="meta">
						<text>{{ it.duration }} 分钟</text>
						<text v-if="safeData.show_sales == 1 && it.sales > 0"> · 已售 {{ it.sales }}</text>
					</view>
					<view class="bottom">
						<view class="price" v-if="safeData.show_price == 1">
							<text class="symbol">¥</text>
							<text class="num">{{ it.price.toFixed(0) }}</text><text v-if="depositRate > 0 && depositRate < 1" class="hs-deposit">预约金¥{{ depositOf(it.price) }}</text>
						</view>
						<view class="book-btn">立即预约</view>
					</view>
				</view>
			</view>
		</view>
	 </view>
	</view>
</template>

<script>
export default {
	name: 'TuiMassageHotService',
	props: { value: { type: Object, default: () => ({}) } },
	data() { return { list: [], loading: true, depositRate: 1 } },
	computed: {
		safeData() {
			return Object.assign({
				title: '热门服务',
				subtitle: '',
				limit: 6,
				show_sales: 1,
				show_price: 1,
				show_more: 1,
				style: 'grid',
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
	created() { this.fetchData(); this.fetchDepositRate() },
	methods: {
		depositOf(price) { return (Math.round(Number(price || 0) * this.depositRate * 100) / 100).toFixed(0) },
		fetchDepositRate() {
			this.$request({ url: '/plugin/massage/api/index/features' }).then(r => {
				if (r.code === 0 && r.data) {
					const rate = Number(r.data.deposit_rate)
					this.depositRate = (rate > 0 && rate < 1) ? rate : 1
				}
			}).catch(() => {})
		},
		fetchData() {
			const limit = parseInt(this.safeData.limit) || 6
			this.$request({
				url: '/plugin/massage/api/index/diy-hot-service',
				data: { limit },
			}).then(res => {
				this.loading = false
				if (res.code === 0) {
					this.list = (res.data.list || []).slice(0, limit)
				}
			}).catch(() => { this.loading = false })
		},
		goDetail(it) {
			// service-detail 页面通过 goods_id 拉详情（onLoad 读 opts.goods_id），
			// 之前传的是 id=service_id 导致页面 "参数错误"
			uni.navigateTo({ url: `/plugins2/massage/service-detail?goods_id=${it.goods_id}` })
		},
		goMore() {
			uni.navigateTo({ url: '/plugins2/massage/service-list' })
		},
	},
}
</script>

<style lang="scss" scoped>
.diy-massage-hot-service-outer {
	margin-bottom: 20rpx;
}
.diy-massage-hot-service {
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

/* 网格 — 用 CSS Grid 强制 2 列,避免 flex-wrap 在窄容器下塌成一列 */
.grid-layout {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 16rpx;
	.svc-card {
		min-width: 0;          /* 关键:允许 grid item 收缩 */
		background: var(--c-item-bg, #FFF7ED);
		border-radius: 16rpx;
		overflow: hidden;
		&:active { transform: scale(0.98); opacity: 0.96; }
	}
	.cover-wrap {
		position: relative;
		width: 100%;
		height: 220rpx;
		background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
	}
	.cover { width: 100%; height: 100%; }
	.cover-placeholder { display: flex; align-items: center; justify-content: center; }
	.hot-badge {
		position: absolute;
		top: 12rpx; left: 12rpx;
		background: #EF4444;
		color: #fff;
		font-size: 20rpx;
		padding: 4rpx 12rpx;
		border-radius: 6rpx;
		font-weight: 700;
	}
	.card-body { padding: 16rpx; overflow: hidden; }
	.name {
		display: block;
		font-size: 26rpx; font-weight: 700; color: var(--c-title, #0F172A);
		overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
	}
	.meta {
		display: block;
		font-size: 20rpx; color: #94A3B8; margin-top: 4rpx;
		overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
	}
	.bottom {
		display: flex; justify-content: space-between; align-items: baseline;
		margin-top: 8rpx;
		gap: 8rpx;
		flex-wrap: wrap;
	}
	.price { color: var(--theme-color); min-width: 0; }
	.symbol { font-size: 20rpx; font-weight: 700; }
	.num { font-size: 32rpx; font-weight: 800; font-family: 'DIN Alternate', sans-serif; }
	.sales {
		font-size: 20rpx; color: #94A3B8;
		flex-shrink: 0;
	}
}

/* 横滑 */
.scroll-layout {
	white-space: nowrap;
	.scroll-track {
		display: inline-flex;
		gap: 16rpx;
		padding-bottom: 8rpx;
	}
	.scroll-card {
		display: inline-block;
		width: 220rpx;
		background: var(--c-item-bg, #FFF7ED);
		border-radius: 16rpx;
		overflow: hidden;
		.cover-wrap {
			height: 180rpx;
			background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
		}
		.cover { width: 100%; height: 100%; }
		.cover-placeholder { display: flex; align-items: center; justify-content: center; }
		.card-body { padding: 12rpx 16rpx; }
		.name {
			display: block;
			font-size: 24rpx; font-weight: 700; color: var(--c-title, #0F172A);
			overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
		}
		.bottom { margin-top: 6rpx; }
		.price { color: var(--c-accent, var(--theme-color)); }
		.symbol { font-size: 18rpx; font-weight: 700; }
		.num { font-size: 28rpx; font-weight: 800; font-family: 'DIN Alternate', sans-serif; }
	}
}

/* 列表 */
.list-layout {
	.svc-row {
		display: flex;
		padding: 20rpx;
		background: var(--c-item-bg, #F8FAFC);
		border-radius: 16rpx;
		margin-bottom: 16rpx;
		&:last-child { margin-bottom: 0; }
		&:active { transform: scale(0.99); opacity: 0.96; }

		.cover {
			width: 160rpx; height: 160rpx;
			background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
			border-radius: 12rpx;
			flex-shrink: 0;
		}
		.cover-placeholder { display: flex; align-items: center; justify-content: center; }
		.info {
			flex: 1; min-width: 0;
			margin-left: 20rpx;
			display: flex; flex-direction: column; justify-content: space-between;
		}
		.name {
			font-size: 28rpx; font-weight: 700; color: var(--c-title, #0F172A);
			overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
		}
		.meta { font-size: 22rpx; color: #94A3B8; }
		.bottom { display: flex; justify-content: space-between; align-items: center; }
		.price { color: var(--c-accent, var(--theme-color)); }
		.symbol { font-size: 22rpx; font-weight: 700; }
		.num { font-size: 36rpx; font-weight: 800; font-family: 'DIN Alternate', sans-serif; }
		.book-btn {
			background: linear-gradient(135deg, var(--theme-color) 0%, var(--theme-color-light, #f5b14a) 100%);
			color: #fff;
			font-size: 24rpx;
			padding: 8rpx 24rpx;
			border-radius: 30rpx;
			font-weight: 700;
		}
	}
}
.hs-deposit {
	margin-left: 8rpx;
	font-size: 18rpx;
	font-weight: 700;
	color: #B45309;
}
</style>
