<template>
	<!-- [核心] 绑定 CSS 变量 -->
	<view class="page-container" :style="{ '--theme-color': themeColor }">
		
		<!-- [核心] Tabs 颜色直接绑定主题色变量 -->
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
					<view class="log-item-main">
						<view class="content-info">
							<view class="content-text">{{ item.content }}</view>
							<view class="time-text">{{ item.created_time }}</view>
						</view>

						<view class="amount-info">
							<!-- [核心] 收入样式绑定主题色 (详见下方 style) -->
							<text class="amount-text" :class="item.type == 1 ? 'income' : 'expense'">
								{{ item.type == 1 ? '+' : '-' }}{{ item.money }}
							</text>
							<view class="balance-text">当前: {{ item.current }}</view>
						</view>
					</view>
				</view>
			</block>

			<tui-no-data :fixed="false" v-if="!loading && list.length === 0">
				暂无记录
			</tui-no-data>
		</view>

		<tui-loadmore v-if="loading" :index="3"></tui-loadmore>
		<tui-nomore v-if="is_no_more" backgroundColor="#f5f5f5"></tui-nomore>
	</view>
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
				// ⚠ 后端 BalanceLogListForm 按 (status-1) 过滤 type,而 BalanceLog 里 type=1 是收入(TYPE_ADD)、
				//   type=0 是支出(TYPE_SUB)。故「收入」须 status=2(→type1)、「支出」须 status=1(→type0)。
				//   勿按直觉写成 收入=1/支出=2,否则两个 tab 数据互换(此前的 bug 就是这样)。
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
						url: "/api/finance/balance-log",
						data: {
							status: this.status,
							page: this.page
						}
					});

					if (res.code === 0) {
						if (this.page === 1) {
							this.list = res.data.list;
						} else {
							this.list = this.list.concat(res.data.list);
						}

						if (res.data.pagination.page_count > this.page) {
							this.page++;
						} else {
							this.is_no_more = true;
						}
					}
				} catch (error) {
					console.error("请求数据失败", error);
				} finally {
					this.loading = false;
				}
			},
		}
	}
</script>

<style lang="scss" scoped>
	/* [核心] 定义 SCSS 变量指向 CSS 变量 */
	$theme-color: var(--theme-color);

	.page-container {
		background-color: #f5f6fa;
		min-height: 100vh;
	}

	.log-list-container {
		padding: 20rpx;
	}

	.log-item {
		background-color: #ffffff;
		border-radius: 16rpx;
		margin-bottom: 20rpx;
		padding: 24rpx;
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);
	}

	.log-item-main {
		display: flex;
		justify-content: space-between;
		align-items: center;
	}

	.content-info {
		flex: 1;
		margin-right: 20rpx;

		.content-text {
			font-size: 28rpx;
			color: #333333;
			word-break: break-all;
		}

		.time-text {
			font-size: 24rpx;
			color: #999999;
			margin-top: 12rpx;
		}
	}

	.amount-info {
		text-align: right;

		.amount-text {
			font-size: 36rpx;
			font-weight: bold;

			// [核心] 收入样式：使用主题色
			&.income {
				color: $theme-color;
			}

			// 支出样式：保持黑色或常规色
			&.expense {
				color: #333333;
			}
		}

		.balance-text {
			font-size: 24rpx;
			color: #999999;
			margin-top: 12rpx;
		}
	}
</style>