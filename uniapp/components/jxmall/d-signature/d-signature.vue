<template>
	<!--
		手写签名(公共组件,各插件复用)。easycom 自动注册,直接写 <d-signature>。

		用法:
		  <d-signature :visible.sync="signVisible" title="审批签名" @confirm="onSigned" />
		  onSigned({ url, path, reused }) { this.signUrl = url }

		· 默认签完自动上传到 /api/upload/upload,confirm 回来的是线上图片地址;:upload="false" 时只给本地临时路径
		· 签过一次后,下次可以「使用上次签名」(按登录账号区分,换账号不串);:reuse="false" 关掉
		· 小程序用 2d 画布(同层渲染,弹层里不会盖住按钮);其他端用旧版画布接口
		· 导出透明底 PNG,方便以后叠到合同/审批单上
	-->
	<view v-if="visible" class="d-sign-mask" :style="themeStyle" @touchmove.stop.prevent="noop">
		<view class="d-sign-panel">
			<view class="d-sign-hd">
				<text class="d-sign-title">{{ title }}</text>
				<text class="d-sign-close" @click="cancel">×</text>
			</view>
			<text class="d-sign-tip">{{ tip }}</text>

			<view class="d-sign-board">
				<!-- #ifdef MP-WEIXIN -->
				<canvas
					type="2d"
					:id="canvasId"
					class="d-sign-canvas"
					disable-scroll
					@touchstart="onStart"
					@touchmove="onMove"
					@touchend="onEnd"
					@touchcancel="onEnd"
				></canvas>
				<!-- #endif -->
				<!-- #ifndef MP-WEIXIN -->
				<canvas
					:id="canvasId"
					:canvas-id="canvasId"
					class="d-sign-canvas"
					disable-scroll
					@touchstart="onStart"
					@touchmove="onMove"
					@touchend="onEnd"
					@touchcancel="onEnd"
				></canvas>
				<!-- #endif -->
				<view v-if="!drawn" class="d-sign-placeholder">
					<text class="d-sign-placeholder-text">签名区</text>
					<view class="d-sign-baseline"></view>
				</view>
			</view>

			<!-- 留住占位，避免落笔后画布移动导致笔迹偏移。 -->
			<view v-if="lastUrl" class="d-sign-reuse" :class="drawn ? 'd-sign-reuse-hidden' : ''" @click="useLast">
				<text class="d-sign-reuse-label">使用上次签名</text>
				<image :src="lastUrl" class="d-sign-reuse-img" mode="aspectFit" />
			</view>

			<view class="d-sign-btns">
				<button class="d-sign-btn ghost" @click="clear">重写</button>
				<button class="d-sign-btn primary" :loading="saving" :disabled="saving" @click="confirm">确认签名</button>
			</view>
		</view>
	</view>
</template>

<script>
const STORAGE_KEY = 'D_SIGNATURE_LAST';

export default {
	name: 'd-signature',
	props: {
		visible: { type: Boolean, default: false },
		title: { type: String, default: '手写签名' },
		tip: { type: String, default: '请在下方空白处用手指签写您的姓名' },
		penColor: { type: String, default: '#1f1f1f' },
		lineWidth: { type: Number, default: 3 },
		// 签完是否自动上传(false 时 confirm 只给本地临时路径,由调用方自己处理)
		upload: { type: Boolean, default: true },
		// 是否提供「使用上次签名」
		reuse: { type: Boolean, default: true },
		// 少于这么多个触点视为没签(防止误点一下就提交一个点)
		minPoints: { type: Number, default: 10 }
	},
	data() {
		return {
			canvasId: 'dsign' + Math.random().toString(36).slice(2, 10),
			drawn: false,
			saving: false,
			lastUrl: ''
		};
	},
	watch: {
		visible: {
			immediate: true,
			handler(v) {
				if (v) {
					this.loadLast();
					this.$nextTick(() => this.init(0));
				} else {
					this.release();
				}
			}
		}
	},
	beforeDestroy() {
		this.release();
	},
	methods: {
		noop() {},

		// 画布节点在弹层渲染出来之后才查得到,小程序偶尔要多等一帧,查不到就重试几次
		init(retry) {
			this.drawn = false;
			this.count = 0;
			this.points = [];
			const query = uni.createSelectorQuery().in(this);
			// #ifdef MP-WEIXIN
			query.select('#' + this.canvasId).fields({ node: true, size: true, rect: true }).exec(res => {
				const r = res && res[0];
				if (!r || !r.node) {
					if (retry < 5) setTimeout(() => this.init(retry + 1), 60);
					return;
				}
				const canvas = r.node;
				const ctx = canvas.getContext('2d');
				const dpr = this.pixelRatio();
				canvas.width = Math.round(r.width * dpr);
				canvas.height = Math.round(r.height * dpr);
				ctx.scale(dpr, dpr);
				this.canvas = canvas;
				this.ctx = ctx;
				this.legacy = false;
				this.size = { width: r.width, height: r.height, left: r.left || 0, top: r.top || 0 };
				this.applyPen();
			});
			// #endif
			// #ifndef MP-WEIXIN
			query.select('#' + this.canvasId).boundingClientRect(r => {
				if (!r || !r.width) {
					if (retry < 5) setTimeout(() => this.init(retry + 1), 60);
					return;
				}
				this.ctx = uni.createCanvasContext(this.canvasId, this);
				this.legacy = true;
				this.size = { width: r.width, height: r.height, left: r.left || 0, top: r.top || 0 };
				this.applyPen();
				// #ifdef H5
				this.bindMouse();
				// #endif
			}).exec();
			// #endif
		},

		release() {
			// #ifdef H5
			this.unbindMouse();
			// #endif
			this.mouseDrawing = false;
			this.ctx = null;
			this.canvas = null;
			this.points = [];
			this.saving = false;
		},

		pixelRatio() {
			try {
				const info = uni.getWindowInfo ? uni.getWindowInfo() : uni.getSystemInfoSync();
				return info.pixelRatio || 2;
			} catch (e) {
				return 2;
			}
		},

		applyPen() {
			const ctx = this.ctx;
			if (!ctx) return;
			if (this.legacy) {
				ctx.setStrokeStyle(this.penColor);
				ctx.setFillStyle(this.penColor);
				ctx.setLineWidth(this.lineWidth);
				ctx.setLineCap('round');
				ctx.setLineJoin('round');
			} else {
				ctx.strokeStyle = this.penColor;
				ctx.fillStyle = this.penColor;
				ctx.lineWidth = this.lineWidth;
				ctx.lineCap = 'round';
				ctx.lineJoin = 'round';
			}
		},

		flush() {
			if (this.legacy && this.ctx) this.ctx.draw(true);
		},

		point(e) {
			const t = (e.touches && e.touches[0]) || (e.changedTouches && e.changedTouches[0]);
			if (!t || !this.size) return null;
			let x = t.x;
			let y = t.y;
			if (typeof x !== 'number' || typeof y !== 'number') {
				x = t.clientX - this.size.left;
				y = t.clientY - this.size.top;
			}
			return { x, y };
		},

		// #ifdef H5
		// 直接监听原生事件，保留 button/buttons 和实时节点坐标，避免 uni-app 归一化时丢失。
		bindMouse() {
			this.unbindMouse();
			const target = this.$el && this.$el.querySelector('#' + this.canvasId);
			if (!target) return;
			this.mouseTarget = target;
			target.addEventListener('mousedown', this.onMouseStart);
			target.addEventListener('mousemove', this.onMouseMove);
			target.addEventListener('mouseup', this.onMouseEnd);
			target.addEventListener('mouseleave', this.onMouseEnd);
		},

		unbindMouse() {
			const target = this.mouseTarget;
			if (!target) return;
			target.removeEventListener('mousedown', this.onMouseStart);
			target.removeEventListener('mousemove', this.onMouseMove);
			target.removeEventListener('mouseup', this.onMouseEnd);
			target.removeEventListener('mouseleave', this.onMouseEnd);
			this.mouseTarget = null;
		},

		// 鼠标坐标转成画布内坐标后复用同一套笔迹逻辑。
		mousePoint(e) {
			if (!this.size || typeof e.clientX !== 'number' || typeof e.clientY !== 'number') return null;
			const target = e.currentTarget;
			const rect = target && typeof target.getBoundingClientRect === 'function'
				? target.getBoundingClientRect() : this.size;
			return { x: e.clientX - rect.left, y: e.clientY - rect.top };
		},

		onMouseStart(e) {
			// 触屏浏览器可能补发兼容鼠标事件，避免同一笔重复落点。
			if (e.button !== 0 || (e.sourceCapabilities && e.sourceCapabilities.firesTouchEvents)) return;
			const p = this.mousePoint(e);
			if (!p || !this.ctx) return;
			e.preventDefault();
			this.mouseDrawing = true;
			this.onStart({ touches: [p] });
		},

		onMouseMove(e) {
			if (!this.mouseDrawing) return;
			if (e.buttons === 0) return this.onMouseEnd();
			e.preventDefault();
			const p = this.mousePoint(e);
			if (p) this.onMove({ touches: [p] });
		},

		onMouseEnd() {
			if (!this.mouseDrawing) return;
			this.mouseDrawing = false;
			this.onEnd();
		},
		// #endif

		onStart(e) {
			const p = this.point(e);
			if (!p || !this.ctx) return;
			this.points = [p];
			this.count++;
			// 落笔先点一个圆点,只点一下也有痕迹
			const ctx = this.ctx;
			ctx.beginPath();
			ctx.arc(p.x, p.y, this.lineWidth / 2, 0, Math.PI * 2);
			ctx.fill();
			this.flush();
		},

		onMove(e) {
			const p = this.point(e);
			if (!p || !this.ctx || !this.points.length) return;
			const pts = this.points;
			pts.push(p);
			this.count++;
			const ctx = this.ctx;
			const n = pts.length;
			// 用相邻两点的中点做二次贝塞尔,笔迹比直线连点圆滑
			if (n === 2) {
				const m = this.mid(pts[0], pts[1]);
				ctx.beginPath();
				ctx.moveTo(pts[0].x, pts[0].y);
				ctx.lineTo(m.x, m.y);
				ctx.stroke();
			} else {
				const p0 = pts[n - 3], p1 = pts[n - 2], p2 = pts[n - 1];
				const m1 = this.mid(p0, p1), m2 = this.mid(p1, p2);
				ctx.beginPath();
				ctx.moveTo(m1.x, m1.y);
				ctx.quadraticCurveTo(p1.x, p1.y, m2.x, m2.y);
				ctx.stroke();
			}
			this.flush();
			if (!this.drawn && this.count >= this.minPoints) this.drawn = true;
			// 只留最后几个点够画曲线,长签名不攒一大串
			if (pts.length > 6) this.points = pts.slice(-3);
		},

		onEnd() {
			const pts = this.points;
			if (this.ctx && pts.length >= 2) {
				const a = pts[pts.length - 2], b = pts[pts.length - 1];
				const m = this.mid(a, b);
				this.ctx.beginPath();
				this.ctx.moveTo(m.x, m.y);
				this.ctx.lineTo(b.x, b.y);
				this.ctx.stroke();
				this.flush();
			}
			this.points = [];
		},

		mid(a, b) {
			return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
		},

		clear() {
			if (!this.ctx || !this.size) return;
			this.ctx.clearRect(0, 0, this.size.width, this.size.height);
			if (this.legacy) this.ctx.draw(false);
			this.drawn = false;
			this.count = 0;
			this.points = [];
		},

		cancel() {
			this.$emit('update:visible', false);
			this.$emit('cancel');
		},

		userId() {
			try {
				const info = (this.$store && this.$store.getters['user/userInfo']) || {};
				return String(info.id || info.user_id || '');
			} catch (e) {
				return '';
			}
		},

		loadLast() {
			this.lastUrl = '';
			if (!this.reuse || !this.upload) return;
			const uid = this.userId();
			if (!uid) return;
			try {
				const last = uni.getStorageSync(STORAGE_KEY);
				if (last && last.uid === uid && last.url) this.lastUrl = last.url;
			} catch (e) {}
		},

		useLast() {
			if (!this.lastUrl) return;
			this.$emit('confirm', { url: this.lastUrl, path: '', reused: true });
			this.$emit('update:visible', false);
		},

		exportImage() {
			return new Promise((resolve, reject) => {
				const opts = {
					fileType: 'png',
					success: r => resolve(r.tempFilePath),
					fail: err => reject(err)
				};
				// #ifdef MP-WEIXIN
				opts.canvas = this.canvas;
				// #endif
				// #ifndef MP-WEIXIN
				opts.canvasId = this.canvasId;
				// #endif
				uni.canvasToTempFilePath(opts, this);
			});
		},

		uploadImage(path) {
			const http = this.$http;
			return new Promise((resolve, reject) => {
				uni.uploadFile({
					url: http.getApiRoot() + '/api/upload/upload',
					name: 'file',
					filePath: path,
					header: {
						'x-mall-id': http.getMallId(),
						'x-access-token': http.getToken(),
						'x-parent-id': http.getPid(),
						'x-platform': http.getPlatform()
					},
					success: res => {
						let d = {};
						// 去 BOM 再解析:公共上传返回偶尔带 BOM,直接 JSON.parse 会抛异常卡住
						try { d = JSON.parse(String(res.data || '').replace(/﻿/g, '') || '{}'); } catch (e) { d = {}; }
						if (res.statusCode === 200 && d.code === 0 && d.data && d.data.url) {
							resolve(d.data.url);
						} else {
							reject(new Error(d.msg || '签名上传失败'));
						}
					},
					fail: () => reject(new Error('网络异常,签名上传失败'))
				});
			});
		},

		async confirm() {
			if (this.saving) return;
			if (!this.drawn) {
				uni.showToast({ title: '请先签名', icon: 'none' });
				return;
			}
			this.saving = true;
			try {
				const path = await this.exportImage();
				let url = '';
				if (this.upload) {
					url = await this.uploadImage(path);
					const uid = this.userId();
					if (this.reuse && uid) {
						try { uni.setStorageSync(STORAGE_KEY, { uid, url, at: Date.now() }); } catch (e) {}
					}
				}
				this.$emit('confirm', { url, path, reused: false });
				this.$emit('update:visible', false);
			} catch (e) {
				uni.showToast({ title: (e && e.message) || '签名保存失败,请重试', icon: 'none' });
			} finally {
				this.saving = false;
			}
		}
	}
};
</script>

<style lang="scss" scoped>
.d-sign-mask {
	position: fixed;
	left: 0;
	right: 0;
	top: 0;
	bottom: 0;
	z-index: 1000;
	background: rgba(0, 0, 0, 0.5);
	display: flex;
	flex-direction: column;
	justify-content: flex-end;
}

.d-sign-panel {
	background: #fff;
	border-radius: 28rpx 28rpx 0 0;
	padding: 28rpx 30rpx;
	padding-bottom: calc(28rpx + env(safe-area-inset-bottom));
}

.d-sign-hd {
	display: flex;
	align-items: center;
	justify-content: space-between;
}

.d-sign-title {
	font-size: 32rpx;
	font-weight: 700;
	color: #1f1f1f;
}

.d-sign-close {
	font-size: 44rpx;
	line-height: 1;
	color: #999;
	padding: 0 8rpx;
}

.d-sign-tip {
	display: block;
	font-size: 24rpx;
	color: #999;
	margin: 12rpx 0 20rpx;
}

.d-sign-board {
	position: relative;
	height: 520rpx;
	border: 2rpx dashed #d9d9d9;
	border-radius: 16rpx;
	background: #fafafa;
	overflow: hidden;
}

.d-sign-canvas {
	width: 100%;
	height: 100%;
}

.d-sign-placeholder {
	position: absolute;
	left: 0;
	right: 0;
	top: 0;
	bottom: 0;
	pointer-events: none;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
}

.d-sign-placeholder-text {
	font-size: 56rpx;
	color: #e5e5e5;
	letter-spacing: 16rpx;
}

.d-sign-baseline {
	width: 70%;
	height: 2rpx;
	background: #ececec;
	margin-top: 40rpx;
}

.d-sign-reuse {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-top: 20rpx;
	padding: 12rpx 20rpx;
	border-radius: 12rpx;
	background: var(--theme-color-bg);
}

.d-sign-reuse-label {
	font-size: 26rpx;
	color: var(--theme-color);
}

.d-sign-reuse-hidden {
	visibility: hidden;
	pointer-events: none;
}

.d-sign-reuse-img {
	width: 200rpx;
	height: 64rpx;
}

.d-sign-btns {
	display: flex;
	gap: 20rpx;
	margin-top: 24rpx;
}

.d-sign-btn {
	flex: 1;
	height: 88rpx;
	line-height: 88rpx;
	border-radius: 44rpx;
	font-size: 30rpx;
	font-weight: 600;
	margin: 0;

	&::after {
		border: none;
	}

	&.ghost {
		background: #fff;
		color: #555;
		border: 2rpx solid #d9d9d9;
	}

	&.primary {
		background: var(--theme-color);
		color: #fff;
	}
}
</style>
