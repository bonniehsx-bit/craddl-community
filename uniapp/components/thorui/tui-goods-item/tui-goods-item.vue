<template>
	<!-- 个人中心 10 套主题的推荐商品 + 我的足迹共用这一个卡片。
	     样子和商品列表页(others/goods-list)、首页商品组(tui-goods-group)统一:
	     价格整数大小数小,原价/已售跟在价格后面,右边主题色圆形购物车钮。
	     themeStyle 来自全局 mixin,绑在根上是为了拿到 --theme-shadow(页面层 tui-page 只注入了 --theme-color)。 -->
	<view class="tui-pro-item" :style="themeStyle" @click="$T.go('/others/goods/goods?id='+goods.id)">
		<image :src="thumbSrc(goods.cover_pic)" class="tui-pro-img" mode="aspectFill" @error="thumbFail(goods.cover_pic)" />
		<view class="tui-pro-content">
			<view class="tui-pro-tit">{{ goods.name }}</view>
			<view class="tui-pro-bottom">
				<view class="tui-pro-price-line">
					<view class="tui-sale-price" v-if="goods.is_negotiable != 1">
						<text class="tui-sym">¥</text><text class="tui-int">{{ priceInt }}</text><text class="tui-dec">{{ priceDec }}</text>
					</view>
					<view class="tui-sale-price" v-else><text class="tui-int sm">面议</text></view>
					<!-- 原来不管有没有原价都显示,没填原价的商品会挂一个划掉的「¥0.00」 -->
					<text class="tui-factory-price" v-if="hasOrigin">¥{{ goods.origin_price }}</text>
					<text class="tui-pro-sold">已售 {{ displaySales }}{{ goods.unit || '' }}</text>
				</view>
				<view class="tui-pro-go">
					<tui-icon name="cart" :size="13" color="#fff"></tui-icon>
				</view>
			</view>
		</view>
	</view>
</template>

<script>
	import { thumbMixin } from '@/utils/thumb.js';

	export default {
		name: "TuiGoodsItem",
		mixins: [thumbMixin],
		props: {
			goods: {
				type: Object,
				default: null
			}
		},
		computed: {
			// 展示销量：优先运营在后台填的虚拟销量，没填才回退真实销量。
			// 口径与 GoodsListForm 一致 —— 后端两个字段都下发、不在后端替换 sales，
			// 因为 sales 的语义被很多调用方依赖，替换会改掉所有列表接口的含义。
			// 用 == null 判断而不是 || ：虚拟销量填 0 是运营的明确意图（就是要显示 0），
			// 用 || 会把 0 当成"没填"而回退到真实销量，反而把想藏的真实销量露出来。
			displaySales() {
				const g = this.goods || {};
				return (g.virtual_sales === undefined || g.virtual_sales === null || g.virtual_sales === '')
					? (g.sales || 0)
					: g.virtual_sales;
			},
			// 价格拆成整数和小数两截:整数大、小数小(同商品列表页)
			priceInt() {
				const s = String(this.goods.price == null ? '' : this.goods.price);
				const i = s.indexOf('.');
				return i < 0 ? s : s.slice(0, i);
			},
			priceDec() {
				const s = String(this.goods.price == null ? '' : this.goods.price);
				const i = s.indexOf('.');
				return i < 0 ? '' : s.slice(i);
			},
			// 有原价且高于现价才显示划线价;原价 0.00 = 没填
			hasOrigin() {
				const g = this.goods || {};
				return g.is_negotiable != 1 && Number(g.origin_price) > 0 && Number(g.origin_price) > Number(g.price);
			}
		}
	}
</script>

<style scoped>
	.tui-pro-item {
		width: 100%;
		margin-bottom: 10rpx;
		background: #fff;
		box-sizing: border-box;
		/* 20rpx 和商品列表页、首页商品组一致 */
		border-radius: 20rpx;
		overflow: hidden;
		transition: all 0.15s ease-in-out;
	}

	.tui-pro-img {
		width: 100%;
		display: block;
		height: 375rpx;
	}

	.tui-pro-content {
		box-sizing: border-box;
		padding: 16rpx 18rpx 18rpx;
	}

	/* 固定两行高:标题短的卡片价格行也和隔壁对齐。
	   原来标签上写死 70rpx、这里又写 68rpx,两处打架,只留这一处 */
	.tui-pro-tit {
		font-weight: bold;
		color: #2e2e2e;
		font-size: 26rpx;
		line-height: 36rpx;
		height: 72rpx;
		word-break: break-all;
		overflow: hidden;
		text-overflow: ellipsis;
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 2;
	}

	/* 价格 + 原价 + 已售在左,购物车钮在右,一行 */
	.tui-pro-bottom {
		margin-top: 14rpx;
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.tui-pro-price-line {
		flex: 1;
		min-width: 0;
		display: flex;
		align-items: baseline;
		white-space: nowrap;
		overflow: hidden;
	}

	.tui-sale-price {
		flex-shrink: 0;
		display: flex;
		align-items: baseline;
		font-weight: bold;
		font-variant-numeric: tabular-nums;
		color: var(--theme-color, #D71F1F);
	}

	.tui-sym,
	.tui-dec {
		font-size: 22rpx;
	}

	.tui-int {
		font-size: 38rpx;
		line-height: 1;
	}

	.tui-int.sm {
		font-size: 30rpx;
	}

	.tui-factory-price {
		flex-shrink: 0;
		margin-left: 8rpx;
		font-size: 22rpx;
		color: #a0a0a0;
		text-decoration: line-through;
	}

	/* 放不下时先收起「已售」,价格和原价不被挤。
	   nowrap 必须写在它自己身上:H5 的 <text> 自带 pre-wrap,父级的 nowrap 管不到,会被挤成一字一行 */
	.tui-pro-sold {
		min-width: 0;
		white-space: nowrap;
		margin-left: 10rpx;
		font-size: 22rpx;
		color: #9a9ea6;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	/* 整张卡片可点进详情,这个钮只是入口提示,不单独加购(加购要选规格,在详情页做) */
	.tui-pro-go {
		flex-shrink: 0;
		margin-left: 8rpx;
		width: 48rpx;
		height: 48rpx;
		border-radius: 50%;
		background: var(--theme-color, #D71F1F);
		box-shadow: 0 4rpx 12rpx var(--theme-shadow);
		display: flex;
		align-items: center;
		justify-content: center;
	}
</style>
