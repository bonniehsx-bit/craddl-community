<template>
	<view class="premium-goods-container"
		:style="{ padding: xc.is_padding == '0' ? '0' : '0 24rpx', backgroundColor: xc.background }">

		<scroll-view v-if="tabs.length" scroll-x class="premium-tabs" scroll-with-animation
			:scroll-into-view="'tab-' + currentTab">
			<view class="tab-item" v-for="(tab, index) in tabs" :key="index" :id="'tab-' + index"
				@click="tabClick(index)" :class="{ 'active': currentTab === index }">
				<text class="tab-text"
					:style="{ color: currentTab === index ? xc.tab_active_color : xc.tab_inactive_color }">
					{{ tab.name || tab.title || '分类' }}
				</text>
				<view class="tab-line" v-if="currentTab === index" :style="{ backgroundColor: xc.tab_line_color }">
				</view>
			</view>
		</scroll-view>

		<view class="goods-content" :class="{ 'card-minimal': xc.card_style === 'minimal' }">
			<tui-no-data v-if="list.length === 0" :fixed="false">暂无商品</tui-no-data>

			<scroll-view v-if="xc.type == 1 && list.length > 0" scroll-x="true" class="scroll-row-view">
				<view class="goods-card scroll-item" v-for="(goods, index) in list" :key="index" @click="pageTo(goods)"
					:class="'width-' + xc.columns">
					<view class="img-wrap" :style="goodsHeight">
						<image :src="goods.cover_pic" mode="aspectFill" class="img"></image>
					</view>
					<view class="info-wrap">
						<text class="title">{{ goods.name }}</text>
						<view class="bottom-action">
							<view class="flex-col">
								<view class="price-box" :style="{ color: xc.price_color }">
									<text class="symbol" v-if="goods.is_negotiable != 1">￥</text>
									<text class="amount">{{ goods.is_negotiable == 1 ? '面议' : shownPrice(goods.price) }}</text>
								</view>
								<view class="sales-box" v-if="xc.show_sales == 1"
									style="margin-bottom: 0; margin-top: 4rpx;">
									<text class="sales-text">{{ xc.sales_text }} {{ goods.virtual_sales }}</text>
								</view>
							</view>
							<view class="btn-wrap" v-if="xc.show_buy_btn == 1">
								<image :src="xc.cart_pic" v-if="xc.cart_type == 1" class="buybtn-icon"></image>
								<view v-if="xc.cart_type == 0" class="text-btn" :style="{
									backgroundColor: xc.cart_color,
									color: '#fff',
									borderRadius: xc.is_circle == 1 ? '30rpx' : '8rpx'
								}">
									{{ xc.cart_text }}
								</view>
							</view>
						</view>
					</view>
				</view>
			</scroll-view>

			<view v-else-if="xc.type == 0 && xc.columns == 0 && list.length > 0" class="layout-banner">
				<view class="goods-card banner-item" v-for="(goods, index) in list" :key="index" @click="pageTo(goods)">
					<image :src="goods.cover_pic" mode="aspectFill" class="banner-img" :style="goodsHeight"></image>
					<view class="banner-overlay">
						<view class="banner-title">{{ goods.name }}</view>
						<view class="banner-bottom flex-x-between flex-y-center">
							<view class="flex-col">
								<view class="price-box" :style="{ color: xc.price_color }">
									<text class="symbol" v-if="goods.is_negotiable != 1">￥</text>
									<text class="amount">{{ goods.is_negotiable == 1 ? '面议' : shownPrice(goods.price) }}</text>
								</view>
								<view class="sales-box banner-sales" v-if="xc.show_sales == 1"
									style="margin-bottom: 0; margin-top: 4rpx;">
									<text class="sales-text">{{ xc.sales_text }} {{ goods.virtual_sales }}</text>
								</view>
							</view>
							<view class="btn-wrap" v-if="xc.show_buy_btn == 1">
								<image :src="xc.cart_pic" v-if="xc.cart_type == 1" class="buybtn-icon"></image>
								<view v-if="xc.cart_type == 0" class="text-btn" :style="{
									backgroundColor: xc.cart_color,
									color: '#fff',
									borderRadius: xc.is_circle == 1 ? '30rpx' : '8rpx'
								}">
									{{ xc.cart_text }}
								</view>
							</view>
						</view>
					</view>
				</view>
			</view>

			<view v-else-if="(xc.columns == 1 || xc.type == 2) && list.length > 0" class="layout-list">
				<view class="goods-card list-item flex-row" v-for="(goods, index) in list" :key="index"
					@click="pageTo(goods)">
					<view class="list-img-wrap" :style="goodsHeight">
						<image :src="goods.cover_pic" mode="aspectFill" class="img"></image>
					</view>
					<view class="list-info-wrap flex-col flex-x-between">
						<view>
							<view class="title">{{ goods.name }}</view>
						</view>
						<view class="bottom-action flex-x-between flex-y-center">
							<view class="flex-col">
								<view class="price-box" :style="{ color: xc.price_color }">
									<text class="symbol" v-if="goods.is_negotiable != 1">￥</text>
									<text class="amount">{{ goods.is_negotiable == 1 ? '面议' : shownPrice(goods.price) }}</text>
								</view>
								<view class="sales-box" v-if="xc.show_sales == 1"
									style="margin-bottom: 0; margin-top: 4rpx;">
									<text class="sales-text">{{ xc.sales_text }} {{ goods.virtual_sales }}</text>
								</view>
							</view>
							<view class="btn-wrap" v-if="xc.show_buy_btn == 1">
								<image :src="xc.cart_pic" v-if="xc.cart_type == 1" class="buybtn-icon"></image>
								<view v-if="xc.cart_type == 0" class="text-btn" :style="{
									backgroundColor: xc.cart_color,
									color: '#fff',
									borderRadius: xc.is_circle == 1 ? '30rpx' : '8rpx'
								}">
									{{ xc.cart_text }}
								</view>
							</view>
						</view>
					</view>
				</view>
			</view>

			<view v-else-if="xc.columns >= 2 && list.length > 0" class="layout-grid"
				:style="{ gridTemplateColumns: `repeat(${xc.columns}, 1fr)` }">
				<view class="goods-card grid-item" v-for="(goods, index) in list" :key="index" @click="pageTo(goods)">
					<view class="img-wrap" :style="goodsHeight">
						<image :src="goods.cover_pic" mode="aspectFill" class="img"></image>
					</view>
					<view class="info-wrap">
						<text class="title">{{ goods.name }}</text>
						<view class="bottom-action flex-x-between flex-y-center">
							<view class="flex-col">
								<view class="price-box" :style="{ color: xc.price_color }">
									<text class="symbol" v-if="goods.is_negotiable != 1">￥</text>
									<text class="amount">{{ goods.is_negotiable == 1 ? '面议' : shownPrice(goods.price) }}</text>
								</view>
								<view class="sales-box" v-if="xc.show_sales == 1"
									style="margin-bottom: 0; margin-top: 4rpx;">
									<text class="sales-text">{{ xc.sales_text }} {{ goods.virtual_sales }}</text>
								</view>
							</view>
							<view class="btn-wrap" v-if="xc.show_buy_btn == 1">
								<image :src="xc.cart_pic" v-if="xc.cart_type == 1" class="buybtn-icon"></image>
								<view v-if="xc.cart_type == 0" class="text-btn" :style="{
									backgroundColor: xc.cart_color,
									color: '#fff',
									borderRadius: xc.is_circle == 1 ? '30rpx' : '8rpx'
								}">
									{{ xc.cart_text }}
								</view>
							</view>
						</view>
					</view>
				</view>
			</view>

		</view>
	</view>
</template>

<script>
export default {
	props: {
		value: {
			type: Object,
			default: () => ({})
		},
	},
	data() {
		return {
			currentTab: 0,
			// 只放选项卡名字(模板只用到名字),各选项卡的整份商品放在 this.tabSource、不进 data:
			// 小程序会把 data 整个 setData 给视图层,原来这里放整份列表,首页一个模块就多传 36KB
			tabs: [],
			list: []
		}
	},
	watch: {
		value: {
			handler(newVal) {
				this.initData(newVal);
			},
			deep: true,
			immediate: true
		}
	},
	computed: {
		goodsHeight() {
			return `height: ${this.xc.cover_pic_height || 340}rpx;`;
		},
		xc() {
			// 不带 list(各选项卡的整份商品):模板用不到,小程序却会把 computed 整个 setData 给视图层,等于又多传一份
			let v = Object.assign({}, this.value);
			delete v.list;
			let type = v.goods_style !== undefined ? (v.goods_style === 0 ? 0 : v.goods_style === 1 ? 1 : 2) : (v.type || 0);
			let columns = v.goods_style === 0 ? 2 : (v.columns !== undefined ? v.columns : 2);

			return {
				...v,
				type,
				columns,
				scrollable: v.scrollable !== false,
				show_buy_btn: v.show_buy_btn !== undefined ? v.show_buy_btn : 1,
				cart_type: v.cart_type !== undefined ? v.cart_type : 0,
				cart_text: v.cart_text || '购买',
				cart_color: v.cart_color || v.active_color || '#FF4A4A',
				price_color: v.price_color || '#FF4A4A',
				tab_active_color: v.active_color || v.tab_active_color || '#333333',
				tab_inactive_color: v.inactive_color || v.tab_inactive_color || '#999999',
				tab_line_color: v.active_color || v.tab_line_color || '#FF4A4A',
				background: v.background || 'transparent',
				is_padding: v.is_padding !== undefined ? v.is_padding : '1',
				show_sales: v.show_sales !== undefined ? v.show_sales : 1,
				sales_text: v.sales_text || '已售',
				// 卡片风格:default 原样 / minimal 简约(字号小一号、窄体价格、淡阴影)
				card_style: v.card_style || 'default'
			}
		}
	},
	methods: {
		/** 简约卡片:整数价不带「.00」 */
		shownPrice(price) {
			const s = String(price == null ? '' : price);
			return this.xc.card_style === 'minimal' ? s.replace(/\.0+$/, '') : s;
		},
		initData(val) {
			if (val && val.list && val.list.length > 0 && val.list[0].goods_list) {
				this.setTabs(val.list);
				this.list = this.tabSource[this.currentTab].goods_list || [];
			} else if (val && val.id) {
				this.$request({
					url: "/api/mall/tab-goods",
					data: { id: val.id }
				}).then(res => {
					if (res.code == 0 && res.data && res.data.list) {
						this.setTabs(res.data.list);
						this.list = this.tabSource[0].list || [];
					}
				})
			}
		},
		setTabs(source) {
			this.tabSource = source;
			this.tabs = source.map(t => ({ name: t.name, title: t.title }));
		},
		tabClick(index) {
			this.currentTab = index;
			let currentData = this.tabSource[index];
			this.list = currentData.goods_list || currentData.list || [];
		},
		pageTo(row) {
			uni.navigateTo({
				url: "/others/goods/goods?id=" + row.id
			})
		}
	}
}
</script>

<style lang="scss" scoped>
/* 隐藏滚动条 */
::-webkit-scrollbar {
	display: none;
	width: 0;
	height: 0;
	color: transparent;
}

.premium-goods-container {
	box-sizing: border-box;
	width: 100%;
}

/* ================= 1. 高级感 Tabs 样式 ================= */
.premium-tabs {
	white-space: nowrap;
	width: 100%;
	padding: 20rpx 0;

	.tab-item {
		display: inline-block;
		padding: 0 32rpx;
		position: relative;
		text-align: center;
		transition: all 0.3s ease;

		.tab-text {
			font-size: 28rpx;
			transition: all 0.3s;
		}

		&.active {
			.tab-text {
				font-size: 32rpx;
				font-weight: 600;
			}
		}

		.tab-line {
			position: absolute;
			bottom: -10rpx;
			left: 50%;
			transform: translateX(-50%);
			width: 40rpx;
			height: 6rpx;
			border-radius: 6rpx;
		}
	}
}

.goods-content {
	margin-top: 20rpx;
	padding-bottom: 20rpx;
}

/* ================= 2. 通用商品卡片样式 (核心质感来源) ================= */
.goods-card {
	background-color: #FFFFFF;
	border-radius: 20rpx;
	overflow: hidden;
	box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.03);
	box-sizing: border-box;

	.img-wrap {
		width: 100%;
		overflow: hidden;
		background-color: #f8f8f8;

		.img {
			width: 100%;
			height: 100%;
			transition: transform 0.3s;
		}
	}

	.info-wrap {
		padding: 20rpx;
	}

	.title {
		font-size: 28rpx;
		color: #222222;
		line-height: 40rpx;
		height: 80rpx;
		font-weight: 500;
		overflow: hidden;
		text-overflow: ellipsis;
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 2;
		margin-bottom: 12rpx;
		/* 标题与销量的间距微调 */
	}

	/* 价格排版优化：符号小，数字大 */
	.price-box {
		font-weight: bold;
		display: flex;
		align-items: baseline;

		.symbol {
			font-size: 24rpx;
			margin-right: 2rpx;
		}

		.amount {
			font-size: 34rpx;
		}
	}
}

/* 购物车按钮统一样式 */
.btn-wrap {
	.buybtn-icon {
		width: 52rpx;
		height: 52rpx;
		display: block;
	}

	.text-btn {
		padding: 8rpx 24rpx;
		font-size: 24rpx;
		font-weight: 500;
	}
}

/* ================= 3. 各布局定制 ================= */

.scroll-row-view {
	white-space: nowrap;
	width: 100%;
	padding-bottom: 10rpx;

	.scroll-item {
		display: inline-block;
		vertical-align: top;
		margin-right: 20rpx;
		white-space: normal;

		&.width-1 {
			width: 85%;
		}

		&.width-2 {
			width: 45%;
		}

		&.width-3 {
			width: 30%;
		}

		&.width-4 {
			width: 22%;
		}
	}
}

.layout-banner {
	.banner-item {
		position: relative;
		margin-bottom: 24rpx;

		.banner-img {
			width: 100%;
			display: block;
		}

		.banner-overlay {
			position: absolute;
			bottom: 0;
			left: 0;
			width: 100%;
			background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0) 100%);
			padding: 40rpx 24rpx 24rpx;
			box-sizing: border-box;

			.banner-title {
				color: #FFFFFF;
				font-size: 32rpx;
				font-weight: bold;
				margin-bottom: 16rpx;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}
		}
	}
}

.layout-list {
	.list-item {
		margin-bottom: 24rpx;
		padding: 20rpx;

		.list-img-wrap {
			width: 240rpx;
			border-radius: 12rpx;
			overflow: hidden;
			flex-shrink: 0;

			.img {
				width: 100%;
				height: 100%;
			}
		}

		.list-info-wrap {
			flex: 1;
			padding-left: 24rpx;

			.title {
				height: auto;
				max-height: 80rpx;
			}
		}
	}
}

.layout-grid {
	display: grid;
	grid-gap: 20rpx;

	.grid-item {
		width: 100%;
		margin-bottom: 0;
	}
}

/* Flex 辅助类 */
.flex-row {
	display: flex;
	flex-direction: row;
}

.flex-col {
	display: flex;
	flex-direction: column;
}

.flex-x-between {
	display: flex;
	justify-content: space-between;
}

.flex-y-center {
	display: flex;
	align-items: center;
}

/* ================= 4. 销量展示高级样式 ================= */

/* 常规卡片中的微标签样式 */
.sales-box {
	display: inline-flex;
	align-items: center;
	background-color: #F6F6F6;
	/* 高级感的淡灰底色 */
	padding: 4rpx 14rpx;
	border-radius: 8rpx;
	/* 微圆角 */
	margin-bottom: 16rpx;

	.sales-text {
		font-size: 22rpx;
		color: #A0A0A0;
		/* 柔和的字体颜色 */
		line-height: 1.2;
	}
}

/* Banner 模式下的玻璃质感样式 */
.banner-sales {
	background-color: rgba(255, 255, 255, 0.2);
	/* 半透明底 */
	backdrop-filter: blur(8px);
	/* 毛玻璃特效 */
	border: 1rpx solid rgba(255, 255, 255, 0.25);
	/* 淡淡的高光白边 */
	border-radius: 8rpx;

	.sales-text {
		color: #FFFFFF;
		/* 白色字体更清晰 */
		text-shadow: 0 2rpx 4rpx rgba(0, 0, 0, 0.2);
		/* 增加一点文字投影防看不清 */
	}
}

/* ── 简约卡片(后台「卡片风格 → 简约」),和商品组 tui-goods-group 同一口径 ── */
.card-minimal .goods-card {
	border: none;
	box-shadow: 0 4rpx 16rpx rgba(42, 37, 34, 0.08);
}
.card-minimal .goods-card .title {
	font-size: 24rpx;
	line-height: 34rpx;
	height: 68rpx;
	color: #3a3532;
	font-weight: 400;
}
.card-minimal .goods-card .price-box {
	font-family: "Avenir Next Condensed", "DIN Condensed", "DIN Alternate", "Helvetica Neue", sans-serif;
	font-weight: 800;
}
.card-minimal .goods-card .price-box .symbol {
	font-size: 20rpx;
}
.card-minimal .goods-card .price-box .amount {
	font-size: 32rpx;
}
.card-minimal .goods-card .sales-text {
	font-size: 20rpx;
}
</style>