<template>
	<view>
		<web-view :src="url" @message="handleMessage"></web-view>
	</view>
</template>

<script>
	export default {
		data() {
			return {
				url: '',
			}
		},
		onLoad(e) {
			if (!e||!e.url) {
				//#ifdef H5
				window.location.replace(uni.getStorageSync('temp_url'))
				return;
				//#endif
				this.url = uni.getStorageSync('temp_url');
			 
				uni.removeStorageSync('temp_url');
				return;
			}
			if (!e.url || e.url == '') {
				uni.showModal({
					title: '提示',
					content: '未传入链接',
					confirmText: '我知道了',
					showCancel: false
				})
				return;
			}
			//#ifdef H5
			window.location.href = e.url;
			return;
			//#endif
			this.url = e.url;
		},
		methods: {
			handleMessage(evt) {
            
				if (evt.detail.data[0].action == 'back') {
					uni.navigateBack({
						delta: 1
					})
				}
			}
		}
	}
</script>

<style>

</style>
