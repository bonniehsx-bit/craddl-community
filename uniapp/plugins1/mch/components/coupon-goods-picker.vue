<!-- components/goods-picker/goods-picker.vue -->
<template>
	<tui-bottom-popup :show="show" @close="close">
		<view class="picker-container" :style="themeStyle">
			<view class="picker-header">
				<view class="title">选择商品</view>
				<tui-icon name="close-fill" color="#999" :size="20" @click="close"></tui-icon>
			</view>

			<view class="search-bar">
				<uni-search-bar @confirm="onSearch" placeholder="搜索商品名称" cancelButton="none"
					v-model="queryParams.keyword"></uni-search-bar>
			</view>

			<scroll-view scroll-y class="goods-scroll-view" @scrolltolower="loadMore">
				<view class="goods-list">
					<view class="goods-item" v-for="goods in goodsList" :key="goods.id" @click="toggleSelect(goods)">
						<image class="cover-pic" :src="goods.cover_pic" mode="aspectFill"></image>
						<view class="goods-name">{{ goods.name }}</view>
						<view class="checkbox">
							<tui-icon :name="isSelected(goods) ? 'circle-selected' : 'circle-selected'"
								:color="isSelected(goods) ? '#2953A8' : '#ccc'" :size="22"></tui-icon>
						</view>
					</view>
				</view>
				<uni-load-more :status="loadingStatus"></uni-load-more>
			</scroll-view>

			<view class="picker-footer" :style="{paddingBottom: is_ios ? '68rpx' : '24rpx'}">
				<view class="selection-info">
					已选 <text class="count">{{ selectedGoods.length }}</text> 件
				</view>
				<tui-button type="primary" shape="circle" height="72rpx" :size="28" width="550rpx"
					customStyle="background-color:var(--theme-color); width: 240rpx;" @click="confirmSelection">
					确定
				</tui-button>
			</view>
		</view>
	</tui-bottom-popup>
</template>

<script>
	export default {
		name: "goods-picker",
		props: {
			// 接收已选的商品列表，用于回显
			initialSelection: {
				type: Array,
				default: () => []
			}
		},
		data() {
			return {
				show: false,
				goodsList: [],
				selectedGoods: [], // 存储完整的商品对象
				queryParams: {
					page: 1,
					limit: 15,
					keyword: ''
				},
				loadingStatus: 'more',
				pagination: null,
				is_ios: false,
			};
		},
		created() {
			uni.getSystemInfo({
				success: res => {
					this.is_ios = res.platform === 'ios';
				}
			});
		},
		methods: {
			open() {
				// 深度克隆，避免直接修改父组件的 prop
				this.selectedGoods = JSON.parse(JSON.stringify(this.initialSelection));
				this.loadGoodsList(true);
				this.show = true;
			},
			close() {
				this.show = false;
			},
			async loadGoodsList(refresh = false) {
				if (refresh) {
					this.queryParams.page = 1;
				}
				this.loadingStatus = 'loading';
				try {
					// 复用商家商品列表接口
					const res = await this.$request({
						url: '/plugin/mch/admin/goods/index',
						data: this.queryParams
					});
					if (res.code === 0) {
						const list = res.data.list || [];
						this.goodsList = refresh ? list : [...this.goodsList, ...list];
						this.pagination = res.data.pagination;
						this.loadingStatus = this.goodsList.length >= this.pagination.totalCount ? 'noMore' : 'more';
					} else {
						this.loadingStatus = 'more';
					}
				} catch (e) {
					this.loadingStatus = 'more';
				}
			},
			loadMore() {
				if (this.loadingStatus === 'more') {
					this.queryParams.page++;
					this.loadGoodsList();
				}
			},
			onSearch() {
				this.loadGoodsList(true);
			},
			isSelected(goods) {
				return this.selectedGoods.some(item => item.id === goods.id);
			},
			toggleSelect(goods) {
				const index = this.selectedGoods.findIndex(item => item.id === goods.id);
				if (index > -1) {
					this.selectedGoods.splice(index, 1);
				} else {
					// 只存储需要回显的核心字段
					this.selectedGoods.push({
						id: goods.id,
						name: goods.name,
						cover_pic: goods.cover_pic
					});
				}
			},
			confirmSelection() {
				// 通过事件将最终选择的商品列表传递给父组件
				this.$emit('confirm', this.selectedGoods);
				this.close();
			}
		}
	}
</script>

<style lang="scss" scoped>
	.picker-container {
		background-color: #fff;
		border-top-left-radius: 24rpx;
		border-top-right-radius: 24rpx;
		padding: 24rpx;
		display: flex;
		flex-direction: column;
		height: 75vh;
		/* 弹窗高度占屏幕75% */
	}

	.picker-header {
		display: flex;
		justify-content: center;
		align-items: center;
		position: relative;
		height: 80rpx;

		.title {
			font-size: 32rpx;
			font-weight: bold;
		}

		.tui-icon {
			position: absolute;
			right: 0;
		}
	}

	.search-bar {
		padding: 10rpx 0;
	}

	.goods-scroll-view {
		flex: 1;
		height: 0;
	}

	.goods-list {
		.goods-item {
			display: flex;
			align-items: center;
			padding: 20rpx 0;
			border-bottom: 1rpx solid #f0f0f0;

			.cover-pic {
				width: 100rpx;
				height: 100rpx;
				border-radius: 8rpx;
				margin-right: 20rpx;
				flex-shrink: 0;
			}

			.goods-name {
				flex: 1;
				font-size: 28rpx;
				/* 省略号 */
			}

			.checkbox {
				padding-left: 30rpx;
			}
		}
	}

	.picker-footer {
		display: flex;
		justify-content: space-between;
		align-items: center;
		border-top: 1rpx solid #f0f0f0;
		padding-top: 24rpx;

		.selection-info {
			font-size: 28rpx;

			.count {
				color: $uni-red-color;
				font-weight: bold;
				padding: 0 8rpx;
			}
		}
	}
</style>