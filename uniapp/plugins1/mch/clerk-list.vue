<template>
	<view class="page-container" :style="themeStyle">
		<view class="search-bar-container">
			<view class="search-bar">
				<uni-icons type="search" size="20" color="#999"></uni-icons>
				<input class="search-input" placeholder="请输入关键字" v-model="keyword" confirm-type="search"
					@confirm="handleSearch" />
			</view>
		</view>

		<scroll-view scroll-y class="list-container" @scrolltolower="onReachBottom" refresher-enabled="true"
			:refresher-triggered="refresherTriggered" @refresherrefresh="onPullDownRefresh">

			<tui-no-data v-if="!list.length && loadStatus !== 'loading'" :fixed="false">暂无核销员</tui-no-data>

			<view v-else class="clerk-list">
				<view class="clerk-item" v-for="item in list">
					<image :src="item.avatar_url" mode="aspectFill" style="width:75rpx;height:75rpx;border-radius:50%;" />

					<view class="info-content">
						<view class="info-line name-id">
							<text class="nickname">{{ item.nickname }}</text>
							<text class="user-id">({{ item.user_id }})</text>
						</view>
						<view class="info-line meta-info">核销订单: {{ item.verify_count || 0 }}笔</view>
						<view class="info-line meta-info">添加时间: {{ item.created_time }}</view>
					</view>

					<view class="actions">
						<view class="action-btn delete" @click.stop="removeClerk(item)">删除</view>
						<view class="action-btn record" @click.stop="viewRecords(item)">核销记录</view>
					</view>
				</view>
			</view>

			<uni-load-more v-if="list.length" :status="loadStatus" />
		</scroll-view>

		<view class="fab-button" @click="navigateToAdd">
			<tui-icon name="add" color="#fff" :size="14"></tui-icon>
		</view>

		<tui-modal :show="showDeleteModal" title="确认操作" :content="`确定要删除核销员 “${currentItem.nickname}” 吗？`"
			:button="[{text:'取消',type:'gray'},{text:'确定删除',type:'danger'}]"
			@click="onDeleteModalClick" @cancel="showDeleteModal = false">
		</tui-modal>

	</view>
</template>

<script>
	// Script 部分无需修改，逻辑是完善的
	export default {
		data() {
			return {
				keyword: '',
				list: [],
				loading: false,
				page: 1,
				limit: 15,
				loadStatus: 'loadmore', // nomore, loading
				refresherTriggered: false,

				showDeleteModal: false,
				currentItem: {}
			};
		},
		onLoad() {
			uni.setNavigationBarTitle({
				title: '核销员管理'
			});
			uni.$on('clerkAdded', this.handleClerkAdded);
		},
		onShow() {
			this.getList(true);
		},
		onUnload() {

			uni.$off('clerkAdded', this.handleClerkAdded);
		},
		onPullDownRefresh() {
			this.getList(true);
		},
		onReachBottom() {
			this.getList();
		},
		methods: {
			handleClerkAdded() {
				this.getList(true);
			},
			handleSearch() {
				this.getList(true);
			},
			async getList(isRefresh = false) {
				if (this.loading) return;
				if (isRefresh) {
					this.page = 1;
				}
				if (this.loadStatus === 'nomore' && !isRefresh) return;

				this.loading = true;
				this.loadStatus = 'loading';

				try {
					const res = await this.$http.request({
						url: '/plugin/mch/admin/clerk/index',
						data: {
							page: this.page,
							limit: this.limit,
							keyword: this.keyword
						}
					});

					if (res.code === 0) {
						const data = res.data.list;
						if (isRefresh) {
							this.list = data;
						} else {
							this.list = this.list.concat(data);
						}

						if (!res.data.pagination || res.data.pagination.current_page >= res.data.pagination
							.total_pages) {
							this.loadStatus = 'nomore';
						} else {
							this.loadStatus = 'loadmore';
							this.page++;
						}
					} else {
						this.$http.toast(res.msg);
						this.loadStatus = 'loadmore';
					}
				} catch (e) {
					console.error(e);
					this.loadStatus = 'loadmore';
				} finally {
					this.loading = false;
					if (isRefresh) {
						uni.stopPullDownRefresh();
					}
				}
			},
			navigateToAdd() {
				uni.navigateTo({
					url: '/plugins1/mch/clerk-add'
				});
			},
			removeClerk(item) {
				this.currentItem = item;
				this.showDeleteModal = true;
			},
			// tui-modal click 事件携带 {index}：0=取消按钮，1=确定删除按钮
			onDeleteModalClick(e) {
				if (e && e.index === 1) {
					this.confirmDelete();
				} else {
					this.showDeleteModal = false;
				}
			},
			async confirmDelete() {
				this.showDeleteModal = false;
				uni.showLoading({
					title: '正在删除...'
				});
				try {
					const res = await this.$http.request({
						url: '/plugin/mch/admin/clerk/delete',
						method: 'POST',
						data: {
							id: this.currentItem.id
						}
					});
					if (res.code === 0) {
						uni.showToast({
							title: '删除成功',
							icon: 'success'
						});
						this.getList(true);
					} else {
						this.$http.toast(res.msg);
					}
				} finally {
					uni.hideLoading();
				}
			},
			viewRecords(item) {
				uni.navigateTo({
					url: `/plugins1/mch/verify-records?id=` + item.id
				});
			}
		}
	}
</script>

<style lang="scss" scoped>
	page {
		background-color: #F8F8F8;
	}

	.page-container {}

	.search-bar-container {
		padding: 20rpx;
		background-color: #F8F8F8;
		/* [新增] 粘性定位，使其停留在顶部 */
		position: sticky;
		top: 0;
		z-index: 99;
	}

	.search-bar {
		display: flex;
		align-items: center;
		background-color: #fff;
		border-radius: 40rpx;
		padding: 16rpx 30rpx;

		.search-input {
			flex: 1;
			margin-left: 12rpx;
			font-size: 28rpx;
		}
	}

	.list-container {
		/* [新增] 适配粘性布局，防止列表顶部被搜索框遮挡 */
		/* 如果你的页面有原生导航栏，用 var(--window-top) */
		/* padding-top: calc(var(--window-top) + 100rpx); */
	}

	.clerk-list {
		background-color: #FFFFFF;
	}

	.clerk-item {
		display: flex;
		align-items: center;
		padding: 16px 15px;
		background-color: #fff;
		border-bottom: 1px solid #F0F0F0;

		&:last-child {
			border-bottom: none;
		}
	}

	.info-content {
		flex: 1;
		margin-left: 12px;
		/* [新增] 必须设置，否则 flex:1 的元素内部无法正确计算宽度 */
		min-width: 0;

		.info-line {
			line-height: 1.6;
		}

		/* --- [核心修改] 开始 --- */
		.name-id {
			display: flex;
			align-items: baseline;
			font-size: 16px;
			color: #333333;
			font-weight: bold;
		}

		.nickname {
			/* CSS 文本省略三件套 */
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;

			/* 限制一个最大宽度，让省略生效 */
			max-width: 280rpx;
			/* 可根据你的设备宽度微调此数值 */
		}

		.user-id {
			flex-shrink: 0;
			/* 防止ID部分被压缩 */
			margin-left: 8rpx;
			/* 与昵称保持一点距离 */
		}

		/* --- [核心修改] 结束 --- */

		.meta-info {
			font-size: 13px;
			color: #888888;
		}
	}

	.actions {
		display: flex;
		flex-direction: column;
		align-items: flex-end;
		gap: 12px;

		.action-btn {
			font-size: 14px;

			&.delete {
				color: #E54D42;
			}

			&.record {
				color: var(--theme-color);
			}
		}
	}

	.fab-button {
		position: fixed;
		right: 30px;
		bottom: 80px;
		width: 56px;
		height: 56px;
		border-radius: 50%;
		background-color: var(--theme-color);
		display: flex;
		justify-content: center;
		align-items: center;
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
		z-index: 10;
	}
</style>