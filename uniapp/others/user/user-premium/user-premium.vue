<template>
	<view class="premium-container" :style="colorVars">

		<!-- 1. 背景层：黑金流光 + 动态光斑 -->
		<view class="premium-bg">
			<view class="noise-texture"></view>
			<!-- 两个漂浮的金色光球 -->
			<view class="gold-orb orb-1"></view>
			<view class="gold-orb orb-2"></view>
		</view>

		<!-- 顶部悬浮按钮区已提取到父组件 user.vue -->

		<!-- 3. 内容滚动区域 -->
		<view class="content-scroll" :style="{ paddingTop: (top + 44) + 'px' }">

			<!-- A. 尊享用户卡片 (流光边框) -->
			<view class="user-card-premium" @click="!isLogin ? goToLogin() : ''">
				<view class="card-glass-layer">
					<view class="card-content">
						<view class="avatar-box">
							<view class="avatar-border">
								<image v-if="isLogin" :src="userInfo.avatar_url" mode="aspectFill"
									@click="$T.go('/others/setting/setting')"
									style="width:130rpx;height:130rpx;border-radius:50%;" />
								<view v-else class="avatar-placeholder">
									<tui-icon name="people" color="#1A1A1A" size="40"></tui-icon>
								</view>
							</view>
							<!-- 皇冠装饰 -->
							<view class="crown-icon" v-if="isLogin">👑</view>
						</view>

						<view class="info-box">
							<view class="nick-row">
								<text class="nickname">{{ isLogin ? userInfo.nickname : '登录 / 注册' }}</text>
								<!-- VIP 徽章 -->
								<view class="vip-badge" v-if="isLogin && userInfo.level_name">
									<text class="vip-text">{{ userInfo.level_name }}</text>
									<view class="shine-effect"></view>
								</view>
							</view>
							<view class="uid-row" v-if="isLogin" @click.stop="copyId(userInfo.id)">
								<text class="uid-text">ID: {{ userInfo.id }}</text>
								<tui-icon name="copy" size="12" color="rgba(229, 192, 123, 0.6)"
									style="margin-left:8rpx;"></tui-icon>
								<text class="mobile-inline" v-if="isLogin && formattedPageSetting.header && formattedPageSetting.header.show_mobile == 1 && userInfo.mobile" style="margin-left:10rpx;opacity:0.8;font-size:24rpx;">· {{ userInfo.mobile }}</text>
							</view>
							<view class="uid-row" v-else>
								<text class="uid-text">Unlock Premium Experience</text>
							</view>
						</view>
					</view>

					<!-- A.2 头部统计 (收藏/足迹) -->
					<view class="header-stats-row" v-if="hasHeaderStats">
						<view class="h-col click-bounce" v-if="formattedPageSetting.header.goods.is_show == 1"
							@click.stop="$T.go('/others/collect/collect')">
							<text class="n">{{ isLogin ? formattedPageSetting.header.goods.value : 0 }}</text>
							<text class="t">{{ formattedPageSetting.header.goods.name }}</text>
						</view>
						<view class="h-col click-bounce" v-if="formattedPageSetting.header.footmark.is_show == 1"
							@click.stop="$T.go('/others/footmark/footmark')">
							<text class="n">{{ isLogin ? formattedPageSetting.header.footmark.value : 0 }}</text>
							<text class="t">{{ formattedPageSetting.header.footmark.name }}</text>
						</view>
						<view class="h-col click-bounce" v-if="formattedPageSetting.header.order.is_show == 1"
							@click.stop="$T.go('/others/order/order')">
							<text class="n">{{ isLogin ? formattedPageSetting.header.order.value : 0 }}</text>
							<text class="t">{{ formattedPageSetting.header.order.name }}</text>
						</view>
					</view>

					<!-- B. 资产数据 -->
					<view class="stats-row"
						v-if="formattedPageSetting && formattedPageSetting.finance && formattedPageSetting.finance.is_show == 1">

						<!-- 余额 -->
						<view class="stat-item click-bounce" v-if="formattedPageSetting.finance.balance.is_show == 1"
							@click="isLogin && goAsset('balance', '/plugins2/withdraw/balance')">
							<text class="num" :style="formattedPageSetting.finance.balance.color ? 'color:' + formattedPageSetting.finance.balance.color : ''">{{ isLogin ? userInfo.money : '0.00' }}</text>
							<text class="lbl">{{ formattedPageSetting.finance.balance.name }}</text>
						</view>

						<view class="stat-item click-bounce"
							v-if="formattedPageSetting.finance.price && formattedPageSetting.finance.price.is_show == 1"
							@click="isLogin && goAsset('price', '/plugins2/withdraw/price')">
							<text class="num gold-text" :style="formattedPageSetting.finance.price.color ? 'color:' + formattedPageSetting.finance.price.color : ''">{{ isLogin ? userInfo.price : '0.00' }}</text>
							<text class="lbl">{{ formattedPageSetting.finance.price.name }}</text>
						</view>

						<view class="stat-item click-bounce" v-if="formattedPageSetting.finance.integral.is_show == 1"
							@click="isLogin && goAsset('integral', '/plugins2/withdraw/integral')">
							<text class="num" :style="formattedPageSetting.finance.integral.color ? 'color:' + formattedPageSetting.finance.integral.color : ''">{{ isLogin ? userInfo.integral : 0 }}</text>
							<text class="lbl">{{ formattedPageSetting.finance.integral.name }}</text>
						</view>

						<view class="stat-item click-bounce" v-if="formattedPageSetting.finance.coupon.is_show == 1"
							@tap="goAsset('coupon', '/others/coupon/coupon?status=1')">
							<text class="num" :style="formattedPageSetting.finance.coupon.color ? 'color:' + formattedPageSetting.finance.coupon.color : ''">{{ isLogin ? userInfo.coupon : 0 }}</text>
							<text class="lbl">{{ formattedPageSetting.finance.coupon.name }}</text>
						</view>
					</view>
				</view>
			</view>

			<!-- C. 订单模块 (深色玻璃态) -->
			<view class="glass-section" v-if="formattedPageSetting && formattedPageSetting.order && formattedPageSetting.order.is_show != 0">
				<view class="section-header" @click="$T.go('/others/order/order?status=-1')">
					<text class="title">{{ formattedPageSetting.order.title }}</text>
					<view class="more">
						<text>{{ formattedPageSetting.order.sub_title }}</text>
						<tui-icon name="arrowright" size="12" color="rgba(255,255,255,0.3)"></tui-icon>
					</view>
				</view>

				<view class="p-grid-wrap">
					<!-- 待付款 -->
					<view class="p-grid-item click-bounce" v-if="formattedPageSetting.order.status_0.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_0.url)">
						<view class="icon-box">
							<image :src="formattedPageSetting.order.status_0.icon" class="main-icon"></image>
							<view class="gold-badge" v-if="isLogin && formattedPageSetting.order.status_0.num > 0">
								{{ formattedPageSetting.order.status_0.num }}
							</view>
						</view>
						<text class="grid-text">{{ formattedPageSetting.order.status_0.name }}</text>
					</view>

					<!-- 待发货 -->
					<view class="p-grid-item click-bounce" v-if="formattedPageSetting.order.status_1.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_1.url)">
						<view class="icon-box">
							<image :src="formattedPageSetting.order.status_1.icon" class="main-icon"></image>
							<view class="gold-badge" v-if="isLogin && formattedPageSetting.order.status_1.num > 0">
								{{ formattedPageSetting.order.status_1.num }}
							</view>
						</view>
						<text class="grid-text">{{ formattedPageSetting.order.status_1.name }}</text>
					</view>

					<!-- 待收货 -->
					<view class="p-grid-item click-bounce" v-if="formattedPageSetting.order.status_2.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_2.url)">
						<view class="icon-box">
							<image :src="formattedPageSetting.order.status_2.icon" class="main-icon"></image>
							<view class="gold-badge" v-if="isLogin && formattedPageSetting.order.status_2.num > 0">
								{{ formattedPageSetting.order.status_2.num }}
							</view>
						</view>
						<text class="grid-text">{{ formattedPageSetting.order.status_2.name }}</text>
					</view>

					<!-- 待评价 -->
					<view class="p-grid-item click-bounce" v-if="formattedPageSetting.order.status_3.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_3.url)">
						<view class="icon-box">
							<image :src="formattedPageSetting.order.status_3.icon" class="main-icon"></image>
							<view class="gold-badge" v-if="isLogin && formattedPageSetting.order.status_3.num > 0">
								{{ formattedPageSetting.order.status_3.num }}
							</view>
						</view>
						<text class="grid-text">{{ formattedPageSetting.order.status_3.name }}</text>
					</view>

					<!-- 售后 -->
					<view class="p-grid-item click-bounce" v-if="formattedPageSetting.order.status_4.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_4.url)">
						<view class="icon-box">
							<image :src="formattedPageSetting.order.status_4.icon" class="main-icon"></image>
							<view class="gold-badge" v-if="isLogin && formattedPageSetting.order.status_4.num > 0">
								{{ formattedPageSetting.order.status_4.num }}
							</view>
						</view>
						<text class="grid-text">{{ formattedPageSetting.order.status_4.name }}</text>
					</view>
				</view>
			</view>

			<!-- D. 常用工具 (列表模式更显高级) -->
			<view class="glass-section"
				v-if="formattedPageSetting && formattedPageSetting.menus && formattedPageSetting.menus.is_show == 1">
				<view class="section-header">
					<text class="title">{{ formattedPageSetting.menus.name }}</text>
				</view>

				<!-- Grid模式 -->
				<view class="p-grid-wrap" v-if="formattedPageSetting.menus.type == 0">
					<view class="p-grid-item click-bounce" style="width: 25%; margin-bottom: 20rpx;"
						v-for="(item, index) in formattedPageSetting.menus.list" :key="index" @click="$T.go(item.url)">
						<view class="dark-icon-bg">
							<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="tool-img"></image>
						</view>
						<text class="grid-text">{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
					</view>
				</view>

				<!-- List模式 -->
				<view class="tools-list" v-else>
					<view class="tool-row click-effect" v-for="(item, index) in formattedPageSetting.menus.list"
						:key="index" @click="$T.go(item.url)">
						<view class="row-left">
							<view class="dark-icon-bg">
								<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="tool-img"></image>
							</view>
							<text class="tool-name">{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
						</view>
						<tui-icon name="arrowright" size="14" color="rgba(255,255,255,0.2)"></tui-icon>
					</view>
				</view>
			</view>

			<!-- E. 常用工具2 -->
			<view class="glass-section"
				v-if="formattedPageSetting && formattedPageSetting.menus2 && formattedPageSetting.menus2.is_show == 1">
				<view class="section-header">
					<text class="title">{{ formattedPageSetting.menus2.name }}</text>
				</view>

				<view class="p-grid-wrap" v-if="formattedPageSetting.menus2.type == 0">
					<view class="p-grid-item click-bounce" style="width: 25%; margin-bottom: 20rpx;"
						v-for="(item, index) in formattedPageSetting.menus2.list" :key="index" @click="$T.go(item.url)">
						<view class="dark-icon-bg">
							<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="tool-img"></image>
						</view>
						<text class="grid-text">{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
					</view>
				</view>

				<view class="tools-list" v-else>
					<view class="tool-row click-effect" v-for="(item, index) in formattedPageSetting.menus2.list"
						:key="index" @click="$T.go(item.url)">
						<view class="row-left">
							<view class="dark-icon-bg">
								<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="tool-img"></image>
							</view>
							<text class="tool-name">{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
						</view>
						<tui-icon name="arrowright" size="14" color="rgba(255,255,255,0.2)"></tui-icon>
					</view>
				</view>
			</view>

			<!-- F. 推荐好物 (黑金风格) -->
			<view class="recommend-section" v-if="list.length">
				<view class="gold-divider">
					<view class="line"></view>
					<text class="text">推荐好物</text>
					<view class="line"></view>
				</view>

				<view class="premium-goods-list">
					<uni-grid :column="2" :square="false" :showBorder="false" :highlight="false">
						<block v-for="(item, index) in list" :key="index">
							<uni-grid-item>
								<!-- 动态 Padding -->
								<view class="goods-item-wrap"
									:style="{ padding: index % 2 == 0 ? '0 10rpx 24rpx 0rpx' : '0 0rpx 24rpx 10rpx' }">
									<!-- 包裹一层强制暗黑样式 -->
									<view class="dark-goods-card">
										<tui-goods-item :goods="item"></tui-goods-item>
									</view>
								</view>
							</uni-grid-item>
						</block>
					</uni-grid>
				</view>
				<tui-loadmore v-if="loadding" :index="3" type="primary" color="#E5C07B"></tui-loadmore>
			</view>

			<!-- 底部版权 -->
			<view class="footer"
				v-if="is_no_more && formattedPageSetting && formattedPageSetting.copyright && formattedPageSetting.copyright.is_show == 1">
				<text class="copyright">{{ formattedPageSetting.copyright.text }}</text>
			</view>

			<view style="height: 50rpx;"></view>
		</view>

		<!-- 登录弹窗 -->
		<tui-user-avatar :visible="showProfileModal" :userData="userInfo" @save="handleSaveSuccess"
			@close="showProfileModal = false" />

	</view>
</template>

<script>
	import TuiUserAvatar from '@/components/thorui/tui-user-avatar/tui-user-avatar.vue'
import goodsRecommend from '@/mixins/goods-recommend.js';

export default {
	mixins: [goodsRecommend],
		components: { TuiUserAvatar },
	name: "user-premium",
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
			navRightGap: 0,
			showProfileModal: false,

			// 商品列表
		}
	},
	computed: {
		// MP 端 :style 注 CSS 变量必须用字符串(对象会丢键,见全局 themeStyle 约定),根节点自注入避免跨组件继承失效
		colorVars() {
			const c = (this.pageSetting || {}).colors || {};
			let s = '';
			if (c.header_from) s += '--user-color-from:' + c.header_from + ';';
			if (c.header_to) s += '--user-color-to:' + c.header_to + ';';
			return s;
		},
		// 数据装饰，给默认值
		formattedPageSetting() {
			let data = this.pageSetting ? JSON.parse(JSON.stringify(this.pageSetting)) : {};
			if (!data.copyright) {
				data.copyright = {
					is_show: 1,
					text: 'PREMIUM MEMBERSHIP'
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
		// 判断是否有头部数据
		hasHeaderStats() {
			if (!this.formattedPageSetting || !this.formattedPageSetting.header) return false;
			const h = this.formattedPageSetting.header;
			return (h.goods && h.goods.is_show == 1) || (h.footmark && h.footmark.is_show == 1);
		}
	},
	created() {
		this.initNav();

		// 个性化推荐不复用跨账号缓存。
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
		// 父组件调用的刷新方法
		refreshLocalData() {
			this.page = 1;
			this.is_no_more = false;
			this.pullUpOn = true;
			this.getRecommend(true);
		},

		// 父组件调用的触底加载
		handleReachBottom() {
			if (!this.pullUpOn || this.loadding || this.is_no_more) return;
			this.getRecommend();
		},

		initNav() {
			const sys = uni.getSystemInfoSync();
			this.top = sys.statusBarHeight || 20;
			// #ifdef MP-WEIXIN
			let menuButton = uni.getMenuButtonBoundingClientRect();
			if (menuButton) this.navRightGap = (sys.screenWidth - menuButton.left) + 10;
			// #endif
			// #ifndef MP-WEIXIN
			this.navRightGap = 20;
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
/* --- 核心变量 --- */
$bg-dark: #121212;
$gold-main: #E5C07B;
$gold-dark: #BFA15F;
/* 深色磨砂玻璃背景 */
$glass-dark: rgba(30, 30, 30, 0.75);
$glass-border: rgba(229, 192, 123, 0.2);
$text-white: #FFFFFF;
$text-gray: rgba(255, 255, 255, 0.6);

.premium-container {
	min-height: 100vh;
	background-color: $bg-dark;
	position: relative;
	overflow-x: hidden;
	font-family: -apple-system, sans-serif;
	color: $text-white;
	/* Increase bottom padding to prevent obstruction by the bottom navigation bar, while adapting to the bottom safety area */
	padding-bottom: calc(120rpx + env(safe-area-inset-bottom));
}

/* --- 1. 背景层 (黑金流光) --- */
.premium-bg {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100vh;
	z-index: 0;
	/* 径向渐变，中心稍亮 */
	background: radial-gradient(circle at 50% -20%, var(--user-color-from, #2a2a2a) 0%, var(--user-color-to, #121212) 80%);

	/* 噪点纹理，增加质感 */
	.noise-texture {
		position: absolute;
		width: 100%;
		height: 100%;
		opacity: 0.03;
		background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
		pointer-events: none;
	}

	/* 漂浮的金色光球 */
	.gold-orb {
		position: absolute;
		border-radius: 50%;
		filter: blur(120rpx);
		opacity: 0.15;
		background: $gold-main;

		&.orb-1 {
			width: 400rpx;
			height: 400rpx;
			top: -100rpx;
			left: -100rpx;
			animation: float 10s ease-in-out infinite alternate;
		}

		&.orb-2 {
			width: 300rpx;
			height: 300rpx;
			bottom: 20%;
			right: -50rpx;
			animation: float 8s ease-in-out infinite alternate-reverse;
		}
	}
}

/* --- 2. 顶部悬浮按钮 --- */
.header-actions {
	position: fixed;
	z-index: 999;
	display: flex;

	.action-btn {
		width: 72rpx;
		height: 72rpx;
		border-radius: 50%;
		display: flex;
		justify-content: center;
		align-items: center;
		/* 玻璃态按钮 */
		background: rgba(255, 255, 255, 0.05);
		border: 1rpx solid $glass-border;
		backdrop-filter: blur(20rpx);

		.gold-dot {
			position: absolute;
			top: 18rpx;
			right: 18rpx;
			width: 12rpx;
			height: 12rpx;
			background: #FF4d4f;
			border-radius: 50%;
			border: 2rpx solid #121212;

			&.pulse {
				animation: pulse 2s infinite;
			}
		}
	}
}

/* --- 3. 内容区 --- */
.content-scroll {
	position: relative;
	z-index: 10;
	padding: 0 32rpx;
}

/* A. 尊享卡片 (核心视觉) */
.user-card-premium {
	margin-bottom: 40rpx;

	.card-glass-layer {
		/* 深色玻璃背景 */
		background: linear-gradient(135deg, rgba(40, 40, 40, 0.9) 0%, rgba(20, 20, 20, 0.95) 100%);
		border-radius: 32rpx;
		padding: 40rpx 30rpx;
		border: 1rpx solid $glass-border;
		box-shadow: 0 20rpx 60rpx rgba(0, 0, 0, 0.5);
		position: relative;
		overflow: hidden;

		/* 扫光动画 */
		&::before {
			content: '';
			position: absolute;
			top: 0;
			left: -100%;
			width: 50%;
			height: 100%;
			background: linear-gradient(90deg, transparent, rgba(229, 192, 123, 0.1), transparent);
			transform: skewX(-20deg);
			animation: shine 4s infinite linear;
			pointer-events: none;
		}

		.card-content {
			display: flex;
			align-items: center;
			margin-bottom: 30rpx;

			.avatar-box {
				position: relative;
				margin-right: 30rpx;

				.avatar-border {
					width: 140rpx;
					height: 140rpx;
					border-radius: 50%;
					display: flex;
					align-items: center;
					justify-content: center;
					border: 2rpx solid $gold-main;
					box-shadow: 0 0 20rpx rgba(229, 192, 123, 0.2);
					background: #000;
				}

				.crown-icon {
					position: absolute;
					top: -16rpx;
					right: -10rpx;
					font-size: 32rpx;
					transform: rotate(15deg);
				}
			}

			.info-box {
				flex: 1;
				// 修复：限制宽度
				min-width: 0;

				.nick-row {
					display: flex;
					align-items: center;
					margin-bottom: 12rpx;
					max-width: 100%;

					.nickname {
						font-size: 40rpx;
						font-weight: 700;
						color: $gold-main;
						letter-spacing: 1rpx;
						margin-right: 16rpx;

						/* 修复：昵称溢出 */
						max-width: 320rpx;
						white-space: nowrap;
						overflow: hidden;
						text-overflow: ellipsis;
					}

					.vip-badge {
						background: linear-gradient(90deg, $gold-dark, $gold-main);
						padding: 4rpx 12rpx;
						border-radius: 8rpx;
						position: relative;
						overflow: hidden;
						flex-shrink: 0;

						.vip-text {
							font-size: 20rpx;
							color: #000;
							font-weight: 800;
							text-transform: uppercase;
						}

						.shine-effect {
							position: absolute;
							top: 0;
							left: -100%;
							width: 50%;
							height: 100%;
							background: rgba(255, 255, 255, 0.4);
							transform: skewX(-20deg);
							animation: shine 2.5s infinite;
						}
					}
				}

				.uid-row {
					display: inline-flex;
					align-items: center;

					.uid-text {
						font-size: 24rpx;
						color: $text-gray;
						font-family: 'DIN', sans-serif;
					}
				}
			}
		}

		.header-stats-row {
			display: flex;
			justify-content: space-around;
			padding-bottom: 24rpx;
			border-bottom: 1px dashed rgba(255, 255, 255, 0.1);

			.h-col {
				text-align: center;

				.n {
					font-weight: bold;
					color: #fff;
					font-size: 32rpx;
					margin-right: 8rpx;
				}

				.t {
					font-size: 22rpx;
					color: $text-gray;
				}
			}
		}

		.stats-row {
			display: flex;
			justify-content: space-around;
			padding-top: 24rpx;

			.stat-item {
				display: flex;
				flex-direction: column;
				align-items: center;
				flex: 1;

				.num {
					font-size: 36rpx;
					font-weight: 700;
					color: $text-white;
					font-family: 'DIN', sans-serif;
					margin-bottom: 6rpx;
				}

				.gold-text {
					color: $gold-main;
				}

				.lbl {
					font-size: 22rpx;
					color: $text-gray;
					text-transform: uppercase;
				}
			}
		}
	}
}

/* C/D/E 通用深色玻璃模块 */
.glass-section {
	background: $glass-dark;
	border-radius: 24rpx;
	margin-bottom: 30rpx;
	border: 1rpx solid rgba(255, 255, 255, 0.08);
	backdrop-filter: blur(10rpx);

	.section-header {
		padding: 30rpx;
		display: flex;
		justify-content: space-between;
		align-items: center;
		border-bottom: 1rpx solid rgba(255, 255, 255, 0.05);

		.title {
			font-size: 28rpx;
			font-weight: 600;
			color: $gold-main;
			letter-spacing: 1rpx;
			text-transform: uppercase;
		}

		.more {
			display: flex;
			align-items: center;

			text {
				font-size: 24rpx;
				color: $text-gray;
				margin-right: 6rpx;
			}
		}
	}

	/* P-Grid 布局 */
	.p-grid-wrap {
		display: flex;
		padding: 30rpx 0;
		flex-wrap: wrap;

		.p-grid-item {
			flex: 1;
			display: flex;
			flex-direction: column;
			align-items: center;
			/* 5列布局 */
			min-width: 20%;

			.icon-box {
				position: relative;
				margin-bottom: 16rpx;

				.main-icon {
					width: 48rpx;
					height: 48rpx;
					opacity: 0.9;
				}

				/* 建议图标是白色的 */
				.gold-badge {
					position: absolute;
					top: -10rpx;
					right: -12rpx;
					background: $gold-main;
					color: #000;
					font-size: 20rpx;
					min-width: 30rpx;
					height: 30rpx;
					line-height: 30rpx;
					text-align: center;
					border-radius: 30rpx;
					font-weight: 700;
				}
			}

			.dark-icon-bg {
				width: 80rpx;
				height: 80rpx;
				border-radius: 20rpx;
				background: rgba(255, 255, 255, 0.05);
				display: flex;
				justify-content: center;
				align-items: center;

				.tool-img {
					width: 44rpx;
					height: 44rpx;
				}
			}

			.grid-text {
				font-size: 24rpx;
				color: $text-gray;
			}
		}
	}

	.tools-list {
		padding: 0 30rpx;

		.tool-row {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 28rpx 0;
			border-bottom: 1rpx solid rgba(255, 255, 255, 0.05);

			&:last-child {
				border-bottom: none;
			}

			.row-left {
				display: flex;
				align-items: center;

				.dark-icon-bg {
					width: 64rpx;
					height: 64rpx;
					border-radius: 16rpx;
					background: rgba(255, 255, 255, 0.05);
					display: flex;
					justify-content: center;
					align-items: center;
					margin-right: 24rpx;

					.tool-img {
						width: 36rpx;
						height: 36rpx;
					}
				}

				.tool-name {
					font-size: 28rpx;
					color: $text-white;
				}
			}
		}
	}
}

/* F. 推荐好物 (黑金风格) */
.recommend-section {
	margin-top: 50rpx;

	.gold-divider {
		display: flex;
		align-items: center;
		justify-content: center;
		margin-bottom: 30rpx;

		.line {
			width: 60rpx;
			height: 2rpx;
			background: linear-gradient(90deg, transparent, $gold-main, transparent);
			opacity: 0.5;
		}

		.text {
			font-size: 24rpx;
			color: $gold-main;
			margin: 0 20rpx;
			letter-spacing: 4rpx;
			font-weight: 300;
		}
	}

	/* 
       强制覆盖商品卡片为深色模式 
       注意：这里使用 ::v-deep 穿透修改子组件样式
    */
	/* ⚠ 类名要和 components/thorui/tui-goods-item 里的对上。
	   原来写的 .tui-goods-item / .tui-goods-title / .tui-goods-price 组件里从来没有,
	   这套深色覆盖一直没生效,黑金主题下卡片是白的。改组件类名时这里要一起改。 */
	.dark-goods-card {
		::v-deep .tui-pro-item {
			background: #1E1E1E !important;
			border: 1rpx solid rgba(255, 255, 255, 0.05) !important;
			box-shadow: none !important;
		}

		::v-deep .tui-pro-tit {
			color: #E0E0E0 !important;
		}

		::v-deep .tui-sale-price {
			color: #E5C07B !important;
		}

		::v-deep .tui-factory-price {
			color: rgba(255, 255, 255, 0.3) !important;
		}

		::v-deep .tui-pro-sold {
			color: rgba(255, 255, 255, 0.45) !important;
		}

		/* 购物车钮跟价格一样用香槟金,不用商城主题红 —— 红在黑金底上太跳 */
		::v-deep .tui-pro-go {
			background: #E5C07B !important;
			box-shadow: 0 4rpx 12rpx rgba(229, 192, 123, 0.25) !important;
		}

		::v-deep .tui-pro-go .tui-icon {
			color: #1E1E1E !important;
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

	.copyright {
		font-size: 20rpx;
		color: rgba(255, 255, 255, 0.2);
		letter-spacing: 2rpx;
		text-transform: uppercase;
	}
}

/* --- 动画 --- */
@keyframes float {
	0% {
		transform: translateY(0);
	}

	100% {
		transform: translateY(20rpx);
	}
}

@keyframes shine {
	0% {
		left: -100%;
	}

	20% {
		left: 200%;
	}

	100% {
		left: 200%;
	}
}

.click-bounce {
	transition: transform 0.1s;
}

.click-bounce:active {
	transform: scale(0.95);
	opacity: 0.8;
}
</style>