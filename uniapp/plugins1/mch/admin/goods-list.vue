<template>
	<view class="page-container" :style="themeStyle">
		
		<!-- Tab 切换 -->
		<!-- 移除原 header-actions，Tabs 直接置顶 -->
		<view class="tabs-container">
			<view class="tab-item" v-for="(tab, index) in tabs" :key="index" :class="{'active': activeTab === index}"
				@click="switchTab(index)">
				{{ tab.name }}({{ tab.count }})
			</view>
		</view>

		<!-- 搜索栏 -->
		<view class="search-bar">
			<input class="search-input" v-model="keyword" placeholder="搜索我的商品" confirm-type="search"
				@confirm="onSearch" />
			<button class="search-btn" @click="onSearch">搜索</button>
			<button class="batch-btn" :class="{ on: batchMode }" @click="toggleBatch">
				{{ batchMode ? '取消' : '批量' }}
			</button>
		</view>

		<!-- 批量操作条:只在批量模式出现,平时不占地方 -->
		<view class="batch-bar" v-if="batchMode">
			<view class="bb-left" @click="toggleAll">
				<view class="pick" :class="{ on: allPicked }"><text v-if="allPicked">✓</text></view>
				<text class="bb-txt">全选（已选 {{ picked.length }}）</text>
			</view>
			<view class="bb-actions">
				<button class="bb-btn up" :disabled="!picked.length" @click="batchStatus(1)">批量上架</button>
				<button class="bb-btn down" :disabled="!picked.length" @click="batchStatus(0)">批量下架</button>
			</view>
		</view>

		<!-- 商品列表 -->
		<scroll-view class="scroll-view" scroll-y @scrolltolower="loadMore" refresher-enabled
			@refresherrefresh="onPullDownRefresh" :refresher-triggered="isRefreshing">
			<view class="product-list" v-if="productList.length > 0">
				<view class="product-card" v-for="(item, index) in productList" :key="item.id"
					@click="batchMode ? togglePick(item) : null">
					<view class="pick-box" v-if="batchMode">
						<view class="pick" :class="{ on: picked.indexOf(item.id) > -1 }">
							<text v-if="picked.indexOf(item.id) > -1">✓</text>
						</view>
					</view>
					<view class="product-info">
						<image class="product-image" :src="item.cover_pic" mode="aspectFill"></image>
						<view class="product-details">
							<view class="name">{{ item.name }}</view>
							<view class="price">¥{{ item.price }}</view>
							<view class="meta">库存: {{ item.stock }} 销量: {{ item.sales || 0 }}</view>
						</view>
					</view>
					<view class="product-actions">
						<view class="status-tag" :class="item.statusClass">
							{{ item.statusText }}
						</view>
						<view class="action-buttons" v-if="!batchMode">
							<button class="btn btn-secondary"
								v-if="item.status === 1 && item.is_check === 1 && item.stock > 0"
								@click="updateStatus(item, 0)">放入仓库</button>
							<button class="btn btn-primary-green"
								v-if="item.status === 0 && item.is_check === 1 && item.stock > 0"
								@click="updateStatus(item, 1)">提交上架</button>
							<button class="btn btn-primary" @click="goToEdit(item.id)">修改</button>
							<button class="btn btn-danger" @click="deleteGoods(item, index)">删除</button>
						</view>
					</view>
				</view>
			</view>

			<tui-no-data v-if="!productList.length && !loading" :fixed="false">暂无商品</tui-no-data>
			<tui-loadmore v-if="loading" :index="3"></tui-loadmore>
			<tui-nomore v-if="noMoreData" text="没有更多商品了"></tui-nomore>
		</scroll-view>

		<!-- 悬浮发布按钮 -->
		<view class="fab-btn" @click="goTo('/plugins1/mch/admin/goods-edit')">
			<tui-icon name="add" :size="22" color="#ffffff"></tui-icon>
			<text class="fab-text">发布</text>
		</view>
	</view>
</template>

<script>
	export default {
		data() {
			return {
				batchMode: false,
				picked: [],
				tabs: [{
						name: '全部',
						count: 0,
						status: -1,
						is_check: -1
					},
					{
						name: '在售',
						count: 0,
						status: 1,
						is_check: 1
					},
					{
						name: '下架',
						count: 0,
						status: 0,
						is_check: 1
					},
					{
						name: '售罄',
						count: 0,
						status: 2,
						is_check: 1
					},
					{
						name: '审核中',
						count: 0,
						status: -1,
						is_check: 0
					},
					{
						name: '未通过',
						count: 0,
						status: -1,
						is_check: 2
					},
				],
				activeTab: 0,
				keyword: '',
				productList: [],
				loading: false,
				noMoreData: false,
				isRefreshing: false,
				page: 1,
				limit: 10,
			};
		},
		onLoad(e) {
			if (e.tab) {
				this.activeTab = parseInt(e.tab)
			}
		},
		onShow() {
			this.loadProductList(true); // 页面首次加载时，刷新列表
		},
		computed: {
			allPicked() { return this.productList.length > 0 && this.picked.length === this.productList.length; }
		},
		methods: {
			toggleBatch() {
				this.batchMode = !this.batchMode;
				this.picked = [];
			},
			togglePick(item) {
				const i = this.picked.indexOf(item.id);
				if (i > -1) this.picked.splice(i, 1);
				else this.picked.push(item.id);
			},
			toggleAll() {
				// 只全选**当前已加载**的,不去动没翻到的页 —— 商家看不到的东西不该被批量改
				this.picked = this.allPicked ? [] : this.productList.map(g => g.id);
			},
			batchStatus(status) {
				if (!this.picked.length) return;
				const txt = status === 1 ? '上架' : '下架';
				uni.showModal({
					title: '批量' + txt,
					content: `确定要批量${txt}选中的 ${this.picked.length} 件商品吗？`,
					success: (r) => {
						if (!r.confirm) return;
						uni.showLoading({ title: '处理中...' });
						this.$request({
							url: '/plugin/mch/admin/goods/batch-status-change',
							method: 'POST',
							data: { ids: this.picked, status: status }
						}).then(e => {
							uni.showToast({ title: e.msg || '操作完成', icon: e.code === 0 ? 'success' : 'none' });
							if (e.code === 0) {
								this.picked = [];
								this.batchMode = false;
								this.onSearch();
							}
						}).finally(() => uni.hideLoading());
					}
				});
			},
			onPullDownRefresh() {
				this.isRefreshing = true;
				this.loadProductList(true);
			},
			switchTab(index) {
				if (this.activeTab === index) return;
				this.activeTab = index;
				this.loadProductList(true);
			},
			onSearch() {
				this.loadProductList(true);
			},
			async loadProductList(isRefresh = false) {
				if (isRefresh) {
					this.page = 1;
					this.productList = [];
					this.noMoreData = false;
				}
				if (this.loading || this.noMoreData) {
					if (isRefresh) this.isRefreshing = false;
					return
				};

				this.loading = true;

				const currentTab = this.tabs[this.activeTab];
				const params = {
					page: this.page,
					limit: this.limit,
					keyword: this.keyword,
					status: currentTab.status,
					is_check: currentTab.is_check,
				};

				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/goods/index',
						data: params
					});
					if (res.code === 0) {
						const processedList = res.data.list.map(item => {
							item.statusText = this.getStatusText(item);
							item.statusClass = this.getStatusClass(item);
							return item;
						});

						if (processedList.length > 0) {
							this.productList.push(...processedList);
							this.page++;
						}

						if (!res.data.pagination || res.data.pagination.current_page >= res.data.pagination
							.total_pages) {
							this.noMoreData = true;
						}

						if (res.data.tabs_count) {
							this.tabs.forEach(tab => {
								for (let key in res.data.tabs_count) {
									if (tab.name === key) {
										tab.count = res.data.tabs_count[key]
									}
								}
							})
						}
					} else {
						this.$util.toast(res.msg);
					}
				} finally {
					this.loading = false;
					this.isRefreshing = false;
				}
			},
			loadMore() {
				this.loadProductList();
			},
			getStatusText(item) {
				if (item.is_check == 0) return '审核中';
				if (item.is_check == 2) return '审核驳回';
				if (item.stock <= 0) return '售罄';
				if (item.status == 1) return '在售';
				return '仓库中';
			},
			getStatusClass(item) {
				if (item.is_check == 0) return 'status-yellow';
				if (item.is_check == 2) return 'status-red';
				if (item.stock <= 0) return 'status-gray';
				if (item.status == 1) return 'status-green';
				return 'status-gray';
			},
			async updateStatus(item, status) {
				const actionText = status === 1 ? '上架' : '下架';
				uni.showModal({
					title: '提示',
					content: `确认要${actionText}该商品吗？`,
					success: async (res) => {
						if (res.confirm) {
							uni.showLoading({
								title: '操作中...'
							});
							const apiRes = await this.$request({
								url: '/plugin/mch/admin/goods/update-status',
								method: 'POST',
								data: {
									id: item.id,
									status
								}
							});
							uni.hideLoading();
							this.$util.toast(apiRes.msg);
							if (apiRes.code === 0) {
								this.loadProductList(true);
							}
						}
					}
				});
			},
			async deleteGoods(item, index) {
				uni.showModal({
					title: '确认删除',
					content: `删除后不可恢复，确认删除商品"${item.name}"吗？`,
					success: async (res) => {
						if (res.confirm) {
							uni.showLoading({
								title: '删除中...'
							});
							const apiRes = await this.$request({
								url: '/plugin/mch/admin/goods/delete',
								method: 'POST',
								data: {
									id: item.id
								}
							});
							uni.hideLoading();
							this.$util.toast(apiRes.msg);
							if (apiRes.code === 0) {
								this.productList.splice(index, 1);
							}
						}
					}
				});
			},
			goToEdit(id) {
				this.goTo(`/plugins1/mch/admin/goods-edit?id=${id}`);
			},
			goTo(url) {
				uni.navigateTo({
					url
				});
			}
		}
	}
</script>

<style lang="scss" scoped>
	.page-container {
		display: flex;
		flex-direction: column;
		height: 100vh;
		background-color: #f5f6fa;
		position: relative; /* 为悬浮按钮提供参考 */
	}

	.tabs-container {
		display: flex;
		background-color: #fff;
		border-bottom: 1rpx solid #f0f0f0;
		padding: 0 24rpx;
		justify-content: space-around;
		position: sticky;
		top: 0;
		z-index: 99;
	}

	.tab-item {
		padding: 24rpx 0;
		font-size: 28rpx;
		color: #666;
		position: relative;

		&.active {
			color: var(--theme-color);
			font-weight: bold;

			&::after {
				content: '';
				position: absolute;
				bottom: 0;
				left: 50%;
				transform: translateX(-50%);
				width: 60rpx;
				height: 6rpx;
				background-color: var(--theme-color);
				border-radius: 4rpx;
			}
		}
	}

	.search-bar {
		padding: 20rpx 24rpx;
		background-color: #fff;
		display: flex;
		align-items: center;
	}

	.search-input {
		flex: 1;
		background-color: #f5f6fa;
		height: 64rpx;
		padding: 0 20rpx;
		border-radius: 32rpx;
		font-size: 26rpx;
	}

	.search-btn {
		margin-left: 20rpx;
		background-color: var(--theme-color);
		color: #fff;
		height: 64rpx;
		line-height: 64rpx;
		font-size: 26rpx;
		border-radius: 32rpx;
		padding: 0 30rpx;

		&::after {
			border: none;
		}
	}

	.scroll-view {
		flex: 1;
		height: 0;
	}

	.product-list {
		padding: 24rpx;
		/* 增加底部内边距，防止最后的内容被悬浮按钮遮挡 */
		padding-bottom: 150rpx; 
	}

	.product-card {
		background-color: #fff;
		border-radius: 16rpx;
		margin-bottom: 24rpx;
		padding: 24rpx;
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);
	}

	.product-info {
		display: flex;
		padding-bottom: 24rpx;
		border-bottom: 1rpx solid #f0f0f0;
	}

	.product-image {
		width: 160rpx;
		height: 160rpx;
		border-radius: 12rpx;
		margin-right: 24rpx;
		flex-shrink: 0;
		background-color: #eee;
	}

	.product-details {
		flex: 1;
		display: flex;
		flex-direction: column;

		.name {
			font-size: 30rpx;
			color: #333;
			font-weight: 500;
			display: -webkit-box;
			-webkit-box-orient: vertical;
			-webkit-line-clamp: 2;
			overflow: hidden;
			line-height: 1.4;
		}

		.price {
			font-size: 32rpx;
			color: #ff4141;
			font-weight: bold;
			margin-top: auto;
		}

		.meta {
			font-size: 24rpx;
			color: #999;
			margin-top: 8rpx;
		}
	}

	.product-actions {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding-top: 24rpx;
	}

	.status-tag {
		font-size: 24rpx;
		padding: 8rpx 16rpx;
		border-radius: 8rpx;

		&.status-green {
			color: #4CAF50;
			background-color: #E8F5E9;
		}

		&.status-yellow {
			color: #FF9800;
			background-color: #FFF3E0;
		}

		&.status-red {
			color: #ff4141;
			background-color: #ffe8e8;
		}

		&.status-gray {
			color: #9E9E9E;
			background-color: #F5F5F5;
		}
	}

	.action-buttons {
		display: flex;
		align-items: center;
	}

	.btn {
		margin-left: 20rpx;
		padding: 0 30rpx;
		height: 60rpx;
		line-height: 60rpx;
		font-size: 26rpx;
		border-radius: 30rpx;

		&::after {
			border: none;
		}

		&.btn-primary {
			background-color: var(--theme-color);
			color: #fff;
		}

		&.btn-primary-green {
			background-color: #4CAF50;
			color: #fff;
		}

		&.btn-secondary {
			background-color: #fff;
			color: #666;
			border: 1rpx solid #ccc;
		}

		&.btn-danger {
			background-color: #fff;
			color: #ff4141;
			border: 1rpx solid #ff4141;
		}
	}

	/* 悬浮按钮样式 */
	.fab-btn {
		position: fixed;
		right: 40rpx;
		bottom: 120rpx; /* 可根据需要调整高度，避免遮挡 TabBar */
		width: 110rpx;
		height: 110rpx;
		background-color: var(--theme-color);
		border-radius: 50%;
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
		box-shadow: 0 6rpx 20rpx var(--theme-shadow);
		z-index: 999;
		/* 适配苹果底部安全区域 */
		padding-bottom: env(safe-area-inset-bottom);
		transition: transform 0.1s;

		&:active {
			transform: scale(0.95);
		}

		.fab-text {
			color: #ffffff;
			font-size: 22rpx;
			margin-top: -2rpx;
			font-weight: 500;
		}
	}

.batch-btn { margin-left: 12rpx; height: 64rpx; line-height: 64rpx; padding: 0 22rpx; font-size: 26rpx;
  border-radius: 32rpx; background: #F1F5F9; color: #475569; }
.batch-btn.on { background: var(--theme-color); color: #fff; }

.batch-bar { display: flex; align-items: center; justify-content: space-between;
  padding: 16rpx 24rpx; background: #fff; border-bottom: 1rpx solid #EDF0F3; }
.bb-left { display: flex; align-items: center; }
.bb-txt { font-size: 26rpx; color: #475569; margin-left: 12rpx; }
.bb-actions { display: flex; gap: 16rpx; }
.bb-btn { height: 60rpx; line-height: 60rpx; padding: 0 26rpx; font-size: 26rpx; border-radius: 30rpx; }
.bb-btn.up { background: var(--theme-color); color: #fff; }
.bb-btn.down { background: #F1F5F9; color: #475569; }
.bb-btn[disabled] { opacity: .45; }

.pick-box { padding-right: 16rpx; display: flex; align-items: center; }
.pick { width: 40rpx; height: 40rpx; border-radius: 50%; border: 2rpx solid #CBD5E1;
  display: flex; align-items: center; justify-content: center; color: #fff; font-size: 24rpx; }
.pick.on { background: var(--theme-color); border-color: var(--theme-color); }
</style>