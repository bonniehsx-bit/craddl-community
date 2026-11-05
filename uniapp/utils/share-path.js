/**
 * 转发路径带上转发人 pid。
 *
 * 全局 mixin 的默认转发会自动拼 pid=当前用户 id(打开的人进来时由全局 onLoad 记成推荐人),
 * 但页面自己写了 onShareAppMessage 就把默认那份整个覆盖了 —— 自己拼 path 的页面
 * (育儿、好孩子检测等)要是忘了带 pid,好友点进来记不到推荐人。自定义转发一律套一层 withPid()。
 *
 *   path: withPid(`/plugins1/hardware/records/knowledge-detail?id=${this.id}`)
 *   query: withPid('year_month=2026-09', true)   // 朋友圈转发用 query,不带路径
 */
export function withPid(pathOrQuery, queryOnly = false) {
	const s = pathOrQuery || '';
	const user = uni.getStorageSync('USER');
	const uid = user && user.id;
	if (!uid || /(^|[?&])pid=/.test(s)) return s;
	if (queryOnly) return s ? s + '&pid=' + uid : 'pid=' + uid;
	return s + (s.indexOf('?') > -1 ? '&' : '?') + 'pid=' + uid;
}
