/**
 * 小程序「分享给好友」卡片图转成 5:4。
 *
 * 微信好友卡片固定 5:4,传进去的图比例不对就被裁:商品主图多是正方形,上下各被切掉一截,
 * 商品显示不完整。这里把原图**完整**缩放进 500×400 的画布居中,导出成本地临时文件给 imageUrl 用。
 * 两边不留白(第一版白边看着空):底下铺一层同一张图放大、模糊、稍压暗,商品带一点阴影浮在上面。
 * 模糊用「先画到 20×16 的小画布再放大」实现,不依赖 ctx.filter(小程序里不一定支持);
 * 小画布这一步不行就退回用整张图的平均色铺底,再不行就白底 —— 商品主体始终完整。
 *
 * 只在微信小程序里处理;任何一步失败都返回原图地址 —— 最差就是和原来一样被裁,不会更糟。
 *
 *   import { fitShareImage } from '@/utils/shareImage.js'
 *   fitShareImage(goods.cover_pic).then(path => { this.shareCover = path })
 */
const W = 500;
const H = 400;
const cache = {};

export function fitShareImage(url) {
	if (!url) return Promise.resolve('');
	// #ifdef MP-WEIXIN
	if (cache[url]) return cache[url];
	cache[url] = draw(url).catch(e => {
		console.warn('[shareImage] 转 5:4 失败,用原图', e);
		delete cache[url];
		return url;
	});
	return cache[url];
	// #endif
	// #ifndef MP-WEIXIN
	return Promise.resolve(url);
	// #endif
}

// #ifdef MP-WEIXIN
function draw(url) {
	return new Promise((resolve, reject) => {
		if (!wx.createOffscreenCanvas) return reject(new Error('基础库不支持离屏 canvas'));
		// 先下到本地:网络图直接画进 canvas 在部分机型上会跨域失败
		uni.getImageInfo({
			src: url,
			fail: reject,
			success: info => {
				const canvas = wx.createOffscreenCanvas({ type: '2d', width: W, height: H });
				const ctx = canvas.getContext('2d');
				const img = canvas.createImage();
				img.onerror = reject;
				img.onload = () => {
					const done = () => {
						// 前景:完整放进去(contain),不裁,带一点阴影
						const scale = Math.min(W / info.width, H / info.height);
						const dw = info.width * scale;
						const dh = info.height * scale;
						ctx.save();
						ctx.shadowColor = 'rgba(0, 0, 0, 0.35)';
						ctx.shadowBlur = 24;
						ctx.drawImage(img, (W - dw) / 2, (H - dh) / 2, dw, dh);
						ctx.restore();
						exportCanvas(canvas).then(resolve, reject);
					};
					paintBackdrop(ctx, info).then(done, () => {
						ctx.fillStyle = '#ffffff';
						ctx.fillRect(0, 0, W, H);
						done();
					});
				};
				img.src = info.path;
			},
		});
	});
}

/**
 * 背景:同一张图铺满后放大模糊、压暗一点。
 * 小画布(20×16)按 cover 画进去,再整张拉回 500×400:放大时的插值就是模糊。
 * 小画布不能当 drawImage 的源时(老基础库),退回读小画布的平均色纯色铺底。
 */
function paintBackdrop(ctx, info) {
	return new Promise((resolve, reject) => {
		const tw = 20, th = 16;
		let tiny;
		try {
			tiny = wx.createOffscreenCanvas({ type: '2d', width: tw, height: th });
		} catch (e) {
			return reject(e);
		}
		const tctx = tiny.getContext('2d');
		const src = tiny.createImage();
		src.onerror = reject;
		src.onload = () => {
			try {
				const s = Math.max(tw / info.width, th / info.height);
				tctx.drawImage(src, (tw - info.width * s) / 2, (th - info.height * s) / 2, info.width * s, info.height * s);
				try {
					ctx.imageSmoothingEnabled = true;
					ctx.drawImage(tiny, -10, -8, W + 20, H + 16); // 四周多出一点,边缘不露底
				} catch (e) {
					const px = tctx.getImageData(0, 0, tw, th).data;
					let r = 0, g = 0, b = 0;
					const n = px.length / 4;
					for (let i = 0; i < px.length; i += 4) { r += px[i]; g += px[i + 1]; b += px[i + 2]; }
					ctx.fillStyle = `rgb(${Math.round(r / n)}, ${Math.round(g / n)}, ${Math.round(b / n)})`;
					ctx.fillRect(0, 0, W, H);
				}
				ctx.fillStyle = 'rgba(0, 0, 0, 0.18)';
				ctx.fillRect(0, 0, W, H);
				resolve();
			} catch (e) {
				reject(e);
			}
		};
		src.src = info.path;
	});
}

/** 导出成本地文件:优先 canvasToTempFilePath,老基础库不认离屏 canvas 时退回 toDataURL 写文件 */
function exportCanvas(canvas) {
	return new Promise((resolve, reject) => {
		const viaDataURL = () => {
			try {
				const base64 = canvas.toDataURL('image/jpeg', 0.9).replace(/^data:image\/\w+;base64,/, '');
				const path = `${wx.env.USER_DATA_PATH}/share_${Date.now()}.jpg`;
				wx.getFileSystemManager().writeFile({
					filePath: path, data: base64, encoding: 'base64',
					success: () => resolve(path), fail: reject,
				});
			} catch (e) {
				reject(e);
			}
		};
		wx.canvasToTempFilePath({
			canvas, fileType: 'jpg', quality: 0.9,
			success: res => resolve(res.tempFilePath),
			fail: viaDataURL,
		});
	});
}
// #endif
