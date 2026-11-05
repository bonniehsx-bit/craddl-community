<template>
	<view class="tui-cs-wrapper" :class="{ 'is-block': block }" @click="handleKefuClick">
		<!-- 原生客服按钮 (根据逻辑判断是否显示) -->
		<!-- 注意：必须置于上层且全覆盖底层插槽内容，点击才能触发在微信原生功能 -->
		<button v-if="shouldShowNativeBtn" open-type="contact" class="contact-btn"></button>

		<!-- 自定义业务内容插槽 -->
		<slot></slot>
	</view>
</template>

<script>
export default {
	name: "tui-cs-wrapper",
	props: {
		// （可选）撑满一行：默认 inline-block 按内容收缩，插槽里放整行按钮时传 block，
		// 否则小程序里宿主节点的 class 管不到内层，按钮会缩成文字那么宽
		block: {
			type: Boolean,
			default: false
		},
		// （可选）如果是在商品页唤起客服，可传入商品对象，以便组装商品卡片发给客服
		goods: {
			type: Object,
			default: () => null
		},
		// （可选）如果要发送卡片，需提供当前商品的 URL 路径
		currentUrl: {
			type: String,
			default: ''
		}
	},
	data() {
		return {
			imConfig: {
				is_open_cs: 0,
				cs_user_id: 0
			},
			currentUser: null,
			matching: false, // 防连点
		};
	},
	computed: {
		// 判断是否显示原生按钮
		shouldShowNativeBtn() {
			// open-type="contact" 是【微信小程序专有】能力,H5/App 里这个 button 无效。
			// 非微信环境一律不渲染它,否则点击冒泡到 handleKefuClick 又命中"原生已覆盖"分支 → 死按钮。
			// #ifndef MP-WEIXIN
			return false;
			// #endif

			// 1. 未开启系统 IM 客服 -> 必须显示微信原生客服按钮
			if (this.imConfig.is_open_cs != 1) return true;

			// 2. 开启了 IM，但当前用户就是客服本人 -> 降级显示微信原生 (防止自己跟自己聊天)
			if (this.currentUser && this.currentUser.id && this.imConfig.cs_user_id) {
				if (String(this.currentUser.id) === String(this.imConfig.cs_user_id)) {
					return true;
				}
			}

			// 3. 其他情况 -> 隐藏原生按钮，后续走 IM 跳转逻辑
			return false;
		},
		// 当前商品所属商户 ID（平台商品/无商品时为 0）
		mchId() {
			if (this.goods && this.goods.mch_id) return Number(this.goods.mch_id) || 0;
			return 0;
		}
	},
	created() {
		// 优先复用 App.vue 已经拉过的全局 imConfig，减少重复请求
		try {
			const global = getApp().globalData || {};
			if (global.imConfig) {
				this.imConfig = { ...this.imConfig, ...global.imConfig };
			} else {
				this.getImConfig();
			}
		} catch (e) {
			this.getImConfig();
		}
		this.refreshUser();
	},
	methods: {
		refreshUser() {
			const user = uni.getStorageSync('USER');
			if (user) this.currentUser = user;
		},

		getImConfig() {
			this.$request({
				url: '/plugin/im/api/index/config',
				method: 'get'
			}).then(res => {
				if (res.code === 0 && res.data.config) {
					this.imConfig = res.data.config;
					// 同步写回全局，避免其他 wrapper 实例再请求
					try {
						const g = getApp().globalData;
						if (g) g.imConfig = res.data.config;
					} catch (e) {}
				}
			}).catch(err => {
				console.error('获取 IM 配置失败:', err);
			});
		},

		async handleKefuClick() {
			this.refreshUser();

			// == 情景 A0：H5 / App 且未开系统 IM ==
			// 这两个平台没有微信原生客服,系统 IM 又没开 —— 降级为拨打后台配置的客服电话。
			// #ifndef MP-WEIXIN
			if (this.imConfig.is_open_cs != 1) {
				const tel = String(this.$util.getSetting('tel') || '').trim();
				if (!tel) {
					uni.showToast({ title: '暂未配置客服电话', icon: 'none' });
					return;
				}
				uni.makePhoneCall({ phoneNumber: tel });
				return;
			}
			// #endif

			// == 情景 A：原生按钮已覆盖点击 ==
			if (this.shouldShowNativeBtn) {
				if (this.currentUser && String(this.currentUser.id) === String(this.imConfig.cs_user_id)) {
					console.log('客服本人点击了客服按钮');
				}
				return;
			}

			// == 情景 B：系统 IM 客服 ==
			if (this.imConfig.is_open_cs != 1) return;
			if (this.matching) return;

			// 检查登录状态
			const token = uni.getStorageSync('ACCESS_TOKEN');
			if (!token) {
				if (this.$T && this.$T.go) this.$T.go('/pages/user/login');
				else uni.navigateTo({ url: '/pages/user/login' });
				return;
			}

			this.matching = true;
			uni.showLoading({ title: '接入客服...', mask: true });

			try {
				// 【关键】点击时实时匹配客服：
				//  · 商家商品传 mch_id → 路由到该商家的店主/店员池
				//  · 平台商品 mch_id=0 → 路由到全商城客服池
				//  · 带上 mch_id 后，每次点击都让后端重新分配，支持多客服负载均衡
				const res = await this.$request({
					url: '/plugin/im/api/index/match-cs',
					method: 'get',
					data: { mch_id: this.mchId }
				});

				if (res.code !== 0) {
					uni.showToast({ title: res.msg || '客服匹配失败', icon: 'none' });
					return;
				}

				const csUserId = res.data && res.data.cs_user_id;
				if (!csUserId) {
					uni.showToast({ title: '当前无客服在线，请稍后再试', icon: 'none' });
					return;
				}

				// 最后防线：防止自己跟自己聊天
				if (this.currentUser && String(this.currentUser.id) === String(csUserId)) {
					uni.showToast({ title: '不能和自己聊天', icon: 'none' });
					return;
				}

				// 组装商品卡片参数
				let cardParam = '';
				if (this.goods && this.currentUrl) {
					const cardData = {
						title: this.goods.share_title || this.goods.name,
						price: this.goods.price,
						image: this.goods.cover_pic,
						url:   this.currentUrl,
					};
					cardParam = `&card=${encodeURIComponent(JSON.stringify(cardData))}`;
				}

				// 带上接入的客服昵称，便于前端聊天页 banner 显示
				const csInfo = res.data.cs_info || {};
				const nameParam = csInfo.nickname
					? `&targetName=${encodeURIComponent(csInfo.nickname)}`
					: '';

				uni.navigateTo({
					url: `/plugins1/chat/chat?targetId=${csUserId}${nameParam}${cardParam}`
				});
			} catch (e) {
				console.error('客服匹配失败:', e);
				uni.showToast({ title: '网络异常，请稍后再试', icon: 'none' });
			} finally {
				this.matching = false;
				uni.hideLoading();
			}
		}
	}
};
</script>

<style scoped lang="scss">
.tui-cs-wrapper {
	position: relative;
	display: inline-block;
}
.tui-cs-wrapper.is-block {
	display: block;
	width: 100%;
}

/* 隐蔽型原生占位按钮，覆盖一切 */
.contact-btn {
	position: absolute;
	width: 100%;
	height: 100%;
	opacity: 0;
	top: 0;
	left: 0;
	z-index: 10;
	padding: 0;
	margin: 0;
}
.contact-btn::after {
	border: none;
}
</style>
