<template>
	<view class="christmas-container" :style="colorVars">

		<!-- 1. 头部:深红夜空(颜色走后台「用户中心」配色)+ 飘雪 + 灯串 + 雪堆 -->
		<view class="xm-hero" :style="'padding-top:' + (top + 44) + 'px'">
			<view class="snow-layer">
				<view v-for="(flake, index) in snowflakes" :key="index" class="snowflake" :style="flake.styleStr"></view>
			</view>

			<!-- 灯串:一根下垂的线 + 一串彩色小灯 -->
			<view class="garland">
				<view class="garland-wire"></view>
				<view v-for="(b, bi) in bulbs" :key="bi" class="bulb" :style="b"></view>
			</view>

			<view class="me" @click="!isLogin ? goToLogin() : ''">
				<view class="av-wrap">
					<view class="avatar-ring">
						<!-- 小程序的 image 不给显式尺寸会塞默认值,这里写死 -->
						<image v-if="isLogin" :src="userInfo.avatar_url" mode="aspectFill"
							@click="$T.go('/others/setting/setting')" class="avatar"
							style="width:152rpx;height:152rpx;border-radius:50%;" />
						<view v-else class="avatar-placeholder">
							<tui-icon name="people" color="#B71C1C" size="34"></tui-icon>
						</view>
					</view>
					<!-- 圣诞帽 -->
					<view class="hat"><view class="hat-cone"></view><view class="hat-brim"></view><view class="hat-ball"></view></view>
				</view>

				<view class="who">
					<!-- 手写体做成图片(和顶栏同一张):安卓微信没有手写字体,文字会退回普通字体 -->
					<image v-if="isLogin" class="hello" :src="xmWord" mode="aspectFit"></image>
					<view class="user-name text-ellipsis">{{ isLogin ? userInfo.nickname : '登录开启圣诞之旅' }}</view>
					<view class="chips" v-if="isLogin">
						<view class="chip" @click.stop="copyId(userInfo.id)">
							<text>ID {{ userInfo.id }}</text>
							<text class="chip-mobile" v-if="formattedPageSetting.header && formattedPageSetting.header.show_mobile == 1 && userInfo.mobile"> · {{ userInfo.mobile }}</text>
						</view>
						<view class="chip chip-lv" v-if="userInfo.level_name">★ {{ userInfo.level_name }}</view>
					</view>
				</view>
			</view>

			<!-- 距圣诞吊牌:每天自动倒数,圣诞当天换成「圣诞快乐」 -->
			<view class="xm-tag" :style="'top:' + (top + 44 + 54) + 'px'">
				<view class="xm-tag-hole"></view>
				<block v-if="xmasDays > 0">
					<view class="xm-tag-k">距圣诞</view>
					<view class="xm-tag-v">{{ xmasDays }}</view>
					<view class="xm-tag-u">天</view>
				</block>
				<view v-else class="xm-tag-today"><view>圣诞</view><view>快乐</view></view>
			</view>

			<!-- 收藏 / 足迹 / 订单数(后台配了才有) -->
			<view class="header-stats-row" v-if="hasHeaderStats">
				<view class="h-stat-item" v-if="formattedPageSetting.header.goods.is_show == 1"
					@click.stop="$T.go('/others/collect/collect')">
					<text class="num">{{ isLogin ? formattedPageSetting.header.goods.value : 0 }}</text>
					<text class="lbl">{{ formattedPageSetting.header.goods.name }}</text>
				</view>
				<view class="h-stat-item" v-if="formattedPageSetting.header.footmark.is_show == 1"
					@click.stop="$T.go('/others/footmark/footmark')">
					<text class="num">{{ isLogin ? formattedPageSetting.header.footmark.value : 0 }}</text>
					<text class="lbl">{{ formattedPageSetting.header.footmark.name }}</text>
				</view>
				<view class="h-stat-item" v-if="formattedPageSetting.header.order.is_show == 1"
					@click.stop="$T.go('/others/order/order')">
					<text class="num">{{ isLogin ? formattedPageSetting.header.order.value : 0 }}</text>
					<text class="lbl">{{ formattedPageSetting.header.order.name }}</text>
				</view>
			</view>

			<!-- 雪堆:三团米白椭圆叠出起伏的雪线,和下面页面底色接上 -->
			<view class="drift">
				<view class="drift-blob drift-a"></view>
				<view class="drift-blob drift-b"></view>
				<view class="drift-blob drift-c"></view>
			</view>
		</view>

		<view class="content-area">

			<!-- B. 资产:礼盒(金丝带 + 蝴蝶结) -->
			<view class="gift"
				v-if="formattedPageSetting && formattedPageSetting.finance && formattedPageSetting.finance.is_show == 1">
				<view class="gift-ribbon"></view>
				<view class="bow"><view class="bow-l"></view><view class="bow-r"></view><view class="bow-knot"></view></view>

				<view class="stat-item click-bounce" v-if="formattedPageSetting.finance.balance.is_show == 1"
					@click="isLogin && goAsset('balance', '/plugins2/withdraw/balance')">
					<text class="stat-num" :style="formattedPageSetting.finance.balance.color ? 'color:' + formattedPageSetting.finance.balance.color : ''">{{ isLogin ? userInfo.money : '0.00' }}</text>
					<text class="stat-label">{{ formattedPageSetting.finance.balance.name }}</text>
				</view>

				<view class="stat-item click-bounce"
					v-if="formattedPageSetting.finance.price && formattedPageSetting.finance.price.is_show == 1"
					@click="isLogin && goAsset('price', '/plugins2/withdraw/price')">
					<text class="stat-num" :style="formattedPageSetting.finance.price.color ? 'color:' + formattedPageSetting.finance.price.color : ''">{{ isLogin ? userInfo.price : '0.00' }}</text>
					<text class="stat-label">{{ formattedPageSetting.finance.price.name }}</text>
				</view>

				<view class="stat-item click-bounce" v-if="formattedPageSetting.finance.integral.is_show == 1"
					@click="isLogin && goAsset('integral', '/plugins2/withdraw/integral')">
					<text class="stat-num" :style="formattedPageSetting.finance.integral.color ? 'color:' + formattedPageSetting.finance.integral.color : ''">{{ isLogin ? userInfo.integral : 0 }}</text>
					<text class="stat-label">{{ formattedPageSetting.finance.integral.name }}</text>
				</view>

				<view class="stat-item click-bounce" v-if="formattedPageSetting.finance.coupon.is_show == 1"
					@tap="goAsset('coupon', '/others/coupon/coupon?status=1')">
					<text class="stat-num" :style="formattedPageSetting.finance.coupon.color ? 'color:' + formattedPageSetting.finance.coupon.color : ''">{{ isLogin ? userInfo.coupon : 0 }}</text>
					<text class="stat-label">{{ formattedPageSetting.finance.coupon.name }}</text>
				</view>
			</view>

			<!-- C. 订单模块 (修复：5个图标强制显示在一行) -->
			<view class="section-card"
				v-if="formattedPageSetting && formattedPageSetting.order && formattedPageSetting.order.is_show != 0">
				<!-- 拐杖糖条纹 -->
				<view class="candy"></view>
				<view class="card-title-row" @click="$T.go('/others/order/order?status=-1')">
					<view class="title-with-holly"><view class="holly"><view class="holly-l"></view><view class="holly-r"></view><view class="holly-berry"></view></view><text class="section-title">{{ formattedPageSetting.order.title }}</text></view>
					<view class="more-link">
						<text>{{ formattedPageSetting.order.sub_title }}</text>
						<tui-icon name="arrowright" size="12" color="#999"></tui-icon>
					</view>
				</view>

				<view class="order-grid">
					<!-- 待付款 -->
					<view class="grid-col click-bounce" v-if="formattedPageSetting.order.status_0.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_0.url)">
						<view class="icon-bubble bg-red">
							<image :src="formattedPageSetting.order.status_0.icon" class="main-icon"></image>
							<view class="badge" v-if="isLogin && formattedPageSetting.order.status_0.num > 0">
								{{ formattedPageSetting.order.status_0.num }}
							</view>
						</view>
						<text class="grid-label">{{ formattedPageSetting.order.status_0.name }}</text>
					</view>

					<!-- 待发货 -->
					<view class="grid-col click-bounce" v-if="formattedPageSetting.order.status_1.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_1.url)">
						<view class="icon-bubble bg-green">
							<image :src="formattedPageSetting.order.status_1.icon" class="main-icon"></image>
							<view class="badge" v-if="isLogin && formattedPageSetting.order.status_1.num > 0">
								{{ formattedPageSetting.order.status_1.num }}
							</view>
						</view>
						<text class="grid-label">{{ formattedPageSetting.order.status_1.name }}</text>
					</view>

					<!-- 待收货 -->
					<view class="grid-col click-bounce" v-if="formattedPageSetting.order.status_2.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_2.url)">
						<view class="icon-bubble bg-gold">
							<image :src="formattedPageSetting.order.status_2.icon" class="main-icon"></image>
							<view class="badge" v-if="isLogin && formattedPageSetting.order.status_2.num > 0">
								{{ formattedPageSetting.order.status_2.num }}
							</view>
						</view>
						<text class="grid-label">{{ formattedPageSetting.order.status_2.name }}</text>
					</view>

					<!-- 待评价 -->
					<view class="grid-col click-bounce" v-if="formattedPageSetting.order.status_3.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_3.url)">
						<view class="icon-bubble bg-silver">
							<image :src="formattedPageSetting.order.status_3.icon" class="main-icon"></image>
							<view class="badge" v-if="isLogin && formattedPageSetting.order.status_3.num > 0">
								{{ formattedPageSetting.order.status_3.num }}
							</view>
						</view>
						<text class="grid-label">{{ formattedPageSetting.order.status_3.name }}</text>
					</view>

					<!-- 售后 -->
					<view class="grid-col click-bounce" v-if="formattedPageSetting.order.status_4.is_show == 1"
						@tap="$T.go(formattedPageSetting.order.status_4.url)">
						<view class="icon-bubble bg-gray">
							<image :src="formattedPageSetting.order.status_4.icon" class="main-icon"></image>
							<view class="badge" v-if="isLogin && formattedPageSetting.order.status_4.num > 0">
								{{ formattedPageSetting.order.status_4.num }}
							</view>
						</view>
						<text class="grid-label">{{ formattedPageSetting.order.status_4.name }}</text>
					</view>
				</view>
			</view>

			<!-- D. 常用工具 (菜单1) -->
			<view class="section-card"
				v-if="formattedPageSetting && formattedPageSetting.menus && formattedPageSetting.menus.is_show == 1">
				<view class="card-title-row">
					<view class="title-with-holly"><view class="holly"><view class="holly-l"></view><view class="holly-r"></view><view class="holly-berry"></view></view><text class="section-title">{{ formattedPageSetting.menus.name }}</text></view>
				</view>

				<!-- 网格模式 (保持4列) -->
				<view class="tools-grid" v-if="formattedPageSetting.menus.type == 0">
					<view class="tool-col click-bounce" v-for="(tool, index) in formattedPageSetting.menus.list"
						:key="index" @click="$T.go(tool.url)">
						<view class="tool-bubble">
							<image :src="tool.icon" class="tool-icon-img"></image>
						</view>
						<text class="grid-label">{{ tool.name }}</text>
					</view>
				</view>
				<!-- 列表模式 -->
				<view class="menu-list" v-else>
					<view class="list-item click-bounce" v-for="(item, index) in formattedPageSetting.menus.list"
						:key="index" @click="$T.go(item.url)">
						<view class="left">
							<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="list-icon"></image>
							<text>{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
						</view>
						<tui-icon name="arrowright" size="16" color="#ccc"></tui-icon>
					</view>
				</view>
			</view>

			<!-- E. 常用工具 (菜单2) -->
			<view class="section-card"
				v-if="formattedPageSetting && formattedPageSetting.menus2 && formattedPageSetting.menus2.is_show == 1">
				<view class="card-title-row">
					<view class="title-with-holly"><view class="holly"><view class="holly-l"></view><view class="holly-r"></view><view class="holly-berry"></view></view><text class="section-title">{{ formattedPageSetting.menus2.name }}</text></view>
				</view>
				<view class="tools-grid" v-if="formattedPageSetting.menus2.type == 0">
					<view class="tool-col click-bounce" v-for="(tool, index) in formattedPageSetting.menus2.list"
						:key="index" @click="$T.go(tool.url)">
						<view class="tool-bubble">
							<image :src="tool.icon" class="tool-icon-img"></image>
						</view>
						<text class="grid-label">{{ tool.name }}</text>
					</view>
				</view>
				<view class="menu-list" v-else>
					<view class="list-item click-bounce" v-for="(item, index) in formattedPageSetting.menus2.list"
						:key="index" @click="$T.go(item.url)">
						<view class="left">
							<image :src="item.icon || '/static/icons/placeholder-icon.png'" class="list-icon"></image>
							<text>{{ item.name && item.name.length > 4 ? item.name.substring(0, 4) + '...' : item.name }}</text>
						</view>
						<tui-icon name="arrowright" size="16" color="#ccc"></tui-icon>
					</view>
				</view>
			</view>

			<!-- F. 推荐好物 -->
			<view class="recommend-section" v-if="list.length">
				<!-- 红丝带标题 -->
				<view class="ribbon-wrap">
					<view class="ribbon">
						<view class="ribbon-tail ribbon-tail-l"></view>
						<text class="ribbon-text">圣诞甄选</text>
						<view class="ribbon-tail ribbon-tail-r"></view>
					</view>
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
				<tui-loadmore v-if="loadding" :index="3" type="primary" color="#E0E0E0"></tui-loadmore>
			</view>

			<!-- 底部版权 -->
			<view class="footer"
				v-if="is_no_more && formattedPageSetting && formattedPageSetting.copyright && formattedPageSetting.copyright.is_show == 1">
				<text>{{ formattedPageSetting.copyright.text }}</text>
			</view>

			<view style="height: 50rpx;"></view>

		</view>

		<!-- 登录弹窗 (复用) -->
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
	name: "user-christmas",
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
			snowflakes: [],
			showProfileModal: false,
		};
	},
	computed: {
		// 金色手写 Merry Christmas 字图(安卓没有手写字体):和顶栏同一张,按地址加载,原因见 user.vue 的 xmWord
		xmWord() {
			return this.$util.getAsset('/static/images/merry-christmas-word.png');
		},
		// MP 端 :style 注 CSS 变量必须用字符串(对象会丢键,见全局 themeStyle 约定),根节点自注入避免跨组件继承失效
		colorVars() {
			const c = (this.pageSetting || {}).colors || {};
			let s = '';
			if (c.header_from) s += '--user-color-from:' + c.header_from + ';';
			if (c.header_to) s += '--user-color-to:' + c.header_to + ';';
			return s;
		},
		formattedPageSetting() {
			let data = this.pageSetting ? JSON.parse(JSON.stringify(this.pageSetting)) : {};
			if (!data.copyright) {
				data.copyright = {
					is_show: 1,
					text: 'Merry Christmas & Happy New Year!'
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
		/** 距今年(过了就明年)圣诞还有几天;圣诞当天为 0(吊牌换成「圣诞快乐」) */
		xmasDays() {
			const now = new Date();
			const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
			let xmas = new Date(now.getFullYear(), 11, 25);
			if (today > xmas) xmas = new Date(now.getFullYear() + 1, 11, 25);
			return Math.round((xmas - today) / 86400000);
		},
		/** 灯串上的小灯:沿下垂的弧线排开,颜色轮换 */
		bulbs() {
			const colors = ['#ffd76a', '#7be0a4', '#ffffff', '#ffd76a', '#7cc6ff'];
			const n = 9;
			const out = [];
			for (let i = 0; i < n; i++) {
				const x = (i + 0.5) / n;                 // 0~1
				const y = 4 * x * (1 - x);               // 抛物线,中间最低
				const c = colors[i % colors.length];
				out.push('left:' + (x * 100).toFixed(1) + '%;top:' + (6 + y * 30).toFixed(0) + 'rpx;background:' + c + ';box-shadow:0 0 14rpx 4rpx ' + c + ';');
			}
			return out;
		},
		hasHeaderStats() {
			if (!this.formattedPageSetting || !this.formattedPageSetting.header) return false;
			const h = this.formattedPageSetting.header;
			return (h.goods && h.goods.is_show == 1) || (h.footmark && h.footmark.is_show == 1);
		}
	},
	created() {
		this.createSnowflakes();
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
			this.createSnowflakes();
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

		createSnowflakes() {
			const list = [];
			for (let i = 0; i < 40; i++) {
				const left = Math.random() * 100;
				const size = Math.random() * 6 + 3;
				const duration = Math.random() * 5 + 5;
				const delay = Math.random() * 5;
				const opacity = Math.random() * 0.6 + 0.2;
				// 数值取一两位小数:随机数原样拼进去每个都带十几位小数,40 片光样式串就 7.8KB,跟着 setData 走
				const styleStr =
					`left:${left.toFixed(1)}%;width:${size.toFixed(1)}px;height:${size.toFixed(1)}px;animation-duration:${duration.toFixed(1)}s;animation-delay:${delay.toFixed(1)}s;opacity:${opacity.toFixed(2)};`;
				list.push({
					styleStr
				});
			}
			this.snowflakes = list;
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
/* 圣诞主题:深红夜空头部(颜色走后台「用户中心」配色 --user-color-from/to)+ 米白页面;装饰全部 CSS 画 */
$cream: #fbf4ea;
$ink: #2a1a14;
$sub: #8c7b70;
$gold: #e8b64c;
$gold-soft: #fff1cf;
$red: #c8161d;
$red-deep: #7d0c12;
$green: #1f6b45;

.christmas-container {
	min-height: 100vh;
	background: $cream;
	position: relative;
	overflow-x: hidden;
	font-family: -apple-system, Helvetica, sans-serif;
	padding-bottom: calc(120rpx + env(safe-area-inset-bottom));
}

/* ===== 头部 ===== */
.xm-hero {
	position: relative;
	overflow: hidden;
	padding-bottom: 210rpx;
	color: #ffffff;
	background: radial-gradient(120% 90% at 30% 0%, var(--user-color-from, #e0262e) 0%, var(--user-color-from, #c8161d) 38%, var(--user-color-to, #7d0c12) 100%);
}

.snow-layer {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	pointer-events: none;
	z-index: 0;

	.snowflake {
		position: absolute;
		top: -20px;
		background: #fff;
		border-radius: 50%;
		filter: blur(1px);
		box-shadow: 0 0 5px rgba(255, 255, 255, 0.8);
		animation: fall linear infinite;
	}
}

@keyframes fall {
	to {
		transform: translateY(110vh) rotate(360deg);
	}
}

/* 灯串 */
.garland {
	position: relative;
	z-index: 1;
	height: 64rpx;
	margin: 0 -20rpx;
}

.garland-wire {
	position: absolute;
	left: 0;
	right: 0;
	top: -40rpx;
	height: 80rpx;
	border-bottom: 3rpx solid rgba(58, 26, 16, 0.5);
	border-radius: 0 0 50% 50%;
}

.bulb {
	position: absolute;
	width: 18rpx;
	height: 26rpx;
	margin-left: -9rpx;
	border-radius: 50% 50% 45% 45%;

	&::before {
		content: "";
		position: absolute;
		top: -6rpx;
		left: 4rpx;
		width: 10rpx;
		height: 8rpx;
		border-radius: 2rpx;
		background: #2d2d2d;
	}
}

/* 头像 + 昵称 */
.me {
	position: relative;
	z-index: 2;
	display: flex;
	align-items: center;
	/* 左边距和下面卡片的左边缘对齐(右边的吊牌也和卡片右边缘对齐) */
	padding: 20rpx 200rpx 0 28rpx;
}

.av-wrap {
	position: relative;
	flex-shrink: 0;
}

/* 头像和右边那一列(Merry Christmas + 名字 + 标签)一样高,上下边缘对齐 */
.avatar-ring {
	width: 164rpx;
	height: 164rpx;
	padding: 6rpx;
	box-sizing: border-box;
	border-radius: 50%;
	background: $gold;
	box-shadow: 0 12rpx 32rpx rgba(0, 0, 0, 0.25);

	.avatar-placeholder {
		width: 152rpx;
		height: 152rpx;
		border-radius: 50%;
		background: #ffffff;
		display: flex;
		align-items: center;
		justify-content: center;
	}
}

.hat {
	position: absolute;
	left: -22rpx;
	top: -34rpx;
	width: 90rpx;
	height: 64rpx;
	/* 头像从 144rpx 放大到 164rpx(1.14 倍):帽子以头像左上角为基点同比放大,戴的位置不变
	   (以帽子中心缩放再平移 (1.14-1)×(帽子中心-头像左上角) ≈ 3rpx,等效于以头像左上角缩放);
	   头像左移和卡片对齐后帽尖贴到屏幕边了,再往右挪 6rpx 留点空 */
	transform: translateX(9rpx) scale(1.14) rotate(-24deg);

	.hat-cone {
		position: absolute;
		left: 14rpx;
		bottom: 12rpx;
		width: 0;
		height: 0;
		border-left: 32rpx solid transparent;
		border-right: 32rpx solid transparent;
		border-bottom: 50rpx solid #e3262f;
		transform: skewX(14deg);
	}

	.hat-brim {
		position: absolute;
		left: 4rpx;
		bottom: 0;
		width: 82rpx;
		height: 18rpx;
		border-radius: 12rpx;
		background: #ffffff;
	}

	.hat-ball {
		position: absolute;
		right: 4rpx;
		top: -4rpx;
		width: 18rpx;
		height: 18rpx;
		border-radius: 50%;
		background: #ffffff;
	}
}

.who {
	flex: 1;
	min-width: 0;
	margin-left: 24rpx;
}

.hello {
	display: block;
	width: 216rpx;
	height: 48rpx;
	margin-left: -4rpx;
}

.user-name {
	margin-top: 8rpx;
	font-size: 42rpx;
	font-weight: 700;
	letter-spacing: 1rpx;
	color: #ffffff;
}

/* ID 和等级固定排一行:名字这一列右边给吊牌让了位,比较窄,折成两行这一列就比头像高出一截、上下对不齐。
   间距和左右内边距收了一点,「精灵长老 Head Elf」这种长度的等级名能放下;再长的等级名省略号收尾 */
.chips {
	margin-top: 14rpx;
	display: flex;
	align-items: center;
}

.chip {
	flex-shrink: 0;
	margin-right: 8rpx;
	padding: 4rpx 12rpx;
	white-space: nowrap;
	border-radius: 20rpx;
	font-size: 21rpx;
	color: #ffffff;
	background: rgba(255, 255, 255, 0.16);
}

.chip-mobile {
	opacity: 0.85;
}

.chip-lv {
	flex-shrink: 1;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	font-weight: 700;
	color: #6b3a00;
	background: linear-gradient(90deg, #f6d27a, $gold);
}

/* 距圣诞吊牌 */
.xm-tag {
	position: absolute;
	right: 36rpx;
	z-index: 2;
	width: 148rpx;
	padding: 16rpx 0 14rpx;
	text-align: center;
	color: $red-deep;
	border-radius: 16rpx 16rpx 20rpx 20rpx;
	background: $gold-soft;
	box-shadow: 0 12rpx 28rpx rgba(0, 0, 0, 0.22);
	transform: rotate(6deg);

	&::before {
		content: "";
		position: absolute;
		top: -44rpx;
		left: 50%;
		width: 2rpx;
		height: 44rpx;
		background: rgba(255, 255, 255, 0.7);
	}
}

.xm-tag-hole {
	width: 14rpx;
	height: 14rpx;
	margin: 0 auto;
	border-radius: 50%;
	background: rgba(125, 12, 18, 0.25);
}

.xm-tag-k {
	margin-top: 8rpx;
	font-size: 19rpx;
	letter-spacing: 2rpx;
}

.xm-tag-v {
	font-size: 52rpx;
	font-weight: 800;
	line-height: 1.05;
	font-family: "DIN Alternate", "Helvetica Neue", sans-serif;
}

.xm-tag-u {
	font-size: 19rpx;
}

.xm-tag-today {
	margin-top: 8rpx;
	font-size: 28rpx;
	font-weight: 800;
	line-height: 1.3;
}

/* 收藏 / 足迹(后台配了才有) */
.header-stats-row {
	position: relative;
	z-index: 2;
	display: flex;
	margin: 32rpx 40rpx 0;

	.h-stat-item {
		margin-right: 56rpx;
		display: flex;
		align-items: baseline;

		.num {
			font-size: 30rpx;
			font-weight: 700;
			margin-right: 8rpx;
		}

		.lbl {
			font-size: 22rpx;
			opacity: 0.85;
		}
	}
}

/* 雪堆 */
.drift {
	position: absolute;
	left: 0;
	right: 0;
	bottom: -2rpx;
	height: 130rpx;
	z-index: 1;

	/* 小程序组件样式不支持标签选择器,用类名 */
	.drift-blob {
		position: absolute;
		bottom: 0;
		background: $cream;
		border-radius: 50% 50% 0 0;
	}

	.drift-a {
		left: -10%;
		width: 70%;
		height: 92rpx;
	}

	.drift-b {
		left: 40%;
		width: 75%;
		height: 120rpx;
	}

	.drift-c {
		left: 22%;
		width: 50%;
		height: 64rpx;
	}
}

/* ===== 内容区 ===== */
.content-area {
	position: relative;
	z-index: 2;
	margin-top: -128rpx;
	padding: 0 28rpx;
}

/* 资产:礼盒 */
.gift {
	position: relative;
	display: flex;
	padding: 36rpx 12rpx 28rpx;
	border-radius: 36rpx;
	background: #ffffff;
	box-shadow: 0 24rpx 52rpx rgba(125, 12, 18, 0.14);
}

.gift-ribbon {
	position: absolute;
	left: 50%;
	top: 0;
	bottom: 0;
	width: 28rpx;
	margin-left: -14rpx;
	background: linear-gradient(90deg, #e3a83c, $gold 50%, #e3a83c);
	opacity: 0.22;
}

.bow {
	position: absolute;
	left: 50%;
	top: -32rpx;
	width: 128rpx;
	height: 60rpx;
	margin-left: -64rpx;
	z-index: 2;

	.bow-l,
	.bow-r {
		position: absolute;
		top: 4rpx;
		width: 60rpx;
		height: 48rpx;
		box-sizing: border-box;
		border: 8rpx solid $gold;
		border-radius: 50%;
		background: #fff6df;
	}

	.bow-l {
		left: 0;
		transform: rotate(-18deg);
	}

	.bow-r {
		right: 0;
		transform: rotate(18deg);
	}

	.bow-knot {
		position: absolute;
		left: 50%;
		top: 18rpx;
		width: 28rpx;
		height: 28rpx;
		margin-left: -14rpx;
		border-radius: 8rpx;
		background: $gold;
	}
}

.stat-item {
	position: relative;
	z-index: 1;
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;

	.stat-num {
		font-size: 40rpx;
		font-weight: 700;
		color: $ink;
		font-family: "DIN Alternate", "Helvetica Neue", sans-serif;
	}

	.stat-label {
		margin-top: 4rpx;
		font-size: 23rpx;
		color: $sub;
	}
}

/* 卡片 */
.section-card {
	position: relative;
	overflow: hidden;
	margin-top: 28rpx;
	padding: 32rpx 28rpx 28rpx;
	border-radius: 36rpx;
	background: #ffffff;

	.card-title-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	.section-title {
		font-size: 32rpx;
		font-weight: 700;
		color: $ink;
	}

	.more-link {
		display: flex;
		align-items: center;
		font-size: 24rpx;
		color: $sub;
	}
}

/* 拐杖糖条纹 */
.candy {
	position: absolute;
	left: 0;
	right: 0;
	top: 0;
	height: 10rpx;
	opacity: 0.85;
	background: repeating-linear-gradient(-45deg, $red 0, $red 16rpx, #ffffff 16rpx, #ffffff 32rpx);
}

/* 冬青叶 */
.title-with-holly {
	display: flex;
	align-items: center;
}

.holly {
	position: relative;
	width: 44rpx;
	height: 32rpx;
	margin-right: 14rpx;

	.holly-l,
	.holly-r {
		position: absolute;
		top: 6rpx;
		width: 24rpx;
		height: 14rpx;
		border-radius: 0 100% 0 100%;
		background: $green;
	}

	.holly-l {
		left: 0;
		transform: rotate(-20deg);
	}

	.holly-r {
		right: 0;
		transform: rotate(20deg) scaleX(-1);
	}

	.holly-berry {
		position: absolute;
		left: 14rpx;
		top: 14rpx;
		width: 12rpx;
		height: 12rpx;
		border-radius: 50%;
		background: $red;
		box-shadow: 8rpx 2rpx 0 $red;
	}
}

/* 订单 */
.order-grid {
	display: flex;
	margin-top: 28rpx;

	.grid-col {
		flex: 1;
		display: flex;
		flex-direction: column;
		align-items: center;
	}

	.icon-bubble {
		position: relative;
		width: 88rpx;
		height: 88rpx;
		border-radius: 28rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		background: linear-gradient(160deg, #fff7ec, #fde8d6);

		.main-icon {
			width: 52rpx;
			height: 52rpx;
		}

		.badge {
			position: absolute;
			top: -10rpx;
			right: -12rpx;
			min-width: 34rpx;
			height: 34rpx;
			padding: 0 8rpx;
			box-sizing: border-box;
			border-radius: 18rpx;
			border: 3rpx solid #ffffff;
			font-size: 20rpx;
			font-weight: 700;
			line-height: 28rpx;
			text-align: center;
			color: #ffffff;
			background: $red;
		}
	}
}

.grid-label {
	margin-top: 12rpx;
	font-size: 24rpx;
	color: #4a3a33;
}

/* 常用工具:圣诞球挂饰 */
.tools-grid {
	display: flex;
	flex-wrap: wrap;
	margin-top: 16rpx;

	.tool-col {
		width: 25%;
		margin-top: 20rpx;
		display: flex;
		flex-direction: column;
		align-items: center;
	}

	.tool-bubble {
		position: relative;
		width: 100rpx;
		height: 100rpx;
		margin-top: 12rpx;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		background: radial-gradient(circle at 32% 28%, #ffffff 0%, #fff4e6 45%, #fbe3cf 100%);
		box-shadow: inset 0 -6rpx 12rpx rgba(200, 22, 29, 0.08), 0 8rpx 20rpx rgba(125, 12, 18, 0.08);

		/* 金色挂扣 */
		&::before {
			content: "";
			position: absolute;
			top: -12rpx;
			left: 50%;
			width: 24rpx;
			height: 14rpx;
			margin-left: -12rpx;
			border-radius: 4rpx 4rpx 2rpx 2rpx;
			background: linear-gradient(90deg, #d39b32, $gold, #d39b32);
		}

		.tool-icon-img {
			width: 60rpx;
			height: 60rpx;
		}
	}

	.grid-label {
		font-size: 23rpx;
	}
}

/* 列表模式菜单 */
.menu-list {
	margin-top: 12rpx;

	.list-item {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 24rpx 8rpx;
		border-bottom: 1px solid #f6efe6;

		&:last-child {
			border-bottom: none;
		}

		.left {
			display: flex;
			align-items: center;
			font-size: 28rpx;
			color: $ink;

			.list-icon {
				width: 40rpx;
				height: 40rpx;
				margin-right: 20rpx;
			}
		}
	}
}

/* 圣诞甄选 */
.recommend-section {
	margin-top: 44rpx;

	.modern-goods-item-wrap {
		height: 100%;
		box-sizing: border-box;
	}

	::v-deep .uni-grid-item {
		padding: 0 !important;
	}
}

.ribbon-wrap {
	display: flex;
	justify-content: center;
	margin-bottom: 28rpx;
}

.ribbon {
	position: relative;
	padding: 14rpx 52rpx;
	background: $red;

	.ribbon-text {
		position: relative;
		z-index: 1;
		font-size: 30rpx;
		font-weight: 700;
		letter-spacing: 4rpx;
		color: #ffffff;
	}

	/* 两侧燕尾:深一号的红,用透明边框剪出缺口 */
	.ribbon-tail {
		position: absolute;
		top: 12rpx;
		width: 0;
		height: 0;
		border-top: 32rpx solid #9e0f16;
		border-bottom: 32rpx solid #9e0f16;
	}

	.ribbon-tail-l {
		left: -28rpx;
		border-left: 16rpx solid transparent;
		border-right: 16rpx solid #9e0f16;
	}

	.ribbon-tail-r {
		right: -28rpx;
		border-right: 16rpx solid transparent;
		border-left: 16rpx solid #9e0f16;
	}
}

.footer {
	text-align: center;
	padding: 30rpx 0 60rpx;

	text {
		font-size: 22rpx;
		color: $sub;
		letter-spacing: 2rpx;
		text-transform: uppercase;
	}
}

.click-bounce {
	transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.click-bounce:active {
	transform: scale(0.92);
	opacity: 0.9;
}
</style>
