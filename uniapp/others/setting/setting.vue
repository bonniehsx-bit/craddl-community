<template>
	<!-- 设置页要安静:标题栏透明、深色字,顶部只铺一层很淡的主题色,不做大红头部 -->
	<tui-page title="账户设置" navBarBgColor="transparent" titleColor="#16181D">
	<view class="page-container" :style="rootStyle">
		<view class="wash"></view>

		<!-- 往下滚过头部后,标题栏淡入白底(压在 tui-page 自绘栏之下)。H5 不画自绘栏,不需要 -->
		<!-- #ifndef H5 -->
		<view class="solid-nav" :class="{ show: navSolid }" :style="'height:' + navHeight + 'px;'"></view>
		<!-- #endif -->

		<view class="head" :style="'padding-top:' + (navHeight || 16) + 'px;'">
			<!-- 身份区 -->
			<view class="me" v-if="user">
				<!-- 头像:点击唤起 tui-user-avatar 弹窗编辑 -->
				<view class="avatar-section" @click="openProfileModal">
					<view class="avatar-wrapper">
						<image :src="user.avatar_url" mode="aspectFill" class="avatar-img" />
					</view>
					<!-- 拍照徽标:在 avatar-wrapper(overflow:hidden)之外,否则被裁掉 -->
					<view class="camera-badge">
						<tui-icon name="camera-fill" color="#ffffff" :size="11"></tui-icon>
					</view>
				</view>

				<view class="who">
					<text class="nickname-text" :class="{ placeholder: !user.nickname }" @click="openProfileModal">{{ user.nickname || '设置昵称' }}</text>
					<view class="id-row">
						<text>ID</text>
						<text class="id-num">{{ user.id }}</text>
						<text class="id-copy" @click="copyId(user.id)">复制</text>
					</view>
					<text class="ref" v-if="user.inviter">推荐人 · {{ user.inviter.nickname }}</text>
				</view>

				<view class="edit-btn" @click="openProfileModal">编辑资料</view>
			</view>

			<view class="me skeleton" v-else>
				<view class="sk-avatar"></view>
				<view class="who">
					<view class="sk-line lg"></view>
					<view class="sk-line md"></view>
				</view>
			</view>

			<!-- 账户安全卡:这页唯一的重点。按用户自己的数据算,不依赖后台菜单配置 -->
			<view class="sec" v-if="user">
				<view class="sec-h">
					<view class="sec-ic"><tui-icon name="shield" color="#ffffff" :size="18"></tui-icon></view>
					<view class="sec-text">
						<text class="sec-t">账户安全</text>
						<text class="sec-s">{{ secTodo ? '还差 ' + (secItems.length - secDone) + ' 项就全部设置好了' : '已全部设置好' }}</text>
					</view>
					<view class="sec-n"><text class="sec-n-b">{{ secDone }}</text> / {{ secItems.length }}</view>
				</view>
				<view class="segs">
					<view class="seg" :class="it.ok ? 'ok' : 'todo'" v-for="(it, si) in secItems" :key="si">
						<view class="seg-bar"></view>
						<view class="seg-l">
							<tui-icon v-if="it.ok" name="check" color="#1A8A4A" :size="11"></tui-icon>
							<text>{{ it.name }}</text>
						</view>
					</view>
				</view>
				<view class="sec-tip" v-if="secTodo">
					<text class="sec-tip-t">{{ secTodo.tip }}</text>
					<view class="sec-go" @click="goSecTodo">去设置</view>
				</view>
			</view>
		</view>

		<!-- 菜单区:整份清单由后台配置驱动(跟着商城设置接口一起下发,进页面即有,不闪)
		     · builtin 项按 key 分派点击行为与动态副文案,见 script 里的 BUILTIN 表
		     · link    项直接跳 url
		     后台没配 / 接口读不到时,menuGroups 回落到 BUILTIN 的内置默认清单。
		     带 danger 标记的项(注销账号)不放在分组里,统一放到页面最底部。 -->
		<!-- ⛔ :key 必须是**纯变量**,不能写 :key="'g' + gi" 这种字符串拼接。
		     uni-app 会拿 :key 的表达式给嵌套 v-for + 事件处理器生成运行时查找路径,
		     拼接式的 key 会把那对单引号原样塞进 data-event-opts,打断 WXML 属性值,
		     小程序上传直接报 `Bad attr data-event-opts ... unexpected token 'g'`。
		     H5 端不会报错,所以只发 H5 时发现不了。 -->
		<view class="group" v-for="(group, gi) in menuGroups" :key="gi">
			<text class="group-title">{{ group.name }}</text>
			<view class="group-card">
				<view class="cell" v-for="(item, ii) in group.list" :key="ii" @click="onMenuTap(item)">
					<!-- 图标保持无底色；Tabler 云端资源优先展示，存量字体图标继续兼容。 -->
					<view class="cell-ic">
						<image v-if="item.pic" :src="item.pic" class="cell-pic" :class="item.isTablerPic ? 'cell-pic--tabler' : ''" mode="aspectFit"></image>
						<tui-icon v-else :name="item.icon" :color="themeColor" :size="19"></tui-icon>
					</view>
					<text class="cell-title">{{ item.name }}</text>
					<view class="cell-right">
						<text class="cell-value" :class="{ go: item.go }" v-if="item.value">{{ item.value }}</text>
						<view class="cell-arr"></view>
					</view>
				</view>
			</view>
		</view>

		<!-- 退出登录单独一张卡;注销账号(危险操作,入口必须保留,应用商店要求)和版本号放最底下一行小字 -->
		<view class="logout-btn" @click="logout">退出登录</view>
		<view class="bottom-line">
			<text class="danger-link" v-for="(d, di) in dangerItems" :key="di" @click="onMenuTap(d)">{{ d.name }}</text>
			<text class="dot" v-if="dangerItems.length && version">·</text>
			<text v-if="version">版本 {{ version }}</text>
		</view>

		<!-- 手机号组件 (同样 v-if="user" 做异步门) -->
		<tui-changemobile v-if="user" :visible.sync="showMobileModal" :current-mobile="user.mobile || ''"
			@success="onMobileChanged">
		</tui-changemobile>

		<!-- 登录密码组件 (和支付密码同等条件: v-if="user" 做异步门) -->
		<tui-loginpwd v-if="user" :visible.sync="showPwdModal" :mobile="user.mobile || ''"
			:mode="user.has_password ? 'edit' : 'set'" @success="onLoginPwdChanged">
		</tui-loginpwd>

		<!-- 支付密码组件 -->
		<!-- v-if="user" 等同步门: 只有当 user 已经从后端拿到后, 弹窗组件才会被挂载,
		     确保 :mobile 和 :mode 传入时已经是最终值, 消除"先开框再出数据"的 race -->
		<tui-paypwd v-if="user" :visible.sync="showPayPwd" :mobile="user.mobile || ''"
			:mode="user.has_pay_pwd ? 'edit' : 'set'" @refresh="getUser">
		</tui-paypwd>

		<!-- 头像/昵称编辑弹窗:与个人中心「完善资料」同一组件 -->
		<tui-user-avatar v-if="user" :visible="showProfileModal" :userData="user" @save="onProfileSaved"
			@close="showProfileModal = false">
		</tui-user-avatar>
	</view>
	</tui-page>
</template>

<script>
	import {
		mapActions,
		mapMutations,
		mapState
	} from 'vuex';

	// 把 hex 往目标色(黑 / 白)混 t 比例,返回 rgb() 字符串;解析不了(不是 #rrggbb)返回原色
	function mixHex(hex, target, t) {
		const h = String(hex || '').trim().replace('#', '');
		const full = h.length === 3 ? h.split('').map(c => c + c).join('') : h;
		if (!/^[0-9a-fA-F]{6}$/.test(full)) return hex;
		const n = parseInt(full, 16);
		const c = [n >> 16 & 255, n >> 8 & 255, n & 255];
		return 'rgb(' + c.map((v, i) => Math.round(v + (target[i] - v) * t)).join(',') + ')';
	}

	import TuiUserAvatar from '@/components/thorui/tui-user-avatar/tui-user-avatar.vue'
	import TuiChangemobile from '@/others/components/tui-changemobile/tui-changemobile.vue'
	import TuiLoginpwd from '@/others/components/tui-loginpwd/tui-loginpwd.vue'
	import TuiPaypwd from '@/others/components/tui-paypwd/tui-paypwd.vue'
	/**
	 * 内置菜单项的**行为表** —— key → 这一项点了干什么、右侧动态副文案怎么算。
	 *
	 * 为什么必须留在前端:这页有三类点击行为,只有 link 能配成链接。
	 *   modal  打开本页的弹窗组件(改手机号/登录密码/支付密码)—— 组件挂在本页
	 *   method 执行本页的方法(清缓存/隐私政策/用户协议/微信绑定)
	 * 后台只能改这些项的名字、图标、顺序、显隐,改不了行为 —— 也不该能改。
	 *
	 * order 是后台没配过时的默认排布,与改造前的写死顺序一一对应,保证存量商城行为不变。
	 */
	const BUILTIN = {
		mobile:           { group: 'security', order: 1, icon: 'voipphone',    name: '手机号码' },
		login_pwd:        { group: 'security', order: 2, icon: 'setup',        name: '登录密码' },
		pay_pwd:          { group: 'security', order: 3, icon: 'attestation',  name: '支付密码' },
		address:          { group: 'security', order: 4, icon: 'location',     name: '地址管理', value: '管理' },
		wx_notify:        { group: 'security', order: 5, icon: 'wechat',       name: '微信通知' },
		about:            { group: 'service',  order: 1, icon: 'message-fill', name: '关于我们' },
		feedback:         { group: 'service',  order: 2, icon: 'kefu',         name: '意见反馈', value: '期待您的声音' },
		privacy:          { group: 'service',  order: 3, icon: 'service',      name: '隐私政策' },
		protocol:         { group: 'service',  order: 4, icon: 'order',        name: '用户协议' },
		upgrade:          { group: 'service',  order: 5, icon: 'download',     name: '检查更新' },
		clear_cache:      { group: 'system',   order: 1, icon: 'delete',       name: '清理缓存', value: '清理后需重新登录' },
		account_deletion: { group: 'system',   order: 3, icon: 'close',        name: '注销账号', value: '账户注销', danger: true },
	};

	const GROUP_NAME = { security: '账户安全', service: '服务与支持', system: '系统设置' };

	export default {
		components: { TuiUserAvatar, TuiChangemobile, TuiLoginpwd, TuiPaypwd },
		data() {
			return {
				// 标题栏占的高度:小程序按胶囊算,H5 没有自绘栏所以是 0
				navHeight: 0,
				navSolid: false,

				user: null,
				showPayPwd: false,
				showPwdModal: false,
				showMobileModal: false,
				is_loading: false,
				version: '',
				showProfileModal: false,
			}
		},
		computed: {
			// 主题色必须用字符串 style(对象写法在小程序里会丢 CSS 变量)。渐变的深 / 浅从主题色推出来
			rootStyle() {
				const c = this.themeColor || '#D71F1F';
				// 顶部那层很淡的主题色:往页面底色 #F3F4F6 混,不用 rgba(小程序里叠渐变更稳)
				return '--theme-color:' + c
					+ ';--wash-1:' + mixHex(c, [243, 244, 246], 0.86)
					+ ';--wash-2:' + mixHex(c, [243, 244, 246], 0.95) + ';';
			},

			// 账户安全三项:手机号 / 登录密码 / 支付密码。数据来自 /api/user/index,不依赖后台菜单配置
			secItems() {
				const u = this.user || {};
				return [
					{ key: 'mobile', name: '手机号', ok: !!u.mobile,
						tip: '绑定手机号后，可以用手机号登录、找回密码' },
					{ key: 'login_pwd', name: '登录密码', ok: !!u.has_password,
						tip: '设置登录密码后，换了手机也能用账号密码登录' },
					// 支付密码只在余额付款(商城开启验密时)和余额 / 积分转赠时校验
					{ key: 'pay_pwd', name: '支付密码', ok: !!u.has_pay_pwd,
						tip: '设置支付密码后，用' + this.$noun('balance') + '付款或转赠时需要验证，账户更安全' },
				];
			},
			secDone() {
				return this.secItems.filter(it => it.ok).length;
			},
			// 只提示第一项没做的,一次一件事
			secTodo() {
				return this.secItems.find(it => !it.ok) || null;
			},
			// [核心] 获取主题色
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F'
			}),
			wxNotifyEnabled() {
				return this.$util.getSetting('wx_notify_enabled') == '1'
			},

			/**
			 * 最终渲染用的菜单。后台配置 + BUILTIN 行为表合并而成。
			 *
			 * ⚠ 这里只产出**纯数据**,不把函数塞进去 —— 小程序模板里事件只能绑方法名,
			 *   拿不到 data 里存的函数;点击统一走 onMenuTap(item) 按 key 分派。
			 */
			resolvedGroups() {
				const cfg = this.$util.getSetting('setting_page');
				const groups = (cfg && Array.isArray(cfg.groups) && cfg.groups.length)
					? cfg.groups
					: this.defaultGroups();

				const out = [];
				groups.forEach(g => {
					if (!g || Number(g.is_show) === 0) return;
					const list = [];
					(g.list || []).forEach(raw => {
						const item = this.resolveMenuItem(raw);
						if (item) list.push(item);
					});
					// 一项都不剩就别留个空卡片在那儿
					if (list.length) out.push({ name: g.name, list });
				});
				return out;
			},

			// 分组里不放危险项(注销账号);一项都不剩的分组不留空卡片
			menuGroups() {
				const out = [];
				this.resolvedGroups.forEach(g => {
					const list = g.list.filter(it => !it.danger);
					if (list.length) out.push({ name: g.name, list });
				});
				return out;
			},

			// 危险项统一放页面最底部
			dangerItems() {
				const out = [];
				this.resolvedGroups.forEach(g => g.list.forEach(it => { if (it.danger) out.push(it); }));
				return out;
			}
		},
		onLoad: function(e) {
			// 外部页面通过 ?action=pay_pwd 让本页入口即自动展开"支付密码"抽屉
			// 典型场景: 收银台发现用户未设支付密码, 跳本页直接进入设置流程
			// 这里可以直接置 true —— 模板上用 v-if="user" 做异步门, user 未就绪时组件本身不会挂载
			if (e && e.action === 'pay_pwd') {
				this.showPayPwd = true;
			}

			// 标题栏高度:小程序按胶囊算,和 tui-page 自绘栏一致;H5 没有自绘栏
			const sys = uni.getSystemInfoSync();
			// #ifdef MP-WEIXIN
			const menu = uni.getMenuButtonBoundingClientRect();
			this.navHeight = (menu.top - sys.statusBarHeight) * 2 + menu.height + sys.statusBarHeight;
			// #endif
			// #ifndef MP-WEIXIN
			this.navHeight = 75;
			// #endif
			// #ifdef H5
			this.navHeight = 0;
			// #endif
			this.version = sys.appVersionCode || '1.0.0';
			this.getUser();
		},
		onPageScroll(e) {
			// 滚过头部再变实色,和头部的高度对上
			const solid = e.scrollTop > 120;
			if (solid !== this.navSolid) this.navSolid = solid;
		},
		onShow() {
			// H5 从微信绑定页重定向回来后，刷新用户信息使"已绑定"状态即时生效
			// #ifdef H5
			if (this.user) this.getUser()
			// #endif
		},
		methods: {
			isTablerPic(pic) {
				return typeof pic === 'string' && /[?&]r=common(?:%2f|\/)icon(?:%2f|\/)svg(?:&|$)/i.test(pic);
			},
			/** 后台没配过时的默认清单 —— 直接由 BUILTIN 生成,不再单独维护一份 */
			defaultGroups() {
				const by = {};
				Object.keys(BUILTIN).forEach(key => {
					const d = BUILTIN[key];
					(by[d.group] = by[d.group] || []).push({
						type: 'builtin', key, name: d.name, icon: d.icon, pic: '',
						value: d.value || '', is_show: d.hidden ? 0 : 1, _o: d.order
					});
				});
				return ['security', 'service', 'system'].map(gk => ({
					key: gk, name: GROUP_NAME[gk], is_show: 1,
					list: (by[gk] || []).sort((a, b) => a._o - b._o)
				}));
			},

			/**
			 * 一条配置 → 一条可渲染的数据。返回 null 表示这一项不该出现。
			 * 动态副文案(手机号掩码 / 绑定状态 / 版本号)在这里覆盖后台填的静态文案 ——
			 * 那几项的右侧本来就不是文案,是运行时算出来的状态。
			 */
			resolveMenuItem(raw) {
				if (!raw || Number(raw.is_show) === 0) return null;

				if (raw.type !== 'builtin') {
					if (!raw.url) return null;   // 自定义项没链接 = 点了没反应
					return {
						type: 'link', key: '', url: raw.url,
						name: raw.name || '', icon: raw.icon || 'about', pic: raw.pic || '',
						isTablerPic: this.isTablerPic(raw.pic),
						value: raw.value || '', danger: false
					};
				}

				const d = BUILTIN[raw.key];
				if (!d) return null;          // 认不出的 key(老配置残留),渲染出来也点不动

				// 内置项的额外显示条件
				if (raw.key === 'wx_notify' && !this.wxNotifyEnabled) return null;

				let value = raw.value || d.value || '';
				// 状态项:上面安全卡已经用颜色讲过了,列表里只写字 —— 已设置灰色,没设置用主题色「去设置」
				let go = false;
				const u = this.user || {};
				if (raw.key === 'mobile') {
					value = u.mobile ? this.maskMobile(u.mobile) : '去绑定';
					go = !u.mobile;
				} else if (raw.key === 'login_pwd') {
					value = u.has_password ? '已设置' : '去设置';
					go = !u.has_password;
				} else if (raw.key === 'pay_pwd') {
					value = u.has_pay_pwd ? '已设置' : '去设置';
					go = !u.has_pay_pwd;
				} else if (raw.key === 'wx_notify') {
					value = u.wechat_openid ? '已绑定' : '去绑定';
					go = !u.wechat_openid;
				} else if (raw.key === 'upgrade') {
					value = this.version || '';
				}

				return {
					type: 'builtin', key: raw.key, url: '',
					name: raw.name || d.name, icon: raw.icon || d.icon, pic: raw.pic || '',
					isTablerPic: this.isTablerPic(raw.pic),
					value, go, danger: !!d.danger
				};
			},

			/** 菜单点击统一入口。内置项按 key 分派,自定义项一律跳 url */
			onMenuTap(item) {
				if (!item) return;
				if (item.type !== 'builtin') {
					this.$T.go(item.url);
					return;
				}
				switch (item.key) {
					case 'mobile':           this.showMobileModal = true; break;
					case 'login_pwd':        this.showPwdModal = true; break;
					case 'pay_pwd':          this.showPayPwd = !this.showPayPwd; break;
					case 'address':          this.$T.go('/others/address/address'); break;
					case 'wx_notify':        this.goWxBind(); break;
					case 'about':            this.$T.go('/others/about/about'); break;
					case 'feedback':         this.$T.go('/others/feedback/feedback'); break;
					case 'privacy':          this.openPrivacy(); break;
					case 'protocol':         this.openProtocol(); break;
					case 'upgrade':          this.$T.go('/pages/upgrade/upgrade'); break;
					case 'clear_cache':      this.clearStorage(); break;
					case 'account_deletion': this.$T.go('/others/account-deletion/account-deletion'); break;
					default: break;
				}
			},

			...mapActions('user', {
				vuexLogout: 'logout'
			}),
			...mapMutations('mch', ['CLEAR_MCH_INFO']),
			// ...mapMutations('mall', ['CLEAR_MALL_SETTING']),

			// 安全卡「去设置」:和点菜单里对应那一项走同一个分派
			goSecTodo() {
				if (this.secTodo) this.onMenuTap({ type: 'builtin', key: this.secTodo.key });
			},
			maskMobile(mobile) {
				if (!mobile) return '';
				return mobile.replace(/(\d{3})\d{4}(\d{4})/, '$1****$2');
			},
			copyId(id) {
				uni.setClipboardData({
					data: String(id),
					success: () => {
						uni.showToast({
							title: 'ID已复制',
							icon: 'none'
						});
					}
				});
			},
			handleLogout(content) {
				uni.showModal({
					title: '提示',
					content: content,
					success: (e) => {
						if (e.confirm) {
							this.$request({
								url: '/api/user/logout'
							}).then(res => {
								uni.removeStorageSync('login_before_url');
								uni.removeStorageSync('X-PARENT-ID');
								// 【优化】清理遗留的 USER 缓存
								uni.removeStorageSync('USER');

								this.vuexLogout();
								this.CLEAR_MCH_INFO();
								// 【优化】不再清除商城配置，避免首页闪烁
								// this.CLEAR_MALL_SETTING();

								uni.reLaunch({
									url: "/pages/index/index"
								});
							}).catch(err => {
								uni.removeStorageSync('X-PARENT-ID');
								// 【优化】清理遗留的 USER 缓存
								uni.removeStorageSync('USER');

								uni.showToast({
									title: '网络请求失败，已在本地退出',
									icon: 'none'
								});
								this.vuexLogout();
								this.CLEAR_MCH_INFO();

								uni.reLaunch({
									url: "/pages/index/index"
								});
							});
						}
					}
				});
			},
			logout() {
				this.handleLogout("确定退出登录？");
			},
			clearStorage() {
				this.handleLogout("确定清理缓存？此操作将同时退出登录。");
			},
			goWxBind() {
				// #ifdef MP-WEIXIN
				// 小程序：用 WebView 页包裹，结果页通过 uni.webView.navigateBack() 自动关闭。
				// 这个页面原来在 hardware 插件分包里，hardware 没打进包就跳不过去，已提到核心 others 分包
				uni.navigateTo({
					url: '/others/wx-bind/wx-bind'
				})
				// #endif

				// #ifdef H5
				// H5（微信浏览器）：直接整页跳转，授权完停在结果页
				// token 键统一用 ACCESS_TOKEN（全项目主流写法），原来这里读 access_token/token 是孤例
				const token = uni.getStorageSync('ACCESS_TOKEN') || ''
				const mallId = this.$store.state.mall?.mallId || uni.getStorageSync('mall_id') || ''
				if (!token || !mallId) {
					return uni.showToast({
						title: '请先登录',
						icon: 'none'
					})
				}
				const bindUrl = `${window.location.origin}/index.php` +
					`?r=wechat/auth/bind-notification` +
					`&mall_id=${mallId}` +
					`&user_token=${encodeURIComponent(token)}`
				window.location.href = bindUrl
				// #endif
			},
			openPrivacy() {
				let privacy = this.$util.getSetting('privacy_url');
				uni.setStorageSync('temp_url', privacy);
				this.$T.go('/others/web/web')
			},
			openProtocol() {
				let protocol = this.$util.getSetting('protocol_url');
				uni.setStorageSync('temp_url', protocol);
				this.$T.go('/others/web/web')
			},
			// 头像/昵称统一在 tui-user-avatar 弹窗里编辑(与个人中心「完善资料」同一交互)
			openProfileModal() {
				if (!this.user) return;
				this.showProfileModal = true;
			},
			// 弹窗保存成功:必须把 visible 置回 false,否则 prop 不变、下次打不开
			onProfileSaved() {
				this.showProfileModal = false;
				this.getUser();
			},
			// 登录密码设置/修改成功后的收尾:
			// 后端已经 invalidateUserAuth 踢掉所有设备 session, 前端也必须清掉本地 token 并跳登录页,
			// 否则留在 setting 页面, 下一次请求就会返回 401, 用户体感"莫名其妙掉线"
			// vuex 里的 user/logout action 已经包办了清 ACCESS_TOKEN / USER_INFO / USER_CENTER_SETTING
			// 并 emit LOGOUT_SUCCESS 让 App.vue 断开 IM, 不要自己再去 removeStorageSync
			onLoginPwdChanged() {
				this.$store.dispatch('user/logout');
				uni.showModal({
					title: '登录密码已更新',
					content: '请使用新密码重新登录',
					showCancel: false,
					success: () => {
						uni.reLaunch({
							url: '/others/login/login'
						});
					}
				});
			},
			getUser() {
				this.$request({
					url: "/api/user/index"
				}).then(res => {
					if (res.code == 0) {
						uni.setStorageSync('USER', res.data.user);
						this.user = res.data.user;
					} else {
						uni.showModal({
							title: '提示',
							content: res.msg,
							success: (e) => {
								uni.navigateBack();
							}
						})
					}
				})
			},
			// 手机号修改/绑定成功后的收尾: 只刷 user, 不需要 logout
			// 因为 token 是和 user_id 绑定的, 改手机号不会让 session 失效
			onMobileChanged() {
				this.getUser();
			},
		}
	}
</script>

<style lang="scss" scoped>
	$theme-color: var(--theme-color);
	$ink: #16181D;
	$sub: #8A909B;
	$line: #ECEEF1;

	/* 小程序里 view 默认是 content-box,H5 默认是 border-box,统一成 border-box,两端算法一致 */
	view, text, image {
		box-sizing: border-box;
	}

	.page-container {
		position: relative;
		min-height: 100vh;
		background-color: #F3F4F6;
		padding-bottom: calc(48rpx + env(safe-area-inset-bottom));
		overflow-x: hidden;
	}

	/* 顶部一层很淡的主题色,右上一团淡光;安静,不抢 */
	.wash {
		position: absolute;
		left: 0;
		right: 0;
		top: 0;
		height: 600rpx;
		background: linear-gradient(180deg, var(--wash-1) 0%, var(--wash-2) 55%, #F3F4F6 100%);
		pointer-events: none;

		&::after {
			content: '';
			position: absolute;
			right: -120rpx;
			top: -160rpx;
			width: 520rpx;
			height: 520rpx;
			border-radius: 50%;
			background: radial-gradient(closest-side, var(--wash-1), rgba(243, 244, 246, 0));
		}
	}

	/* 滚动后淡入的白色标题栏底 */
	.solid-nav {
		position: fixed;
		left: 0;
		right: 0;
		top: 0;
		z-index: 49;
		opacity: 0;
		background: rgba(255, 255, 255, 0.96);
		box-shadow: 0 1rpx 0 $line;
		transition: opacity 0.2s;

		&.show { opacity: 1; }
	}

	.head { position: relative; }

	/* ───── 身份区 ───── */
	.me {
		display: flex;
		align-items: center;
		padding: 8rpx 40rpx 0;
	}
	.avatar-section {
		position: relative;
		flex-shrink: 0;
		width: 124rpx;
		height: 124rpx;
		margin-right: 28rpx;
	}
	.avatar-wrapper {
		width: 100%;
		height: 100%;
		border-radius: 40rpx;
		overflow: hidden;
		background: #E5E7EB;
		box-shadow: 0 16rpx 40rpx rgba(0, 0, 0, 0.12);
	}
	.avatar-img {
		width: 100%;
		height: 100%;
		display: block;
	}
	.camera-badge {
		position: absolute;
		right: -10rpx;
		bottom: -10rpx;
		width: 44rpx;
		height: 44rpx;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		background: $ink;
		border: 4rpx solid #fff;
	}
	.who {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
	}
	.nickname-text {
		font-size: 40rpx;
		font-weight: 800;
		color: $ink;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;

		&.placeholder { color: $sub; font-weight: 600; }
	}
	.id-row {
		display: flex;
		align-items: center;
		margin-top: 10rpx;
		font-size: 24rpx;
		color: $sub;
	}
	.id-num {
		margin: 0 12rpx 0 8rpx;
		font-size: 24rpx;
		font-weight: 600;
		color: $ink;
		font-family: Menlo, monospace;
	}
	.id-copy {
		padding: 2rpx 14rpx;
		font-size: 22rpx;
		color: #555B66;
		border-radius: 12rpx;
		background: rgba(0, 0, 0, 0.05);
	}
	.ref {
		margin-top: 8rpx;
		font-size: 24rpx;
		color: $sub;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.edit-btn {
		flex-shrink: 0;
		margin-left: 16rpx;
		padding: 14rpx 24rpx;
		font-size: 25rpx;
		font-weight: 600;
		color: $ink;
		border-radius: 32rpx;
		background: #fff;
		box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.06);

		&:active { opacity: 0.8; }
	}

	/* ───── 账户安全卡 ───── */
	.sec {
		margin: 40rpx 28rpx 0;
		padding: 32rpx;
		border-radius: 40rpx;
		background: #fff;
		box-shadow: 0 12rpx 48rpx rgba(20, 20, 30, 0.06);
	}
	.sec-h {
		display: flex;
		align-items: center;
	}
	.sec-ic {
		flex-shrink: 0;
		width: 68rpx;
		height: 68rpx;
		margin-right: 20rpx;
		border-radius: 22rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		background: $theme-color;
	}
	.sec-text {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
	}
	.sec-t {
		font-size: 30rpx;
		font-weight: 700;
		color: $ink;
	}
	.sec-s {
		margin-top: 2rpx;
		font-size: 24rpx;
		color: $sub;
	}
	.sec-n {
		flex-shrink: 0;
		font-size: 26rpx;
		color: $sub;
	}
	.sec-n-b {
		font-size: 44rpx;
		font-weight: 700;
		color: $ink;
		font-family: Arial, sans-serif;
	}
	/* 三段进度:完成绿色,没完成橙色虚线(状态是语义色,不跟主题色走) */
	.segs {
		display: flex;
		margin-top: 28rpx;
	}
	.seg {
		flex: 1;
		& + .seg { margin-left: 12rpx; }

		.seg-bar {
			height: 10rpx;
			border-radius: 6rpx;
			background: #E5E7EB;
		}
		.seg-l {
			display: flex;
			align-items: center;
			margin-top: 14rpx;
			font-size: 23rpx;
			color: $sub;
		}
		&.ok .seg-bar { background: #1A8A4A; }
		&.todo .seg-bar { background: repeating-linear-gradient(90deg, #D97706 0 12rpx, #FDE7C2 12rpx 20rpx); }
		&.todo .seg-l { color: #D97706; font-weight: 600; }
	}
	.sec-tip {
		display: flex;
		align-items: center;
		margin-top: 28rpx;
		padding: 20rpx 24rpx;
		border-radius: 24rpx;
		background: #FFF7EA;
	}
	.sec-tip-t {
		flex: 1;
		min-width: 0;
		font-size: 25rpx;
		line-height: 1.5;
		color: #7A4A05;
	}
	.sec-go {
		flex-shrink: 0;
		margin-left: 20rpx;
		padding: 12rpx 26rpx;
		font-size: 25rpx;
		font-weight: 700;
		color: #fff;
		border-radius: 28rpx;
		background: $theme-color;

		&:active { opacity: 0.85; }
	}

	/* ───── 分组列表:组名在卡片外,行扁一些,图标是纯线条 ───── */
	.group { position: relative; }
	.group-title {
		display: block;
		margin: 44rpx 60rpx 14rpx;
		font-size: 24rpx;
		letter-spacing: 1rpx;
		color: $sub;
	}
	.group-card {
		margin: 0 28rpx;
		border-radius: 36rpx;
		background: #fff;
		overflow: hidden;
	}
	.cell {
		position: relative;
		display: flex;
		align-items: center;
		height: 100rpx;
		padding: 0 28rpx 0 32rpx;

		& + .cell::before {
			content: '';
			position: absolute;
			left: 96rpx;
			right: 0;
			top: 0;
			height: 1rpx;
			background: $line;
		}
		&:active { background: #F7F8FA; }
	}
	.cell-ic {
		flex-shrink: 0;
		width: 40rpx;
		margin-right: 24rpx;
		display: flex;
		align-items: center;
	}
	.cell-pic {
		width: 38rpx;
		height: 38rpx;
	}
	/* 75% 图形占比对应约 38rpx 的实际图形，与原 19px 字体图标接近。 */
	.cell-pic--tabler {
		width: 50rpx;
		height: 50rpx;
		flex-shrink: 0;
		margin-left: -5rpx;
	}
	.cell-title {
		font-size: 30rpx;
		color: $ink;
	}
	.cell-right {
		margin-left: auto;
		display: flex;
		align-items: center;
	}
	.cell-value {
		font-size: 26rpx;
		color: $sub;

		&.go {
			color: $theme-color;
			font-weight: 600;
		}
	}
	.cell-arr {
		width: 14rpx;
		height: 14rpx;
		margin-left: 16rpx;
		border-top: 3rpx solid #C4C8CF;
		border-right: 3rpx solid #C4C8CF;
		transform: rotate(45deg);
	}

	/* ───── 底部 ───── */
	.logout-btn {
		margin: 48rpx 28rpx 0;
		height: 100rpx;
		line-height: 100rpx;
		text-align: center;
		font-size: 30rpx;
		font-weight: 600;
		color: $theme-color;
		border-radius: 36rpx;
		background: #fff;

		&:active { background: #F7F8FA; }
	}
	.bottom-line {
		display: flex;
		align-items: center;
		justify-content: center;
		margin-top: 36rpx;
		font-size: 24rpx;
		color: #A3A9B3;

		.dot { margin: 0 16rpx; color: #CFD3D8; }
	}
	.danger-link {
		&:active { opacity: 0.6; }
		& + .danger-link { margin-left: 24rpx; }
	}

	/* ───── 骨架屏 ───── */
	.skeleton .sk-avatar {
		flex-shrink: 0;
		width: 124rpx;
		height: 124rpx;
		margin-right: 28rpx;
		border-radius: 40rpx;
		background: rgba(0, 0, 0, 0.06);
	}
	.skeleton .sk-line {
		height: 32rpx;
		margin-bottom: 20rpx;
		border-radius: 16rpx;
		background: rgba(0, 0, 0, 0.06);

		&.lg { width: 60%; }
		&.md { width: 40%; }
	}
</style>
