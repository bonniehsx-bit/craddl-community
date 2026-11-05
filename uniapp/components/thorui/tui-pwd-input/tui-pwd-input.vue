<template>
	<tui-modal :show="visible" :custom="true" title="请输入支付密码" @cancel="handleCancel">
		<view class="flex-x-center" style="font-weight: bold;">请输入支付密码</view>
		<view class="pwd-input-modal">

			<view class="input-wrap">
				<input v-model="password" type="number" password
					placeholder="请输入密码" maxlength="6" style="text-align:center;" />
			</view>
			<view class="flex-x-between">
				<tui-button shape="circle" width="200rpx" height="70rpx" size="25" @click="handleCancel">取消</tui-button>
				<tui-button shape="circle" width="200rpx" height="70rpx" size="25" type="danger"
					@click="handleConfirm">确定</tui-button>
			</view>
		</view>
	</tui-modal>
</template>

<script>
	export default {
		name: 'TuiPwdInput',
		props: {
			visible: Boolean
		},
		data() {
			return {
				password: ''
			}
		},
		methods: {
			handleCancel() {
				this.$emit('update:visible', false);
				this.$emit('cancel', this.password);
				this.password = '';
			},
			handleConfirm() {
				if (this.password.length !== 6) {
					uni.showToast({
						title: '请输入6位密码',
						icon: 'none'
					});
					return;
				}
				this.$emit('update:visible', false);
				this.$emit('confirm', this.password);
				this.password = '';
			}
		}
	}
</script>

<style scoped>
	.pwd-input-modal {
		padding: 40rpx 20rpx 10rpx;
	}

	.input-wrap {
		display: flex;
		justify-content: center;
		margin-bottom: 40rpx;
	}

	.btn-group {
		display: flex;
		
		justify-content: space-between;
	}

	.cancel-btn,
	.confirm-btn {
		width: 45%;
		padding: 20rpx 0;
		border-radius: 10rpx;
		font-size: 30rpx;
	}

	.cancel-btn {
		background-color: #f2f2f2;
		color: #666;
	}

	.confirm-btn {
		background-color: #007aff;
		color: #fff;
	}
</style>