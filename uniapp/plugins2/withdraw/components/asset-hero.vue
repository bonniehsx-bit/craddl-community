<template>
	<!-- 余额 / 奖励 / 积分三个中心页共用的头部:主题色底、大号金额、一行累计数据、操作按钮。
	     没有累计数据 / 没有按钮(后台没开提现和转赠)时对应那一块整个不画,头部自动收紧,不会留空洞。 -->
	<view class="asset-hero" :class="compact ? 'compact' : ''" :style="'padding-top:' + navTop + 'px'">
		<view class="hero-deco d1"></view>
		<view class="hero-deco d2"></view>

		<view class="hero-top">
			<text class="hero-label">{{ label }}</text>
			<view class="hero-link" v-if="link" @click="$emit('link')">
				<text>{{ link }}</text>
				<uni-icons type="right" size="12" color="rgba(255,255,255,0.9)"></uni-icons>
			</view>
		</view>

		<view class="hero-amount">{{ amount }}</view>

		<view class="hero-stats" v-if="stats.length">
			<block v-for="(s, i) in stats" :key="i">
				<view class="hs-line" v-if="i > 0"></view>
				<view class="hs-item">
					<text class="hs-val">{{ s.value }}</text>
					<text class="hs-label">{{ s.label }}</text>
				</view>
			</block>
		</view>

		<view class="hero-actions" v-if="actions.length">
			<view class="act" v-for="(a, i) in actions" :key="a.key" :class="i === 0 ? 'primary' : 'ghost'"
				@click="$emit('action', a.key)">{{ a.text }}</view>
		</view>
	</view>
</template>

<script>
	export default {
		name: 'assetHero',
		props: {
			label: { type: String, default: '' },
			amount: { type: [String, Number], default: '0.00' },
			// [{label, value}]:累计收入、提现中……没有就不画这一行
			stats: { type: Array, default: () => [] },
			// [{key, text}]:第一个是主按钮(白底),其余是半透明描边;没有就不画按钮
			actions: { type: Array, default: () => [] },
			// 右上角小胶囊的文字(如「提现明细」),空 = 不显示
			link: { type: String, default: '' }
		},
		data() {
			return { navTop: 0 };
		},
		computed: {
			// 既没累计数据也没按钮:只剩金额,头部收紧
			compact() {
				return !this.stats.length && !this.actions.length;
			}
		},
		created() {
			// 状态栏高度 + 自绘导航高度:头部从屏幕最顶上铺起,内容让开导航
			this.navTop = uni.getSystemInfoSync().statusBarHeight + 44;
		}
	};
</script>

<style lang="scss" scoped>
	.asset-hero {
		position: relative;
		overflow: hidden;
		padding: 0 30rpx 84rpx;
		background: var(--theme-color);
		border-bottom-left-radius: 44rpx;
		border-bottom-right-radius: 44rpx;

		/* 上亮下暗的一层渐变,让纯色头部有层次(主题色是任意色,不能直接写渐变终点) */
		&::before {
			content: '';
			position: absolute;
			left: 0;
			right: 0;
			top: 0;
			bottom: 0;
			background: linear-gradient(160deg, rgba(255, 255, 255, 0.16) 0%, rgba(255, 255, 255, 0) 45%, rgba(0, 0, 0, 0.14) 100%);
			pointer-events: none;
		}

		&.compact {
			padding-bottom: 56rpx;
		}
	}

	.hero-deco {
		position: absolute;
		border-radius: 50%;
		background: rgba(255, 255, 255, 0.1);
		pointer-events: none;

		&.d1 {
			top: -90rpx;
			right: -70rpx;
			width: 340rpx;
			height: 340rpx;
		}

		&.d2 {
			bottom: -60rpx;
			left: -90rpx;
			width: 240rpx;
			height: 240rpx;
			background: rgba(255, 255, 255, 0.07);
		}
	}

	.hero-top {
		position: relative;
		display: flex;
		align-items: center;
		justify-content: space-between;
		margin-top: 28rpx;
	}

	.hero-label {
		font-size: 26rpx;
		color: rgba(255, 255, 255, 0.85);
	}

	.hero-link {
		display: flex;
		align-items: center;
		padding: 6rpx 8rpx 6rpx 20rpx;
		border-radius: 30rpx;
		background: rgba(255, 255, 255, 0.18);
		font-size: 23rpx;
		color: #fff;
	}

	.hero-amount {
		position: relative;
		margin: 18rpx 0 34rpx;
		font-size: 92rpx;
		font-weight: bold;
		color: #fff;
		line-height: 1.05;
		font-family: DINAlternate-Bold, Helvetica, Arial, sans-serif;
		text-shadow: 0 6rpx 18rpx rgba(0, 0, 0, 0.14);
	}

	.compact .hero-amount {
		margin-bottom: 0;
	}

	.hero-stats {
		position: relative;
		display: flex;
		align-items: center;
		padding: 22rpx 0;
		border-radius: 20rpx;
		background: rgba(255, 255, 255, 0.14);
		border: 1rpx solid rgba(255, 255, 255, 0.2);
	}

	.hs-item {
		flex: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
		align-items: center;
	}

	.hs-val {
		max-width: 100%;
		font-size: 30rpx;
		font-weight: bold;
		color: #fff;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.hs-label {
		margin-top: 6rpx;
		font-size: 22rpx;
		color: rgba(255, 255, 255, 0.78);
	}

	.hs-line {
		width: 1rpx;
		height: 40rpx;
		background: rgba(255, 255, 255, 0.28);
	}

	.hero-actions {
		position: relative;
		display: flex;
		margin-top: 30rpx;
	}

	.act {
		flex: 1;
		height: 88rpx;
		line-height: 88rpx;
		margin-right: 20rpx;
		text-align: center;
		border-radius: 44rpx;
		font-size: 30rpx;
		font-weight: bold;
		letter-spacing: 2rpx;

		&:last-child {
			margin-right: 0;
		}

		&.primary {
			flex: 1.5;
			background: #fff;
			color: var(--theme-color);
			box-shadow: 0 10rpx 26rpx rgba(0, 0, 0, 0.16);
		}

		&.ghost {
			background: rgba(255, 255, 255, 0.18);
			border: 1rpx solid rgba(255, 255, 255, 0.55);
			color: #fff;
		}

		&:active {
			transform: scale(0.98);
		}
	}
</style>
