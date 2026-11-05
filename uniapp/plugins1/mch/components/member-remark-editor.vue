<template>
	<tui-bottom-popup :show="show" @close="close">
		<view class="remark-container">
			<view class="remark-header">
				<view class="title">编辑备注</view>
				<tui-icon name="close-fill" color="#999" :size="20" @click="close"></tui-icon>
			</view>
			<view class="remark-body">
				<textarea class="remark-textarea" v-model="currentRemark" placeholder="请输入对该会员的备注信息..." :maxlength="500"
					:focus="show" placeholder-style="color:#999;font-size:28rpx;"></textarea>
				<view class="char-counter">{{ remarkLength }}/500</view>
			</view>
			<view class="remark-footer">
				<button class="save-btn" @click="onSave" :loading="loading">保存</button>
			</view>
		</view>
	</tui-bottom-popup>
</template>

<script>
	export default {
		name: "remark-editor",
		data() {
			return {
				show: false,
				loading: false,
				member: null,
				currentRemark: ''
			};
		},
		computed: {
			remarkLength() {
				return this.currentRemark.length;
			}
		},
		methods: {
			open(member) {
				this.member = member;
				this.currentRemark = member.remark || '';
				this.show = true;
			},
			close() {
				this.show = false;
			},
			async onSave() {
				if (this.loading) return;

				this.loading = true;
				try {
					const res = await this.$request({
						url: '/plugin/mch/admin/member/update-remark',
						method: 'POST',
						data: {
							user_id: this.member.id,
							remark: this.currentRemark
						}
					});

					if (res.code === 0) {
						this.$emit('confirm', {
							userId: this.member.id,
							remark: this.currentRemark
						});
						uni.showToast({
							title: res.msg
						})
						this.close();
					} else {
						this.$showModal(res.msg);
					}
				} finally {
					this.loading = false;
				}
			}
		}
	}
</script>

<style lang="scss" scoped>
	.remark-container {
		background-color: #fff;
		border-top-left-radius: 20rpx;
		border-top-right-radius: 20rpx;
		padding: 30rpx;
	}

	.remark-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding-bottom: 20rpx;

		.title {
			font-size: 32rpx;
			font-weight: bold;
			color: #333;
		}
	}

	.remark-body {
		padding: 20rpx 0;
		position: relative;
	}

	.remark-textarea {
		width: 100%;
		height: 200rpx;
		background-color: #f7f7f7;
		border-radius: 12rpx;
		padding: 20rpx;
		font-size: 28rpx;
		box-sizing: border-box;
	}

	.char-counter {
		position: absolute;
		bottom: 30rpx;
		right: 20rpx;
		font-size: 24rpx;
		color: #999;
	}

	.remark-footer {
		padding-top: 20rpx;
	}

	.save-btn {
		width: 100%;
		height: 88rpx;
		line-height: 88rpx;
		background-color: #133A95;
		color: #fff;
		border-radius: 44rpx;
		font-size: 30rpx;
		border: none;

		&::after {
			border: none;
		}
	}
</style>