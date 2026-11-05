/**
 * 提现规则文案(提现页 withdraw.vue 与余额 / 佣金 / 积分页的「提现规则」卡共用)。
 *
 * 微信小程序审核要求:涉及提现的页面要写清「可提现额度、每日提现次数、提现时间、到账时间」等。
 * 所以这四项 + 手续费 + 审核说明**始终输出**,数字全部取后台配置(渠道 min/max/倍数/每日上限/手续费/到账说明,
 * 全局每日次数);后台给渠道写的自定义规则追加在后面,不再整段顶掉这几条(否则审核要的项会被冲没)。
 *
 * @param {Object} opt
 *   noun      提现叫法($noun('withdraw'))
 *   channels  当前资金来源下可用的提现方式(prepare 接口 assets[].channels)
 *   dayLimit  全局每日提现次数(0=不限)
 *   available 当前可提现余额(没有就不写)
 *   money     金额格式化:v => '¥10.00' / '100积分'
 * @return {Array<{k:String, v:String}>} 结构化规则:k 标题(可空),v 内容
 */
export function buildWithdrawRules(opt) {
	const noun = opt.noun || '提现';
	const chs = (opt.channels || []).filter(Boolean);
	const money = opt.money || (v => String(v));
	const dayLimit = Number(opt.dayLimit) || 0;
	const multi = chs.length > 1;
	const name = c => (multi ? c.name + '：' : '');
	const out = [];

	// 1. 可提现额度
	const quota = [];
	if (opt.available !== null && opt.available !== undefined && opt.available !== '') {
		quota.push('当前可' + noun + ' ' + money(opt.available));
	}
	chs.forEach(c => {
		const parts = [];
		const min = Number(c.min_amount) || 0;
		const max = Number(c.max_amount) || 0;
		if (min > 0 && max > 0) parts.push('单笔 ' + money(min) + ' ~ ' + money(max));
		else if (min > 0) parts.push('单笔最低 ' + money(min) + '，不设上限');
		else if (max > 0) parts.push('单笔最高 ' + money(max));
		else parts.push('单笔不限金额');
		if (Number(c.multiple) > 0) parts.push('须为 ' + money(c.multiple) + ' 的整数倍');
		if (Number(c.day_limit_amount) > 0) parts.push('每日最多 ' + money(c.day_limit_amount));
		quota.push(name(c) + parts.join('，'));
	});
	if (!chs.length) quota.push('以所选' + noun + '方式的额度为准');
	out.push({ k: '可' + noun + '额度', v: quota.join('；') + '。' });

	// 2. 每日提现次数:全局(各方式合计)与渠道上限同时生效
	const times = [];
	if (dayLimit > 0) times.push('每天最多 ' + dayLimit + ' 次' + (multi ? '（各方式合计）' : ''));
	chs.forEach(c => {
		const n = Number(c.day_limit_count) || 0;
		if (n > 0 && (dayLimit <= 0 || n < dayLimit)) times.push((multi ? c.name : '该方式') + '每天最多 ' + n + ' 次');
	});
	out.push({ k: '每日' + noun + '次数', v: (times.length ? times.join('；') : '不限次数') + '。' });

	// 3. 提现时间:系统不限时段
	out.push({ k: noun + '时间', v: '全天 24 小时均可提交申请。' });

	// 4. 到账时间:渠道「到账说明」,没配写默认
	const arrival = chs.map(c => name(c) + (c.arrival_desc || '审核通过后 1~3 个工作日内到账'));
	out.push({ k: '到账时间', v: (arrival.length ? arrival.join('；') : '审核通过后 1~3 个工作日内到账') + '。' });

	// 5. 手续费
	const fees = chs.map(c => {
		const fee = Number(c.fee_rate) || 0;
		const tax = Number(c.tax_rate) || 0;
		const parts = [];
		if (fee > 0) parts.push('手续费 ' + +(fee * 100).toFixed(2) + '%');
		if (tax > 0) parts.push('代扣税费 ' + +(tax * 100).toFixed(2) + '%');
		return name(c) + (parts.length ? parts.join('、') + '，实际到账为扣费后的金额' : '不收手续费，全额到账');
	});
	if (fees.length) out.push({ k: '手续费', v: fees.join('；') + '。' });

	// 6. 审核与退回
	const audit = chs.length && chs.every(c => !Number(c.need_audit));
	out.push({
		k: '审核说明',
		v: audit
			? '提交后自动打款；打款失败的金额会原路退回您的账户。'
			: '提交后需平台审核，审核通过后按所选方式打款；未通过的，冻结金额原路退回您的账户。'
	});

	// 通用条款
	out.push({ k: '', v: '发起' + noun + '后，对应金额从可' + noun + '余额中实时冻结。' });
	out.push({ k: '', v: '请确保收款账户真实、准确且为本人账户；因账户信息有误导致' + noun + '失败的，款项退回后可重新发起。' });
	out.push({ k: '', v: noun + '进度可在「' + noun + '记录」中查询，如有疑问请联系平台客服。' });

	// 后台给渠道写的自定义规则:追加,不覆盖
	const seen = {};
	chs.forEach(c => (c.rules || []).forEach(r => {
		const t = String(r || '').trim();
		if (t && !seen[t]) {
			seen[t] = 1;
			out.push({ k: '', v: t });
		}
	}));
	return out;
}

export default { buildWithdrawRules };
