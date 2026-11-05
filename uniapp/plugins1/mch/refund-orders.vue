<template>
	<view class="page-container">
		<view class="search-section">
			<uni-search-bar @confirm="onSearch" @cancel="onCancelSearch" placeholder="搜售后单号/昵称/手机号" cancelButton="none"
				v-model="queryParams.keyword" bgColor="#f5f5f5" radius="100"></uni-search-bar>
		</view>

		<view class="tabs-container">
			<view class="tabs">
				<view class="tab-item" v-for="(tab, index) in tabList" :key="index" @click="changeTab(index)">
					<text :class="{ 'active-text': activeTab === index }">{{ tab.name }}</text>
					<view v-if="activeTab === index" class="active-line"></view>
				</view>
			</view>
		</view>

		<scroll-view class="scroll-content" scroll-y refresher-enabled :refresher-triggered="isRefreshing"
			@refresherrefresh="onPullDownRefresh" @scrolltolower="onReachBottom">
			
			<view class="list-wrapper">
				<block v-for="(item, index) in list" :key="item.id">
					<view class="order-card">
						<view class="card-header">
							<view class="header-left">
								<text class="label">售后单号</text>
								<text class="sn">{{ item.order_no }}</text>
								<text class="copy-btn" @click.stop="copyText(item.order_no)">复制</text>
							</view>
							<view class="status-tag" :style="{ color: getStatusColor(item.status) }">
								{{ getStatusText(item.status) }}
							</view>
						</view>

						<view class="goods-box">
							<image :src="item.goods_cover" class="goods-img" mode="aspectFill"></image>
							<view class="goods-info">
								<view class="goods-name">{{ item.goods_name }}</view>
								<view class="goods-spec" v-if="item.attr_text">{{ item.attr_text }}</view>
							</view>
						</view>

						<view class="info-grid">
							<view class="grid-row">
								<text class="label">售后类型</text>
								<view class="value type-tag" :class="item.type == 1 ? 'warning' : 'danger'">
									{{ typeText(item.type) }}
								</view>
							</view>
							<view class="grid-row">
								<text class="label">退款金额</text>
								<text class="value price">¥{{ item.refund_price }}</text>
							</view>
							<view class="grid-row" v-if="item.status == 6 && item.refund_task">
								<text class="label">退款进度</text>
								<text class="value" :class="'task-' + item.refund_task.state">{{ item.refund_task.text }}</text>
							</view>
							<view class="grid-row">
								<text class="label">申请原因</text>
								<text class="value reason">{{ item.reason || item.content || '无' }}</text>
							</view>
							
							<view class="grid-row column" v-if="item.pic_list && item.pic_list.length">
								<text class="label mb-10">凭证图片</text>
								<view class="img-list">
									<image v-for="(img, idx) in item.pic_list" :key="idx" :src="img" 
										mode="aspectFill" class="proof-img" @click.stop="previewImage(item.pic_list, idx)"></image>
								</view>
							</view>

							<view class="express-box" v-if="(item.type == 2 || item.type == 3) && item.user_express_no">
								<view class="express-header">
									<text class="tit">用户已发货</text>
									<text class="time">{{ item.user_express_at_text }}</text>
								</view>
								<view class="express-content">
									<text>{{ item.user_express }}</text>
									<text class="no">{{ item.user_express_no }}</text>
									<text class="copy-link" @click.stop="copyText(item.user_express_no)">复制</text>
								</view>
							</view>
						</view>

						<view class="card-footer">
							<view class="user-info">
								<image :src="item.user.avatar_url" class="avatar"></image>
								<text class="name">{{ item.user.nickname }}</text>
							</view>
							
							<view class="btn-group">
								<template v-if="item.status == 0">
									<button class="btn plain" @click="openActionModal(item, 2)">拒绝</button>
									<button class="btn primary" @click="openActionModal(item, 1)">同意</button>
								</template>

								<template v-if="canShowPayBtn(item)">
									<button class="btn warning" @click="handleRefundPay(item)">确认打款</button>
								</template>

								<template v-if="canShowExchangeBtn(item)">
									<button class="btn warning" @click="handleExchangeComplete(item)">完成换货</button>
								</template>
							</view>
						</view>
					</view>
				</block>

				<view v-if="loading" class="loading-more">
					<uni-load-more status="loading"></uni-load-more>
				</view>
				<view v-else-if="list.length === 0" class="empty-box">
					<tui-no-data :fixed="false">暂无售后记录</tui-no-data>
				</view>
				<view v-else-if="noMore" class="loading-more">
					<uni-load-more status="noMore"></uni-load-more>
				</view>
			</view>
		</scroll-view>

		<tui-modal :show="modal.show" :title="modal.title" :content="modal.content" :button="modal.buttons" @click="handleModalClick"></tui-modal>
		
		</view>
</template>

<script>
	export default {
		data() {
			return {
				// 状态 Tab
				tabList: [
					{ name: '待审核', status: 0 },
					{ name: '待处理', status: 15 }, // 聚合状态：待发货/待打款
					{ name: '退款中', status: 6 }, // 已确认打款,平台正在退
					{ name: '已完成', status: 3 },
					{ name: '已拒绝', status: 2 },
					{ name: '全部', status: -1 }
				],
				activeTab: 0,

				// 列表数据
				list: [],
				queryParams: {
					page: 1,
					limit: 10,
					status: 0, // 默认显示待审核
					keyword: ''
				},
				loading: false,
				isRefreshing: false,
				noMore: false,

				// 弹窗控制
				modal: {
					show: false,
					title: '提示',
					content: '',
					buttons: [{text: '取消', plain: true}, {text: '确定', color: '#E02020'}],
					currentItem: null,
					actionType: 0 // 1=同意, 2=拒绝, 3=打款, 4=完成换货
				}
			}
		},
		onLoad(options) {
			if (options.status) {
				// 根据传入状态跳转到对应Tab
				const idx = this.tabList.findIndex(t => t.status == options.status);
				if (idx > -1) {
					this.activeTab = idx;
					this.queryParams.status = options.status;
				}
			}
			if (options.keyword) {
				// 从订单操作里点进来:带着订单号,只看这一单的售后
				this.queryParams.keyword = decodeURIComponent(options.keyword);
			}
			this.loadData();
		},
		methods: {
			// 加载数据
			async loadData(refresh = false) {
				if (refresh) {
					this.queryParams.page = 1;
					this.noMore = false;
				}
				if (this.loading || (this.noMore && !refresh)) return;

				this.loading = true;
				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/order/refunds',
						method: 'GET',
						data: this.queryParams
					});

					if (res.code === 0) {
						const curList = res.data.list || [];
						if (refresh) {
							this.list = curList;
						} else {
							this.list = [...this.list, ...curList];
						}
						
						// 判断是否到底
						const pagination = res.data.pagination;
						if (this.list.length >= pagination.total_count) {
							this.noMore = true;
						} else {
							this.queryParams.page++;
						}
					}
				} catch (e) {
					// err
				} finally {
					this.loading = false;
					this.isRefreshing = false;
				}
			},

			// 切换Tab
			changeTab(index) {
				this.activeTab = index;
				this.queryParams.status = this.tabList[index].status;
				this.loadData(true);
			},

			// 下拉刷新
			onPullDownRefresh() {
				this.isRefreshing = true;
				this.loadData(true);
			},

			// 上拉加载
			onReachBottom() {
				this.loadData();
			},

			// 搜索
			onSearch(e) {
				this.queryParams.keyword = e.value;
				this.loadData(true);
			},
			onCancelSearch() {
				this.queryParams.keyword = '';
				this.loadData(true);
			},

			typeText(type) {
				return { 1: '仅退款', 2: '退货退款', 3: '换货' }[type] || '售后';
			},

			// 换货:已同意 / 顾客已寄回 时可以完成(换货不退钱,没有打款这一步)
			canShowExchangeBtn(item) {
				return item.type == 3 && (item.status == 1 || item.status == 5);
			},

			handleExchangeComplete(item) {
				this.modal.currentItem = item;
				this.modal.actionType = 4;
				this.modal.show = true;
				this.modal.title = '完成换货';
				this.modal.content = '确认收到退货并已寄出换货商品？';
			},

			// 判断是否显示打款按钮
			canShowPayBtn(item) {
				// 1. 仅退款(1) & 商家已同意(1)
				if (item.type == 1 && item.status == 1) return true;
				// 2. 退货退款(2) & 用户已发货(5)
				if (item.type == 2 && item.status == 5) return true;
				return false;
			},

			// 打开操作确认框
			openActionModal(item, type) {
				this.modal.currentItem = item;
				this.modal.actionType = type;
				this.modal.show = true;

				if (type === 1) {
					this.modal.title = '同意申请';
					let txt = '确定同意该用户的售后申请吗？';
					if (item.type == 2 || item.type == 3) txt += '\n系统将自动发送您店铺配置的退货地址给用户。';
					this.modal.content = txt;
				} else if (type === 2) {
					// 拒绝通常需要输入原因，这里简化为二次确认，建议配合uni-popup做输入框
					this.modal.title = '拒绝申请';
					this.modal.content = '确定要拒绝该申请吗？拒绝后用户可再次发起。';
				}
			},

			// 处理确认打款 (特殊，单独弹窗)
			handleRefundPay(item) {
				this.modal.currentItem = item;
				this.modal.actionType = 3;
				this.modal.show = true;
				this.modal.title = '确认退款';
				let txt = item.type == 1 ? '确认直接退款给用户？' : '确认收到退货并退款？';
				txt += `\n金额：¥${item.refund_price}`;
				this.modal.content = txt;
			},

			// 弹窗点击回调
			async handleModalClick(e) {
				if (e.index === 0) { // 取消
					this.modal.show = false;
					return;
				}

				const item = this.modal.currentItem;
				const type = this.modal.actionType;
				this.modal.show = false;

				uni.showLoading({ title: '处理中' });

				try {
					let res;
					// 同意/拒绝
					if (type === 1 || type === 2) {
						res = await this.$request({
							url: '/plugin/mch/admin/order/refund-action',
							method: 'POST',
							data: {
								id: item.id,
								status: type,
								remark: type === 2 ? '商家拒绝' : '' // 简化处理，实际应弹窗输入
							}
						});
					} 
					// 打款
					else if (type === 3) {
						res = await this.$request({
							url: '/plugin/mch/admin/order/refund-pay',
							method: 'POST',
							data: { id: item.id }
						});
					}
					// 完成换货
					else if (type === 4) {
						res = await this.$request({
							url: '/plugin/mch/admin/order/refund-exchange-complete',
							method: 'POST',
							data: { id: item.id }
						});
					}

					uni.hideLoading();

					if (res.code === 0) {
						uni.showToast({ title: type === 3 ? '已发起退款' : (type === 4 ? '换货完成' : '操作成功') });
						setTimeout(() => {
							this.loadData(true);
						}, 1000);
					} else {
						uni.showModal({ title: '提示', content: res.msg, showCancel: false });
					}
				} catch (err) {
					uni.hideLoading();
				}
			},

			// 辅助功能
			copyText(text) {
				uni.setClipboardData({ data: text });
			},
			previewImage(urls, current) {
				uni.previewImage({ urls, current });
			},
			getStatusText(status) {
				const map = { 0: '待审核', 1: '待退货', 2: '已拒绝', 3: '已完成', 4: '已关闭', 5: '用户已发货', 6: '退款中' };
				return map[status] || '未知';
			},
			getStatusColor(status) {
				const map = { 0: '#ff9900', 1: '#143A94', 2: '#999', 3: '#2E8B57', 5: '#143A94', 6: '#ff9900' };
				return map[status] || '#333';
			}
		}
	}
</script>

<style lang="scss" scoped>
	/* 基础变量 */
	$primary-color: #143A94;
	$bg-color: #f5f5f5;
	$card-bg: #ffffff;

	.page-container {
		display: flex;
		flex-direction: column;
		height: 100vh;
		background-color: $bg-color;
	}

	/* 1. 搜索栏 */
	.search-section {
		background: #fff;
		padding: 10rpx 0;
	}

	/* 2. 选项卡 */
	.tabs-container {
		background: #fff;
		border-bottom: 1rpx solid #eee;
		.tabs {
			display: flex;
			height: 88rpx;
			align-items: center;
			justify-content: space-around;
			.tab-item {
				position: relative;
				height: 100%;
				display: flex;
				align-items: center;
				font-size: 28rpx;
				color: #666;
				
				.active-text {
					color: $primary-color;
					font-weight: 600;
					font-size: 30rpx;
				}
				.active-line {
					position: absolute;
					bottom: 0;
					left: 50%;
					transform: translateX(-50%);
					width: 40rpx;
					height: 6rpx;
					background: $primary-color;
					border-radius: 3rpx;
				}
			}
		}
	}

	/* 3. 列表内容 */
	.scroll-content {
		flex: 1;
		height: 0;
		.list-wrapper {
			padding: 20rpx;
		}
	}

	/* 订单卡片 */
	.order-card {
		background: #fff;
		border-radius: 16rpx;
		padding: 24rpx;
		margin-bottom: 20rpx;
		
		.card-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 20rpx;
			.header-left {
				display: flex;
				align-items: center;
				font-size: 24rpx;
				.label { color: #999; margin-right: 10rpx; }
				.sn { color: #333; font-weight: 500; font-family: DIN; }
				.copy-btn { margin-left: 16rpx; background: #f0f0f0; padding: 2rpx 10rpx; border-radius: 4rpx; font-size: 20rpx; color: #666; }
			}
			.status-tag {
				font-size: 26rpx;
				font-weight: 600;
			}
		}

		/* 商品 */
		.goods-box {
			display: flex;
			background: #f9f9f9;
			padding: 16rpx;
			border-radius: 8rpx;
			margin-bottom: 20rpx;
			.goods-img {
				width: 120rpx;
				height: 120rpx;
				border-radius: 8rpx;
				flex-shrink: 0;
				margin-right: 20rpx;
			}
			.goods-info {
				flex: 1;
				display: flex;
				flex-direction: column;
				justify-content: space-between;
				.goods-name {
					font-size: 26rpx;
					color: #333;
					line-height: 1.4;
					overflow: hidden;
					text-overflow: ellipsis;
					display: -webkit-box;
					-webkit-line-clamp: 2;
					-webkit-box-orient: vertical;
				}
				.goods-spec {
					font-size: 22rpx;
					color: #999;
				}
			}
		}

		/* 详情Grid */
		.info-grid {
			font-size: 24rpx;
			margin-bottom: 20rpx;
			.grid-row {
				display: flex;
				margin-bottom: 12rpx;
				align-items: flex-start;
				
				&.column {
					flex-direction: column;
				}

				.label {
					color: #999;
					width: 120rpx;
					flex-shrink: 0;
				}
				.mb-10 { margin-bottom: 10rpx; }

				.value {
					color: #333;
					flex: 1;
					&.price { color: #ff4544; font-weight: bold; font-size: 28rpx; }
					/* 退款进度:语义色,不跟主题 */
					&.task-processing, &.task-done { color: #143A94; }
					&.task-attention { color: #ff4544; }
					&.type-tag { 
						display: inline-block; padding: 2rpx 8rpx; border-radius: 4rpx; font-size: 20rpx;
						&.warning { background: #fff0e0; color: #ff9900; }
						&.danger { background: #ffe0e0; color: #ff4544; }
					}
				}
				
				/* 图片列表 */
				.img-list {
					display: flex; flex-wrap: wrap;
					.proof-img {
						width: 100rpx; height: 100rpx; border-radius: 8rpx;
						margin-right: 16rpx; margin-bottom: 10rpx;
						border: 1rpx solid #eee;
					}
				}
			}
		}

		/* 物流盒子 */
		.express-box {
			background: #f0f9eb;
			padding: 16rpx;
			border-radius: 8rpx;
			font-size: 24rpx;
			margin-top: 10rpx;
			
			.express-header {
				display: flex; justify-content: space-between; margin-bottom: 8rpx;
				.tit { font-weight: bold; color: #67c23a; }
				.time { color: #999; font-size: 22rpx; }
			}
			.express-content {
				color: #333;
				.no { margin: 0 10rpx; font-family: DIN; }
				.copy-link { color: $primary-color; text-decoration: underline; }
			}
		}

		/* 底部栏 */
		.card-footer {
			border-top: 1rpx solid #f0f0f0;
			padding-top: 20rpx;
			display: flex;
			justify-content: space-between;
			align-items: center;

			.user-info {
				display: flex; align-items: center;
				.avatar { width: 40rpx; height: 40rpx; border-radius: 50%; margin-right: 10rpx; }
				.name { font-size: 24rpx; color: #666; max-width: 200rpx; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
			}

			.btn-group {
				display: flex; gap: 20rpx;
				.btn {
					margin: 0; padding: 0 28rpx; height: 56rpx; line-height: 54rpx; 
					font-size: 24rpx; border-radius: 28rpx;
					&.plain { background: #fff; border: 1rpx solid #ccc; color: #333; }
					&.primary { background: $primary-color; color: #fff; border: 1rpx solid $primary-color; }
					&.warning { background: #ff9900; color: #fff; border: 1rpx solid #ff9900; }
				}
			}
		}
	}

	.empty-box {
		padding-top: 200rpx;
	}
	.loading-more {
		padding: 30rpx 0;
	}
</style>