<script>
import config from "./config.json";
import bgMusic from '@/utils/bg-music.js';
import store from '@/store/index.js';
import http from '@/utils/request.js';
import wxAuth from '@/utils/wxAuth.js';
// #ifdef H5 || MP-WEIXIN
import { registerLegacyFontLoader, retryUsedLegacyFonts } from '@/utils/legacy-icon-fonts.js';
// #endif

export default {
	globalData: {
		// 系统信息
		version: '',
		// 后端域名：H5 用当前访问域名（与后端同域，天然多租户）；MP/App 用 config（占位符上传时替换为真域名）
		// #ifdef H5
		domain: window.location.origin,
		apiroot: window.location.origin + '/index.php?r=',
		// #endif
		// #ifndef H5
		domain: config.apiroot,
		apiroot: config.apiroot + '/index.php?r=',
		// #endif

		// --- IM 核心数据 ---
		socketTask: null, // WebSocket 任务对象
		imConfig: null, // PHP 下发的配置
		imToken: '', // 核心鉴权 Token
		isConnected: false, // 连接状态标记
		heartbeatTimer: null, // 心跳定时器
		reconnectTimer: null, // 重连定时器
		lockReconnect: false, // 锁定重连（防止重复触发）
		reconnectAttempts: 0, // 记录重连次数
		globalUnread: 0, // 全局未读数

		// 本次启动那一轮商城配置刷新的 Promise。
		// 插件存在性判断(hasPlugin)必须等它落地 —— 见 hasPlugin 的说明。
		mallConfigReady: null
	},

	onLaunch: function () {
		// #ifdef H5 || MP-WEIXIN
		registerLegacyFontLoader(options => uni.loadFontFace(options));
		// #endif
		console.log('App Launch: 应用启动');

		// 0. 微信 H5：静默确保 openid 就位（供支付/推送；按 mall_id 缓存，切号自愈）
		//    非 H5 / 非微信环境为 no-op；可能在此触发一次 snsapi_base 静默跳转
		wxAuth.ensure();

		// 隐私授权处理（微信小程序 __usePrivacyCheck__=true 时必须注册，否则蓝牙等隐私 API 报错）
		// #ifdef MP-WEIXIN
		wx.onNeedPrivacyAuthorization(resolve => {
			uni.showModal({
				title: '用户隐私保护提示',
				content: '我们需要使用您的蓝牙、摄像头等权限来提供完整服务，请查阅《隐私政策》后决定是否授权。',
				confirmText: '同意',
				cancelText: '拒绝',
				success(res) {
					if (res.confirm) {
						resolve({ event: 'agree', buttonId: 'agree-btn' });
					} else {
						resolve({ event: 'disagree' });
					}
				}
			});
		});
		// #endif

		// 1. 初始化系统配置
		const systemInfo = uni.getSystemInfoSync();
		store.dispatch('app/setSystemInfo', systemInfo);

		// 2. 尝试获取 Token
		const token = uni.getStorageSync('ACCESS_TOKEN');
		if (token) {
			store.dispatch('user/setToken', token);
		}

		// 3. 加载商城配置 & 启动 IM
		//
		// 句柄要留给 hasPlugin 等 —— onShow 与 onLaunch 是同一个 tick,
		// 位置上报那条路会在配置回来之前就跑到插件判断那一步。
		// .catch 是必须的:配置请求失败时这个 Promise 会 reject,没人接就是
		// 一条 unhandled rejection(小程序会打红),而 hasPlugin 自己也有兜底。
		this.globalData.mallConfigReady = store.dispatch('mall/fetchMallConfig', true)
			.then(res => {
				// 获取 IM 配置 (包含 ws_url 和 im_token)
				this.loadImPluginConfig();
				// 背景音乐(后台「商城设置 → 背景音乐」),开了就进小程序循环播放
				bgMusic.init(res && res.setting);
				return res;
			})
			.catch(() => null);
		// 【新增】监听登录成功事件
		uni.$on('LOGIN_SUCCESS', () => {
			console.log('App: Login Success, Reload IM Config');
			this.loadImPluginConfig();
		});
		// 【新增】监听登出事件
		uni.$on('LOGOUT_SUCCESS', () => {
			console.log('App: Logout Success, Close Socket');
			this.closeSocket();
			this.globalData.imConfig = null;
			this.globalData.imToken = '';
			this.stopHeartbeat();
			this.globalData.globalUnread = 0; // 清空未读数
			this.updateTabBarBadge(); // 更新TabBar
		});
	},

	onShow: function () {
		// #ifdef H5 || MP-WEIXIN
		retryUsedLegacyFonts();
		// #endif
		console.log('App Show: 应用切回前台');
		bgMusic.resume();
		// 如果配置已存在但连接断开，尝试重连
		if (this.globalData.imConfig && !this.globalData.isConnected) {
			this.reconnect();
		}
		// #ifdef MP-WEIXIN
		this.reportPlazaBcLocation();
		// #endif
	},

	onHide: function () {
		console.log('App Hide: 应用切后台');
		bgMusic.pause();
		// 可选：后台是否断开连接？通常保持连接直到被系统杀掉
	},

	methods: {
		/**
		 * 本商城有没有授权某个插件。
		 *
		 * 主框架里有几处会直接去调插件接口(plaza 的位置上报、im 的配置)。而插件是
		 * **按商城授权**的,没授权的客户请求 `plugin/xxx/...` 会被后端当路由解析不到,
		 * 抛 404;utils/request.js 对 4xx 一律弹「服务错误(404)」——
		 * 于是这类客户每次启动/切前台都被弹一次错,而这功能他本来就用不上。
		 *
		 * 清单跟着商城配置一起下发(setting.plugins),不额外发请求。
		 *
		 * ⛔ 判不出来时一律返回 true(照调),不能返回 false:
		 *   拿不到配置只说明「这一刻不知道」,不代表插件不存在。宁可多弹一次错,
		 *   也不能把人家已经在用的功能静默关掉 —— 那种故障没有任何报错可查。
		 *   老后端不下发 plugins 字段时走的也是这条路,保持向后兼容。
		 */
		async hasPlugin(name) {
			try {
				// ⛔ 不能只判「store 里有没有值」就直接读。
				//   state.setting 的初值来自 uni.getStorageSync('MALL_SETTING'),
				//   老用户升级后第一次打开时它**一开始就是非空的** —— 里面是上个版本的
				//   配置,没有 plugins 字段。那样会跳过等待、判成「无从判断」而照调,
				//   升级后的第一次启动照样弹一次 404。
				//   所以这里等的是【本次启动那一轮强制刷新】,不是「有没有缓存」。
				if (this.globalData.mallConfigReady) {
					await this.globalData.mallConfigReady;
				}
				let setting = store.state.mall.setting;
				if (!setting) {
					const data = await store.dispatch('mall/fetchMallConfig');
					setting = (data && data.setting) || store.state.mall.setting;
				}
				const list = setting && setting.plugins;
				if (!Array.isArray(list)) return true;
				return list.indexOf(name) !== -1;
			} catch (e) {
				return true;
			}
		},

		/**
		 * 「商圈会员快速积分」插件的地理位置上报。
		 *
		 * 微信插件文档要求**必须**在 app.js 的 onShow 里调 getLocation,所以这段位于主包全局,
		 * 是整个插件接入里唯一跑出授权页的代码。而插件声明是**上传小程序时按客户注入**的
		 * (没申请到插件授权的客户,app.json 里根本没有 plugins),因此这里必须双保险:
		 *   ① 先问后端该商城开没开通,没开通直接不碰插件;
		 *   ② requirePlugin 用 try/catch 兜住 —— 万一开关与实际注入不一致(例如后台刚打开
		 *      开关但代码包还没重传),也只是这一个方法失败,绝不能拖垮 App 启动。
		 *
		 * ⛔⛔ 位置**每次 onShow 都要报**,不能"报过一次就不报了"。
		 *   微信靠这个信号判断顾客此刻在不在场内 —— 不在场内的消费,它不会推「场内支付结果通知」,
		 *   于是无感积分整条链一分钱都走不通。而人的位置天然是会变的,最典型的一条路径:
		 *       在家/路上打开小程序 → 上报了"不在商场" → 到了商场切回小程序(onShow)
		 *       → 如果这里跳过,微信记的还是"他在家" → 消费不算场内 → 永远收不到支付通知
		 *   文档把调用点钉死在 onShow 而不是 onLaunch,就是这个原因。
		 *   (原实现有个 `_bcReported` 只报一次的门,正是上面这条路径的成因,已删除。)
		 *
		 * ⛔ 还要判 `return_code` —— 文档:`res = {return_code, return_msg}`,**0 才是成功**。
		 *   Promise resolve ≠ 业务成功:用户拒绝位置授权、隐私协议未同意、插件没初始化,
		 *   都会正常 resolve 但 return_code≠0。把这些当成功,现场就是"代码明明调了、微信就是不推"。
		 */
		reportPlazaBcLocation() {
			// #ifdef MP-WEIXIN
			// 商城没授权 plaza 就别碰它的接口(404 会弹「服务错误」)
			this.hasPlugin('plaza').then(ok => {
				if (!ok) return;
				this._requestPlazaBcParams();
			});
			// #endif
		},

		/** 取插件授权参数。抽出来只是为了让上面的插件存在性判断读起来是一道门,而不是嵌一层。 */
		_requestPlazaBcParams() {
			// #ifdef MP-WEIXIN
			// ⛔ 这里**不缓存 openid**,每次 onShow 都重新问一次后端。
			//    曾经缓存过一版,理由是"参数变化极慢,省一次请求"。但 openid 是跟着**当前登录账号**
			//    走的:同一台手机上退出账号再登另一个,缓存里还是上一个人的 openid ——
			//    位置就报到别人头上去了,而且不报错、看不出来。
			//    这个接口很轻,onShow 一次会话也就触发几次,拿它换一个"会静默报错人"的风险不值。
			http.request({
				url: '/plugin/plaza/api/index/bc-auth-params',
				method: 'get'
			}).then(res => {
				const d = (res && res.data) || {};
				if (res.code !== 0 || !d.enabled || !d.openid) return;   // 未开通/无 openid → 不碰插件
				this._callBcGetLocation(d);
			}).catch(() => {});
			// #endif
		},

		/** 真正调插件上报位置。抽出来是因为它有两个入口:缓存命中 与 首次取参成功。 */
		_callBcGetLocation(d) {
			// #ifdef MP-WEIXIN
			try {
				const plugin = requirePlugin(d.plugin_alias || 'myPlugin');
				plugin.getLocation(d.openid).then(r => {
					// 文档:return_code = 0 为成功。非 0 一律当失败并留痕 —— 这是排查
					// 「顾客消费了却没积分」时唯一能证明"位置到底报上去没有"的东西。
					// 文档没说 return_code 是数字还是字符串,统一转字符串比,两种都认
					const code = (r && r.return_code !== undefined) ? String(r.return_code) : '';
					if (code === '0') {
						console.log('[plaza-bc] 位置上报成功');
					} else {
						console.warn('[plaza-bc] 位置上报未成功 return_code=' + code
							+ ' return_msg=' + ((r && r.return_msg) || '')
							+ '(常见原因:用户拒绝位置授权 / 隐私协议未同意 / 小程序未申请 getLocation 权限)', r);
					}
				}).catch(e => console.warn('[plaza-bc] 位置上报异常', e));
			} catch (e) {
				console.warn('[plaza-bc] 插件未引入,跳过位置上报');
			}
			// #endif
		},

		/**
		 * 1. 加载 IM 插件配置
		 * 请求 PHP 接口获取 WebSocket 地址和 JWT Token
		 */
		loadImPluginConfig() {
			// 同上:im 也是按商城授权的插件,没授权的客户调它一样是 404
			this.hasPlugin('im').then(ok => {
				if (!ok) return;
				this._requestImConfig();
			});
		},

		_requestImConfig() {
			http.request({
				url: '/plugin/im/api/index/config',
				method: 'get'
			}).then(res => {

				if (res.code === 0 && res.data.config) {
					const conf = res.data.config;
					this.globalData.imConfig = conf;

					// 只有当 token 存在时才连接 (代表用户已登录)
					if (conf.im_token && conf.ws_url) {
						this.globalData.imToken = conf.im_token;
						this.initIM(); // 启动连接
						this.fetchGlobalUnread(); // 拉取一次最新全局未读数
					}
				}
			}).catch(err => {
				console.error('[IM Setup] 获取配置失败', err);
			});
		},

		/**
		 * 2. 初始化 WebSocket 连接 (核心)
		 */
		initIM() {

			// 防止重复连接
			if (this.globalData.isConnected) return;

			const {
				api_url,
				ws_url,
				app_appid,
				mall_id
			} = this.globalData.imConfig;
			const token = this.globalData.imToken;

			// 关闭旧连接
			this.closeSocket();

			// 构造鉴权 URL: ws://host/ws?appid=xxx&token=ey...&mall_id=xxx
			const url = `${ws_url}?appid=${app_appid}&token=${token}&mall_id=${mall_id}`;

			console.log(`[IM Connect] 开始连接... AppID: ${app_appid}, MallID: ${mall_id}`);

			// 建立连接	
			this.globalData.socketTask = uni.connectSocket({
				url: url,
				success: () => console.log('[IM Connect] 接口调用成功'),
				fail: (err) => console.error('[IM Connect] 接口调用失败', err)
			});

			// 监听: 连接打开
			this.globalData.socketTask.onOpen((res) => {
				console.log('[IM Event] WebSocket 已连接');
				this.globalData.isConnected = true;
				this.globalData.lockReconnect = false;
				this.globalData.reconnectAttempts = 0; // 重置重连次数

				// 开启心跳保活
				this.startHeartbeat();
			});

			// 监听: 收到消息
			this.globalData.socketTask.onMessage((res) => {
				this.handleMessage(res.data);
			});

			// 监听: 连接错误
			this.globalData.socketTask.onError((err) => {
				console.error('[IM Event] 连接发生错误', err);
				this.globalData.isConnected = false;
				this.reconnect(); // 触发重连
			});

			// 监听: 连接关闭
			this.globalData.socketTask.onClose((res) => {
				console.log('[IM Event] 连接已关闭', res);
				this.globalData.isConnected = false;
				this.stopHeartbeat(); // 停止心跳
				this.reconnect(); // 触发重连
			});
		},

		/**
		 * 3. 处理收到的消息
		 */
		handleMessage(data) {
			try {
				const msg = JSON.parse(data);

				// 如果是服务端返回的心跳响应(pong)，忽略
				if (msg.type === 'pong') {
					// console.log('Pong received'); 
					return;
				}

				console.log('[IM Msg] 收到新消息:', msg);

				// 全局广播消息，任何页面都可以通过 uni.$on('IM_NEW_MSG', callback) 监听
				uni.$emit('IM_NEW_MSG', msg);

				// 维护全局未读数
				if (msg.type === 'read_ack') {
					// 收到已读回执，说明自己在某个页面清除了未读，最好全量同步一次
					this.fetchGlobalUnread();
				} else if (['text', 'image', 'voice', 'video', 'card', 'goods', 'system'].includes(msg.type)) {
					// 收到新消息，未读+1
					this.globalData.globalUnread++;
					this.updateTabBarBadge();
				}

			} catch (e) {
				console.error('[IM Msg] JSON 解析失败', e);
			}
		},

		/**
		 * 抓取全局总未读数
		 */
		async fetchGlobalUnread() {
			try {
				const res = await this.$request({
					url: 'plugin/im/api/chat/unread',
					method: 'GET'
				});
				if (res.code === 0) {
					this.globalData.globalUnread = res.data || 0;
					this.updateTabBarBadge();
				}
			} catch (e) { }
		},

		/**
		 * 更新底部的全局小红点
		 * 我们假设客服/消息 Tab 在索引 3 或者别的固定位置，或者你想全局广播该数字由各页面随意使用
		 */
		updateTabBarBadge() {
			const count = this.globalData.globalUnread;
			// 触发一个全局事件，允许有需要的自定义TabBar去接收
			uni.$emit('IM_UNREAD_CHANGE', count);

			// 如果使用的是原生TabBar，可以选择尝试设置特定索引 (具体看你项目的pages.json排列)
			// 注意：这里用 try-catch 包裹是因为如果当前页面不是 TabBar 页面调用可能会报错
			try {
				if (count > 0) {
					// 下面填写你应用里真实的“消息”tab索引，通常是 1 或者 2 或者 3
					// uni.setTabBarBadge({ index: 3, text: String(count > 99 ? '99+' : count) });
				} else {
					// uni.removeTabBarBadge({ index: 3 });
				}
			} catch (e) { }
		},

		/**
		 * 4. 心跳保活机制 (Ping)
		 * 每 30 秒发送一个空包，告诉服务器我还活着
		 */
		startHeartbeat() {
			this.stopHeartbeat(); // 先清除旧的
			this.globalData.heartbeatTimer = setInterval(() => {
				if (this.globalData.isConnected && this.globalData.socketTask) {
					const pingMsg = JSON.stringify({
						type: 'ping',
						content: 'ping'
					});
					this.globalData.socketTask.send({
						data: pingMsg,
						fail: () => {
							console.warn('[IM Ping] 发送失败，连接可能已断');
							// 发送失败通常意味着连接已断，手动关闭触发重连
							this.closeSocket();
						}
					});
				}
			}, 30000); // 30秒一次
		},

		stopHeartbeat() {
			if (this.globalData.heartbeatTimer) {
				clearInterval(this.globalData.heartbeatTimer);
				this.globalData.heartbeatTimer = null;
			}
		},

		/**
		 * 5. 断线重连机制
		 * 指数退避或固定间隔重试
		 */
		reconnect() {
			if (this.globalData.lockReconnect) return;

			// 如果重连次数达到 10 次，彻底放弃
			if (this.globalData.reconnectAttempts >= 10) {
				console.error('[IM Reconnect] 重连已达上限(10次)，放弃重试');
				return;
			}

			this.globalData.lockReconnect = true;

			// 没配置或没 Token 就不重连了
			if (!this.globalData.imConfig || !this.globalData.imToken) {
				this.globalData.lockReconnect = false;
				return;
			}

			this.globalData.reconnectAttempts++;
			console.log(`[IM Reconnect] 第 ${this.globalData.reconnectAttempts} 次尝试重连...`);

			// 避免频繁请求，设置延迟
			if (this.globalData.reconnectTimer) clearTimeout(this.globalData.reconnectTimer);

			this.globalData.reconnectTimer = setTimeout(() => {
				this.globalData.lockReconnect = false;
				this.initIM();
			}, 3000);
		},

		/**
		 * 辅助：手动关闭连接
		 */
		closeSocket() {
			if (this.globalData.socketTask) {
				// 移除所有监听器，防止多次触发
				// 注意：UniApp 的 socketTask 没有 removeAllListeners，只能直接 close
				this.globalData.socketTask.close();
				this.globalData.socketTask = null;
			}
			this.globalData.isConnected = false;
		}
	}
}
</script>
<style lang="scss">
/* uview-ui 已移除 */
@import url("/static/style/main.scss");
@import "@/uni.scss";

/* uview-ui 移除后兜底的全局工具类（单行/双行文字截断） */
.u-line-1 {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.u-line-2 {
	overflow: hidden;
	text-overflow: ellipsis;
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	line-clamp: 2;
}

/* 全局基础样式 */
* {
	box-sizing: border-box;
}

.tui-bg-img {
	position: absolute;
	width: 100%;
	height: 8rpx;
	left: 0;
	bottom: 0;
}

.tui-btn-ml {
	margin-left: 20rpx;
}

.bg-primary {
	background-color: $uni-color-primary;
}

.color-primary {
	color: $uni-color-primary;
}

.bg-danger {
	background-color: $uni-color-danger;
}

.color-danger {
	color: $uni-color-danger;
}

.border-color {
	border-color: $uni-border-color;
}

.color-grey {
	color: $uni-text-color-grey;
}

.color-main-danger {
	color: $uni-color-danger;
}
</style>
