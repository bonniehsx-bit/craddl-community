/**
 * 全局背景音乐(后台「商城设置 → 背景音乐」)。
 *
 * 整个小程序只有一个播放器:App 拿到商城配置后 init(),进小程序就循环播放,切换页面不中断;
 * App onHide 暂停、onShow 接着放(不做退出后台继续播放 —— 用户已经离开了还在响很招人烦,
 * 而且要单独申请后台音频能力)。
 * 用户点右下角小唱片(tui-music-btn)静音后记在本机,之后进来也不放,直到他再点开。
 * iPhone 开了静音键时不出声(obeyMuteSwitch)。
 *
 * 配置字段:bg_music_open('1'/'0')、bg_music_url、bg_music_volume(10-100)、
 *          bg_music_start / bg_music_end(YYYY-MM-DD,可空;不在时间段内不放)
 */
import Vue from 'vue';

const MUTE_KEY = 'BG_MUSIC_MUTED';
const DOCK_KEY = 'BG_MUSIC_DOCK';

function readDock() {
	try {
		const d = uni.getStorageSync(DOCK_KEY);
		if (d && (d.side === 'left' || d.side === 'right') && d.y >= 0) return { side: d.side, y: Number(d.y) };
	} catch (e) {}
	return { side: 'right', y: -1 }; // y = -1:还没拖过,按钮自己算默认高度
}

// 小唱片按钮读这个:enabled = 本商城开了且在时间段内;playing = 正在出声;muted = 用户点了静音
// dock = 吸在哪一边、离顶多高(px)。每个页面各有一个按钮实例,位置放这里共享,换页面不跳
const state = Vue.observable({ enabled: false, playing: false, muted: false, dock: readDock() });

let ctx = null;
let src = '';

function today() {
	const d = new Date();
	const p = n => (n < 10 ? '0' : '') + n;
	return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate());
}

function inWindow(start, end) {
	const t = today();
	if (start && t < start) return false;
	if (end && t > end) return false;
	return true;
}

function destroy() {
	if (ctx) {
		try { ctx.destroy(); } catch (e) {}
	}
	ctx = null;
	src = '';
	state.playing = false;
}

function play() {
	if (!ctx || !state.enabled || state.muted) return;
	try { ctx.play(); } catch (e) {}
}

// #ifdef H5
// 浏览器不让没交互就出声:第一次点屏幕时再补一次播放
let h5Unlocked = false;
function unlockOnFirstTouch() {
	if (h5Unlocked || typeof document === 'undefined') return;
	h5Unlocked = true;
	const once = () => {
		document.removeEventListener('touchstart', once, true);
		document.removeEventListener('click', once, true);
		if (!state.playing) play();
	};
	document.addEventListener('touchstart', once, true);
	document.addEventListener('click', once, true);
}
// #endif

export default {
	state,

	/** 拿到商城配置后调;配置变了(换歌、关掉、过了时间段)再调一次即可 */
	init(setting) {
		const s = setting || {};
		const url = String(s.bg_music_url || '').trim();
		const on = String(s.bg_music_open) === '1' && !!url && inWindow(s.bg_music_start, s.bg_music_end);
		state.enabled = on;
		if (!on) {
			destroy();
			return;
		}
		try { state.muted = !!uni.getStorageSync(MUTE_KEY); } catch (e) {}

		if (!ctx || src !== url) {
			destroy();
			ctx = uni.createInnerAudioContext();
			ctx.loop = true;
			ctx.obeyMuteSwitch = true;
			ctx.src = src = url;
			ctx.onPlay(() => { state.playing = true; });
			ctx.onPause(() => { state.playing = false; });
			ctx.onStop(() => { state.playing = false; });
			ctx.onError(() => { state.playing = false; }); // 链接失效等:不报错打扰用户,按钮显示成没在放
		}
		const vol = Number(s.bg_music_volume);
		ctx.volume = Math.max(0.1, Math.min(1, (vol > 0 ? vol : 60) / 100));
		play();
		// #ifdef H5
		unlockOnFirstTouch();
		// #endif
	},

	/** App onShow */
	resume() {
		play();
	},

	/** App onHide */
	pause() {
		if (ctx) {
			try { ctx.pause(); } catch (e) {}
		}
	},

	/** 小唱片拖动后记住吸附位置(本机),其它页面的按钮跟着走 */
	setDock(side, y) {
		state.dock = { side, y: Math.round(y) };
		try { uni.setStorageSync(DOCK_KEY, state.dock); } catch (e) {}
	},

	/** 小唱片按钮:静音 / 恢复,记在本机 */
	toggle() {
		state.muted = !state.muted;
		try { uni.setStorageSync(MUTE_KEY, state.muted ? 1 : ''); } catch (e) {}
		if (state.muted) this.pause();
		else play();
	}
};
