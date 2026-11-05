<template>
	<view class="page-container" :style="{ '--theme-color': themeColor || '#143A94' }">
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
						<view class="header-left">
							<text class="plugin-tag yuda-tag" v-if="order.sign === 'yuda'">遇搭订单</text>
							<text class="plugin-tag group-tag" v-else-if="order.sign === 'group'">拼团订单</text>
							<text class="plugin-tag checkout-tag" v-else-if="order.sign === 'checkout'">收银台</text>
							<text class="order-no">订单编号：{{ order.order_no }}</text>
						</view>
						<text class="order-status" :style="{ color: getStatus(order).color }">{{ getStatus(order).text
							}}</text>
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

					<!-- 核销订单：显示联系人+核销码 -->
					<view class="pickup-section" v-if="order.deliver_type === 'pickup' || order.deliver_type === 'none'">
						<view class="pickup-info-row">
							<view class="pickup-user" v-if="order.name || order.mobile">
								<uni-icons type="person-filled" size="16" color="#999"></uni-icons>
								<text class="pickup-contact">{{ order.name }} {{ order.mobile }}</text>
							</view>
							<view class="pickup-code" v-if="order.pickup_code" @click.stop="copyCode(order.pickup_code)">
								<text class="code-label">核销码</text>
								<text class="code-val">{{ order.pickup_code }}</text>
							</view>
						</view>
					</view>

					<!-- 快递/同城：显示完整收货地址 -->
					<view class="address-section"
						v-else-if="order.deliver_type === 'express' || order.deliver_type === 'city'">
						<uni-icons type="location-filled" size="18" color="#333"></uni-icons>
						<view class="address-info">
							<text class="user-info" v-if="order.name || order.mobile">{{ order.name }} {{ order.mobile }}</text>
							<text class="address-text" v-if="order.address">{{ order.address }}</text>
						</view>
					</view>

					<view class="card-footer">
						<view class="total-payment">
							<text>实付款：</text>
							<text class="price">¥{{ order.pay_price }}</text>
						</view>
						<view class="action-buttons">
							<button class="btn plain" @click.stop="handleDetail(order)">订单详情</button>
							<button class="btn primary" @click.stop="$refs.actionDrawer.open(order)">操作管理</button>
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

		<action-drawer ref="actionDrawer" @success="getList(true)"></action-drawer>
	</view>
</template>

<script>
import { mapState } from 'vuex';
import actionDrawer from './components/order-action-drawer.vue';
export default {
	components: {
		actionDrawer
	},
	computed: {
		...mapState('mall', ['themeColor'])
	},
	data() {
		return {
			tabList: [
				{ name: '全部', params: { status: '-1' } },
				{ name: '待付款', params: { status: '10' } },
				{ name: '待发货/核销', params: { status: '20' } },
				{ name: '待收货', params: { status: '30' } },
				{ name: '已完成', params: { status: '40' } },
				{ name: '已关闭', params: { status: '0' } },
				{ name: '售后中', params: { status: 'refund' } }
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
			initialTab = 0; // '全部' 标签页的新索引是 0
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
		// 极简化的列表重载逻辑
		onActionSuccess() {
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
					if (order.deliver_type == 'pickup' || order.deliver_type == 'none') return {
						text: '待核销',
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
						color: 'var(--theme-color)'
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
		copyCode(code) {
			uni.setClipboardData({
				data: code,
				success: () => uni.showToast({ title: '核销码已复制', icon: 'success' })
			});
		},
		handleDetail(order) {
			uni.navigateTo({
				url: `/plugins1/mch/order-detail?id=${order.id}`
			});
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
				background-color: var(--theme-color);
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

		.header-left {
			display: flex;
			align-items: center;

			.plugin-tag {
				font-size: 20rpx;
				padding: 4rpx 12rpx;
				border-radius: 6rpx;
				margin-right: 12rpx;
				color: #fff;
				background-color: var(--theme-color);

				&.yuda-tag {
					background: linear-gradient(135deg, #FF6B81, #E02B41);
				}

				&.group-tag {
					background: linear-gradient(135deg, #4facfe, #00f2fe);
				}

				&.checkout-tag {
					background-color: #E6A23C;
				}
			}
		}

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
				-webkit-line-clamp: 2;
				line-clamp: 2;
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

	.pickup-section {
		padding: 20rpx 0;
		border-top: 1rpx solid #f0f0f0;

		.pickup-info-row {
			display: flex;
			justify-content: space-between;
			align-items: center;
		}

		.pickup-user {
			display: flex;
			align-items: center;

			.pickup-contact {
				font-size: 26rpx;
				color: #666;
				margin-left: 8rpx;
			}
		}

		.pickup-code {
			display: flex;
			align-items: center;
			background: #FFF7E6;
			border: 1rpx solid #FFD591;
			border-radius: 8rpx;
			padding: 6rpx 16rpx;

			.code-label {
				font-size: 22rpx;
				color: #FA8C16;
				margin-right: 8rpx;
			}

			.code-val {
				font-size: 28rpx;
				font-weight: bold;
				color: #D46B08;
				font-family: monospace;
				letter-spacing: 2rpx;
			}
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
					background-color: var(--theme-color);
					color: #fff;
				}
			}
		}
	}
}
</style>