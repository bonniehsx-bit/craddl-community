<template>
	<!-- 风格组件 · 分类宫格:邮票 / 卡片 / 圆形三种样式;角标默认显示分类在售商品数(后端 DiyHydrateHelper 补) -->
	<view v-if="items.length" class="cg" :style="'background:' + d.bg_color + ';padding:' + (d.is_padding == 1 ? '12rpx 24rpx' : '12rpx 0')">
		<view class="grid">
			<view v-for="(it, i) in items" :key="i" class="cell" :class="'is-' + (d.style || 'stamp')" :style="cellStyle" @click="go(it)">
				<view class="box" :style="'background:' + d.bg_color2">
					<view class="pic">
						<image v-if="it.pic_url || it.cat_pic" class="pic-img" :src="it.pic_url || it.cat_pic" mode="aspectFill"></image>
					</view>
					<text class="name" :style="'color:' + d.text_color">{{ it.name || it.cat_name || '' }}</text>
				</view>
				<text v-if="badge(it)" class="badge" :style="'background:' + d.accent_color">{{ badge(it) }}</text>
				<view v-if="d.style === 'stamp' && markLines.length" class="mark" :style="'color:' + d.sub_color + ';border-color:' + d.sub_color">
					<text v-for="(l, j) in markLines" :key="j" class="mark-line">{{ l }}</text>
				</view>
			</view>
		</view>
	</view>
</template>

<script>
	import { lines, goLink } from '../tui-style-kit/style-kit.js';

	export default {
		name: 'tui-cat-grid',
		props: {
			value: { type: Object, default: () => ({}) }
		},
		computed: {
			d() {
				return this.value || {};
			},
			items() {
				return (this.d.items || []).filter(it => it && (it.cat_id || it.url));
			},
			markLines() {
				return lines(this.d.mark_text, 2);
			},
			cellStyle() {
				// 3 列 / 4 列:按列数算宽度,间距 16rpx(用 flex 换行,小程序老基础库不一定支持 grid)
				const cols = Number(this.d.columns) === 4 ? 4 : 3;
				return 'width:calc((100% - ' + (cols - 1) * 16 + 'rpx) / ' + cols + ')';
			}
		},
		methods: {
			badge(it) {
				if (it.badge) return it.badge;
				if (this.d.show_count != 1 || it.goods_count === undefined) return '';
				return it.goods_count + (this.d.count_suffix || '');
			},
			go(it) {
				goLink(it.url || (it.cat_id ? '/others/goods-list/goods-list?cat_id=' + it.cat_id : ''));
			}
		}
	};
</script>

<style scoped>
	.grid { display: flex; flex-direction: row; flex-wrap: wrap; gap: 20rpx 16rpx; }
	.cell { position: relative; box-sizing: border-box; }
	.box { padding: 8rpx; }
	.pic { height: 140rpx; background: #f0ebe1; overflow: hidden; }
	.pic-img { width: 100%; height: 100%; display: block; }
	.name { display: block; margin-top: 10rpx; font-size: 24rpx; font-weight: 700; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
	.badge { position: absolute; left: 18rpx; top: 18rpx; font-size: 18rpx; color: #ffffff; padding: 2rpx 8rpx; border-radius: 4rpx; }
	/* 邮票:外圈锯齿边,内容区整块保留 */
	.is-stamp { padding: 9rpx; background: #ffffff; filter: drop-shadow(0 4rpx 6rpx rgba(0, 0, 0, 0.12));
		-webkit-mask: linear-gradient(#000 0 0) content-box, radial-gradient(circle 6rpx at 9rpx 9rpx, transparent 96%, #000) -9rpx -9rpx / 18rpx 18rpx; }
	.is-card .box { border-radius: 16rpx; box-shadow: 0 2rpx 10rpx rgba(0, 0, 0, 0.08); }
	.is-card .pic { border-radius: 12rpx; }
	.is-circle .box { background: transparent !important; }
	.is-circle .pic { width: 120rpx; height: 120rpx; margin: 0 auto; border-radius: 50%; }
	.mark { position: absolute; right: 4rpx; top: 96rpx; width: 76rpx; height: 76rpx; border-radius: 50%; border: 2rpx solid;
		display: flex; flex-direction: column; align-items: center; justify-content: center; transform: rotate(-18deg); }
	.mark-line { display: block; font-size: 12rpx; font-weight: 700; line-height: 1.1; }
</style>
