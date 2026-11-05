<template>
	<view class="dream-container" :style="colorVars">

		<!-- 1. 梦幻弥散背景 (CSS 动画) -->
		<view class="dream-bg">
			<view class="blob blob-1"></view>
			<view class="blob blob-2"></view>
			<view class="blob blob-3"></view>
			<view class="glass-overlay"></view>
		</view>

		<!-- 顶部导航已提取到父组件 user.vue -->

		<!-- 3. 内容区 -->
		<view class="dream-content" :style="{ paddingTop: (top + 44) + 'px' }">

			<!-- A. 用户卡片 -->
			<view class="dream-card user-info-card" @click="!isLogin ? goToLogin() : ''">
				<view class="card-glass-shine"></view>
				<!-- 等级标签：卡片右上角 -->
				<view class="level-tag" v-if="isLogin && userInfo.level_name">
					<text>{{ userInfo.level_name }}</text>
				</view>
				<view class="card-body">
					<view class="avatar-section">
						<view class="avatar-ring">
							<image v-if="isLogin" :src="userInfo.avatar_url" mode="aspectFill"
								@click="$T.go('/others/setting/setting')"
								style="width:120rpx;height:120rpx;border-radius:50%;" />
							<view v-else class="avatar-empty">
								<tui-icon name="people" size="36" color="#fff"></tui-icon>
							</view>
						</view>
					</view>

					<view class="text-section">
						<view class="nick-box">
							<!-- 修复：昵称过长省略 -->
							<text class="nickname">{{ isLogin ? userInfo.nickname : '登录 / 注册' }}</text>
						</view>
						<view class="uid-box" v-if="isLogin" @click.stop="copyId(userInfo.id)">
							<text class="uid-num">UID: {{ userInfo.id }}</text>
							<tui-icon name="copy" size="10" color="rgba(0,0,0,0.4)"
								style="margin-left: 6rpx;"></tui-icon>
							<text class="mobile-inline" v-if="isLogin && formattedPageSetting.header && formattedPageSetting.header.show_mobile == 1 && userInfo.mobile" style="margin-left:10rpx;opacity:0.8;font-size:22rpx;">· {{ userInfo.mobile }}</text>
						</view>
						<view class="uid-box" v-else>
							<text class="uid-num">开启梦幻之旅</text>
						</view>
					</view>

					<!-- 签到按钮 -->
					<view class="check-btn click-push" style="display: none;" v-if="isLogin"
						@click.stop="$T.go('/plugins/check_in/index')">
						<text>签到</text>
					</view>
				</view>

				<!-- A.2 头部数据 (收藏/足迹) -->
				<view class="header-stats-row" v-if="hasHeaderStats">
					<view class="h-col click-push" v-if="safePageSetting.header.goods.is_show == 1"
						@click.stop="$T.go('/others/collect/collect')">
						<text class="n">{{ isLogin ? safePageSetting.header.goods.value : 0 }}</text>
						<text class="t">{{ safePageSetting.header.goods.name }}</text>
					</view>
					<view class="h-col click-push" v-if="safePageSetting.header.footmark.is_show == 1"
						@click.stop="$T.go('/others/footmark/footmark')">
						<text class="n">{{ isLogin ? safePageSetting.header.footmark.value : 0 }}</text>
						<text class="t">{{ safePageSetting.header.footmark.name }}</text>
					</view>
					<view class="h-col click-push" v-if="safePageSetting.header.order.is_show == 1"
						@click.stop="$T.go('/others/order/order')">
						<text class="n">{{ isLogin ? safePageSetting.header.order.value : 0 }}</text>
						<text class="t">{{ safePageSetting.header.order.name }}</text>
					</view>
					<!-- 如果有成交订单统计也可以放这里 -->
				</view>
			</view>

			<!-- B. 资产卡片 (独立显示) -->
			<view class="dream-card finance-card"
				v-if="safePageSetting && safePageSetting.finance && safePageSetting.finance.is_show == 1">
				<view class="dream-stats">
					<!-- 余额 -->
					<view class="stat-col click-push" v-if="safePageSetting.finance.balance.is_show == 1"
						@click="isLogin && goAsset('balance', '/plugins2/withdraw/balance')">
						<text class="num" :style="safePageSetting.finance.balance.color ? 'color:' + safePageSetting.finance.balance.color : ''">{{ isLogin ? userInfo.money : '0.00' }}</text>
						<text class="lbl">{{ safePageSetting.finance.balance.name }}</text>
					</view>

					<view class="stat-col click-push"
						v-if="safePageSetting.finance.price && safePageSetting.finance.price.is_show == 1"
						@click="isLogin && goAsset('price', '/plugins2/withdraw/price')">
						<text class="num highlight" :style="safePageSetting.finance.price.color ? 'color:' + safePageSetting.finance.price.color : ''">{{ isLogin ? userInfo.price : '0.00' }}</text>
						<text class="lbl">{{ safePageSetting.finance.price.name }}</text>
					</view>

					<view class="stat-col click-push" v-if="safePageSetting.finance.integral.is_show == 1"
						@click="isLogin && goAsset('integral', '/plugins2/withdraw/integral')">
						<text class="num" :style="safePageSetting.finance.integral.color ? 'color:' + safePageSetting.finance.integral.color : ''">{{ isLogin ? userInfo.integral : 0 }}</text>
						<text class="lbl">{{ safePageSetting.finance.integral.name }}</text>
					</view>

					<view class="stat-col click-push" v-if="safePageSetting.finance.coupon.is_show == 1"
						@tap="goAsset('coupon', '/others/coupon/coupon?status=1')">
						<text class="num" :style="safePageSetting.finance.coupon.color ? 'color:' + safePageSetting.finance.coupon.color : ''">{{ isLogin ? userInfo.coupon : 0 }}</text>
						<text class="lbl">{{ safePageSetting.finance.coupon.name }}</text>
					</view>
				</view>
			</view>

			<!-- C. 订单模块 -->
			<view class="dream-card section-card" v-if="safePageSetting && safePageSetting.order && safePageSetting.order.is_show != 0">
				<view class="card-header" @click="$T.go('/others/order/order?status=-1')">
					<text class="header-title">{{ safePageSetting.order.title }}</text>
					<view class="header-more">
						<text>{{ safePageSetting.order.sub_title }}</text>
						<tui-icon name="arrowright" size="12" color="#999"></tui-icon>
					</view>
				</view>

				<view class="dream-grid-box">
					<view class="dream-grid-item click-push" v-if="safePageSetting.order.status_0.is_show == 1"
						@tap="$T.go(safePageSetting.order.status_0.url)">
						<view class="icon-wrap">
							<view class="icon-glow glow-blue"></view>
							<image :src="safePageSetting.order.status_0.icon" class="main-icon"></image>
							<view class="float-badge" v-if="isLogin && safePageSetting.order.status_0.num > 0">
								{{ safePageSetting.order.status_0.num }}
							</view>
						</view>
						<text class="item-name">{{ safePageSetting.order.status_0.name }}</text>
					</view>

					<view class="dream-grid-item click-push" v-if="safePageSetting.order.status_1.is_show == 1"
						@tap="$T.go(safePageSetting.order.status_1.url)">
						<view class="icon-wrap">
							<view class="icon-glow glow-purple"></view>
							<image :src="safePageSetting.order.status_1.icon" class="main-icon"></image>
							<view class="float-badge" v-if="isLogin && safePageSetting.order.status_1.num > 0">
								{{ safePageSetting.order.status_1.num }}
							</view>
						</view>
						<text class="item-name">{{ safePageSetting.order.status_1.name }}</text>
					</view>

					<view class="dream-grid-item click-push" v-if="safePageSetting.order.status_2.is_show == 1"
						@tap="$T.go(safePageSetting.order.status_2.url)">
						<view class="icon-wrap">
							<view class="icon-glow glow-pink"></view>
							<image :src="safePageSetting.order.status_2.icon" class="main-icon"></image>
							<view class="float-badge" v-if="isLogin && safePageSetting.order.status_2.num > 0">
								{{ safePageSetting.order.status_2.num }}
							</view>
						</view>
						<text class="item-name">{{ safePageSetting.order.status_2.name }}</text>
					</view>

					<view class="dream-grid-item click-push" v-if="safePageSetting.order.status_3.is_show == 1"
						@tap="$T.go(safePageSetting.order.status_3.url)">
						<view class="icon-wrap">
							<view class="icon-glow glow-green"></view>
							<image :src="safePageSetting.order.status_3.icon" class="main-icon"></image>
							<view class="float-badge" v-if="isLogin && safePageSetting.order.status_3.num > 0">
								{{ safePageSetting.order.status_3.num }}
							</view>
						</view>
						<text class="item-name">{{ safePageSetting.order.status_3.name }}</text>
					</view>

					<view class="dream-grid-item click-push" v-if="safePageSetting.order.status_4.is_show == 1"
						@tap="$T.go(safePageSetting.order.status_4.url)">
						<view class="icon-wrap">
							<view class="icon-glow glow-orange"></view>
							<image :src="safePageSetting.order.status_4.icon" class="main-icon"></image>
							<view class="float-badge" v-if="isLogin && safePageSetting.order.status_4.num > 0">
								{{ safePageSetting.order.status_4.num }}
							</view>
						</view>
						<text class="item-name">{{ safePageSetting.order.status_4.name }}</text>
					</view>
				</view>
			</view>

			<!-- D. 常用工具 -->
			<view class="dream-card section-card"
				v-if="safePageSetting && safePageSetting.menus && safePageSetting.menus.is_show == 1">
				<view class="card-header">
					<text class="header-title">{{ safePageSetting.menus.name }}</text>
				</view>

				<!-- 网格模式 -->
				<view class="dream-grid-box tools-layout" v-if="safePageSetting.menus.type == 0">
					<view class="dream-grid-item click-push" v-for="(item, index) in safePageSetting.menus.list"
						:key="index" @click="$T.go(item.url)">
						<view class="tool-icon-box">
							<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="tool-img"></image>
						</view>
						<text class="item-name">{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
					</view>
				</view>
				<!-- 列表模式 -->
				<view class="dream-list-layout" v-else>
					<view class="list-item click-push" v-for="(item, index) in safePageSetting.menus.list" :key="index"
						@click="$T.go(item.url)">
						<view class="left">
							<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="icon"></image>
							<text>{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
						</view>
						<tui-icon name="arrowright" size="14" color="#ccc"></tui-icon>
					</view>
				</view>
			</view>

			<!-- E. 常用工具2 -->
			<view class="dream-card section-card"
				v-if="safePageSetting && safePageSetting.menus2 && safePageSetting.menus2.is_show == 1">
				<view class="card-header">
					<text class="header-title">{{ safePageSetting.menus2.name }}</text>
				</view>

				<view class="dream-grid-box tools-layout" v-if="safePageSetting.menus2.type == 0">
					<view class="dream-grid-item click-push" v-for="(item, index) in safePageSetting.menus2.list"
						:key="index" @click="$T.go(item.url)">
						<view class="tool-icon-box">
							<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="tool-img"></image>
						</view>
						<text class="item-name">{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
					</view>
				</view>
				<view class="dream-list-layout" v-else>
					<view class="list-item click-push" v-for="(item, index) in safePageSetting.menus2.list" :key="index"
						@click="$T.go(item.url)">
						<view class="left">
							<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="icon"></image>
							<text>{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
						</view>
						<tui-icon name="arrowright" size="14" color="#ccc"></tui-icon>
					</view>
				</view>
			</view>

			<!-- F. 推荐好物 -->
			<view class="recommend-box" v-if="list.length">
				<view class="dream-title">
					<text class="en">DISCOVER</text>
					<text class="zh">推荐好物</text>
				</view>

				<view class="dream-waterfall">
					<uni-grid :column="2" :square="false" :showBorder="false" :highlight="false">
						<block v-for="(item, index) in list" :key="index">
							<uni-grid-item>
								<!-- 动态间距 -->
								<view class="goods-wrapper"
									:style="{ padding: index % 2 == 0 ? '0 12rpx 24rpx 0rpx' : '0 0rpx 24rpx 12rpx' }">
									<view class="dream-goods-card">
										<tui-goods-item :goods="item"></tui-goods-item>
									</view>
								</view>
							</uni-grid-item>
						</block>
					</uni-grid>
				</view>
				<tui-loadmore v-if="loadding" :index="3" type="primary" color="#BDB2FF"></tui-loadmore>
			</view>

			<!-- 底部版权 -->
			<view class="dream-footer"
				v-if="is_no_more && safePageSetting && safePageSetting.copyright && safePageSetting.copyright.is_show == 1">
				<text>{{ safePageSetting.copyright.text }}</text>
			</view>

			<view style="height: 50rpx;"></view>
		</view>

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
	name: "user-dream",
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
		};
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
		safePageSetting() {
			if (!this.pageSetting) return {};
			let data = {};
			try {
				data = JSON.parse(JSON.stringify(this.pageSetting));
			} catch (e) {
				data = {
					...this.pageSetting
				};
			}
			if (!data.copyright) {
				data.copyright = {
					is_show: 1,
					text: 'Simplicity & Aurora UI'
				};
			}
			// 确保 finance 对象完整
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
			if (!this.safePageSetting || !this.safePageSetting.header) return false;
			const h = this.safePageSetting.header;
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
		// 资产项跳转：后台配置了链接走配置，否则走默认（兼容提现中心插件迁移）
		goAsset(key, defaultUrl) {
			const f = this.safePageSetting && this.safePageSetting.finance;
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
};
</script>

<style lang="scss" scoped>
/* --- 全局样式变量 --- */
$dream-bg: #F3F5FA;
$text-main: #333333;
$text-sub: #909399;

.dream-container {
	min-height: 100vh;
	background-color: $dream-bg;
	position: relative;
	overflow-x: hidden;
	font-family: -apple-system, sans-serif;
	/* Increase bottom padding to prevent obstruction by the bottom navigation bar, while adapting to the bottom safety area */
	padding-bottom: calc(120rpx + env(safe-area-inset-bottom));
}

/* 1. 梦幻弥散背景 —— 全高铺满容器，避免 1000rpx 处的硬切边 */
.dream-bg {
	position: absolute;
	/* 原来 height: 1000rpx 会在中段出现一条毛玻璃终止的硬边，
	   改成 inset:0 跟着容器 min-height:100vh + 内容高度全程覆盖 */
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 0;
	overflow: hidden;
	pointer-events: none;
	background: $dream-bg;

	/* 毛玻璃漫射层 —— 也全高覆盖；让整个页面统一在"梦幻雾化"基底上，
	   而不是只在顶部 1000rpx 内有效，下方突然回到硬灰底 */
	.glass-overlay {
		position: absolute;
		inset: 0;
		background: rgba(255, 255, 255, 0.4);
		backdrop-filter: blur(30px);
		z-index: 2;
	}

	.blob {
		position: absolute;
		border-radius: 50%;
		filter: blur(80rpx);
		opacity: 0.8;
		z-index: 1;
		animation: floatBlob 10s infinite ease-in-out alternate;
	}

	/* blobs 集中在顶部 hero 区，下方靠 glass-overlay 自然过渡到纯净底色 */
	.blob-1 {
		width: 600rpx;
		height: 600rpx;
		background: var(--user-color-from, #A0C4FF);
		top: -200rpx;
		left: -200rpx;
	}

	.blob-2 {
		width: 500rpx;
		height: 500rpx;
		background: var(--user-color-to, #FFC6FF);
		top: 0;
		right: -100rpx;
		animation-delay: -5s;
	}

	.blob-3 {
		width: 400rpx;
		height: 400rpx;
		background: #BDB2FF;
		top: 300rpx;
		left: 20%;
		opacity: 0.6;
	}
}

/* 2. 头部 */
.dream-header {
	position: fixed;
	width: 100%;
	z-index: 99;

	.header-content {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding-left: 32rpx;
		height: 88rpx;

		.header-left .page-title {
			font-size: 34rpx;
			font-weight: 800;
			color: #333;
			letter-spacing: 2rpx;
		}

		.header-right {
			display: flex;
			align-items: center;

			.icon-btn {
				width: 72rpx;
				height: 72rpx;
				background: rgba(255, 255, 255, 0.6);
				border-radius: 24rpx;
				display: flex;
				justify-content: center;
				align-items: center;
				backdrop-filter: blur(5px);
				border: 1rpx solid rgba(255, 255, 255, 0.8);
				box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.05);
				position: relative;

				.dream-dot {
					position: absolute;
					top: 14rpx;
					right: 14rpx;
					width: 12rpx;
					height: 12rpx;
					background: #FF75C3;
					border-radius: 50%;
				}
			}
		}
	}
}

/* 3. 内容 */
.dream-content {
	position: relative;
	z-index: 10;
	padding: 0 32rpx;
}

/* 通用卡片 */
.dream-card {
	background: rgba(255, 255, 255, 0.65);
	border-radius: 40rpx;
	border: 2rpx solid rgba(255, 255, 255, 1);
	box-shadow: 0 10rpx 30rpx rgba(160, 196, 255, 0.1), inset 0 0 20rpx rgba(255, 255, 255, 0.5);
	margin-bottom: 30rpx;
	backdrop-filter: blur(10px);
	position: relative;
	overflow: hidden;
}

/* A. 用户卡片 */
.user-info-card {
	padding: 40rpx 30rpx 20rpx;

	.level-tag {
		position: absolute;
		top: 0;
		right: 0;
		z-index: 5;
		background: linear-gradient(135deg, #BDB2FF, #FFC6FF);
		padding: 6rpx 24rpx 6rpx 20rpx;
		border-bottom-left-radius: 24rpx;
		border-top-right-radius: 38rpx;

		text {
			font-size: 22rpx;
			color: #fff;
			font-weight: 700;
			letter-spacing: 2rpx;
		}
	}

	.card-glass-shine {
		position: absolute;
		top: -50%;
		left: -50%;
		width: 200%;
		height: 200%;
		background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.4), transparent);
		transform: rotate(30deg);
		pointer-events: none;
	}

	.card-body {
		display: flex;
		align-items: center;
		margin-bottom: 30rpx;

		.avatar-section {
			margin-right: 30rpx;

			.avatar-ring {
				width: 130rpx;
				height: 130rpx;
				border-radius: 50%;
				border: 4rpx solid #fff;
				box-shadow: 0 8rpx 20rpx rgba(160, 196, 255, 0.3);
				background: #EAF4FF;
				display: flex;
				align-items: center;
				justify-content: center;
				overflow: hidden;

				.avatar-empty {
					display: flex;
					align-items: center;
					justify-content: center;
					width: 100%;
					height: 100%;
					background: linear-gradient(135deg, #A0C4FF, #BDB2FF);
				}
			}

		}

		.text-section {
			flex: 1;
			overflow: hidden;

			.nick-box {
				margin-bottom: 8rpx;
				display: flex;
				align-items: center;

				// 修复：昵称截断
				.nickname {
					font-size: 42rpx;
					font-weight: 800;
					color: #333;
					display: block;
					max-width: 340rpx;
					white-space: nowrap;
					overflow: hidden;
					text-overflow: ellipsis;
				}
			}

			.uid-box {
				display: inline-flex;
				align-items: center;
				background: rgba(255, 255, 255, 0.5);
				padding: 6rpx 16rpx;
				border-radius: 30rpx;
				border: 1rpx solid rgba(255, 255, 255, 0.8);

				.uid-num {
					font-size: 22rpx;
					color: #666;
					font-family: 'DIN', sans-serif;
				}
			}
		}

		.check-btn {
			background: #fff;
			border: 2rpx solid #BDB2FF;
			color: #BDB2FF;
			padding: 8rpx 24rpx;
			border-radius: 30rpx;
			font-size: 24rpx;
		}
	}

	/* 头部统计 (收藏/足迹) */
	.header-stats-row {
		display: flex;
		justify-content: space-around;
		padding-top: 20rpx;
		border-top: 2rpx dashed rgba(0, 0, 0, 0.05);

		.h-col {
			text-align: center;

			.n {
				font-size: 32rpx;
				font-weight: bold;
				color: #333;
				margin-right: 8rpx;
			}

			.t {
				font-size: 24rpx;
				color: #888;
			}
		}
	}
}

/* B. 资产卡片 */
.finance-card {
	padding: 30rpx 0;

	.dream-stats {
		display: flex;
		justify-content: space-around;

		.stat-col {
			display: flex;
			flex-direction: column;
			align-items: center;
			flex: 1;

			.num {
				font-size: 36rpx;
				font-weight: 800;
				color: #333;
				margin-bottom: 6rpx;
				font-family: 'DIN Alternate', sans-serif;
			}

			.highlight {
				color: #FF75C3;
			}

			.lbl {
				font-size: 24rpx;
				color: #888;
			}
		}
	}
}

/* C/D. 订单 & 工具 */
.card-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 30rpx 30rpx 20rpx;

	.header-title {
		font-size: 32rpx;
		font-weight: 700;
		color: #333;
	}

	.header-more {
		display: flex;
		align-items: center;
		font-size: 24rpx;
		color: #999;
	}
}

/* Grid */
.dream-grid-box {
	display: flex;
	justify-content: space-between;
	padding: 20rpx 10rpx 40rpx;
	flex-wrap: wrap;

	&.tools-layout {
		justify-content: flex-start;

		.dream-grid-item {
			flex: 0 0 25%;
			margin-bottom: 20rpx;
		}
	}

	.dream-grid-item {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;

		.icon-wrap,
		.tool-icon-box {
			position: relative;
			width: 88rpx;
			height: 88rpx;
			margin-bottom: 12rpx;
			display: flex;
			justify-content: center;
			align-items: center;
		}

		.icon-wrap {
			.main-icon {
				width: 50rpx;
				height: 50rpx;
				z-index: 2;
			}

			.icon-glow {
				position: absolute;
				width: 100%;
				height: 100%;
				border-radius: 30rpx;
				opacity: 0.2;
				z-index: 1;
				filter: blur(10px);
			}

			.glow-blue {
				background: #A0C4FF;
			}

			.glow-purple {
				background: #BDB2FF;
			}

			.glow-pink {
				background: #FFC6FF;
			}

			.glow-orange {
				background: #FFD6A5;
			}

			.glow-green {
				background: #CAFFBF;
			}

			.float-badge {
				position: absolute;
				top: -6rpx;
				right: -6rpx;
				z-index: 3;
				background: #FF75C3;
				color: #fff;
				font-size: 20rpx;
				min-width: 32rpx;
				height: 32rpx;
				line-height: 32rpx;
				text-align: center;
				border-radius: 32rpx;
				border: 2rpx solid #fff;
				box-shadow: 0 4rpx 8rpx rgba(255, 117, 195, 0.4);
			}
		}

		.tool-icon-box {
			background: #F8F9FD;
			border-radius: 30rpx;

			.tool-img {
				width: 44rpx;
				height: 44rpx;
			}
		}

		.item-name {
			font-size: 24rpx;
			color: #555;
		}
	}
}

/* 列表模式 */
.dream-list-layout {
	.list-item {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 24rpx 30rpx;
		border-bottom: 2rpx solid rgba(0, 0, 0, 0.03);

		&:last-child {
			border-bottom: none;
		}

		.left {
			display: flex;
			align-items: center;
			font-size: 28rpx;
			color: #333;

			.icon {
				width: 40rpx;
				height: 40rpx;
				margin-right: 20rpx;
			}
		}
	}
}

/* E. 推荐好物 */
.recommend-box {
	margin-top: 40rpx;

	.dream-title {
		text-align: center;
		margin-bottom: 30rpx;

		.en {
			display: block;
			font-size: 20rpx;
			color: #BDB2FF;
			letter-spacing: 6rpx;
			font-weight: 800;
			margin-bottom: 4rpx;
		}

		.zh {
			font-size: 32rpx;
			font-weight: 700;
			color: #333;
		}
	}

	.goods-wrapper {
		box-sizing: border-box;
		height: 100%;
	}

	.dream-goods-card {
		background: #fff;
		border-radius: 24rpx;
		overflow: hidden;
		box-shadow: 0 8rpx 24rpx rgba(160, 196, 255, 0.1);
	}

	::v-deep .uni-grid-item {
		padding: 0 !important;
	}
}

/* 底部 */
.dream-footer {
	text-align: center;
	padding: 30rpx 0 60rpx;

	text {
		font-size: 22rpx;
		color: #B0B0B0;
		letter-spacing: 2rpx;
	}
}

/* 动画 */
@keyframes floatBlob {
	0% {
		transform: translate(0, 0) scale(1);
	}

	50% {
		transform: translate(30rpx, 50rpx) scale(1.1);
	}

	100% {
		transform: translate(-20rpx, 20rpx) scale(0.9);
	}
}

.click-push {
	transition: transform 0.1s;
}

.click-push:active {
	transform: scale(0.92);
}
</style>