<template>
	<tui-page>
		<view class="container">
			<view class="tui-searchbox">
				<view class="tui-search-input" @tap="search">
					<icon type="search" :size="13" color="#999"></icon>
					<text class="tui-search-text">搜索商品</text>
				</view>
			</view>

			<!-- 主体内容 -->
			<view class="main-content">
				<!-- 左侧导航 -->
				<scroll-view scroll-y scroll-with-animation class="tab-view" :scroll-into-view="scrollViewId" :style="{ height: scrollViewHeight + 'px'}">
					<view :id="`id_${index}`" v-for="(item, index) in categoryList" :key="index" class="tab-bar-item"
						:class="{'active': currentTab === index}" :data-current="index" @tap.stop="swichNav">
						<text>{{ item.name }}</text>
					</view>
				</scroll-view>

				<!-- 右侧内容 -->
				<scroll-view scroll-y class="right-box" :style="{ height: scrollViewHeight + 'px'}">
					<view class="page-view" v-if="currentCategory">
						<!-- [UI-Revamp] 顶部 Banner/广告图 -->
						<image class="top-banner" :src="currentCategory.adv_pic" mode="widthFix" v-if="currentCategory.adv_pic"></image>

						<!-- [UI-Revamp] 子分类及商品卡片 -->
						<block v-if="currentCategory.childs && currentCategory.childs.length > 0">
							<view class="category-card" v-for="cat in currentCategory.childs" :key="cat.id">
								<view class="card-header">
									<text class="card-title">{{ cat.name }}</text>
									<view class="card-more" @click="$T.go('/others/goods-list/goods-list?cat_id='+cat.id)">
										<text>查看全部</text>
										<tui-icon name="arrowright" :size="14" color="#999"></tui-icon>
									</view>
								</view>
								<view class="card-content">
									<!-- 商品网格 -->
									<view class="g-grid" v-if="cat.goods && cat.goods.length > 0">
										<view class="g-item" v-for="goods in cat.goods" :key="goods.id" @click="$T.go('/others/goods/goods?id='+goods.id)">
											<image class="g-image" :src="goods.cover_pic" mode="aspectFill" lazy-load />
											<view class="g-name">{{ goods.name }}</view>
											<view class="g-price">￥{{ goods.price }}</view>
										</view>
									</view>
									<!-- 无商品提示 -->
									<tui-no-data :fixed="false" imgWidth="150rpx" imgHeight="150rpx" v-else>该分类下暂无商品</tui-no-data>
								</view>
							</view>
						</block>
						<!-- 无子分类提示 -->
						<tui-no-data v-else>
							暂无子分类~
						</tui-no-data>
					</view>
					
					<!-- 初始加载时的骨架屏 -->
					<view class="skeleton-wrapper" v-if="loading && categoryList.length === 0">
						<view class="skeleton-banner"></view>
						<view class="skeleton-card" v-for="i in 2" :key="i">
							<view class="skeleton-header"></view>
							<view class="skeleton-grid">
								<view class="skeleton-item" v-for="j in 3" :key="j"></view>
							</view>
						</view>
					</view>
				</scroll-view>
			</view>
		</view>
	</tui-page>
</template>


<script>
	export default {
		data() {
			return {
				categoryList: [],
				currentTab: 0,
				scrollViewId: "id_0",
				scrollViewHeight: 0,
				loading: false,
			};
		},
		computed: {
			currentCategory() {
				if (this.categoryList.length > 0) {
					return this.categoryList[this.currentTab];
				}
				return null;
			}
		},
		onLoad: function(options) {
			// [优化] 计算可滚动区域的高度
			uni.getSystemInfo({
				success: (res) => {
					// 窗口高度 - 搜索框高度 - H5顶部的原生tabbar(如果存在)
					let topBarHeight = 55; // 搜索框高度
					// #ifdef H5
					topBarHeight += 44; // H5端顶部导航栏
					// #endif
					this.scrollViewHeight = res.windowHeight - uni.upx2px(100);
				}
			});
			
			// [优化] 先从缓存加载，再请求网络
			const cachedData = uni.getStorageSync('CAT_GOODS_DATA');
			if (cachedData) {
				this.categoryList = cachedData;
			}
			this.getCatGoods();
		},
		methods: {
			async getCatGoods() {
				// 防止重复加载
				if (this.loading) return;
				this.loading = true;
				try {
					const res = await this.$request({
						url: '/api/goods/cat-goods', // [注意] 确认您的接口路由
						method: 'get'
					});
					if (res.code === 0) {
						this.categoryList = res.data.list;
						uni.setStorageSync('CAT_GOODS_DATA', res.data.list);
					}
				} catch (e) {
					// handle error
				} finally {
					this.loading = false;
				}
			},
			swichNav(e) {
				let cur = e.currentTarget.dataset.current;
				if (this.currentTab == cur) {
					return false;
				} else {
					this.currentTab = cur;
					this.checkCor();
				}
			},
			checkCor() {
				if (this.currentTab > 6) {
					this.scrollViewId = `id_${this.currentTab - 2}`;
				} else {
					this.scrollViewId = `id_0`;
				}
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
	/* 整体布局 */
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
		justify-content: center;
		flex-shrink: 0;
	}
	.tui-search-input {
		width: 100%;
		height: 64rpx;
		background: #f6f6f6;
		border-radius: 32rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 26rpx;
		color: #999;
	}
	.tui-search-text {
		padding-left: 16rpx;
	}

	.main-content {
		display: flex;
		flex: 1;
		overflow: hidden;
	}

	/* [UI-Revamp] 左侧导航 */
	.tab-view {
		width: 180rpx;
		background: #f6f6f6;
		flex-shrink: 0;
	}
	.tab-bar-item {
		width: 180rpx;
		height: 100rpx;
		box-sizing: border-box;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 26rpx;
		color: #666;
		font-weight: 400;
		transition: all 0.3s ease;
		position: relative;
	}
	.active {
		color: #333;
		font-size: 28rpx;
		font-weight: 600;
		background: #fff;
		&::before {
			content: '';
			position: absolute;
			width: 8rpx;
			height: 40rpx;
			left: 0;
			background: linear-gradient(to bottom, #ff8c4a, #ff5757);
			border-radius: 0 4rpx 4rpx 0;
		}
	}

	/* 右侧内容 */
	.right-box {
		flex: 1;
		background-color: #fff;
		padding: 20rpx;
		box-sizing: border-box;
	}
	.top-banner {
		width: 100%;
		border-radius: 12rpx;
		margin-bottom: 20rpx;
		display: block; // 消除图片底部的间隙
	}
	/* [UI-Revamp] 卡片式设计 */
	.category-card {
		margin-bottom: 30rpx;
	}
	.card-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-bottom: 20rpx;
	}
	.card-title {
		font-size: 30rpx;
		font-weight: bold;
		color: #333;
	}
	.card-more {
		display: flex;
		align-items: center;
		font-size: 24rpx;
		color: #999;
	}
	
	/* [UI-Revamp] 商品网格 */
	.g-grid {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 20rpx;
	}
	.g-item {
		display: flex;
		flex-direction: column;
	}
	.g-image {
		width: 100%;
		height: 160rpx;
		border-radius: 12rpx;
		background-color: #f6f6f6;
	}
	.g-name {
		font-size: 24rpx;
		color: #333;
		margin-top: 10rpx;
		// 单行文本溢出
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.g-price {
		font-size: 26rpx;
		color: #ff5757;
		font-weight: 500;
		margin-top: 5rpx;
	}

	/* [UI-Revamp] 骨架屏 */
	@keyframes skeleton-blink {
		0% { background-color: #f0f0f0; }
		50% { background-color: #e0e0e0; }
		100% { background-color: #f0f0f0; }
	}
	.skeleton-wrapper {
		padding: 20rpx;
	}
	.skeleton-banner {
		width: 100%;
		height: 200rpx;
		border-radius: 12rpx;
		animation: skeleton-blink 1.5s infinite ease-in-out;
	}
	.skeleton-card {
		margin-top: 30rpx;
	}
	.skeleton-header {
		width: 40%;
		height: 30rpx;
		border-radius: 8rpx;
		animation: skeleton-blink 1.5s infinite ease-in-out;
	}
	.skeleton-grid {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 20rpx;
		margin-top: 20rpx;
	}
	.skeleton-item {
		width: 100%;
		height: 200rpx;
		border-radius: 12rpx;
		animation: skeleton-blink 1.5s infinite ease-in-out;
	}

</style>