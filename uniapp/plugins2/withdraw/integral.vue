<template>
	<tui-page :useCustomNav="true" :transparentNav="true" :showBackBtn="true" :title="$noun('withdraw.title_integral')">

		<!-- 
			[核心] 显式绑定 --theme-color 变量 
			确保小程序内 slot 内容能读取到主题色
		-->
		<view class="integral-page" :style="{ '--theme-color': themeColor, '--nav-top': navTop + 'px' }">
			
			<!-- 1. 头部:可用积分 + 累计获得 + 提现 / 转赠;后台没开就只剩积分和累计 -->
			<asset-hero :label="'当前可用' + $noun('integral')" :amount="integralText" :stats="heroStats"
				:actions="heroActions" :link="withdrawEnabled ? $noun('withdraw') + '记录' : ''" @action="onAction" @link="$T.go('/plugins2/withdraw/records')"></asset-hero>

			<!-- 提现规则:额度 / 每日次数 / 提现时间 / 到账时间直接展示(小程序审核要求);后台没开提现就整张卡不显示 -->
			<view class="rules-card" v-if="withdrawEnabled">
				<withdraw-notice plain asset="integral" :available="stats.integral"></withdraw-notice>
			</view>

			<!-- 3. 积分明细列表 -->
			<view class="logs-container">
				<!-- 标题栏 -->
				<view class="section-header">
					<view class="header-left" @click="$T.go('/others/integral-log/integral-log')">
						<text class="header-title">{{ $noun('integral') }}明细</text>
					</view>
					<!-- 简单的切换 Tab -->
					<view class="simple-tabs">
						<view class="tab-pill" :class="{ 'active': currentTab === 0 }" @click="changeTab(0)">{{ $noun('withdraw.integral_all') }}</view>
						<view class="tab-pill" :class="{ 'active': currentTab === 1 }" @click="changeTab(1)">{{ $noun('withdraw.integral_income') }}</view>
						<view class="tab-pill" :class="{ 'active': currentTab === 2 }" @click="changeTab(2)">{{ $noun('withdraw.integral_expense') }}</view>
					</view>
				</view>
	
				<!-- 列表项 -->
				<view v-if="logList.length > 0">
					<block v-for="(item, index) in logList" :key="index">
						<view class="log-row" @click="toggleDetails(item)">
							<!-- 
								[注意] 这里的图标颜色是【功能色】，不要随主题变
								绿色=获得，橙色=消耗
							-->
							<view class="row-icon" :class="item.type === 1 ? 'icon-income' : 'icon-expense'">
								<uni-icons :type="item.type === 1 ? 'arrowthinup' : 'arrowthindown'" size="16" :color="item.type === 1 ? '#18b566' : '#ff9900'"></uni-icons>
							</view>
							
							<!-- 中间文字 -->
							<view class="row-content">
								<view class="row-title">{{ item.title }}</view>
								<view class="row-time">{{ item.created_time }}</view>
							</view>
							
							<!-- 右侧金额 -->
							<view class="row-amount" :class="item.type === 1 ? 'text-green' : 'text-orange'">
								{{ item.type === 1 ? '+' : '-' }}{{ item.integral }}
							</view>
						</view>
	
						<!-- 展开详情 (内嵌式) -->
						<view class="row-detail-box" v-if="item.showDetails">
							<view class="detail-line">
								<text class="d-label">变动后{{ $noun('integral') }}：</text>
								<text class="d-val">{{ item.current }}</text>
							</view>
							<view class="detail-line" v-if="item.content">
								<text class="d-label">备注说明：</text>
								<text class="d-val">{{ item.content }}</text>
							</view>
						</view>
					</block>
				</view>
	
				<tui-no-data v-if="logList.length === 0 && loadStatus === 'nomore'" :fixed="false">暂无记录</tui-no-data>
				<uni-load-more :status="loadStatus" />
			</view>
	
		</view>
	</tui-page>
</template>

<script>
	// [核心] 引入 Vuex
	import { mapState } from 'vuex';

	import withdrawNotice from './components/withdraw-notice.vue';
	import assetHero from './components/asset-hero.vue';

	export default {
		components: {
			withdrawNotice,
			assetHero
		},
		data() {
			return {
				navTop: 0,
				stats: {
					integral: '0',
					total_integral: '0'
				},
				setting: null,
				logList: [],
				page: 1,
				limit: 15,
				loadStatus: 'loadmore',
				withdrawEnabled: false, // 提现插件入口开关
				transferEnabled: false, // 积分转赠开关（提现插件 finance 配置）
				currentTab: 0, // 0: 全部, 1: 获得, 2: 使用(默认落在「全部」,进页面先看完整流水)
			};
		},
		computed: {
			// 积分是整数,接口给的是 97730.00 这种带小数的写法,去掉小数
			integralText() {
				return String(Math.floor(Number(this.stats.integral) || 0));
			},
			heroStats() {
				return [{ label: this.$noun('withdraw.integral_total_income'), value: String(this.stats.total_integral || '0') }];
			},
			heroActions() {
				const a = [];
				if (this.withdrawEnabled) a.push({ key: 'withdraw', text: this.$noun('withdraw') });
				if (this.transferEnabled) a.push({ key: 'transfer', text: this.$noun('withdraw.integral_transfer') });
				return a;
			},
			// [核心] 获取主题色
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F'
			}),
			// 是否存在可见的操作按钮（提现 or 转赠），用于动态调整底部布局
			hasActions() {
				return this.withdrawEnabled || this.transferEnabled;
			}
		},
		onLoad() {
			this.navTop = uni.getSystemInfoSync().statusBarHeight + 44;
			// [核心] 设置原生导航栏颜色
			this.setNavBarColor();
			// 标题走后台可配完整词库(pages.json 静态标题取不了词)
			uni.setNavigationBarTitle({ title: this.$noun('withdraw.title_integral') });
			// 数据与提现入口统一在 onShow 加载：onShow 首次进页必在 onLoad 后触发，
			// 此处再调会与 onShow 并发拉取同一页 → 记录重复（对齐 price.vue 的正确写法）
		},
		onShow() {
			// 返回本页时刷新数据与提现入口开关
			this.setNavBarColor();
			this.fetchData(true);
			this.fetchEntry();
			this.fetchTransferConfig();
		},
		onPullDownRefresh() {
			this.fetchData(true);
		},
		onReachBottom() {
			if (this.loadStatus === 'loadmore') {
				this.fetchData();
			}
		},
		methods: {
			onAction(key) {
				if (key === 'withdraw') this.goToTransfer();
				else if (key === 'transfer') this.$T.go('/plugins2/withdraw/transfer-integral');
			},
			fetchEntry() {
				this.$request({ url: "/plugin/withdraw/api/withdraw/entry", method: "get", data: { asset_type: "integral" } })
					.then(res => { if (res && res.code === 0) this.withdrawEnabled = res.data.enabled === 1; }).catch(() => {});
			},
			// 积分转赠开关：走提现插件 finance 配置（不再依赖老 cash_setting）
			fetchTransferConfig() {
				this.$request({ url: "/plugin/withdraw/api/transfer/config", method: "get" })
					.then(res => { if (res && res.code === 0) this.transferEnabled = res.data.integral_transfer_enabled === 1; }).catch(() => {});
			},
			// [新增] 动态修改原生导航栏背景色
			setNavBarColor() {
				if (this.themeColor) {
					// #ifdef APP-PLUS
					if (window.plus && plus.navigator) plus.navigator.setStatusBarStyle('dark');
					// #endif
					// custom 模式下 App 端调它会唤回原生导航,见规范 §1.6 红线②
					// #ifndef APP-PLUS
					uni.setNavigationBarColor({
						frontColor: '#ffffff',
						backgroundColor: this.themeColor,
						animation: {
							duration: 300,
							timingFunc: 'easeIn'
						}
					});
					// #endif
				}
			},

			async fetchData(isRefresh = false) {
				if (isRefresh) {
					this.page = 1;
					this.logList = [];
					this.loadStatus = 'loadmore';
				}
				if (this.loadStatus === 'nomore' || this.loadStatus === 'loading') {
					return;
				}
				this.loadStatus = 'loading';
				// 0=全部 1=收入 2=支出。后端 log_type 支持 all，不加 type 条件
				const logType = ['all', 'income', 'expense'][this.currentTab] || 'all';

				try {
					const res = await this.$request({
						url: '/api/finance/integral-overview',
						data: {
							page: this.page,
							limit: this.limit,
							log_type: logType
						}
					});
					uni.stopPullDownRefresh();

					if (res.code === 0) {
						const {
							stats,
							setting,
							list,
							pagination
						} = res.data;
						list.forEach(item => item.showDetails = false);

						if (this.page === 1) {
							this.stats = stats;
							this.setting = setting;
						}
						this.logList.push(...list);
						if (pagination.current_page >= pagination.page_count) {
							this.loadStatus = 'nomore';
						} else {
							this.loadStatus = 'loadmore';
							this.page++;
						}
					} else {
						this.loadStatus = 'nomore';
					}
				} catch (e) {
					this.loadStatus = 'nomore';
					console.error("fetchData error", e);
				}
			},
			changeTab(index) {
				if (this.currentTab === index) return;
				this.currentTab = index;
				this.fetchData(true);
			},
			toggleDetails(item) {
				item.showDetails = !item.showDetails;
			},
			goToTransfer() {
				this.$T.go('/plugins2/withdraw/withdraw?asset=integral');
			},
			goToLogList() {
				this.$T.go('/pages/finance/integral-log');
			}
		},
		watch: {
			// [核心] 监听主题色变化
			themeColor(val) {
				if(val) this.setNavBarColor();
			}
		}
	}
</script>

<style lang="scss" scoped>
	/* [核心] 定义 SCSS 变量指向 CSS 变量 */
	$theme-color: var(--theme-color);

	/* 全局设置 */
	.integral-page {
		background-color: #f7f8fa;
		min-height: 100vh;
	}

	/* 规则卡:往上叠进头部一截(头部没有按钮时叠得浅一点,见 asset-hero) */
	.rules-card {
		position: relative;
		z-index: 2;
		margin: -50rpx 30rpx 0;
		padding: 26rpx 28rpx 14rpx;
		border-radius: 24rpx;
		background: #fff;
		box-shadow: 0 8rpx 26rpx rgba(0, 0, 0, 0.06);
	}

	/* 3. 列表区域 */
	.logs-container {
		padding: 40rpx 30rpx;
	}

	.section-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 30rpx;
	}

	.header-title {
		font-size: 32rpx;
		font-weight: bold;
		color: #333;
		position: relative;
		padding-left: 20rpx;
		
		&::before {
			content: '';
			position: absolute;
			left: 0;
			top: 50%;
			transform: translateY(-50%);
			width: 6rpx;
			height: 28rpx;
			/* [核心] 标题装饰条 - 主题色 */
			background-color: $theme-color;
			border-radius: 4rpx;
		}
	}

	.simple-tabs {
		display: flex;
		background-color: #fff;
		border-radius: 30rpx;
		border: 1rpx solid #eee;
		padding: 4rpx;
		/* 不参与压缩:左边的标题也是可配词条,客户把它改长时
		   flex 默认会去挤右边的胶囊组,三个胶囊比两个更经不起挤。 */
		flex-shrink: 0;
	}

	.tab-pill {
		font-size: 24rpx;
		/* 三个胶囊比两个挤,横向内边距从 28rpx 收到 22rpx —— 实测 375 宽下
		   标题 + 三胶囊约占 222/345px,还余 123px,收一点只是让它更透气。
		   nowrap 是必须的:这几个标签走词库可配,客户改成长词(如「推广收入」)
		   时不能折行把胶囊撑成两行。 */
		padding: 8rpx 22rpx;
		color: #999;
		border-radius: 26rpx;
		white-space: nowrap;
		transition: all 0.3s;
		
		&.active {
			/* [核心] Tab 选中态 - 主题色 */
			background-color: $theme-color;
			color: #fff;
		}
	}

	/* 列表行样式 */
	.log-row {
		display: flex;
		align-items: center;
		background-color: #fff;
		padding: 30rpx;
		border-radius: 16rpx;
		margin-bottom: 20rpx;
		justify-content: space-between; 
	}

	.row-icon {
		width: 80rpx;
		height: 80rpx;
		border-radius: 12rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		margin-right: 24rpx;
		flex-shrink: 0; 
		
		/* 图标背景色保持功能色 */
		&.icon-income { background-color: rgba(24, 181, 102, 0.1); }
		&.icon-expense { background-color: rgba(255, 153, 0, 0.1); }
	}

	.row-content {
		flex: 1;
		margin-right: 20rpx;
		overflow: hidden; 
	}

	.row-title {
		font-size: 28rpx;
		color: #333;
		font-weight: 500;
		margin-bottom: 8rpx;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.row-time {
		font-size: 22rpx;
		color: #aaa;
	}

	.row-amount {
		font-size: 30rpx;
		font-weight: bold;
		flex-shrink: 0; 
		
		&.text-green { color: #18b566; }
		&.text-orange { color: #ff9900; }
	}

	/* 详情展开框 */
	.row-detail-box {
		background-color: #fafafa;
		margin: -10rpx 0 20rpx 0;
		padding: 24rpx 30rpx;
		border-bottom-left-radius: 16rpx;
		border-bottom-right-radius: 16rpx;
		border-top: 1rpx solid #f5f5f5;
	}

	.detail-line {
		display: flex;
		font-size: 24rpx;
		margin-bottom: 8rpx;
		&:last-child { margin-bottom: 0; }
		
		.d-label { color: #999; min-width: 120rpx; }
		.d-val { color: #333; flex: 1; }
	}
</style>