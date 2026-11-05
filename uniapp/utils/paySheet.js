/**
 * 支付统一入口:在当前页弹出支付弹窗(components/jx-pay-sheet)付款,不再跳收银台页。
 *
 * 用法(页面模板里要放 <jx-pay-sheet ref="paySheet"></jx-pay-sheet>):
 *   import paySheet from '@/utils/paySheet.js';
 *   paySheet(this, { payOrderId, successUrl: '/plugins3/lucky/record', replace: true, keepOrder: true });
 *
 * @param {Object} vm   当前页面实例(this)
 * @param {Object} opts
 *   payOrderId / orderNo  二选一
 *   successUrl  付完去哪(未编码的路径),也可以是函数 (result) => url;
 *               不给且没给 onPaid 时,跳订单详情(拿不到订单 id 就去订单列表)
 *   onPaid      付完不跳页、由页面自己处理(比如原地刷新),给了它就不看 successUrl
 *   replace     true:付完用 redirectTo 换掉当前页 —— 提交页这类「付完不该再回来」的页面用;
 *               false(默认):navigateTo 新开,当前页保留(详情页这类)
 *   keepOrder   true:订单是本页刚建的(提交页)。没付就关掉时提示「订单已保留」,
 *               给「继续付款 / 查看订单」,别把用户留在提交页上再点一次又下一单
 *   pendingUrl  「查看订单」去哪,默认订单详情 / successUrl
 *   onClose     没付款关掉(且 keepOrder=false)时的回调,比如刷新页面状态
 * @returns {Promise<{status, orderId, payOrderId, orderNo}>}
 */
export default function paySheet(vm, opts = {}) {
	const sheet = vm && vm.$refs && vm.$refs.paySheet;
	if (!sheet) {
		// 页面忘了放组件:退回收银台页,至少能付款。开发时看到这条日志就去补组件
		console.warn('[paySheet] 当前页没有 <jx-pay-sheet ref="paySheet">,退回收银台页');
		const q = opts.payOrderId ? 'pay_order_id=' + opts.payOrderId : 'order_no=' + opts.orderNo;
		const back = typeof opts.successUrl === 'string' && opts.successUrl ? '&redirect_url=' + encodeURIComponent(opts.successUrl) : '';
		uni.navigateTo({ url: '/others/pay/pay?' + q + back });
		return Promise.resolve({ status: 'closed' });
	}

	return sheet.open({ payOrderId: opts.payOrderId, orderNo: opts.orderNo }).then(r => {
		if (r.status === 'paid') {
			if (typeof opts.onPaid === 'function') {
				opts.onPaid(r);
			} else {
				go(resolveUrl(opts.successUrl, r) || orderUrl(r), opts.replace);
			}
			return r;
		}
		if (opts.keepOrder) {
			const pending = resolveUrl(opts.pendingUrl, r) || orderUrl(r, true);
			if (r.status === 'expired') {
				go(pending, true);
				return r;
			}
			uni.showModal({
				title: '订单已保留',
				content: '可以稍后在订单列表里继续付款，超过付款时间订单会自动取消',
				confirmText: '继续付款',
				cancelText: '查看订单',
				success: m => {
					if (m.confirm) paySheet(vm, opts);
					else go(pending, true);
				}
			});
			return r;
		}
		if (typeof opts.onClose === 'function') opts.onClose(r);
		return r;
	});
}

function resolveUrl(u, r) {
	return typeof u === 'function' ? u(r) : (u || '');
}

// 默认去处:订单详情;老数据拿不到订单 id 时去订单列表(付完看「待发货」,没付看「待付款」)
function orderUrl(r, pending) {
	if (r && r.orderId) return '/others/order-detail/order-detail?order_id=' + r.orderId;
	return '/others/order/order?status=' + (pending ? 1 : 2);
}

function go(url, replace) {
	if (!url) return;
	const fallback = () => uni.reLaunch({ url });
	// 小程序 redirectTo / navigateTo 遇到 tabBar 页或页面栈满会失败,用 reLaunch 兜底
	if (replace) uni.redirectTo({ url, fail: fallback });
	else uni.navigateTo({ url, fail: () => uni.redirectTo({ url, fail: fallback }) });
}
