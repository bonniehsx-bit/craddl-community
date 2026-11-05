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
					<view class="log-item__header">
						<view class="log-item__info">
							<view class="title">{{ item.title }}</view>
							<view class="description">{{ item.description }}</view>
						</view>
						<view class="log-item__amount">
							<!-- [核心] 金额颜色跟随主题 -->
							<text class="amount-value">{{ item.amount }}</text>
							<text class="amount-unit">{{ $noun('integral') }}</text>
						</view>
					</view>
					<view class="log-item__footer">
						<text>当前{{ $noun('integral') }}：{{ item.balance_after }}</text>
						<text>{{ item.created_time }}</text>
					</view>
				</view>
			</block>
			
			<tui-no-data :fixed="false"  v-if="!loading && list.length === 0">
				暂无记录
			</tui-no-data>
		</view>

		<!-- 去掉了 type="red"，避免与自定义主题色冲突 -->
		<tui-loadmore v-if="loading" :index="3"></tui-loadmore>
		<tui-nomore v-if="is_no_more" backgroundColor="#F5F6FA"></tui-nomore>
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
				navbar: [{
					name: "全部",
					type: 'all'
				}, {
					name: "收入",
					type: 'in'
				}, {
					name: "支出",
					type: 'out'
				}],
				loading: false,
				page: 1,
				is_no_more: false,
				list: [],
				type: 'all' 
			}
		},
		computed: {
			// [核心] 获取主题色，默认红色
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F'
			})
		},
		onLoad() {
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
				if(val) this.setNavBarColor();
			}
		},
		onReachBottom() {
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
				this.type = this.navbar[e.index].type;
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
						url: "/api/finance/integral-log",
						data: {
							page: this.page,
							type: this.type
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
					console.error("Failed to fetch integral log:", error);
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
		background-color: #F5F6FA;
		min-height: 100vh;
	}
	
	.log-list-container {
		padding: 20rpx;
	}

	.log-item {
		background-color: #FFFFFF;
		border-radius: 16rpx;
		padding: 24rpx;
		margin-bottom: 20rpx;
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);

		&__header {
			display: flex;
			justify-content: space-between;
			align-items: flex-start;
			padding-bottom: 20rpx;
			border-bottom: 1rpx solid #F0F0F0;
		}

		&__info {
			flex: 1;
			margin-right: 20rpx;
			
			.title, .description {
				font-size: 28rpx;
				color: #333;
				word-break: break-all;
			}
			
			.description {
				color: #666;
				font-size: 26rpx;
				margin-top: 8rpx;
			}
		}

		&__amount {
			display: flex;
			align-items: baseline;
			white-space: nowrap;

			.amount-value {
				/* [核心] 使用主题色变量 */
				color: $theme-color;
				font-weight: bold;
				font-size: 36rpx;
				margin-right: 8rpx;
			}

			.amount-unit {
				font-size: 24rpx;
				color: #666;
			}
		}

		&__footer {
			display: flex;
			justify-content: space-between;
			color: #999;
			font-size: 24rpx;
			margin-top: 20rpx;
		}
	}
</style>