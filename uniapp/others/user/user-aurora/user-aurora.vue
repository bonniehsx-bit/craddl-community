<template>
	<view class="aurora-container" :style="colorVars">

		<!-- 1. 极光动态背景 -->
		<view class="aurora-bg">
			<view class="aurora-blob blob-1"></view>
			<view class="aurora-blob blob-2"></view>
			<view class="aurora-blob blob-3"></view>
		</view>

		<!-- 顶部导航已提取到父组件 user.vue -->

		<view class="content-wrapper" :style="{ paddingTop: (top + 44) + 'px' }">

			<!-- 3. 用户卡片 (包含头像、头部统计、资产) -->
			<view class="glass-card">
				<view class="level-tag" v-if="isLogin && userInfo.level_name">
					<text>{{ userInfo.level_name }}</text>
				</view>
				<view class="card-content">
					<!-- 用户信息区 -->
					<view class="user-section" @click="!isLogin ? goToLogin() : ''">
						<view class="avatar-wrap">
							<image v-if="isLogin" :src="userInfo.avatar_url" mode="aspectFill"
								@click="$T.go('/others/setting/setting')"
								style="width:120rpx;height:120rpx;border-radius:50%;" />
							<view v-else class="avatar-placeholder">
								<tui-icon name="people" color="#fff" size="40"></tui-icon>
							</view>
						</view>
						<view class="info-wrap">
							<view class="name-row">
								<!-- 修复：昵称省略 -->
								<text class="nickname">{{ isLogin ? userInfo.nickname : '登录/注册' }}</text>
							</view>
							<view class="id-row" v-if="isLogin" @click.stop="copyId(userInfo.id)">
								<text>ID: {{ userInfo.id }}</text>
								<tui-icon name="copy" size="10" color="#95A5A6" style="margin-left: 6rpx;"></tui-icon>
								<text class="mobile-inline" v-if="isLogin && formattedPageSetting.header && formattedPageSetting.header.show_mobile == 1 && userInfo.mobile" style="margin-left:10rpx;opacity:0.8;font-size:24rpx;">· {{ userInfo.mobile }}</text>
							</view>
							<view class="id-row" v-else>
								<text>点击登录体验完整功能</text>
							</view>
						</view>
					</view>
					<!-- 签到按钮 -->
					<view class="checkin-btn click-push" style="display: none;" v-if="isLogin" @click.stop="$T.go('/plugins/integral/sign')">
						<text>签到</text>
					</view>
				</view>

				<!-- 3.1 头部统计数据 (收藏、足迹、订单数) -->
				<!-- 对应 header 模块配置 -->
				<view class="stats-row" v-if="hasHeaderStats">
					<view class="stat-item click-push"
						v-if="formattedPageSetting.header.goods && formattedPageSetting.header.goods.is_show == 1"
						@click.stop="$T.go('/others/collect/collect')">
						<text class="num">{{ isLogin ? formattedPageSetting.header.goods.value : 0 }}</text>
						<!-- 使用后台配置的名称 -->
						<text class="lbl">{{ formattedPageSetting.header.goods.name }}</text>
					</view>
					<view class="stat-item click-push"
						v-if="formattedPageSetting.header.footmark && formattedPageSetting.header.footmark.is_show == 1"
						@click.stop="$T.go('/others/footmark/footmark')">
						<text class="num">{{ isLogin ? formattedPageSetting.header.footmark.value : 0 }}</text>
						<text class="lbl">{{ formattedPageSetting.header.footmark.name }}</text>
					</view>
					<!-- 可选：显示头部配置的订单数 -->
					<view class="stat-item click-push"
						v-if="formattedPageSetting.header.order && formattedPageSetting.header.order.is_show == 1"
						@click.stop="$T.go('/others/order/order?status=-1')">
						<text class="num">{{ isLogin ? formattedPageSetting.header.order.value : 0 }}</text>
						<text class="lbl">{{ formattedPageSetting.header.order.name }}</text>
					</view>
				</view>

				<!-- 3.2 资产数据 (余额、佣金、积分、优惠券) -->
				<view class="assets-box"
					v-if="formattedPageSetting.finance && formattedPageSetting.finance.is_show == 1">

					<!-- 余额 -->
					<view class="asset-col click-push"
						v-if="formattedPageSetting.finance.balance && formattedPageSetting.finance.balance.is_show == 1"
						@click="isLogin && goAsset('balance', '/plugins2/withdraw/balance')">
						<text class="num" :style="formattedPageSetting.finance.balance.color ? 'color:' + formattedPageSetting.finance.balance.color : ''">{{ isLogin ? userInfo.money : '0.00' }}</text>
						<text class="lbl">{{ formattedPageSetting.finance.balance.name }}</text>
					</view>

					<view class="asset-col click-push"
						v-if="formattedPageSetting.finance.price && formattedPageSetting.finance.price.is_show == 1"
						@click="isLogin && goAsset('price', '/plugins2/withdraw/price')">
						<text class="num highlight" :style="formattedPageSetting.finance.price.color ? 'color:' + formattedPageSetting.finance.price.color : ''">{{ isLogin ? userInfo.price : '0.00' }}</text>
						<text class="lbl">{{ formattedPageSetting.finance.price.name }}</text>
					</view>

					<view class="asset-col click-push"
						v-if="formattedPageSetting.finance.integral && formattedPageSetting.finance.integral.is_show == 1"
						@click="isLogin && goAsset('integral', '/plugins2/withdraw/integral')">
						<text class="num" :style="formattedPageSetting.finance.integral.color ? 'color:' + formattedPageSetting.finance.integral.color : ''">{{ isLogin ? userInfo.integral : 0 }}</text>
						<text class="lbl">{{ formattedPageSetting.finance.integral.name }}</text>
					</view>

					<view class="asset-col click-push"
						v-if="formattedPageSetting.finance.coupon && formattedPageSetting.finance.coupon.is_show == 1"
						@tap="goAsset('coupon', '/others/coupon/coupon?status=1')">
						<text class="num" :style="formattedPageSetting.finance.coupon.color ? 'color:' + formattedPageSetting.finance.coupon.color : ''">{{ isLogin ? userInfo.coupon : 0 }}</text>
						<text class="lbl">{{ formattedPageSetting.finance.coupon.name }}</text>
					</view>
				</view>
			</view>

			<!-- 4. 订单面板 -->
			<view class="panel-card"
				v-if="formattedPageSetting && formattedPageSetting.order && formattedPageSetting.order.is_show != 0">
				<view class="panel-header" @click="$T.go('/others/order/order?status=-1')">
					<!-- 使用配置的标题 -->
					<text class="panel-title">{{ formattedPageSetting.order.title }}</text>
					<view class="panel-more">{{ formattedPageSetting.order.sub_title }} <text class="arr">›</text>
					</view>
				</view>

				<view class="grid-layout">
					<!-- 待付款 -->
					<view class="grid-item click-push" v-if="formattedPageSetting.order.status_0.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_0.url)">
						<view class="icon-container">
							<image :src="formattedPageSetting.order.status_0.icon" class="main-icon"></image>
							<!-- 修复：显示角标 -->
							<view class="badge" v-if="isLogin && formattedPageSetting.order.status_0.num > 0">
								{{ formattedPageSetting.order.status_0.num }}
							</view>
							<!-- 呼吸灯光效 -->
							<view class="icon-breathing-glow"
								:style="{ background: formattedPageSetting.order.status_0.styleColor }"></view>
						</view>
						<!-- 使用配置的名称 -->
						<text class="grid-label">{{ formattedPageSetting.order.status_0.name }}</text>
					</view>

					<!-- 待发货 -->
					<view class="grid-item click-push" v-if="formattedPageSetting.order.status_1.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_1.url)">
						<view class="icon-container">
							<image :src="formattedPageSetting.order.status_1.icon" class="main-icon"></image>
							<view class="badge" v-if="isLogin && formattedPageSetting.order.status_1.num > 0">
								{{ formattedPageSetting.order.status_1.num }}
							</view>
							<view class="icon-breathing-glow"
								:style="{ background: formattedPageSetting.order.status_1.styleColor }"></view>
						</view>
						<text class="grid-label">{{ formattedPageSetting.order.status_1.name }}</text>
					</view>

					<!-- 待收货 -->
					<view class="grid-item click-push" v-if="formattedPageSetting.order.status_2.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_2.url)">
						<view class="icon-container">
							<image :src="formattedPageSetting.order.status_2.icon" class="main-icon"></image>
							<view class="badge" v-if="isLogin && formattedPageSetting.order.status_2.num > 0">
								{{ formattedPageSetting.order.status_2.num }}
							</view>
							<view class="icon-breathing-glow"
								:style="{ background: formattedPageSetting.order.status_2.styleColor }"></view>
						</view>
						<text class="grid-label">{{ formattedPageSetting.order.status_2.name }}</text>
					</view>

					<!-- 待评价 -->
					<view class="grid-item click-push" v-if="formattedPageSetting.order.status_3.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_3.url)">
						<view class="icon-container">
							<image :src="formattedPageSetting.order.status_3.icon" class="main-icon"></image>
							<view class="badge" v-if="isLogin && formattedPageSetting.order.status_3.num > 0">
								{{ formattedPageSetting.order.status_3.num }}
							</view>
							<view class="icon-breathing-glow"
								:style="{ background: formattedPageSetting.order.status_3.styleColor }"></view>
						</view>
						<text class="grid-label">{{ formattedPageSetting.order.status_3.name }}</text>
					</view>

					<!-- 售后 -->
					<view class="grid-item click-push" v-if="formattedPageSetting.order.status_4.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_4.url)">
						<view class="icon-container">
							<image :src="formattedPageSetting.order.status_4.icon" class="main-icon"></image>
							<view class="badge" v-if="isLogin && formattedPageSetting.order.status_4.num > 0">
								{{ formattedPageSetting.order.status_4.num }}
							</view>
							<view class="icon-breathing-glow"
								:style="{ background: formattedPageSetting.order.status_4.styleColor }"></view>
						</view>
						<text class="grid-label">{{ formattedPageSetting.order.status_4.name }}</text>
					</view>
				</view>
			</view>

			<!-- 5. 常用工具 (菜单1) -->
			<view class="panel-card"
				v-if="formattedPageSetting && formattedPageSetting.menus && formattedPageSetting.menus.is_show == 1">
				<view class="panel-header">
					<text class="panel-title">{{ formattedPageSetting.menus.name }}</text>
				</view>

				<!-- Grid模式 -->
				<view class="grid-layout col-4" v-if="formattedPageSetting.menus.type == 0">
					<view :class="['grid-item', item.customClass, 'click-push']"
						v-for="(item, index) in formattedPageSetting.menus.list" :key="index" @click="$T.go(item.url)">
						<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="tool-img"></image>
						<text class="grid-label">{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
					</view>
				</view>
				<!-- List模式 -->
				<view class="list-layout" v-else>
					<view class="list-item click-push" v-for="(item, index) in formattedPageSetting.menus.list"
						:key="index" @click="$T.go(item.url)">
						<view class="left">
							<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="icon"></image>
							<text>{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
						</view>
						<tui-icon name="arrowright" size="16" color="#ccc"></tui-icon>
					</view>
				</view>
			</view>

			<!-- 6. 常用工具2 -->
			<view class="panel-card"
				v-if="formattedPageSetting && formattedPageSetting.menus2 && formattedPageSetting.menus2.is_show == 1">
				<view class="panel-header">
					<text class="panel-title">{{ formattedPageSetting.menus2.name }}</text>
				</view>

				<view class="grid-layout col-4" v-if="formattedPageSetting.menus2.type == 0">
					<view :class="['grid-item', item.customClass, 'click-push']"
						v-for="(item, index) in formattedPageSetting.menus2.list" :key="index" @click="$T.go(item.url)">
						<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="tool-img"></image>
						<text class="grid-label">{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
					</view>
				</view>
				<view class="list-layout" v-else>
					<view class="list-item click-push" v-for="(item, index) in formattedPageSetting.menus2.list"
						:key="index" @click="$T.go(item.url)">
						<view class="left">
							<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="icon"></image>
							<text>{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
						</view>
						<tui-icon name="arrowright" size="16" color="#ccc"></tui-icon>
					</view>
				</view>
			</view>

			<!-- 7. 推荐好物 -->
			<view class="recommend-section" v-if="list.length">
				<view class="panel-header">
					<text class="panel-title">推荐好物</text>
				</view>
				<view class="modern-product-list">
					<uni-grid :column="2" :square="false" :showBorder="false" :highlight="false">
						<block v-for="(item, index) in list" :key="index">
							<uni-grid-item>
								<view class="modern-goods-item-wrap"
									:style="{ padding: index % 2 == 0 ? '0 10rpx 20rpx 0rpx' : '0 0rpx 20rpx 10rpx' }">
									<tui-goods-item :goods="item"></tui-goods-item>
								</view>
							</uni-grid-item>
						</block>
					</uni-grid>
				</view>
				<tui-loadmore v-if="loadding" :index="3" type="primary"></tui-loadmore>
			</view>

			<!-- 8. 版权信息 -->
			<view class="footer-copyright"
				v-if="is_no_more && formattedPageSetting && formattedPageSetting.copyright && formattedPageSetting.copyright.is_show == 1">
				<text>{{ formattedPageSetting.copyright.text }}</text>
			</view>
		</view>

		<tui-user-avatar :visible="showProfileModal" :userData="userInfo" @save="handleSaveSuccess"
			@close="showProfileModal = false" />
	</view>
</template>

<script>
import {
	mapState
} from 'vuex';

	import TuiUserAvatar from '@/components/thorui/tui-user-avatar/tui-user-avatar.vue'
import goodsRecommend from '@/mixins/goods-recommend.js';

export default {
	mixins: [goodsRecommend],
		components: { TuiUserAvatar },
	name: "user-aurora",
	// 1. 接收父组件数据
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
		...mapState('mall', {
			themeColor: state => state.themeColor || '#D71F1F'
		}),
		// 2. 数据装饰层 (处理颜色、默认值)
		formattedPageSetting() {
			if (!this.pageSetting) return null;
			let data = JSON.parse(JSON.stringify(this.pageSetting));

			// 订单呼吸灯颜色映射
			const orderColors = ['#FF9F43', '#54A0FF', '#00D2D3', '#5F27CD', '#FF6B6B'];
			if (data.order) {
				if (data.order.status_0) data.order.status_0.styleColor = orderColors[0];
				if (data.order.status_1) data.order.status_1.styleColor = orderColors[1];
				if (data.order.status_2) data.order.status_2.styleColor = orderColors[2];
				if (data.order.status_3) data.order.status_3.styleColor = orderColors[3];
				if (data.order.status_4) data.order.status_4.styleColor = orderColors[4];
			}

			// 菜单样式类
			const processMenus = (list) => {
				if (list) {
					list.forEach(item => {
						item.customClass = item.course_type ? `type-${item.course_type}` : '';
					});
				}
			}
			if (data.menus) processMenus(data.menus.list);
			if (data.menus2) processMenus(data.menus2.list);

			// 确保 finance 对象完整
			if (!data.finance) data.finance = {
				balance: {},
				integral: {},
				coupon: {},
				price: {}
			};

			return data;
		},
		// 3. 判断是否显示头部统计
		hasHeaderStats() {
			if (!this.formattedPageSetting) return false;
			const h = this.formattedPageSetting.header;
			if (!h) return false;
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
			this.top = sys.statusBarHeight || 20;
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
}
</script>

<style lang="scss" scoped>
/* 变量 */
$bg-color: #F8F9FB;
$card-white: #FFFFFF;
$text-main: #2C3E50;
$text-sub: #95A5A6;

.aurora-container {
	min-height: 100vh;
	background-color: $bg-color;
	position: relative;
	overflow-x: hidden;
	font-family: -apple-system, Helvetica, sans-serif;
	/* 增加底部内边距，防止被底部导航栏遮挡，同时适配底部安全区 */
	padding-bottom: calc(120rpx + env(safe-area-inset-bottom));
}

/* 1. 极光不规则运动背景 —— 全高覆盖整个容器 */
.aurora-bg {
	position: absolute;
	/* 改为 inset:0 让背景跟随 .aurora-container 的 min-height: 100vh 全高铺满，
	   原来写死的 height: 700rpx 只覆盖顶部一截，下方会露出容器灰底色 */
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	overflow: hidden;
	z-index: 0;
	/* 用页面 bg-color 作底色 —— 这样下方的 panel-card（白底）能继续浮起来，
	   不至于和背景融成一片；hero 区由 blobs 主导 */
	background: $bg-color;

	.aurora-blob {
		position: absolute;
		border-radius: 50%;
		filter: blur(80rpx);
		opacity: 0.6;
	}

	/* 顶部主色块（保持原位） */
	.blob-1 {
		width: 500rpx;
		height: 500rpx;
		background: var(--user-color-from, #a18cd1);
		top: -100rpx;
		left: -100rpx;
		animation: auroraMove1 12s infinite ease-in-out alternate;
	}

	.blob-2 {
		width: 400rpx;
		height: 400rpx;
		background: var(--user-color-to, #fad0c4);
		top: 0;
		right: -50rpx;
		animation: auroraMove2 15s infinite ease-in-out alternate;
	}

	/* 中部色块：原本 bottom: 100rpx，现在容器高度变大 → 改成相对 top
	   定位（页面 hero 区下方），保证依然在用户可见的"上半屏"区域 */
	.blob-3 {
		width: 300rpx;
		height: 300rpx;
		background: #a1c4fd;
		top: 480rpx;
		left: 20%;
		opacity: 0.5;
		animation: auroraMove3 10s infinite ease-in-out alternate;
	}

	/* 移除了 .white-mask —— 原本 aurora-bg 是 #fff 时它用来把白色 hero 淡出到 $bg-color，
	   现在 aurora-bg 全高都是 $bg-color，mask 起点终点 RGB 完全相同（都是 248,249,251），
	   实际效果反而变成在 blob-3 上盖一道"硬切"渐变带，导致视觉上的渐变断层。
	   blobs 自带 opacity 0.5-0.6 + blur 80rpx，会自然消散，不需要额外蒙层。 */
}

/* 2. 导航栏 */
.nav-bar {
	position: relative;
	z-index: 5;
	padding-left: 32rpx;
	padding-bottom: 30rpx;
	display: flex;
	justify-content: space-between;
	align-items: center;
	box-sizing: border-box;

	.app-name {
		font-size: 38rpx;
		font-weight: 800;
		color: $text-main;
		letter-spacing: 1rpx;
	}

	.nav-right {
		display: flex;
		gap: 24rpx;

		.glass-icon-btn {
			width: 72rpx;
			height: 72rpx;
			background: rgba(255, 255, 255, 0.4);
			border: 1px solid rgba(255, 255, 255, 0.6);
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			backdrop-filter: blur(10rpx);
			position: relative;

			.dot {
				position: absolute;
				top: 16rpx;
				right: 16rpx;
				width: 12rpx;
				height: 12rpx;
				background: #FF4757;
				border-radius: 50%;
				border: 2rpx solid #fff;
			}
		}
	}
}

.content-wrapper {
	position: relative;
	z-index: 10;
	padding: 0 32rpx 40rpx;
}

/* 3. 玻璃态卡片 */
.glass-card {
	position: relative;
	background: rgba(255, 255, 255, 0.65);
	backdrop-filter: blur(24rpx);
	border: 2rpx solid rgba(255, 255, 255, 0.8);
	box-shadow: 0 20rpx 40rpx rgba(0, 0, 0, 0.04);
	border-radius: 40rpx;
	padding: 40rpx;
	margin-bottom: 30rpx;

	.level-tag {
		position: absolute;
		top: 0;
		right: 0;
		z-index: 5;
		background: linear-gradient(135deg, #2D3436, #636E72);
		padding: 8rpx 24rpx 8rpx 20rpx;
		border-bottom-left-radius: 24rpx;
		border-top-right-radius: 38rpx;

		text {
			font-size: 22rpx;
			color: #F1C40F;
			font-weight: 700;
			letter-spacing: 2rpx;
		}
	}

	.card-content {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 30rpx;

		.user-section {
			display: flex;
			align-items: center;

			.avatar-wrap {
				position: relative;
				margin-right: 24rpx;
				width: 120rpx;
				height: 120rpx;

				.avatar-placeholder {
					width: 120rpx;
					height: 120rpx;
					border-radius: 50%;
					border: 4rpx solid #fff;
					background: rgba(255, 255, 255, 0.5);
					display: flex;
					align-items: center;
					justify-content: center;
				}

			}

			.info-wrap {
				/* 昵称截断 */
				flex: 1;
				min-width: 0;

				.nickname {
					font-size: 38rpx;
					font-weight: bold;
					color: $text-main;
					margin-bottom: 8rpx;
					display: block;
					max-width: 320rpx;
					white-space: nowrap;
					overflow: hidden;
					text-overflow: ellipsis;
				}

				.id-row {
					font-size: 24rpx;
					color: $text-sub;
					display: flex;
					align-items: center;
				}
			}
		}

		.checkin-btn {
			background: #fff;
			padding: 10rpx 30rpx;
			border-radius: 30rpx;
			font-size: 24rpx;
			font-weight: bold;
			color: $text-main;
			box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.05);
		}
	}

	/* 头部统计 */
	.stats-row {
		display: flex;
		justify-content: space-around;
		padding-bottom: 24rpx;

		.stat-item {
			text-align: center;
			min-width: 120rpx;

			.num {
				display: block;
				font-size: 32rpx;
				font-weight: bold;
				color: $text-main;
				font-family: 'DIN Alternate', sans-serif;
			}

			.lbl {
				font-size: 22rpx;
				color: $text-sub;
				margin-top: 4rpx;
			}
		}
	}

	/* 资产栏 */
	.assets-box {
		display: flex;
		justify-content: space-between;
		padding-top: 24rpx;
		border-top: 1px solid rgba(0, 0, 0, 0.05);

		.asset-col {
			flex: 1;
			display: flex;
			flex-direction: column;
			align-items: center;
			position: relative;

			.num {
				font-size: 36rpx;
				font-weight: 800;
				color: $text-main;
				margin-bottom: 6rpx;
				font-family: 'DIN Alternate', sans-serif;
			}

			.lbl {
				font-size: 24rpx;
				color: $text-sub;
			}

			.highlight {
				color: #E67E22;
			}
		}
	}
}

/* 4. 面板 & Grid */
.panel-card {
	background: $card-white;
	border-radius: 32rpx;
	padding: 32rpx 20rpx;
	margin-bottom: 24rpx;
	box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.02);

	.panel-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 0 16rpx 24rpx;

		.panel-title {
			font-size: 32rpx;
			font-weight: 700;
			color: $text-main;
		}

		.panel-more {
			font-size: 24rpx;
			color: $text-sub;

			.arr {
				margin-left: 4rpx;
			}
		}
	}
}

.grid-layout {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-start;
	padding: 10rpx 0;

	&.col-4 .grid-item {
		flex: 0 0 25%;
		width: 25%;
	}

	.grid-item {
		flex: 0 0 20%;
		width: 20%;
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		padding: 16rpx 0;
		position: relative;

		.icon-container {
			position: relative;
			width: 88rpx;
			height: 88rpx;
			display: flex;
			align-items: center;
			justify-content: center;
			margin-bottom: 8rpx;

			.main-icon {
				width: 52rpx;
				height: 52rpx;
				position: relative;
				z-index: 2;
				display: block;
			}

			.icon-breathing-glow {
				position: absolute;
				width: 32rpx;
				height: 32rpx;
				border-radius: 50%;
				bottom: 12rpx;
				opacity: 0.3;
				filter: blur(12rpx);
				z-index: 1;
				animation: glowPulse 3s infinite ease-in-out;
			}

			/* 修复：角标样式 */
			.badge {
				position: absolute;
				top: -6rpx;
				right: -6rpx;
				z-index: 3;
				background: #FF4757;
				color: #fff;
				font-size: 18rpx;
				padding: 0 8rpx;
				min-width: 28rpx;
				height: 28rpx;
				line-height: 28rpx;
				text-align: center;
				border-radius: 28rpx;
				border: 2rpx solid #fff;
			}
		}

		.grid-label {
			font-size: 24rpx;
			color: #555;
			text-align: center;
		}

		.tool-img {
			width: 52rpx;
			height: 52rpx;
			margin-bottom: 12rpx;
			display: block;
		}
	}
}

/* 列表菜单 */
.list-layout {
	.list-item {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 24rpx 16rpx;
		border-bottom: 1px solid #f5f7fa;

		&:last-child {
			border-bottom: none;
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

.recommend-section {
	margin-top: 30rpx;

	.panel-title {
		margin-left: 20rpx;
		margin-bottom: 20rpx;
		display: block;
	}
}

.modern-product-list {
	margin-bottom: 20rpx;
}

.modern-goods-item-wrap {
	box-sizing: border-box;
}

.uni-grid-item {
	padding: 0 !important;
}

/* 动画关键帧 */
@keyframes auroraMove1 {
	0% {
		transform: translate(0, 0) scale(1);
	}

	33% {
		transform: translate(50rpx, 30rpx) scale(1.1);
	}

	66% {
		transform: translate(-20rpx, 50rpx) scale(0.9);
	}

	100% {
		transform: translate(30rpx, -20rpx) scale(1);
	}
}

@keyframes auroraMove2 {
	0% {
		transform: translate(0, 0) scale(1);
	}

	50% {
		transform: translate(-40rpx, 40rpx) scale(1.2);
	}

	100% {
		transform: translate(20rpx, -30rpx) scale(1);
	}
}

@keyframes auroraMove3 {
	0% {
		transform: translate(0, 0);
		opacity: 0.4;
	}

	50% {
		transform: translate(30rpx, -30rpx);
		opacity: 0.6;
	}

	100% {
		transform: translate(-30rpx, 30rpx);
		opacity: 0.4;
	}
}

@keyframes glowPulse {

	0%,
	100% {
		transform: scale(1);
		opacity: 0.3;
	}

	50% {
		transform: scale(1.2);
		opacity: 0.5;
	}
}

/* 辅助类 */
.click-push {
	transition: transform 0.1s;
}

.click-push:active {
	transform: scale(0.95);
	opacity: 0.8;
}

.footer-copyright {
	text-align: center;
	color: #E0E0E0;
	font-size: 20rpx;
	padding: 30rpx 0;
	letter-spacing: 2rpx;
}
</style>