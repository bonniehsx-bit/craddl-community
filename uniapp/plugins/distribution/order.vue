<template>
	<!-- 佣金明细:状态切换 + 按月筛选,卡片样式和分销中心 / 代理中心首页的「最近佣金」一致 -->
	<tui-page :title="$noun('commission') + '明细'">
		<view class="dist-order" :style="themeStyle">
			<view class="bar">
				<view class="tabs">
					<view v-for="t in tabs" :key="t.value" class="tab" :class="{ active: status === t.value }" @click="switchTab(t.value)">{{ t.label }}</view>
				</view>
				<picker mode="date" fields="month" :value="month || thisMonth" @change="onMonth">
					<view class="month" :class="{ on: !!month }">
						<tui-icon name="calendar" :size="15" :color="month ? 'var(--theme-color)' : '#86909c'"></tui-icon>
						<text>{{ month ? monthText : '全部月份' }}</text>
					</view>
				</picker>
			</view>
			<view v-if="month" class="month-clear" @click="clearMonth">只看 {{ monthText }} · 点这里看全部</view>

			<view class="list">
				<view v-for="r in list" :key="r.id" class="item">
					<view class="item-main">
						<image class="goods-pic" :src="thumbSrc(r.goods_pic)" mode="aspectFill" @error="thumbFail(r.goods_pic)"></image>
						<view class="item-info">
							<view class="goods-name">{{ r.goods_name || ('订单 ' + r.order_no) }}</view>
							<view class="item-meta">{{ levelText(r.level) }} {{ maskName(r.user_nickname) }} · 下单 ¥{{ money(r.order_amount) }}</view>
						</view>
						<view class="item-right">
							<view class="amount" :class="{ invalid: r.status === 2 }">{{ r.status === 2 ? '' : '+' }}{{ money(r.commission) }}</view>
							<text class="pill" :class="'pill-' + r.status">{{ r.status_text }}</text>
						</view>
					</view>
					<view class="item-foot">
						<text class="order-no">订单号 {{ r.order_no }}</text>
						<text>{{ fullTime(r.created_at) }}</text>
					</view>
				</view>

				<view v-if="loaded && !list.length" class="empty">
					<tui-icon name="nodata" :size="56" color="#c9cdd4"></tui-icon>
					<view class="empty-text">{{ month ? monthText + '没有' : '还没有' }}{{ tabLabel === '全部' ? '' : tabLabel }}{{ $noun('commission') }}记录</view>
				</view>
				<view v-if="list.length" class="more">{{ loading ? '加载中…' : (noMore ? '没有更多了' : '上拉加载更多') }}</view>
			</view>
		</view>
	</tui-page>
</template>

<script>
	import { thumbMixin } from '@/utils/thumb.js';

	export default {
		mixins: [thumbMixin],
		data() {
			return {
				tabs: [
					{ label: '全部', value: -1 },
					{ label: '待结算', value: 0 },
					{ label: '已结算', value: 1 },
					{ label: '已失效', value: 2 }
				],
				status: -1, // -1 全部 / 0 待结算 / 1 已结算 / 2 已失效
				month: '', // YYYY-MM,空 = 全部
				list: [],
				page: 1,
				loading: false,
				noMore: false,
				loaded: false
			};
		},
		computed: {
			tabLabel() {
				const t = this.tabs.find(x => x.value === this.status);
				return t ? t.label : '';
			},
			thisMonth() {
				const d = new Date();
				return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
			},
			monthText() {
				const [y, m] = this.month.split('-');
				return y + '年' + Number(m) + '月';
			}
		},
		onLoad(q) {
			if (q && q.status !== undefined && [0, 1, 2].indexOf(Number(q.status)) !== -1) this.status = Number(q.status);
			this.reload();
		},
		onPullDownRefresh() {
			this.reload().finally(() => uni.stopPullDownRefresh());
		},
		onReachBottom() {
			this.load();
		},
		methods: {
			switchTab(v) {
				if (this.status === v) return;
				this.status = v;
				this.reload();
			},
			onMonth(e) {
				this.month = e.detail.value;
				this.reload();
			},
			clearMonth() {
				this.month = '';
				this.reload();
			},
			reload() {
				this.page = 1;
				this.noMore = false;
				this.list = [];
				this.loaded = false;
				return this.load(true);
			},
			load(force) {
				if (!force && (this.loading || this.noMore)) return Promise.resolve();
				this.loading = true;
				const status = this.status;
				const month = this.month;
				return this.$request({
					url: '/plugin/distribution/api/distribution/index',
					data: { page: this.page, limit: 15, status, month }
				}).then(res => {
					// 切了 tab / 月份后,旧请求晚回来就丢掉
					if (status !== this.status || month !== this.month) return;
					if (res.code !== 0) {
						if (res.code !== -1) this.$util.toast(res.msg);
						return;
					}
					const d = res.data || {};
					const rows = d.list || [];
					this.list = this.list.concat(rows);
					const p = d.pagination || {};
					if (!rows.length || !p.page_count || this.page >= p.page_count) {
						this.noMore = true;
					} else {
						this.page++;
					}
				}).finally(() => {
					this.loading = false;
					this.loaded = true;
				});
			},
			levelText(level) {
				return Number(level) <= 1 ? '客户' : (level + '级客户');
			},
			money(v) {
				return Number(v || 0).toFixed(2);
			},
			maskName(name) {
				const s = String(name || '');
				return s ? s.slice(0, 1) + '**' : '';
			},
			fullTime(ts) {
				if (!ts) return '';
				const d = new Date(ts * 1000);
				const p = n => String(n).padStart(2, '0');
				return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
			}
		}
	};
</script>

<style lang="scss" scoped>
	.dist-order {
		min-height: 100vh;
		background: #f5f6f8;
		padding-bottom: 40rpx;
	}

	/* 顶部:状态切换 + 月份,吸在标题栏下面 */
	.bar {
		position: sticky;
		top: var(--tui-nav-height, 0px); /* 吸在自绘标题栏下面,别钻到标题栏底下 */
		z-index: 10;
		display: flex;
		align-items: center;
		padding: 0 24rpx 0 8rpx;
		background: #ffffff;
		border-bottom-left-radius: 24rpx;
		border-bottom-right-radius: 24rpx;
		box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.04);
	}

	.tabs {
		flex: 1;
		display: flex;
	}

	.tab {
		flex: 1;
		position: relative;
		height: 92rpx;
		line-height: 92rpx;
		text-align: center;
		font-size: 27rpx;
		color: #4e5969;
	}

	.tab.active {
		color: var(--theme-color);
		font-weight: 700;
	}

	.tab.active::after {
		content: '';
		position: absolute;
		left: 50%;
		bottom: 14rpx;
		width: 40rpx;
		height: 6rpx;
		margin-left: -20rpx;
		border-radius: 3rpx;
		background: var(--theme-color);
	}

	.month {
		display: flex;
		align-items: center;
		height: 56rpx;
		padding: 0 18rpx;
		margin-left: 8rpx;
		border-radius: 28rpx;
		font-size: 23rpx;
		color: #86909c;
		background: #f5f6f8;

		text {
			margin-left: 6rpx;
		}
	}

	.month.on {
		color: var(--theme-color);
		background: var(--theme-color-bg);
	}

	.month-clear {
		margin: 20rpx 28rpx 0;
		font-size: 23rpx;
		color: #86909c;
	}

	.list {
		padding: 4rpx 28rpx 0;
	}

	.item {
		margin-top: 20rpx;
		padding: 26rpx 28rpx 20rpx;
		border-radius: 24rpx;
		background: #ffffff;
	}

	.item-main {
		display: flex;
		align-items: center;
	}

	.goods-pic {
		width: 88rpx;
		height: 88rpx;
		border-radius: 16rpx;
		background: #f2f3f5;
		flex-shrink: 0;
	}

	.item-info {
		flex: 1;
		min-width: 0;
		margin: 0 20rpx;
	}

	.goods-name {
		font-size: 27rpx;
		color: #1f2329;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.item-meta {
		margin-top: 8rpx;
		font-size: 22rpx;
		color: #86909c;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.item-right {
		text-align: right;
		flex-shrink: 0;
	}

	.amount {
		font-size: 32rpx;
		font-weight: 600;
		color: var(--theme-color);
		font-family: "DIN Alternate", "Helvetica Neue", sans-serif;
	}

	.amount.invalid {
		color: #c0c4cc;
		text-decoration: line-through;
	}

	.pill {
		display: inline-block;
		margin-top: 6rpx;
		padding: 2rpx 12rpx;
		border-radius: 16rpx;
		font-size: 20rpx;
	}

	/* 状态色,不跟主题色(和首页一致) */
	.pill-0 {
		color: #b7791f;
		background: #fff7e6;
	}

	.pill-1 {
		color: #2e7d32;
		background: #eaf6ec;
	}

	.pill-2 {
		color: #909399;
		background: #f2f3f5;
	}

	.item-foot {
		display: flex;
		justify-content: space-between;
		margin-top: 20rpx;
		padding-top: 18rpx;
		border-top: 1rpx solid #f0f1f4;
		font-size: 22rpx;
		color: #a9aeb8;
	}

	.order-no {
		flex: 1;
		min-width: 0;
		margin-right: 16rpx;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.empty {
		padding: 160rpx 0 80rpx;
		display: flex;
		flex-direction: column;
		align-items: center;
	}

	.empty-text {
		margin-top: 20rpx;
		font-size: 26rpx;
		color: #86909c;
	}

	.more {
		padding: 32rpx 0 8rpx;
		text-align: center;
		font-size: 22rpx;
		color: #a9aeb8;
	}
</style>
