<template>
	<view class="page-container">
	 
		<!-- 2. 顶部筛选栏 -->
		<view class="filter-bar">
			<!-- 核销员筛选 -->
			<picker :range="clerkColumns[0]" range-key="name" @change="onClerkConfirm">
				<view class="filter-item">
					<text class="filter-text">{{ selectedClerk.name || '选择核销员' }}</text>
					<tui-icon name="arrowdown" color="#999" :size="10"></tui-icon>
				</view>
			</picker>

			<!-- 月份筛选 -->
			<picker mode="date" fields="month" :value="selectedMonth" @change="onMonthConfirm">
				<view class="filter-item month-filter">
					<text class="filter-text">{{ selectedMonth || '选择月份' }}</text>
					<tui-icon name="calendar" color="#888" :size="15"></tui-icon>
				</view>
			</picker>
		</view>

		<!-- 3. 记录列表 -->
		<scroll-view scroll-y class="list-container" @scrolltolower="onReachBottom" refresher-enabled="true"
			:refresher-triggered="refresherTriggered" @refresherrefresh="onPullDownRefresh">
			
			<tui-no-data v-if="!list.length && loadStatus !== 'loading'" :fixed="false">暂无核销记录</tui-no-data>

			<view v-else class="record-list">
				<view class="record-item" v-for="(item, index) in list" :key="index">
					<view class="info-line">
						<text class="label">凭证条码：</text>
						<text class="value bold">{{ item.order_no }}</text>
						<text class="type-tag" v-if="item.verify_type_name">{{ item.verify_type_name }}</text>
					</view>
					<!-- 如果有商品快照，就显示第一条的核心内容 -->
					<view class="info-line" v-if="item.products_snapshot && item.products_snapshot.length">
						<text class="label">核销商品：</text>
						<text class="value ellipsis">{{ item.products_snapshot[0].name }}</text>
					</view>
					<view class="info-line">
						<text class="label">核销时间：</text>
						<text class="value">{{ item.verify_time }}</text>
					</view>
					<view class="info-line">
						<text class="label">核销专员：</text>
						<text class="value">{{ item.clerk_name }}</text>
					</view>
					<!-- 动态显示的备注信息 (支持店员填写、也支持底层日志直出) -->
					<view class="info-line remark-line" v-if="item.remark">
						<text class="label">操作摘要：</text>
						<text class="value remark-text">{{ item.remark }}</text>
					</view>
				</view>
			</view>
			
			<uni-load-more v-if="list.length" :status="loadStatus" />
		</scroll-view>

	</view>
</template>

<script>
	export default {
		data() {
			return {
				list: [],
				page: 1,
				limit: 15,
				loading: false,
				loadStatus: 'loadmore',
				refresherTriggered: false,

				// 筛选相关
				selectedClerk: { id: '', name: '全部核销员' },
				selectedMonth: '',
				
				// 选择器相关
				showClerkPicker: false,
				clerkColumns: [
					[{ id: null, name: '全部核销员' }]
				], 
				showMonthPicker: false,
				currentDate: Number(new Date()),
			};
		},
		onLoad() {
			this.fetchAllClerks();
			this.getList(true);
		},
		methods: {
			goBack() {
				uni.navigateBack();
			},
			onPullDownRefresh() {
				this.refresherTriggered = true;
				this.getList(true);
			},
			onReachBottom() {
				this.getList();
			},
			
			// --- API 请求 ---
			async fetchAllClerks() {
				try {
					const res = await this.$http.request({ url: '/plugin/mch/admin/clerk/all-clerks' });
					if(res.code === 0) {
						// 将后端返回的列表，构造成 u-picker 需要的格式
						this.clerkColumns = [[{ id: null, name: '全部核销员' }, ...res.data.list]];
					}
				} catch(e) {
					console.error("获取核销员列表失败", e);
				}
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
						url: '/plugin/mch/admin/clerk/records',
						data: {
							page: this.page,
							limit: this.limit,
							clerk_id: this.selectedClerk.id,
							month: this.selectedMonth
						}
					});
					
					if (res.code === 0) {
						const data = res.data.list;
						if (isRefresh) {
							this.list = data;
						} else {
							this.list = this.list.concat(data);
						}
						
						if (!res.data.pagination || res.data.pagination.current_page >= res.data.pagination.total_pages) {
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
					this.refresherTriggered = false;
				}
			},

			// --- 事件处理 ---
			// 原生 picker：e.detail.value 为所选索引
			onClerkConfirm(e) {
				const list = (this.clerkColumns && this.clerkColumns[0]) || [];
				const selected = list[e.detail.value];
				if (!selected) return;
				this.selectedClerk = { id: selected.id, name: selected.name };
				this.getList(true);
			},
			
			// 原生 picker mode=date fields=month 直接给 'YYYY-MM' 字符串
			onMonthConfirm(e) {
				this.selectedMonth = e.detail.value;
				this.getList(true);
			}
		}
	}
</script>

 
<style lang="scss" scoped>
	.page-container {
		display: flex;
		flex-direction: column;
		height: 100vh;
		background-color: #F8F8F8;
	}

	/* 筛选栏 */
	.filter-bar {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 12px 15px;
		background-color: #FFFFFF;
		border-bottom: 1px solid #F0F0F0;
	}

	.filter-item {
		display: flex;
		align-items: center;
		gap: 5px; // 图标和文字的间距
		
		.filter-text {
			font-size: 14px;
			color: #333;
		}
	}
	
	.month-filter {
		padding: 6px 12px;
		border: 1px solid #E0E0E0;
		border-radius: 20px;
	}

	/* 列表区域 */
	.list-container {
		flex: 1;
		height: 100vh;
	}
	
	.record-list {
		background-color: #FFFFFF;
	}

	.record-item {
		padding: 16px 15px;
		border-bottom: 1px solid #F0F0F0;
		
		&:last-child {
			border-bottom: none;
		}

		.info-line {
			display: flex;
			align-items: center;
			line-height: 1.7;
			font-size: 14px;
		}

		.label {
			color: #888888;
		}

		.value {
			color: #333333;
			flex: 1;

			&.bold {
				font-weight: bold;
			}
			&.ellipsis {
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}
			&.remark-text {
				color: #FF9900;
				font-size: 13px;
				line-height: 1.5;
			}
		}

		.type-tag {
			font-size: 11px;
			color: #fff;
			background-color: #2979ff;
			padding: 2px 6px;
			border-radius: 4px;
			margin-left: 8px;
		}

		.remark-line {
			margin-top: 6px;
			padding-top: 6px;
			border-top: 1px dashed #f0f0f0;
			align-items: flex-start;
		}
	}
</style>