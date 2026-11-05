<template>
	<tui-page>
		<view class="container" :style="{ '--theme-color': themeColor }">
			
			<view class="tui-address-list">
				<view v-if="list.length === 0 && !loading" class="empty-state">
					<tui-no-data :fixed="false"
						@click="addAddress">
						<text class="empty-text">您还没有收货地址哦~</text>
					</tui-no-data>
				</view>

				<view class="tui-address-item" v-for="(item, index) in list" :key="index">
					<view class="tui-address-content" hover-class="address-hover" :hover-stay-time="150" @click="selectAddress(item)">
						<view class="tui-address-main">
							<text class="tui-address-name">{{ item.name }}</text>
							<text class="tui-address-tel">{{ item.mobile }}</text>
						</view>
						<view class="tui-address-detail">
							<text class="tui-address-label" v-if="item.is_default === 1">默认</text>
							<text class="address-text">{{ item.address }}</text>
						</view>
					</view>
					
					<view class="tui-address-actions">
						<view class="tui-address-default" @click="setDefault(item.id)">
							<tui-icon :name="item.is_default === 1 ? 'circle-fill' : 'circle'"
								:color="item.is_default === 1 ? themeColor : '#C0C4CC'" :size="20"></tui-icon>
							<text class="default-text" :class="{'is-default': item.is_default === 1}">
								{{ item.is_default === 1 ? '默认地址' : '设为默认' }}
							</text>
						</view>
						
						<view class="tui-actions-right">
							<view class="tui-action-btn" hover-class="btn-hover" @click="editAddress(item.id)">
								<tui-icon name="edit" color="#909399" :size="24"></tui-icon>
								<text>编辑</text>
							</view>
							<view class="tui-action-btn delete-btn" hover-class="btn-hover" @click="deleteAddress(item.id)">
								<tui-icon name="delete" color="#909399" :size="18"></tui-icon>
								<text>删除</text>
							</view>
						</view>
					</view>
				</view>
			</view>
			
			<view class="bottom-placeholder"></view>
			
			<view class="tui-add-address-container">
				<view class="tui-safe-area">
					<tui-button shadow shape="circle" :background="themeColor" height="88rpx" size="32" @click="addAddress">
						<text class="add-icon">+</text> 新增收货地址
					</tui-button>
				</view>
			</view>

		</view>
	</tui-page>
</template>

<script>
	// [核心] 引入 Vuex
	import { mapState } from 'vuex';

	export default {
		data() {
			return {
				loading: false,
				list: [],
				type: ''
			}
		},
		computed: {
			// [核心] 获取主题色，默认红色
			...mapState('mall', {
				themeColor: state => state.themeColor || '#D71F1F'
			})
		},
		onLoad(options) {
			// [核心] 设置导航栏颜色
			this.setNavBarColor();
			
			if (options.type === 'SELECT') {
				this.type = options.type;
			}
		},
		onShow() {
			this.setNavBarColor();
			this.getList();
		},
		watch: {
			// [核心] 监听主题色变化
			themeColor(val) {
				if (val) this.setNavBarColor();
			}
		},
		methods: {
			// [核心] 动态设置导航栏背景色
			setNavBarColor() {
				if (this.themeColor) {
					uni.setNavigationBarColor({
						frontColor: '#ffffff',
						backgroundColor: this.themeColor,
						animation: { duration: 300, timingFunc: 'easeIn' }
					});
				}
			},

			async getList() {
				this.loading = true;
				try {
					const res = await this.$request({
						url: '/api/user/address-list'
					});
					if (res.code === 0) {
						this.list = res.data.list;
					}
				} finally {
					this.loading = false;
					uni.stopPullDownRefresh();
				}
			},
			async handleAction(url, data, successMessage) {
				uni.showLoading({
					title: '请稍候...',
					mask: true
				});
				try {
					const res = await this.$request({
						url,
						method: 'POST',
						data
					});
					if (res.code === 0) {
						uni.showToast({
							title: successMessage,
							icon: 'success'
						});
						this.getList(); 
					} else {
						this.$showModal(res.msg);
					}
				} finally {
					uni.hideLoading();
				}
			},
			setDefault(id) {
				this.handleAction('/api/user/set-default-address', {
					id
				}, '设置成功');
			},
			deleteAddress(id) {
				uni.showModal({
					title: '提示',
					content: '确定要删除该收货地址吗？',
					confirmColor: this.themeColor,
					success: (res) => {
						if (res.confirm) {
							this.handleAction('/api/user/address-delete', {
								id
							}, '删除成功');
						}
					}
				});
			},
			selectAddress(item) {
				if (this.type === 'SELECT') {
					uni.setStorageSync('SELECT_ADDRESS', item);
					uni.navigateBack({
						delta: 1
					});
				}
			},
			addAddress() {
				uni.navigateTo({
					url: "/others/address-edit/address-edit"
				});
			},
			editAddress(id) {
				uni.navigateTo({
					url: `/others/address-edit/address-edit?id=${id}`
				});
			}
		},
		onPullDownRefresh() {
			this.getList();
		}
	}
</script>

<style lang="scss" scoped>
	/* [核心] 定义 SCSS 变量指向 CSS 变量 */
	$theme-color: var(--theme-color);

	.container {
		/* 改为柔和的灰底色，让白色卡片更凸显 */
		background-color: #F5F7FA;
		padding: 24rpx;
		min-height: 100vh;
		box-sizing: border-box;
	}

	.empty-state {
		padding-top: 150rpx;
		.empty-text {
			color: #909399;
			font-size: 28rpx;
			margin-top: 20rpx;
		}
	}

	.tui-address-item {
		background-color: #FFFFFF;
		border-radius: 24rpx;
		margin-bottom: 24rpx;
		/* 更柔和现代的阴影 */
		box-shadow: 0 4rpx 24rpx rgba(0, 0, 0, 0.03);
		overflow: hidden;
		transition: all 0.2s linear;
	}

	/* 点击按压反馈 */
	.address-hover {
		background-color: #FAFAFA;
	}

	.tui-address-content {
		padding: 32rpx 30rpx 24rpx;
		border-bottom: 1rpx dashed #EBEEF5;
	}

	.tui-address-main {
		display: flex;
		align-items: flex-end;
		margin-bottom: 16rpx;
	}

	.tui-address-name {
		font-size: 34rpx;
		font-weight: 600;
		color: #303133;
		max-width: 320rpx;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		line-height: 1.2;
	}

	.tui-address-tel {
		font-size: 28rpx;
		color: #909399;
		margin-left: 24rpx;
		line-height: 1.2;
		font-weight: 500;
	}

	.tui-address-detail {
		display: flex;
		align-items: flex-start;
		margin-top: 10rpx;
	}

	/* 默认标签更精致 */
	.tui-address-label {
		display: inline-block;
		padding: 2rpx 12rpx;
		border: 1rpx solid $theme-color;
		color: $theme-color;
		border-radius: 8rpx;
		font-size: 20rpx;
		margin-right: 16rpx;
		margin-top: 4rpx;
		flex-shrink: 0;
		font-weight: 500;
	}

	.address-text {
		font-size: 26rpx;
		color: #606266;
		line-height: 1.6;
		flex: 1;
		word-break: break-all;
	}

	.tui-address-actions {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 20rpx 30rpx;
	}

	.tui-address-default {
		display: flex;
		align-items: center;
		padding: 10rpx 0;
	}

	.default-text {
		margin-left: 12rpx;
		font-size: 26rpx;
		color: #909399;
		transition: color 0.2s;
		
		&.is-default {
			color: #303133;
			font-weight: 500;
		}
	}

	.tui-actions-right {
		display: flex;
		align-items: center;
	}

	.tui-action-btn {
		display: flex;
		align-items: center;
		margin-left: 40rpx;
		padding: 10rpx 0;
		transition: opacity 0.2s;
		
		text {
			margin-left: 8rpx;
			font-size: 26rpx;
			color: #606266;
		}
	}

	/* 图标按钮按压效果 */
	.btn-hover {
		opacity: 0.6;
	}

	/* 底部占位区域 */
	.bottom-placeholder {
		height: calc(140rpx + env(safe-area-inset-bottom));
		width: 100%;
	}

	/* 悬浮底部按钮 */
	.tui-add-address-container {
		width: 100%;
		position: fixed;
		left: 0;
		bottom: 0;
		z-index: 99;
		background-color: #FFFFFF;
		padding: 20rpx 30rpx;
		box-sizing: border-box;
		box-shadow: 0 -4rpx 20rpx rgba(0, 0, 0, 0.04);
	}

	.tui-safe-area {
		padding-bottom: env(safe-area-inset-bottom);
		
		.add-icon {
			font-size: 36rpx;
			font-weight: normal;
			margin-right: 6rpx;
			vertical-align: middle;
			margin-top: -4rpx;
			display: inline-block;
		}
	}
</style>