<template>
	<!--
		背景音乐小唱片:平时吸在屏幕边缘只露一小半;点一下滑出来,滑出来再点才是静音 / 恢复;
		3 秒没碰自动缩回去;可以拖到任意高度,松手吸到最近的左边或右边(位置所有页面共用、本机记住)。
		位置只动 transform,拖动时不加过渡,松手吸附时才有回弹动画。
	-->
	<view v-if="music.enabled" class="tui-music-btn" :class="{ animating: !dragging, expanded: expanded, muted: music.muted }"
		:style="boxStyle" @touchstart="onStart" @touchmove.stop.prevent="onMove" @touchend="onEnd" @touchcancel="onEnd"
		@tap.stop="onTap">
		<view class="disc" :class="{ spinning: music.playing }" :style="discStyle">
			<view class="groove g1"></view>
			<view class="groove g2"></view>
			<view class="sheen"></view>
			<view class="label">
				<view class="mark"></view>
				<view class="hole"></view>
			</view>
			<view class="rim-dot"></view>
		</view>
		<text v-if="expanded && music.playing" class="note" :class="dockSide">♪</text>
		<view v-if="music.muted" class="slash"></view>
	</view>
</template>

<script>
	import bgMusic from '@/utils/bg-music.js';

	const COLLAPSE_MS = 3000; // 多久没碰就缩回去
	const PEEK = 0.45;        // 缩回去时露出多少(占按钮宽度)
	const GAP = 12;           // 弹出时离屏幕边多远(px)

	/** 全局背景音乐的小唱片按钮。tui-page 里已经放了一个;不套 tui-page 的页面自己放一个即可 */
	export default {
		name: 'TuiMusicBtn',
		data() {
			return {
				music: bgMusic.state,
				size: 42,
				winW: 375,
				winH: 667,
				screenH: 667,
				minY: 80,
				maxY: 560,
				expanded: false,
				dragging: false,
				dragX: 0,
				dragY: 0
			};
		},
		computed: {
			dockSide() {
				return this.music.dock.side === 'left' ? 'left' : 'right';
			},
			dockY() {
				// 没拖过:按屏幕高度取默认位置(有底部导航的页面 windowHeight 更矮,用它会每页高低不一)
				const y = this.music.dock.y >= 0 ? this.music.dock.y : this.screenH * 0.58;
				return Math.min(this.maxY, Math.max(this.minY, y));
			},
			// 吸边时的横坐标:缩回只露 PEEK,弹出离边 GAP
			restX() {
				const s = this.size;
				if (this.dockSide === 'left') return this.expanded ? GAP : -(s * (1 - PEEK));
				return this.expanded ? this.winW - s - GAP : this.winW - s * PEEK;
			},
			boxStyle() {
				const x = this.dragging ? this.dragX : this.restX;
				const y = this.dragging ? this.dragY : this.dockY;
				return 'width:' + this.size + 'px;height:' + this.size + 'px;transform:translate3d(' + x + 'px,' + y + 'px,0);';
			},
			discStyle() {
				return 'width:' + this.size + 'px;height:' + this.size + 'px;';
			}
		},
		created() {
			try {
				const si = uni.getSystemInfoSync();
				this.winW = si.windowWidth || 375;
				this.winH = si.windowHeight || 667;
				this.screenH = si.screenHeight || this.winH;
				this.size = Math.round(84 * this.winW / 750);
				// 上面避开状态栏和胶囊那一行,下面避开底部导航
				this.minY = (si.statusBarHeight || 20) + 56;
				this.maxY = this.winH - this.size - 70;
			} catch (e) {}
		},
		// #ifdef H5
		// 电脑浏览器里没有触摸事件,鼠标按住拖也要能拖:按下记起点,移动/松开挂在 document 上,
		// 拖出按钮范围也跟得住。手机上走 touch,不受影响
		mounted() {
			this._onMouseDown = (e) => {
				if (e.button !== 0 || !this.$el || !this.$el.contains || !this.$el.contains(e.target)) return;
				e.preventDefault(); // 别选中文字、别拖出图片
				this.onStart(e);
				const move = (ev) => this.onMove(ev);
				const up = () => {
					document.removeEventListener('mousemove', move);
					document.removeEventListener('mouseup', up);
					this.onEnd();
				};
				document.addEventListener('mousemove', move);
				document.addEventListener('mouseup', up);
			};
			document.addEventListener('mousedown', this._onMouseDown, true);
		},
		// #endif
		beforeDestroy() {
			clearTimeout(this._timer);
			// #ifdef H5
			if (this._onMouseDown) document.removeEventListener('mousedown', this._onMouseDown, true);
			// #endif
		},
		methods: {
			scheduleCollapse() {
				clearTimeout(this._timer);
				this._timer = setTimeout(() => {
					if (!this.dragging) this.expanded = false;
				}, COLLAPSE_MS);
			},
			onStart(e) {
				const t = (e.touches && e.touches[0]) || e;
				this._start = { x: t.clientX, y: t.clientY, bx: this.restX, by: this.dockY };
				this._moved = false;
				clearTimeout(this._timer);
			},
			onMove(e) {
				if (!this._start) return;
				const t = (e.touches && e.touches[0]) || e;
				const dx = t.clientX - this._start.x;
				const dy = t.clientY - this._start.y;
				if (!this._moved && Math.abs(dx) < 6 && Math.abs(dy) < 6) return; // 手指抖一下不算拖
				this._moved = true;
				this.dragging = true;
				this.dragX = Math.min(this.winW - this.size, Math.max(0, this._start.bx + dx));
				this.dragY = Math.min(this.maxY, Math.max(this.minY, this._start.by + dy));
			},
			onEnd() {
				this._start = null;
				if (!this.dragging) {
					if (this.expanded) this.scheduleCollapse();
					return;
				}
				// 松手:吸到离得近的那一边,先弹出停一会儿再缩回去
				const side = this.dragX + this.size / 2 < this.winW / 2 ? 'left' : 'right';
				bgMusic.setDock(side, this.dragY);
				this.expanded = true;
				this.dragging = false;
				this._justDragged = true;
				setTimeout(() => { this._justDragged = false; }, 300);
				this.scheduleCollapse();
			},
			onTap() {
				if (this._justDragged) return; // 拖完松手不当成点击
				if (!this.expanded) {
					this.expanded = true; // 缩着的时候点:先滑出来
				} else {
					bgMusic.toggle();
					uni.showToast({ title: this.music.muted ? '已关闭背景音乐' : '已打开背景音乐', icon: 'none' });
				}
				this.scheduleCollapse();
			}
		}
	}
</script>

<style>
	.tui-music-btn {
		position: fixed;
		left: 0;
		top: 0;
		z-index: 998;
	}

	/* 吸边 / 弹出 / 缩回:带一点回弹 */
	.tui-music-btn.animating {
		transition: transform 0.38s cubic-bezier(0.34, 1.4, 0.64, 1);
	}

	/* 缩着的时候淡一点,不抢内容 */
	.tui-music-btn .disc {
		opacity: 0.88;
		transition: opacity 0.3s;
	}

	.tui-music-btn.expanded .disc {
		opacity: 1;
	}

	.disc {
		position: relative;
		border-radius: 50%;
		box-sizing: border-box;
		overflow: hidden;
		background: #1c1c22;
		border: 3rpx solid rgba(255, 255, 255, 0.9);
		box-shadow: 0 6rpx 20rpx rgba(0, 0, 0, 0.3);
	}

	/* 3 秒一圈,反光条和偏心白点转起来一眼就看得出 */
	.disc.spinning {
		animation: tui-music-spin 3s linear infinite;
	}

	@keyframes tui-music-spin {
		from { transform: rotate(0deg); }
		to { transform: rotate(360deg); }
	}

	/* 黑胶纹路 */
	.groove {
		position: absolute;
		border-radius: 50%;
		border: 1px solid rgba(255, 255, 255, 0.1);
	}

	.g1 { left: 14%; top: 14%; right: 14%; bottom: 14%; }
	.g2 { left: 24%; top: 24%; right: 24%; bottom: 24%; }

	/* 一道斜向反光,跟着唱片转 */
	.sheen {
		position: absolute;
		left: 0;
		top: 0;
		right: 0;
		bottom: 0;
		border-radius: 50%;
		background: linear-gradient(120deg, rgba(255, 255, 255, 0) 30%, rgba(255, 255, 255, 0.38) 48%, rgba(255, 255, 255, 0) 62%);
	}

	/* 红色标签 + 偏心白点(转动参照) */
	.label {
		position: absolute;
		left: 32%;
		top: 32%;
		width: 36%;
		height: 36%;
		border-radius: 50%;
		background: #c8102e;
	}

	.mark {
		position: absolute;
		left: 14%;
		top: 14%;
		width: 24%;
		height: 24%;
		border-radius: 50%;
		background: #ffffff;
	}

	.hole {
		position: absolute;
		left: 40%;
		top: 40%;
		width: 20%;
		height: 20%;
		border-radius: 50%;
		background: #f8f3ea;
	}

	/* 外圈一颗金点,也跟着转 */
	.rim-dot {
		position: absolute;
		left: 46%;
		top: 4%;
		width: 8%;
		height: 8%;
		border-radius: 50%;
		background: #e8b75a;
	}

	/* 弹出且在放:一个音符轻轻往上飘 */
	.note {
		position: absolute;
		top: -14rpx;
		font-size: 28rpx;
		color: #e8b75a;
		text-shadow: 0 2rpx 4rpx rgba(0, 0, 0, 0.4);
		animation: tui-music-note 1.6s ease-in-out infinite;
	}

	.note.right { left: -10rpx; }
	.note.left { right: -10rpx; }

	@keyframes tui-music-note {
		0% { transform: translateY(6rpx); opacity: 0; }
		30% { opacity: 1; }
		100% { transform: translateY(-18rpx); opacity: 0; }
	}

	/* 静音:一道斜杠,唱片变灰 */
	.muted .disc {
		filter: grayscale(1);
	}

	.slash {
		position: absolute;
		left: -8%;
		top: 47%;
		width: 116%;
		height: 5rpx;
		background: #ffffff;
		border-radius: 3rpx;
		transform: rotate(-45deg);
		box-shadow: 0 0 0 2rpx rgba(0, 0, 0, 0.35);
	}
</style>
