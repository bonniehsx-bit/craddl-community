<template>
	<!-- 页面装修「砍价专区」:取正在进行的砍价活动;一个都没有时整块不显示 -->
	<view class="diy-bargain-root" :style="rootStyle" v-if="list.length > 0">
		<!-- 2. 大图主推 + 两列:主题色专区头部(标题 / 标签 / 本场倒计时),第一件做大图,其余两列 -->
		<view v-if="safeData.type == 2" class="zone" :style="zoneStyle">
			<view class="zone-head" v-if="safeData.show_title != 0">
				<view class="zh-glow zh-glow-a"></view>
				<view class="zh-glow zh-glow-b"></view>
				<view class="zh-row">
					<view class="zh-title">
						<text class="zh-name">{{ safeData.title || (bn + '专区') }}</text>
						<text class="zh-tag">邀好友 砍到底价</text>
					</view>
					<view class="zh-more" v-if="safeData.show_more != 0" @click="goMore">
						<text>更多</text>
						<tui-icon name="arrowright" :size="24" unit="rpx" color="#ffffff"></tui-icon>
					</view>
				</view>
				<view class="zh-sub" v-if="clock">
					<text>本场还剩</text>
					<block v-if="clock.d > 0"><text class="cb">{{ clock.d }}</text><text class="cs">天</text></block>
					<text class="cb">{{ clock.h }}</text><text class="cs">:</text>
					<text class="cb">{{ clock.m }}</text><text class="cs">:</text>
					<text class="cb">{{ clock.s }}</text>
				</view>
			</view>

			<view class="lc">
				<!-- 主推:第一件 -->
				<view class="lc-hero" @click="goDetail(hero)">
					<image class="lc-hero-pic" :src="hero.cover_pic" mode="aspectFill"></image>
					<view class="lc-hero-shade"></view>
					<text v-if="maxCut(hero)" class="lc-hero-rib">最多砍 ¥{{ maxCut(hero) }}</text>
					<view class="lc-hero-info">
						<view class="lc-hero-name">{{ hero.name }}</view>
						<view class="lc-hero-row">
							<view class="lc-hero-left">
								<view class="lc-hero-price">
									<text class="lc-hero-sym">底价 ¥</text>
									<text class="lc-hero-num">{{ money(hero.floor_price) }}</text>
									<text v-if="safeData.show_origin != 0" class="lc-hero-ori">¥{{ money(hero.start_price) }}</text>
								</view>
								<view class="lc-hero-meta">
									<block v-if="safeData.show_success != 0 && hero.success_count > 0">
										<view class="avs" v-if="safeData.show_avatar != 0">
											<block v-for="(a, ai) in hero.success_avatars" :key="ai">
												<image v-if="a" class="av" :src="a" mode="aspectFill"></image>
												<view v-else class="av" :style="'background:' + avColor(ai)"></view>
											</block>
										</view>
										<text>已有 {{ hero.success_count }} 人砍成</text>
									</block>
									<text v-else-if="safeData.show_success != 0">刚开始，快来抢第一个砍成</text>
									<text v-if="safeData.show_stock != 0">{{ safeData.show_success != 0 ? ' · ' : '' }}仅剩 {{ hero.stock }} 件</text>
								</view>
							</view>
							<view class="lc-hero-btn">去{{ bn }}</view>
						</view>
					</view>
				</view>

				<!-- 其余两列 -->
				<view class="lc-grid" v-if="list.length > 1">
					<view class="lc-card" v-for="(g, index) in rest" :key="g.id" @click="goDetail(g)">
						<view class="lc-cover">
							<image class="lc-pic" :src="thumbSrc(g.cover_pic)" mode="aspectFill" @error="thumbFail(g.cover_pic)"></image>
							<text v-if="maxCut(g)" class="lc-rib">砍 ¥{{ maxCut(g) }}</text>
						</view>
						<view class="lc-body">
							<view class="lc-name">{{ g.name }}</view>
							<view class="lc-row">
								<view class="price lc-price">
									<text class="sym">¥</text>
									<text class="floor small">{{ money(g.floor_price) }}</text>
									<text v-if="safeData.show_origin != 0" class="ori">¥{{ money(g.start_price) }}</text>
								</view>
								<view class="btn small">去砍</view>
							</view>
						</view>
					</view>
				</view>
			</view>
		</view>

		<view v-else class="group-inner" :style="innerStyle">

			<view class="group-header" v-if="safeData.show_title != 0">
				<text class="title">{{ safeData.title || (bn + '专区') }}</text>
				<view class="more" v-if="safeData.show_more != 0" @click="goMore">
					<text>更多</text>
					<tui-icon name="arrowright" :size="24" unit="rpx" color="#999999"></tui-icon>
				</view>
			</view>

			<!-- 0. 列表:左图右文,和砍价专区页同一套样式 -->
			<view v-if="safeData.type == 0 || !safeData.type">
				<view class="list-item" v-for="(g, index) in list" :key="g.id" @click="goDetail(g)">
					<image class="list-pic" :src="thumbSrc(g.cover_pic)" mode="aspectFill" @error="thumbFail(g.cover_pic)"></image>
					<view class="list-info">
						<view class="name">{{ g.name }}</view>
						<view class="price">
							<text class="floor-label">底价</text>
							<text class="sym">¥</text>
							<text class="floor">{{ g.floor_price }}</text>
							<text v-if="safeData.show_origin != 0" class="ori">¥{{ g.start_price }}</text>
						</view>
						<view class="foot">
							<view class="meta">
								<text v-if="safeData.show_success != 0 && g.success_count > 0">已有 {{ g.success_count }} 人砍成</text>
								<text v-if="safeData.show_stock != 0">仅剩 {{ g.stock }} 件</text>
							</view>
							<view class="btn">去{{ bn }}</view>
						</view>
					</view>
				</view>
			</view>

			<!-- 1. 两列网格 -->
			<view v-else class="grid">
				<view class="grid-item" v-for="(g, index) in list" :key="g.id" @click="goDetail(g)">
					<view class="grid-cover">
						<image class="grid-pic" :src="thumbSrc(g.cover_pic)" mode="aspectFill" @error="thumbFail(g.cover_pic)"></image>
						<text v-if="safeData.show_success != 0 && g.success_count > 0" class="badge">{{ g.success_count }}人砍成</text>
					</view>
					<view class="grid-info">
						<view class="name">{{ g.name }}</view>
						<view class="price">
							<text class="floor-label">底价</text>
							<text class="sym">¥</text>
							<text class="floor small">{{ g.floor_price }}</text>
						</view>
						<view class="grid-foot">
							<text v-if="safeData.show_origin != 0" class="ori first">¥{{ g.start_price }}</text>
							<view class="btn small">去{{ bn }}</view>
						</view>
					</view>
				</view>
			</view>

		</view>
	</view>
</template>

<script>
	import { thumbMixin } from '@/utils/thumb.js';

	export default {
		name: 'TuiBargainGroup',
		// 商品图走缩略图,首页一屏好几张原图太费流量
		mixins: [thumbMixin],
		props: {
			value: {
				type: Object,
				default: () => ({})
			}
		},
		data() {
			return {
				list: [],
				now: 0,
				timer: null
			}
		},
		computed: {
			safeData() {
				return this.value || {};
			},
			// 活动名走词库 bargain.name(商户可改叫「助力价」);没配回落「砍价」。
			// 不 import plugins4/bargain/util.js:首页在主包,不能依赖分包里的文件
			bn() {
				const w = this.$noun ? this.$noun('bargain.name') : '';
				return w && w !== 'bargain.name' ? w : '砍价';
			},
			// 强调色:装修里选了就用,没选跟主题色(--bargain-main 在样式里回落 --theme-color)
			rootStyle() {
				const padding = this.safeData.is_padding == 1 ? '0 20rpx' : '0';
				const main = this.safeData.main_color ? `;--bargain-main:${this.safeData.main_color}` : '';
				return `${this.themeStyle};padding:${padding};margin-bottom:20rpx${main}`;
			},
			// 大图主推:第一件做大图,其余两列
			hero() {
				return this.list[0] || {};
			},
			rest() {
				return this.list.slice(1);
			},
			zoneStyle() {
				const radius = this.safeData.is_circle == 1 ? '32rpx' : '0';
				const bg = this.safeData.background || '#ffffff';
				return `border-radius:${radius};background-color:${bg};overflow:hidden`;
			},
			/** 本场倒计时:列表里最早结束的那个活动 */
			clock() {
				if (this.safeData.show_countdown == 0 || !this.now) return null;
				const ends = this.list.map(g => Number(g.end_at) || 0).filter(t => t > this.now);
				if (!ends.length) return null;
				const sec = Math.min(...ends) - this.now;
				const p = n => (n < 10 ? '0' + n : '' + n);
				return {
					d: Math.floor(sec / 86400),
					h: p(Math.floor((sec % 86400) / 3600)),
					m: p(Math.floor((sec % 3600) / 60)),
					s: p(sec % 60)
				};
			},
			innerStyle() {
				const radius = this.safeData.is_circle == 1 ? '20rpx' : '0';
				const bg = this.safeData.background || '#ffffff';
				return `border-radius:${radius};background-color:${bg};padding:20rpx;overflow:hidden`;
			}
		},
		created() {
			this.fetchData();
		},
		beforeDestroy() {
			this.stopTick();
		},
		methods: {
			fetchData() {
				const limit = parseInt(this.safeData.limit) || 4;
				// 专区列表接口不用登录:没登录的新客也能在首页看到
				this.$request({
					url: '/plugin/bargain/api/bargain/index',
					data: { page: 1, limit: limit }
				}).then(res => {
					if (res.code == 0) {
						this.list = ((res.data && res.data.list) || []).slice(0, limit);
						// 倒计时基准用服务端时间;只有大图主推样式画倒计时
						this.now = (res.data && res.data.now) || Math.floor(Date.now() / 1000);
						if (this.safeData.type == 2 && this.safeData.show_countdown != 0 && this.list.length) this.startTick();
					}
				}).catch(() => {});
			},
			startTick() {
				this.stopTick();
				this.timer = setInterval(() => {
					this.now++;
					// 最早的那场刚结束:重新拉一次,结束的活动从首页消失
					if (this.list.some(g => Number(g.end_at) === this.now)) this.fetchData();
				}, 1000);
			},
			stopTick() {
				if (this.timer) {
					clearInterval(this.timer);
					this.timer = null;
				}
			},
			/** 可砍金额:起砍价 − 底价 */
			maxCut(g) {
				const v = Number(g.start_price) - Number(g.floor_price);
				return v > 0 ? this.money(v) : '';
			},
			/** 价格去掉多余的 .00:168.00 → 168,99.50 → 99.50 */
			money(v) {
				const n = Number(v) || 0;
				return n % 1 === 0 ? String(n) : n.toFixed(2);
			},
			avColor(i) {
				return ['#f7775a', '#5b8def', '#36b37e'][i % 3];
			},
			goDetail(g) {
				uni.navigateTo({ url: '/plugins4/bargain/goods?id=' + g.id });
			},
			goMore() {
				uni.navigateTo({ url: '/plugins4/bargain/index' });
			}
		}
	}
</script>

<style lang="scss" scoped>
	.diy-bargain-root {
		box-sizing: border-box;
		width: 100%;
	}

	.group-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 16rpx;

		.title {
			font-size: 30rpx;
			font-weight: bold;
			color: #222222;
		}

		.more {
			display: flex;
			align-items: center;
			font-size: 24rpx;
			color: #999999;
		}
	}

	.name {
		font-size: 26rpx;
		color: #222222;
		line-height: 1.4;
		overflow: hidden;
		text-overflow: ellipsis;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
	}

	.price {
		display: flex;
		align-items: baseline;
		margin-top: 8rpx;
		color: var(--bargain-main, var(--theme-color));
	}

	.floor-label {
		font-size: 22rpx;
		margin-right: 6rpx;
	}

	.sym {
		font-size: 24rpx;
	}

	.floor {
		font-size: 38rpx;
		font-weight: 700;

		&.small {
			font-size: 32rpx;
		}
	}

	.ori {
		margin-left: 12rpx;
		font-size: 22rpx;
		color: #bbbbbb;
		text-decoration: line-through;

		&.first {
			margin-left: 0;
		}
	}

	.btn {
		flex-shrink: 0;
		padding: 10rpx 26rpx;
		border-radius: 30rpx;
		font-size: 24rpx;
		color: #ffffff;
		background: var(--bargain-main, var(--theme-color));

		&.small {
			padding: 6rpx 18rpx;
			font-size: 22rpx;
		}
	}

	/* 列表 */
	.list-item {
		display: flex;
		padding: 16rpx 0;
	}

	/* 分隔线画在后一条顶上 */
	.list-item + .list-item {
		border-top: 1rpx solid #f3f3f3;
	}

	.list-pic {
		width: 192rpx;
		height: 192rpx;
		border-radius: 12rpx;
		background: #f2f3f5;
		flex-shrink: 0;
	}

	.list-info {
		flex: 1;
		min-width: 0;
		padding-left: 20rpx;
		display: flex;
		flex-direction: column;
		justify-content: space-between;
	}

	.foot {
		display: flex;
		align-items: flex-end;
		justify-content: space-between;
	}

	.meta {
		display: flex;
		flex-direction: column;
		font-size: 22rpx;
		color: #999999;
		line-height: 1.6;
	}

	/* 两列网格 */
	.grid {
		display: flex;
		flex-wrap: wrap;
		justify-content: space-between;
	}

	.grid-item {
		width: calc((100% - 16rpx) / 2);
		margin-bottom: 16rpx;
		border-radius: 16rpx;
		overflow: hidden;
		background: #fafafa;
	}

	.grid-cover {
		position: relative;
		width: 100%;
		height: 0;
		padding-top: 100%;
	}

	.grid-pic {
		position: absolute;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background: #f2f3f5;
	}

	.badge {
		position: absolute;
		left: 12rpx;
		bottom: 12rpx;
		padding: 2rpx 12rpx;
		border-radius: 16rpx;
		font-size: 20rpx;
		color: #ffffff;
		background: rgba(0, 0, 0, 0.45);
	}

	.grid-info {
		padding: 12rpx 16rpx 16rpx;
	}

	.grid-foot {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-top: 8rpx;
	}
	/* ===== 大图主推 + 两列 ===== */
	.zone-head {
		position: relative;
		overflow: hidden;
		padding: 28rpx 28rpx 24rpx;
		color: #ffffff;
		background: linear-gradient(120deg, var(--bargain-main, var(--theme-color)), var(--theme-color-light));
	}

	.zh-glow {
		position: absolute;
		border-radius: 50%;
		background: rgba(255, 255, 255, 0.1);
	}

	.zh-glow-a {
		right: -60rpx;
		top: -80rpx;
		width: 280rpx;
		height: 280rpx;
	}

	.zh-glow-b {
		right: 120rpx;
		bottom: -100rpx;
		width: 180rpx;
		height: 180rpx;
		background: rgba(255, 255, 255, 0.07);
	}

	.zh-row {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.zh-title {
		display: flex;
		align-items: center;
	}

	.zh-name {
		font-size: 38rpx;
		font-weight: 700;
		letter-spacing: 2rpx;
	}

	.zh-tag {
		margin-left: 16rpx;
		padding: 2rpx 14rpx;
		border-radius: 18rpx;
		font-size: 21rpx;
		font-weight: 700;
		color: #7a3b00;
		background: #ffd76a;
	}

	.zh-more {
		display: flex;
		align-items: center;
		font-size: 24rpx;
		opacity: 0.9;
	}

	.zh-sub {
		position: relative;
		margin-top: 12rpx;
		display: flex;
		align-items: center;
		font-size: 23rpx;
	}

	.zh-sub > text:first-child {
		margin-right: 10rpx;
	}

	.cb {
		min-width: 38rpx;
		height: 38rpx;
		line-height: 38rpx;
		padding: 0 6rpx;
		border-radius: 8rpx;
		text-align: center;
		font-size: 23rpx;
		font-weight: 700;
		color: var(--bargain-main, var(--theme-color));
		background: #ffffff;
		font-family: "DIN Alternate", "Helvetica Neue", sans-serif;
	}

	.cs {
		margin: 0 4rpx;
		font-size: 21rpx;
	}

	.lc {
		padding: 24rpx;
	}

	.lc-hero {
		position: relative;
		height: 340rpx;
		border-radius: 28rpx;
		overflow: hidden;
		background: #f2f3f5;
	}

	.lc-hero-pic {
		position: absolute;
		left: 0;
		top: 0;
		width: 100%;
		height: 100%;
	}

	.lc-hero-shade {
		position: absolute;
		left: 0;
		right: 0;
		bottom: 0;
		height: 70%;
		background: linear-gradient(180deg, rgba(0, 0, 0, 0), rgba(0, 0, 0, 0.62));
	}

	.lc-hero-rib {
		position: absolute;
		left: 20rpx;
		top: 20rpx;
		padding: 4rpx 18rpx;
		border-radius: 20rpx;
		font-size: 22rpx;
		font-weight: 700;
		color: #7a3b00;
		background: #ffd76a;
	}

	.lc-hero-info {
		position: absolute;
		left: 24rpx;
		right: 24rpx;
		bottom: 20rpx;
		color: #ffffff;
	}

	.lc-hero-name {
		font-size: 29rpx;
		font-weight: 600;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.lc-hero-row {
		margin-top: 10rpx;
		display: flex;
		align-items: flex-end;
		justify-content: space-between;
	}

	.lc-hero-left {
		flex: 1;
		min-width: 0;
	}

	.lc-hero-price {
		display: flex;
		align-items: baseline;
	}

	.lc-hero-sym {
		font-size: 24rpx;
		font-weight: 700;
	}

	.lc-hero-num {
		font-size: 52rpx;
		font-weight: 700;
		font-family: "DIN Alternate", "Helvetica Neue", sans-serif;
	}

	.lc-hero-ori {
		margin-left: 12rpx;
		font-size: 22rpx;
		opacity: 0.75;
		text-decoration: line-through;
	}

	.lc-hero-meta {
		margin-top: 4rpx;
		display: flex;
		align-items: center;
		font-size: 21rpx;
		opacity: 0.92;
	}

	.avs {
		display: flex;
		margin-right: 10rpx;
	}

	.av {
		width: 34rpx;
		height: 34rpx;
		border-radius: 50%;
		border: 3rpx solid #ffffff;
		margin-left: -10rpx;
		box-sizing: border-box;
		background: #eeeeee;
	}

	.avs .av:first-child {
		margin-left: 0;
	}

	.lc-hero-btn {
		flex-shrink: 0;
		margin-left: 16rpx;
		padding: 12rpx 28rpx;
		border-radius: 30rpx;
		font-size: 25rpx;
		font-weight: 700;
		color: var(--bargain-main, var(--theme-color));
		background: #ffffff;
	}

	.lc-grid {
		margin-top: 20rpx;
		display: flex;
		flex-wrap: wrap;
		justify-content: space-between;
	}

	.lc-card {
		width: calc((100% - 20rpx) / 2);
		margin-bottom: 20rpx;
		border-radius: 24rpx;
		overflow: hidden;
		background: #faf7f5;
	}

	.lc-cover {
		position: relative;
		width: 100%;
		height: 0;
		padding-top: 78%;
	}

	.lc-pic {
		position: absolute;
		left: 0;
		top: 0;
		width: 100%;
		height: 100%;
		background: #f2f3f5;
	}

	.lc-rib {
		position: absolute;
		left: 12rpx;
		top: 12rpx;
		padding: 2rpx 12rpx;
		border-radius: 14rpx;
		font-size: 20rpx;
		font-weight: 700;
		color: #ffffff;
		background: var(--bargain-main, var(--theme-color));
	}

	.lc-body {
		padding: 14rpx 16rpx 16rpx;
	}

	.lc-name {
		font-size: 25rpx;
		color: #222222;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.lc-row {
		margin-top: 8rpx;
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.lc-price {
		margin-top: 0;
	}
</style>
