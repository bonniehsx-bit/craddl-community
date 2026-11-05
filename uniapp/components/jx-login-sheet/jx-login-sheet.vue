<template>
	<!-- 登录弹窗:首页「开启首页登录」先用它,以后别的页面要引导登录也放这个,不再各弹各的系统框。
	     小程序:主按钮就是手机号一键登录(不用跳页);其它端:主按钮进登录页。 -->
	<view v-if="show" class="ls-mask" :class="{ 'is-in': entered }" @touchmove.stop.prevent @click="close">
		<view class="ls-card" :style="themeVars" @click.stop>
			<view class="ls-close" @click="close">
				<tui-icon name="close" :size="16" color="#8a8a8a"></tui-icon>
			</view>

			<view class="ls-head">
				<view class="ls-logo">
					<image v-if="logo" :src="logo" class="ls-logo-img" mode="aspectFill" />
					<text v-else class="ls-logo-text">{{ (mallName || '商').slice(0, 1) }}</text>
				</view>
				<text class="ls-mall" v-if="mallName">{{ mallName }}</text>
				<text class="ls-title">{{ titleText }}</text>
			</view>

			<view class="ls-benefits" v-if="benefits.length">
				<view class="ls-bf" v-for="(b, i) in benefits" :key="i">
					<view class="ls-bf-icon">
						<view class="ls-bf-glyph">
							<tui-icon :name="b.icon || 'star'" :size="22" :color="themeColor"></tui-icon>
						</view>
					</view>
					<text class="ls-bf-text">{{ b.text }}</text>
				</view>
			</view>

			<view class="ls-actions">
				<!-- 小程序且后台「弹窗登录方式」选了手机号一键登录:在弹窗里直接要手机号登录。
				     没勾协议前按钮只做提醒,勾了才真正去要手机号(和登录页同一套规矩) -->
				<block v-if="phoneLogin">
					<button v-if="!agreed" class="ls-primary" hover-class="ls-primary-hover" @click="needAgree">手机号一键登录</button>
					<button v-else class="ls-primary" hover-class="ls-primary-hover" :loading="busy"
						open-type="getPhoneNumber|agreePrivacyAuthorization" @getphonenumber="onPhone">手机号一键登录</button>
					<view class="ls-other" @click="goLoginPage">其他方式登录</view>
				</block>
				<!-- 其它端,或后台选了「跳转登录页」:进登录页(登录页里照旧能选手机号授权或账号密码) -->
				<button v-else class="ls-primary" hover-class="ls-primary-hover" @click="goLoginPage">登录 / 注册</button>
				<view class="ls-later" @click="close">先随便逛逛</view>
			</view>

			<view v-if="phoneLogin" :class="['ls-agree', shake ? 'is-shake' : '']" @click="agreed = !agreed">
				<view :class="['ls-check', agreed ? 'is-on' : '']">
					<tui-icon v-if="agreed" name="check" :size="10" color="#fff"></tui-icon>
				</view>
				<text class="ls-agree-text">我已阅读并同意<text class="ls-link" @click.stop="openDoc('protocol_url', 'protocol')">《用户协议》</text>和<text class="ls-link" @click.stop="openDoc('privacy_url', 'privacy')">《隐私条款》</text></text>
			</view>
		</view>
	</view>
</template>

<script>
import { loginByMpPhone, sheetUsesPhoneLogin } from '@/utils/mpPhoneLogin.js'

// 后台没配时的默认文案:只写每个商城都有的东西,不写「新人券」这类不一定有的,免得变成虚假宣传
const DEFAULT_TITLE = '登录后，好物和优惠都在这'
const DEFAULT_BENEFITS = [
	{ icon: 'coupon', text: '领取优惠券' },
	{ icon: 'member', text: '会员专享价' },
	{ icon: 'transport', text: '订单物流随时查' },
]

export default {
	name: 'jx-login-sheet',
	props: {
		show: { type: Boolean, default: false },
		// 页面自己的标题(如购物车「登录后查看购物车」);不传用后台「登录弹窗标题」
		title: { type: String, default: '' },
	},
	data() {
		return {
			agreed: false,
			shake: false,
			busy: false,
			entered: false,
		}
	},
	computed: {
		/** 小程序里,且后台「弹窗登录方式」选了手机号一键登录(其它端恒为 false) */
		phoneLogin() {
			return sheetUsesPhoneLogin(this.$store)
		},
		themeColor() {
			return this.$store.state.mall.themeColor || this.mallSetting().theme_color || '#D71F1F'
		},
		themeVars() {
			return '--ls-theme:' + this.themeColor + ';'
		},
		logo() {
			return this.mallSetting().logo || ''
		},
		mallName() {
			return this.mallSetting().name || ''
		},
		titleText() {
			return this.title || (this.mallSetting().home_login_title || '').trim() || DEFAULT_TITLE
		},
		/** 后台「开启首页登录」下面配的三条好处;没配或全空就用默认 */
		benefits() {
			const raw = this.mallSetting().home_login_benefits
			const list = (Array.isArray(raw) ? raw : []).filter(b => b && String(b.text || '').trim())
			return (list.length ? list : DEFAULT_BENEFITS).slice(0, 3)
		},
	},
	watch: {
		// immediate:组件第一次渲染时 show 可能已经是 true(首页配置先到),不加的话
		// 入场动画不触发,弹窗停在透明状态、透明遮罩还挡着整页点击
		show: {
			immediate: true,
			handler(v) {
				// 先挂上再加 is-in,缩放动画才有起点
				this.entered = false
				if (v) setTimeout(() => { this.entered = true }, 20)
			},
		},
	},
	methods: {
		// 商城配置不做成 computed:小程序会把 computed 整个 setData 给视图层,整份配置约 6KB;
		// 模板只用 logo / 名称 / 标题这些派生值,它们在 computed 里调这里照样跟着配置更新
		mallSetting() {
			return this.$store.state.mall.setting || {}
		},
		close() {
			if (this.busy) return
			this.$emit('close')
		},
		needAgree() {
			this.shake = true
			setTimeout(() => { this.shake = false }, 500)
			uni.showToast({ title: '请先阅读并勾选用户协议和隐私条款', icon: 'none' })
		},
		onPhone(e) {
			if (this.busy) return
			this.busy = true
			loginByMpPhone(e.detail, this.$store, this.$request).then(() => {
				this.busy = false
				uni.showToast({ title: '登录成功', icon: 'success' })
				this.$emit('success')
				this.$emit('close')
			}).catch((err) => {
				this.busy = false
				const msg = (err && err.message) || '登录失败'
				// 用户自己点了拒绝,轻提示就够;真失败才弹框说原因
				if (msg === '已取消授权') {
					uni.showToast({ title: msg, icon: 'none' })
				} else {
					uni.showModal({ title: '登录失败', content: msg, showCancel: false })
				}
			})
		},
		/** 其他方式(账号密码、注册):记下当前页,登录完回到这里 */
		goLoginPage() {
			const pages = getCurrentPages()
			const cur = pages.length ? pages[pages.length - 1] : null
			if (cur && cur.route) {
				const q = cur.options || (cur.$page && cur.$page.options) || {}
				const qs = Object.keys(q).map(k => k + '=' + encodeURIComponent(q[k])).join('&')
				uni.setStorageSync('login_before_url', '/' + cur.route + (qs ? '?' + qs : ''))
			}
			this.$emit('close')
			uni.navigateTo({ url: '/others/login/login' })
		},
		openDoc(key, fallback) {
			const url = this.mallSetting()[key] || (getApp().globalData.domain + '/' + fallback + '.html')
			uni.setStorageSync('temp_url', url)
			uni.navigateTo({ url: '/others/web/web' })
		},
	},
}
</script>

<style lang="scss" scoped>
.ls-mask {
	position: fixed; left: 0; right: 0; top: 0; bottom: 0;
	// 要盖住商城自定义底栏(tabbar-container 是 9999)
	z-index: 10000;
	background: rgba(0, 0, 0, 0);
	display: flex; align-items: center; justify-content: center;
	transition: background 0.25s ease;
	&.is-in { background: rgba(0, 0, 0, 0.5); }
	.ls-card { transform: scale(0.9); opacity: 0; transition: transform 0.25s ease, opacity 0.25s ease; }
	&.is-in .ls-card { transform: scale(1); opacity: 1; }
}
.ls-card {
	width: 600rpx;
	background: #fff;
	border-radius: 36rpx;
	overflow: hidden;
	position: relative;
}
.ls-close {
	position: absolute; right: 20rpx; top: 20rpx; z-index: 2;
	width: 52rpx; height: 52rpx; border-radius: 50%;
	background: rgba(255, 255, 255, 0.75);
	display: flex; align-items: center; justify-content: center;
}
.ls-head {
	// 主题色的浅底:主题色铺底,上面盖一层 88% 的白(主题色是后台配的任意值,没法直接算浅色)
	background: var(--ls-theme);
	position: relative;
	padding: 48rpx 32rpx 32rpx;
	display: flex; flex-direction: column; align-items: center;
	&::before {
		content: ''; position: absolute; left: 0; right: 0; top: 0; bottom: 0; z-index: 0;
		background: #fff; opacity: 0.88;
	}
}
// 内容压在白罩上面。⛔ 别写成 `> * { position: relative }`:小程序组件样式不支持通配符,
// 那条规则整条失效,白罩就盖在 logo 和标题上面(H5 正常、小程序发白,9/30 真机踩到)
.ls-logo, .ls-mall, .ls-title, .ls-bf-glyph { position: relative; z-index: 1; }
.ls-bf-glyph { display: flex; align-items: center; justify-content: center; }
.ls-logo {
	width: 112rpx; height: 112rpx; border-radius: 50%;
	background: #fff; border: 4rpx solid #fff;
	box-shadow: 0 6rpx 20rpx rgba(0, 0, 0, 0.08);
	display: flex; align-items: center; justify-content: center; overflow: hidden;
}
.ls-logo-img { width: 100%; height: 100%; }
.ls-logo-text { font-size: 44rpx; font-weight: 700; color: var(--ls-theme); }
.ls-mall { margin-top: 14rpx; font-size: 22rpx; color: #8a8a8a; }
.ls-title { margin-top: 10rpx; font-size: 34rpx; font-weight: 700; color: #2c2c2a; text-align: center; }
.ls-benefits { display: flex; padding: 28rpx 20rpx 8rpx; }
.ls-bf { flex: 1; display: flex; flex-direction: column; align-items: center; }
.ls-bf-icon {
	width: 80rpx; height: 80rpx; border-radius: 24rpx;
	background: #f7f5f3;
	display: flex; align-items: center; justify-content: center;
	position: relative; overflow: hidden;
	&::before { content: ''; position: absolute; left: 0; right: 0; top: 0; bottom: 0; z-index: 0; background: var(--ls-theme); opacity: 0.1; }
}
.ls-bf-text { margin-top: 12rpx; font-size: 22rpx; color: #444441; text-align: center; padding: 0 6rpx; }
.ls-actions { padding: 28rpx 36rpx 8rpx; }
.ls-primary {
	height: 88rpx; line-height: 88rpx; border-radius: 44rpx; margin: 0;
	background: var(--ls-theme); color: #fff; font-size: 30rpx; font-weight: 600;
	&::after { border: none; }
}
.ls-primary-hover { opacity: 0.85; }
.ls-other { margin-top: 22rpx; text-align: center; font-size: 26rpx; color: var(--ls-theme); }
.ls-later { margin-top: 18rpx; text-align: center; font-size: 24rpx; color: #9b9b9b; padding-bottom: 8rpx; }
.ls-agree {
	display: flex; align-items: flex-start; justify-content: center; gap: 10rpx;
	padding: 12rpx 36rpx 32rpx;
	&.is-shake { animation: ls-shake 0.4s; }
}
.ls-check {
	width: 28rpx; height: 28rpx; border-radius: 50%; border: 2rpx solid #c8c8c8; margin-top: 4rpx; flex-shrink: 0;
	display: flex; align-items: center; justify-content: center;
	&.is-on { background: var(--ls-theme); border-color: var(--ls-theme); }
}
.ls-agree-text { font-size: 22rpx; color: #9b9b9b; line-height: 1.5; }
.ls-link { color: var(--ls-theme); }
@keyframes ls-shake {
	0%, 100% { transform: translateX(0); }
	25% { transform: translateX(-10rpx); }
	75% { transform: translateX(10rpx); }
}
</style>
