<template>
	<view class="diy-massage-new-therapist-outer" :style="outerStyle" v-if="list.length > 0">
	 <view class="diy-massage-new-therapist" :style="styleVars">
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

		<!-- 横滑卡片 -->
		<scroll-view v-if="safeData.style === 'scroll'" scroll-x class="scroll-layout">
			<view class="scroll-track">
				<view class="th-card scroll-card" v-for="(t, idx) in list" :key="idx" @click="goDetail(t)">
					<view class="avatar-wrap">
						<image v-if="t.avatar" :src="t.avatar" class="avatar" mode="aspectFill" />
						<view v-else class="avatar avatar-placeholder">
							<tui-icon name="people" :size="36" color="#fff"></tui-icon>
						</view>
						<view v-if="safeData.show_new_tag == 1 && isFresh(idx, t)" class="new-tag">NEW</view>
					</view>
					<text class="name">{{ maskName(t.name) }}</text>
					<text class="cert">{{ t.cert_type_name }}</text>
					<view class="meta" v-if="safeData.show_rating == 1 && t.rating > 0">
						<tui-icon name="star-fill" :size="18" unit="rpx" color="#F59E0B"></tui-icon>
						<text>{{ t.rating.toFixed(1) }}</text>
					</view>
					<text class="orders" v-if="safeData.show_orders == 1">已服务 {{ t.order_count }} 单</text>
				</view>
			</view>
		</scroll-view>

		<!-- 网格 -->
		<view v-else class="grid-layout">
			<view class="th-card" v-for="(t, idx) in list" :key="idx" @click="goDetail(t)">
				<view class="avatar-wrap">
					<image v-if="t.avatar" :src="t.avatar" class="avatar" mode="aspectFill" />
					<view v-else class="avatar avatar-placeholder">
						<tui-icon name="people" :size="28" color="#fff"></tui-icon>
					</view>
					<view v-if="safeData.show_new_tag == 1 && isFresh(idx, t)" class="new-tag">NEW</view>
				</view>
				<view class="info">
					<view class="name-row">
						<text class="name">{{ maskName(t.name) }}</text>
						<text class="cert-tag" v-if="t.cert_type_name && t.cert_type_name !== '-'">证</text>
					</view>
					<text class="cert">{{ t.cert_type_name }}</text>
					<view class="bottom">
						<view class="rating" v-if="safeData.show_rating == 1 && t.rating > 0">
							<tui-icon name="star-fill" :size="18" unit="rpx" color="#F59E0B"></tui-icon>
							<text>{{ t.rating.toFixed(1) }}</text>
						</view>
						<text class="orders" v-if="safeData.show_orders == 1">{{ t.order_count }} 单</text>
					</view>
				</view>
			</view>
		</view>
	 </view>
	</view>
</template>

<script>
const FRESH_THRESHOLD_DAYS = 30

export default {
	name: 'TuiMassageNewTherapist',
	props: { value: { type: Object, default: () => ({}) } },
	data() { return { list: [], loading: true } },
	computed: {
		safeData() {
			return Object.assign({
				title: '新晋技师',
				subtitle: '',
				limit: 6,
				show_rating: 1,
				show_orders: 1,
				show_more: 1,
				show_new_tag: 0,            // NEW 标签：默认关闭（容易遮头像）
				style: 'scroll',
				is_padding: 1,
				// —— 配色（后台可改），空串走 SCSS 默认
				bg_color: '',               // 卡片容器背景
				title_color: '',            // 标题文字
				item_bg_color: '',          // 单卡片背景
				accent_color: '',           // 强调色（认证标签 / NEW 标签底色）
			}, this.value || {})
		},
		outerStyle() {
			return `padding:${this.safeData.is_padding == 1 ? '0 20rpx' : '0'};`
		},
		/** 把后台配置的颜色映射为 CSS 变量，SCSS 内部用 var(--xxx, fallback) 引用 */
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
	created() { this.fetchData() },
	methods: {
		/** 隐私脱敏:姓名第 2 字符替换为 *。1 字不动 / 2 字「张三→张*」/ 3 字+「张三丰→张*丰」 */
		maskName(name) {
			if (!name) return ''
			const chars = Array.from(String(name))
			if (chars.length < 2) return name
			chars[1] = '*'
			return chars.join('')
		},
		fetchData() {
			const limit = parseInt(this.safeData.limit) || 6
			this.$request({
				url: '/plugin/massage/api/index/diy-new-therapist',
				data: { limit },
			}).then(res => {
				this.loading = false
				if (res.code === 0) {
					this.list = (res.data.list || []).slice(0, limit)
				}
			}).catch(() => { this.loading = false })
		},
		/** 30 天内入驻 → 显示 NEW 标签（前 2 个永远是 NEW 也行，更简单） */
		isFresh(idx, t) {
			if (idx < 2) return true
			if (!t.join_time) return false
			const days = (Date.now() / 1000 - t.join_time) / 86400
			return days <= FRESH_THRESHOLD_DAYS
		},
		goDetail(t) {
			uni.navigateTo({ url: `/plugins2/massage/therapist-detail?id=${t.id}` })
		},
		goMore() {
			uni.navigateTo({ url: '/plugins2/massage/therapist-list' })
		},
	},
}
</script>

<style lang="scss" scoped>
.diy-massage-new-therapist-outer {
	margin-bottom: 20rpx;
}
.diy-massage-new-therapist {
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

/* 横滑 */
.scroll-layout {
	white-space: nowrap;
	.scroll-track {
		display: inline-flex;
		gap: 20rpx;
		padding: 8rpx 0;
	}
	.scroll-card {
		display: inline-flex;
		flex-direction: column;
		align-items: center;
		width: 180rpx;
		background: var(--c-item-bg, #F8FAFC);
		border-radius: 16rpx;
		padding: 24rpx 12rpx;
		text-align: center;
		.avatar-wrap {
			position: relative;
			width: 100rpx; height: 100rpx;
			margin-bottom: 14rpx;
		}
		.avatar {
			width: 100%; height: 100%;
			border-radius: 50%;
			background: linear-gradient(135deg, #C7D2FE 0%, #818CF8 100%);
		}
		.avatar-placeholder { display: flex; align-items: center; justify-content: center; }
		/* 缩小版 NEW 标签：尺寸更小、贴右上但完全在头像外侧（top:-2rpx, right:-4rpx 偏移更克制） */
		.new-tag {
			position: absolute;
			top: -2rpx; right: -4rpx;
			background: var(--c-accent, #EF4444);
			color: #fff;
			font-size: 16rpx;
			line-height: 1;
			font-weight: 800;
			padding: 3rpx 8rpx;
			border-radius: 16rpx;
			border: 2rpx solid #fff;
		}
		.name { display: block; font-size: 26rpx; font-weight: 700; color: var(--c-title, #0F172A); }
		.cert {
			display: inline-block;
			font-size: 18rpx;
			color: var(--c-accent, #6366F1);
			background: #EEF2FF;
			padding: 2rpx 12rpx;
			border-radius: 6rpx;
			margin-top: 6rpx;
		}
		.meta {
			display: inline-flex;
			align-items: center;
			gap: 4rpx;
			font-size: 22rpx;
			color: #475569;
			margin-top: 8rpx;
		}
		.orders { display: block; font-size: 18rpx; color: #94A3B8; margin-top: 4rpx; }
	}
}

/* 网格 — 用 CSS Grid 强制 2 列,避免 flex-wrap 在窄容器下塌成一列 */
.grid-layout {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 16rpx;
	.th-card {
		box-sizing: border-box;
		min-width: 0;          /* 关键:允许 grid item 收缩到小于内容自然宽 */
		overflow: hidden;
		display: flex;
		background: var(--c-item-bg, #F8FAFC);
		border-radius: 16rpx;
		padding: 20rpx 16rpx;  /* 横向 padding 减小,腾出文本空间 */
		gap: 14rpx;
		&:active { transform: scale(0.98); opacity: 0.96; }
		.avatar-wrap {
			position: relative;
			width: 88rpx; height: 88rpx;
			flex-shrink: 0;
		}
		.avatar {
			width: 100%; height: 100%;
			border-radius: 50%;
			background: linear-gradient(135deg, #C7D2FE 0%, #818CF8 100%);
		}
		.avatar-placeholder { display: flex; align-items: center; justify-content: center; }
		/* 缩小版 NEW 标签 */
		.new-tag {
			position: absolute;
			top: -2rpx; right: -4rpx;
			background: var(--c-accent, #EF4444);
			color: #fff;
			font-size: 16rpx;
			line-height: 1;
			font-weight: 800;
			padding: 3rpx 8rpx;
			border-radius: 16rpx;
			border: 2rpx solid #fff;
		}
		.info {
			flex: 1; min-width: 0;
			display: flex; flex-direction: column;
			overflow: hidden;
		}
		.name-row {
			display: flex; align-items: center; gap: 6rpx;
			.name {
				flex: 1; min-width: 0;
				font-size: 26rpx; font-weight: 700; color: var(--c-title, #0F172A);
				overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
			}
			.cert-tag {
				font-size: 18rpx;
				background: var(--c-accent, #6366F1);
				color: #fff;
				padding: 0 8rpx;
				border-radius: 6rpx;
				flex-shrink: 0;
			}
		}
		.cert {
			display: inline-block;
			align-self: flex-start;
			max-width: 100%;
			font-size: 18rpx;
			color: var(--c-accent, #6366F1);
			background: #EEF2FF;
			padding: 2rpx 10rpx;
			border-radius: 6rpx;
			margin-top: 6rpx;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		.bottom {
			display: flex;
			align-items: center;
			gap: 12rpx;
			font-size: 22rpx;
			color: #475569;
			margin-top: 8rpx;
			flex-wrap: wrap;
			.rating {
				display: inline-flex;
				align-items: center;
				gap: 4rpx;
			}
			.orders { color: #94A3B8; font-size: 20rpx; }
		}
	}
}
</style>
