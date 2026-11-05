// mixins/pageMixin.js
import Vue from 'vue';
import {
	mapState
} from 'vuex';
import { parseNoun } from '@/utils/nouns.js';

// 商城配置 / 底栏配置 / 窗口信息挂在原型上(只读),不放进下面的 computed。
// 小程序每次渲染都会把组件的全部 data + computed 深拷贝后 setData 给视图层,放在全局混入的 computed 里,
// 等于每个组件都多带一份整份商城配置(约 6KB):首页 29 个组件光这一项就 170KB+,每个页面都一样。
// 脚本里照旧 this.$setting / this.$tabbar / this.window;模板里要直接用的,页面自己声明 computed(小程序模板只认 data/computed)。
Object.defineProperties(Vue.prototype, {
	$setting: {
		configurable: true,
		get() { return this.$store.state.mall.setting; }
	},
	$tabbar: {
		configurable: true,
		get() { return this.$store.state.mall.tabbar; }
	},
	// 窗口系统信息:第一次读时取一次记在实例上(原来的 computed 也只取一次)
	window: {
		configurable: true,
		get() {
			if (!this.__windowInfo) {
				// #ifndef MP-TOUTIAO
				this.__windowInfo = uni.getWindowInfo ? uni.getWindowInfo() : uni.getSystemInfoSync();
				// #endif
				// #ifdef MP-TOUTIAO
				this.__windowInfo = uni.getSystemInfoSync();
				// #endif
			}
			return this.__windowInfo;
		}
	}
});

export default {
	data() {
		return {
			// 页面接收的参数（包含路由参数和 scene 解析后的参数）
			$params: null,
		}
	},

	computed: {
		// ==========================================
		// 1. Vuex 状态映射
		// ==========================================
		// $setting / $tabbar 在文件顶部挂到原型上了,见那里的说明
		...mapState('mall', {
			// [核心] 全局主题色，默认使用 Craddl 品牌橙
			themeColor: state => state.themeColor || '#F7931E'
		}),

		// ==========================================
		// 2. 主题色动态样式系统
		// ==========================================

		/**
		 * [核心] 主题色 RGB 分量（用于动态生成 rgba）
		 */
		themeColorRgb() {
			const hex = (this.themeColor || '').replace('#', '');
			let c = hex;
			if (c.length === 3) c = c[0]+c[0]+c[1]+c[1]+c[2]+c[2];
			const r = parseInt(c.substring(0,2), 16) || 0;
			const g = parseInt(c.substring(2,4), 16) || 0;
			const b = parseInt(c.substring(4,6), 16) || 0;
			return `${r},${g},${b}`;
		},

		/**
		 * [核心] CSS变量注入（字符串格式，兼容小程序）
		 * 用法：在页面根节点绑定 <view :style="themeStyle">
		 * 注入变量：
		 *   --theme-color: 主题色
		 *   --theme-color-light: 主题色 70% 透明度（用于渐变终点）
		 *   --theme-shadow: 主题色 25% 透明度（用于阴影）
		 *   --theme-color-bg: 主题色 10% 透明度（用于浅色背景）
		 */
		themeStyle() {
			const c = this.themeColor;
			const rgb = this.themeColorRgb;
			return `--theme-color:${c};--theme-color-light:rgba(${rgb},0.7);--theme-shadow:rgba(${rgb},0.25);--theme-color-bg:rgba(${rgb},0.1)`;
		},

		/**
		 * 主题色的浅色版本 (透明度 10%)
		 */
		themeColorLight() {
			return this.hexToRgba(this.themeColor, 0.1);
		},

		// ==========================================
		// 3. 界面与布局辅助(窗口信息 this.window 在文件顶部挂到原型上了)
		// ==========================================

		/**
		 * [优化] 计算自定义 Tabbar 的占位高度
		 * 如果当前页面是 Tabbar 页面，返回 "100rpx + 底部安全区"
		 * 否则返回 "0px"
		 */
		tabbarHeight() {
			// 1. 基础校验
			if (!this.$tabbar || !this.$tabbar.list || this.$tabbar.list.length === 0) {
				return '0px';
			}

			// 2. 获取当前页面路径
			const pages = getCurrentPages();
			if (pages.length === 0) return '0px';

			let currentRoute = pages[pages.length - 1].route;
			// 修正路径格式，确保以 / 开头
			if (!currentRoute.startsWith('/')) {
				currentRoute = '/' + currentRoute;
			}

			// 3. 比对是否为 Tabbar 页面
			const isTabPage = this.$tabbar.list.some(item => {
				// 兼容处理：配置项可能带/也可能不带/
				let itemUrl = item.url.startsWith('/') ? item.url : '/' + item.url;
				// 忽略参数进行比对
				return currentRoute.split('?')[0] === itemUrl.split('?')[0];
			});

			// 4. 返回 CSS 高度字符串
			return isTabPage ? 'calc(100rpx + env(safe-area-inset-bottom))' : '0px';
		},
	},

	onLoad(options) {
		// 1. 参数解析 (兼容小程序扫码 scene 场景值)
		if (options.scene) {
			const util = this.$util || uni.$util; // 防止未挂载报错
			if (util && util.getScene) {
				// 解析 scene 字符串为对象
				const sceneParams = util.getScene(options.scene);
				options = {
					...options,
					...sceneParams
				};
			}
		}

		// 保存参数到 data，便于页面内部使用
		this.$params = options;

		// 2. 绑定推荐人 PID (如果链接里带了)
		// 优先从 options 获取，其次从全局存储或逻辑中获取
		if (this.$params.pid && this.$http && this.$http.setPid) {
			this.$http.setPid(this.$params.pid);
		}

		// 3. H5 微信 SDK 分享初始化
		//#ifdef H5
		if (this.$wechatSdk && this.$wechatSdk.isWechat && this.$wechatSdk.isWechat()) {
			// 延迟一点执行，确保 SDK 加载完成
			setTimeout(() => {
				this.$wechatSdk.share();
			}, 200);
		}
		//#endif
	},

	// ==========================================
	// 4. 小程序分享逻辑 (好友 & 朋友圈)
	// ==========================================

	/**
	 * 分享给好友
	 */
	onShareAppMessage() {
		return this._getShareConfig();
	},

	/**
	 * [新增] 分享到朋友圈
	 */
	onShareTimeline() {
		return this._getShareConfig();
	},

	methods: {
		/**
		 * 品牌词库读取 —— 模板 {{ $noun('balance') }}，JS this.$noun('balance')
		 * 实现见 utils/nouns.js；读 vuex state，商城配置变化时模板自动刷新。
		 * 复合词原子拼接：{{ $noun('integral') }}明细
		 */
		$noun(key) {
			return parseNoun(key);
		},

		/**
		 * [内部] 生成统一的分享配置对象
		 */
		_getShareConfig() {
			const pages = getCurrentPages();
			const currentPage = pages[pages.length - 1];
			let path = currentPage.route;

			// 补全路径前缀
			if (!path.startsWith('/')) {
				path = '/' + path;
			}

			// 1. 获取当前用户 ID 作为 PID (确保分享带上推荐关系)
			const user = uni.getStorageSync('USER');
			let query = '';

			// 如果已有参数，保留原参数（这里简单处理，实际可能需要解析 options）
			if (this.$params) {
				const paramsArr = [];
				for (let key in this.$params) {
					if (key !== 'pid') { // 避免 pid 重复
						paramsArr.push(`${key}=${this.$params[key]}`);
					}
				}
				if (paramsArr.length > 0) query = paramsArr.join('&');
			}

			// 追加 PID
			if (user && user.id) {
				query += (query ? '&' : '') + `pid=${user.id}`;
			}

			// 拼接完整路径
			const fullPath = query ? `${path}?${query}` : path;

			// 2. 标题/图各走一条兜底链,专用分享配置优先,商城基础信息垫底。
			//    原来兜底写死 'Craddl 商城' —— 客户站点没配 share_title 时,
			//    分享出去顶着别人家的名字,这是最容易被投诉的一处。
			const st = this.$setting || {};
			const pick = (...vals) => {
				for (const v of vals) {
					const t = (v === undefined || v === null) ? '' : String(v).trim();
					if (t) return t;
				}
				return '';
			};
			const title = pick(st.share_title, st.name, 'Craddl 商城');
			// 分享图:配了就用,没配才留空退回页面截图。
			// 截图常常截到半截内容(加载中的骨架屏、弹窗遮罩),分享出去就是"看着怪";
			// 但商品详情这类页面截图其实比 LOGO 更合适,所以不强制覆盖,交给后台配。
			// ⚠ 小程序分享卡片是 5:4,方形 LOGO 会被裁上下,所以这里只认专用分享图,不回退 LOGO。
			const imageUrl = pick(st.share_pic);

			// console.log('自动生成分享配置:', { title, path: fullPath });

			return {
				title: title,
				path: fullPath,
				query: query, // 朋友圈分享使用的是 query 字段
				imageUrl: imageUrl // 留空则使用当前页面截图
			};
		},

		/**
		 * [核心] Hex 颜色转 RGBA
		 * @param {String} hex 例如 #FF0000
		 * @param {Number} opacity 透明度 0-1
		 */
		hexToRgba(hex, opacity) {
			// 1. 基础空值校验
			if (!hex) return '';

			// 2. 数据清洗：转字符串、去两端空格、转小写
			let color = String(hex).trim().toLowerCase();

			// 3. 正则校验：允许带或不带 # 号，支持 3位 或 6位
			const reg = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/;

			if (reg.test(color)) {
				// 去掉 # 号
				if (color.indexOf('#') === 0) {
					color = color.slice(1);
				}

				// 处理 3位 简写的情况 (例如: f00 => ff0000)
				if (color.length === 3) {
					color = color.split('').map(char => char + char).join('');
				}

				// 4. 解析 RGB 数值 (使用 parseInt 指定 16 进制解析)
				const r = parseInt(color.substring(0, 2), 16);
				const g = parseInt(color.substring(2, 4), 16);
				const b = parseInt(color.substring(4, 6), 16);

				// 5. 返回 rgba 字符串
				return `rgba(${r}, ${g}, ${b}, ${opacity})`;
			}

			// 如果不符合 Hex 格式（可能是已经传了 rgba 或 red 等），直接返回原值
			return hex;
		},

		/**
		 * 快捷获取配置
		 */
		$getSetting(key) {
			if (this.$setting && key) {
				return this.$setting[key] || null;
			}
			return this.$setting;
		},

		/**
		 * 快捷处理图片资源路径
		 */
		$getSysImage(url) {
			if (this.$util && this.$util.getAsset) {
				return this.$util.getAsset(url);
			}
			return url;
		}
	}
}