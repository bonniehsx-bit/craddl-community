<template>
	<tui-page ref="page">
		<!-- 主题色走全局 themeStyle mixin 注入 CSS 变量(小程序端必须是字符串,对象会丢键) -->
		<view class="fb-page" :style="themeStyle">
			<view class="fb-body">
				<textarea v-model="content" class="fb-textarea" maxlength="500"
					placeholder="您的意见对我们很重要" placeholder-class="fb-ph" />
				<view class="fb-count">{{ content.length }}/500</view>

				<!-- 这句是微信小程序审核要求的自有投诉渠道声明,只在微信小程序里才有意义。
				     H5/App 里显示"非微信官方平台渠道"会让用户莫名其妙 —— 他压根不在微信里 -->
				<!-- #ifdef MP-WEIXIN -->
				<view class="fb-tip">
					此投诉为本应用自有投诉渠道，非微信官方平台渠道
				</view>
				<!-- #endif -->

				<view class="fb-submit-wrap">
					<tui-button shape="circle" height="88rpx" :background="themeColor"
						:disabled="!content.trim() || is_loading" :loading="is_loading"
						@click="submit">提交</tui-button>
				</view>
			</view>
		</view>
	</tui-page>
</template>
<script>
	export default {
		data() {
			return {
				content: '',
				is_loading: false,
			}
		},
		methods: {
			submit() {
				if (!this.content) {
					uni.showModal({
						title: '提示',
						content: '反馈内容不能为空',
						success: (e) => {

						}
					})
					return
				}
				this.is_loading = true;
				this.$request({
					url: "/api/user/feedback",
					data: {
						content: this.content,
					},
					method: 'post'
				}).then(res => {
					this.is_loading = false;
					if (res.code == -1) {
						this.$refs.page.showLoginModalWindow();
						return;
					}
					if (res.code == 0) {
						uni.showModal({
							title: '提示',
							content: res.msg,
							success: (e) => {
								uni.navigateBack({
									delta: 1
								})
							}
						})
					} else {
						uni.showModal({
							title: '提示',
							content: res.msg,
							success: (e) => {

							}
						})
					}
				})
			},
		}
	}
</script>

<style scoped>
	.fb-page {
		min-height: 100vh;
		background: #F7F8FA;
		padding-top: 24rpx;
	}

	.fb-body {
		width: 92%;
		margin: 0 auto;
		background: #fff;
		border-radius: 24rpx;
		padding: 32rpx 28rpx 40rpx;
		box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.04);
	}

	.fb-textarea {
		width: 100%;
		height: 380rpx;
		box-sizing: border-box;
		padding: 24rpx;
		border-radius: 16rpx;
		background: #F7F8FA;
		border: 2rpx solid transparent;
		font-size: 28rpx;
		line-height: 1.7;
		color: #333;
	}
	/* 聚焦态用主题色描边,而不是写死的红 */
	.fb-textarea:focus {
		border-color: var(--theme-color);
		background: #fff;
	}
	.fb-ph {
		color: #b9bcc2;
		font-size: 28rpx;
	}

	.fb-count {
		text-align: right;
		font-size: 24rpx;
		color: #b9bcc2;
		margin-top: 12rpx;
	}

	.fb-tip {
		margin-top: 20rpx;
		font-size: 24rpx;
		line-height: 1.7;
		color: #909399;
		background: var(--theme-color-bg);
		border-radius: 12rpx;
		padding: 16rpx 20rpx;
	}

	.fb-submit-wrap {
		margin-top: 48rpx;
	}
</style>