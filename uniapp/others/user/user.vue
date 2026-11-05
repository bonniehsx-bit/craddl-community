<template>
	<tui-page>
		<view class="user-page-container" :style="colorVars">

			<!-- 统一全局导航栏 (固定在最顶层) -->
			<view class="global-nav-bar" :class="{ 'nav-solid': navSolid }" :style="{ paddingTop: top + 'px', paddingRight: navRightGap + 'px' }">
				<view class="nav-content">
					<view class="nav-left">
						<!-- 圣诞风格:滚过头部后顶栏左边浮出金色手写 Merry Christmas + 三颗闪烁的小星星。
						     手写体做成图片:安卓微信没有手写字体,文字会退回普通字体 -->
						<view v-if="currentStyle === 'christmas'" class="xm-nav-title" :class="{ show: navSolid }">
							<view class="xm-spark xm-spark-a"></view>
							<image class="xm-nav-word" :src="xmWord" mode="aspectFit"></image>
							<view class="xm-spark xm-spark-b"></view>
							<view class="xm-spark xm-spark-c"></view>
						</view>
					</view>
					<view class="nav-actions">
						<view class="icon-btn click-bounce" v-if="imEnabled" @click="$T.go('/plugins1/chat/list')">
							<tui-icon name="message" size="24" :color="navColor"></tui-icon>
							<tui-badge :absolute="true" :scaleRatio="0.8" top="-4rpx" right="-4rpx"
								:bgColor="themeColor" color="#ffffff" v-if="unreadCount > 0">{{ unreadCount > 99 ? '99+' : unreadCount }}</tui-badge>
						</view>
						<view class="icon-btn click-bounce" style="margin-left: 20rpx;"
							@click="$T.go('/others/setting/setting')">
							<tui-icon name="setup" size="24" :color="navColor"></tui-icon>
						</view>
					</view>
				</view>
			</view>


			<!-- 
        核心改造点：
        1. 使用 v-if 判断 currentStyle
        2. 使用 :userInfo, :pageSetting, :isLogin 将数据“喂”给子组件
        3. ref="styleComponent" 用于父组件调用子组件的方法(如触底加载)
      -->

			<!-- 1. 极光风格 -->
			<user-aurora v-if="currentStyle === 'aurora'" ref="styleComponent" :userInfo="userInfo"
				:pageSetting="pageSetting" :isLogin="isLogin"></user-aurora>

			<!-- 2. 圣诞风格 -->
			<user-christmas v-if="currentStyle === 'christmas'" ref="styleComponent" :userInfo="userInfo"
				:pageSetting="pageSetting" :isLogin="isLogin"></user-christmas>

			<!-- 3. 高级黑金风格 -->
			<user-premium v-if="currentStyle === 'premium'" ref="styleComponent" :userInfo="userInfo"
				:pageSetting="pageSetting" :isLogin="isLogin"></user-premium>

			<!-- 4. 经典风格 -->
			<user-classic v-if="currentStyle === 'classic'" ref="styleComponent" :userInfo="userInfo"
				:pageSetting="pageSetting" :isLogin="isLogin"></user-classic>

			<!-- 5. 极简风格 -->
			<user-minimalist v-if="currentStyle === 'minimalist'" ref="styleComponent" :userInfo="userInfo"
				:pageSetting="pageSetting" :isLogin="isLogin"></user-minimalist>

			<!-- 6. 银色风格 -->
			<user-silver v-if="currentStyle === 'silver'" ref="styleComponent" :userInfo="userInfo"
				:pageSetting="pageSetting" :isLogin="isLogin"></user-silver>
			<user-dream v-if="currentStyle === 'dream'" ref="styleComponent" :userInfo="userInfo"
				:pageSetting="pageSetting" :isLogin="isLogin"></user-dream>

			<!-- 7. 卡片·色头(新,后台可配主题色) -->
			<user-card v-if="currentStyle === 'card'" ref="styleComponent" :userInfo="userInfo"
				:pageSetting="pageSetting" :isLogin="isLogin"></user-card>

			<!-- 8. 极简·留白(新) -->
			<user-airy v-if="currentStyle === 'airy'" ref="styleComponent" :userInfo="userInfo"
				:pageSetting="pageSetting" :isLogin="isLogin"></user-airy>

			<!-- 9. 暗色·极简(新) -->
			<user-night v-if="currentStyle === 'night'" ref="styleComponent" :userInfo="userInfo"
				:pageSetting="pageSetting" :isLogin="isLogin"></user-night>

			<!-- 悬浮客服：im=在线IM客服(全端) / wechat=微信原生会话(仅小程序) / custom=电话弹窗；H5·App 无原生会话 → im走IM、其余走电话；对所有风格模板统一生效 -->
			<view v-if="serviceEnabled" class="float-service-wrap">
				<!-- 在线IM客服（全端）：tui-cs-wrapper 内部按 IM 配置路由，微信未开IM自动降级原生 -->
				<tui-cs-wrapper v-if="useImService" class="float-service-cs">
					<view class="float-service-btn" hover-class="none">
						<view class="float-service-icon-box">
							<image v-if="serviceIcon" :src="serviceIcon" class="float-service-icon" mode="aspectFit"></image>
							<tui-icon v-else name="service" size="28" :color="themeColor"></tui-icon>
						</view>
						<text v-if="serviceTitle" class="float-service-title">{{ serviceTitle }}</text>
					</view>
				</tui-cs-wrapper>
				<!-- 微信原生客服会话（仅小程序 + wechat 模式） -->
				<!-- #ifdef MP-WEIXIN -->
				<button v-if="!useImService && useWechatContact" class="float-service-btn" open-type="contact" hover-class="none">
					<view class="float-service-icon-box">
						<image v-if="serviceIcon" :src="serviceIcon" class="float-service-icon" mode="aspectFit"></image>
						<tui-icon v-else name="service" size="28" :color="themeColor"></tui-icon>
					</view>
					<text v-if="serviceTitle" class="float-service-title">{{ serviceTitle }}</text>
				</button>
				<!-- #endif -->
				<!-- 电话弹窗（custom 模式 / H5·App 非 im） -->
				<view v-if="!useImService && !useWechatContact" class="float-service-btn" hover-class="none" @click="openCustomerService">
					<view class="float-service-icon-box">
						<image v-if="serviceIcon" :src="serviceIcon" class="float-service-icon" mode="aspectFit"></image>
						<tui-icon v-else name="service" size="28" :color="themeColor"></tui-icon>
					</view>
					<text v-if="serviceTitle" class="float-service-title">{{ serviceTitle }}</text>
				</view>
			</view>
		</view>
	</tui-page>
</template>

<script>
import { mapState } from 'vuex';
// 引入你的所有风格组件
import UserAurora from './user-aurora/user-aurora.vue';
import UserChristmas from './user-christmas/user-christmas.vue';
import UserPremium from './user-premium/user-premium.vue';
import UserClassic from './user-classic/user-classic.vue';
import UserMinimalist from './user-minimalist/user-minimalist.vue';
import UserSilver from './user-silver/user-silver.vue';
import UserDream from './user-dream/user-dream.vue';
import UserCard from './user-card/user-card.vue';
import UserAiry from './user-airy/user-airy.vue';
import UserNight from './user-night/user-night.vue';
export default {
	components: {
		UserAurora,
		UserChristmas,
		UserPremium,
		UserClassic,
		UserMinimalist,
		UserSilver,
		UserDream,
		UserCard,
		UserAiry,
		UserNight
	},
	data() {
		// 1. 【同步读取缓存】防止页面闪烁
		// Vue初始化时直接拿缓存，这样页面一打开就是上次的皮肤和数据
		const cacheUser = uni.getStorageSync('USER') || {};
		const cacheSetting = uni.getStorageSync('USER_CENTER_SETTING') || {};

		// 默认兜底风格，如果缓存里没有 style_type，就默认 aurora
		const initStyle = cacheSetting.style_type || 'aurora';

		// 判断是否登录的简单逻辑 (根据你的业务调整)
		const initIsLogin = !!(cacheUser.nickname && cacheUser.nickname !== '未登录');

		return {
			navSolid: false, // 圣诞风格滚过头部后导航栏铺底色(见 onPageScroll)
			// 导航栏安全高度
			top: 0,
			navRightGap: 0,

			// 核心数据
			currentStyle: initStyle,
			userInfo: cacheUser,
			pageSetting: cacheSetting,
			isLogin: initIsLogin,
			unreadCount: 0,
			imEnabled: false,

			// 锁：防止重复请求
			isApiLoading: false
		}
	},
	computed: {
		...mapState({
			themeColor: state => state.themeColor || '#D71F1F'
		}),
		// 圣诞顶栏的手写字图(安卓微信没有手写字体,做成图片):放后端系统图片目录,按地址加载。
		// 原来是 29KB 的 base64 写在数据里,页面和圣诞主题组件各一份,小程序每次进个人中心都要多传近 60KB
		xmWord() {
			return this.$util.getAsset('/static/images/merry-christmas-word.png');
		},
		navColor() {
			const darkStyles = ['aurora', 'christmas', 'classic', 'premium', 'dream'];
			return darkStyles.includes(this.currentStyle) ? '#fff' : '#333';
		},
		// 将后台配置的色调注入为 CSS 变量，供所有风格子组件通过 var(--user-color-from/to) 读取
		// 不配置时不注入，各组件 fallback 到自身默认色
		colorVars() {
			// MP 端 :style 注 CSS 变量必须用字符串(对象会丢键,见全局 themeStyle 约定)。
			// 注:跨组件继承在 MP 不可靠,真正生效靠各风格子组件在自己根节点再注入一次(见各 user-* 组件)。
			const colors = (this.pageSetting || {}).colors || {};
			let s = '';
			if (colors.header_from) s += '--user-color-from:' + colors.header_from + ';';
			if (colors.header_to) s += '--user-color-to:' + colors.header_to + ';';
			// 圣诞风格滚动后的毛玻璃顶栏:头部起始色(较亮那头)× 55% 不透明,换配色跟着变
			s += '--user-nav-glass:' + this.toRgba(colors.header_from || '#c8161d', 0.55) + ';';
			// 容器自己也铺一层风格底色,补上「原生页面底色」与「风格组件背景」之间的缝。
			// 与 applyPageBg 同源(都读 styleBgColor),不会两边对不上。
			if (this.styleBgColor) s += 'background:' + this.styleBgColor + ';';
			return s;
		},

		/**
		 * 各风格的页面底色 —— 取自每个 user-* 组件根容器的 background。
		 * 改风格组件底色时这里要跟着改,否则进场时会先闪一下旧色再被组件盖掉。
		 * christmas 是径向渐变、setBackgroundColor 只收纯色,所以取渐变中心色
		 * (它在 50% 20%,正是首屏最先看到的那块);中心色后台可配,跟着 colors 走。
		 */
		styleBgColor() {
			const colors = (this.pageSetting || {}).colors || {};
			if (this.currentStyle === 'christmas') {
				return colors.header_from || '#D32F2F';
			}
			const MAP = {
				aurora:     '#F8F9FB',
				premium:    '#121212',
				classic:    '#F7F8FA',
				minimalist: '#F5F6F8',
				silver:     '#F4F5F9',
				dream:      '#F3F5FA',
				card:       '#F5F6F8',
				airy:       '#F6F7F9',
				night:      '#131417'
			};
			return MAP[this.currentStyle] || '';
		},
		// 悬浮客服开关（后台统一配置，存于 page_data.customer_service）
		serviceEnabled() {
			const cs = (this.pageSetting || {}).customer_service || {};
			return cs.is_show == 1;
		},
		// 客服按钮图标（后台可配，留空则用默认 tui-icon 兜底）
		serviceIcon() {
			const cs = (this.pageSetting || {}).customer_service || {};
			return cs.icon || '';
		},
		// 客服标题（后台可配，留空则不显示，展示在图标下方）
		serviceTitle() {
			const cs = (this.pageSetting || {}).customer_service || {};
			return cs.title || '';
		},
		// 是否走微信原生客服会话：仅微信小程序 + 非 custom 且非 im；H5/App 恒 false → 走自定义弹窗
		// 用「排除 custom / im」而非「=== wechat」：老数据 mode 缺省(undefined)时保持原「微信原生」行为不变
		useWechatContact() {
			// #ifdef MP-WEIXIN
			const cs = (this.pageSetting || {}).customer_service || {};
			return cs.mode !== 'custom' && cs.mode !== 'im';
			// #endif
			// eslint-disable-next-line no-unreachable
			return false;
		},
		// 是否走系统 IM 在线客服（全端；mode==='im'，接 tui-cs-wrapper → match-cs，微信未开IM自动降级原生）
		useImService() {
			const cs = (this.pageSetting || {}).customer_service || {};
			return cs.mode === 'im';
		}
	},
	created() {
		this.initNav();
		// 尽早把页面底色改成当前风格的底色 —— created 是能拿到 currentStyle 的最早时机,
		// 越早调,浅灰底露出的时间越短(详见 applyPageBg 注释)
		this.applyPageBg();
		// 监听全局原生IM未读消息变化
		uni.$on('IM_UNREAD_CHANGE', (count) => {
			this.unreadCount = count;
		});
	},
	destroyed() {
		uni.$off('IM_UNREAD_CHANGE');
	},
	onShow() {
		// 2. 每次显示页面时，静默更新数据
		// 这里的 refresh 设为 false，表示不是下拉刷新，不需要重置子组件状态
		this.fetchAllData(false);

		// 3. 获取最新全局未读消息（每次 onShow 重新拉服务器，防止已读后角标不更新）
		if (getApp() && getApp().fetchGlobalUnread) {
			getApp().fetchGlobalUnread();
		} else if (getApp() && getApp().globalData) {
			this.unreadCount = getApp().globalData.globalUnread || 0;
		}
	},

	// 3. 下拉刷新
	onPullDownRefresh() {
		// 传 true，表示这是强制刷新
		this.fetchAllData(true);
	},

	// 4. 触底加载 (转发给子组件)
	// 圣诞风格头部下面是米白底,白色图标滚过头部就看不见了:滚过头部后导航栏铺深红底。
	// 只在跨过分界线时改一次状态,不每次滚动都 setData
	onPageScroll(e) {
		if (this.currentStyle !== 'christmas') return;
		const solid = e.scrollTop > 120;
		if (solid !== this.navSolid) this.navSolid = solid;
	},
	onReachBottom() {
		// 如果子组件有 handleReachBottom 方法，就调用它
		// 通常用于加载更多“猜你喜欢”商品
		if (this.$refs.styleComponent && this.$refs.styleComponent.handleReachBottom) {
			this.$refs.styleComponent.handleReachBottom();
		}
	},

	methods: {
		// #rrggbb / #rgb → rgba();配置的不是 hex(比如已经是 rgb())就原样返回
		toRgba(hex, alpha) {
			const h = String(hex || '').trim().replace('#', '');
			const full = h.length === 3 ? h.split('').map(c => c + c).join('') : h;
			if (!/^[0-9a-fA-F]{6}$/.test(full)) return hex;
			const n = parseInt(full, 16);
			return 'rgba(' + (n >> 16 & 255) + ',' + (n >> 8 & 255) + ',' + (n & 255) + ',' + alpha + ')';
		},
		// 自定义客服弹窗：展示工作时间 + 电话，可一键拨打（H5/App 恒用；小程序 mode=custom 时用）
		openCustomerService() {
			const cs = (this.pageSetting || {}).customer_service || {};
			const phones = (cs.phones || []).filter(Boolean);
			const lines = [];
			if (cs.work_time) lines.push('工作时间：' + cs.work_time);
			if (phones.length) lines.push('客服电话：' + phones.join('、'));
			uni.showModal({
				title: '联系客服',
				content: lines.join('\n') || '暂无客服信息',
				confirmText: phones.length ? '拨打' : '知道了',
				showCancel: phones.length > 0,
				success: (r) => {
					if (!r.confirm || !phones.length) return;
					if (phones.length === 1) {
						uni.makePhoneCall({ phoneNumber: phones[0] });
					} else {
						uni.showActionSheet({
							itemList: phones.map(p => '📞 ' + p),
							success: (res) => uni.makePhoneCall({ phoneNumber: phones[res.tapIndex] }),
							fail: () => {}
						});
					}
				}
			});
		},
		// 初始化导航栏距离
		initNav() {
			const sys = uni.getSystemInfoSync();
			this.top = sys.statusBarHeight || 20;

			// #ifdef MP-WEIXIN
			let menuButton = uni.getMenuButtonBoundingClientRect();
			if (menuButton) {
				this.navRightGap = (sys.screenWidth - menuButton.left) + 15;
			}
			// #endif

			// #ifndef MP-WEIXIN
			this.navRightGap = 15;
			// #endif
		},

		/**
		 * 把页面原生底色刷成当前风格的底色。
		 *
		 * 为什么需要:pages.json 里 backgroundColorTop/Bottom 写死 #F7F7F7,这层底色在
		 * 导航动画一结束就绘制,而风格组件的背景要等 Vue 渲染完才盖上去。
		 * .user-page-container 本身又没有背景(只有 width + min-height),中间这段缝隙
		 * 就直接露出浅灰。浅色系的 7 个风格底色都在 #F3F5FA~#F8F9FB,和 #F7F7F7 几乎
		 * 一样,看不出来;但 christmas(深红渐变)/ premium(#121212)/ night(#131417)
		 * 这三个深色风格会明显闪一片白。
		 *
		 * 用缓存里的 style_type 就能算出底色,不用等接口 —— 这是能提前的部分。
		 */
		applyPageBg() {
			const bg = this.styleBgColor;
			if (!bg) return;
			// H5 端该 API 支持有限,失败不影响功能(容器背景那一层仍会兜住),所以静默失败
			uni.setBackgroundColor({
				backgroundColor: bg,
				backgroundColorTop: bg,
				backgroundColorBottom: bg,
				fail: () => {}
			});
		},

		/**
		 * 获取所有数据 (核心控制方法)
		 * @param {Boolean} isRefresh 是否为下拉刷新/强制刷新
		 */
		async fetchAllData(isRefresh = false) {
			if (this.isApiLoading) return;
			this.isApiLoading = true;

			try {
				// 并行请求：用户信息 + 页面装修配置
				// 使用 Promise.allSettled 即使一个失败另一个也能成功
				const results = await Promise.allSettled([
					this.$request({
						url: "/api/user/index"
					}),
					this.$request({
						url: "/api/user/page-data"
					})
				]);

				const userRes = results[0].status === 'fulfilled' ? results[0].value : null;
				const settingRes = results[1].status === 'fulfilled' ? results[1].value : null;

				// --- 处理用户信息 ---
				if (userRes && userRes.code === 0) {
					this.isLogin = true;
					// 仅在数据真正变化时才赋值:避免每次 onShow 重复赋新引用,触发子组件重渲/闪屏
					if (JSON.stringify(this.userInfo) !== JSON.stringify(userRes.data.user)) {
						this.userInfo = userRes.data.user;
						uni.setStorageSync('USER', userRes.data.user);
					}
				} else {
					// 如果 user 接口报错(如token过期)，视为未登录
					this.isLogin = false;
					// 可以选择清空 userInfo 或者保留之前的残影，这里重置为默认结构防止报错
					this.userInfo = {
						nickname: '未登录',
						avatar_url: '',
						money: 0,
						integral: 0,
						coupon: 0
					};
				}

				// --- 处理页面配置 ---
				if (settingRes && settingRes.code === 0) {
					this.imEnabled = !!settingRes.data.im_enabled;
					const newSetting = settingRes.data.page_data;
					// 装修配置极少变;只有内容真的变了才赋值 → 消除"返回页面配置没变却整树重渲"的闪屏
					if (JSON.stringify(this.pageSetting) !== JSON.stringify(newSetting)) {
						this.pageSetting = newSetting;
						uni.setStorageSync('USER_CENTER_SETTING', newSetting);

						// 【关键】风格变化才切换组件(切换会重挂子组件+重播入场动画，属预期)
						if (newSetting.style_type && newSetting.style_type !== this.currentStyle) {
							this.currentStyle = newSetting.style_type;
						}
						// 风格或配色变了都要重刷原生底色:下次进页面 created 会用缓存算出新底色,
						// 但这一次得当场补上,否则本次停留期间原生底色还是上一个风格的
						this.applyPageBg();
					}
				}

				// 如果是下拉刷新，通知子组件刷新它内部的数据(比如重置商品列表分页、重置雪花动画)
				if (this.$refs.styleComponent) {
					if (isRefresh && this.$refs.styleComponent.refreshLocalData) this.$refs.styleComponent.refreshLocalData();
					else if (this.$refs.styleComponent.getRecommend) this.$refs.styleComponent.getRecommend(true);
				}

			} catch (e) {
				console.error('父组件数据请求异常:', e);
			} finally {
				this.isApiLoading = false;
				// 无论成功失败，都停止下拉刷新动画
				uni.stopPullDownRefresh();
			}
		}
	}
}
</script>

<style lang="scss">
/* 
     设置页面通用底色 
     这个颜色最好设置成一个中性灰，或者跟你所有风格都比较搭的颜色
     防止子组件加载前的一瞬间白屏
  */
page {
	background-color: #F8F9FB;
	min-height: 100vh;
}

.user-page-container {
	width: 100%;
	min-height: 100vh;
}

/* 悬浮客服容器：fixed 定位挪到容器，三种分支(IM/微信原生/电话)共用；
   IM 分支用 tui-cs-wrapper 包按钮 —— 必须让容器尺寸=内容，其内部的原生覆盖按钮(open-type=contact)才生效 */
.float-service-wrap {
	position: fixed;
	right: 28rpx;
	bottom: 200rpx;
	z-index: 9990;
}
/* 悬浮客服按钮（微信小程序原生 open-type=contact，需重置 button 默认样式） */
.float-service-btn {
	padding: 0;
	margin: 0;
	border: none;
	background-color: transparent;
	display: flex;
	flex-direction: column;
	align-items: center;
	line-height: 1;
}

.float-service-btn::after {
	border: none;
}

.float-service-icon-box {
	width: 96rpx;
	height: 96rpx;
	border-radius: 50%;
	background-color: #ffffff;
	box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.16);
	display: flex;
	align-items: center;
	justify-content: center;
}

.float-service-icon {
	width: 56rpx;
	height: 56rpx;
}

.float-service-title {
	margin-top: 8rpx;
	max-width: 150rpx;
	padding: 2rpx 12rpx;
	font-size: 22rpx;
	line-height: 1.4;
	color: #333333;
	text-align: center;
	background-color: #ffffff;
	border-radius: 18rpx;
	box-shadow: 0 4rpx 12rpx rgba(0, 0, 0, 0.12);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

/* 全局悬浮导航栏 */
.global-nav-bar {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	z-index: 999;
	box-sizing: border-box;
	background: transparent;
	transition: background-color 0.25s;

	/* 毛玻璃:半透明主题红 + 背景模糊(安卓老机型不支持模糊时,半透明底也足够衬出白色图标) */
	&.nav-solid {
		background-color: var(--user-nav-glass, rgba(200, 22, 29, 0.55));
		backdrop-filter: blur(20px) saturate(160%);
		-webkit-backdrop-filter: blur(20px) saturate(160%);
		box-shadow: inset 0 -1rpx 0 rgba(255, 255, 255, 0.25), 0 8rpx 24rpx rgba(125, 12, 18, 0.12);
	}
	pointer-events: none;
	/* 让空白处鼠标穿透 */

	.nav-content {
		height: 44px;
		/* 标准导航栏高度 */
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding-left: 30rpx;
		pointer-events: auto;
		/* 恢复内部元素的点击响应 */
	}

	.nav-title {
		font-size: 36rpx;
		font-weight: 800;
		letter-spacing: -1rpx;
		transition: color 0.3s;
	}

	.xm-nav-title {
		position: relative;
		display: flex;
		align-items: center;
		height: 44px;
		padding-left: 6rpx;
		opacity: 0;
		transform: translateY(12rpx);
		transition: opacity 0.35s, transform 0.35s;

		&.show {
			opacity: 1;
			transform: translateY(0);
		}
	}

	/* 宽高写死(字图 440×98):图片没加载出来时 uni 的 image 默认 320px 宽,会把右边按钮挤出屏幕 */
	.xm-nav-word {
		width: 288rpx;
		height: 64rpx;
		/* 毛玻璃底偏浅,金字加一层暗红投影才清楚 */
		filter: drop-shadow(0 2rpx 4rpx rgba(90, 0, 0, 0.55));
	}

	/* 四角小星:两个细长菱形交叉,一闪一闪 */
	.xm-spark {
		position: absolute;
		width: 18rpx;
		height: 18rpx;
		animation: xmTwinkle 1.8s ease-in-out infinite;

		&::before,
		&::after {
			content: "";
			position: absolute;
			left: 50%;
			top: 50%;
			width: 4rpx;
			height: 18rpx;
			margin: -9rpx 0 0 -2rpx;
			border-radius: 2rpx;
			background: #fff3c4;
			box-shadow: 0 0 8rpx rgba(255, 228, 140, 0.9);
		}

		&::after {
			transform: rotate(90deg);
		}
	}

	.xm-spark-a {
		left: -8rpx;
		top: 6rpx;
	}

	.xm-spark-b {
		right: 18rpx;
		top: 2rpx;
		transform: scale(0.75);
		animation-delay: 0.6s;
	}

	.xm-spark-c {
		right: -14rpx;
		bottom: 8rpx;
		transform: scale(0.6);
		animation-delay: 1.2s;
	}

	.nav-actions {
		display: flex;
		align-items: center;

		.icon-btn {
			width: 64rpx;
			height: 64rpx;
			background: rgba(255, 255, 255, 0.15);
			/* 稍微给点玻璃感透明背景衬托图标 */
			backdrop-filter: blur(4rpx);
			border-radius: 50%;
			display: flex;
			justify-content: center;
			align-items: center;
			position: relative;
			transition: background 0.3s;

			&:active {
				opacity: 0.7;
			}

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
@keyframes xmTwinkle {
	0%,
	100% {
		opacity: 0.25;
		transform: scale(0.6) rotate(0deg);
	}

	50% {
		opacity: 1;
		transform: scale(1.1) rotate(45deg);
	}
}
</style>
