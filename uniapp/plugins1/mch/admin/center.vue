<template>
	<tui-page :use-custom-nav="true" :transparent-nav="true" :is-pulldown="true" @pulldown="onPullDownRefresh">
		<!-- [核心] 绑定 CSS 变量。用全局 themeStyle 而不是只塞 --theme-color:
			 它一次性给出 --theme-color / -light / -shadow / -color-bg 四个量,
			 渐变和阴影才有得用,不用再手算 rgba(规范 §14.2.1) -->
		<view class="page-container" :style="themeStyle">
			
			<!-- 1. 顶部区域：主题色渐变 + 光晕装饰 -->
			<view class="header-section" :style="headerPadTop ? { paddingTop: headerPadTop } : null">
				<view class="bg-shape-1"></view>
				<view class="bg-shape-2"></view>
				
				<view class="header-inner">
					<view class="user-info">
						<image :src="currentMch.logo || '/static/images/default_avatar.png'" class="avatar" mode="aspectFill"></image>
						<view class="details">
							<text class="name">{{ currentMch.mch_name || '加载中...' }}</text>
							<view class="id-tag" v-if="isManager && uiMchType">
								<text>ID: {{ currentMch.id || '---' }}</text>
								<text class="type-chip" :class="'type-' + uiMchType">{{ typeLabel }}</text>
							</view>
						</view>

						<view class="header-actions">
							<view class="action-btn glass-effect" @click="showSwitchSheet = true" v-if="mchList.length > 1">
								<tui-icon name="switch" size="20" color="#fff"></tui-icon>
								<text class="btn-text">切换</text>
							</view>
							<!-- 店铺码：个人租户没店铺，无核销/扫码场景 → 不展示 -->
							<view class="action-btn glass-effect" @click="$T.go('/plugins1/mch/shop-qrcode')" v-if="!isIndividual">
								<tui-icon name="qrcode" size="20" color="#fff"></tui-icon>
								<text class="btn-text">店铺码</text>
							</view>
						</view>
					</view>
				</view>
			</view>

			<view class="content-wrapper">
				<!-- 销售/营业额统计：管理员可见 + 后台卡片配置控制（cards_config.stats） -->
				<block v-if="isManager && visibleCards.stats">

					<!-- 供应商/商家的 数据看板 -->
					<view class="stats-card animate-fade-in">
						<view class="stats-header">
							<view class="title-row">
								<view class="title-indicator"></view>
								<text class="title">{{ mchType == 1 ? '销售统计' : '营业额统计' }}</text>
							</view>
							
							<!-- 时间切换 Tabs -->
							<view class="time-tabs">
								<view 
									v-for="(tab, index) in timeTabs" 
									:key="tab.period" 
									class="tab-item"
									:class="{'active': activeTimeTab === index}"
									@click="switchTimeTab(index)"
								>
									{{ tab.name }}
								</view>
							</view>
						</view>

						<view class="stats-body">
							<!-- 核心金额：使用主题色高亮 -->
							<view class="main-metric">
								<view class="total-amount theme-text">
									{{ (mchType == 1 ? revenueStats.sales_amount : revenueStats.collection_amount).toFixed(2) }}
								</view>
								<view class="label-under-amount">
									{{ mchType == 1 ? '销售额 (元)' : '总营业额 (元)' }}
								</view>
								<!-- 有退款时才显示净额:平时不打扰,一旦有退款就说清"实际到手多少" -->
								<view class="net-line" v-if="revenueStats.refund_amount > 0">
									扣退款后净额 <text class="net-val">¥{{ revenueStats.net_amount.toFixed(2) }}</text>
								</view>
							</view>

							<view class="divider"></view>

							<!-- 每格 flex:1 均分,3 格 / 4 格都能铺满一行,
								 单数折进标签(「取消 3 单」)而不单独占格,避免第 5 格孤零零掉到下一行 -->
							<view class="breakdown-grid">
								<!-- 供应商特有字段 -->
								<block v-if="mchType == 1">
									<view class="grid-item">
										<text class="val">{{ revenueStats.sales_count }}</text>
										<text class="lbl">销售单数</text>
									</view>
								</block>

								<!-- 商家特有字段 -->
								<block v-if="mchType == 2">
									<view class="grid-item">
										<text class="val">¥{{ revenueStats.online_amount.toFixed(2) }}</text>
										<text class="lbl">线上商品</text>
									</view>

								</block>

								<!-- 通用字段 -->
								<view class="grid-item">
									<text class="val text-red">¥{{ revenueStats.refund_amount.toFixed(2) }}</text>
									<text class="lbl">退款{{ revenueStats.refund_count > 0 ? ' ' + revenueStats.refund_count + ' 笔' : '金额' }}</text>
								</view>
								<view class="grid-item">
									<text class="val text-red">¥{{ revenueStats.cancel_amount.toFixed(2) }}</text>
									<text class="lbl">取消{{ revenueStats.cancel_count > 0 ? ' ' + revenueStats.cancel_count + ' 单' : '金额' }}</text>
								</view>
							</view>
						</view>
					</view>
				</block>

				<!-- 非管理员欢迎提示 -->
				<view class="hint-card" v-if="!isManager">
					<image src="/static/images/toast/img_nodata.png" class="hint-img" mode="aspectFit"></image>
					<text class="hint-text">欢迎使用商家后台，请联系管理员分配权限</text>
				</view>

				<!-- 订单管理：固定卡片，由 cards_config.order 控制显隐 -->
				<view class="status-card" v-if="visibleCards.order">
					<view class="card-header-simple">订单管理</view>
					<view class="status-grid">
						<view class="status-item" @click="$T.go('/plugins1/mch/orders?tab=2')">
							<view class="icon-box">
								<tui-icon name="transport" size="28" :color="themeColor"></tui-icon>
								<view class="badge" v-if="orderStats.to_be_shipped > 0">{{ orderStats.to_be_shipped }}</view>
							</view>
							<text class="label">待发货</text>
						</view>
						<view class="status-item" v-if="mchType === 2" @click="$T.go('/plugins1/mch/orders?tab=2')">
							<view class="icon-box">
								<tui-icon name="search" size="28" :color="themeColor"></tui-icon>
								<view class="badge" v-if="orderStats.to_be_verified > 0">{{ orderStats.to_be_verified }}</view>
							</view>
							<text class="label">待核销</text>
						</view>
						<view class="status-item" @click="$T.go('/plugins1/mch/refund-orders')">
							<view class="icon-box">
								<tui-icon name="service" size="28" :color="themeColor"></tui-icon>
								<view class="badge" v-if="orderStats.processingRefund > 0">{{ orderStats.processingRefund }}</view>
							</view>
							<text class="label">售后/退款</text>
						</view>
					</view>
				</view>

				<!-- 商品管理：固定卡片，由 cards_config.goods 控制显隐 -->
				<view class="status-card" v-if="visibleCards.goods">
					<view class="card-header-simple">商品管理</view>
					<view class="status-grid">
						<view class="status-item" @click="$T.go('/plugins1/mch/admin/goods-list?tab=1')">
							<text class="val-text">{{ goodsTabCounts.on_sale }}</text>
							<text class="label">出售中</text>
						</view>
						<view class="status-item" @click="$T.go('/plugins1/mch/admin/goods-list?tab=2')">
							<text class="val-text">{{ goodsTabCounts.off_shelf }}</text>
							<text class="label">已下架</text>
						</view>
						<view class="status-item" @click="$T.go('/plugins1/mch/admin/goods-list?tab=4')">
							<text class="val-text">{{ goodsTabCounts.on_check }}</text>
							<text class="label">审核中</text>
						</view>
					</view>
				</view>

				<!-- 常用功能菜单 -->
				<view class="action-grid-card">
					<view class="card-header-simple">常用功能</view>
					<view class="action-flex">
						<view class="action-item" @click="btnClick(item)" v-for="(item, index) in actions" :key="index">
							<view class="icon-wrapper" :style="{ backgroundColor: item.pic_url ? 'transparent' : (item.bgColor || '#f0f2f5') }">
								<!-- 如果运营上传了自定义图片图片，优先渲染图片 -->
								<image v-if="item.pic_url" :src="item.pic_url" style="width: 100%; height: 100%; border-radius: 24rpx;" mode="aspectFit"></image>
								<!-- 图标颜色使用 item.color 或 fallback 到主题色 -->
								<tui-icon v-else :name="item.icon || 'star-fill'" :color="item.color || themeColor" :size="14"></tui-icon>
							</view>
							<text class="action-name">{{ item.name }}</text>
						</view>
					</view>
				</view>
			</view>

			<!-- 切换店铺弹窗 -->
			<tui-actionsheet :show="showSwitchSheet" :item-list="mchSheetItemList"
				@click="onMchSheetClick" @cancel="showSwitchSheet = false"></tui-actionsheet>
				
			<!-- 底部安全区 -->
			<view class="safe-area"></view>
		</view>
	</tui-page>
</template>

<script>
	// [核心] 引入 Vuex
	import { mapState } from 'vuex';

	export default {
		data() {
			return {
				showSwitchSheet: false,
				// 沉浸式头部顶部留白:按胶囊/状态栏真实位置动态算,避免头像/二维码被返回键和右上角胶囊遮住
				headerPadTop: '',
				timeTabs: [
					{ name: '今日', period: 'today' },
					{ name: '昨日', period: 'yesterday' },
					{ name: '7日', period: 'week' },
					{ name: '本月', period: 'month' },
					{ name: '累计', period: 'total' }
				],
				activeTimeTab: 0,
				mchType: 0, // 1=供应商, 2=商家
				isManager: false,
				revenueStats: {
					sales_amount: 0.00,
					sales_count: 0,
					collection_amount: 0.00,
					online_amount: 0.00,
					qrcode_amount: 0.00,
					cancel_amount: 0.00,
					net_amount: 0.00,
					refund_amount: 0.00,
					refund_count: 0,
					cancel_count: 0,
				},
				orderStats: {
					to_be_shipped: 0,
					to_be_verified: 0,
					processingRefund: 0 // 确保初始化有这个字段
				},
				goodsStats: {
					on_sale_goods: 0,
					off_shelf_goods: 0,
					on_check_goods: 0,
				},
				// 与 goods-list.vue 同源的精确数量（来自 goods/index tabs_count）
				goodsTabCounts: {
					on_sale:  0,
					off_shelf: 0,
					on_check:  0,
				},
				actions: [],
				// 顶部固定卡片可见性（stats / order / goods）：由 cards_config 后台配置 + 当前商户主体类型过滤
				// 默认 false：等接口返回后再决定显示，避免个人租户看到一闪而过的"商品管理"卡片
				visibleCards: {
					stats: false,
					order: false,
					goods: false,
				},
			};
		},
		computed: {
			// [核心] 获取主题色
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F'
			}),
			currentMch() {
				return this.$store.state.mch.currentMch || {};
			},
			mchList() {
				return this.$store.state.mch.mchList || [];
			},
			mchListForSheet() {
				if (!Array.isArray(this.mchList)) return [];
				return this.mchList.map(item => ({
					name: item.mch_name,
					...item
				}));
			},
			// tui-actionsheet 用 text 字段
			mchSheetItemList() {
				return (this.mchListForSheet || []).map(it => ({ text: it.name }));
			},

			// 主体类型展示。
			// 优先级：getActions 接口最新返回 mchType > store 缓存的 currentMch.type
			// 原因：localStorage 的 currentMch 可能是旧版本数据（无 type 字段或 type 已变）；
			//      mchType 是 getActions 实时从 mch 表查到的，更可信。
			uiMchType() {
				return Number(this.mchType) || Number(this.currentMch && this.currentMch.type) || 0;
			},

			isIndividual() {
				return this.uiMchType === 4;
			},

			typeLabel() {
				const map = { 1: '线上商家', 2: '线下门店', 3: '酒店/民宿', 4: '个人租户' };
				return map[this.uiMchType] || '';
			},
		},
		onLoad() {
			this.computeHeaderPadTop();
			this.initialize(false);
		},
		onPullDownRefresh() {
			this.initialize(true).finally(() => {
				uni.stopPullDownRefresh();
			});
		},
		methods: {
			// 计算沉浸式头部顶部留白:小程序按胶囊真实下沿 + 对称间距,其它端按状态栏 + 导航栏高
			computeHeaderPadTop() {
				let top = 64;
				try {
					const sys = uni.getSystemInfoSync();
					const statusBar = sys.statusBarHeight || 20;
					// #ifdef MP
					const cap = uni.getMenuButtonBoundingClientRect();
					top = cap.bottom + (cap.top - statusBar);
					// #endif
					// #ifndef MP
					top = statusBar + 44;
					// #endif
				} catch (e) {
					top = 64;
				}
				// 再留 12px 呼吸位,内容压到返回键/胶囊下方
				this.headerPadTop = (top + 12) + 'px';
			},

			async initialize(forceRefresh = false) {
				uni.showLoading({ title: '加载中...' });
				try {
					await this.$store.dispatch('mch/initMchInfo', forceRefresh);
					await this.getActions();

					if (this.isManager && this.visibleCards.stats) {
						this.getDashboardStats(this.timeTabs[this.activeTimeTab].period);
					}
					// 商品管理卡片不可见时不拉商品数量
					if (this.visibleCards.goods) {
						this.loadGoodsCounts();
					}
				} catch (error) {
					// Error handling
				} finally {
					uni.hideLoading();
				}
			},
			async getActions() {
				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/mch/get-actions'
					});
					if (res.code === 0) {
						this.isManager = res.data.is_manager;
						this.actions = res.data.list;
						// 后端已按当前商户 mch.type 算好可见性
						if (res.data.visible_cards) {
							this.visibleCards = res.data.visible_cards;
						}
						// 提前用 mch.type 渲染（数据看板的 mchType==1/2 判断也需要它）
						if (res.data.mch_type !== undefined) {
							this.mchType = res.data.mch_type;
						}
					}
				} catch (e) {
					console.error("获取操作菜单失败", e);
				}
			},
			async getDashboardStats(period) {
				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/statistics/dashboard',
						data: {
							period: period
						}
					});
					if (res.code === 0) {
						this.mchType = res.data.mch_type;
						this.revenueStats = res.data.revenue_stats || this.revenueStats;
						this.orderStats = res.data.order_stats || this.orderStats;
						this.goodsStats = res.data.common_stats || this.goodsStats;
					} else {
						this.$util.toast(res.msg);
					}
				} catch (e) {
					console.error("获取首页统计失败", e);
				}
			},
			// 从 goods/index 的 tabs_count 拉取精确数量，与 goods-list.vue 完全同源
			async loadGoodsCounts() {
				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/goods/index',
						data: { page: 1, limit: 1, status: -1, is_check: -1 }
					});
					if (res.code === 0 && res.data.tabs_count) {
						const tc = res.data.tabs_count;
						this.goodsTabCounts = {
							on_sale:   tc['在售']  || 0,
							off_shelf: tc['下架']  || 0,
							on_check:  tc['审核中'] || 0,
						};
					}
				} catch (e) {
					console.error('loadGoodsCounts failed', e);
				}
			},
			btnClick(row) {
				if (row.url) {
					this.$util.go(row.url);
				}
			},
			switchTimeTab(index) {
				this.activeTimeTab = index;
				const period = this.timeTabs[index].period;
				this.getDashboardStats(period);
			},
			onMchSelect(item) {
				this.$store.dispatch('mch/switchMch', item).then(() => {
					uni.showToast({
						title: '切换成功',
						icon: 'none'
					});
					this.initialize(true);
				});
			},
			// tui-actionsheet 的 click 事件携带 {index}
			onMchSheetClick(e) {
				const item = this.mchListForSheet[e.index];
				this.showSwitchSheet = false;
				if (item) this.onMchSelect(item);
			}
		}
	}
</script>

<style lang="scss" scoped>
	/* [核心] 定义 SCSS 变量指向 CSS 变量 */
	$theme-color: var(--theme-color);

	page {
		background-color: #f5f6fa;
	}

	.page-container {
		display: flex;
		flex-direction: column;
		min-height: 100vh;
	}

	/* 1. 顶部区域 - 主题色渐变。
	   原来是写死的高级灰(#D7D8DE→#E6E8EB),换商城主题色时头部纹丝不动、
	   跟下面主题色的数字/装饰条对不上。 */
	.header-section {
		background: var(--theme-color);
		/* 渐变起点用运行时主题色变量(原来是编译期 scss 常量,换肤时对不上) */
		background: linear-gradient(160deg, var(--theme-color) 0%, var(--theme-color-light) 100%);
		position: relative;
		overflow: hidden;
		border-bottom-left-radius: 40rpx;
		border-bottom-right-radius: 40rpx;
		/* 沉浸式:hero 上延到状态栏顶;顶部留出【状态栏 + 导航栏】高度,让头像/名称避开悬浮返回键 */
		padding: calc(var(--status-bar-height, 20px) + 88rpx) 24rpx 140rpx 24rpx;
	}

	/* 背景装饰:改成白色光晕,深浅主题色下都成立 */
	.bg-shape-1, .bg-shape-2 {
		position: absolute;
		border-radius: 50%;
		filter: blur(60rpx);
	}
	.bg-shape-1 {
		width: 300rpx;
		height: 300rpx;
		background: rgba(255, 255, 255, 0.45);
		top: -60rpx;
		right: -60rpx;
	}
	.bg-shape-2 {
		width: 240rpx;
		height: 240rpx;
		background: rgba(255, 255, 255, 0.22);
		bottom: 40rpx;
		left: -70rpx;
	}

	.header-inner {
		position: relative;
		z-index: 2;
	}

	.user-info {
		display: flex;
		align-items: center;

		.avatar {
			width: 110rpx;
			height: 110rpx;
			border-radius: 50%;
			border: 4rpx solid #fff;
			box-shadow: 0 4rpx 12rpx rgba(0,0,0,0.1);
			flex-shrink: 0;
			background-color: #eee;
		}

		.details {
			flex: 1;
			margin: 0 24rpx;
			min-width: 0;

			.name {
				font-size: 34rpx;
				font-weight: 900;
				color: #fff;
				display: block;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
				margin-bottom: 8rpx;
				text-shadow: 0 1rpx 4rpx rgba(0, 0, 0, .12);
			}

			.id-tag {
				display: inline-flex;
				align-items: center;
				gap: 8rpx;
				background: rgba(255,255,255,0.25);
				padding: 2rpx 12rpx;
				border-radius: 8rpx;
				border: 1rpx solid rgba(255,255,255,0.35);

				text {
					font-size: 22rpx;
					color: #fff;
					font-weight: bold;
					font-family: monospace;
				}

				.type-chip {
					padding: 0 8rpx;
					border-radius: 6rpx;
					font-size: 20rpx;
					font-family: inherit;
					color: #fff;
					font-weight: normal;

					&.type-1 { background: #1890ff; }
					&.type-2 { background: #52c41a; }
					&.type-3 { background: #faad14; }
					&.type-4 { background: #722ed1; }
				}
			}
		}

		.header-actions {
			display: flex;
			gap: 16rpx;
		}

		.action-btn {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			width: 90rpx;
			height: 90rpx;
			border-radius: 20rpx;
			
			&.glass-effect {
				background: rgba(255, 255, 255, 0.22);
				border: 1rpx solid rgba(255, 255, 255, 0.38);
			}

			.btn-text {
				font-size: 20rpx;
				color: #fff;
				margin-top: 4rpx;
			}

			&:active {
				background: rgba(255, 255, 255, 0.38);
			}
		}
	}

	.content-wrapper {
		padding: 0 24rpx;
		margin-top: -100rpx; /* 卡片上浮 */
		position: relative;
		z-index: 5;
	}

	/* 数据卡片 */
	.stats-card {
		background-color: white;
		border-radius: 24rpx;
		box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.05);
		padding: 30rpx;
		margin-bottom: 24rpx;

		.stats-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 30rpx;
			flex-wrap: wrap; 
			gap: 20rpx;

			.title-row {
				display: flex;
				align-items: center;
			}

			.title-indicator {
				width: 8rpx;
				height: 28rpx;
				/* [核心] 装饰条颜色 */
				background: $theme-color;
				border-radius: 4rpx;
				margin-right: 16rpx;
			}

			.title {
				font-size: 30rpx;
				font-weight: bold;
				color: #333;
			}

			.time-tabs {
				display: flex;
				background: #f7f8fa;
				border-radius: 8rpx;
				padding: 4rpx;

				.tab-item {
					padding: 6rpx 16rpx;
					font-size: 24rpx;
					color: #999;
					border-radius: 6rpx;
					transition: all 0.2s;

					&.active {
						/* [核心] 选中态文字颜色 */
						color: $theme-color;
						background: #fff;
						font-weight: bold;
						box-shadow: 0 2rpx 8rpx rgba(0,0,0,0.05);
					}
				}
			}
		}

		.stats-body {
			.main-metric {
				text-align: center;
				padding: 20rpx 0;
			}

			.total-amount {
				font-size: 64rpx;
				font-weight: bold;
				font-family: 'DIN-Alternate-Bold', sans-serif;
				line-height: 1;
				margin-bottom: 12rpx;
				
				&.theme-text {
					/* [核心] 核心数字颜色 */
					color: $theme-color;
				}
			}

			.label-under-amount {
				font-size: 24rpx;
				color: #999;
			}

			.net-line {
				display: inline-block;
				margin-top: 14rpx;
				padding: 6rpx 18rpx;
				border-radius: 24rpx;
				background: #f7f8fa;
				font-size: 22rpx;
				color: #909399;

				.net-val {
					color: #606266;
					font-weight: 600;
				}
			}

			.divider {
				height: 1rpx;
				background: #f5f5f5;
				margin: 30rpx 0;
			}

			.breakdown-grid {
				display: flex;

				// flex:1 均分 —— 商家 4 格、供应商 3 格都能整行铺满,
				// 不像固定 25% 那样多一格就孤零零掉到下一行
				.grid-item {
					flex: 1;
					min-width: 0;
					display: flex;
					flex-direction: column;
					align-items: center;
					position: relative;

					// 竖分隔线,几个数字之间有边界感
					& + .grid-item::before {
						content: '';
						position: absolute;
						left: 0;
						top: 8rpx;
						bottom: 8rpx;
						width: 1rpx;
						background: #f0f2f5;
					}

					.val {
						font-size: 30rpx;
						color: #333;
						font-weight: 600;
						margin-bottom: 8rpx;
						font-family: 'DIN', sans-serif;
						white-space: nowrap;

						&.text-red { color: #ff5b5b; }
					}

					.lbl {
						font-size: 22rpx;
						color: #999;
						white-space: nowrap;
					}

					&.clickable:active { opacity: .6; }
				}
			}
		}
	}

	.hint-card {
		background: #fff;
		border-radius: 24rpx;
		padding: 60rpx 30rpx;
		text-align: center;
		margin-bottom: 24rpx;
		box-shadow: 0 4rpx 12rpx rgba(0,0,0,0.03);

		.hint-img {
			width: 200rpx;
			height: 200rpx;
			margin-bottom: 20rpx;
		}
		.hint-text {
			font-size: 26rpx;
			color: #999;
		}
	}

	.status-card {
		background-color: white;
		border-radius: 24rpx;
		padding: 30rpx;
		margin-bottom: 24rpx;
		box-shadow: 0 4rpx 12rpx rgba(0,0,0,0.02);

		.card-header-simple {
			display: flex;
			align-items: center;
			font-size: 30rpx;
			font-weight: bold;
			color: #333;
			margin-bottom: 30rpx;

			// 和上面统计卡的 title-indicator 保持同一套视觉语言
			&::before {
				content: '';
				width: 8rpx;
				height: 28rpx;
				background: $theme-color;
				border-radius: 4rpx;
				margin-right: 16rpx;
			}
		}

		.status-grid {
			display: flex;
			justify-content: space-around;

			.status-item {
				flex: 1;
				display: flex;
				flex-direction: column;
				align-items: center;
				
				// 图标垫一层主题色浅底圆角,和纯图标相比更像"可点的入口"
				.icon-box {
					position: relative;
					width: 88rpx;
					height: 88rpx;
					border-radius: 26rpx;
					background: var(--theme-color-bg);
					display: flex;
					align-items: center;
					justify-content: center;
					margin-bottom: 12rpx;
				}

				.badge {
					position: absolute;
					top: -8rpx;
					right: -8rpx;
					background: #ff5b5b;
					color: #fff;
					font-size: 20rpx;
					padding: 0 8rpx;
					height: 32rpx;
					line-height: 32rpx;
					border-radius: 16rpx;
					border: 2rpx solid #fff;
				}

				.val-text {
					font-size: 36rpx;
					font-weight: bold;
					color: #333;
					margin-bottom: 6rpx;
					font-family: 'DIN', sans-serif;
				}

				.label {
					font-size: 24rpx;
					color: #666;
				}
				
				&:active { opacity: 0.7; }
			}
		}
	}

	.action-grid-card {
		background-color: white;
		border-radius: 24rpx;
		padding: 30rpx;
		margin-bottom: 24rpx;
		box-shadow: 0 4rpx 12rpx rgba(0,0,0,0.02);

		.card-header-simple {
			display: flex;
			align-items: center;
			font-size: 30rpx;
			font-weight: bold;
			color: #333;
			margin-bottom: 20rpx;

			&::before {
				content: '';
				width: 8rpx;
				height: 28rpx;
				background: $theme-color;
				border-radius: 4rpx;
				margin-right: 16rpx;
			}
		}

		.action-flex {
			display: flex;
			flex-wrap: wrap;
		}

		.action-item {
			width: 25%;
			display: flex;
			flex-direction: column;
			align-items: center;
			margin-bottom: 30rpx;
			padding-top: 10rpx;

			.icon-wrapper {
				width: 80rpx;
				height: 80rpx;
				border-radius: 24rpx;
				display: flex;
				justify-content: center;
				align-items: center;
				margin-bottom: 12rpx;
			}

			.action-name {
				font-size: 24rpx;
				color: #555;
			}
			
			&:active { opacity: 0.7; }
		}
	}

	.safe-area {
		height: env(safe-area-inset-bottom);
		width: 100%;
	}

	.animate-fade-in {
		animation: fadeIn 0.4s ease-out;
	}

	@keyframes fadeIn {
		from { opacity: 0; transform: translateY(10px); }
		to { opacity: 1; transform: translateY(0); }
	}
</style>