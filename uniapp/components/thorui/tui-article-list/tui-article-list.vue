<template>
	<!-- 一篇文章都没有时整块不渲染:否则首页上会留一个带背景色的空白卡片 -->
	<view class="diy-article-root" :style="outerStyle" v-if="articles.length">
		<view class="group-inner" :style="innerStyle">
			<view class="group-body">

				<!-- 头部:标题为空时仍保留「更多」入口，否则运营配无标题列表就等于砍掉了列表页入口 -->
				<view class="group-header" :class="!safeData.title ? 'no-title' : ''">
					<text class="title" v-if="safeData.title">{{ safeData.title }}</text>
					<view class="more" @click="goList">
						更多
						<tui-icon name="arrowright" :size="24" unit="rpx"></tui-icon>
					</view>
				</view>

				<!-- 大图:封面在上、标题在下，一篇一卡片 -->
				<block v-if="style === 'big'">
					<view class="art-big" v-for="(item, i) in articles" :key="i" @click="goDetail(item)">
						<image v-if="item.cover_pic" :src="item.cover_pic" class="big-cover" mode="aspectFill"></image>
						<view class="big-info">
							<view class="art-title">{{ item.title }}</view>
							<view class="art-desc" v-if="desc(item)">{{ desc(item) }}</view>
							<view class="art-meta">{{ views(item) }} 人浏览</view>
						</view>
					</view>
				</block>

				<!-- 纯文字:不显示封面 -->
				<block v-else-if="style === 'text'">
					<view class="art-text" v-for="(item, i) in articles" :key="i" @click="goDetail(item)">
						<view class="art-title">{{ item.title }}</view>
						<view class="art-desc" v-if="desc(item)">{{ desc(item) }}</view>
						<view class="art-meta">{{ views(item) }} 人浏览</view>
					</view>
				</block>

				<!-- 左文右图(默认):没有封面的那篇自动占满整行，不留灰方块 -->
				<block v-else>
					<view class="art-row" v-for="(item, i) in articles" :key="i" @click="goDetail(item)">
						<view class="row-info">
							<view class="row-text">
								<view class="art-title">{{ item.title }}</view>
								<view class="art-desc" v-if="desc(item)">{{ desc(item) }}</view>
							</view>
							<view class="art-meta">{{ views(item) }} 人浏览</view>
						</view>
						<view class="row-img" v-if="item.cover_pic">
							<image :src="item.cover_pic" class="cover-pic" mode="aspectFill"></image>
						</view>
					</view>
				</block>

			</view>
		</view>
	</view>
</template>

<script>
	export default {
		props: {
			value: {
				type: Object,
				default: () => {
					return null
				}
			}
		},
		computed: {
			safeData() {
				return this.value || {};
			},
			articles() {
				const list = this.safeData.list;
				return Array.isArray(list) ? list : [];
			},
			// 存量页面的 page_data 里没有 style 字段,兜底成原有的左文右图
			style() {
				const s = this.safeData.style;
				return (s === 'big' || s === 'text') ? s : 'list';
			},
			outerStyle() {
				const padding = this.safeData.is_padding == 1 ? '0 20rpx' : '0';
				return `padding:${padding};margin-bottom:20rpx`;
			},
			innerStyle() {
				const radius = this.safeData.is_circle == 1 ? '20rpx' : '0';
				const bg = this.safeData.background || '#ffffff';
				return `border-radius:${radius};background-color:${bg};overflow:hidden`;
			}
		},
		methods: {
			// 摘要字段以 Article 表为准是 sub_title;info 是老快照留下的键，一并兼容
			desc(item) {
				return item.sub_title || item.info || '';
			},
			// 同上:浏览量表里叫 views，click_count 是老快照
			views(item) {
				return Number(item.views || item.click_count || 0);
			},
			goList() {
				this.$T.go('/plugins/article/list');
			},
			goDetail(item) {
				this.$T.go('/plugins/article/article?id=' + item.id);
			}
		}
	}
</script>

<style lang="scss" scoped>
	.group-body {
		padding: 20rpx;
		padding-top: 10rpx;
	}

	.group-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 20rpx;
		padding-bottom: 10rpx;
		border-bottom: 1rpx solid #f5f5f5;

		// 没有标题时「更多」靠右，且不画那条分隔线，避免顶部出现一条孤零零的横线
		&.no-title {
			justify-content: flex-end;
			border-bottom: none;
			padding-bottom: 0;
			margin-bottom: 10rpx;
		}

		.title {
			font-size: 30rpx;
			font-weight: bold;
			color: #333;
		}

		.more {
			font-size: 24rpx;
			color: #999;
			display: flex;
			align-items: center;
		}
	}

	/* ── 三种布局共用的文字样式 ── */
	.art-title {
		font-size: 30rpx;
		font-weight: bold;
		color: #333;
		line-height: 1.4;
		overflow: hidden;
		text-overflow: ellipsis;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		line-clamp: 2;
		-webkit-box-orient: vertical;
	}

	.art-desc {
		font-size: 24rpx;
		color: #999;
		line-height: 1.4;
		margin-top: 12rpx;
		overflow: hidden;
		text-overflow: ellipsis;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		line-clamp: 2;
		-webkit-box-orient: vertical;
	}

	.art-meta {
		font-size: 22rpx;
		color: #ccc;
		margin-top: 12rpx;
	}

	/* ── 左文右图 ── */
	.art-row {
		display: flex;
		justify-content: space-between;
		padding: 30rpx 0;
		border-bottom: 1rpx solid #f5f5f5;

		&:last-child {
			border-bottom: none;
		}

		.row-info {
			flex: 1;
			min-width: 0; // 不加这条，长标题会把右侧封面挤出容器
			display: flex;
			flex-direction: column;
			justify-content: space-between;
		}

		.row-img {
			width: 220rpx;
			height: 160rpx;
			margin-left: 20rpx;
			flex-shrink: 0;
			border-radius: 8rpx;
			overflow: hidden;
			background: #f5f7fa;
		}
	}

	/* ── 大图 ── */
	.art-big {
		padding-bottom: 30rpx;
		margin-bottom: 30rpx;
		border-bottom: 1rpx solid #f5f5f5;

		&:last-child {
			border-bottom: none;
			margin-bottom: 0;
			padding-bottom: 0;
		}

		.big-cover {
			width: 100%;
			height: 320rpx;
			display: block;
			border-radius: 12rpx;
			background: #f5f7fa;
		}

		.big-info {
			margin-top: 16rpx;
		}
	}

	/* ── 纯文字 ── */
	.art-text {
		padding: 26rpx 0;
		border-bottom: 1rpx solid #f5f5f5;

		&:last-child {
			border-bottom: none;
		}
	}

	.cover-pic {
		width: 100%;
		height: 100%;
		display: block;
	}
</style>
