<template>
	<view class="page-container">

		<!-- 顶部导航已提取到父组件 user.vue -->

		<!-- 2. 内容滚动区 -->
		<!-- paddingTop = 状态栏(top) + 导航栏(44px) -->
		<view class="content-wrapper" :style="{ paddingTop: (top + 44) + 'px' }">

			<!-- A. 立体感用户卡片 -->
			<view class="user-card-wrap" @click="!isLogin ? goToLogin() : ''">
				<view class="user-card-3d">
					<view class="level-tag" v-if="isLogin && userInfo.level_name">
						<text>{{ userInfo.level_name }}</text>
					</view>
					<!-- 用户基础信息 -->
					<view class="user-main">
						<view class="avatar-group">
							<image v-if="isLogin" :src="userInfo.avatar_url" mode="aspectFill"
								class="avatar-img" @click="$T.go('/others/setting/setting')"
								style="width:128rpx;height:128rpx;border-radius:50%;" />
							<view v-else class="avatar-placeholder">
								<tui-icon name="people" size="40" color="#999"></tui-icon>
							</view>
						</view>

						<view class="info-group">
							<view class="nickname-row">
								<text class="nickname">{{ isLogin ? userInfo.nickname : '登录 / 注册' }}</text>
							</view>
							<view class="uid-row" v-if="isLogin" @click.stop="copyId(userInfo.id)">
								<text>ID: {{ userInfo.id }}</text>
								<tui-icon name="copy" size="11" color="#999" style="margin-left: 8rpx;"></tui-icon>
								<text class="mobile-inline" v-if="isLogin && formattedPageSetting.header && formattedPageSetting.header.show_mobile == 1 && userInfo.mobile" style="margin-left:10rpx;opacity:0.8;font-size:22rpx;">· {{ userInfo.mobile }}</text>
							</view>
							<view class="uid-row" v-else>
								<text>点击登录体验完整功能</text>
							</view>
						</view>

						<!-- 签到按钮 -->
						<view class="check-btn click-bounce" style="display: none;" v-if="isLogin"
							@click.stop="$T.go('/plugins/integral/sign')">
							<text>签到</text>
						</view>
					</view>

					<!-- A.2 头部统计数据 (收藏/足迹/订单数) -->
					<view class="header-stats-row" v-if="hasHeaderStats">
						<view class="h-stat-item click-bounce" v-if="formattedPageSetting.header.goods.is_show == 1"
							@click.stop="$T.go('/others/collect/collect')">
							<text class="num">{{ isLogin ? formattedPageSetting.header.goods.value : 0 }}</text>
							<text class="lbl">{{ formattedPageSetting.header.goods.name }}</text>
						</view>
						<view class="h-stat-item click-bounce" v-if="formattedPageSetting.header.footmark.is_show == 1"
							@click.stop="$T.go('/others/footmark/footmark')">
							<text class="num">{{ isLogin ? formattedPageSetting.header.footmark.value : 0 }}</text>
							<text class="lbl">{{ formattedPageSetting.header.footmark.name }}</text>
						</view>
						<view class="h-stat-item click-bounce"
							v-if="formattedPageSetting.header.order && formattedPageSetting.header.order.is_show == 1"
							@click.stop="$T.go('/others/order/order?status=-1')">
							<text class="num">{{ isLogin ? formattedPageSetting.header.order.value : 0 }}</text>
							<text class="lbl">{{ formattedPageSetting.header.order.name }}</text>
						</view>
					</view>
				</view>
				<!-- 装饰阴影 -->
				<view class="card-shadow-layer"></view>
			</view>

			<!-- B. 资产模块 (我的钱包) -->
			<view class="section-card"
				v-if="formattedPageSetting && formattedPageSetting.finance && formattedPageSetting.finance.is_show == 1">

				<view class="card-header" @click.stop="onFinanceClick">
					<text class="title">{{ formattedPageSetting.finance.name }}</text>
					<view class="more">
						<text>资金管理</text>
						<tui-icon name="arrowright" size="12" color="#ccc"></tui-icon>
					</view>
				</view>

				<view class="finance-row">
					<!-- 余额 -->
					<view class="f-item click-bounce" v-if="formattedPageSetting.finance.balance.is_show == 1"
						@click="isLogin && goAsset('balance', '/plugins2/withdraw/balance')">
						<text class="amount" :style="formattedPageSetting.finance.balance.color ? 'color:' + formattedPageSetting.finance.balance.color : ''">{{ isLogin ? userInfo.money : '0.00' }}</text>
						<text class="label">{{ formattedPageSetting.finance.balance.name }}</text>
					</view>

					<view class="f-item click-bounce"
						v-if="formattedPageSetting.finance.price && formattedPageSetting.finance.price.is_show == 1"
						@click="isLogin && goAsset('price', '/plugins2/withdraw/price')">
						<text class="amount gold-text" :style="formattedPageSetting.finance.price.color ? 'color:' + formattedPageSetting.finance.price.color : ''">{{ isLogin ? userInfo.price : '0.00' }}</text>
						<text class="label">{{ formattedPageSetting.finance.price.name }}</text>
					</view>

					<view class="f-item click-bounce" v-if="formattedPageSetting.finance.integral.is_show == 1"
						@click="isLogin && goAsset('integral', '/plugins2/withdraw/integral')">
						<text class="amount" :style="formattedPageSetting.finance.integral.color ? 'color:' + formattedPageSetting.finance.integral.color : ''">{{ isLogin ? userInfo.integral : 0 }}</text>
						<text class="label">{{ formattedPageSetting.finance.integral.name }}</text>
					</view>

					<view class="f-item click-bounce" v-if="formattedPageSetting.finance.coupon.is_show == 1"
						@tap="goAsset('coupon', '/others/coupon/coupon?status=1')">
						<text class="amount" :style="formattedPageSetting.finance.coupon.color ? 'color:' + formattedPageSetting.finance.coupon.color : ''">{{ isLogin ? userInfo.coupon : 0 }}</text>
						<text class="label">{{ formattedPageSetting.finance.coupon.name }}</text>
					</view>
				</view>
			</view>

			<!-- C. 订单模块 -->
			<view class="section-card"
				v-if="formattedPageSetting && formattedPageSetting.order && formattedPageSetting.order.is_show != 0">
				<view class="card-header" @click="$T.go('/others/order/order?status=-1')">
					<text class="title">{{ formattedPageSetting.order.title }}</text>
					<view class="more">
						<text>{{ formattedPageSetting.order.sub_title }}</text>
						<tui-icon name="arrowright" size="12" color="#ccc"></tui-icon>
					</view>
				</view>

				<view class="grid-box">
					<!-- 待付款 -->
					<view class="grid-item click-bounce" v-if="formattedPageSetting.order.status_0.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_0.url)">
						<view class="icon-wrap">
							<image :src="formattedPageSetting.order.status_0.icon" class="main-icon"></image>
							<view class="dot-badge" v-if="isLogin && formattedPageSetting.order.status_0.num > 0">
								{{ formattedPageSetting.order.status_0.num }}</view>
						</view>
						<text class="grid-text">{{ formattedPageSetting.order.status_0.name }}</text>
					</view>

					<!-- 待发货 -->
					<view class="grid-item click-bounce" v-if="formattedPageSetting.order.status_1.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_1.url)">
						<view class="icon-wrap">
							<image :src="formattedPageSetting.order.status_1.icon" class="main-icon"></image>
							<view class="dot-badge" v-if="isLogin && formattedPageSetting.order.status_1.num > 0">
								{{ formattedPageSetting.order.status_1.num }}</view>
						</view>
						<text class="grid-text">{{ formattedPageSetting.order.status_1.name }}</text>
					</view>

					<!-- 待收货 -->
					<view class="grid-item click-bounce" v-if="formattedPageSetting.order.status_2.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_2.url)">
						<view class="icon-wrap">
							<image :src="formattedPageSetting.order.status_2.icon" class="main-icon"></image>
							<view class="dot-badge" v-if="isLogin && formattedPageSetting.order.status_2.num > 0">
								{{ formattedPageSetting.order.status_2.num }}</view>
						</view>
						<text class="grid-text">{{ formattedPageSetting.order.status_2.name }}</text>
					</view>

					<!-- 待评价 -->
					<view class="grid-item click-bounce" v-if="formattedPageSetting.order.status_3.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_3.url)">
						<view class="icon-wrap">
							<image :src="formattedPageSetting.order.status_3.icon" class="main-icon"></image>
							<view class="dot-badge" v-if="isLogin && formattedPageSetting.order.status_3.num > 0">
								{{ formattedPageSetting.order.status_3.num }}</view>
						</view>
						<text class="grid-text">{{ formattedPageSetting.order.status_3.name }}</text>
					</view>

					<!-- 售后 -->
					<view class="grid-item click-bounce" v-if="formattedPageSetting.order.status_4.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_4.url)">
						<view class="icon-wrap">
							<image :src="formattedPageSetting.order.status_4.icon" class="main-icon"></image>
							<view class="dot-badge" v-if="isLogin && formattedPageSetting.order.status_4.num > 0">
								{{ formattedPageSetting.order.status_4.num }}</view>
						</view>
						<text class="grid-text">{{ formattedPageSetting.order.status_4.name }}</text>
					</view>
				</view>
			</view>

			<!-- D. 常用工具 (菜单1) -->
			<view class="section-card"
				v-if="formattedPageSetting && formattedPageSetting.menus && formattedPageSetting.menus.is_show == 1">
				<view class="card-header">
					<text class="title">{{ formattedPageSetting.menus.name }}</text>
				</view>

				<!-- Grid模式 -->
				<view class="grid-box tools-grid" v-if="formattedPageSetting.menus.type == 0">
					<view class="grid-item click-bounce" v-for="(tool, idx) in formattedPageSetting.menus.list"
						:key="idx" @click="$T.go(tool.url)">
						<view class="tool-icon-bg" :style="{ background: getMenuColor(idx) }">
							<image :src="tool.icon" class="tool-icon-img"></image>
						</view>
						<text class="grid-text">{{ tool.name }}</text>
					</view>
				</view>
				<!-- List模式 -->
				<view class="list-layout" v-else>
					<view class="list-item click-bounce" v-for="(item, index) in formattedPageSetting.menus.list"
						:key="index" @click="$T.go(item.url)">
						<view class="left">
							<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="icon"></image>
							<text>{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
						</view>
						<tui-icon name="arrowright" size="16" color="#ccc"></tui-icon>
					</view>
				</view>
			</view>

			<!-- E. 常用工具 (菜单2) -->
			<view class="section-card"
				v-if="formattedPageSetting && formattedPageSetting.menus2 && formattedPageSetting.menus2.is_show == 1">
				<view class="card-header">
					<text class="title">{{ formattedPageSetting.menus2.name }}</text>
				</view>
				<view class="grid-box tools-grid" v-if="formattedPageSetting.menus2.type == 0">
					<view class="grid-item click-bounce" v-for="(tool, idx) in formattedPageSetting.menus2.list"
						:key="idx" @click="$T.go(tool.url)">
						<view class="tool-icon-bg" :style="{ background: '#f4f5f7' }">
							<image :src="tool.icon" class="tool-icon-img"></image>
						</view>
						<text class="grid-text">{{ tool.name }}</text>
					</view>
				</view>
				<view class="list-layout" v-else>
					<view class="list-item click-bounce" v-for="(item, index) in formattedPageSetting.menus2.list"
						:key="index" @click="$T.go(item.url)">
						<view class="left">
							<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="icon"></image>
							<text>{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
						</view>
						<tui-icon name="arrowright" size="16" color="#ccc"></tui-icon>
					</view>
				</view>
			</view>

			<!-- F. 推荐好物 -->
			<view class="recommend-section" v-if="list.length">
				<view class="section-title-simple">
					<text>推荐好物</text>
				</view>
				<view class="product-list-simple">
					<uni-grid :column="2" :square="false" :showBorder="false" :highlight="false">
						<block v-for="(item, index) in list" :key="index">
							<uni-grid-item>
								<!-- 动态Padding -->
								<view class="goods-item-wrap"
									:style="{ padding: index % 2 == 0 ? '0 10rpx 20rpx 0rpx' : '0 0rpx 20rpx 10rpx' }">
									<tui-goods-item :goods="item"></tui-goods-item>
								</view>
							</uni-grid-item>
						</block>
					</uni-grid>
				</view>
				<tui-loadmore v-if="loadding" :index="3" type="primary"></tui-loadmore>
			</view>

			<!-- 底部版权 -->
			<view class="footer"
				v-if="is_no_more && formattedPageSetting && formattedPageSetting.copyright && formattedPageSetting.copyright.is_show == 1">
				<text>{{ formattedPageSetting.copyright.text }}</text>
			</view>

			<view style="height: 40rpx;"></view>

			<!-- 登录弹窗 (复用) -->
			<tui-user-avatar :visible="showProfileModal" :userData="userInfo" @save="handleSaveSuccess"
				@close="showProfileModal = false" />

		</view>
	</view>
</template>

<script>
	import TuiUserAvatar from '@/components/thorui/tui-user-avatar/tui-user-avatar.vue'
import goodsRecommend from '@/mixins/goods-recommend.js';

export default {
	mixins: [goodsRecommend],
		components: { TuiUserAvatar },
	name: "user-minimalist",
	props: {
		userInfo: {
			type: Object,
			default: () => ({})
		},
		pageSetting: {
			type: Object,
			default: () => null
		},
		isLogin: {
			type: Boolean,
			default: false
		}
	},
	data() {
		return {
			top: 0,
			navRightGap: 0, // 距离右边的距离，避免遮挡胶囊

			// 莫兰迪色系背景池 (用于菜单图标)
			menuColors: [
				'rgba(92, 124, 250, 0.1)',
				'rgba(255, 146, 43, 0.1)',
				'rgba(32, 201, 151, 0.1)',
				'rgba(250, 82, 82, 0.1)',
				'rgba(250, 176, 5, 0.1)',
				'rgba(134, 142, 150, 0.1)'
			],

			showProfileModal: false,
		};
	},
	computed: {
		formattedPageSetting() {
			let data = this.pageSetting ? JSON.parse(JSON.stringify(this.pageSetting)) : {};
			if (!data.copyright) {
				data.copyright = {
					is_show: 1,
					text: 'Simple & Professional'
				};
			}
			// 确保 financial 对象存在
			if (!data.finance) data.finance = {
				balance: {},
				integral: {},
				coupon: {},
				price: {}
			};
			return data;
		},
		hasHeaderStats() {
			if (!this.formattedPageSetting || !this.formattedPageSetting.header) return false;
			const h = this.formattedPageSetting.header;
			return (h.goods && h.goods.is_show == 1) ||
				(h.footmark && h.footmark.is_show == 1) ||
				(h.order && h.order.is_show == 1);
		}
	},
	created() {
		this.initNav();
		if (this.isLogin && this.userInfo.is_avatar != 1) {
			this.checkAvatar();
		}
	},
	watch: {
		isLogin(val) {
			if (val && this.userInfo.is_avatar != 1) {
				this.checkAvatar();
			}
		}
	},
	methods: {
			goAsset(key, defaultUrl) {
				const f = this.formattedPageSetting && this.formattedPageSetting.finance;
				const cfg = f && f[key];
				this.$T.go(cfg && cfg.url ? cfg.url : defaultUrl);
			},
		refreshLocalData() {
			this.page = 1;
			this.is_no_more = false;
			this.pullUpOn = true;
			this.getRecommend(true);
		},
		handleReachBottom() {
			if (!this.pullUpOn || this.loadding || this.is_no_more) return;
			this.getRecommend();
		},

		initNav() {
			const sys = uni.getSystemInfoSync();
			this.top = sys.statusBarHeight; // 状态栏高度

			// #ifdef MP-WEIXIN
			let menuButton = uni.getMenuButtonBoundingClientRect();
			// 计算胶囊左侧的距离，作为右侧padding
			// 屏幕宽度 - 胶囊左边界 = 右侧需要留出的空间
			// 再多加15px作为间距
			if (menuButton) {
				this.navRightGap = (sys.screenWidth - menuButton.left) + 15;
			}
			// #endif

			// #ifndef MP-WEIXIN
			this.navRightGap = 15;
			// #endif
		},
		getMenuColor(index) {
			return this.menuColors[index % this.menuColors.length];
		},
		copyId(id) {
			uni.setClipboardData({
				data: String(id),
				success: () => {
					uni.showToast({
						title: 'ID已复制',
						icon: 'none'
					})
				}
			});
		},
		goToLogin() {
			this.$T.go('/others/login/login')
		},
		handleSaveSuccess() {
			uni.redirectTo({
				url: "/others/user/user"
			})
		},
		checkAvatar() {
			this.$request({
				url: "/api/user/check-avatar"
			}).then(res => {
				if (res.code == 0 && res.data.is_need_bind == 1) {
					this.showProfileModal = true;
				}
			})
		}
	}
};
</script>

<style lang="scss" scoped>
/* 变量定义 */
$page-bg: #F5F6F8;
$text-main: #2c3e50;
$card-white: #ffffff;

.page-container {
	min-height: 100vh;
	background-color: $page-bg;
	position: relative;
	/* Increase bottom padding to prevent obstruction by the bottom navigation bar, while adapting to the bottom safety area */
	padding-bottom: calc(120rpx + env(safe-area-inset-bottom));
}

/* 1. 顶部导航 (Fixed) */
.minimal-header {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	z-index: 999;
	background-color: $page-bg;
	/* 必须有背景色，否则滚动时穿透 */
	box-sizing: border-box;

	.header-content {
		height: 44px;
		/* 标准导航栏高度 */
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding-left: 30rpx;
		/* 左侧边距 */
		/* paddingRight 由 style 动态控制 */
	}

	.page-title {
		font-size: 36rpx;
		font-weight: 800;
		color: $text-main;
		letter-spacing: -1rpx;
	}

	.header-actions {
		display: flex;
		align-items: center;

		.icon-btn {
			width: 64rpx;
			height: 64rpx;
			background: #fff;
			border-radius: 50%;
			display: flex;
			justify-content: center;
			align-items: center;
			box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.05);
			position: relative;

			.dot {
				position: absolute;
				top: 14rpx;
				right: 14rpx;
				width: 10rpx;
				height: 10rpx;
				background: #ff4d4f;
				border-radius: 50%;
			}
		}
	}
}

/* 占位，防止内容被Fixed Header遮挡 */
/* 
   实际使用 padding-top 在 .content-wrapper 实现 
   所以不需要专门的 placeholder div 
*/

/* 内容区域 */
.content-wrapper {
	padding: 0 30rpx;
	box-sizing: border-box;
}

/* 2. 立体感用户卡片 */
.user-card-wrap {
	position: relative;
	margin-bottom: 40rpx;
	margin-top: 0;
}

.user-card-3d {
	position: relative;
	z-index: 2;
	border-radius: 30rpx;
	padding: 40rpx 30rpx 30rpx;
	background: linear-gradient(145deg, #ffffff 0%, #f9fafb 100%);
	box-shadow: 0 10rpx 30rpx rgba(0, 0, 0, 0.05), 0 2rpx 8rpx rgba(0, 0, 0, 0.02);
	border: 1rpx solid rgba(255, 255, 255, 1);
	overflow: hidden;

	.level-tag {
		position: absolute;
		top: 0;
		right: 0;
		z-index: 5;
		background: #333;
		padding: 8rpx 24rpx 8rpx 20rpx;
		border-bottom-left-radius: 24rpx;
		border-top-right-radius: 28rpx;

		text {
			font-size: 22rpx;
			color: #FFD700;
			font-weight: 700;
			letter-spacing: 2rpx;
		}
	}

	.user-main {
		display: flex;
		align-items: center;
		margin-bottom: 40rpx;

		.avatar-group {
			position: relative;
			margin-right: 24rpx;

			.avatar-img {
				border-radius: 50%;
				border: 4rpx solid #fff;
				box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.1);
			}

			.avatar-placeholder {
				width: 128rpx;
				height: 128rpx;
				border-radius: 50%;
				background: #eee;
				display: flex;
				align-items: center;
				justify-content: center;
				border: 4rpx solid #fff;
				box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.1);
			}

		}

		.info-group {
			flex: 1;
			min-width: 0;

			.nickname-row .nickname {
				font-size: 40rpx;
				font-weight: 700;
				color: $text-main;
				margin-bottom: 8rpx;
				/* 昵称截断 */
				display: block;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
				max-width: 320rpx;
			}

			.uid-row {
				display: inline-flex;
				align-items: center;
				background: #f0f2f5;
				padding: 4rpx 12rpx;
				border-radius: 12rpx;

				text {
					font-size: 22rpx;
					color: #888;
				}
			}
		}

		.check-btn {
			background: #333;
			color: #fff;
			padding: 10rpx 28rpx;
			border-radius: 30rpx;
			font-size: 24rpx;
			font-weight: 600;
			box-shadow: 0 4rpx 10rpx rgba(0, 0, 0, 0.15);
		}
	}

	/* 分割线 */
	&::after {
		content: '';
		display: block;
		width: 100%;
		height: 1rpx;
		background: #f0f0f0;
		margin-bottom: 30rpx;
	}

	.header-stats-row {
		display: flex;
		justify-content: space-around;

		.h-stat-item {
			text-align: center;

			.num {
				font-size: 36rpx;
				font-weight: 800;
				color: $text-main;
				margin-bottom: 6rpx;
				font-family: 'DIN Alternate', sans-serif;
			}

			.lbl {
				font-size: 22rpx;
				color: #999;
			}
		}
	}
}

.card-shadow-layer {
	position: absolute;
	bottom: -10rpx;
	left: 40rpx;
	right: 40rpx;
	height: 40rpx;
	background: rgba(0, 0, 0, 0.03);
	filter: blur(10rpx);
	border-radius: 50%;
	z-index: 1;
}

/* 3. 通用 Section 卡片 */
.section-card {
	background: $card-white;
	border-radius: 24rpx;
	margin-bottom: 30rpx;
	padding: 0 24rpx;
	box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.02);
	border: 1rpx solid #fff;

	.card-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 32rpx 0;
		border-bottom: 1rpx solid #fafafa;

		.title {
			font-size: 30rpx;
			font-weight: 600;
			color: $text-main;
		}

		.more {
			display: flex;
			align-items: center;
			font-size: 24rpx;
			color: #bbb;

			text {
				margin-right: 4rpx;
			}
		}
	}
}

/* 资产栏 (Finance) - 修复布局 */
.finance-row {
	display: flex;
	justify-content: space-between;
	/* 确保4个平分 */
	padding: 30rpx 0;

	.f-item {
		display: flex;
		flex-direction: column;
		align-items: center;
		flex: 1;

		.amount {
			font-size: 36rpx;
			font-weight: 800;
			color: $text-main;
			margin-bottom: 8rpx;
			font-family: 'DIN Alternate', sans-serif;
		}

		.gold-text {
			color: #FF9F43;
		}

		.label {
			font-size: 22rpx;
			color: #999;
		}
	}
}

/* 订单 (Order) - 修复布局 */
.grid-box {
	display: flex;
	flex-wrap: wrap;
	padding: 30rpx 0;
	justify-content: flex-start;
	/* 左对齐 */

	.grid-item {
		width: 20%;
		/* 强制5列 */
		display: flex;
		flex-direction: column;
		align-items: center;
		margin-bottom: 10rpx;

		.icon-wrap {
			position: relative;
			margin-bottom: 14rpx;

			.main-icon {
				width: 52rpx;
				height: 52rpx;
			}

			.dot-badge {
				position: absolute;
				top: 0;
				right: 0;
				background: #ff4d4f;
				color: #fff;
				font-size: 18rpx;
				min-width: 28rpx;
				height: 28rpx;
				line-height: 28rpx;
				text-align: center;
				border-radius: 28rpx;
				border: 2rpx solid #fff;
			}
		}

		.grid-text {
			font-size: 24rpx;
			color: #666;
		}
	}
}

/* 工具栏 - 4列 */
.tools-grid {
	display: flex;
	flex-wrap: wrap;
	padding: 30rpx 0;

	.grid-item {
		width: 25%;
		display: flex;
		flex-direction: column;
		align-items: center;
		margin-bottom: 24rpx;

		.tool-icon-bg {
			width: 88rpx;
			height: 88rpx;
			border-radius: 24rpx;
			display: flex;
			justify-content: center;
			align-items: center;
			margin-bottom: 12rpx;

			.tool-icon-img {
				width: 44rpx;
				height: 44rpx;
			}
		}

		.grid-text {
			font-size: 24rpx;
			color: $text-main;
		}
	}
}

/* 列表菜单 */
.list-layout {
	.list-item {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 24rpx 0;
		border-bottom: 1rpx solid #f9f9f9;

		&:last-child {
			border-bottom: none;
			padding-bottom: 0;
		}

		&:first-child {
			padding-top: 0;
		}

		.left {
			display: flex;
			align-items: center;
			font-size: 28rpx;
			color: $text-main;

			.icon {
				width: 40rpx;
				height: 40rpx;
				margin-right: 20rpx;
			}
		}
	}
}

/* 推荐好物 */
.recommend-section {
	margin-top: 40rpx;

	.section-title-simple {
		text-align: left;
		margin-bottom: 24rpx;
		padding-left: 10rpx;
		font-size: 32rpx;
		font-weight: 700;
		color: #333;
		position: relative;

		&::before {
			content: '';
			position: absolute;
			left: -10rpx;
			top: 50%;
			transform: translateY(-50%);
			width: 8rpx;
			height: 32rpx;
			background: #333;
			border-radius: 4rpx;
		}
	}

	.goods-item-wrap {
		box-sizing: border-box;
		height: 100%;
	}

	::v-deep .uni-grid-item {
		padding: 0 !important;
	}
}

/* 底部 */
.footer {
	text-align: center;
	padding: 20rpx 0 60rpx;

	text {
		font-size: 22rpx;
		color: #dcdcdc;
		letter-spacing: 2rpx;
	}
}

/* 动效 */
.click-bounce {
	transition: transform 0.1s;
}

.click-bounce:active {
	transform: scale(0.95);
	opacity: 0.8;
}
</style>