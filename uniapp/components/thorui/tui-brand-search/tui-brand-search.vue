<template>
	<!-- 顶栏搜索:整条底色 + 左侧店名 + 胶囊搜索框。和原「搜索」组件互不影响 -->
	<!-- 沉浸式页面(首页 / 自定义页)的第一个模块:分两行 —— 店名放在胶囊那一行,搜索框另起一行占满宽 -->
	<view v-if="top" class="tui-brand-search" :style="bgStyle">
		<view class="top-row" :style="topRowStyle">
			<view v-if="d.brand_title" class="brand-inline">
				<text class="brand-title" :style="'color:' + (d.text_color || '#ffffff') + ';'">{{ d.brand_title }}</text>
				<text v-if="d.brand_sub" class="brand-sub" :style="'color:' + (d.accent_color || '#e8b75a') + ';'">{{ d.brand_sub }}</text>
			</view>
		</view>
		<view class="search-row">
			<view class="box" :style="boxStyle" @click.stop="onSearch">
				<icon type="search" :size="12" :color="tipsColor"></icon>
				<text class="tips" :style="'color:' + tipsColor + ';'">{{ d.tips || '搜索商品' }}</text>
			</view>
		</view>
	</view>
	<!-- 其它位置 / H5:店名和搜索框一行 -->
	<view v-else class="tui-brand-search is-inline" :style="bgStyle">
		<view v-if="d.brand_title" class="brand">
			<view class="brand-title" :style="'color:' + (d.text_color || '#ffffff') + ';'">{{ d.brand_title }}</view>
			<view v-if="d.brand_sub" class="brand-sub" :style="'color:' + (d.accent_color || '#e8b75a') + ';'">{{ d.brand_sub }}</view>
		</view>
		<view class="box" :style="boxStyle" @click.stop="onSearch">
			<icon type="search" :size="12" :color="tipsColor"></icon>
			<text class="tips" :style="'color:' + tipsColor + ';'">{{ d.tips || '搜索商品' }}</text>
		</view>
	</view>
</template>

<script>
	import { goLink, immersiveTop } from '../tui-style-kit/style-kit.js';

	export default {
		name: 'TuiBrandSearch',
		props: {
			value: { type: Object, default: () => null },
			// 是页面第一个模块:首页 / 自定义页都是沉浸式顶部(没有原生标题栏),要让出状态栏和胶囊那一行
			first: { type: Boolean, default: false }
		},
		computed: {
			// 沉浸式页面顶部尺寸;不是第一个模块、不是沉浸式页面、H5 都是 null → 走单行排法
			top() {
				return this.first ? immersiveTop() : null;
			},
			d() {
				return this.value || {};
			},
			tipsColor() {
				return this.d.tips_color || 'rgba(255,255,255,0.55)';
			},
			bgStyle() {
				return 'background:' + (this.d.bg_color || '#0e1a2b') + ';';
			},
			// 胶囊那一行:上面让出状态栏,左边让出返回按钮(diy 页),右边让出胶囊
			topRowStyle() {
				const t = this.top;
				return 'padding-top:' + t.status + 'px;height:' + (t.status + t.bar) + 'px;'
					+ 'padding-left:' + (t.back ? t.back + 'px' : '32rpx') + ';'
					+ 'padding-right:' + (t.right ? t.right + 8 : 16) + 'px;';
			},
			boxStyle() {
				let s = 'background:' + (this.d.box_bg || 'rgba(255,255,255,0.1)') + ';';
				if (this.d.box_border) s += 'border:1px solid ' + this.d.box_border + ';';
				return s;
			}
		},
		methods: {
			onSearch() {
				if (this.d.url) {
					goLink(this.d.url);
					return;
				}
				uni.navigateTo({ url: '/others/search/search' });
			}
		}
	}
</script>

<style>
	.tui-brand-search {
		box-sizing: border-box;
	}

	.is-inline {
		display: flex;
		align-items: center;
		padding: 20rpx 32rpx 24rpx;
	}

	.top-row {
		display: flex;
		align-items: center;
		box-sizing: border-box;
	}

	.search-row {
		display: flex;
		padding: 4rpx 32rpx 24rpx;
	}

	.brand {
		flex-shrink: 0;
		max-width: 40%;
		margin-right: 20rpx;
	}

	/* 胶囊那一行:店名和小字横排、底线对齐,太长就省略 */
	.brand-inline {
		display: flex;
		align-items: baseline;
		min-width: 0;
		overflow: hidden;
	}

	.brand-title {
		font-size: 30rpx;
		font-weight: 700;
		letter-spacing: 1rpx;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.brand-sub {
		font-size: 18rpx;
		letter-spacing: 4rpx;
		opacity: 0.85;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.brand-inline .brand-title {
		flex-shrink: 0;
		max-width: 100%;
	}

	.brand-inline .brand-sub {
		margin-left: 14rpx;
		min-width: 0;
	}

	.box {
		flex: 1;
		min-width: 0;
		height: 64rpx;
		border-radius: 32rpx;
		display: flex;
		align-items: center;
		padding: 0 24rpx;
		box-sizing: border-box;
	}

	.tips {
		flex: 1;
		min-width: 0;
		margin-left: 12rpx;
		font-size: 24rpx;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
</style>
