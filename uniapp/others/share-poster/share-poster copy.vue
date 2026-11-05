<template>
	<tui-page ref="page" :customNavbar="false">
		<!-- 全屏加载 -->
		<view v-if="isLoading" class="loading-container">
			<view class="loading-indicator"></view>
			<text class="loading-text">正在生成海报...</text>
		</view>

		<!-- [重构] 内容展示区：将海报和按钮包裹在一起，作为一个整体居中 -->
		<view class="content-container" v-else-if="poster_url">
			<view class="poster-card">
				<image :src="poster_url" class="poster-image" mode="aspectFit" @click="preview"></image>
			</view>
			<button class="save-btn" @click="savePoster">保存至相册</button>
		</view>

		<!-- 错误或空状态 -->
		<view v-else class="empty-container">
			<text>海报加载失败</text>
		</view>

	</tui-page>
</template>

<script>
	import { saveImageWithTip } from "@/utils/permissionTip.js";
	export default {
		data() {
			return {
				isLoading: true,
				poster_url: '',
			}
		},
		onLoad(e) {
			this.getPoster();
		},
		methods: {
			// 图片预览
			preview() {
				if (!this.poster_url) return;
				uni.previewImage({
					urls: [this.poster_url],
					current: this.poster_url
				})
			},
			// 获取海报
			getPoster() {
				this.isLoading = true;
				this.$request({
					url: '/api/poster/share-poster',
					method: 'get'
				}).then(res => {
					if (res.code == -1) {
						this.$refs.page.showLoginModalWindow();
						return;
					}
					if (res.code == 0) {
						this.poster_url = res.data.url;
					} else {
						uni.showModal({
							title: '提示',
							content: res.msg || '获取海报失败'
						});
					}
				}).finally(() => {
					this.isLoading = false;
				});
			},
			// 保存海报到相册
			savePoster() {
				if (!this.poster_url) {
					return uni.showToast({
						title: '海报还未生成',
						icon: 'none'
					});
				}
				uni.showLoading({
					title: '保存中...'
				});
				// 先下载图片
				uni.downloadFile({
					url: this.poster_url,
					success: (res) => {
						if (res.statusCode === 200) {
							// 再保存到相册
							saveImageWithTip({
								filePath: res.tempFilePath,
								success: () => {
									uni.showToast({
										title: '已保存到相册',
										icon: 'success'
									});
								},
								fail: (err) => {
									if (err.errMsg && err.errMsg.includes('auth deny')) {
										uni.showModal({
											title: '提示',
											content: '需要您授权保存相册才能使用该功能',
											showCancel: false,
											success: () => uni.openSetting()
										});
									} else {
										uni.showToast({
											title: '保存失败',
											icon: 'none'
										});
									}
								},
								complete: () => {
									uni.hideLoading();
								}
							});
						} else {
							uni.hideLoading();
							uni.showToast({
								title: '图片下载失败',
								icon: 'none'
							});
						}
					},
					fail: () => {
						uni.hideLoading();
						uni.showToast({
							title: '图片下载失败',
							icon: 'none'
						});
					}
				});
			}
		}
	}
</script>

<style lang="scss" scoped>
	/* 整体页面布局 */
	page {
		display: flex;
		flex-direction: column;
		height: 100vh;
		background-color: #ffffff;
		overflow: hidden; // 禁止页面滚动
	}

	.loading-container,
	.empty-container {
		flex: 1;
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
		color: #999999;
	}

	.loading-indicator {
		width: 60rpx;
		height: 60rpx;
		border-radius: 50%;
		border: 4rpx solid #e0e0e0;
		border-top-color: #3498db;
		animation: spin 1s linear infinite;
		margin-bottom: 20rpx;
	}

	@keyframes spin {
		to {
			transform: rotate(360deg);
		}
	}

	/* [新增] fade-in 动画 */
	@keyframes fadeIn {
		from {
			opacity: 0;
			transform: translateY(20px);
		}

		to {
			opacity: 1;
			transform: translateY(0);
		}
	}

	/* [重构] 内容容器 */
	.content-container {
		flex: 1;
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
		padding: 40rpx;
		box-sizing: border-box;
		animation: fadeIn 0.5s ease-out forwards; // 应用加载动画
	}

	.poster-card {
		background-color: #fff;
		border-radius: 20rpx;
		overflow: hidden;
		box-shadow: 0 10rpx 40rpx rgba(0, 0, 0, 0.1);
		transition: transform 0.2s ease-in-out;
		/* [修改] 限制海报最大尺寸 */
		width: 100%;
		height: 70vh; // 限制最大高度，为按钮留出空间
		margin-bottom: 50rpx; // 与按钮的间距
		display: flex;

		&:active {
			transform: scale(0.98);
		}
	}

	.poster-image {
		width: 100%;
		height: 100%;
		display: block;
	}

	/* [重构] 保存按钮样式 */
	.save-btn {
		flex-shrink: 0;
		width: 80%;
		max-width: 600rpx;
		background: linear-gradient(to right, #4d82da, #3a5dbf);
		color: white;
		height: 88rpx;
		line-height: 88rpx;
		border-radius: 44rpx;
		font-size: 32rpx;
		border: none;
		box-shadow: 0 5rpx 15rpx rgba(52, 152, 219, 0.4);
		transition: all 0.2s ease;

		&::after {
			border: none;
		}

		&:active {
			transform: scale(0.98);
			opacity: 0.9;
		}
	}
</style>