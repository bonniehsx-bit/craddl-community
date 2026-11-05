<template>
	<view class="page-container">


		<!-- 2. 表单与搜索区域 -->
		<view class="content-wrapper">
			<view class="form-card">
				<!-- 用户名/ID 输入框 -->
				<view class="form-item">
					<view class="item-label">搜索用户</view>
					<input placeholder="请输入用户ID" v-model="keyword"
						class="search-input-native" style="font-size:28rpx;padding:18rpx 24rpx;border:1rpx solid #e4e7ed;border-radius:8rpx;" />
				</view>

				<!-- 搜索按钮 -->
				<view class="search-btn-wrapper">
					<tui-button type="danger" shape="circle" :loading="searchLoading" @click="searchUser">搜索用户</tui-button>
				</view>

				<!-- 3. 搜索结果展示 -->
				<!-- 无结果时 -->
				<view v-if="searched && !searchResult" class="empty-result">
					<tui-no-data :fixed="false">未搜索到相关用户</tui-no-data>
				</view>


				<!-- 有结果时 -->
				<view v-if="searchResult" class="result-card">
					<view class="user-item">
						<image :src="searchResult.avatar_url" mode="aspectFill" style="width:70rpx;height:70rpx;border-radius:50%;" />
						<view class="user-info">
							<view class="name">{{ searchResult.nickname }}</view>
							<view class="mobile">ID: {{ searchResult.id }} | 手机号: {{ searchResult.mobile || '未绑定' }}
							</view>
						</view>
					</view>
				</view>
			</view>
		</view>

		<!-- 4. 底部确认添加按钮 -->
		<view class="bottom-button-container">
			<tui-button type="danger" shape="circle" :disabled="!searchResult || addLoading" :loading="addLoading" @click="addClerk">确认添加核销员</tui-button>
		</view>

	</view>
</template>

<script>
	// 您的 script 逻辑部分，无需修改
	export default {
		data() {
			return {
				keyword: '', // 搜索关键词
				searchLoading: false,
				addLoading: false,
				searched: false, // 是否已执行过搜索
				searchResult: null, // 存储搜索到的单个用户对象

				// uView 按钮样式
				searchButtonStyle: {
					backgroundColor: '#2953A8',
					color: '#fff',
					height: '44px'
				},
				confirmButtonStyle: {
					backgroundColor: '#2953A8',
					color: '#fff',
					height: '48px',
					fontSize: '16px'
				}
			};
		},
		methods: {
			goBack() {
				uni.navigateBack();
			},
			async searchUser() {
				if (!this.keyword) {
					// 这里假设您在 http 封装中挂载了 toast 方法
					// 如果没有，可以换成 uni.showToast
					uni.showToast({
						title: '请输入搜索关键词',
						icon: 'none'
					});
					return;
				}
				this.searchLoading = true;
				this.searched = true;
				this.searchResult = null; // 重置上次结果

				try {
					// 假设您的 this.$http.request 是封装好的请求
					const res = await this.$request({
						url: '/plugin/mch/admin/clerk/search-user',
						data: {
							keyword: this.keyword
						}
					});

					if (res.code == 0) {
						const list = res.data.list; // 将返回的 user 对象赋值
						if (!list || list.length < 0) {
							return;
						}
						console.log(list);
						this.searchResult = list[0];
					} else {
						uni.showToast({
							title: res.msg,
							icon: 'none'
						});
					}
				} catch (e) {
					console.error(e);
					uni.showToast({
						title: '搜索失败，请稍后再试',
						icon: 'none'
					});
				} finally {
					this.searchLoading = false;
				}
			},
			async addClerk() {
				if (!this.searchResult) {
					uni.showToast({
						title: '请先搜索并选中一个用户',
						icon: 'none'
					});
					return;
				}

				this.addLoading = true;
				try {
					const res = await this.$http.request({
						url: '/plugin/mch/admin/clerk/add',
						method: 'POST',
						data: {
							user_id: this.searchResult.id
						}
					});

					if (res.code === 0) {
						uni.showToast({
							title: '添加成功',
							icon: 'success'
						});
						// 添加成功后，返回上一页并通知刷新
						uni.$emit('clerkAdded'); // 发送一个全局事件
						setTimeout(() => uni.navigateBack(), 800);
					} else {
						uni.showToast({
							title: res.msg,
							icon: 'none'
						});
					}
				} catch (e) {
					console.error(e);
					uni.showToast({
						title: '添加失败，请稍后再试',
						icon: 'none'
					});
				} finally {
					this.addLoading = false;
				}
			}
		}
	}
</script>

<style lang="scss" scoped>
	// 页面整体布局
	.page-container {
		background-color: #f8f8f8;
		min-height: 100vh;
		display: flex;
		flex-direction: column;
	}

	.content-wrapper {

		height: 100vh;
		flex: 1; // 占据剩余空间
	}

	// 白色卡片样式
	.form-card {
		background-color: #ffffff;
		padding: 32rpx;
		border-radius: 16rpx;
	}

	.form-item {
		margin-bottom: 30rpx;

		.item-label {
			font-size: 28rpx;
			color: #333333;
			margin-bottom: 20rpx;
		}
	}

	// 搜索按钮容器
	.search-btn-wrapper {
		margin-top: 40rpx;
	}

	// 空状态
	.empty-result {
		margin-top: 60rpx;
	}

	// 搜索结果卡片
	.result-card {
		margin-top: 40rpx;
		border-top: 1px solid #eee;
		padding-top: 40rpx;
	}

	.user-item {
		display: flex;
		align-items: center;
	}

	.user-info {
		margin-left: 24rpx;
		flex: 1;

		.name {
			font-size: 30rpx;
			color: #333;
			font-weight: bold;
		}

		.mobile {
			font-size: 26rpx;
			color: #888;
			margin-top: 10rpx;
		}
	}

	// 底部按钮容器
	.bottom-button-container {
		position: fixed;
		bottom: 0;
		left: 0;
		width: 100%;
		background-color: #ffffff;
		padding: 20rpx 30rpx;
		border-top: 1px solid #f0f0f0;
		// 使用 fixed 布局的替代方案，更稳定
		box-sizing: border-box;
		box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
	}
</style>