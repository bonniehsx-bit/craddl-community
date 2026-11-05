<template>
	<tui-page>
		<!-- 
			[核心] 显式绑定 --theme-color 变量 
			确保小程序内 slot 内容能读取到主题色
		-->
		<view class="container" :style="{ '--theme-color': themeColor }">
			<!-- 顶部搜索框 -->
			<view class="tui-searchbox">
				<view class="tui-search-input" @tap="search">
					<icon type="search" :size="13" color="#999"></icon>
					<text class="tui-search-text">搜索商品名称</text>
				</view>
			</view>

			<!-- 主体内容 -->
			<view class="main-content">
				<!-- 骨架屏 -->
				<view class="skeleton-wrapper" v-if="categoryLoading" :style="{ height: scrollViewHeight + 'px'}">
					<view class="skeleton-left">
						<view class="skeleton-nav-item active"></view>
						<view class="skeleton-nav-item" v-for="i in 5" :key="i"></view>
					</view>
					<view class="skeleton-right">
						<view class="skeleton-sub-nav">
							<view class="skeleton-tab" v-for="i in 4" :key="i"></view>
						</view>
						<view class="skeleton-sort-bar">
							<view class="skeleton-sort"></view>
							<view class="skeleton-sort"></view>
						</view>
						<view class="skeleton-goods-item" v-for="i in 4" :key="i">
							<view class="skeleton-img"></view>
							<view class="skeleton-info">
								<view class="skeleton-title"></view>
								<view class="skeleton-line"></view>
								<view class="skeleton-line short"></view>
							</view>
						</view>
					</view>
				</view>

				<!-- 真实内容 -->
				<template v-else>
					<!-- 左侧一级分类导航 -->
					<scroll-view scroll-y scroll-with-animation class="tab-view" :scroll-into-view="scrollViewId"
						:style="{ height: scrollViewHeight + 'px'}">
						<view :id="`id_${index}`" v-for="(item, index) in categoryList" :key="item.id"
							class="tab-bar-item" :class="{'active': currentTab === index}" :data-current="index"
							@tap.stop="swichNav">
							<text>{{ item.name }}</text>
						</view>
					</scroll-view>

					<!-- 右侧内容区 -->
					<view class="right-box" :style="{ height: scrollViewHeight + 'px'}">
						<view v-if="currentCategory" style="height: 100%; display: flex; flex-direction: column;">
							
							<!-- 二级分类横向滚动Tab -->
							<scroll-view 
								v-if="subCategoryTabs.length > 1"
								scroll-x 
								class="sub-category-scroll" 
								:scroll-with-animation="true"
								:show-scrollbar="false"
								enable-flex="true"
							>
								<view class="sub-category-item" v-for="(subCat, index) in subCategoryTabs"
									:key="subCat.id" :class="{'sub-active': currentSubCatIndex === index}"
									@click="switchSubNav(index)">
									{{subCat.name}}
								</view>
							</scroll-view>

							<!-- 排序选项 -->
							<view class="sort-bar">
								<view class="sort-item" :class="{'sort-active': sortBy === 'sales'}"
									@click="changeSort('sales')">
									<text>销量</text>
									<view class="sort-arrows">
										<!-- [核心] 箭头颜色动态绑定主题色 -->
										<tui-icon name="arrowup" :size="12"
											:color="sortBy === 'sales' && sortOrder === 'asc' ? themeColor : '#999'">
										</tui-icon>
										<tui-icon name="arrowdown" :size="12"
											:color="sortBy === 'sales' && sortOrder === 'desc' ? themeColor : '#999'">
										</tui-icon>
									</view>
								</view>
								<view class="sort-item" :class="{'sort-active': sortBy === 'price'}"
									@click="changeSort('price')">
									<text>价格</text>
									<view class="sort-arrows">
										<tui-icon name="arrowup" :size="12"
											:color="sortBy === 'price' && sortOrder === 'asc' ? themeColor : '#999'">
										</tui-icon>
										<tui-icon name="arrowdown" :size="12"
											:color="sortBy === 'price' && sortOrder === 'desc' ? themeColor : '#999'">
										</tui-icon>
									</view>
								</view>
							</view>

							<!-- 商品列表展示 -->
							<scroll-view scroll-y class="goods-scroll-list"
								:style="{height: goodsScrollHeight + 'px'}" @scrolltolower="loadMoreGoods">
								<view class="goods-list" v-if="productList.length > 0">
									<view class="goods-item" v-for="goods in productList" :key="goods.id"
										@click="$T.go('/others/goods/goods?id='+goods.id)">
										<image class="goods-img" :src="goods.cover_pic" mode="aspectFill" lazy-load>
										</image>
										<view class="goods-info">
											<view class="goods-name">{{goods.name}}</view>
											<!-- 商品小标题（Goods.subtitle）—— 仅在有值时显示，占位补足标题下空白 -->
											<view class="goods-subtitle" v-if="goods.subtitle">{{goods.subtitle}}</view>
											<view class="goods-tags">
												<text class="tag" v-if="goods.points">预计获得 {{goods.points}} {{ $noun('integral') }}</text>
											</view>
											<view class="goods-sales">销量{{goods.sales || 0}}</view>
											<view class="goods-price-box">
												<view class="goods-price"><text class="rmb">¥</text>{{goods.price}}</view>
												<view class="original-price" v-if="goods.origin_price > 0">¥{{goods.origin_price}}</view>
												
												<!-- [核心] 加入购物车按钮使用主题色 -->
												<view class="add-to-cart-btn" :style="{ backgroundColor: themeColor }">
													<tui-icon name="plus" :size="16" color="#fff"></tui-icon>
												</view>
											</view>
										</view>
									</view>
								</view>

								<!-- 加载状态 -->
								<tui-loadmore v-if="productLoading" :index="3"></tui-loadmore>
								<tui-nomore v-if="!productHasMore && productList.length > 0"></tui-nomore>
								<tui-no-data :fixed="false" imgWidth="150rpx" imgHeight="150rpx"
									v-if="productList.length === 0 && !productLoading">该分类下暂无商品</tui-no-data>
							</scroll-view>
						</view>
					</view>
				</template>
			</view>
		</view>
	</tui-page>
</template>

<script>
	// [核心] 引入 Vuex
	import { mapState } from 'vuex';

	export default {
		data() {
			return {
				categoryList: [], 
				currentTab: 0, 
				currentSubCatIndex: 0, 

				productList: [], 
				productPage: 1, 
				productHasMore: true, 
				productLoading: false, 

				sortBy: 'sales', 
				sortOrder: 'desc', 

				scrollViewId: "id_0",
				scrollViewHeight: 0,
				categoryLoading: true, 
				hasBottomNav: true, 
			};
		},
		computed: {
			// [核心] 获取主题色
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D6201F'
			}),

			currentCategory() {
				return this.categoryList.length > 0 ? this.categoryList[this.currentTab] : null;
			},
			subCategoryTabs() {
				if (this.currentCategory && this.currentCategory.childs && this.currentCategory.childs.length > 0) {
					return [{
						id: 'all',
						name: '全部'
					}, ...this.currentCategory.childs];
				}
				return [{
					id: 'all',
					name: '全部'
				}];
			},
			goodsScrollHeight() {
				let subtractHeight = 88;
				if (this.subCategoryTabs.length > 1) {
					subtractHeight += 80; 
				}
				return this.scrollViewHeight - uni.upx2px(subtractHeight);
			}
		},
		onLoad: function(options) {
			if (options.hasNav === '0') {
				this.hasBottomNav = false;
			}
			uni.getSystemInfo({
				success: (res) => {
					const searchBoxHeight = uni.upx2px(100);
					const bottomNavHeight = this.hasBottomNav ? uni.upx2px(100) : 0;
					this.scrollViewHeight = res.windowHeight - searchBoxHeight - bottomNavHeight;
				}
			});
			this.getCategoryTree();
		},
		methods: {
			async getCategoryTree() {
				try {
					const res = await this.$request({
						url: '/api/cat/list',
						method: 'GET'
					});
					if (res.code === 0 && res.data.list.length > 0) {
						this.categoryList = res.data.list;
						this.getProductList(true);
					}
				} catch (e) {
					// 模拟数据
					this.categoryList = Array.from({length: 10}, (_, i) => ({
						id: i,
						name: `分类${i}`,
						childs: i % 2 === 0 ? Array.from({length: 8}, (_, j) => ({id: j, name: `子分类${j}`})) : []
					}));
					this.getProductList(true);
				} finally {
					this.categoryLoading = false;
				}
			},
			async getProductList(isRefresh = false) {
				if (this.productLoading) return;
				this.productLoading = true;

				if (isRefresh) {
					this.productPage = 1;
					this.productList = [];
					this.productHasMore = true;
				}
				if (!this.productHasMore) {
					this.productLoading = false;
					return;
				}
				try {
					const params = {
						cat_id: this.currentCategory ? this.currentCategory.id : '',
						sub_cat_id: this.subCategoryTabs[this.currentSubCatIndex] ? this.subCategoryTabs[this.currentSubCatIndex].id : '',
						sort_by: this.sortBy,
						sort_order: this.sortOrder,
						page: this.productPage,
						limit: 10
					};
					const res = await this.$request({
						url: '/api/goods/list',
						method: 'GET',
						data: params
					});

					if (res.code === 0) {
						const newProducts = res.data.list;
						this.productList = isRefresh ? newProducts : [...this.productList, ...newProducts];
						if (newProducts.length < 10 || res.data.pagination.current_page >= res.data.pagination.page_count) {
							this.productHasMore = false;
						}
						this.productPage++;
					} else {
						this.productHasMore = false;
					}
				} catch(e) {
					const mockList = Array.from({length: 10}, (_, i) => ({
						id: this.productList.length + i,
						name: '测试商品' + (this.productList.length + i),
						cover_pic: '',
						price: '99.00',
						origin_price: '199.00',
						sales: 100
					}));
					this.productList = isRefresh ? mockList : [...this.productList, ...mockList];
					if (this.productList.length > 30) this.productHasMore = false;
				} finally {
					this.productLoading = false;
				}
			},
			loadMoreGoods() {
				this.getProductList(false);
			},
			swichNav(e) {
				let cur = e.currentTarget.dataset.current;
				if (this.currentTab === cur) return;
				this.currentTab = cur;
				this.currentSubCatIndex = 0; 
				this.scrollViewId = `id_${cur > 3 ? cur - 2 : 0}`;
				this.getProductList(true);
			},
			switchSubNav(index) {
				if (this.currentSubCatIndex === index) return;
				this.currentSubCatIndex = index;
				this.getProductList(true);
			},
			changeSort(type) {
				if (this.sortBy === type) {
					this.sortOrder = this.sortOrder === 'desc' ? 'asc' : 'desc';
				} else {
					this.sortBy = type;
					this.sortOrder = 'desc';
				}
				this.getProductList(true);
			},
			search() {
				uni.navigateTo({
					url: '/others/search/search'
				});
			}
		}
	};
</script>

<style lang="scss" scoped>
	/* [核心] 定义 SCSS 变量指向 CSS 变量 */
	$theme-color: var(--theme-color);

	.container {
		display: flex;
		flex-direction: column;
		height: 100vh;
		background-color: #f6f6f6;
	}

	.tui-searchbox {
		width: 100%;
		height: 100rpx;
		padding: 0 30rpx;
		box-sizing: border-box;
		background: #fff;
		display: flex;
		align-items: center;
		flex-shrink: 0;
	}

	.tui-search-input {
		width: 100%;
		height: 64rpx;
		background: #f1f1f1;
		border-radius: 32rpx;
		display: flex;
		align-items: center;
		padding: 0 30rpx;
		box-sizing: border-box;
	}

	.tui-search-text {
		padding-left: 16rpx;
		font-size: 28rpx;
		color: #999;
	}

	.main-content {
		display: flex;
		flex: 1;
		overflow: hidden;
	}

	/* 左侧一级分类 */
	.tab-view {
		width: 200rpx;
		background: #f6f6f6;
		flex-shrink: 0;
	}

	.tab-bar-item {
		width: 100%;
		height: 110rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 28rpx;
		color: #666;
		font-weight: 400;
	}

	.active {
		color: #333;
		font-weight: 600;
		background: #fff;
		position: relative;

		&::before {
			content: '';
			position: absolute;
			width: 8rpx;
			height: 40rpx;
			left: 0;
			top: 50%;
			transform: translateY(-50%);
			/* [核心] 左侧装饰条颜色使用变量 */
			background: $theme-color;
			border-radius: 0 4rpx 4rpx 0;
		}
	}

	/* 右侧内容区 */
	.right-box {
		flex: 1;
		background-color: #fff;
		box-sizing: border-box;
		display: flex;
		flex-direction: column;
		overflow: hidden; 
	}

	/* 二级分类Tab */
	.sub-category-scroll {
		width: 100%;
		height: 80rpx;
		box-sizing: border-box;
		padding: 0 10rpx;
		border-bottom: 1rpx solid #f6f6f6;
		flex-shrink: 0;
		white-space: nowrap; 
		display: flex;
		flex-direction: row;
		flex-wrap: nowrap; 
		justify-content: flex-start;
	}

	.sub-category-item {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0; 
		padding: 0 20rpx;
		height: 80rpx;
		font-size: 26rpx;
		color: #666;
		position: relative;
	}

	.sub-active {
		/* [核心] 选中文字颜色 */
		color: $theme-color;
		font-weight: bold;

		&::after {
			content: '';
			position: absolute;
			bottom: 10rpx;
			left: 50%;
			transform: translateX(-50%);
			width: 40rpx;
			height: 6rpx;
			/* [核心] 选中下划线颜色 */
			background: $theme-color;
			border-radius: 3rpx;
		}
	}

	/* 排序栏 */
	.sort-bar {
		display: flex;
		align-items: center;
		height: 88rpx;
		padding: 0 30rpx;
		flex-shrink: 0;
	}

	.sort-item {
		display: flex;
		align-items: center;
		margin-right: 40rpx;
		font-size: 26rpx;
		color: #333;
	}

	.sort-active {
		/* [核心] 排序文字选中色 */
		color: $theme-color;
		font-weight: bold;
	}

	.sort-arrows {
		display: flex;
		flex-direction: column;
		margin-left: 6rpx;
		height: 20px;
		justify-content: center;
	}

	/* 商品列表 */
	.goods-scroll-list {
		flex: 1;
		height: 0; 
	}

	.goods-list {
		padding: 0 24rpx;
	}

	.goods-item {
		display: flex;
		padding: 24rpx 0;
		border-bottom: 1rpx solid #f6f6f6;

		&:last-child {
			border-bottom: none;
		}
	}

	.goods-img {
		width: 200rpx;
		height: 200rpx;
		border-radius: 12rpx;
		flex-shrink: 0;
		margin-right: 20rpx;
		background-color: #f1f1f1;
	}

	.goods-info {
		flex: 1;
		display: flex;
		flex-direction: column;
		justify-content: space-between;
		min-width: 0;
	}

	.goods-name {
		font-size: 28rpx;
		color: #333;
		font-weight: 500;
		text-overflow: ellipsis;
		overflow: hidden;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
	}

	/* 商品小标题：浅灰、单行省略，填补标题与销量之间的空白 */
	.goods-subtitle {
		margin-top: 6rpx;
		font-size: 22rpx;
		color: #909399;
		line-height: 1.3;
		text-overflow: ellipsis;
		overflow: hidden;
		white-space: nowrap;
	}

	.goods-tags {
		margin-top: 8rpx;
		.tag {
			background-color: #f1f1f1;
			/* [核心] 积分文字颜色 */
			color: $theme-color;
			font-size: 20rpx;
			padding: 4rpx 10rpx;
			border-radius: 6rpx;
		}
	}

	.goods-sales {
		font-size: 24rpx;
		color: #999;
		margin-top: 8rpx;
	}

	.goods-price-box {
		display: flex;
		align-items: flex-end;
		margin-top: 8rpx;
	}

	.goods-price {
		/* [核心] 价格颜色 */
		color: $theme-color;
		font-size: 34rpx;
		font-weight: bold;

		.rmb {
			font-size: 24rpx;
		}
	}

	.original-price {
		font-size: 24rpx;
		color: #999;
		text-decoration: line-through;
		margin-left: 12rpx;
		padding-bottom: 6rpx;
	}

	.add-to-cart-btn {
		width: 44rpx;
		height: 44rpx;
		/* 背景色已在 template 动态绑定 */
		/* background-color: $theme-color; */
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		margin-left: auto;
	}

	/* 骨架屏 */
	@keyframes shimmer {
		0% { background-position: -100% 0; }
		100% { background-position: 100% 0; }
	}

	.skeleton-wrapper {
		display: flex;
		width: 100%;
		overflow: hidden;

		.skeleton-nav-item, .skeleton-tab, .skeleton-sort, .skeleton-img, .skeleton-title, .skeleton-line {
			background-image: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
			background-size: 200% 100%;
			animation: shimmer 1.5s infinite linear;
		}
	}

	.skeleton-left {
		width: 200rpx;
		background: #f6f6f6;
		flex-shrink: 0;
	}

	.skeleton-nav-item {
		width: 100%;
		height: 110rpx;
		&.active { background: #fff; }
	}

	.skeleton-right {
		flex: 1;
		padding: 0 24rpx;
		background-color: #fff;
	}

	.skeleton-sub-nav {
		display: flex;
		align-items: center;
		height: 80rpx;
		border-bottom: 1rpx solid #f6f6f6;
	}

	.skeleton-tab {
		width: 80rpx;
		height: 30rpx;
		border-radius: 8rpx;
		margin-right: 30rpx;
	}

	.skeleton-sort-bar {
		display: flex;
		align-items: center;
		height: 88rpx;
	}

	.skeleton-sort {
		width: 100rpx;
		height: 30rpx;
		border-radius: 8rpx;
		margin-right: 40rpx;
	}

	.skeleton-goods-item {
		display: flex;
		padding: 24rpx 0;
		border-bottom: 1rpx solid #f6f6f6;
	}

	.skeleton-img {
		width: 200rpx;
		height: 200rpx;
		border-radius: 12rpx;
		flex-shrink: 0;
		margin-right: 20rpx;
	}

	.skeleton-info {
		flex: 1;
		display: flex;
		flex-direction: column;
		justify-content: space-around;
	}

	.skeleton-title {
		width: 90%;
		height: 32rpx;
		border-radius: 8rpx;
	}

	.skeleton-line {
		width: 70%;
		height: 24rpx;
		border-radius: 8rpx;
		&.short { width: 40%; }
	}
</style>