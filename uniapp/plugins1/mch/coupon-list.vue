<template>
	<tui-page title="优惠券管理">
		<view class="container" :style="themeStyle">
			<!-- 1. 状态筛选:tabList/changeTab 和样式一直都在,只是模板里漏了这段,
				 导致「已失效」根本点不到、后端 status 永远只收到 valid -->
			<view class="tabs-container">
				<view class="tab-item" :class="{ active: activeTab === index }" v-for="(tab, index) in tabList"
					:key="index" @click="changeTab(index)">
					{{ tab.name }}
				</view>
			</view>

			<!-- 2. 优惠券列表 -->
			<scroll-view scroll-y class="list-container" @scrolltolower="loadMore">
				<!-- 加载占位 -->
				<tui-loadmore v-if="loading && queryParams.page === 1" :index="3" text="加载中..."></tui-loadmore>

				<!-- 空状态 -->
				<tui-no-data v-if="!loading && list.length === 0" :fixed="false" @click="createCoupon">
					<text class="tui-color-gray">暂无优惠券</text>
				</tui-no-data>

				<!-- 优惠券卡片循环 -->
				<view class="coupon-item" v-for="(item, index) in list" :key="index" @click="openActionSheet(item)">
					<!-- [MODIFIED] 直接使用预处理好的 item.cardClass 属性 -->
					<view class="coupon-card" :class="item.cardClass">
						<!-- 左侧金额 -->
						<view class="left-part">
							<view class="value"><text class="symbol">¥</text>{{ parseInt(item.sub_price) }}</view>
							<view class="condition">满{{ item.min_price }}可用</view>
						</view>
						<!-- 右侧信息 -->
						<view class="right-part">
							<view class="info-section">
								<view class="title-line">
									<text class="tag">商家券</text>
									<text class="title">{{ item.name }}</text>
								</view>
								<view class="date-line">
									<tui-icon name="clock" color="#999" :size="14"></tui-icon>
									<text v-if="item.time_type == 0">领取后{{ item.day }}天内有效</text>
									<text v-else>{{ item.start_at | formatDate }} -
										{{ item.end_at | formatDate }}</text>
								</view>
							</view>
							<view class="stats-section">
								<text>剩余: {{ item.remaining_count }}</text>
								<text>已发: {{ item.sent_count }}</text>
								<text>已用: {{ item.used_count }}</text>
							</view>
						</view>
						<!-- 角标 -->
						<!-- [MODIFIED] v-if 判断也使用 item.cardClass -->
						<view class="corner-badge" v-if="item.cardClass === 'used'">已用完</view>
						<view class="corner-badge" v-if="item.cardClass === 'expired'">已失效</view>
						<!-- disabled 原来没有角标,手动停用的券和正常券长得一模一样 -->
						<view class="corner-badge" v-if="item.cardClass === 'disabled'">已停用</view>
					</view>
				</view>

				<!-- 列表底部加载更多 -->
				<uni-load-more v-if="list.length > 0" :status="loadStatus"></uni-load-more>
			</scroll-view>

			<!-- 3. 悬浮添加按钮 -->
			<view class="fab-container">
				<button class="fab-btn" @click="createCoupon">
					<uni-icons type="plusempty" size="24" color="#fff"></uni-icons>
					<text>新建</text>
				</button>
			</view>

			<!-- 4. 操作菜单 -->
			<tui-actionsheet :show="showActionSheet" :item-list="actionItems"
				@click="onActionSelect" @cancel="showActionSheet = false"></tui-actionsheet>
		</view>
	</tui-page>
</template>

<script>
	export default {
		data() {
			return {
				tabList: [{
					name: '可使用',
					status: 'valid'
				}, {
					name: '已失效',
					status: 'expired'
				}],
				activeTab: 0,
				queryParams: {
					page: 1,
					limit: 15,
					status: 'valid'
				},
				list: [],
				loading: false,
				pagination: null,
				loadStatus: 'more',
				showActionSheet: false,
				actions: [{
						name: '编辑'
					},
					{
						name: '发放',
						subname: '手动向用户发放优惠券'
					},
					{
						name: '持有会员',
					},
					{
						name: '删除',
						color: '#fa3534'
					}
				],
				currentItem: null,
			};
		},
		filters: {
			formatDate(timestamp) {
				if (!timestamp) return '';
				const date = new Date(timestamp * 1000);
				return `${date.getFullYear()}.${String(date.getMonth() + 1).padStart(2, '0')}.${String(date.getDate()).padStart(2, '0')}`;
			}
		},
		onLoad() {
			this.getList(true);
		},
		onReachBottom() {
			this.loadMore();
		},
		computed: {
			// tui-actionsheet 用 text 字段，复用 actions 数据并映射 name → text
			actionItems() {
				return (this.actions || []).map(a => ({ text: a.name, color: a.color }));
			},
		},
		methods: {
			async getList(refresh = false) {
				if (this.loading) return;
				if (refresh) this.queryParams.page = 1;
				if (!refresh && this.loadStatus === 'nomore') return;

				this.loading = true;
				this.loadStatus = 'loading';
				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/coupon/index',
						data: this.queryParams
					});
					if (res.code === 0) {
						let dataList = res.data.list || [];

						// [MODIFIED] 核心改动：在此处预处理数据
						// 遍历从后端获取的列表
						dataList.forEach(item => {
							// 调用方法计算出 class，并添加到 item 对象上
							item.cardClass = this.calculateCardClass(item);
						});

						this.list = refresh ? dataList : [...this.list, ...dataList];
						this.pagination = res.data.pagination;
						this.loadStatus = this.list.length >= (this.pagination?.total_count || 0) ? 'nomore' :
							'more';
					} else {
						this.loadStatus = 'more';
					}
				} finally {
					this.loading = false;
				}
			},
			// [MODIFIED] 方法重命名为 calculateCardClass 以避免混淆，其逻辑不变
			// 这个方法现在只在 getList 方法内部被调用，而不再被模板直接调用
			calculateCardClass(item) {
				if (item.is_delete == 1 || item.is_expire == 1) {
					return 'expired';
				}
				if (item.is_enabled == 0) {
					return 'disabled';
				}
				if (item.total_count != -1 && item.remaining_count <= 0) {
					return 'used';
				}
				return 'valid';
			},
			changeTab(index) {
				if (this.activeTab === index) return;
				this.activeTab = index;
				this.queryParams.status = this.tabList[index].status;
				this.getList(true);
			},
			loadMore() {
				if (this.loadStatus === 'more') {
					this.queryParams.page++;
					this.getList();
				}
			},
			createCoupon() {
				uni.navigateTo({
					url: '/plugins1/mch/coupon-edit'
				});
			},
			openActionSheet(item) {
				this.currentItem = item;
				this.showActionSheet = true;
			},
			onActionSelect(e) {
				if (!this.currentItem) return;
				const action = this.actions[e.index];
				if (!action) return;
				this.showActionSheet = false;
				switch (action.name) {
					case '编辑':
						uni.navigateTo({
							url: `/plugins1/mch/coupon-edit?id=${this.currentItem.id}`
						});
						break;
					case '发放':
						uni.navigateTo({
							url: `/plugins1/mch/coupon-grant?id=${this.currentItem.id}`
						});
						break;
					case '持有会员':
						uni.navigateTo({
							url: `/plugins1/mch/coupon-holders?id=${this.currentItem.id}`
						});
						break;
					case '删除':
						this.deleteCoupon(this.currentItem);
						break;
				}
			},
			deleteCoupon(item) {
				uni.showModal({
					title: '提示',
					content: `确定要删除优惠券 "${item.name}" 吗？`,
					success: async (res) => {
						if (res.confirm) {
							uni.showLoading({
								title: '删除中...'
							});
							try {
								const apiRes = await this.$request({
									url: '/plugin/mch/admin/coupon/delete',
									method: 'POST',
									data: {
										id: item.id
									}
								});
								if (apiRes.code === 0) {
									uni.showToast({
										title: '删除成功',
										icon: 'success'
									});
									this.getList(true);
								} else {
									this.$showModal(apiRes.msg);
								}
							} finally {
								uni.hideLoading();
							}
						}
					}
				});
			}
		}
	}
</script>

<style lang="scss" scoped>
	.container {
		display: flex;
		flex-direction: column;
		height: 100vh;
		background-color: #f8f8f8;
	}

	.tabs-container {
		display: flex;
		justify-content: space-around;
		background-color: #fff;
		border-bottom: 1rpx solid #f0f0f0;
		flex-shrink: 0; // 容器是 height:100vh 的 flex 列,不锁死会被列表挤扁

		.tab-item {
			flex: 1;
			text-align: center;
			padding: 24rpx 0;
			font-size: 28rpx;
			color: #666;
			position: relative;
			transition: all 0.2s;
		}

		.active {
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
				border-radius: 3rpx;
			}
		}
	}

	.list-container {
		flex: 1;
		height: 0;
		padding: 24rpx;
		padding-bottom: 180rpx;
		box-sizing: border-box;
	}

	.coupon-item {
		margin-bottom: 30rpx;
		filter: drop-shadow(0 4rpx 20rpx rgba(0, 0, 0, 0.06));
	}

	.coupon-card {
		display: flex;
		height: 200rpx;
		position: relative;
		overflow: hidden;

		&::before,
		&::after {
			content: '';
			position: absolute;
			width: 40rpx;
			height: 40rpx;
			background-color: #f8f8f8;
			border-radius: 50%;
			left: 180rpx;
			transform: translateX(-50%);
		}

		&::before {
			top: -20rpx;
		}

		&::after {
			bottom: -20rpx;
		}

		&.disabled .left-part,
		&.used .left-part,
		&.expired .left-part {
			background: linear-gradient(135deg, #E0E0E0, #BDBDBD);
		}

		&.disabled .title,
		&.used .title,
		&.expired .title {
			color: #999;
		}
	}

	.left-part {
		width: 200rpx;
		// 原来写死一套蓝色渐变,和商城主题色无关;改走 themeStyle 下发的变量(规范 §14.2.1)
		background: linear-gradient(135deg, var(--theme-color), var(--theme-color-light));
		border-radius: 16rpx 0 0 16rpx;
		color: #fff;
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
		flex-shrink: 0;
		border-right: 1rpx dashed #fff;

		.value {
			font-size: 64rpx;
			font-weight: bold;

			.symbol {
				font-size: 32rpx;
			}
		}

		.condition {
			font-size: 24rpx;
			margin-top: 8rpx;
		}
	}

	.right-part {
		flex: 1;
		background-color: #fff;
		border-radius: 0 16rpx 16rpx 0;
		padding: 20rpx;
		display: flex;
		flex-direction: column;
		justify-content: space-between;

		.info-section {
			.title-line {
				display: flex;
				align-items: center;

				.tag {
					background-color: var(--theme-color-bg);
					color: var(--theme-color);
					font-size: 20rpx;
					padding: 4rpx 8rpx;
					border-radius: 4rpx;
					margin-right: 12rpx;
					flex-shrink: 0;
				}

				.title {
					font-size: 28rpx;
					font-weight: 500;
					color: #333;
					overflow: hidden;
					text-overflow: ellipsis;
					white-space: nowrap;
				}
			}

			.date-line {
				display: flex;
				align-items: center;
				font-size: 22rpx;
				color: #999;
				margin-top: 10rpx;

				text {
					margin-left: 8rpx;
				}
			}
		}

		.stats-section {
			font-size: 22rpx;
			color: #666;
			display: flex;
			justify-content: space-between;
			border-top: 1rpx dashed #eee;
			padding-top: 16rpx;
			margin-top: 16rpx;
		}
	}

	.corner-badge {
		position: absolute;
		top: -2rpx;
		right: -2rpx;
		background-color: #BDBDBD;
		color: white;
		padding: 4rpx 20rpx 4rpx 30rpx;
		font-size: 22rpx;
		border-radius: 0 16rpx 0 16rpx;
	}

	.fab-container {
		position: fixed;
		right: 40rpx;
		bottom: 120rpx;
		z-index: 10;

		.fab-btn {
			width: 180rpx;
			height: 80rpx;
			border-radius: 40rpx;
			background: linear-gradient(135deg, var(--theme-color), var(--theme-color-light));
			color: #fff;
			display: flex;
			justify-content: center;
			align-items: center;
			box-shadow: 0 6rpx 20rpx var(--theme-shadow);
			font-size: 28rpx;

			&::after {
				border: none;
			}

			text {
				margin-left: 8rpx;
			}
		}
	}
</style>