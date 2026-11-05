// 建议文件名: utils/payment.js

// #ifdef H5
import wechatSdk from '@/utils/wechatJsSdk.js';
// #endif

export default {
	/**
	 * 统一支付调度器
	 * @param {Object} payParams - 后端返回的支付参数 (对于企微支付，这里包含 url 字段)
	 * @param {String} pay_type - 后端返回的支付类型标识
	 * @returns {Promise<any>}
	 */
	pay(payParams, pay_type) {
		console.log('发起支付:', pay_type, payParams);

		switch (pay_type) {
			// ==========================================
			// 1. 微信小程序支付 / JSAPI 支付
			// ==========================================
			case "app\\payments\\MpwxPayment":
			case "app\\payments\\FastPayMpwxPayment":
				return new Promise((resolve, reject) => {
					// #ifdef MP-WEIXIN
					uni.requestPayment({
						provider: "wxpay",
						timeStamp: payParams.timestamp || payParams.timeStamp,
						nonceStr: payParams.nonceStr,
						package: payParams.package,
						signType: payParams.signType,
						paySign: payParams.paySign,
						success: (res) => {
							resolve(res);
						},
						fail: (err) => {
							// 用户取消支付或支付失败
							reject(err);
						},
					});
					// #endif

					// #ifndef MP-WEIXIN
					reject({ errMsg: "此支付方式仅支持微信小程序" });
					// #endif
				});

			// ==========================================
			// 2. 微信 H5 / 公众号 JSAPI 支付
			// ==========================================
			case "app\\payments\\FastPayWechatJsPayment":
			case "app\\payments\\WechatPayment":
				// #ifdef H5
				return new Promise((resolve, reject) => {
					wechatSdk.pay(payParams).then(res => {
						resolve(res);
					}).catch(e => {
						reject(e);
					});
				});
				// #endif

				// #ifndef H5
				return Promise.reject({ errMsg: "当前环境不支持微信JSAPI支付" });
			// #endif

			// ==========================================
			// 3. 企业微信客服引导支付 (新增部分)
			// ==========================================
			case "app\\payments\\WxWorkPayment":
			case "app\\payments\\FastPayWxWorkPayment": // 兼容旧名称
				return new Promise((resolve, reject) => {
					const targetUrl = payParams.url;

					if (!targetUrl) {
						reject({ errMsg: "未获取到跳转链接" });
						return;
					}

					// #ifdef H5
					// H5 环境直接跳转
					window.location.href = targetUrl;
					resolve({ errMsg: "request:ok", msg: "正在跳转企业微信..." });
					// #endif

					// #ifdef APP-PLUS
					// APP 环境调用系统能力打开链接 (会唤起企业微信或浏览器)
					plus.runtime.openURL(targetUrl, (err) => {
						reject({ errMsg: "无法打开支付链接", err });
					});
					// 由于 openURL 是异步且不一定有回调，这里直接 resolve
					resolve({ errMsg: "request:ok", msg: "正在唤起企业微信..." });
					// #endif

					// #ifdef MP-WEIXIN
					// 小程序环境无法直接跳转外部 H5，需要提示或者复制链接
					// 或者使用 <web-view> 组件，但这需要在页面层面处理
					uni.setClipboardData({
						data: targetUrl,
						success: () => {
							uni.showModal({
								title: '提示',
								content: '支付链接已复制，请在浏览器中打开完成支付',
								showCancel: false
							});
							reject({ errMsg: "不支持自动跳转，链接已复制" });
						}
					});
					// #endif
				});

			// ==========================================
			// 4. 支付宝 H5 / 链接支付
			// ==========================================
			case "app\\payments\\FastPayAliJsPayment":
				return new Promise((resolve, reject) => {
					// #ifdef APP-PLUS
					plus.runtime.openURL(payParams.qrData, (err) => {
						reject({ errMsg: "打开支付宝失败", err });
					});
					resolve({ msg: "正在跳转支付宝..." });
					// #endif

					// #ifdef H5
					window.location.href = payParams.qrData;
					resolve({ msg: "正在跳转支付宝..." });
					// #endif

					// #ifdef MP-WEIXIN
					// 小程序不支持支付宝外链
					reject({ errMsg: "微信小程序不支持支付宝支付" });
					// #endif
				});

			// ==========================================
			// 5. 微信 App 端原生支付 (trade_type=APP)
			//    后端 WechatAppPayment 用 EasyWeChat buildAppConfig 生成二次签名参数，
			//    字段名与 JSAPI 不同(全小写 noncestr/prepayid/partnerid/sign)，
			//    App 端经 uni.requestPayment(provider:wxpay, orderInfo) 唤起微信客户端。
			// ==========================================
			case "app\\payments\\WechatAppPayment":
				return new Promise((resolve, reject) => {
					// #ifdef APP-PLUS
					// 全部字段统一转字符串再交给 SDK。后端 buildAppConfig 的 timestamp 是
					// time() 返回的 int，JSON 里就是数字；安卓侧能自行兼容，iOS 侧有反馈说
					// 收到非字符串会不响应（两个回调都不触发，表现为"点了没反应"）。
					// 转字符串对安卓无副作用，所以两端统一这么传。
					// 用空值兜底而不是直接 String()：String(undefined) 会得到字面量
					// "undefined" 传给 SDK，比传空还难查。
					const s = (v) => (v === undefined || v === null) ? '' : String(v);
					uni.requestPayment({
						provider: "wxpay",
						orderInfo: {
							appid: s(payParams.appid),
							partnerid: s(payParams.partnerid),
							prepayid: s(payParams.prepayid),
							package: s(payParams.package),
							noncestr: s(payParams.noncestr),
							timestamp: s(payParams.timestamp),
							sign: s(payParams.sign)
						},
						success: (res) => {
							resolve(res);
						},
						fail: (err) => {
							reject(err);
						},
					});
					// #endif

					// #ifndef APP-PLUS
					reject({ errMsg: "此支付方式仅支持 App 端" });
					// #endif
				});

			default:
				return Promise.reject({
					errMsg: `未知的支付方式: ${pay_type}`
				});
		}
	}
};