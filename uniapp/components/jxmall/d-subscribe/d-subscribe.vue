<template>
	<!-- #ifdef MP-WEIXIN -->
	<view v-if="mpVisible" class="mp-banner"
		:class="{ 'mp-banner-leave': mpLeaving, 'mp-banner-absorb': mpAbsorbing }"
		:style="'top:' + mpTopPx + 'px'">

		<!-- 左侧文案 -->
		<view class="mp-content">
			<text class="mp-icon">🔔</text>
			<view class="mp-text">
				<text class="mp-title">开启消息通知</text>
				<text class="mp-desc">关注公众号，接收实时消息提醒</text>
			</view>
		</view>

		<!-- 右侧按钮区 -->
		<view class="mp-action">
			<!-- 关注公众号组件覆盖在按钮上，用户点击时触发原生关注 -->
			<view class="mp-btn-wrap">
				<text class="mp-btn-text">立即关注</text>
				<view class="mp-official-overlay">
					<official-account @follow="mpOnFollow"></official-account>
				</view>
			</view>
			<text class="mp-countdown" @click.stop="mpDismiss">{{ mpCountdown }}s</text>
		</view>

	</view>
	<!-- #endif -->
</template>

<script>
	export default {
		name: 'dSubscribe',
		data() {
			return {
				mpVisible:    false,
				mpLeaving:    false,
				mpAbsorbing:  false,
				mpCountdown:  10,
				mpTopPx:      0,
				mpCapsuleX:   0,   // 胶囊中心 X（px）
				mpCapsuleY:   0,   // 胶囊中心 Y（px）
				_mpTimer:     null,
				_mpDismissed: false,
			}
		},
		computed: {
			bannerStyle() {
				const s = { top: this.mpTopPx + 'px' }
				if (this.mpAbsorbing) {
					// 吸入动画：设置 transform-origin 指向胶囊位置
					s['--capsule-x'] = this.mpCapsuleX + 'px'
					s['--capsule-y'] = this.mpCapsuleY + 'px'
				}
				return s
			}
		},
		created() {
			// #ifdef MP-WEIXIN
			this._mpCheck()
			// #endif
		},
		mounted() {
			// #ifdef MP-WEIXIN
			this._calcTopOffset()
			// #endif
		},
		beforeDestroy() {
			this._mpClearTimer()
		},
		methods: {
			/** 父页面 onShow 时可主动调用，刷新展示状态 */
			async refresh() {
				// #ifdef MP-WEIXIN
				if (this._mpDismissed || this.mpVisible) return
				await this._mpCheck()
				// #endif
			},

			_calcTopOffset() {
				const sysInfo = uni.getSystemInfoSync()
				const statusH = sysInfo.statusBarHeight || 20

				// 优先用胶囊按钮实际位置
				let menuBtn = null
				try {
					// uni-app 封装的 API
					if (typeof uni.getMenuButtonBoundingClientRect === 'function') {
						menuBtn = uni.getMenuButtonBoundingClientRect()
					}
				} catch (_) {}
				if (!menuBtn || !menuBtn.bottom) {
					try {
						// 原生 wx API
						if (typeof wx !== 'undefined' && wx.getMenuButtonBoundingClientRect) {
							menuBtn = wx.getMenuButtonBoundingClientRect()
						}
					} catch (_) {}
				}

				if (menuBtn && menuBtn.bottom > 0) {
					this.mpTopPx = menuBtn.bottom + 8
					this.mpCapsuleX = menuBtn.left + menuBtn.width / 2
					this.mpCapsuleY = menuBtn.top + menuBtn.height / 2
				} else {
					// 兜底：状态栏 + 导航栏（44px）+ 间距
					this.mpTopPx = statusH + 44 + 8
					this.mpCapsuleX = (sysInfo.windowWidth || 375) - 50
					this.mpCapsuleY = statusH + 22
				}

				console.log('[d-subscribe] topOffset:', this.mpTopPx, 'capsule:', this.mpCapsuleX, this.mpCapsuleY)
			},

			async _mpCheck() {
				// 先读商城主设置开关，关闭时不发请求
				if (this.$util.getSetting('show_follow_banner') != '1') return

				const token = uni.getStorageSync('ACCESS_TOKEN')
				if (!token) return

				const res = await this.$request({
					url: '/api/wx-notify/status',
					method: 'get'
				}).catch(() => null)

				if (!res || res.code !== 0) return
				if (res.data.bound) return

				this._mpShow()
			},

			_mpShow() {
				this.mpVisible   = true
				this.mpLeaving   = false
				this.mpAbsorbing = false
				this.mpCountdown = 10
				this._mpStartTimer()
				console.log('[d-subscribe] show, bannerStyle:', JSON.stringify(this.bannerStyle))
			},

			_mpStartTimer() {
				this._mpClearTimer()
				this._mpTimer = setInterval(() => {
					this.mpCountdown--
					if (this.mpCountdown <= 0) this.mpDismiss()
				}, 1000)
			},

			_mpClearTimer() {
				if (this._mpTimer) {
					clearInterval(this._mpTimer)
					this._mpTimer = null
				}
			},

			mpDismiss() {
				this._mpClearTimer()
				this._mpDismissed = true
				// 吸入胶囊动画
				this.mpAbsorbing = true
				setTimeout(() => { this.mpVisible = false }, 500)
			},

			async mpOnFollow(e) {
				const openid = e.detail?.openId || e.detail?.openid
				if (!openid) return

				await this.$request({
					url: '/api/wx-notify/bind-by-follow',
					method: 'post',
					data: { openid }
				}).catch(() => null)

				this.mpDismiss()
				uni.showToast({ title: '推送通知已开启', icon: 'success' })
			},
		}
	}
</script>

<style lang="scss">
	.mp-banner {
		position: fixed;
		left: 16rpx; right: 16rpx;
		/* top 由内联 :style 动态设置，避开胶囊按钮 */
		z-index: 9999999;
		/* 毛玻璃效果 —— 半透明底 + 模糊 + 高光边框 */
		background: rgba(255,255,255,0.65);
		-webkit-backdrop-filter: saturate(180%) blur(20px);
		backdrop-filter: saturate(180%) blur(20px);
		border: 1rpx solid rgba(255,255,255,0.6);
		border-top: 1rpx solid rgba(255,255,255,0.9);
		border-radius: 24rpx;
		box-shadow: 0 8rpx 40rpx rgba(99,102,241,0.15),
		            inset 0 1rpx 0 rgba(255,255,255,0.5);
		padding: 22rpx 28rpx 20rpx;
		display: flex;
		align-items: center;
		gap: 16rpx;
		animation: mpSlideDown 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
		transition: transform 0.45s cubic-bezier(0.4, 0, 0.2, 1),
		            opacity 0.45s ease,
		            border-radius 0.45s ease;

		/* 吸入胶囊动画 */
		&.mp-banner-absorb {
			/* 通过 CSS 变量拿到胶囊位置，计算偏移 */
			transform-origin: var(--capsule-x, 90%) var(--capsule-y, 0);
			transform: scale(0.05);
			opacity: 0;
			border-radius: 100rpx;
		}
	}

	@keyframes mpSlideDown {
		from {
			transform: translateY(-30rpx) scale(0.95);
			opacity: 0;
		}
		to {
			transform: translateY(0) scale(1);
			opacity: 1;
		}
	}

	.mp-content {
		display: flex;
		align-items: center;
		gap: 14rpx;
		flex: 1;
		min-width: 0;
	}
	.mp-icon  { font-size: 40rpx; flex-shrink: 0; }
	.mp-text  { display: flex; flex-direction: column; min-width: 0; }
	.mp-title { font-size: 28rpx; font-weight: 600; color: #1E293B; line-height: 1.3; }
	.mp-desc  { font-size: 20rpx; color: #64748B; margin-top: 4rpx; }

	/* 右侧按钮区 */
	.mp-action {
		display: flex;
		align-items: center;
		gap: 16rpx;
		flex-shrink: 0;
	}

	.mp-btn-wrap {
		position: relative;
		height: 56rpx;
		line-height: 56rpx;
		padding: 0 28rpx;
		border-radius: 28rpx;
		background: linear-gradient(135deg, #6366F1, #8B5CF6);
		box-shadow: 0 4rpx 12rpx rgba(99,102,241,0.3);
		overflow: hidden;
	}
	.mp-btn-text {
		font-size: 24rpx;
		font-weight: 500;
		color: #fff;
		white-space: nowrap;
	}
	/* official-account 组件覆盖在按钮上，完全透明 */
	.mp-official-overlay {
		position: absolute;
		top: 0; left: 0; right: 0; bottom: 0;
		opacity: 0.01;
		overflow: hidden;
	}

	.mp-countdown {
		font-size: 20rpx;
		color: #94A3B8;
		white-space: nowrap;
	}
</style>
