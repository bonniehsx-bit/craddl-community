<template>
	<view class="page-container">
		<view class="search-bar">
			<uni-search-bar @confirm="onSearch" placeholder="搜索订单号/商品名" cancelButton="none"
				v-model="queryParams.keyword"></uni-search-bar>
		</view>

		<view class="tabs-container">
			<view class="tabs">
				<view class="tab-item" v-for="(tab, index) in tabList" :key="tab.name" @click="changeTab(index)">
					<text :class="{ 'active': activeTab === index }">{{ tab.name }}</text>
					<view v-if="activeTab === index" class="active-line"></view>
				</view>
			</view>
		</view>

		<scroll-view class="order-list-container" scroll-y @scrolltolower="onReachBottom"
			:refresher-triggered="loading && queryParams.page === 1">
			<view v-if="loading && queryParams.page === 1" class="loading-placeholder">
				<uni-load-more status="loading"></uni-load-more>
			</view>

			<template v-else-if="orderList.length > 0">
				<view class="order-card" v-for="order in orderList" :key="order.id">
					<view class="card-header">
						<text class="order-no">订单编号：{{ order.order_no }}</text>
						<text class="order-status"
							:style="{ color: getStatus(order).color }">{{ getStatus(order).text }}</text>
					</view>

					<view @click="handleDetail(order)" class="goods-section" v-for="goods in order.orderDetails"
						:key="goods.id">
						<image class="goods-image" :src="goods.goods.cover_pic" mode="aspectFill"></image>
						<view class="goods-info">
							<view class="goods-name">{{ goods.goods.name }}</view>
							<view class="goods-spec">
								规格：{{ goods.attr && goods.attr.length > 0 ? goods.attr[0].value : '默认' }}</view>
							<view class="goods-price">价格：¥{{ goods.price }}</view>
						</view>
						<view class="goods-num">
							<text>x {{ goods.num }}</text>
						</view>
					</view>

					<view class="address-section">
						<uni-icons type="location-filled" size="18" color="#333"></uni-icons>
						<view class="address-info">
							<text class="user-info">{{ order.name }} {{ order.mobile }}</text>
							<text class="address-text">{{ order.address }}</text>
						</view>
					</view>

					<view class="card-footer">
						<view class="total-payment">
							<text>实付款：</text>
							<text class="price">¥{{ order.pay_price }}</text>
						</view>
						<view class="action-buttons">
							<!-- 有售后还在处理时(after_sale_block_reason,与后端拦截同一个判断)取消 / 发货 / 核销都不能做,整排收起,
								 从下面的「订单详情」进去处理售后 -->
							<template v-if="order.status == 20 && !order.after_sale_block_reason">
								<template v-if="order.deliver_type == 'city'">
									<button class="btn plain" @click="handleCancel(order)">取消</button>
									<button class="btn primary" @click="handleShip(order)">开始配送</button>
								</template>
								<template v-if="order.deliver_type == 'express'">
									<button class="btn plain" @click="handleCancel(order)">取消</button>
									<button class="btn primary" v-if="!(order.plugin_data && order.plugin_data.action_config && order.plugin_data.action_config.show_ship === false)" @click="handleExpressShip(order)">去发货</button>
								</template>
								<template v-if="order.deliver_type == 'pickup'">
									<button class="btn plain" @click="handleCancel(order)">取消</button>
									<button class="btn primary" v-if="!(order.plugin_data && order.plugin_data.action_config && order.plugin_data.action_config.show_force_verify === false)" @click="handleVerify(order)">去核销</button>
								</template>
							</template>

							<template v-if="order.status == 30 && order.deliver_type == 'city'">
								<button class="btn primary" @click="handleComplete(order)">确认送达</button>
							</template>

							<template v-if="order.status == 40 || order.status == 0 || order.refund_status > 0">
								<button class="btn plain" @click="handleDetail(order)">订单详情</button>
							</template>
						</view>
					</view>
				</view>

				<uni-load-more :status="loadingStatus"></uni-load-more>
			</template>

			<template v-else>
				<view class="empty-state">
					<tui-no-data>暂无相关订单</tui-no-data>

				</view>
			</template>
		</scroll-view>

		<ship-modal ref="shipModal" @success="onShipSuccess"></ship-modal>
	</view>
</template>

<script>
	import shipModal from './components/order-ship-modal.vue';
	export default {
		components: {
			shipModal
		},
		data() {
			return {
				tabList: [{
						name: '待发货',
						params: {
							status: '20'
						},
						filterKey: ['city', 'express']
					},
					{
						name: '待收货',
						params: {
							status: '30'
						}
					},
					{
						name: '已完成',
						params: {
							status: '40'
						}
					},
					{
						name: '售后/已关闭',
						params: {
							status: 'refund'
						}
					},
					{
						name: '全部',
						params: {
							status: '-1'
						}
					}
				],
				activeTab: 0,
				queryParams: {
					user_id: '',
					page: 1,
					limit: 10,
					status: '20',
					keyword: ''
				},
				orderList: [],
				loading: false,
				pagination: null,
				loadingStatus: 'more',
				pageTitle: '订单管理', // 动态页面标题
			};
		},
		onLoad(options) {
			// [核心逻辑] 支持多种入口参数
			let initialTab = 0;
			let initialParams = {};

			if (options.user_id) {
				// 场景1：从会员页跳转，查看某个会员的全部订单
				this.queryParams.user_id = options.user_id;
				initialTab = 5; // '全部' 标签页的索引是 5
				if (options.nickname) {
					this.pageTitle = `${options.nickname}的订单`;
				}
			} else if (options.tab && this.tabList[options.tab]) {
				// 场景2：通过 tab 参数指定初始标签页
				initialTab = parseInt(options.tab, 10);
			}

			// 应用初始状态
			this.activeTab = initialTab;
			initialParams = this.tabList[initialTab].params;
			this.queryParams = {
				...this.queryParams,
				...initialParams
			};

			uni.setNavigationBarTitle({
				title: this.pageTitle
			});

			//	this.getList(true);
		},
		onReachBottom() {
			if (this.loadingStatus === 'more') {
				this.queryParams.page++;
				this.getList();
			}
		},
		onShow() {
			this.getList(true);
		},
		methods: {
			handleExpressShip(order) {
				this.$refs.shipModal.open(order);
			},
			onShipSuccess() {
				this.getList(true);
			},
			getStatus(order) {
				if (order.refund_status > 0) {
					switch (order.refund_status) {
						case 10:
						case 20:
							return {
								text: '售后中', color: '#E6A23C'
							};
						case 30:
							return {
								text: '售后完成', color: '#909399'
							};
						case 40:
							return {
								text: '售后关闭', color: '#909399'
							};
						default:
							return {
								text: '售后', color: '#E6A23C'
							};
					}
				}
				switch (order.status) {
					case 0:
						return {
							text: '已关闭', color: '#909399'
						};
					case 10:
						return {
							text: '待付款', color: '#FA5151'
						};
					case 20: {
						if (order.deliver_type == 'express') return {
							text: '待发货',
							color: '#FF9900'
						};
						if (order.deliver_type == 'pickup') return {
							text: '待自提',
							color: '#FF9900'
						};
						if (order.deliver_type == 'city') return {
							text: '待配送',
							color: '#FF9900'
						};
						return {
							text: '待处理',
							color: '#FF9900'
						};
					}
					case 30: {
						let obj = {
							text: '配送中',
							color: '#143A94'
						};
						if (order.deliver_type == 'express') obj.text = '待收货';
						if (order.deliver_type == 'pickup') obj.text = '待确认';
						return obj;
					}
					case 40:
						return {
							text: '已完成', color: '#2E8B57'
						};
					default:
						return {
							text: `未知状态(${order.status})`, color: '#303133'
						};
				}
			},
			async getList(refresh = false) {
				if (this.loading && !refresh) return;
				if (refresh) {
					this.queryParams.page = 1;
				}
				this.loading = true;
				this.loadingStatus = 'loading';

				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/order/index',
						data: this.queryParams
					});

					if (res.code === 0) {
						let list = res.data.list;
						const filterKey = this.tabList[this.activeTab].filterKey;

						if (filterKey && Array.isArray(filterKey)) {
							list = list.filter(order => filterKey.includes(order.deliver_type));
						}

						this.orderList = refresh ? list : [...this.orderList, ...list];
						this.pagination = res.data.pagination;
						this.loadingStatus = this.orderList.length >= (this.pagination.total_count || 0) ? 'noMore' :
							'more';
					} else {
						this.$showModal(res.msg);
						this.loadingStatus = 'more';
					}
				} finally {
					this.loading = false;
					if (refresh) {
						uni.stopPullDownRefresh();
					}
				}
			},
			changeTab(index) {
				if (this.activeTab === index || this.loading) return;
				this.activeTab = index;
				const tab = this.tabList[index];
				const newParams = {
					...tab.params
				};

				this.queryParams = {
					...this.queryParams,
					...newParams,
					page: 1
				};
				this.getList(true);
			},
			onSearch(e) {
				this.queryParams.keyword = e.value;
				this.getList(true);
			},
			async handleAction(url, data, successMessage, confirmOptions = null) {
				const confirmAndAction = async () => {
					uni.showLoading({
						title: '请稍候...'
					});
					try {
						const res = await this.$request({
							url,
							method: 'POST',
							data
						});
						if (res.code === 0) {
							uni.showToast({
								title: successMessage,
								icon: 'success'
							});
							setTimeout(() => this.getList(true), 800);
						} else {
							this.$showModal(res.msg);
						}
					} finally {
						uni.hideLoading();
					}
				};

				if (confirmOptions) {
					uni.showModal({
						title: confirmOptions.title || '提示',
						content: confirmOptions.content,
						success: (res) => {
							if (res.confirm) confirmAndAction();
						}
					});
				} else {
					confirmAndAction();
				}
			},
			handleCancel(order) {
				this.handleAction('/plugin/mch/admin/order/cancel', {
					order_id: order.id
				}, '取消成功', {
					content: '确定要取消这个订单吗？款项将原路退回。'
				});
			},
			handleVerify(order) {
				this.$T.go('/plugins1/mch/order-detail?id=' + order.id);
			},
			handleShip(order) {
				this.handleAction('/plugin/mch/admin/order/ship', {
					order_id: order.id
				}, '发货成功', {
					content: '确认开始配送此订单吗？'
				});
			},
			handleComplete(order) {
				this.handleAction('/plugin/mch/admin/order/complete', {
					order_id: order.id
				}, '订单已送达', {
					content: '请确保商品已送达客户手中！'
				});
			},
			handleDetail(order) {
				this.$T.go('/plugins1/mch/order-detail?id=' + order.id);
			}
		}
	}
</script>

<style lang="scss" scoped>
	// 页面和容器样式
	.page-container {
		display: flex;
		flex-direction: column;
		height: 100vh;
		background-color: #f5f5f5;
	}

	.search-bar {
		background-color: #ffffff;
	}

	.tabs-container {
		position: sticky;
		top: 0;
		/* #ifdef H5 */
		top: var(--window-top);
		/* #endif */

		background-color: #ffffff;
		border-bottom: 1rpx solid #f0f0f0;

		.tabs {
			display: flex;
			justify-content: space-around;
			height: 88rpx;
			align-items: center;

			.tab-item {
				position: relative;
				padding: 0 10rpx;
				height: 100%;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 28rpx;
				color: #666;

				.active {
					color: #333;
					font-weight: bold;
				}

				.active-line {
					position: absolute;
					bottom: 0;
					left: 50%;
					transform: translateX(-50%);
					width: 60rpx;
					height: 6rpx;
					background-color: #143A94;
					border-radius: 3rpx;
				}
			}
		}
	}

	.order-list-container {
		flex: 1;
		box-sizing: border-box;
		padding: 20rpx;
	}

	// 空状态样式
	.empty-state {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		padding-top: 200rpx;

		.empty-image {
			width: 200rpx;
		}

		.empty-text {
			margin-top: 20rpx;
			color: #999;
		}
	}

	// --- 订单卡片样式 ---
	.order-card {
		background-color: #ffffff;
		border-radius: 16rpx;
		padding: 24rpx;
		margin-bottom: 20rpx;
		font-size: 28rpx;

		.card-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 24rpx;

			.order-no {
				color: #666;
				font-size: 26rpx;
			}

			.order-status {
				font-weight: bold;
				font-size: 28rpx;
			}
		}

		.goods-section {
			display: flex;
			padding: 20rpx 0;
			border-top: 1rpx solid #f0f0f0;

			.goods-image {
				width: 160rpx;
				height: 160rpx;
				border-radius: 8rpx;
				flex-shrink: 0;
				margin-right: 20rpx;
			}

			.goods-info {
				flex-grow: 1;
				min-width: 0;

				.goods-name {
					font-size: 30rpx;
					font-weight: 500;
					color: #333;
					display: -webkit-box;
					-webkit-box-orient: vertical;
					-webkit-line-clamp: 2;
					overflow: hidden;
				}

				.goods-spec,
				.goods-price {
					font-size: 26rpx;
					color: #999;
					margin-top: 8rpx;
				}
			}

			.goods-num {
				color: #666;
				margin-left: 20rpx;
				font-size: 26rpx;
			}
		}

		.address-section {
			display: flex;
			align-items: flex-start;
			padding: 24rpx 0;
			border-top: 1rpx solid #f0f0f0;

			.address-info {
				margin-left: 12rpx;
				display: flex;
				flex-direction: column;

				.user-info {
					font-weight: 500;
					color: #333;
					margin-bottom: 8rpx;
				}

				.address-text {
					color: #666;
					line-height: 1.4;
					font-size: 26rpx;
				}
			}
		}

		.card-footer {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-top: 20rpx;
			padding-top: 20rpx;
			border-top: 1rpx solid #f0f0f0;

			.total-payment {
				color: #666;
				font-size: 26rpx;

				.price {
					font-size: 32rpx;
					font-weight: bold;
					color: #ff5733;
				}
			}

			.action-buttons {
				display: flex;
				gap: 20rpx;

				.btn {
					margin: 0;
					padding: 0 28rpx;
					height: 60rpx;
					line-height: 60rpx;
					font-size: 26rpx;
					border-radius: 30rpx;

					&.plain {
						border: 1rpx solid #999;
						color: #666;
						background-color: #fff;
					}

					&.primary {
						border: none;
						background-color: #143A94;
						color: #fff;
					}
				}
			}
		}
	}
</style>