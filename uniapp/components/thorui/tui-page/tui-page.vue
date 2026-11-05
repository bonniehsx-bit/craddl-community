<template>
	<!-- 
		[核心改造 1] 
		在这里绑定 dynamicStyle。
		这样插槽(<slot>)里的所有内容，作为子元素，都能读取到 --theme-color 变量 
	-->
	<view class="tui-page-root" :style="dynamicStyle">

		<!-- [全新优化] 轻量级、无依赖的纯原生自定义导航栏 -->
		<!-- H5 下不渲染自绘标题栏:浏览器/微信自带顶栏已显示 document.title,再画一条就是双标题。
		     标题靠 syncH5Title() 同步进 document.title 由浏览器顶栏显示。 -->
		<view
			v-if="showCustomNav"
			class="tui-custom-nav"
			:style="{ 
				backgroundColor: transparentNav ? 'transparent' : navBarBgColor,
				pointerEvents: transparentNav ? 'none' : 'auto'
			}"
		>
			<!-- 滚动后渐显的实色底(solidOnScroll 开启且有标题时才有):单独一层叠在下面,透明度跟着滚动走 -->
			<view v-if="solidRatio > 0" class="tui-nav-solid" :style="'background-color:' + navBarBgColor + ';opacity:' + solidRatio + ';'"></view>
			<!-- 第一层：状态栏安全撑高区 -->
			<view :style="{ height: statusBarHeight + 'px', width: '100%' }"></view>
			
			<!-- 第二层：真正的标题栏区 -->
			<view class="tui-nav-content" 
				  :style="{ 
					  height: customBarHeight + 'px', 
					  paddingRight: capsuleSafeWidth + 'px', 
					  paddingLeft: capsuleSafeWidth + 'px',
					  pointerEvents: 'auto'
				  }">
				<view class="nav-back-wrapper" v-if="computedShowBack" @tap="navigateBack">
					<view :class="['nav-back-btn-inner', glassMode ? 'circle-glass-btn' : '']">
						<!-- [升级] 抛弃纯CSS画的折线，默认使用 tui-icon，并注入沉浸式玻璃态 -->
						<tui-icon name="arrowleft" :size="24" :color="glassMode ? '#1A1D26' : titleColor" v-if="canGoBack"></tui-icon>
						<tui-icon name="home" :size="22" :color="glassMode ? '#1A1D26' : titleColor" v-else></tui-icon>
					</view>
				</view>
				<text class="tui-nav-title" :style="{ color: titleColor, opacity: transparentNav ? solidRatio : 1 }">{{ title }}</text>
			</view>
		</view>

		<!-- 页面内容插槽 -->
		<!-- 如果处于全透明沉浸式且设为透明，则不填充 paddingTop，让内容直接钻到顶部去 -->
		<view 
			:class="['page-content', is_show_tabbar ? 'with-tabbar' : '']" 
			:style="{ paddingTop: showCustomNav && !transparentNav && navBarBgColor !== 'transparent' ? (navBarHeight + 'px') : '0' }"
		>
			<slot></slot>
		</view>

		<!-- 底部 Tabbar -->
		<!-- 
			[核心改造 2] 
			selectedColor 优先使用全局 themeColor，
			这样你的 Tabbar 选中色也会自动跟随后台配置 
		-->
		<tui-tabbar v-if="is_show_tabbar" :current="currentTabIndex" :selectedColor="themeColor" :tabBar="tabbarList">
		</tui-tabbar>

		<!-- 背景音乐静音按钮(后台没开背景音乐就不显示) -->
		<tui-music-btn></tui-music-btn>

		<!-- 全局广告弹窗 -->
		<tui-alert-adv v-if="showAlertAdv" :value="alertData" @close="showAlertAdv = false"></tui-alert-adv>
	</view>
</template>

<script>
import { mapState, mapMutations } from 'vuex'; // [优化] 引入辅助函数

export default {
	name: "tui-page", // 建议加上 name
	props: {
		// [优化参数] 页面及导航栏标题
		title: {
			type: String,
			default: ''
		},
		// [优化参数] 标题栏背景颜色
		navBarBgColor: {
			type: String,
			default: '#FFFFFF'
		},
		// [优化参数] 标题文字及返回按钮颜色
		titleColor: {
			type: String,
			default: '#333333'
		},
		// [优化参数] 整体页面背景颜色
		pageBgColor: {
			type: String,
			default: '#F7F7F7'
		},
		// [优化参数] 是否强制隐藏/显示返回按钮
		showBackBtn: {
			type: Boolean,
			default: true
		},
		// [优化参数] 导航栏是否全透明（仅保留字和按钮悬浮）
		transparentNav: {
			type: Boolean,
			default: false
		},
		// 沉浸式透明导航滑下去后渐变成实色底 + 标题(navBarBgColor / titleColor)。默认关,不传的页面行为不变。
		// 只在 transparentNav 且有 title 时生效:没标题的页面变实色只会多一条空白底,所以保持透明。
		// 组件拿不到页面滚动,页面要在 onPageScroll 里把 scrollTop 传进来;H5 不画自绘栏,这组参数在 H5 不起作用。
		solidOnScroll: {
			type: Boolean,
			default: false
		},
		// 页面当前滚动距离(px),配合 solidOnScroll
		scrollTop: {
			type: Number,
			default: 0
		},
		// 滚动多少 px 时完全变成实色
		solidDistance: {
			type: Number,
			default: 150
		},
		// [优化参数] 强制开启自定义导航（即时不传 title 也激活 custom-nav 框架）
		useCustomNav: {
			type: Boolean,
			default: false
		},
		// 页面自身的 query 参数，用于 tabbar URL 精确匹配（如 { id: '2' }）
		// 由页面在 onLoad 拿到参数后主动传入，比从 getCurrentPages().options 读取更可靠
		pageQueryParams: {
			type: Object,
			default: null
		}
	},
	data() {
		return {
			alertData: null,
			showAlertAdv: false,
			navBarHeight: 0, // 导航栏占据的总高度
			statusBarHeight: 0, // 系统状态栏高度
			customBarHeight: 44, // [修复] 剥离出的纯标题栏高度
			capsuleSafeWidth: 0, // [优化] 防遮挡：胶囊占用的水平宽度及边界间距
			canGoBack: false, // 路由栈层数判断
			// 本实例所在页面的路由信息（reactive），避免 computed 直接调用 getCurrentPages()
			// tabbarList 变化时 computed 重算，若直接调 getCurrentPages()[-1] 取的是栈顶页面，
			// 当页面栈是 [A, B] 时，A 的 tui-page 重算会错误读到 B 的路由导致忽显忽隐
			_ownRoute: '',
			_ownOptions: {},
		}
	},
	computed: {
		// [优化] 使用 mapState 获取数据，代码更整洁
		...mapState('mall', {
			tabbarConfig: state => state.tabbar,
			themeColor: state => state.themeColor || '#F7931E'
		}),

		// [核心配置] 自动判断返回按钮开关
		computedShowBack() {
			// 如果调用方强制传了 true（默认就是 true），那么不管是不是第一页都显示
			// 如果是分享进来或扫码进来的第一页，点击则返回首页
			return this.showBackBtn;
		},

		// [H5 双标题治理] 是否渲染自绘标题栏。
		// 小程序/App 没有浏览器 chrome，只有自绘这一条，必须渲染。
		// H5 里浏览器/微信自带顶栏已经显示 document.title（关不掉），再画一条就是双标题，
		// 所以 H5 一律不渲染自绘栏 —— 标题改由 syncH5Title() 同步进 document.title 交给浏览器顶栏显示。
		showCustomNav() {
			// #ifdef H5
			return false;
			// #endif
			// #ifndef H5
			return this.title || this.useCustomNav;
			// #endif
		},

		// 实色底的不透明度 0~1;没开 solidOnScroll / 不是透明导航 / 没标题时恒为 0
		solidRatio() {
			if (!this.solidOnScroll || !this.transparentNav || !this.title) return 0;
			const d = this.solidDistance > 0 ? this.solidDistance : 150;
			const r = this.scrollTop / d;
			return r <= 0 ? 0 : (r >= 1 ? 1 : Math.round(r * 100) / 100);
		},
		// 返回按钮用毛玻璃圆钮的时候:透明导航,且实色底还没过半
		glassMode() {
			return this.transparentNav && this.solidRatio < 0.5;
		},

		// [核心改造 3] 生成动态样式对象
		dynamicStyle() {
			return {
				// 注入 CSS 变量
				'--theme-color': this.themeColor,
				// [H5 双标题治理·配套] 自绘标题栏实际占位高度,吐给子页面做吸顶偏移(top: var(--tui-nav-height)):
				// 渲染自绘栏时 = navBarHeight;H5 不渲染 / 沉浸式透明时 = 0(与下方 page-content paddingTop 同一条件)。
				// 页面吸顶元素不要再自算 statusBarHeight+44 —— H5 砍掉自绘栏后那套算法会把 tab 吸在半空。
				'--tui-nav-height': (this.showCustomNav && !this.transparentNav && this.navBarBgColor !== 'transparent' ? this.navBarHeight : 0) + 'px',
				// 保持原有的高度设置
				'min-height': '100vh',
				// 若外部传入背景色则覆盖默认颜色，否则走统一底色
				'background-color': this.pageBgColor
			}
		},

		// --- 判断是否有 Tabbar ---
		hasTabbar() {
			return this.tabbarConfig && this.tabbarConfig.list && this.tabbarConfig.list.length > 0;
		},

		tabbarList() {
			return this.tabbarConfig ? this.tabbarConfig.list : [];
		},
		currentPath() {
			return this.tabbarConfig && this.tabbarConfig.current ? this.tabbarConfig.current : '/pages/index/index';
		},

		// [优化] 计算当前 Tab 索引
		// 使用 _ownRoute/_ownOptions（本实例自己的路由），而非每次调用 getCurrentPages()[-1]
		// 后者在多页面栈场景下会取到错误的栈顶页面，导致 tabbar 忽显忽隐
		currentTabIndex() {
			if (!this.tabbarList || this.tabbarList.length === 0) return -1;
			if (!this._ownRoute) return -1;
			const currentRoute = this._ownRoute;
			let matchedIndex = -1;
			let maxMatchLength = 0;

			// pageQueryParams 由页面主动传入（最可靠），否则降级用 created 时抓取的 _ownOptions
			const currentOptions = this.pageQueryParams || this._ownOptions;
			this.tabbarList.forEach((item, index) => {
				// 兼容处理：有的配置带/有的不带
				let itemUrl = item.url.startsWith('/') ? item.url : '/' + item.url;
				let [itemPath, itemQuery] = itemUrl.split('?');

				// 路径必须前缀匹配
				if (!currentRoute.startsWith(itemPath)) return;

				// 若配置 URL 带 query 参数，当前页面的 options 必须全部命中
				// 例如配置 /pages/diy/diy?id=2，只有 id=2 的页面才显示 tabbar
				if (itemQuery) {
					const itemParams = {};
					itemQuery.split('&').forEach(pair => {
						const [k, v] = pair.split('=');
						if (k) itemParams[k] = v ?? '';
					});
					const allMatch = Object.entries(itemParams).every(([k, v]) => String(currentOptions[k]) === String(v));
					if (!allMatch) return;
				}

				if (itemPath.length > maxMatchLength) {
					maxMatchLength = itemPath.length;
					matchedIndex = index;
				}
			});
			return matchedIndex;
		},

		// [核心优化] 从 data 重构成 computed，实现自动的响应式依赖！
		is_show_tabbar() {
			return this.currentTabIndex !== -1;
		}
	},
	created() {
		// --- 动态计算状态栏与自定义导航栏的适配高度 ---
		let statusBarHeight = 20;
		let customBarHeight = 44; // 默认自定义导航栏高度
		let safeWidth = 0; // 防遮挡的安全宽度
		
		// 获取系统信息中的状态栏高度
		const systemInfo = uni.getSystemInfoSync();
		if (systemInfo.statusBarHeight) {
			statusBarHeight = systemInfo.statusBarHeight;
		}

		// [关键修复]：在 H5 端（网页），浏览器自带的顶部地址栏已经把显示区域顶下来了
		// 所以 H5 端我们绝不能再额外加 status bar 的留白，否则就看着像“双倍”高度！
		// #ifdef H5
		statusBarHeight = 0;
		// #endif

		// 微信小程序和部分支持的小程序：获取胶囊按钮位置
		// #ifndef APP-PLUS || H5 || MP-ALIPAY
		if (typeof uni.getMenuButtonBoundingClientRect === 'function') {
			try {
				let menuButtonInfo = uni.getMenuButtonBoundingClientRect();
				if (menuButtonInfo && menuButtonInfo.top) {
					let margin = menuButtonInfo.top - statusBarHeight;
					if (margin < 0) margin = 4;
					customBarHeight = margin * 2 + menuButtonInfo.height;
					if (customBarHeight < 32) customBarHeight = 44;
					safeWidth = systemInfo.windowWidth - menuButtonInfo.left;
				}
			} catch (e) {}
		}
		// #endif
		
		this.customBarHeight = customBarHeight;
		this.statusBarHeight = statusBarHeight;
		this.navBarHeight = statusBarHeight + customBarHeight;
		this.capsuleSafeWidth = safeWidth;
		
		// 记录本实例所在页面的路由和参数（必须在 created 阶段抓取，此时页面已入栈）
		// 存入 reactive data 供 currentTabIndex computed 使用，避免 computed 直接调 getCurrentPages()
		const pages = getCurrentPages();
		if (pages.length > 0) {
			const p = pages[pages.length - 1];
			this._ownRoute = '/' + p.route;
			this._ownOptions = p.options || {};
		}
		if (pages.length > 1) {
			this.canGoBack = true;
		}
	},
	mounted() {
		// 初始化 Tabbar
		if (!this.tabbarList || this.tabbarList.length === 0) {
			this.getTabbarFromServer();
		}
		this.initAlertAdv();
		// H5 自绘标题栏已砍，把 title 同步进浏览器顶栏（document.title）
		this.syncH5Title();
	},
	watch: {
		// 页面标题动态变化时（如详情页拿到数据后才设 title），H5 顶栏同步跟上
		title() {
			this.syncH5Title();
		}
	},
	methods: {
		...mapMutations('mall', ['SET_TABBAR']), // [优化] 映射 mutation

		// [H5 双标题治理] 把 tui-page 的 title 同步进 document.title，交给浏览器/微信自带顶栏显示。
		// H5 砍掉了自绘标题栏后，页面标题只剩浏览器顶栏这一处；而它读的是 document.title
		// （来自 pages.json 的 navigationBarTitleText），常和页面传入的 title 不一致（如工作台传"商户管理中心"）。
		// 不同步的话，浏览器顶栏会显示错标题。仅 H5 生效，小程序/App 靠自绘栏无需处理。
		syncH5Title() {
			// #ifdef H5
			if (this.title) {
				uni.setNavigationBarTitle({ title: this.title });
			}
			// #endif
		},

		// [优化] 通用返回逻辑：带首页兜底
		navigateBack() {
			if (this.canGoBack) {
				uni.navigateBack({
					delta: 1
				});
			} else {
				// 兜底：如果单层页面且强制显示返回，点返回去商城首页
				uni.switchTab({
					url: '/pages/index/index'
				});
				setTimeout(() => {
					uni.reLaunch({
						url: '/pages/index/index'
					});
				}, 100);
			}
		},

		async getTabbarFromServer() {
			try {
				const res = await this.$request({ url: '/api/mall/tabbar' });
				if (res.code === 0 && res.data.tabbar) {
					this.SET_TABBAR(res.data.tabbar);
					// 由于 Vue 的响应式 computed 设计，如果匹配到当前路由，它将在此刻自动显示！不需要手动赋值了！
				}
			} catch (error) {
				console.error("获取Tabbar失败:", error);
			}
		},

		initAlertAdv() {
			this.$store.dispatch('mall/fetchAlertAdv').then(alert_data => {
				if (!alert_data || alert_data.status != 1) return;

				if (alert_data.show_time == 0) {
					let day = new Date();
					let todayStr = day.getFullYear() + "-" + (day.getMonth() + 1) + "-" + day.getDate();
					let alert_date = uni.getStorageSync('ALERT_DATE');
					if (alert_date !== todayStr) {
						uni.setStorageSync('ALERT_DATE', todayStr);
						this.alertData = alert_data;
						this.showAlertAdv = true;
					}
				} else if (alert_data.show_time == 1) {
					let appGlobal = getApp().globalData || {};
					if (!appGlobal.hasShownAlertSession) {
						appGlobal.hasShownAlertSession = true;
						this.alertData = alert_data;
						this.showAlertAdv = true;

					}
				}
			});
		}
	},
}
</script>

<style scoped>
/* 
       这里不写 scoped 也可以，但为了防止污染全局，建议加上。
       注意：CSS变量是可以穿透 scoped 的，所以 --theme-color 依然会生效。
    */

.tui-page-root {
	width: 100%;
	/* 这里不需要写 height: 100vh，因为 style 绑定里写了 min-height */
	display: flex;
	flex-direction: column;
}

.page-content {
	flex: 1;
	/* 让内容区撑满剩余空间 */
	width: 100%;
	position: relative;
	box-sizing: border-box;
}

.with-tabbar {
	/* 如果你的 tui-tabbar 是 fixed 定位的，这里需要加 padding */
	/* 如果 tui-tabbar 是流式布局（占位的），则不需要 padding */
	/* padding-bottom: 100rpx;  <-- 根据实际情况开启 */
}

/* --- 自定义全原生导航栏样式 --- */
.tui-custom-nav {
	width: 100%;
	position: fixed;
	left: 0;
	top: 0;
	z-index: 50;
	box-sizing: border-box;
	transition: background-color 0.3s;
}

.tui-nav-solid {
	position: absolute;
	left: 0;
	top: 0;
	right: 0;
	bottom: 0;
	box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.06);
	pointer-events: none;
}

.tui-nav-content {
	width: 100%;
	height: 100%; /* 防塌陷 */
	box-sizing: border-box; /* [修正] 重点：因为加了左右 padding 避让胶囊，必须限制宽度为 100% border-box 才不会炸出去 */
	position: relative;
	display: flex;
	align-items: center;
	justify-content: center;
}

.tui-nav-title {
	font-size: 17px;
	font-weight: 500;
	/* #ifdef APP-PLUS */
	font-weight: normal;
	/* #endif */
	max-width: 60%;
	overflow: hidden;
	white-space: nowrap;
	text-overflow: ellipsis;
	transition: opacity 0.3s;
}

/* --- 自定义返回按钮样式 --- */
.nav-back-wrapper {
	position: absolute;
	left: 0;
	bottom: 0;
	height: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 0 16px;
	z-index: 10;
}

.nav-back-btn-inner {
	display: flex;
	align-items: center;
	justify-content: center;
}

/* 沉浸式透明头专属毛玻璃悬浮圆环按钮 (致敬活动中心风格) */
.circle-glass-btn {
	width: 64rpx;
	height: 64rpx;
	background: rgba(255, 255, 255, 0.7);
	backdrop-filter: blur(12px);
	-webkit-backdrop-filter: blur(12px);
	border: 1rpx solid rgba(255, 255, 255, 0.8);
	border-radius: 50%;
	box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.08);
	transition: all 0.3s ease;
}

.circle-glass-btn:active {
	transform: scale(0.92);
	background: rgba(240, 240, 240, 0.9);
}

</style>