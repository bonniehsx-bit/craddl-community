<template>
	<view v-if="visible" class="mrj-mask" @click.stop @touchmove.stop.prevent>
		<view class="mrj-box">
			<view class="mrj-handle"></view>
			<text class="mrj-title">拒绝接单</text>
			<text class="mrj-sub">请选择拒单原因（必填）</text>
			<view class="mrj-chips">
				<view v-for="r in reasons" :key="r"
					:class="['mrj-chip', choice === r ? 'active' : '']"
					@click="choice = r">{{ r }}</view>
			</view>
			<textarea v-if="choice === OTHER" class="mrj-input" v-model="other"
				:maxlength="100" placeholder="请简单说明拒单原因…" />
			<view class="mrj-actions">
				<button class="mrj-btn cancel" @click="onCancel">取 消</button>
				<button class="mrj-btn confirm" :disabled="submitting" @click="onConfirm">确认拒绝</button>
			</view>
		</view>
	</view>
</template>

<script>
const OTHER = '其他';
export default {
	name: 'massage-reject',
	props: {
		// 是否显示
		visible: { type: Boolean, default: false },
		// 提交中（父组件发请求时置 true，禁用确认按钮防重复）
		submitting: { type: Boolean, default: false },
	},
	data() {
		return {
			OTHER,
			reasons: ['时间冲突 / 已排满', '距离过远', '身体不适', '项目不擅长', OTHER],
			choice: '',
			other: '',
		};
	},
	watch: {
		// 每次打开重置选择
		visible(v) {
			if (v) {
				this.choice = '';
				this.other = '';
			}
		},
	},
	methods: {
		onCancel() {
			this.$emit('cancel');
		},
		onConfirm() {
			if (!this.choice) {
				uni.showToast({ title: '请选择拒单原因', icon: 'none' });
				return;
			}
			let reason = this.choice;
			// 选「其他」时取自填文本
			if (this.choice === OTHER) {
				reason = (this.other || '').trim();
				if (!reason) {
					uni.showToast({ title: '请填写拒单原因', icon: 'none' });
					return;
				}
			}
			this.$emit('submit', reason);
		},
	},
};
</script>

<style lang="scss" scoped>
.mrj-mask {
	position: fixed;
	inset: 0;
	background: rgba(15, 23, 42, 0.55);
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 1000;
}
.mrj-box {
	background: #fff;
	border-radius: 32rpx;
	padding: 20rpx 36rpx 44rpx;
	width: 620rpx;
	box-shadow: 0 24rpx 64rpx rgba(15, 23, 42, 0.2);
}
.mrj-handle {
	width: 64rpx;
	height: 8rpx;
	background: #F1F5F9;
	border-radius: 4rpx;
	margin: 0 auto 28rpx;
}
.mrj-title {
	font-size: 34rpx;
	font-weight: 900;
	color: #0F172A;
	display: block;
	margin-bottom: 8rpx;
}
.mrj-sub {
	font-size: 24rpx;
	color: #94A3B8;
	display: block;
	margin-bottom: 24rpx;
}
.mrj-chips {
	display: flex;
	flex-wrap: wrap;
	gap: 16rpx;
}
.mrj-chip {
	padding: 14rpx 28rpx;
	background: #F1F5F9;
	color: #64748B;
	font-size: 26rpx;
	border-radius: 999rpx;
	&.active {
		/* 跟随商城主题色，组件外可能无主题变量时回退默认红 */
		background: var(--theme-color, #D71F1F);
		color: #fff;
		font-weight: 600;
	}
}
.mrj-input {
	width: 100%;
	height: 150rpx;
	box-sizing: border-box;
	background: #F8FAFC;
	border-radius: 16rpx;
	padding: 20rpx 24rpx;
	font-size: 26rpx;
	color: #0F172A;
	line-height: 1.6;
	margin-top: 20rpx;
}
.mrj-actions {
	display: flex;
	gap: 20rpx;
	margin-top: 32rpx;
}
.mrj-btn {
	flex: 1;
	font-size: 30rpx;
	font-weight: 700;
	padding: 20rpx 0;
	border-radius: 999rpx;
	line-height: 1.4;
	border: none;
	&.cancel {
		background: #F1F5F9;
		color: #64748B;
		&:active { opacity: 0.7; }
	}
	&.confirm {
		background: var(--theme-color, #D71F1F);
		color: #fff;
		box-shadow: 0 6rpx 20rpx var(--theme-shadow, rgba(215, 31, 31, 0.25));
		&:active { transform: scale(0.97); opacity: 0.9; }
	}
}
</style>
