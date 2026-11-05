<template>
	<tui-page>
		<view class="container">
			<!-- 搜索栏 -->
			<view class="search-bar">
				<uni-search-bar @confirm="onSearch" placeholder="搜索昵称、ID或手机号" cancelButton="none"
					v-model="queryParams.keyword"></uni-search-bar>
			</view>

			<!-- Tabs -->
			<view class="tabs-container">
				<view class="tab-item" :class="{'active': queryParams.type === 'in_store'}"
					@click="changeTab('in_store')">
					进店用户
				</view>
				<view class="tab-item" :class="{'active': queryParams.type === 'recommended'}"
					@click="changeTab('recommended')">
					推荐用户
				</view>
			</view>

			<!-- 会员列表 -->
			<view class="member-list">
				<view v-if="loading && queryParams.page === 1" class="loading-placeholder">
					<uni-load-more status="loading"></uni-load-more>
				</view>
				<template v-else-if="list.length > 0">
					<view class="member-card" v-for="member in list" :key="member.id">
						<view class="member-info">
							<image class="avatar" :src="member.avatar_url" mode="aspectFill"></image>
							<view class="details">
								<view class="name-line">
									<text class="name">{{ member.nickname }} (ID:{{ member.id }})</text>
									<tui-tag v-if="queryParams.type === 'in_store'" type="light-blue"
										padding="8rpx 12rpx" size="22rpx">进店</tui-tag>
									<tui-tag v-else type="light-green" padding="8rpx 12rpx" size="22rpx">推荐</tui-tag>
								</view>
								<view class="phone">电话：{{ member.mobile || '未绑定' }}</view>
								<view class="consumption" v-if="queryParams.type === 'in_store'">
									本店消费金额：<text class="amount">¥ {{ member.total_consumption }}</text>
								</view>
							</view>
						</view>

						<!-- ======================= [核心新增] 开始: 备注显示区域 ======================= -->
						<view class="remark-display" v-if="member.remark">
							<text class="remark-label">备注：</text>
							<text class="remark-content">{{ member.remark }}</text>
						</view>
						<!-- ======================= [核心新增] 结束 ======================= -->

						<view class="member-actions">
							<button class="action-btn" @click="viewOrders(member)">查看订单</button>
							<!-- [核心修改] 修改备注按钮的文字 -->
							<button class="action-btn"
								@click="editRemark(member)">{{ member.remark ? '修改备注' : '添加备注' }}</button>
						</view>
					</view>
					<uni-load-more :status="loadingStatus"></uni-load-more>
				</template>
				<tui-no-data v-else :fixed="false" >
					暂无会员数据
				</tui-no-data>
			</view>
		</view>

		 
		<remark-editor ref="remarkEditor" @confirm="onRemarkConfirm"></remark-editor>
 
	</tui-page>
</template>

<script>
	import RemarkEditor from './components/member-remark-editor.vue';
	

	export default {

		components: {
			RemarkEditor
		},

		data() {
			return {
				queryParams: {
					page: 1,
					limit: 15,
					keyword: '',
					type: 'in_store'
				},
				list: [],
				loading: false,
				pagination: null,
				loadingStatus: 'more'
			};
		},
		onLoad() {
			this.getList(true);
		},
		onReachBottom() {
			if (this.loadingStatus === 'more') {
				this.queryParams.page++;
				this.getList();
			}
		},
		onPullDownRefresh() {
			this.getList(true);
		},
		methods: {
			async getList(refresh = false) {
				if (this.loading && !refresh) return;
				if (refresh) {
					this.queryParams.page = 1;
				}
				this.loading = true;
				this.loadingStatus = 'loading';

				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/member/index',
						data: this.queryParams
					});
					if (res.code === 0) {
						const dataList = res.data.list || [];
						this.list = refresh ? dataList : [...this.list, ...dataList];
						this.pagination = res.data.pagination;
						this.loadingStatus = this.list.length >= (this.pagination?.total_count || 0) ? 'noMore' :
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
			changeTab(type) {
				if (this.queryParams.type === type || this.loading) return;
				this.queryParams.type = type;
				this.getList(true);
			},
			onSearch() {
				this.getList(true);
			},
			viewOrders(member) {
				uni.navigateTo({
					url: `/plugins1/mch/orders?user_id=${member.id}`
				});
			},

			// ======================= [核心修改] 开始: 修改备注方法 =======================
			editRemark(member) {
				// 调用子组件的 open 方法，并把当前会员对象传过去
				this.$refs.remarkEditor.open(member);
			},
			onRemarkConfirm(data) {
				// 监听子组件的 confirm 事件，实现无刷新更新
				const {
					userId,
					remark
				} = data;
				const member = this.list.find(item => item.id === userId);
				if (member) {
					member.remark = remark;
				}
			}
			// ======================= [核心修改] 结束 =======================
		}
	}
</script>

<style lang="scss" scoped>
	.container {
		padding-bottom: 40rpx;
	}

	.search-bar {
		background-color: #fff;
		padding: 10rpx 20rpx;
	}

	.tabs-container {
		display: flex;
		background-color: #fff;
		border-bottom: 1rpx solid #f0f0f0;
		padding: 0 40rpx;
		height: 88rpx;

		.tab-item {
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 0 20rpx;
			font-size: 28rpx;
			color: #666;
			position: relative;
			margin-right: 40rpx;
			width: 50%;

			&.active {
				color: #333;
				font-weight: bold;

				&::after {
					content: '';
					position: absolute;
					bottom: 10rpx;
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

	.member-list {
		padding: 20rpx;
	}

	.member-card {
		background-color: #fff;
		border-radius: 16rpx;
		padding: 30rpx;
		margin-bottom: 20rpx;

		.member-info {
			display: flex;
			align-items: center;
			padding-bottom: 24rpx;
			border-bottom: 1rpx solid #f0f0f0;

			.avatar {
				width: 100rpx;
				height: 100rpx;
				border-radius: 50%;
				flex-shrink: 0;
				margin-right: 24rpx;
			}

			.details {
				flex: 1;

				.name-line {
					display: flex;
					align-items: center;
					justify-content: space-between;

					.name {
						font-size: 30rpx;
						font-weight: bold;
						color: #333;
					}
				}

				.phone {
					font-size: 26rpx;
					color: #666;
					margin-top: 8rpx;
				}

				.consumption {
					font-size: 26rpx;
					color: #333;
					margin-top: 8rpx;

					.amount {
						color: #ff5733;
						font-weight: bold;
					}
				}
			}
		}

		/* ======================= [核心新增] 开始: 备注显示区域样式 ======================= */
		.remark-display {
			background-color: #f7f8fa;
			border-radius: 8rpx;
			padding: 16rpx 20rpx;
			margin-top: 24rpx;
			font-size: 26rpx;
			line-height: 1.6;

			.remark-label {
				color: #999;
				font-weight: bold;
			}

			.remark-content {
				color: #333;
			}
		}

		/* ======================= [核心新增] 结束 ======================= */
		.member-actions {
			display: flex;
			justify-content: flex-end;
			padding-top: 24rpx;
			gap: 20rpx;

			.action-btn {
				margin: 0;
				padding: 0 40rpx;
				height: 60rpx;
				line-height: 60rpx;
				font-size: 26rpx;
				border-radius: 30rpx;
				border: 1rpx solid #999;
				color: #666;
				background-color: #fff;

				&::after {
					border: none;
				}
			}
		}
	}
</style>