<template>
	<tui-page :backgroundColorF5="true">
		<view class="page-container" :style="{ '--theme-color': themeColor }">
			
			<view class="header-section">
				<view class="user-info-box">
					<view class="left-part">
						<image class="user-avatar" :src="info.avatar_url || '/static/images/default-avatar.png'" mode="aspectFill"></image>
						<view class="user-details">
							<view class="user-name">{{info.nickname || '未登录'}}</view>
							<view class="user-parent-tag" v-if="info.parent_name">
								<text class="label">{{setting.header.parent_label || '推荐人'}}</text>
								<text class="divider">|</text>
								<text class="val">{{info.parent_name}}</text>
							</view>
						</view>
					</view>

					<view class="right-part" @click="$T.go('/others/share-poster/share-poster')">
						<view class="qr-btn-pill">
							<tui-icon name="qrcode" size="16" color="#ffffff"></tui-icon>
							<text class="qr-text">推广码</text>
						</view>
					</view>
				</view>

				<view class="header-stats" v-if="info">
					<view class="stat-item" hover-class="ui-press" @click="go(setting.header.balance && setting.header.balance.url)">
						<view class="num">{{info.money || '0.00'}}</view>
						<view class="txt">{{(setting.header.balance && setting.header.balance.name) || $noun('balance')}}</view>
					</view>
					<view class="stat-item" hover-class="ui-press" @click="go(setting.header.price && setting.header.price.url)">
						<view class="num">{{info.price || '0.00'}}</view>
						<view class="txt">{{(setting.header.price && setting.header.price.name) || $noun('commission')}}</view>
					</view>
					<view class="stat-item" hover-class="ui-press" @click="go(setting.header.integral && setting.header.integral.url)">
						<view class="num">{{info.integral || '0'}}</view>
						<view class="txt">{{(setting.header.integral && setting.header.integral.name) || $noun('integral')}}</view>
					</view>
				</view>
			</view>

			<view class="content-area">
				
				<view class="custom-card">
					<view class="card-header">
						<view class="title">{{setting.finance.name}}</view>
						<view class="more" hover-class="ui-press" @click="go(setting.finance.more_url)">
							<text>查看明细</text>
							<tui-icon name="arrowright" size="14" color="#999"></tui-icon>
						</view>
					</view>
					<view class="card-body">
						<!-- 可提现 主区 + 提现按钮 -->
						<view class="withdraw-row">
							<view class="wd-left">
								<view class="wd-label">{{setting.finance.price.name}}</view>
								<view class="wd-val">{{info.price || '0.00'}}</view>
							</view>
							<view class="wd-btn" hover-class="ui-press" @click="goWithdraw">
								<text>{{ $noun('withdraw') }}</text>
								<tui-icon name="arrowright" size="13" color="#ffffff"></tui-icon>
							</view>
						</view>
						<!-- 累计 / 提现中 / 已提现 次要三栏 -->
						<view class="finance-grid sub">
							<view class="f-item">
								<view class="f-val highlight">{{info.total_price || '0.00'}}</view>
								<view class="f-label">{{setting.finance.total.name}}</view>
							</view>
							<view class="f-item" hover-class="ui-press" @click="go(setting.finance.cashing && setting.finance.cashing.url)">
								<view class="f-val">{{info.cashing || '0.00'}}</view>
								<view class="f-label">{{setting.finance.cashing.name}}</view>
							</view>
							<view class="f-item" hover-class="ui-press" @click="go(setting.finance.cash && setting.finance.cash.url)">
								<view class="f-val">{{info.cash || '0.00'}}</view>
								<view class="f-label">{{setting.finance.cash.name}}</view>
							</view>
						</view>
					</view>
				</view>

				<view class="custom-card" v-if="setting.menus && setting.menus.list && setting.menus.list.length > 0">
					<view class="card-header">
						<view class="title">{{setting.menus.name}}</view>
					</view>
					<view class="card-body menu-grid">
						<view class="menu-item" v-for="(menu, index) in setting.menus.list" :key="index" @click="pageTo(menu)">
							<view class="icon-box" :style="{ backgroundColor: menu.iconBgColor }">
								<image :src="menu.icon" mode="aspectFit" class="menu-img"></image>
							</view>
							<view class="menu-text">{{menu.name}}</view>
						</view>
					</view>
				</view>

			</view>
		</view>
	</tui-page>
</template>

<script>
	// [核心] 引入 Vuex
	import { mapState } from 'vuex';

	export default {
		data() {
			return {
				info: {}, 
				is_loading: false,
				setting: {
					header: {
						parent_label: '推荐人',
						// 兜底默认名走品牌词库，未配后台名时跟随商户改词（收益归入 commission 词，默认显示「佣金」）
						balance: { name: this.$noun('balance'), url: '' },
						price: { name: this.$noun('commission'), url: '' },
						integral: { name: this.$noun('integral'), url: '' },
					},
					menus: {
						name: '常用工具',
						list: [],
					},
					finance: {
						name: '我的资产',
						more_url: '',
						total: { name: '累计' + this.$noun('commission') },
						price: { name: '可' + this.$noun('withdraw'), url: '' },
						cashing: { name: this.$noun('withdraw') + '中', url: '' },
						cash: { name: '已' + this.$noun('withdraw'), url: '' },
					},
				},
			}
		},
		computed: {
			// [核心] 获取主题色
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F'
			})
		},
		onShow() {
			// [核心] 刷新导航栏颜色
			this.setNavBarColor();
			this.getSetting()
		},
		methods: {
			// [新增] 动态设置原生导航栏
			setNavBarColor() {
				if (this.themeColor) {
					uni.setNavigationBarColor({
						frontColor: '#ffffff',
						backgroundColor: this.themeColor,
						animation: {
							duration: 300,
							timingFunc: 'easeIn'
						}
					});
				}
			},

			pageTo(e) {
				if(!e.url) return;
				uni.navigateTo({
					url: e.url
				})
			},
			// 统一跳转：链接由后台配置驱动，未配置则不跳转（避免硬编码老页面）
			go(url) {
				if (!url) return;
				this.$T.go(url);
			},
			// 提现按钮：优先走后台配置链接，未配置则兜底直达新提现中心页（不再用旧页面）
			goWithdraw() {
				const url = this.setting.finance.price && this.setting.finance.price.url;
				if (url) {
					this.$T.go(url);
					return;
				}
				uni.navigateTo({ url: '/plugins2/withdraw/withdraw' });
			},
			getSetting() {
				this.$request({
					url: "/api/manage/info"
				}).then(res => {
					if (res.code == 0) {
						if (res.data.setting) {
							if(res.data.setting.header) Object.assign(this.setting.header, res.data.setting.header);
							if(res.data.setting.finance) Object.assign(this.setting.finance, res.data.setting.finance);
							if(res.data.setting.menus) Object.assign(this.setting.menus, res.data.setting.menus);
						}
						// 菜单图标底色归一化：吃掉后台配置的 iconBgColor，未配则用与后台一致的默认浅蓝
						if (this.setting.menus && Array.isArray(this.setting.menus.list)) {
							this.setting.menus.list.forEach(menu => {
								if (!menu.iconBgColor) menu.iconBgColor = '#F3F6FF';
							});
						}
						this.info = res.data.info || {};
					}
				})
			},
		},
		watch: {
			// [核心] 监听颜色变化
			themeColor(val) {
				if(val) this.setNavBarColor();
			}
		}
	}
</script>

<style lang="scss" scoped>
	/* [核心] 定义变量 */
	$theme-color: var(--theme-color);

	.page-container {
		background-color: #F7F8FA; /* 更柔和的通用浅灰背景 */
		min-height: 100vh;
		padding-bottom: 40rpx;
	}

	/* 1. 简约头部区域 */
	.header-section {
		background: $theme-color;
		padding: 50rpx 40rpx 140rpx 40rpx; /* 增加左右留白，提升呼吸感 */
		color: #fff;
		border-bottom-left-radius: 48rpx;
		border-bottom-right-radius: 48rpx;
		position: relative;
	}

	.user-info-box {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 60rpx;

		.left-part {
			display: flex;
			align-items: center;
			
			.user-avatar {
				width: 120rpx;
				height: 120rpx;
				border-radius: 50%;
				border: 4rpx solid rgba(255, 255, 255, 0.4); /* 柔和的边框 */
				margin-right: 30rpx;
				background-color: #f5f5f5;
				box-shadow: 0 4rpx 12rpx rgba(0,0,0,0.05);
			}
			
			.user-details {
				.user-name {
					font-size: 36rpx;
					font-weight: 600;
					margin-bottom: 12rpx;
					letter-spacing: 2rpx;
				}
				
				.user-parent-tag {
					display: inline-flex;
					align-items: center;
					background: rgba(255, 255, 255, 0.15);
					padding: 4rpx 20rpx;
					border-radius: 40rpx;
					font-size: 22rpx;
					
					.label { opacity: 0.8; }
					.divider { margin: 0 10rpx; opacity: 0.5; font-size: 20rpx; }
					.val { font-weight: 500; }
				}
			}
		}

		.right-part {
			/* 优化后的推广码按钮：半透明胶囊状 */
			.qr-btn-pill {
				display: flex;
				align-items: center;
				background: rgba(255, 255, 255, 0.15);
				padding: 12rpx 24rpx;
				border-radius: 40rpx;
				backdrop-filter: blur(4px); /* 毛玻璃效果 */
				transition: all 0.2s;
				
				&:active {
					background: rgba(255, 255, 255, 0.25);
				}
				
				.qr-text {
					font-size: 24rpx;
					margin-left: 8rpx;
					font-weight: 500;
					color: #fff;
				}
			}
		}
	}

	.header-stats {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 0 10rpx;
		
		.stat-item {
			flex: 1;
			text-align: center;
			
			.num {
				font-size: 40rpx;
				font-weight: bold;
				font-family: 'DIN', -apple-system, sans-serif;
				margin-bottom: 8rpx;
				color: #fff;
			}
			
			.txt {
				font-size: 24rpx;
				opacity: 0.8;
				font-weight: 400;
			}
		}
	}

	/* 2. 内容区域 */
	.content-area {
		padding: 0 30rpx;
		margin-top: -80rpx; /* 向上浮动减弱，避免拥挤 */
		position: relative;
		z-index: 10;
	}

	/* 通用卡片样式 - 更极简 */
	.custom-card {
		background: #ffffff;
		border-radius: 24rpx; /* 更大的圆角显得现代 */
		padding: 0 32rpx;
		margin-bottom: 24rpx;
		box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.02); /* 极其微弱的阴影 */
		
		.card-header {
			height: 100rpx;
			display: flex;
			justify-content: space-between;
			align-items: center;
			border-bottom: 1rpx solid #F2F2F2; /* 更柔和的分割线 */
			
			.title {
				font-size: 32rpx;
				font-weight: 600;
				color: #222; /* 加深纯度 */
			}
			
			.more {
				font-size: 24rpx;
				color: #999;
				display: flex;
				align-items: center;
				
				text { margin-right: 4rpx; }
			}
		}
		
		.card-body {
			padding: 36rpx 0;
		}
	}

	/* 资产卡片内部 */
	.finance-grid {
		display: flex;
		flex-wrap: wrap;
		
		.f-item {
			width: 25%;
			text-align: center;
			
			.f-val {
				font-size: 32rpx;
				font-weight: 600;
				font-family: 'DIN', -apple-system, sans-serif;
				color: #333;
				margin-bottom: 12rpx;
				
				/* [核心] 重点数据使用主题色 */
				&.highlight { 
					color: $theme-color; 
					font-size: 36rpx; /* 稍微放大突出 */
				} 
			}
			
			.f-label {
				font-size: 24rpx;
				color: #888;
			}
		}
	}

	/* 菜单卡片内部 */
	.menu-grid {
		display: flex;
		flex-wrap: wrap;
		
		.menu-item {
			width: 25%;
			display: flex;
			flex-direction: column;
			align-items: center;
			margin-bottom: 40rpx; /* 增加行间距 */
			
			.icon-box {
				width: 88rpx;
				height: 88rpx;
				margin-bottom: 16rpx;
				border-radius: 50%; /* 圆形底色，对齐后台预览 */
				display: flex;
				align-items: center;
				justify-content: center;
				
				.menu-img {
					width: 56rpx;
					height: 56rpx;
				}
			}
			
			.menu-text {
				font-size: 24rpx;
				color: #444;
			}
		}
	}

	/* ============ UI 优化：交互反馈 + 细节 ============ */
	.ui-press { opacity: 0.55; }
	.header-stats .stat-item { position: relative; }
	.header-stats .stat-item:not(:last-child)::after {
		content: "";
		position: absolute;
		right: 0;
		top: 50%;
		transform: translateY(-50%);
		width: 1rpx;
		height: 48rpx;
		background: rgba(255, 255, 255, 0.18);
	}
	.finance-grid .f-item {
		border-radius: 14rpx;
		padding: 6rpx 0;
	}

	/* 财务卡：可提现主区 + 提现按钮 */
	.withdraw-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 8rpx 4rpx 32rpx 4rpx;
		border-bottom: 1rpx solid #F2F2F2;

		.wd-left {
			.wd-label {
				font-size: 24rpx;
				color: #888;
				margin-bottom: 10rpx;
			}
			.wd-val {
				font-size: 56rpx;
				font-weight: bold;
				font-family: 'DIN', -apple-system, sans-serif;
				color: var(--theme-color);
				line-height: 1;
			}
		}

		.wd-btn {
			display: flex;
			align-items: center;
			background: var(--theme-color);
			color: #fff;
			font-size: 28rpx;
			font-weight: 500;
			padding: 16rpx 36rpx;
			border-radius: 40rpx;
			box-shadow: 0 6rpx 16rpx rgba(0, 0, 0, 0.08);

			text { margin-right: 4rpx; }
		}
	}

	/* 次要三栏：从 4 列改 3 列 */
	.finance-grid.sub {
		padding-top: 32rpx;
		.f-item { width: 33.333%; }
	}
</style>