<template>
	<view class="silver-container" :style="colorVars">

		<view class="aurora-bg">
			<view class="orb orb-1"></view>
			<view class="orb orb-2"></view>
		</view>

		<!-- 顶部导航已提取到父组件 user.vue -->

		<view class="content-wrapper" :style="{ paddingTop: (statusBarHeight + 44) + 'px' }">

			<view class="glass-panel user-card" style="position: relative; overflow: hidden;"
				@click="!isLogin ? goToLogin() : ''">
				<view class="level-tag" v-if="isLogin && userInfo.level_name">
					<text>{{ userInfo.level_name }}</text>
				</view>
				<view class="card-inner">
					<view class="avatar-group">
						<image v-if="isLogin" :src="userInfo.avatar_url" mode="aspectFill"
							class="avatar-img" @click="$T.go('/others/setting/setting')"
							style="width:130rpx;height:130rpx;border-radius:50%;" />
						<view v-else class="avatar-placeholder">
							<tui-icon name="people" size="40" color="#999"></tui-icon>
						</view>
					</view>

					<view class="info-group">
						<view class="name-row">
							<text class="nickname">{{ isLogin ? userInfo.nickname : '登录 / 注册' }}</text>
						</view>
						<view class="uid-row" v-if="isLogin" @click.stop="copyId(userInfo.id)">
							<text class="uid-text">ID: {{ userInfo.id }}</text>
							<tui-icon name="copy" size="10" color="#8E8E93" style="margin-left: 8rpx;"></tui-icon>
							<text class="mobile-inline" v-if="isLogin && formattedPageSetting.header && formattedPageSetting.header.show_mobile == 1 && userInfo.mobile" style="margin-left:10rpx;opacity:0.8;font-size:22rpx;">· {{ userInfo.mobile }}</text>
						</view>
						<view class="uid-row" v-else>
							<text class="uid-text">Welcome Back</text>
						</view>
					</view>

					<view class="checkin-btn click-scale" style="display: none;" v-if="isLogin"
						@click.stop="$T.go('/plugins/check_in/index')">
						<text>签到</text>
					</view>
				</view>

				<view class="header-stats-row" v-if="hasHeaderStats">
					<view class="h-col click-scale" v-if="formattedPageSetting.header.goods.is_show == 1"
						@click.stop="$T.go('/others/collect/collect')">
						<text class="n">{{ isLogin ? formattedPageSetting.header.goods.value : 0 }}</text>
						<text class="t">{{ formattedPageSetting.header.goods.name }}</text>
					</view>
					<view class="h-col click-scale" v-if="formattedPageSetting.header.footmark.is_show == 1"
						@click.stop="$T.go('/others/footmark/footmark')">
						<text class="n">{{ isLogin ? formattedPageSetting.header.footmark.value : 0 }}</text>
						<text class="t">{{ formattedPageSetting.header.footmark.name }}</text>
					</view>
					<view class="h-col click-scale" v-if="formattedPageSetting.header.order.is_show == 1"
						@click.stop="$T.go('/others/order/order')">
						<text class="n">{{ isLogin ? formattedPageSetting.header.order.value : 0 }}</text>
						<text class="t">{{ formattedPageSetting.header.order.name }}</text>
					</view>
				</view>

				<view class="silver-stats"
					v-if="formattedPageSetting && formattedPageSetting.finance && formattedPageSetting.finance.is_show == 1">

					<view class="stat-item click-scale" v-if="formattedPageSetting.finance.balance.is_show == 1"
						@click="isLogin && goAsset('balance', '/plugins2/withdraw/balance')">
						<text class="num" :style="formattedPageSetting.finance.balance.color ? 'color:' + formattedPageSetting.finance.balance.color : ''">{{ isLogin ? userInfo.money : '0.00' }}</text>
						<text class="label">{{ formattedPageSetting.finance.balance.name }}</text>
					</view>

					<view class="stat-item click-scale"
						v-if="formattedPageSetting.finance.price && formattedPageSetting.finance.price.is_show == 1"
						@click="isLogin && goAsset('price', '/plugins2/withdraw/price')">
						<text class="num silver-highlight" :style="formattedPageSetting.finance.price.color ? 'color:' + formattedPageSetting.finance.price.color : ''">{{ isLogin ? userInfo.price : '0.00' }}</text>
						<text class="label">{{ formattedPageSetting.finance.price.name }}</text>
					</view>

					<view class="stat-item click-scale" v-if="formattedPageSetting.finance.integral.is_show == 1"
						@click="isLogin && goAsset('integral', '/plugins2/withdraw/integral')">
						<text class="num" :style="formattedPageSetting.finance.integral.color ? 'color:' + formattedPageSetting.finance.integral.color : ''">{{ isLogin ? userInfo.integral : 0 }}</text>
						<text class="label">{{ formattedPageSetting.finance.integral.name }}</text>
					</view>

					<view class="stat-item click-scale" v-if="formattedPageSetting.finance.coupon.is_show == 1"
						@tap="goAsset('coupon', '/others/coupon/coupon?status=1')">
						<text class="num" :style="formattedPageSetting.finance.coupon.color ? 'color:' + formattedPageSetting.finance.coupon.color : ''">{{ isLogin ? userInfo.coupon : 0 }}</text>
						<text class="label">{{ formattedPageSetting.finance.coupon.name }}</text>
					</view>
				</view>
			</view>

			<view class="glass-panel section-card"
				v-if="formattedPageSetting && formattedPageSetting.order && formattedPageSetting.order.is_show != 0">
				<view class="card-header" @click="$T.go('/others/order/order?status=-1')">
					<text class="title">{{ formattedPageSetting.order.title }}</text>
					<view class="more">
						<text>{{ formattedPageSetting.order.sub_title }}</text>
						<tui-icon name="arrowright" size="12" color="#8E8E93"></tui-icon>
					</view>
				</view>

				<view class="order-grid-box">
					<view class="order-item click-scale" v-if="formattedPageSetting.order.status_0.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_0.url)">
						<view class="icon-box">
							<image :src="formattedPageSetting.order.status_0.icon" class="main-icon"></image>
							<view class="dot-badge" v-if="isLogin && formattedPageSetting.order.status_0.num > 0">
							</view>
						</view>
						<text class="grid-label">{{ formattedPageSetting.order.status_0.name }}</text>
					</view>

					<view class="order-item click-scale" v-if="formattedPageSetting.order.status_1.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_1.url)">
						<view class="icon-box">
							<image :src="formattedPageSetting.order.status_1.icon" class="main-icon"></image>
							<view class="dot-badge" v-if="isLogin && formattedPageSetting.order.status_1.num > 0">
							</view>
						</view>
						<text class="grid-label">{{ formattedPageSetting.order.status_1.name }}</text>
					</view>

					<view class="order-item click-scale" v-if="formattedPageSetting.order.status_2.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_2.url)">
						<view class="icon-box">
							<image :src="formattedPageSetting.order.status_2.icon" class="main-icon"></image>
							<view class="dot-badge" v-if="isLogin && formattedPageSetting.order.status_2.num > 0">
							</view>
						</view>
						<text class="grid-label">{{ formattedPageSetting.order.status_2.name }}</text>
					</view>

					<view class="order-item click-scale" v-if="formattedPageSetting.order.status_3.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_3.url)">
						<view class="icon-box">
							<image :src="formattedPageSetting.order.status_3.icon" class="main-icon"></image>
							<view class="dot-badge" v-if="isLogin && formattedPageSetting.order.status_3.num > 0">
							</view>
						</view>
						<text class="grid-label">{{ formattedPageSetting.order.status_3.name }}</text>
					</view>

					<view class="order-item click-scale" v-if="formattedPageSetting.order.status_4.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_4.url)">
						<view class="icon-box">
							<image :src="formattedPageSetting.order.status_4.icon" class="main-icon"></image>
							<view class="dot-badge" v-if="isLogin && formattedPageSetting.order.status_4.num > 0">
							</view>
						</view>
						<text class="grid-label">{{ formattedPageSetting.order.status_4.name }}</text>
					</view>
				</view>
			</view>

			<view class="glass-panel section-card"
				v-if="formattedPageSetting && formattedPageSetting.menus && formattedPageSetting.menus.is_show == 1">
				<view class="card-header">
					<text class="title">{{ formattedPageSetting.menus.name }}</text>
				</view>

				<view class="tools-grid" v-if="formattedPageSetting.menus.type == 0">
					<view class="grid-item click-scale" v-for="(item, index) in formattedPageSetting.menus.list"
						:key="index" @click="$T.go(item.url)">
						<view class="tool-icon-circle" :style="{ background: '#F2F2F7' }">
							<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="tool-icon-img"></image>
						</view>
						<text class="grid-label">{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
					</view>
				</view>
				<view class="menu-list" v-else>
					<view class="list-item click-scale" v-for="(item, index) in formattedPageSetting.menus.list"
						:key="index" @click="$T.go(item.url)">
						<view class="left">
							<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="list-icon"></image>
							<text>{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
						</view>
						<tui-icon name="arrowright" size="14" color="#ccc"></tui-icon>
					</view>
				</view>
			</view>

			<view class="glass-panel section-card"
				v-if="formattedPageSetting && formattedPageSetting.menus2 && formattedPageSetting.menus2.is_show == 1">
				<view class="card-header">
					<text class="title">{{ formattedPageSetting.menus2.name }}</text>
				</view>
				<view class="tools-grid" v-if="formattedPageSetting.menus2.type == 0">
					<view class="grid-item click-scale" v-for="(item, index) in formattedPageSetting.menus2.list"
						:key="index" @click="$T.go(item.url)">
						<view class="tool-icon-circle" :style="{ background: '#F2F2F7' }">
							<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="tool-icon-img"></image>
						</view>
						<text class="grid-label">{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
					</view>
				</view>
				<view class="menu-list" v-else>
					<view class="list-item click-scale" v-for="(item, index) in formattedPageSetting.menus2.list"
						:key="index" @click="$T.go(item.url)">
						<view class="left">
							<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="list-icon"></image>
							<text>{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
						</view>
						<tui-icon name="arrowright" size="14" color="#ccc"></tui-icon>
					</view>
				</view>
			</view>

			<view class="recommend-section" v-if="list.length">
				<view class="silver-title">
					<text>推荐好物</text>
				</view>

				<view class="silver-goods-list">
					<uni-grid :column="2" :square="false" :showBorder="false" :highlight="false">
						<block v-for="(item, index) in list" :key="index">
							<uni-grid-item>
								<view class="goods-item-wrap"
									:style="{ padding: index % 2 == 0 ? '0 12rpx 24rpx 0rpx' : '0 0rpx 24rpx 12rpx' }">
									<view class="silver-goods-card">
										<tui-goods-item :goods="item"></tui-goods-item>
									</view>
								</view>
							</uni-grid-item>
						</block>
					</uni-grid>
				</view>
				<tui-loadmore v-if="loadding" :index="3" type="primary" color="#D1D1D6"></tui-loadmore>
			</view>

			<view class="footer"
				v-if="is_no_more && formattedPageSetting && formattedPageSetting.copyright && formattedPageSetting.copyright.is_show == 1">
				<text class="brand">{{ formattedPageSetting.copyright.text }}</text>
			</view>

			<view style="height: 50rpx;"></view>
		</view>

		<tui-user-avatar :visible="showProfileModal" :userData="userInfo" @save="handleSaveSuccess"
			@close="showProfileModal = false" />

	</view>
</template>

<script>
// [核心] 引入 Vuex mapState
import {
	mapState
} from 'vuex';

	import TuiUserAvatar from '@/components/thorui/tui-user-avatar/tui-user-avatar.vue'
import goodsRecommend from '@/mixins/goods-recommend.js';

export default {
	mixins: [goodsRecommend],
		components: { TuiUserAvatar },
	name: "user-silver",
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
			statusBarHeight: 20,
			navBarHeight: 44,
			navRightGap: 15,
			showProfileModal: false,
		};
	},
	computed: {
		// MP 端 :style 注 CSS 变量必须用字符串(对象会丢键,见全局 themeStyle 约定);silver 还要带 --theme-color(模板用了 var(--theme-color))
		colorVars() {
			const c = (this.pageSetting || {}).colors || {};
			let s = '--theme-color:' + this.themeColor + ';';
			if (c.header_from) s += '--user-color-from:' + c.header_from + ';';
			if (c.header_to) s += '--user-color-to:' + c.header_to + ';';
			return s;
		},
		// [核心] 获取主题色
		...mapState('mall', {
			themeColor: state => state.themeColor || '#5E5CE6' // 默认紫色，如果没有设置
		}),
		// [核心] 定义 CSS 变量样式对象
		themeStyle() {
			return {
				'--theme-color': this.themeColor
			};
		},
		formattedPageSetting() {
			let data = this.pageSetting ? JSON.parse(JSON.stringify(this.pageSetting)) : {};
			if (!data.copyright) {
				data.copyright = {
					is_show: 1,
					text: 'Silver UI Design'
				};
			}
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
			return (h.goods && h.goods.is_show == 1) || (h.footmark && h.footmark.is_show == 1);
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
			this.statusBarHeight = sys.statusBarHeight || 20;
			// #ifdef MP-WEIXIN
			try {
				let menuButton = uni.getMenuButtonBoundingClientRect();
				if (menuButton) {
					this.navRightGap = (sys.screenWidth - menuButton.left) + 10;
					let gap = menuButton.top - this.statusBarHeight;
					this.navBarHeight = menuButton.height + (gap * 2);
				}
			} catch (e) {
				this.navRightGap = 15;
				this.navBarHeight = 44;
			}
			// #endif
			// #ifndef MP-WEIXIN
			this.navRightGap = 15;
			this.navBarHeight = 44;
			// #endif
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
				if (res.code == 0 && res.data.is_need_bind == 1) this.showProfileModal = true;
			})
		}
	}
}
</script>

<style lang="scss" scoped>
/* --- 全局变量 --- */
$bg-color: #F4F5F9;
$text-main: #1D1D1F;
$text-sub: #86868B;
$glass-white: rgba(255, 255, 255, 0.85);

.silver-container {
	min-height: 100vh;
	background-color: $bg-color;
	position: relative;
	overflow-x: hidden;
	font-family: -apple-system, BlinkMacSystemFont, "PingFang SC", "Helvetica Neue", sans-serif;
	/* Increase bottom padding to prevent obstruction by the bottom navigation bar, while adapting to the bottom safety area */
	padding-bottom: calc(120rpx + env(safe-area-inset-bottom));
}

/* 1. 银光氛围背景 —— 全视口覆盖 */
.aurora-bg {
	position: fixed;
	/* 原来 height: 800rpx 只覆盖视口顶部一段，下方 viewport 直接露出 bg-color 灰底；
	   改成 100vh 让银光氛围铺满整个视口，orbs 仍然集中在顶部区域作为 hero 装饰 */
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	width: 100%;
	height: 100vh;
	z-index: 0;
	pointer-events: none;
	background: $bg-color;

	.orb {
		position: absolute;
		border-radius: 50%;
		filter: blur(80px);
		opacity: 0.6;
	}

	.orb-1 {
		width: 400rpx;
		height: 400rpx;
		background: var(--user-color-from, #E0EAFF);
		top: -100rpx;
		left: -100rpx;
	}

	.orb-2 {
		width: 500rpx;
		height: 500rpx;
		background: var(--user-color-to, #F3E5F5);
		top: 50rpx;
		right: -150rpx;
	}
}

.nav-bar {
	position: fixed;
	width: 100%;
	top: 0;
	left: 0;
	z-index: 99;
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding-left: 32rpx;
	box-sizing: border-box;

	.nav-right {
		display: flex;
		align-items: center;

		.icon-btn {
			width: 72rpx;
			height: 72rpx;
			background: rgba(255, 255, 255, 0.6);
			border-radius: 50%;
			display: flex;
			justify-content: center;
			align-items: center;
			border: 1rpx solid rgba(255, 255, 255, 0.8);
			position: relative;

			.dot {
				position: absolute;
				top: 16rpx;
				right: 16rpx;
				width: 12rpx;
				height: 12rpx;
				background: #FF3B30;
				border-radius: 50%;
			}
		}
	}
}

.content-wrapper {
	position: relative;
	z-index: 10;
	padding: 0 32rpx;
}

.glass-panel {
	background: $glass-white;
	backdrop-filter: blur(20px);
	-webkit-backdrop-filter: blur(20px);
	border-radius: 32rpx;
	border: 1rpx solid rgba(255, 255, 255, 1);
	box-shadow: 0 10rpx 40rpx rgba(0, 0, 0, 0.04), 0 2rpx 10rpx rgba(0, 0, 0, 0.02);
	margin-bottom: 32rpx;
}

.user-card {
	padding: 40rpx 30rpx 10rpx;

	.level-tag {
		position: absolute;
		top: 0;
		right: 0;
		z-index: 5;
		background: linear-gradient(135deg, #C0C0C0, #E8E8E8);
		padding: 8rpx 24rpx 8rpx 20rpx;
		border-bottom-left-radius: 24rpx;
		border-top-right-radius: 30rpx;

		text {
			font-size: 22rpx;
			color: #333;
			font-weight: 700;
			letter-spacing: 2rpx;
			text-transform: uppercase;
		}
	}

	.card-inner {
		display: flex;
		align-items: center;
		margin-bottom: 40rpx;

		.avatar-group {
			position: relative;
			margin-right: 30rpx;

			.avatar-img,
			.avatar-placeholder {
				width: 130rpx;
				height: 130rpx;
				border-radius: 50%;
				box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.08);
			}

			.avatar-placeholder {
				display: flex;
				align-items: center;
				justify-content: center;
				background: #f2f2f7;
			}

		}

		.info-group {
			flex: 1;
			min-width: 0;

			.name-row {
				display: flex;
				align-items: center;
				margin-bottom: 8rpx;

				.nickname {
					font-size: 40rpx;
					font-weight: 700;
					color: $text-main;
					letter-spacing: 0.5rpx;
					display: block;
					max-width: 320rpx;
					white-space: nowrap;
					overflow: hidden;
					text-overflow: ellipsis;
				}
			}

			.uid-row {
				display: inline-flex;
				align-items: center;
				background: rgba(0, 0, 0, 0.03);
				padding: 6rpx 16rpx;
				border-radius: 12rpx;

				.uid-text {
					font-size: 22rpx;
					color: $text-sub;
					font-family: monospace;
				}
			}
		}

		.checkin-btn {
			padding: 10rpx 24rpx;
			background: rgba(0, 0, 0, 0.05);
			border-radius: 30rpx;
			font-size: 24rpx;
			color: #333;
		}
	}

	.header-stats-row {
		display: flex;
		justify-content: space-around;
		padding-bottom: 24rpx;
		border-bottom: 1rpx dashed rgba(0, 0, 0, 0.05);

		.h-col {
			text-align: center;

			.n {
				font-size: 32rpx;
				font-weight: bold;
				color: $text-main;
				margin-right: 8rpx;
			}

			.t {
				font-size: 24rpx;
				color: $text-sub;
			}
		}
	}

	.silver-stats {
		display: flex;
		justify-content: space-around;
		padding-top: 24rpx;
		padding-bottom: 20rpx;

		.stat-item {
			display: flex;
			flex-direction: column;
			align-items: center;
			flex: 1;

			.num {
				font-size: 36rpx;
				font-weight: 600;
				color: $text-main;
				margin-bottom: 4rpx;
				font-family: 'DIN', sans-serif;
			}

			/* [核心修改] 使用 CSS 变量控制颜色 */
			.silver-highlight {
				color: var(--theme-color) !important;
			}

			.label {
				font-size: 22rpx;
				color: $text-sub;
			}
		}
	}
}

.card-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 30rpx 30rpx 20rpx;

	.title {
		font-size: 30rpx;
		font-weight: 600;
		color: $text-main;
	}

	.more {
		display: flex;
		align-items: center;

		text {
			font-size: 24rpx;
			color: $text-sub;
			margin-right: 6rpx;
		}
	}
}

.order-grid-box {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-start;
	padding: 20rpx 0 40rpx;

	.order-item {
		display: flex;
		flex-direction: column;
		align-items: center;
		width: 20%;
		margin-bottom: 10rpx;

		.icon-box {
			position: relative;
			margin-bottom: 16rpx;
			width: 60rpx;
			height: 60rpx;
			display: flex;
			justify-content: center;
			align-items: center;

			.main-icon {
				width: 52rpx;
				height: 52rpx;
			}

			.dot-badge {
				position: absolute;
				top: 0;
				right: 0;
				width: 12rpx;
				height: 12rpx;
				background: #FF3B30;
				border-radius: 50%;
				border: 2rpx solid #fff;
			}
		}

		.grid-label {
			font-size: 24rpx;
			color: #555;
		}
	}
}

.tools-grid {
	display: flex;
	flex-wrap: wrap;
	padding: 10rpx 0 30rpx;
	justify-content: flex-start;

	.grid-item {
		width: 25%;
		display: flex;
		flex-direction: column;
		align-items: center;
		margin-bottom: 30rpx;

		.tool-icon-circle {
			width: 88rpx;
			height: 88rpx;
			border-radius: 30rpx;
			display: flex;
			justify-content: center;
			align-items: center;
			margin-bottom: 12rpx;

			.tool-icon-img {
				width: 44rpx;
				height: 44rpx;
			}
		}

		.grid-label {
			font-size: 24rpx;
			color: #555;
		}
	}
}

.menu-list {
	.list-item {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 24rpx 30rpx;
		border-bottom: 1px solid #f5f5f5;

		&:last-child {
			border-bottom: none;
		}

		.left {
			display: flex;
			align-items: center;
			font-size: 28rpx;
			color: #333;

			.list-icon {
				width: 40rpx;
				height: 40rpx;
				margin-right: 20rpx;
			}
		}
	}
}

.recommend-section {
	margin-top: 40rpx;

	.silver-title {
		text-align: center;
		margin-bottom: 30rpx;

		text {
			font-size: 22rpx;
			color: $text-sub;
			letter-spacing: 4rpx;
			font-weight: 600;
			text-transform: uppercase;
		}
	}

	.goods-item-wrap {
		box-sizing: border-box;
		height: 100%;
	}

	.silver-goods-card {
		background: #fff;
		border-radius: 20rpx;
		overflow: hidden;
		box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.03);
	}

	::v-deep .uni-grid-item {
		padding: 0 !important;
	}
}

.footer {
	text-align: center;
	padding: 20rpx 0 60rpx;

	.brand {
		font-size: 20rpx;
		color: #D1D1D6;
		letter-spacing: 4rpx;
		text-transform: uppercase;
	}
}

.click-scale {
	transition: transform 0.1s;
}

.click-scale:active {
	transform: scale(0.95);
	opacity: 0.8;
}
</style>