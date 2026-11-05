/**
 * 用户中心主题 · 共享逻辑 mixin
 *
 * 把"配色注入 + 数据契约 + 推荐商品 + 导航高度 + 父壳回调"收进一处,新主题组件只需:
 *   import userTheme from '../mixins/userTheme.js'
 *   export default { name: 'user-xxx', mixins: [userTheme] }
 * 模板根节点绑 :style="colorVars" 即可拿到后台配的主题色。
 *
 * ⚠️ 配色为何用字符串:MP(微信小程序)端 :style 注入 CSS 自定义属性必须用字符串,
 *    对象格式会被丢键(与全局 themeStyle mixin 同款约定)。新主题统一走本 mixin,杜绝再写错格式。
 */
import goodsRecommend from '@/mixins/goods-recommend.js';

export default {
	mixins: [goodsRecommend],
	props: {
		userInfo: { type: Object, default: () => ({}) },
		pageSetting: { type: Object, default: () => null },
		isLogin: { type: Boolean, default: false }
	},
	data() {
		return {
			top: 0,
			navRightGap: 0,
			menuColors: [
				'rgba(92, 124, 250, 0.1)', 'rgba(255, 146, 43, 0.1)', 'rgba(32, 201, 151, 0.1)',
				'rgba(250, 82, 82, 0.1)', 'rgba(250, 176, 5, 0.1)', 'rgba(134, 142, 150, 0.1)'
			],
			showProfileModal: false,
		};
	},
	computed: {
		// 根节点绑 :style="colorVars"。字符串格式,MP 安全。
		// 只配了主色时,辅色补成主色 → "色头"渐变退化为纯色块,不会蹦出随机默认色。
		colorVars() {
			const from = this.themeFrom;
			if (!from) return ''; // 没配色 → 不注入,各主题走自身 CSS 默认色
			return '--user-color-from:' + from + ';--user-color-to:' + this.themeTo + ';';
		},
		// 解析后的主色/辅色(JS 用;辅色缺省回退主色)
		themeFrom() {
			return ((this.pageSetting || {}).colors || {}).header_from || '';
		},
		themeTo() {
			const c = (this.pageSetting || {}).colors || {};
			return c.header_to || c.header_from || '';
		},
		formattedPageSetting() {
			let data = this.pageSetting ? JSON.parse(JSON.stringify(this.pageSetting)) : {};
			if (!data.copyright) data.copyright = { is_show: 1, text: '' };
			if (!data.finance) data.finance = { balance: {}, integral: {}, coupon: {}, price: {} };
			return data;
		},
		hasHeaderStats() {
			const h = this.formattedPageSetting && this.formattedPageSetting.header;
			if (!h) return false;
			return (h.goods && h.goods.is_show == 1) || (h.footmark && h.footmark.is_show == 1) || (h.order && h.order.is_show == 1);
		}
	},
	created() {
		this.initNav();
		if (this.isLogin && this.userInfo.is_avatar != 1) this.checkAvatar();
	},
	watch: {
		isLogin(val) {
			if (val && this.userInfo.is_avatar != 1) this.checkAvatar();
		}
	},
	methods: {
		initNav() {
			const sys = uni.getSystemInfoSync();
			this.top = sys.statusBarHeight || 20;
			// #ifdef MP-WEIXIN
			const menuButton = uni.getMenuButtonBoundingClientRect();
			if (menuButton) this.navRightGap = (sys.screenWidth - menuButton.left) + 15;
			// #endif
			// #ifndef MP-WEIXIN
			this.navRightGap = 15;
			// #endif
		},
		goAsset(key, defaultUrl) {
			const f = this.formattedPageSetting && this.formattedPageSetting.finance;
			const cfg = f && f[key];
			this.$T.go(cfg && cfg.url ? cfg.url : defaultUrl);
		},
		onFinanceClick() {
			this.goAsset('balance', '/plugins2/withdraw/balance');
		},
		// 父壳(user.vue)在下拉刷新 / 触底时调用
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

		getMenuColor(index) {
			return this.menuColors[index % this.menuColors.length];
		},
		copyId(id) {
			uni.setClipboardData({
				data: String(id),
				success: () => uni.showToast({ title: 'ID已复制', icon: 'none' })
			});
		},
		goToLogin() {
			this.$T.go('/others/login/login');
		},
		handleSaveSuccess() {
			uni.redirectTo({ url: '/others/user/user' });
		},
		checkAvatar() {
			this.$request({ url: '/api/user/check-avatar' }).then(res => {
				if (res.code == 0 && res.data.is_need_bind == 1) this.showProfileModal = true;
			});
		}
	}
};
