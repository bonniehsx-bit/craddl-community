<template>
	<tui-page :backgroundColorF5="true">
		<view class="page-container" :style="themeStyle">
			<view class="goods-list">
				<view class="review-card" v-for="(item, i) in list" :key="i">

					<view class="goods-info">
						<image class="cover-img" :src="item.cover_pic" mode="aspectFill"></image>
						<view class="rating-box">
							<view class="title">描述相符</view>
							<view class="stars">
								<tui-rate :current="item.grade_level" :select_index="i" @change="change"
									:size="22"></tui-rate>
								<text class="rating-text">{{ getRatingText(item.grade_level) }}</text>
							</view>
						</view>
					</view>

					<view class="divider"></view>

					<view class="input-section">
						<view class="textarea-box">
							<textarea class="textarea" v-model="item.content" maxlength="200"
								placeholder-class="placeholder-style" placeholder="商品满足你的期待吗？说说你的使用心得，分享给想买的他们吧！">
							</textarea>
							<view class="word-count">{{item.content ? item.content.length : 0}}/200</view>
						</view>

						<view class="upload-section">
							<view class="section-title">
								上传图片 <text class="sub-title">(选填)</text>
							</view>
							<view class="upload-wrap">
								<tui-upload v-model="item.pic_list">
								</tui-upload>
							</view>
						</view>
					</view>
				</view>
			</view>

			<view class="bottom-space"></view>

			<view class="bottom-bar">
				<view class="btn-wrap">
					<tui-button :background="themeColor" @click="submit" shape="circle" width="100%" height="84rpx"
						:disabled="loading">
						{{ loading ? '发布中...' : '确认发布' }}
					</tui-button>
				</view>
			</view>
		</view>
	</tui-page>
</template>

<script>
	export default {
		data() {
			return {
				list: [],
				order_id: 0,
				loading: false,
			};
		},
		onLoad(options) {
			if (options.order_id) {
				this.order_id = options.order_id;
			}
			this.getDetail();
		},
		methods: {
			// 辅助方法：根据星级显示文字
			getRatingText(level) {
				const texts = ['', '非常差', '差', '一般', '好', '非常好'];
				return texts[level] || '非常好';
			},

			submit() {
				if (this.loading) return;

				this.loading = true;
				this.$request({
					url: '/api/goods/comment',
					data: {
						order_id: this.order_id,
						// v-model 已经自动更新了 item.pic_list，这里直接提交 this.list 即可
						data_list: JSON.stringify(this.list)
					},
					method: 'post'
				}).then(res => {
					this.loading = false;
					if (res.code == 0) {
						uni.showModal({
							title: '提示',
							content: res.msg || '评价成功',
							showCancel: false,
							success: function(e) {
								uni.navigateBack({
									delta: 1
								})
							}
						})
					} else {
						uni.showModal({
							title: '提示',
							content: res.msg,
							showCancel: false
						})
					}
				}).catch(() => {
					this.loading = false;
				})
			},

			getDetail() {
				this.$request({
					url: '/api/order/detail',
					data: {
						order_id: this.order_id
					},
				}).then(res => {
					if (res.code === 0) {
						const list = (res.data && res.data.list) ? res.data.list : [];
						for (let i in list) {
							let item = list[i];
							this.list.push({
								goods_id: item.goods_id,
								order_id: item.order_id,
								cover_pic: item.goods ? item.goods.cover_pic : '',
								grade_level: 5,
								content: '',
								pic_list: [], // v-model 绑定的初始空数组
							})
						}
					}
				});
			},

			change(e) {
				this.list[e.select_index].grade_level = e.index;
			}
		}
	};
</script>

<style lang="scss" scoped>
	.page-container {
		background-color: #F7F8FA;
		/* 浅灰底色，突出白卡片 */
		min-height: 100vh;
	}

	.goods-list {
		padding: 24rpx;
	}

	.review-card {
		background-color: #FFFFFF;
		border-radius: 24rpx;
		padding: 30rpx;
		margin-bottom: 24rpx;
		box-shadow: 0 4rpx 16rpx rgba(0, 0, 0, 0.02);
	}

	/* 商品信息与评分 */
	.goods-info {
		display: flex;
		align-items: center;

		.cover-img {
			width: 120rpx;
			height: 120rpx;
			border-radius: 12rpx;
			background-color: #f5f5f5;
			margin-right: 24rpx;
			flex-shrink: 0;
		}

		.rating-box {
			flex: 1;

			.title {
				font-size: 28rpx;
				font-weight: 600;
				color: #333333;
				margin-bottom: 12rpx;
			}

			.stars {
				display: flex;
				align-items: center;

				.rating-text {
					margin-left: 20rpx;
					font-size: 24rpx;
					color: #999999;
				}
			}
		}
	}

	/* 分割线 */
	.divider {
		height: 1rpx;
		background-color: #F0F0F0;
		margin: 30rpx 0;
	}

	/* 输入区 */
	.input-section {
		.textarea-box {
			background-color: #F7F8FA;
			/* 浅灰色输入框背景 */
			border-radius: 16rpx;
			padding: 24rpx;
			position: relative;

			.textarea {
				width: 100%;
				height: 200rpx;
				font-size: 28rpx;
				color: #333333;
				line-height: 1.5;
			}

			.word-count {
				position: absolute;
				bottom: 20rpx;
				right: 24rpx;
				font-size: 22rpx;
				color: #C0C4CC;
			}
		}
	}

	/* placeholder 样式 */
	.placeholder-style {
		color: #BDBDBD;
		font-size: 28rpx;
	}

	/* 上传区 */
	.upload-section {
		margin-top: 30rpx;

		.section-title {
			font-size: 28rpx;
			font-weight: 500;
			color: #333333;
			margin-bottom: 20rpx;

			.sub-title {
				font-size: 24rpx;
				color: #999999;
				margin-left: 8rpx;
				font-weight: normal;
			}
		}

		.upload-wrap {
			/* 让 tui-upload 内部自然排列 */
		}
	}

	/* 底部占位与操作栏 */
	.bottom-space {
		height: 140rpx;
		height: calc(140rpx + env(safe-area-inset-bottom));
	}

	.bottom-bar {
		position: fixed;
		bottom: 0;
		left: 0;
		width: 100%;
		background-color: #FFFFFF;
		box-shadow: 0 -4rpx 12rpx rgba(0, 0, 0, 0.03);
		padding: 20rpx 30rpx;
		padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
		box-sizing: border-box;
		z-index: 99;

		.btn-wrap {
			width: 100%;
		}
	}
</style>