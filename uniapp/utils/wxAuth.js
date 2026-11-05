// 微信 H5 静默获取 openid（snsapi_base）
// ------------------------------------------------------------
// 仅在「H5 + 微信内置浏览器」生效；小程序/App/普通浏览器为 no-op。
// openid 仅供「支付 / 推送」出方向使用，绝不用于身份认证（与后端约定一致）。
// 缓存按 mall_id 分键 + TTL：切换微信号后，冷启动复核或 TTL 过期即自愈，杜绝串号。
import http from '@/utils/request.js';
import util from '@/utils/util.js';
import { Base64 } from '@/jssdk/base64.min.js';

const KEY_PREFIX = 'WX_OPENID_';
const TTL = 24 * 60 * 60 * 1000; // 缓存兜底有效期（方案A下每次冷启动都复核，TTL 仅作离线兜底）

let _ready = null; // 本次启动 openid 就绪 Promise（ensure 设置，页面 ready() 读取）

const wxAuth = {
	_key() {
		return KEY_PREFIX + http.getMallId(); // 按 mall_id 分键，多租户不串号
	},

	// 读未过期缓存，返回 { openid, unionid, t } 或 null
	_read() {
		// #ifdef H5
		const c = uni.getStorageSync(this._key());
		if (c && c.openid && c.t && (Date.now() - c.t) < TTL) {
			return c;
		}
		// #endif
		return null;
	},

	getOpenid() {
		const c = this._read();
		return c ? c.openid : '';
	},

	getUnionid() {
		const c = this._read();
		return c ? (c.unionid || '') : '';
	},

	// 【仅 App.vue onLaunch 调用】方案A：每次冷启动都跳 snsapi_base 复核当前微信号 openid，
	// 彻底杜绝切号串号。页面不要调此方法（会触发跳转），改用 ready() / getOpenid()。
	ensure() {
		// #ifdef H5
		if (!util.isWeChat()) { _ready = Promise.resolve(''); return _ready; }

		const authCode = http.getUrlParam('auth_code');
		if (authCode) {
			_ready = this._exchange(authCode); // 回跳：换当前微信号 openid 入缓存
			return _ready;
		}
		this._redirect();                 // 无 code → 静默跳 snsapi_base 复核（页面离开）
		_ready = Promise.resolve('');
		return _ready;
		// #endif
		// #ifndef H5
		_ready = Promise.resolve('');
		return _ready;
		// #endif
	},

	// 【页面用】等待本次启动 openid 就绪，返回 Promise<openid>；只读，绝不触发跳转。
	ready() {
		if (_ready) return _ready;
		return Promise.resolve(this.getOpenid());
	},

	// 跳转 snsapi_base 静默授权（不弹页）
	_redirect() {
		// #ifdef H5
		if (http.getUrlParam('auth_code')) return; // 防循环：已带 code 不再跳
		http.markNavigating(); // 标记即将整页跳转，抑制在途请求被中断时误报"网络不给力"
		const base64Url = Base64.encode(window.location.href);
		const apiroot = getApp().globalData.apiroot; // = domain + '/index.php?r='
		window.location.href = apiroot + '/wechat/auth/openid&redirect_url=' + base64Url +
			'&mall_id=' + http.getMallId();
		// #endif
	},

	// 用 auth_code 换 openid（复用现有 /api/wechat/wechat-info）。返回 Promise<openid>。
	_exchange(authCode) {
		return http.request({
			url: '/api/wechat/wechat-info',
			method: 'POST',
			data: { auth_code: authCode }
		}).then(res => {
			if (res.code == 0 && res.data && res.data.info && res.data.info.openid) {
				uni.setStorageSync(this._key(), {
					openid: res.data.info.openid,
					unionid: res.data.info.unionid || '',
					t: Date.now()
				});
				return res.data.info.openid;
			}
			return '';
		}).catch(() => '');
	}
};

export default wxAuth;
