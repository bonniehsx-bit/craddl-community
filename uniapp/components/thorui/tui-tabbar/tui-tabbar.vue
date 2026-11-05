<template>
	<view class="tabbar-container" :class="{ 'fixed-bottom': isFixed }">
		<!-- 
			核心 Tabbar 容器 
			修改点：增加 :class="{ 'glass-effect': glass }" 用于控制毛玻璃样式
		-->
		<view class="tui-tabbar" :class="{ 'glass-effect': glass }" :style="{ zIndex: zIndex }">

			<!-- 遍历 Tab 按钮 -->
			<block v-for="(item, index) in tabBar" :key="index">
				<view class="tui-tabbar-item" :class="{ 'item-hump': item.hump, 'item-active': current == index }"
					@tap="handleTabClick(index, item)">

					<!-- 图标区域 -->
					<view class="icon-wrapper" :class="{ 'hump-wrapper': item.hump }">
						<!-- 如果是中间凸起按钮，增加光晕背景 -->
						<view v-if="item.hump" class="hump-glow"></view>

						<image :src="current == index ? item.active_icon : item.icon" class="tab-icon"
							:class="{ 'hump-icon': item.hump, 'bounce-anim': current == index && !item.hump }"></image>

						<!-- 角标 -->
						<view v-if="item.num" :class="[item.isDot ? 'badge-dot' : 'badge-num']"
							:style="{ backgroundColor: badgeBgColor, color: badgeColor }">
							{{ item.isDot ? '' : item.num }}
						</view>
					</view>

					<!-- 文字区域 -->
					<text class="tab-text" :class="{ 'text-hump': item.hump }"
						:style="{ color: current == index ? selectedColor : color, fontWeight: current == index ? 'bold' : 'normal' }">
						{{ item.name }}
					</text>
				</view>
			</block>
		</view>
		
		<!-- 占位符：防止 Tabbar 遮挡页面底部内容 -->
		<view class="tabbar-placeholder"></view>
	</view>
</template>

<script>
	export default {
		name: 'TuiTabbar',
		props: {
			current: {
				type: [String, Number],
				default: 0
			},
			color: {
				type: String,
				default: '#8A8A9E'
			},
			selectedColor: {
				type: String,
				default: '#BA0C2F'
			},
			hump: {
				type: Boolean,
				default: false
			},
			isFixed: {
				type: Boolean,
				default: true
			},
			/* 新增属性：是否开启毛玻璃效果 */
			glass: {
				type: Boolean,
				default: true
			},
			tabBar: {
				type: Array,
				default: () => []
			},
			badgeColor: {
				type: String,
				default: '#FFFFFF'
			},
			badgeBgColor: {
				type: String,
				default: '#FF4D4F'
			},
			zIndex: {
				type: [Number, String],
				default: 9999
			}
		},
		methods: {
			handleTabClick(index, item) {
				// 点的就是当前已激活的 tab → 不重复跳，避免 reLaunch 把同页整个重载(闪屏 + 丢状态)
				if (Number(index) === Number(this.current)) return;
				// 用 reLaunch 而非 redirectTo:tab 页是「根页面」,应清空整个栈成为唯一页。
				// redirectTo 只替换栈顶,深层进入后点 tab 会残留孤儿页 → 返回错乱 + 栈堆满崩溃。
				uni.reLaunch({
					url: item.url
				});
			}
		}
	};
</script>

<style lang="scss" scoped>
	/* 
	 * 变量定义
	 */
	$tabbar-height: 120rpx;
	$shadow-color: rgba(0, 0, 0, 0.05);

	.tabbar-container {
		width: 100%;
		position: relative;
		height: $tabbar-height;
		
		&.fixed-bottom {
			/* 固定在底部 */
			position: fixed;
			left: 0;
			bottom: 0;
			z-index: 9999;	
		}
	}

	/* 
	 * 核心样式：固定底部栏
	 */
	.tui-tabbar {
		width: 100%;
		/* 核心修改：高度自动，由 padding 撑开安全区 */
		height: auto; 
		min-height: $tabbar-height;
		
		display: flex;
		align-items: center;
		justify-content: space-between;
		
		/* 核心修改：适配 iPhone X 等全面屏底部安全区 */
		padding-bottom: constant(safe-area-inset-bottom);
		padding-bottom: env(safe-area-inset-bottom);
		box-sizing: content-box; 

		/* 默认背景 (非毛玻璃时为纯白) */
		background: #FFFFFF;
		
		/* 顶部细边框，视觉分隔 */
		border-top: 1rpx solid rgba(0, 0, 0, 0.08);
		/* 轻微阴影提升层次感 */
		box-shadow: 0 -2rpx 10rpx $shadow-color;
		
		/* 过渡效果，切换开关时更平滑 */
		transition: background 0.3s, backdrop-filter 0.3s;

		/* 
		 * 新增：毛玻璃效果样式 
		 */
		&.glass-effect {
			/* 必须要半透明背景，毛玻璃才能显现 */
			background: rgba(255, 255, 255, 0.85);
			/* 标准属性 */
			backdrop-filter: blur(10px); 
			/* 适配 iOS Safari / WebKit */
			-webkit-backdrop-filter: blur(10px);
			/* 稍微加深一点阴影以适应半透明 */
			box-shadow: 0 -2rpx 15rpx rgba(0, 0, 0, 0.08);
		}
	}

	.tui-tabbar-item {
		flex: 1;
		/* 高度固定为 tabbar 内容高度 */
		height: $tabbar-height;
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
		position: relative;
	}

	/* 图标容器 */
	.icon-wrapper {
		position: relative;
		width: 56rpx;
		height: 56rpx;
		display: flex;
		align-items: center;
		justify-content: center;

		/* 
		 * 凸起按钮容器 
		 * 保持凸起效果，但底部与 Tabbar 融合
		 */
		&.hump-wrapper {
			width: 100rpx;
			height: 100rpx;
			position: absolute;
			/* 向上突出的距离 */
			top: -36rpx;
			border-radius: 50%;
			background: linear-gradient(135deg, #BA0C2F, #FF4D4F);
			box-shadow: 0 -4rpx 10rpx rgba(186, 12, 47, 0.2);
			/* 增加白色边框，使其与白底 Tabbar 分隔开 */
			border: 6rpx solid #FFFFFF;
			z-index: 10;
		}
	}

	.hump-glow {
		position: absolute;
		top: 15%;
		left: 15%;
		width: 30%;
		height: 30%;
		background: radial-gradient(circle, rgba(255, 255, 255, 0.7) 0%, rgba(255, 255, 255, 0) 70%);
		border-radius: 50%;
		z-index: 11;
	}

	.tab-icon {
		width: 44rpx;
		height: 44rpx;
		display: block;

		&.hump-icon {
			width: 56rpx;
			height: 56rpx;
			z-index: 12;
		}
	}

	/* 简单的点击回弹动画 */
	.bounce-anim {
		animation: bounce-scale 0.3s;
	}

	@keyframes bounce-scale {
		0% { transform: scale(1); }
		50% { transform: scale(1.1); }
		100% { transform: scale(1); }
	}

	.tab-text {
		font-size: 20rpx;
		margin-top: 4rpx;
		transition: color 0.3s;
		line-height: 1.2;

		&.text-hump {
			/* 凸起按钮下方的文字位置调整 */
			margin-top: 56rpx;
		}
	}

	/* 角标样式 */
	.badge-num {
		position: absolute;
		top: -6rpx;
		right: -14rpx;
		font-size: 20rpx; /* 字体稍微调大，保证清晰 */
		padding: 0 8rpx;
		height: 28rpx;
		line-height: 28rpx;
		border-radius: 28rpx;
		border: 2rpx solid #FFF;
		z-index: 15;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.badge-dot {
		position: absolute;
		top: 0;
		right: -6rpx;
		width: 16rpx;
		height: 16rpx;
		border-radius: 50%;
		border: 2rpx solid #FFF;
		z-index: 15;
	}

	/* 
	 * 占位区块 
	 * 放在页面底部，确保页面内容不被 Fixed 的 Tabbar 遮挡
	 */
	.tabbar-placeholder {
		width: 100%;
		height: $tabbar-height;
		padding-bottom: constant(safe-area-inset-bottom);
		padding-bottom: env(safe-area-inset-bottom);
	}
</style>