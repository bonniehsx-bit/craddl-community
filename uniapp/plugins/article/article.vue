<template>
	<tui-page ref="page" :title="article.title" :useCustomNav="true" :transparentNav="true" :solidOnScroll="true"
		:scrollTop="navScroll" :solidDistance="NAV_SOLID_PX">
		<view class="container" :style="themeStyle + ';padding-bottom:' + (hasGoods ? 'calc(150rpx + env(safe-area-inset-bottom) * 0.3)' : '60rpx') + ';'">

			<!-- 顶部:有视频放视频,否则放封面图;通顶,导航栏透明悬在上面 -->
			<view class="video-wrapper" v-if="article.video">
				<video :src="article.video" class="video-player" controls :show-center-play-btn="true" object-fit="contain"
					poster-size="contain" :poster="article.cover_pic"></video>
			</view>
			<image v-else-if="article.cover_pic" class="cover" :src="article.cover_pic" mode="aspectFill"></image>
			<view v-else class="cover-blank"></view>

			<view class="main-content">
				<view class="article-title">{{ article.title }}</view>

				<view class="author-card">
					<view class="avatar-circle">{{ article.created_by ? article.created_by[0] : '官' }}</view>
					<view class="author-info">
						<text class="author-name">{{ article.created_by || '官方发布' }}</text>
						<view class="info-bottom">
							<text class="publish-time">{{ article.created_at || formatDate(article.created_time) }}</text>
							<text class="divider-dot">·</text>
							<text class="read-count">{{ formatViews(article.views) }} 阅读</text>
						</view>
					</view>
				</view>

				<view class="content-section">
					<tui-rich-text :value="processHtml(article.detail)"></tui-rich-text>
				</view>

				<!-- 文中好物 -->
				<view class="goods-section" v-if="hasGoods">
					<view class="sec-head">
						<view class="sec-bar"></view>
						<text class="sec-title">文中提到的好物</text>
						<text class="sec-count">共 {{ goodsList.length }} 件</text>
					</view>
					<view class="gcard" v-for="g in goodsList" :key="g.id" @click="goGoods(g.id)">
						<view class="gimg-box">
							<image :src="g.cover_pic" mode="aspectFill" class="gimg"></image>
							<view class="gtag" v-if="g.is_main">文中主推</view>
						</view>
						<view class="ginfo">
							<view class="gname">{{ g.name }}</view>
							<view class="grec" v-if="g.recommend">“{{ g.recommend }}”</view>
							<view class="gbottom">
								<view class="gprice"><text class="gyen">¥</text><text class="gnum">{{ g.price }}</text><text class="gup" v-if="g.price_multi">起</text></view>
								<view class="ggo">去看看</view>
							</view>
						</view>
					</view>
				</view>

				<view class="divider-end">
					<view class="line"></view>
					<text class="text">THE END</text>
					<view class="line"></view>
				</view>
			</view>

			<!-- 底部常驻购买条:主推商品 + 全部好物入口 -->
			<view class="buy-bar" v-if="hasGoods">
				<view class="buy-inner">
					<image class="buy-img" :src="mainGoods.cover_pic" mode="aspectFill" @click="goGoods(mainGoods.id)"></image>
					<view class="buy-info" @click="goGoods(mainGoods.id)">
						<view class="buy-name">{{ mainGoods.name }}</view>
						<view class="buy-price"><text class="gyen">¥</text><text class="buy-num">{{ mainGoods.price }}</text><text class="gup" v-if="mainGoods.price_multi">起</text></view>
					</view>
					<view class="buy-bag" v-if="goodsList.length > 1" @click="showGoods = true">
						<view class="bag-icon">
							<view class="bag-body"></view>
							<view class="bag-handle"></view>
						</view>
						<view class="bag-badge">{{ goodsList.length }}</view>
					</view>
					<view class="buy-btn" @click="goGoods(mainGoods.id)">立即购买</view>
				</view>
			</view>

			<!-- 本文所有好物 -->
			<tui-bottom-popup :show="showGoods" @close="showGoods = false" :zIndex="1002">
				<view class="gpop" :style="themeStyle">
					<view class="gpop-head">
						<text class="gpop-title">本文提到的好物</text>
						<text class="gpop-count">共 {{ goodsList.length }} 件</text>
						<view class="gpop-close" @click="showGoods = false">×</view>
					</view>
					<scroll-view scroll-y class="gpop-list">
						<view class="grow" v-for="g in goodsList" :key="g.id" @click="goGoods(g.id)">
							<view class="gimg-box small">
								<image :src="g.cover_pic" mode="aspectFill" class="gimg"></image>
								<view class="gtag" v-if="g.is_main">主推</view>
							</view>
							<view class="ginfo">
								<view class="gname">{{ g.name }}</view>
								<view class="grec" v-if="g.recommend">“{{ g.recommend }}”</view>
								<view class="gbottom">
									<view class="gprice"><text class="gyen">¥</text><text class="gnum">{{ g.price }}</text><text class="gup" v-if="g.price_multi">起</text></view>
									<view class="ggo" :class="g.is_main ? 'main' : ''">{{ g.is_main ? '立即购买' : '去看看' }}</view>
								</view>
							</view>
						</view>
					</scroll-view>
					<view class="gpop-tip">点商品进入商品详情,分享文章会带上你的推荐关系</view>
				</view>
			</tui-bottom-popup>
		</view>
	</tui-page>
</template>

<script>
	export default {
		data() {
			return {
				id: '',
				pid: '',
				navScroll: 0,
				NAV_SOLID_PX: 240,
				showGoods: false,
				article: {
					created_at: '',
					created_time: '',
					title: '',
					detail: '',
					video: '',
					cover_pic: '',
					views: 0,
					created_by: '',
					goods_list: []
				}
			}
		},
		computed: {
			goodsList() {
				return this.article.goods_list || [];
			},
			hasGoods() {
				return this.goodsList.length > 0;
			},
			// 底栏主推:后台标的那件;被下架时接口已让第一件顶上
			mainGoods() {
				return this.goodsList.find(g => g.is_main) || this.goodsList[0] || {};
			}
		},
		onLoad: function(options) {
			if (options.id) this.id = options.id;
			if (options.pid) this.pid = options.pid;
		},
		onShow: function() {
			this.getArticle();
		},
		// 导航栏滑到 NAV_SOLID_PX 就完全变实色,再往下不用更新
		onPageScroll(e) {
			const t = Math.min(Math.max(e.scrollTop, 0), this.NAV_SOLID_PX);
			if (t !== this.navScroll) this.navScroll = t;
		},
		onShareAppMessage: function(e) {
			let user = uni.getStorageSync('USER');
			let path = 'plugins/article/article?id=' + this.id;
			if (user) path += '&pid=' + user.id;

			return {
				title: this.article.title,
				path: path,
				imageUrl: this.article.cover_pic
			}
		},
		methods: {
			// 处理富文本中的图片，防止宽度溢出
			processHtml(html) {
				if (!html) return '';
				// 正则替换：给 img 标签增加最大宽度样式
				return html.replace(/<img/g, '<img style="max-width:100%;height:auto;display:block;margin:20px 0;"');
			},

			// 格式化数字
			formatViews(num) {
				if (!num) return 0;
				if (num > 9999) return (num / 10000).toFixed(1) + 'w';
				return num;
			},

			formatDate(str) {
				if (!str) return '';
				return str.length > 10 ? str.substring(0, 10) : str;
			},

			goGoods(id) {
				this.showGoods = false;
				uni.navigateTo({ url: '/others/goods/goods?id=' + id });
			},

			getArticle() {
				this.$request({
					url: "/plugin/article/api/article/article",
					data: { id: this.id, pid: this.pid }
				}).then(res => {
					if (res.code == 0) {
						this.article = Object.assign({ goods_list: [] }, res.data.article);
						uni.setNavigationBarTitle({ title: this.article.title });
					} else if (res.code == 1) {
						uni.showModal({
							title: '提示',
							content: res.msg,
							showCancel: false,
							success: (e) => { if (e.confirm) uni.navigateBack() }
						})
					} else if (res.code == -1) {
						this.$refs.page.showLoginModalWindow();
					}
				}).catch(() => {})
			}
		}
	}
</script>

<style lang="scss" scoped>
	.container {
		min-height: 100vh;
		background: #fff;
	}

	.video-wrapper {
		width: 100%;
		background: #000;
	}

	.video-player {
		width: 100%;
		height: 422rpx;
		display: block;
	}

	.cover {
		width: 750rpx;
		height: 560rpx;
		display: block;
		background: #e8e5de;
	}

	.cover-blank {
		height: 200rpx;
		background: linear-gradient(160deg, var(--theme-color), var(--theme-color-light));
	}

	/* 白色内容卡往上叠一截,和封面衔接 */
	.main-content {
		position: relative;
		margin-top: -40rpx;
		padding: 40rpx 36rpx 20rpx;
		background: #fff;
		border-radius: 40rpx 40rpx 0 0;
	}

	.article-title {
		font-size: 42rpx;
		font-weight: 700;
		line-height: 1.38;
		color: #222;
	}

	.author-card {
		display: flex;
		align-items: center;
		margin: 28rpx 0 20rpx;

		.avatar-circle {
			width: 68rpx;
			height: 68rpx;
			border-radius: 50%;
			background: var(--theme-color);
			color: #fff;
			font-size: 28rpx;
			font-weight: 600;
			text-align: center;
			line-height: 68rpx;
			margin-right: 18rpx;
			flex-shrink: 0;
		}

		.author-name {
			display: block;
			font-size: 27rpx;
			font-weight: 600;
			color: #333;
		}

		.info-bottom {
			margin-top: 4rpx;
			font-size: 22rpx;
			color: #a8a39b;
		}

		.divider-dot {
			margin: 0 8rpx;
		}
	}

	.content-section {
		font-size: 30rpx;
		line-height: 1.85;
		color: #444;
	}

	/* 文中好物 */
	.goods-section {
		margin-top: 36rpx;
	}

	.sec-head {
		display: flex;
		align-items: center;
		margin-bottom: 22rpx;

		.sec-bar {
			width: 8rpx;
			height: 32rpx;
			border-radius: 4rpx;
			background: var(--theme-color);
			margin-right: 14rpx;
		}

		.sec-title {
			font-size: 32rpx;
			font-weight: 700;
			color: #222;
		}

		.sec-count {
			margin-left: auto;
			font-size: 23rpx;
			color: #a8a39b;
		}
	}

	.gcard,
	.grow {
		display: flex;
	}

	.gcard {
		padding: 20rpx;
		margin-bottom: 20rpx;
		border-radius: 28rpx;
		background: #faf8f4;
		border: 1rpx solid #f0ebe2;
	}

	.gimg-box {
		position: relative;
		width: 190rpx;
		height: 190rpx;
		border-radius: 20rpx;
		overflow: hidden;
		flex-shrink: 0;
		margin-right: 22rpx;
		background: #eee;

		&.small {
			width: 160rpx;
			height: 160rpx;
		}
	}

	.gimg {
		width: 100%;
		height: 100%;
		display: block;
	}

	.gtag {
		position: absolute;
		left: 0;
		top: 0;
		padding: 2rpx 16rpx;
		border-radius: 0 0 16rpx 0;
		background: var(--theme-color);
		color: #fff;
		font-size: 20rpx;
		font-weight: 600;
	}

	.ginfo {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
	}

	.gname {
		font-size: 28rpx;
		font-weight: 600;
		line-height: 1.4;
		color: #222;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
	}

	.grec {
		margin-top: 8rpx;
		font-size: 23rpx;
		color: #b4822c;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.gbottom {
		margin-top: auto;
		display: flex;
		align-items: flex-end;
		justify-content: space-between;
	}

	.gprice {
		color: var(--theme-color);
		font-weight: 700;
		line-height: 1.1;

		.gyen {
			font-size: 24rpx;
		}

		.gnum {
			font-size: 40rpx;
		}

		.gup {
			font-size: 22rpx;
			margin-left: 4rpx;
			font-weight: 400;
		}
	}

	.ggo {
		padding: 10rpx 28rpx;
		border-radius: 30rpx;
		font-size: 24rpx;
		color: var(--theme-color);
		border: 2rpx solid var(--theme-color);
		background: #fff;

		&.main {
			color: #fff;
			background: var(--theme-color);
			box-shadow: 0 6rpx 14rpx var(--theme-shadow);
		}
	}

	.gcard .ggo {
		color: #fff;
		background: var(--theme-color);
		border-color: var(--theme-color);
		box-shadow: 0 6rpx 14rpx var(--theme-shadow);
	}

	.divider-end {
		margin: 60rpx 0 20rpx;
		display: flex;
		align-items: center;
		justify-content: center;

		.line {
			width: 80rpx;
			height: 1rpx;
			background: #e2dfd8;
		}

		.text {
			margin: 0 20rpx;
			font-size: 20rpx;
			color: #c5c1b8;
			letter-spacing: 4rpx;
		}
	}

	/* 底部常驻购买条:内容区 100rpx,iOS 安全区只补三成 */
	.buy-bar {
		position: fixed;
		left: 0;
		right: 0;
		bottom: 0;
		z-index: 90;
		padding: 0 28rpx;
		padding-bottom: calc(env(safe-area-inset-bottom) * 0.3);
		background: rgba(255, 255, 255, 0.96);
		backdrop-filter: blur(12px);
		-webkit-backdrop-filter: blur(12px);
		border-top: 1rpx solid #eeeae2;
		box-shadow: 0 -6rpx 24rpx rgba(40, 30, 10, 0.05);
	}

	.buy-inner {
		height: 100rpx;
		display: flex;
		align-items: center;
	}

	.buy-img {
		width: 76rpx;
		height: 76rpx;
		border-radius: 16rpx;
		flex-shrink: 0;
		background: #eee;
	}

	.buy-info {
		flex: 1;
		min-width: 0;
		margin: 0 16rpx;
	}

	.buy-name {
		font-size: 25rpx;
		color: #333;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.buy-price {
		margin-top: 4rpx;
		color: var(--theme-color);
		font-weight: 700;
		line-height: 1.1;

		.gyen {
			font-size: 22rpx;
		}

		.buy-num {
			font-size: 34rpx;
		}

		.gup {
			font-size: 20rpx;
			margin-left: 4rpx;
			font-weight: 400;
		}
	}

	.buy-bag {
		position: relative;
		width: 76rpx;
		height: 76rpx;
		border-radius: 50%;
		background: var(--theme-color-bg);
		margin-right: 16rpx;
		flex-shrink: 0;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	/* 购物袋小图标:纯 CSS 画,不依赖图标字体 */
	.bag-icon {
		position: relative;
		width: 34rpx;
		height: 34rpx;
	}

	.bag-body {
		position: absolute;
		left: 0;
		right: 0;
		bottom: 0;
		height: 24rpx;
		border-radius: 6rpx;
		background: var(--theme-color);
	}

	.bag-handle {
		position: absolute;
		left: 8rpx;
		right: 8rpx;
		top: 0;
		height: 16rpx;
		box-sizing: border-box;
		border: 4rpx solid var(--theme-color);
		border-bottom: none;
		border-radius: 10rpx 10rpx 0 0;
	}

	.bag-badge {
		position: absolute;
		right: -6rpx;
		top: -6rpx;
		min-width: 32rpx;
		height: 32rpx;
		padding: 0 8rpx;
		box-sizing: border-box;
		border-radius: 16rpx;
		border: 3rpx solid #fff;
		background: var(--theme-color);
		color: #fff;
		font-size: 20rpx;
		font-weight: 700;
		line-height: 26rpx;
		text-align: center;
	}

	.buy-btn {
		flex-shrink: 0;
		height: 76rpx;
		line-height: 76rpx;
		padding: 0 44rpx;
		border-radius: 38rpx;
		background: var(--theme-color);
		color: #fff;
		font-size: 29rpx;
		font-weight: 600;
		letter-spacing: 2rpx;
		box-shadow: 0 8rpx 20rpx var(--theme-shadow);
	}

	/* 好物抽屉 */
	.gpop {
		background: #fff;
		border-radius: 36rpx 36rpx 0 0;
		padding: 28rpx 28rpx 0;
	}

	.gpop-head {
		display: flex;
		align-items: center;

		.gpop-title {
			font-size: 32rpx;
			font-weight: 700;
			color: #222;
		}

		.gpop-count {
			margin-left: 14rpx;
			font-size: 23rpx;
			color: #a8a39b;
		}

		.gpop-close {
			margin-left: auto;
			width: 56rpx;
			height: 56rpx;
			line-height: 52rpx;
			text-align: center;
			border-radius: 50%;
			background: #f3f1ec;
			color: #888;
			font-size: 40rpx;
		}
	}

	.gpop-list {
		max-height: 760rpx;
		margin-top: 12rpx;
	}

	.grow {
		padding: 22rpx 0;
		border-bottom: 1rpx solid #f3eee8;

		.gimg-box {
			margin-right: 20rpx;
		}
	}

	.gpop-tip {
		padding: 20rpx 0 calc(24rpx + env(safe-area-inset-bottom) * 0.3);
		font-size: 22rpx;
		color: #a8a39b;
		text-align: center;
	}
</style>
