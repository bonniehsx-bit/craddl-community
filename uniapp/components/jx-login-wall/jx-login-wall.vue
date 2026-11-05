<template>
	<view class="login-wall">
		<view class="lw-icon-circle">
			<tui-icon name="people-fill" :size="48" color="#fff"></tui-icon>
		</view>
		<text class="lw-title">{{ title }}</text>
		<text class="lw-desc">{{ desc }}</text>
		<button class="lw-btn" @click="goLogin">去登录</button>
	</view>
</template>

<script>
export default {
	name: 'jx-login-wall',
	props: {
		title: { type: String, default: '请先登录' },
		desc:  { type: String, default: '登录后即可使用该功能' },
	},
	methods: {
		goLogin() {
			uni.navigateTo({ url: '/others/login/login' })
		},
	},
}
</script>

<style lang="scss" scoped>
/* 母婴主题硬编码(避免依赖 plugins1/hardware/_theme.scss 路径) */
$jx-primary: #FF6B9D;
$jx-primary-soft: #FFE4EC;
$jx-text: #2C2C2C;
$jx-text-muted: #9B9B9B;
$jx-radius-btn: 40rpx;
$jx-gradient: linear-gradient(145deg, #FF6B9D 0%, #FF8C42 100%);
$jx-shadow-card: 0 4rpx 24rpx rgba(255, 107, 157, 0.08);

.login-wall {
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 160rpx 60rpx;
	gap: 24rpx;
}
.lw-icon-circle {
	width: 160rpx;
	height: 160rpx;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	background: $jx-gradient;
	box-shadow: $jx-shadow-card;
	margin-bottom: 8rpx;
}
.lw-title {
	font-size: 36rpx;
	font-weight: 700;
	color: $jx-text;
}
.lw-desc {
	font-size: 26rpx;
	color: $jx-text-muted;
	text-align: center;
}
.lw-btn {
	margin-top: 24rpx;
	width: 320rpx;
	height: 88rpx;
	line-height: 88rpx;
	background: $jx-primary;
	color: #fff;
	border-radius: $jx-radius-btn;
	font-size: 30rpx;
	font-weight: 600;
	box-shadow: $jx-shadow-card;
}
</style>
