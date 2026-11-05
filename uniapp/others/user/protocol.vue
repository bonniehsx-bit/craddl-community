<template>
	<view style="height: 100ch;width: 100%;">
		<textarea v-model="protocol" :maxlength="-1" style="height: 100vh;width: 100%;" disabled></textarea>
	</view>
</template>

<script>
	export default {
		data() {
			return {
				protocol: '',
				type: 'register',
			}
		},
		onLoad(e) {
			if (e.type) {
				this.type = e.type;
			}
			if (this.type == 'register') {
				uni.setNavigationBarTitle({
					title: '用户注册协议'
				})
			} else {
				uni.setNavigationBarTitle({
					title: '用户隐私协议'
				})
			}
			this.getProtocol();
		},
		methods: {
			getProtocol() {
				this.$request({
					url: '/api/mall/user-protocol',
					data: {
						type: this.type,
					}
				}).then(res => {
					if (res.code == 0) {
						this.protocol = res.data.user_protocol;
					}
				})
			},
		}
	}
</script>

<style>

</style>
