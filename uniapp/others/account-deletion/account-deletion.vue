<template>
	<tui-page :customNavbar="false">
		<view class="container" v-if="user">
			<!-- 顶部导航占位 -->

			<!-- 警告与资产信息卡片 -->
			<view class="warning-card" v-if="user">
				<view class="warning-icon-wrapper">
					<!-- 使用 tui-icon 显示警告图标 -->
					<tui-icon name="warning" :size="30" color="#E43D33"></tui-icon>
				</view>
				<text class="warning-text">
					{{ mallName }}平台账号含有{{ $noun('balance') }}、{{ $noun('integral') }}、{{ $noun('commission') }}、{{ $noun('coupon') }}，可在{{ mallName }}入驻的商户购物时享受相应权益。注销后账号进入 120 天冷却期，冷却期内使用原手机号重新登录即可恢复账号与资产；超过 120 天未登录将视同永久注销，敏感信息将被清除，请谨慎操作！
				</text>

				<view class="asset-section">
					<text class="asset-title">您在{{ mallName }}账号现有资产</text>
					<view class="asset-item">
						<text class="asset-label">{{ $noun('balance') }}：</text>
						<text class="asset-value amount">{{ user.money }}元</text>
					</view>
					<view class="asset-item">
						<text class="asset-label">{{ $noun('integral') }}：</text>
						<text class="asset-value points">{{ user.integral }}{{ $noun('integral') }}</text>
					</view>
					<view class="asset-item">
						<text class="asset-label">{{ $noun('commission') }}：</text>
						<text class="asset-value reward">{{ user.price }}元</text>
					</view>
					<view class="asset-item">
						<text class="asset-label">{{ $noun('coupon') }}：</text>
						<text class="asset-value coupon">{{ user.coupon || 0 }}张</text>
						<!-- 假设 coupon 可能不存在，给个默认值 -->
					</view>
				</view>
			</view>

			<!-- 底部操作按钮 -->
			<view class="action-buttons">
				<button class="btn cancel-btn" @click="goBack">取消</button>
				<!-- [修改] 点击按钮改为显示协议弹窗 -->
				<button class="btn delete-btn" @click="showAgreementModal = true">继续注销</button>
			</view>
		</view>
		<view v-else class="loading-full">
			<!-- 可以添加一个加载中的提示 -->
			<text>加载中...</text>
		</view>

		<!-- [新增] 第一步：注销协议确认弹窗 -->
		<tui-modal :custom="true" :show="showAgreementModal" @cancel="showAgreementModal = false">
			<view class="agreement-modal-content">
				<view class="agreement-modal-header">
					<tui-icon name="warning" :size="20" color="#E43D33"></tui-icon>
					<text class="agreement-modal-title">注销</text>
					<uni-icons type="closeempty" size="20" color="#999" @click="showAgreementModal = false"></uni-icons>
				</view>
				<view class="agreement-modal-body">
					<text class="agreement-text">
						账号注销后进入 120 天冷却期，冷却期内无法使用 {{ mallName }} 服务，但账号和资产被完整保留。在冷却期内使用原手机号重新登录，即视为放弃注销、账号自动恢复。超过 120 天未登录，系统将视同永久注销并清除敏感信息。
					</text>
					<view class="agreement-checkbox-row" @click="toggleAgreeTerms">
						<radio :checked="agreedToTerms" color="#E43D33" style="transform:scale(0.7)" />
						<text class="agreement-label">我已阅读并同意上述账号注销说明</text>
					</view>
				</view>
				<view class="agreement-modal-footer">
					<button class="agreement-modal-btn cancel-agreement-btn"
						@click="showAgreementModal = false">取消</button>
					<!-- [修改] 继续注销按钮现在需要检查是否同意条款 -->
					<button class="agreement-modal-btn confirm-agreement-btn" :disabled="!agreedToTerms"
						@click="proceedToSmsVerification">继续注销</button>
				</view>
			</view>
		</tui-modal>

		<!-- 第二步：确认注销短信验证弹窗 -->
		<tui-modal :custom="true" :show="showConfirmModal" @cancel="showConfirmModal = false">
			<view class="modal-content">
				<view class="modal-header">
					<text class="modal-title">用户确认</text>
					<uni-icons type="closeempty" size="20" color="#999" @click="showConfirmModal = false"></uni-icons>
				</view>
				<view class="modal-body" v-if="user">
					<text class="modal-warning-text">
						您在{{ mallName }}平台现有资产：{{ $noun('balance') }} {{ user.money || 0 }} 元、{{ $noun('integral') }} {{ user.integral || 0 }}、{{ $noun('commission') }} {{ user.price || 0 }} 元、{{ $noun('coupon') }} {{ user.coupon || 0 }} 张。注销后进入 120 天冷却期，期内重新登录可恢复账户与资产，请确认是您本人操作！
					</text>
					<view class="input-row mobile-row">
						<text class="input-label">手机号</text>
						<input type="text" :value="user.mask_mobile || '加载中...'" disabled
							class="input-field mobile-input" />
					</view>
					<view class="input-row code-row">
						<text class="input-label">验证码</text>
						<input type="number" v-model="code" placeholder="请输入验证码" class="input-field code-input" />
						<button class="code-btn" @click="getCode" :disabled="is_send_code || smsLoading">
							{{ msg }}
						</button>
					</view>
				</view>
				<view class="modal-footer">
					<button class="modal-btn cancel-modal-btn" @click="showConfirmModal = false">取消</button>
					<button class="modal-btn confirm-modal-btn" @click="confirmDeletion" :loading="loading">确认</button>
				</view>
			</view>
		</tui-modal>

	</tui-page>
</template>

<script>
import {
	mapActions,
	mapMutations,
	mapState // [新增]
} from 'vuex';
export default {
	data() {
		return {
			showAgreementModal: false, // [新增] 控制协议弹窗
			agreedToTerms: false, // [新增] 是否同意条款
			showConfirmModal: false,
			code: '',
			user: null,
			loading: false, // 确认按钮loading
			smsLoading: false, // 发送验证码loading
			is_send_code: false,
			sec: 60,
			msg: '获取验证码',
			timer: null // 定时器
		};
	},
	computed: {
		// [新增] 获取商城名称
		...mapState('mall', ['setting']),
		mallName() {
			return this.setting ? this.setting.name : '商城';
		}
	},
	onLoad() {
		this.getUser();
	},
	onUnload() {
		// 页面卸载时清除定时器
		if (this.timer) {
			clearInterval(this.timer);
			this.timer = null;
		}
	},
	methods: {
		...mapActions('user', {
			vuexLogout: 'logout'
		}),
		...mapMutations('mch', ['CLEAR_MCH_INFO']),
		...mapMutations('mall', ['CLEAR_MALL_SETTING']),
		getUser() {
			// [优化] 添加加载状态
			uni.showLoading({
				title: '加载中...'
			});
			this.$request({
				url: "/api/user/index"
			}).then(res => {
				if (res.code == 0) {
					this.user = res.data.user;
				} else {
					// [优化] 错误处理
					uni.showModal({
						title: '错误',
						content: res.msg || '获取用户信息失败',
						showCancel: false,
						success: () => {
							uni.navigateBack();
						}
					})
				}
			}).catch(err => {
				// [优化] 网络错误处理
				uni.showModal({
					title: '错误',
					content: '网络错误，请稍后重试',
					showCancel: false,
					success: () => {
						uni.navigateBack();
					}
				})
			}).finally(() => {
				uni.hideLoading();
			});
		},
		goBack() {
			uni.navigateBack();
		},
		// [新增] 切换同意条款状态
		toggleAgreeTerms() {
			this.agreedToTerms = !this.agreedToTerms;
		},
		// [新增] 从协议弹窗进入短信验证弹窗
		proceedToSmsVerification() {
			if (!this.agreedToTerms) {
				uni.showToast({
					title: '请先同意账号注销说明',
					icon: 'none'
				});
				return;
			}
			this.showAgreementModal = false;
			this.showConfirmModal = true;
		},
		getCode() {
			if (this.is_send_code || this.smsLoading) {
				return;
			}
			if (!this.user || !this.user.mobile) {
				return uni.showModal({
					title: '提示',
					content: '暂未获取到手机号',
					showCancel: false // [优化] 移除不必要的按钮
				})
			}
			this.smsLoading = true;
			this.$request({
				url: "/api/login/sms-code",
				data: {
					mobile: this.user.mobile,
					// [建议] 添加一个场景参数，后端可以区分验证码用途
					// scene: 'account_deletion'
				},
				method: 'post'
			}).then(res => {
				if (res.code == 0) {
					uni.showToast({ // [优化] 使用 Toast 提示成功
						title: res.msg || '验证码已发送',
						icon: 'none'
					});
					this.is_send_code = true;
					this.msg = this.sec + '秒后重试';
					this.timer = setInterval(() => {
						this.sec--;
						this.msg = this.sec + '秒后重试';
						if (this.sec <= 0) {
							clearInterval(this.timer);
							this.timer = null;
							this.is_send_code = false;
							this.sec = 60;
							this.msg = '获取验证码';
						}
					}, 1000);
				} else {
					uni.showModal({
						title: '提示',
						content: res.msg || '发送失败', // [优化] 提供默认错误消息
						showCancel: false
					})
					this.is_send_code = false; // [修复] 发送失败时重置状态
				}
			}).catch(err => {
				// [优化] 网络错误提示
				uni.showModal({
					title: '提示',
					content: '发送失败，请检查网络',
					showCancel: false
				});
				this.is_send_code = false; // [修复] 发送失败时重置状态
			}).finally(() => {
				this.smsLoading = false; // 确保 loading 状态被重置
			});
		},
		confirmDeletion() {
			if (!this.code) {
				return uni.showToast({
					title: '请输入验证码',
					icon: 'none'
				});
			}
			this.loading = true;

			this.$request({
				url: "/api/user/account-del",
				data: {
					code: this.code
				},
				method: "post"
			}).then(res => {
				this.showConfirmModal = false; // 无论成功失败都关闭弹窗
				if (res.code == 0) {
					uni.showModal({
						title: '提示',
						content: res.msg || '注销成功', // [优化] 提供默认成功消息
						showCancel: false, // [优化] 移除不必要的按钮
						success: (e) => {
							this.vuexLogout();
							this.CLEAR_MCH_INFO();
							this.CLEAR_MALL_SETTING()
							uni.reLaunch({
								url: "/pages/index/index"
							});
						}
					})
				} else {
					uni.showModal({
						title: '提示',
						content: res.msg || '注销失败', // [优化] 提供默认错误消息
						showCancel: false
					})
				}
			}).catch(err => {
				// [优化] 网络错误提示
				this.showConfirmModal = false;
				uni.showModal({
					title: '提示',
					content: '注销失败，请检查网络',
					showCancel: false
				});
			}).finally(() => {
				this.loading = false; // 确保 loading 状态被重置
			});
		}
	}
}
</script>

<style lang="scss" scoped>
page {
	background-color: #f7f7f7; // 页面背景色
}

.container {
	display: flex;
	flex-direction: column;
	min-height: 100vh; // 确保内容至少撑满屏幕
	padding-bottom: 140rpx; // 为底部按钮留出空间
}

// 顶部导航样式
.navbar-placeholder {
	display: flex;
	align-items: center;
	padding: 60rpx 30rpx 20rpx; // 模拟状态栏和导航栏高度
	color: #333; // 文字颜色改为深色
	background-color: #ffffff; // 导航栏背景白色
	position: sticky;
	top: 0;
	z-index: 10;
	border-bottom: 1rpx solid #eee; // 添加下边框

	.navbar-title {
		flex: 1;
		text-align: center;
		font-size: 34rpx;
		font-weight: bold;
	}

	.navbar-action-placeholder {
		width: 24px; // 与左侧图标宽度一致，保持居中
	}
}

// 警告卡片
.warning-card {
	background-color: #ffffff;
	border-radius: 20rpx;
	margin: 30rpx;
	padding: 40rpx 30rpx;
	box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.05);
	text-align: center; // 内部内容居中
}

.warning-icon-wrapper {
	margin-bottom: 30rpx;
	// 使用 tui-icon 代替 image
}

.warning-text {
	font-size: 28rpx;
	color: #666;
	line-height: 1.6;
	text-align: left; // 警告文字左对齐
	display: block; // 确保宽度生效
	margin-bottom: 40rpx;
}

// 资产区域
.asset-section {
	border-top: 1rpx dashed #eee; // 虚线分割
	padding-top: 40rpx;
	text-align: left; // 资产信息左对齐
}

.asset-title {
	font-size: 30rpx;
	font-weight: bold;
	color: #333;
	margin-bottom: 25rpx;
	display: block;
}

.asset-item {
	display: flex;
	justify-content: space-between; // 标签和值两端对齐
	margin-bottom: 15rpx;
	font-size: 28rpx;

	&:last-child {
		margin-bottom: 0;
	}

	.asset-label {
		color: #666;
	}

	.asset-value {
		font-weight: 500;
	}

	// 不同资产类型使用不同颜色
	.amount,
	.points,
	.reward,
	.coupon {
		color: #E43D33;
	}
}

// 底部按钮
.action-buttons {
	position: fixed; // 固定在底部
	bottom: 0;
	left: 0;
	width: 100%;
	padding: 20rpx 30rpx calc(20rpx + constant(safe-area-inset-bottom)) 30rpx; // 适配 iPhone X 等底部安全区域
	padding: 20rpx 30rpx calc(20rpx + env(safe-area-inset-bottom)) 30rpx;
	background-color: #ffffff; // 按钮区域背景白色
	display: flex;
	gap: 30rpx; // 按钮间距
	border-top: 1rpx solid #eee; // 添加上边框
	box-sizing: border-box; // 防止 padding 撑开宽度

	.btn {
		flex: 1; // 按钮平分宽度
		height: 80rpx;
		line-height: 80rpx;
		border-radius: 40rpx;
		font-size: 30rpx;
		border: none;

		&::after {
			border: none;
		}
	}

	.cancel-btn {
		background-color: #f7f7f7;
		color: #666;
		border: 1rpx solid #eee;
	}

	.delete-btn {
		background-color: #E43D33;
		color: #ffffff;
	}
}

// [新增] 协议弹窗样式
.agreement-modal-content {
	padding: 40rpx 30rpx;
	background-color: #fff;
	border-radius: 16rpx;
}

.agreement-modal-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 30rpx;

	.agreement-modal-title {
		font-size: 32rpx;
		font-weight: bold;
		color: #333;
		margin-left: 10rpx; // 图标和标题间距
		flex: 1; // 占据剩余空间
		text-align: center; // 标题居中
	}
}

.agreement-modal-body {
	.agreement-text {
		font-size: 28rpx;
		color: #666;
		line-height: 1.6;
		display: block;
		margin-bottom: 40rpx;
	}

	.agreement-checkbox-row {
		display: flex;
		align-items: center;
		justify-content: center; // 水平居中

		.agreement-label {
			font-size: 26rpx;
			margin-left: 10rpx;
		}
	}
}

.agreement-modal-footer {
	display: flex;
	gap: 30rpx;
	margin-top: 50rpx;

	.agreement-modal-btn {
		flex: 1;
		height: 70rpx;
		line-height: 70rpx;
		border-radius: 35rpx;
		font-size: 28rpx;
		border: none;

		&::after {
			border: none;
		}
	}

	.cancel-agreement-btn {
		background-color: #f7f7f7;
		color: #666;
		border: 1rpx solid #eee;
	}

	.confirm-agreement-btn {
		background-color: #E43D33; // 主题红色
		color: #ffffff;

		&[disabled] {
			background-color: #fab6b6; // 禁用时的颜色
			color: #fff;
		}
	}
}


// 短信验证弹窗样式
.modal-content {
	padding: 40rpx 30rpx;
	background-color: #fff;
	border-radius: 16rpx; // 弹窗圆角
}

.modal-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 30rpx;

	.modal-title {
		font-size: 32rpx;
		font-weight: bold;
		color: #333;
	}
}

.modal-body {
	.modal-warning-text {
		font-size: 26rpx;
		color: #E43D33; // 警告文字红色
		line-height: 1.6;
		display: block;
		margin-bottom: 40rpx;
	}

	.input-row {
		display: flex;
		align-items: center;
		margin-bottom: 30rpx;
		border-bottom: 1rpx solid #f0f0f0; // 输入行下边框
		padding-bottom: 20rpx;

		.input-label {
			width: 120rpx; // 固定标签宽度
			font-size: 28rpx;
			color: #333;
			flex-shrink: 0; // 防止标签被压缩
		}

		.input-field {
			flex: 1;
			font-size: 28rpx;
			height: 60rpx; // 增加输入框高度
			padding: 0 10rpx; // 左右一点内边距
			min-width: 0; // 防止输入框撑开 flex
		}

		.mobile-input {
			color: #999; // 禁用状态颜色
		}
	}

	.code-row {
		.code-input {
			// 可以添加边框等样式
		}

		.code-btn {
			background-color: transparent;
			color: #E43D33;
			border: none;
			padding: 0 20rpx;
			margin: 0;
			margin-left: 20rpx; // 与输入框间距
			font-size: 26rpx;
			white-space: nowrap; // 防止文字换行
			height: 60rpx;
			line-height: 60rpx;
			flex-shrink: 0; // 防止按钮被压缩

			&::after {
				border: none;
			}

			&[disabled] {
				color: #999;
			}
		}
	}

}

.modal-footer {
	display: flex;
	gap: 30rpx;
	margin-top: 40rpx;

	.modal-btn {
		flex: 1;
		height: 70rpx;
		line-height: 70rpx;
		border-radius: 35rpx;
		font-size: 28rpx;
		border: none;

		&::after {
			border: none;
		}
	}

	.cancel-modal-btn {
		background-color: #f7f7f7;
		color: #666;
		border: 1rpx solid #eee;
	}

	.confirm-modal-btn {
		background-color: #E43D33;
		color: #ffffff;
	}
}

// 全屏加载占位
.loading-full {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	display: flex;
	justify-content: center;
	align-items: center;
	background-color: #f7f7f7;
	z-index: 99;
}
</style>