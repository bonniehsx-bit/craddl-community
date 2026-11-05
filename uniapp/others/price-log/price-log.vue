<template>
	<tui-page backgroundColor="#F5F6FA">
		<!-- [核心] 增加容器 view 用于绑定 CSS 变量 -->
		<view class="container" :style="{ '--theme-color': themeColor }">
			
			<!-- [核心] Tabs 颜色绑定主题色 -->
			<tui-tabs 
				:tabs="navbar" 
				:currentTab="currentTab"
				:selectedColor="themeColor" 
				:sliderBgColor="themeColor" 
				itemWidth="33.333%"
				@change="tabChange">
			</tui-tabs>
	
			<view class="log-list-container">
				<block v-if="list.length > 0">
					<view class="log-item" v-for="(item, index) in list" :key="index">
						<view class="item-details">
							<view class="remarks-text">{{ item.remarks }}</view>
							<view class="time-text">{{ item.created_time }}</view>
						</view>
						<view class="item-amount">
							<!-- [核心] 收入样式绑定主题色 -->
							<text :class="item.type == 1 ? 'income-text' : 'expense-text'">
								{{ item.type == 1 ? '+' : '-' }}{{ item.price }}
							</text>
							<text class="unit-text">元</text>
						</view>
					</view>
				</block>
				
				<tui-no-data v-if="list.length === 0 && !loading" :fixed="false">
					暂无记录
				</tui-no-data>
			</view>
	
			<!-- 去掉 type="primary" 避免颜色冲突 -->
			<tui-loadmore v-if="loading" :index="3"></tui-loadmore>
			<tui-nomore v-if="is_no_more" backgroundColor="#F5F6FA"></tui-nomore>
		</view>
	</tui-page>
</template>

<script>
	// [核心] 引入 Vuex
	import { mapState } from 'vuex';

	import TuiTabs from '@/others/components/tui-tabs/tui-tabs.vue'
	export default {
		components: { TuiTabs },
		data() {
			return {
				currentTab: 0,
				// ⚠ 后端 PriceLogListForm 按 status 过滤:status=1→type=0、status=2→type=1,而 PriceLog 里
				//   type=1 是收入(TYPE_ADD/增加)、type=0 是支出(TYPE_SUB/减少)。故「收入」须 status=2(→type1)、
				//   「支出」须 status=1(→type0)。勿按直觉写成 收入=1/支出=2,否则两个 tab 数据互换(此前的 bug)。
				navbar: [{
					name: "全部",
					status: 0
				}, {
					name: "收入",
					status: 2
				}, {
					name: "支出",
					status: 1
				}],
				loading: false,
				status: 0,
				page: 1,
				is_no_more: false,
				list: [],
			}
		},
		computed: {
			// [核心] 获取主题色，默认红色
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F'
			})
		},
		onLoad: function(e) {
			// [核心] 设置导航栏颜色
			this.setNavBarColor();
			this.getList();
			
			// 页面访问上报 (基于游客UUID验证)
			this.reportVisit();
		},
		onShow() {
			this.setNavBarColor();
		},
		watch: {
			// [核心] 监听主题色变化
			themeColor(val) {
				if (val) this.setNavBarColor();
			}
		},
		onReachBottom: function(e) {
			this.getList();
		},
		methods: {
			// [核心] 动态设置导航栏背景色
			setNavBarColor() {
				if (this.themeColor) {
					uni.setNavigationBarColor({
						frontColor: '#ffffff',
						backgroundColor: this.themeColor,
						animation: { duration: 300, timingFunc: 'easeIn' }
					});
				}
			},

			tabChange(e) {
				this.currentTab = e.index;
				this.status = this.navbar[e.index].status;
				this.page = 1;
				this.is_no_more = false;
				this.list = [];
				this.getList();
			},
			
			async getList() {
				if (this.loading || this.is_no_more) {
					return;
				}
				this.loading = true;

				try {
					const res = await this.$request({
						url: "/api/finance/price-log",
						data: {
							status: this.status,
							page: this.page
						}
					});

					if (res.code === 0) {
						this.list = this.page === 1 ? res.data.list : this.list.concat(res.data.list);
						
						if (res.data.pagination.page_count > this.page) {
							this.page++;
						} else {
							this.is_no_more = true;
						}
					}
				} catch (error) {
					console.error("Failed to fetch data:", error);
				} finally {
					this.loading = false;
				}
			},

			// [新增] 单页访问量上报 (不阻塞页面)
			reportVisit() {
				// 获取或生成游客的UUID
				let touristId = uni.getStorageSync('H5_TOURIST_V_ID');
				if (!touristId) {
					touristId = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
						var r = Math.random() * 16 | 0,
							v = c == 'x' ? r : (r & 0x3 | 0x8);
						return v.toString(16);
					});
					uni.setStorageSync('H5_TOURIST_V_ID', touristId);
				}
				
				this.$request({
					url: '/api/index/visit-report',
					method: 'POST',
					data: {
						tourist_id: touristId
					}
				}).catch(e => {
					console.error('统计接口上报失败', e);
				});
			},
		}
	}
</script>

<style lang="scss" scoped>
	/* [核心] 定义 SCSS 变量指向 CSS 变量 */
	$theme-color: var(--theme-color);

	.container {
		/* 确保容器占满高度 */
		min-height: 100vh;
	}

	.log-list-container {
		padding: 24rpx;
	}

	.log-item {
		display: flex;
		justify-content: space-between;
		align-items: center;
		background-color: #ffffff;
		padding: 30rpx;
		border-radius: 16rpx;
		margin-bottom: 20rpx;
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);
	}

	.item-details {
		flex: 1;
		margin-right: 24rpx;

		.remarks-text {
			font-size: 28rpx;
			color: #333;
			word-break: break-all;
			white-space: normal;
		}

		.time-text {
			font-size: 24rpx;
			color: #999;
			margin-top: 12rpx;
		}
	}

	.item-amount {
		display: flex;
		align-items: baseline;
		white-space: nowrap;

		.income-text,
		.expense-text {
			font-size: 34rpx;
			font-weight: bold;
		}

		// [核心] 收入颜色使用主题色
		.income-text {
			color: $theme-color;
		}

		// 支出保持深色
		.expense-text {
			color: #333333;
		}
		
		.unit-text {
			font-size: 24rpx;
			color: #666;
			margin-left: 8rpx;
		}
	}
</style>