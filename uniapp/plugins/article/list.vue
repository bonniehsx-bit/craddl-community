<template>
	<tui-page>
		<view class="container" :style="themeStyle">

			<view class="sticky-box">
				<scroll-view class="tab-scroll" scroll-x :scroll-into-view="scrollInto" :scroll-with-animation="true">
					<view class="tab-flex">
						<view class="tab-item" v-for="(item, index) in cat_list" :key="item.id" :id="'tab' + index"
							:class="{ 'active': currentTab === index }" @click="change(index)">
							<text class="tab-text">{{ item.name }}</text>
							<view class="tab-line" v-if="currentTab === index"></view>
						</view>
					</view>
				</scroll-view>
			</view>

			<view class="list-container">
				<tui-no-data v-if="list.length == 0 && !loading" :fixed="false">
					<text class="no-data-text">暂无相关文章</text>
				</tui-no-data>

				<!-- 第一篇做成大卡:大封面压渐暗,标题压在图上 -->
				<view class="hero" v-if="hero" @click="detail(hero.id)">
					<image :src="hero.cover_pic" mode="aspectFill" class="hero-img"></image>
					<view class="hero-mask"></view>
					<view class="hero-badge">精选</view>
					<view class="play-btn hero-play" v-if="hero.video"><tui-icon name="play" size="22" color="#fff" style="margin-left: 4rpx;"></tui-icon></view>
					<view class="hero-title">{{ hero.title }}</view>
					<view class="hero-meta">
						<text class="hero-meta-text">{{ hero.created_by || '官方发布' }} · {{ hero.created_at }} · {{ formatViews(hero.views) }} 阅读</text>
						<view class="hero-goods" v-if="hero.goods_count > 0">
							<view class="thumbs"><image v-for="(t, i) in hero.goods_thumbs" :key="i" :src="t" mode="aspectFill" class="thumb"></image></view>
							<text>{{ hero.goods_count }} 件好物</text>
						</view>
					</view>
				</view>

				<view class="news-card" v-for="item in rest" :key="item.id" @click="detail(item.id)">
					<view class="card-content">
						<view class="card-title">{{ item.title }}</view>
						<view class="card-desc" v-if="item.sub_title">{{ item.sub_title }}</view>
						<view class="goods-chip" v-if="item.goods_count > 0">
							<view class="thumbs"><image v-for="(t, i) in item.goods_thumbs" :key="i" :src="t" mode="aspectFill" class="thumb"></image></view>
							<text class="chip-text">关联 {{ item.goods_count }} 件好物 · ¥{{ item.goods_min_price }}{{ item.goods_price_multi ? ' 起' : '' }}</text>
						</view>
						<view class="card-meta">
							<text class="meta-left">{{ item.created_by || '官方发布' }} · {{ item.created_at }}</text>
							<view class="meta-right">
								<tui-icon name="eye" size="14" color="#a8a39b"></tui-icon>
								<text class="view-num">{{ formatViews(item.views) }}</text>
							</view>
						</view>
					</view>
					<view class="card-cover-box">
						<image :src="item.cover_pic" mode="aspectFill" class="cover-img"></image>
						<view class="video-mask" v-if="item.video">
							<view class="play-btn"><tui-icon name="play" size="20" color="#fff" style="margin-left: 4rpx;"></tui-icon></view>
						</view>
					</view>
				</view>

				<view class="load-more" v-if="list.length > 0">
					<tui-loadmore :index="3" v-if="loading"></tui-loadmore>
					<text v-else>{{ is_no_more ? '- 到底啦 -' : '上拉加载' }}</text>
				</view>
			</view>
		</view>
	</tui-page>
</template>

<script>
	export default {
		data() {
			return {
				currentTab: 0,
				scrollInto: '',
				page: 1,
				list: [],
				cat_list: [{ id: 0, name: '全部' }],
				is_no_more: false,
				loading: false
			}
		},
		computed: {
			// 每个分类的第一页第一篇做大卡;没封面的不做(大卡没图不好看)
			hero() {
				const f = this.list[0];
				return f && f.cover_pic ? f : null;
			},
			rest() {
				return this.hero ? this.list.slice(1) : this.list;
			}
		},
		onLoad() {
			this.getCatList();
			this.getArticleList(0);
		},
		onReachBottom() {
			this.getArticleList(this.cat_list[this.currentTab].id);
		},
		methods: {
			formatViews(num) {
				if (!num) return 0;
				if (num > 9999) return (num / 10000).toFixed(1) + 'w';
				return num;
			},
			detail(id) {
				uni.navigateTo({
					url: "/plugins/article/article?id=" + id
				})
			},
			change(index) {
				if (this.currentTab === index) return;
				this.currentTab = index;
				this.scrollInto = 'tab' + index;
				this.is_no_more = false;
				this.list = [];
				this.page = 1;
				this.getArticleList(this.cat_list[index].id);
			},
			getCatList() {
				this.$request({
					url: "/plugin/article/api/article/cat-list",
				}).then(res => {
					if (res.code == 0) {
						// 「全部」是前端补的第一项,后台没建分类 / 文章没分类时也有内容可看
						this.cat_list = [{ id: 0, name: '全部' }].concat(res.data.list || []);
					}
				})
			},
			getArticleList(id) {
				if (this.is_no_more || this.loading) return;
				this.loading = true;
				const seq = this._seq = (this._seq || 0) + 1;
				this.$request({
					url: "/plugin/article/api/article/list",
					data: { page: this.page, cat_id: id || '' }
				}).then(res => {
					if (seq !== this._seq) return; // 切分类时慢的旧请求不能盖掉新结果
					this.loading = false;
					if (res.code == 0) {
						if (this.page == 1) {
							this.list = res.data.list;
						} else {
							this.list = this.list.concat(res.data.list);
						}
						// 接口返回的是 page_count(下划线);原来读 pageCount 永远是 undefined,到底了也不会停
						const pc = Number((res.data.pagination || {}).page_count || (res.data.pagination || {}).pageCount || 0);
						if (this.page >= pc) {
							this.is_no_more = true;
						} else {
							this.page++;
						}
					}
				}).catch(() => { if (seq === this._seq) this.loading = false; })
			}
		}
	}
</script>

<style lang="scss" scoped>
	.container {
		min-height: 100vh;
		background-color: #f6f5f2;
	}

	/* 分类标签 */
	.sticky-box {
		position: sticky;
		top: 0;
		z-index: 100;
		background: #fff;
		box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.03);
	}

	.tab-scroll {
		width: 100%;
		height: 90rpx;
		white-space: nowrap;
	}

	.tab-flex {
		display: flex;
		align-items: center;
		height: 90rpx;
		padding: 0 10rpx;
	}

	.tab-item {
		display: inline-flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		padding: 0 30rpx;
		height: 100%;
		position: relative;

		.tab-text {
			font-size: 28rpx;
			color: #777;
		}

		&.active {
			.tab-text {
				font-size: 31rpx;
				font-weight: 600;
				color: var(--theme-color);
			}

			.tab-line {
				position: absolute;
				bottom: 12rpx;
				width: 40rpx;
				height: 6rpx;
				background: var(--theme-color);
				border-radius: 4rpx;
			}
		}
	}

	.list-container {
		padding: 24rpx 24rpx 40rpx;
	}

	/* 精选大卡 */
	.hero {
		position: relative;
		height: 400rpx;
		border-radius: 28rpx;
		overflow: hidden;
		margin-bottom: 24rpx;
		background: #e8e5de;
		box-shadow: 0 10rpx 28rpx rgba(0, 0, 0, 0.1);
	}

	.hero-img {
		width: 100%;
		height: 100%;
		display: block;
	}

	.hero-mask {
		position: absolute;
		left: 0;
		right: 0;
		top: 0;
		bottom: 0;
		background: linear-gradient(180deg, rgba(0, 0, 0, 0) 38%, rgba(0, 0, 0, 0.72) 100%);
	}

	.hero-badge {
		position: absolute;
		left: 22rpx;
		top: 22rpx;
		padding: 4rpx 20rpx;
		border-radius: 20rpx;
		background: var(--theme-color);
		color: #fff;
		font-size: 22rpx;
		font-weight: 600;
		box-shadow: 0 4rpx 12rpx var(--theme-shadow);
	}

	.hero-title {
		position: absolute;
		left: 26rpx;
		right: 26rpx;
		bottom: 66rpx;
		color: #fff;
		font-size: 36rpx;
		font-weight: 700;
		line-height: 1.38;
		text-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.4);
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
	}

	.hero-meta {
		position: absolute;
		left: 26rpx;
		right: 22rpx;
		bottom: 20rpx;
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.hero-meta-text {
		flex: 1;
		min-width: 0;
		font-size: 22rpx;
		color: rgba(255, 255, 255, 0.85);
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.hero-goods {
		flex-shrink: 0;
		display: flex;
		align-items: center;
		margin-left: 16rpx;
		padding: 4rpx 18rpx 4rpx 8rpx;
		border-radius: 30rpx;
		background: rgba(255, 255, 255, 0.22);
		border: 1rpx solid rgba(255, 255, 255, 0.4);
		color: #fff;
		font-size: 22rpx;
	}

	.hero-play {
		position: absolute;
		right: 24rpx;
		top: 22rpx;
	}

	/* 叠放的商品缩略图 */
	.thumbs {
		display: flex;
		margin-right: 10rpx;
	}

	.thumb {
		width: 36rpx;
		height: 36rpx;
		border-radius: 50%;
		border: 3rpx solid #fff;
		margin-left: -12rpx;
		background: #eee;
	}

	.thumb:first-child {
		margin-left: 0;
	}

	/* 普通文章卡:左文右图 */
	.news-card {
		display: flex;
		background: #fff;
		border-radius: 24rpx;
		padding: 22rpx;
		margin-bottom: 22rpx;
		box-shadow: 0 6rpx 20rpx rgba(0, 0, 0, 0.04);
	}

	.card-content {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
		margin-right: 22rpx;
	}

	.card-title {
		font-size: 30rpx;
		font-weight: 600;
		line-height: 1.42;
		color: #222;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
	}

	.card-desc {
		font-size: 24rpx;
		color: #9a978f;
		margin-top: 8rpx;
		line-height: 1.5;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.goods-chip {
		align-self: flex-start;
		display: flex;
		align-items: center;
		margin-top: 14rpx;
		padding: 4rpx 18rpx 4rpx 8rpx;
		border-radius: 30rpx;
		background: var(--theme-color-bg);

		.chip-text {
			font-size: 22rpx;
			color: var(--theme-color);
		}
	}

	.card-meta {
		margin-top: auto;
		padding-top: 14rpx;
		display: flex;
		align-items: center;
		justify-content: space-between;
		font-size: 22rpx;
		color: #a8a39b;

		.meta-left {
			flex: 1;
			min-width: 0;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		.meta-right {
			display: flex;
			align-items: center;
			flex-shrink: 0;
			margin-left: 12rpx;
		}

		.view-num {
			margin-left: 6rpx;
		}
	}

	.card-cover-box {
		position: relative;
		width: 220rpx;
		height: 220rpx;
		flex-shrink: 0;
		border-radius: 18rpx;
		overflow: hidden;
		background: #eee;
	}

	.cover-img {
		width: 100%;
		height: 100%;
		display: block;
	}

	.video-mask {
		position: absolute;
		right: 10rpx;
		bottom: 10rpx;
	}

	.play-btn {
		width: 52rpx;
		height: 52rpx;
		border-radius: 50%;
		background: rgba(0, 0, 0, 0.5);
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.load-more {
		text-align: center;
		font-size: 24rpx;
		color: #b5b1a8;
		padding: 20rpx 0;
	}

	.no-data-text {
		color: #aaa;
		font-size: 26rpx;
	}
</style>
