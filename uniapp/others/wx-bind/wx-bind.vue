<template>
	<view class="container">
		<web-view :src="bindUrl" @message="onMessage"></web-view>
	</view>
</template>

<script>
import config from '@/config.json'

// 绑定公众号接收推送通知（平台页面）。
// 里面是公众号网页授权(snsapi_base)，授权完后端把 openid 写进 user.wechat_openid，
// 结果页通过 uni.postMessage + uni.navigateBack 回到这里。
// 原来这个页面在 hardware 插件分包里（plugins1/hardware/bottle/wx-bind），
// 核心设置页和别的插件都要跳它 —— hardware 没打进包就跳不过去，所以提到核心。
export default {
	data() {
		return {
			bindUrl: ''
		}
	},
	onLoad() {
		const token  = uni.getStorageSync('ACCESS_TOKEN') || ''
		const mallId = config.mall_id || 1
		if (!token) {
			uni.showToast({ title: '请先登录', icon: 'none' })
			setTimeout(() => uni.navigateBack(), 800)
			return
		}
		// 后端域名走 globalData.domain（H5=当前域名 / MP=已替换的真域名），不直接读 config 占位符
		const base = getApp().globalData.domain
		this.bindUrl = `${base}/index.php?r=wechat/auth/bind-notification&mall_id=${mallId}&user_token=${token}`
	},
	methods: {
		onMessage(e) {
			// 只有 navigateBack 触发时才会收到，status: success / fail
			const status = (e.detail && e.detail.data && e.detail.data[0] && e.detail.data[0].status) || ''
			if (status === 'success') {
				uni.showToast({ title: '推送通知已开启', icon: 'success' })
			} else if (status === 'fail') {
				uni.showToast({ title: '绑定失败，请重试', icon: 'none' })
			}
		}
	}
}
</script>

<style>
.container { flex: 1; }
</style>
