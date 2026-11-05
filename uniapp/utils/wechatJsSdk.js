import {
	request,
	getMallId,
	getUrlParam,
	getApiRoot
} from './request.js'
import {
	Base64
} from '@/jssdk/base64.min.js';

var jweixin = require('jweixin-module');
export default {
	getUrl() {
		let pages = getCurrentPages();
		let curPage = pages[pages.length - 1];
		let url = curPage.$route.fullPath;
		return url;
	},
	//判断是否在微信中  
	isWechat: function() {
		var ua = window.navigator.userAgent.toLowerCase();
		if (ua.match(/micromessenger/i) == 'micromessenger') {
			// console.log('是微信客户端')
			return true;
		} else {
			// console.log('不是微信客户端')
			return false;
		}
	},
	//初始化sdk配置  
	/**
	 * 拉取并应用公众号 JS-SDK 签名配置。
	 *
	 * @param {Function} callback 成功回调,入参为签名配置
	 * @param {String}   url      预留
	 * @param {Function} onFail   失败回调,入参为可读原因。**必须传** —— 不传的话
	 *                            调用方(如扫码)会在失败时既不 resolve 也不 reject,页面一直转圈。
	 */
	initJssdk: function(callback, url, onFail) {
		var self = this;
		let root_url = window.location.href.split('#')[0];
		var fail = function(msg) {
			// 失败**绝不写缓存**:原实现把坏配置缓存 50 分钟,期间 wx.config 从未成功、
			// jweixin.ready 永不触发 → 回调永不执行 → 扫码页一直转圈,后端修好了也得等缓存过期。
			self._jssdkUrl = null;
			self._jssdkAt = 0;
			self._jssdkCache = null;
			console.warn('[JSSDK] ' + msg);
			if (onFail) onFail(msg);
		};

		// SPA 同一页面（# 前 URL 相同）50 分钟内复用已签名配置,避免每次跳页都请求
		if (self._jssdkUrl === root_url && self._jssdkAt && (Date.now() - self._jssdkAt) < 3000000) {
			if (callback) jweixin.ready(function() { callback(self._jssdkCache); });
			return;
		}

		request({
			url: '/api/wechat/jssdk',
			data: {
				url: root_url
			}
		}).then((res) => {
			// 后端在「未配公众号 / 签名失败 / 配置不完整」时会返回非 0 并带 msg,
			// 直接透出去,别再拿着空配置去 config 换一句笼统的「配置失败」。
			if (!res || res.code != 0) {
				fail((res && res.msg) ? res.msg : '微信配置获取失败');
				return;
			}
			let sdk = res.data && res.data.jssdk;
			if (!sdk || !sdk.appId || !sdk.signature) {
				fail('微信 JS-SDK 配置不完整(缺 appId/签名)');
				return;
			}

			// config 是异步的,错误只能从 jweixin.error 里拿 —— 必须在 config 之前注册
			jweixin.error(function(e) {
				fail('微信 JS-SDK 校验失败:' + ((e && e.errMsg) ? e.errMsg : '请确认当前域名已加入公众号「JS接口安全域名」'));
			});
			jweixin.config({
				debug: sdk.debug,
				appId: sdk.appId,
				timestamp: sdk.timestamp,
				nonceStr: sdk.nonceStr,
				signature: sdk.signature,
				jsApiList: sdk.jsApiList
			});
			if (res.data.default_share) {
				uni.setStorageSync('DEFAULT_SHARE', res.data.default_share);
			}
			self._jssdkUrl = root_url;
			self._jssdkAt = Date.now();
			self._jssdkCache = sdk;
			if (callback) {
				callback(sdk);
			}
		}).catch((e) => {
			// 原实现没有 catch:网络异常/接口 500 时静默,调用方永远等不到回调
			fail('微信配置请求失败,请检查网络后重试');
		});
	},
	//在需支付
	pay: function(data, success_url, fail_url, paramas) {

		if (!this.isWechat()) {
			return;
		}
		return new Promise((resolve, reject) => {
			//每需要重新初始化
			this.initJssdk(function(sdk) {
				jweixin.ready(function() {
					// ⚠ 时间戳的大小写是微信的一个老坑,别再"顺手改整齐":
					//   后端下单返回 / 小程序 wx.requestPayment 用的是 timeStamp(大写 S),
					//   而 H5 的 jweixin.chooseWXPay 要的是 timestamp(全小写)。
					// 网关把上游返回的支付参数原样透传,拿到的通常是 timeStamp;
					// 只读 data.timestamp 会得到 undefined,chooseWXPay 拿着空时间戳去校验,
					// 微信就报「支付验证签名失败」——报错来自微信本身,不是支付平台拒单,极易查错方向。
					// 其余几个字段(appId/nonceStr/paySign)两边同名,但网关口径未必统一,一并兜底。
					var _ts = data.timestamp || data.timeStamp;
					if (!_ts) {
						console.warn('[微信支付] 支付参数里没有 timestamp/timeStamp,收到的字段:',
							Object.keys(data || {}).join(','));
					}
					jweixin.chooseWXPay({
						appId: data.appId || data.appid,
						timestamp: _ts, // 支付签名时间戳(见上方注释:必须兼容 timeStamp)
						nonceStr: data.nonceStr || data.noncestr, // 支付签名随机串，不长于 32 位
						package: data
							.package, // 统一支付接口返回的prepay_id参数值，提交格式如：prepay_id=\*\*\*）
						signType: data.signType || data.signtype, // 签名方式，默认为'SHA1'，使用新版支付需传入'MD5'
						paySign: data.paySign || data.paysign, // 支付签名
						success: function(res) {
							resolve(res)
						},
						cancel: function(res) {
						 
							reject({
								msg: '取消支付，重新发起支付'
							});
						},
						fail: function(e) {
						 
							reject({
								msg: '支付过程中发生错误，重新发起支付'
							});
						}
					});
				});
			}, null, function(msg) {
				// 初始化失败也要 reject,否则这个 Promise 永不 settle、支付页一直转圈
				reject({ msg: msg || '微信支付初始化失败,请刷新后重试' });
			});
		});

	},

	share: function(param, diy) {

		if (!this.isWechat()) {
			return;
		}
		// —— 自定义分享优先：本页若已设过 diy 分享，后续默认分享(无 diy)不再覆盖，避免竞态把商品分享盖回商城默认 ——
		var _routes = getCurrentPages();
		var _nowRoute = _routes.length ? _routes[_routes.length - 1].$route.fullPath : '';
		if (diy) {
			this._customShareRoute = _nowRoute;
		} else if (this._customShareRoute === _nowRoute) {
			return;
		}
		// 标题/描述/图在配置完成后(jweixin.ready 里)再读 DEFAULT_SHARE，避免首屏读到空值
		let routes = getCurrentPages();
		// 购物车中准备的好物分享落到独立详情页；默认仍分享当前页面。
		let curRoute = param && typeof param.route === 'string' ? param.route
			: (routes.length ? routes[routes.length - 1].$route.fullPath : '/pages/index/index');
		let user = uni.getStorageSync("USER") || {};

		// URL 拼装抽成纯函数(见文件底部 buildShareUrl),便于对各种 URL 形态做单测。
		// 旧实现是在**整条 URL** 上 split('?')/split('&'),对 hash 路由是错的:
		//   https://host/h5/?mall_id=5#/pages/index/index
		//   → split('?') 把 "mall_id=5#/pages/index/index" 当成一整段 query
		//   → pid 被追加成 "#/pages/index/index&pid=1":既读不出 pid(推荐关系断),
		//     路由 path 也变成 "/pages/index/index&pid=1" 可能匹配不到页面。
		let url = buildShareUrl({
			href: window.location.href,
			curRoute: curRoute,
			mallId: getMallId(),
			pid: param && param.pid !== undefined ? param.pid : ((user && user.id) ? user.id : 0)
		});

		//每需要重新初始化
		this.initJssdk(function(sdk) {
			jweixin.ready((e) => {

				// 配置完成后再读 DEFAULT_SHARE（此时 initJssdk 已写入），修复首屏默认分享标题/描述/图为空
				var ds = uni.getStorageSync('DEFAULT_SHARE') || {};
				// 逐项独立兜底,不能"有 diy 就整组用 diy" ——
				// 商品分享通常只配了 share_title,share_pic/share_desc 是空的;
				// 整组切过去的话卡片就没图没描述了,反而比默认分享还难看。
				var pick = function(a, b) {
					a = (a === undefined || a === null) ? '' : String(a).trim();
					return a !== '' ? a : ((b === undefined || b === null) ? '' : String(b).trim());
				};
				var title  = pick(diy && diy.share_title, ds.name);
				var desc   = pick(diy && diy.share_desc,  ds.detail);
				var imgUrl = pick(diy && diy.share_pic,   ds.logo);
				// 微信抓不到相对路径,没图的卡片会退化成一块灰底
				if (imgUrl && imgUrl.indexOf('//') !== 0 && !/^https?:\/\//i.test(imgUrl)) {
					imgUrl = window.location.origin + '/' + imgUrl.replace(/^\/+/, '');
				}
				// base64 用标准字母表(含 + / =),必须 encodeURIComponent 后再进 query:
				// '+' 在 query 里会被解成空格,PHP 侧 base64_decode 拿到的串就错位 → 链接打不开。
				// 纯 ASCII 的 URL 几乎不产出 '+',但页面 query 带中文(搜索词等)时必现。
				let base64Url = encodeURIComponent(Base64.encode(url));
				url = getApp().globalData.apiroot + '/wechat/redirect/h5&id=' + base64Url

				// 分享给朋友
				jweixin.updateAppMessageShareData({
					title: title, // 分享标题
					desc: desc, // 分享描述
					link: url, // 分享链接，该链接域名或路径必须与当前页面对应的公众号JS安全域名一致
					imgUrl: imgUrl, // 分享图标
					success: function(e) {
						// 用户点击了分享后执行的回调函数
					}
				});
				jweixin.updateTimelineShareData({
					title: title, // 分享标题
					desc: desc, // 分享描述
					link: url, // 分享链接，该链接域名或路径必须与当前页面对应的公众号JS安全域名一致
					imgUrl: imgUrl, // 分享图标
					success: function(e) {
						// 用户点击了分享后执行的回调函数
					}
				});

			})
		}, url)
	},
	//在需要自定义分享的页面中调用  
	shareUrl: function(data, url) {
		url = url ? url : window.location.href;
		if (!this.isWechat()) {
			return;
		}
		console.log('----------------------');
		console.log(url);
		//每次都需要重新初始化配置，才可以进行分享  
		this.initJssdk(function(signData) {
			jweixin.ready(function() {
				var shareData = {
					title: data && data.title ? data.title : signData.site_name,
					desc: data && data.desc ? data.desc : signData.site_description,
					link: url,
					imgUrl: data && data.img ? data.img : signData.site_logo,
					success: function(res) {
						//用户点击分享后的回调，这里可以进行统计，例如分享送金币之类的  
						// post('/api/member/share');
					},
					cancel: function(res) {}
				};
				//分享给朋友接口  
				jweixin.onMenuShareAppMessage(shareData);
				//分享到朋友圈接口  
				jweixin.onMenuShareTimeline(shareData);
			});
		}, url);
	},
	//在需要扫码的页面调用（仅微信 H5；小程序/APP 请用 uni.scanCode）
	//返回 Promise<string>，resolve 扫到的二维码内容，reject {msg}
	scanQRCode: function(scanType) {
		return new Promise((resolve, reject) => {
			if (!this.isWechat()) {
				reject({ msg: '请在微信中打开后扫码，或使用手动输入' });
				return;
			}
			// 防重:config 失败与 scanQRCode 失败可能先后触发,只认第一次
			var settled = false;
			var done = function(fn, arg) {
				if (settled) return;
				settled = true;
				fn(arg);
			};

			this.initJssdk(function() {
				jweixin.ready(function() {
					jweixin.scanQRCode({
						needResult: 1,          // 1=返回扫描结果，0=直接跳转（如打开网址）
						// 默认只扫二维码;调用方可传 ['qrCode','barCode'] 等(与 uni.scanCode 的 scanType 对齐)
						scanType: (Array.isArray(scanType) && scanType.length) ? scanType : ['qrCode'],
						success: function(res) {
							// 部分机型 resultStr 形如 "QR_CODE,真实内容"，取逗号后的真实内容
							var raw = (res && res.resultStr) || '';
							var idx = raw.indexOf(',');
							var code = idx >= 0 ? raw.slice(idx + 1) : raw;
							done(resolve, (code || '').trim());
						},
						fail: function(e) {
							done(reject, { msg: (e && e.errMsg) || '扫码失败，请重试或手动输入' });
						}
					});
				});
			}, null, function(msg) {
				// ⚠ 关键:initJssdk 失败必须 reject。
				// 原实现只在 initJssdk 的**成功回调里**注册 jweixin.error —— 而配置拉取失败时
				// 那个回调根本不会执行,于是 Promise 既不 resolve 也不 reject,核销页一直转圈,
				// 用户只看到"卡住",拿不到任何原因。
				done(reject, { msg: msg || '微信 JS-SDK 初始化失败,请刷新页面或手动输入核销码' });
			});
		});
	},
	//在需要定位页面调用
	location: function(callback) {
		if (!this.isWechat()) {
			console.log('不是微信客户端')
			return;
		}
		// console.info('定位')
		this.initJssdk(function(res) {
			jweixin.ready(function() {
				console.info('定位ready')
				jweixin.getLocation({
					type: 'gcj02', // 默认为gcj02的gps坐标，如果要返回直接给openLocation用的火星坐标，可传入'gcj02'
					success: function(res) {
						// console.log(res);
						callback(res)
					},
					fail: function(res) {
						console.log(res)
					},
					// complete:function(res){
					//     console.log(res)
					// }
				});
			});
		});
	}
}

/**
 * 构造 H5 分享目标地址(纯函数,无副作用,便于单测)。
 *
 * uniapp H5 是 **hash 路由**(manifest 里 router.base=/h5/ 且未设 mode),完整地址形如:
 *     https://host/h5/?mall_id=5&code=xxx#/others/goods/goods?id=1
 *                     └── search ──┘      └─── hash:路由 + 它自己的 query ───┘
 * 两段 query 必须分开处理,不能把整条 URL 当一段 query 切 —— 那正是旧实现丢 pid 的原因。
 *
 * 规则:
 *  · mall_id / pid 一律写进 **hash 的 query** —— 那是页面 onLoad 能拿到的 options,
 *    也是 SPA 内跳转后仍跟随的部分;search 段在前端路由跳转时不会变,靠它不可靠。
 *  · search 段里的微信授权残留 code/state 必须删掉:它是一次性的,分享给别人早已失效,
 *    对方带着它进来会走进授权异常分支。
 *  · search 段原有的 mall_id 保留 —— getUrlParam 先查 search 再查 hash,取值相同不冲突。
 */
export function buildShareUrl(opts) {
	opts = opts || {};
	const href = String(opts.href || '');
	const mallId = opts.mallId;
	const pid = opts.pid;

	// ── 1. 拆 origin+path 与 search ──
	const beforeHash = href.split('#')[0];
	const qIndex = beforeHash.indexOf('?');
	const base = qIndex === -1 ? beforeHash : beforeHash.slice(0, qIndex);
	const search = parseQuery(qIndex === -1 ? '' : beforeHash.slice(qIndex + 1));
	delete search.code;   // 微信授权残留,一次性,绝不能分享出去
	delete search.state;

	// ── 2. 拆 hash 路由的 path 与 query ──
	let routePath = String(opts.curRoute || '') || '/pages/index/index';
	let routeQueryStr = '';
	const rq = routePath.indexOf('?');
	if (rq !== -1) {
		routeQueryStr = routePath.slice(rq + 1);
		routePath = routePath.slice(0, rq);
	}
	if (!routePath || routePath === '/') {
		routePath = '/pages/index/index';
	}
	const routeQuery = parseQuery(routeQueryStr);

	// ── 3. 写入 mall_id / pid(同名键覆盖,不会重复) ──
	if (mallId !== undefined && mallId !== null && mallId !== '') {
		routeQuery.mall_id = mallId;
	}
	routeQuery.pid = (pid === undefined || pid === null) ? 0 : pid;

	// ── 4. 拼回 ──
	const searchOut = buildQuery(search);
	const routeOut = buildQuery(routeQuery);
	return base
		+ (searchOut ? '?' + searchOut : '')
		+ '#' + routePath
		+ (routeOut ? '?' + routeOut : '');
}

function parseQuery(str) {
	const out = {};
	if (!str) return out;
	str.split('&').forEach(function(kv) {
		if (!kv) return;
		const i = kv.indexOf('=');
		const k = i === -1 ? kv : kv.slice(0, i);
		if (k) out[k] = i === -1 ? '' : kv.slice(i + 1);
	});
	return out;
}

function buildQuery(obj) {
	return Object.keys(obj).map(function(k) {
		return k + '=' + obj[k];
	}).join('&');
}
