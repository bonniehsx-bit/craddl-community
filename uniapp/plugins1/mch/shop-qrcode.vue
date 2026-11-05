<template>
	<view class="page-container" :style="themeStyle">
		<!-- 全屏加载状态 -->
		<view v-if="isLoading" class="loading-container">
			<view class="circle-loading" style="width:30px;height:30px;border:3px solid #e0e0e0;border-top-color:var(--theme-color);border-radius:50%;animation:spin 0.8s linear infinite;"></view>
			<text class="loading-text">正在生成门店码...</text>
		</view>

		<!-- 加载成功后显示 -->
		<template v-else-if="!isLoading && posterImageUrl">
			<!-- 海报预览区 -->
			<view class="poster-preview">
				<!-- 
				  直接展示后端返回的、已经合成好的海报图片。
				  show-menu-by-longpress 属性可以在微信小程序中实现长按弹出“识别二维码”、“保存图片”等菜单。
				-->
				<image :src="posterImageUrl" class="poster-image" mode="widthFix" show-menu-by-longpress></image>
			</view>

			<!-- 底部操作按钮 -->
			<view class="action-footer">
				<button class="save-btn" @click="savePoster">保存海报到相册</button>
			</view>
		</template>

		<!-- 加载失败或无数据时显示 -->
		<view v-else class="error-container">
			<tui-no-data :fixed="false">获取门店码失败</tui-no-data>
		</view>
	</view>
</template>

<script>
	import { saveImageWithTip } from "@/utils/permissionTip.js";
	export default {
		data() {
			return {
				isLoading: true,
				posterImageUrl: '', // 存储后端返回的最终海报图片 URL
			};
		},
		onLoad() {
			uni.setNavigationBarTitle({
				title: '门店推广码'
			});
			this.fetchPoster();
		},
		methods: {
			/**
			 * [核心] 从后端获取已生成好的海报图片 URL
			 */
			async fetchPoster() {
				this.isLoading = true;
				try {
					// 调用我们最终确定的后端接口
					const res = await this.$request({
						url: '/plugin/mch/admin/mch/poster'
					});

					if (res.code === 0 && res.data.poster_url) {
						this.posterImageUrl = res.data.poster_url;
					} else {
						// 接口返回失败或 poster_url 为空
						uni.showToast({
							title: res.msg || '获取海报失败',
							icon: 'none'
						});
					}
				} catch (e) {
					console.error("获取海报接口请求失败", e);
					uni.showToast({
						title: '网络开小差了',
						icon: 'none'
					});
				} finally {
					this.isLoading = false;
				}
			},

			/**
			 * 保存海报到用户手机相册
			 */
			savePoster() {
				if (!this.posterImageUrl) {
					return uni.showToast({
						title: '海报图片不存在',
						icon: 'none'
					});
				}

				uni.showLoading({
					title: '正在保存...'
				});

				// 步骤一：先将远程图片下载到本地临时文件
				uni.downloadFile({
					url: this.posterImageUrl,
					success: (downloadRes) => {
						if (downloadRes.statusCode === 200) {
							// 步骤二：再将本地临时文件保存到相册
							saveImageWithTip({
								filePath: downloadRes.tempFilePath,
								success: () => {
									uni.showToast({
										title: '已保存到相册',
										icon: 'success'
									});
								},
								fail: (saveErr) => {
									// 引导用户开启相册权限
									if (saveErr.errMsg.includes('auth deny')) {
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
					fail: (downloadErr) => {
						uni.hideLoading();
						uni.showToast({
							title: '图片下载失败，请检查网络',
							icon: 'none'
						});
						console.error("下载海报失败", downloadErr);
					}
				});
			}
		}
	}
</script>

<style lang="scss" scoped>
	page {
		background-color: #f5f6fa;
	}

	.page-container {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: space-between;
		height: 100vh;
		background-color: #f5f6fa;
	}

	.loading-container,
	.error-container {
		flex: 1;
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
		width: 100%;
		gap: 20rpx;

		.loading-text {
			font-size: 28rpx;
			color: #999;
		}
	}

	.poster-preview {
		flex: 1;
		display: flex;
		justify-content: center;
		align-items: center;
		width: 100%;
		padding: 40rpx;
		box-sizing: border-box;
	}

	.poster-image {
		width: 100%;
		max-height: 100%;
		box-shadow: 0 10rpx 40rpx rgba(0, 0, 0, 0.1);
		border-radius: 20rpx;
	}

	.action-footer {
		width: 100%;
		padding: 20rpx 40rpx;
		position: fixed;
		bottom: 0;
		left: 0;
		background-color: #fff;
		box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.05);
		box-sizing: border-box;
		flex-shrink: 0;

		.save-btn {
			background: linear-gradient(to right, #3F82FF, #2952A3);
			color: white;
			height: 88rpx;
			line-height: 88rpx;
			border-radius: 44rpx;
			font-size: 32rpx;
			border: none;

			&::after {
				border: none;
			}
		}
	}
	@keyframes spin {
		to { transform: rotate(360deg); }
	}
</style>