/**
 * 品牌词库 —— 标准 key 与默认词
 *
 * 用途：商户可在后台「词库管理」把这些词改成自有品牌叫法
 *      （如 积分 → 金豆、余额 → 储值），前端通过 $noun(key) 读取。
 *
 * 三级兜底：商户配置 nouns[key]  →  本表默认词 NOUN_DEFAULTS[key]  →  key 本身
 *
 * 接入方式：
 *   · 模板：{{ $noun('balance') }}        （全局 mixin 方法，自动响应商城配置变化）
 *   · JS  ：this.$noun('balance') 或 this.$util.parseNoun('balance')
 *   · 复合词原子拼接：{{ $noun('integral') }}明细 / 可用{{ $noun('balance') }}
 *
 * ⚠️ 新增词必须：① 在此登记 key + 默认词  ② 同步到 docs/开发规范.md 词库字典
 *    key 一律小写英文，前后端 / 各插件统一引用，禁止自创散落的 key。
 */
import store from '@/store/index.js';

export const NOUN_DEFAULTS = {
	balance:    '余额',
	integral:   '积分',
	commission: '佣金',
	withdraw:   '提现',
	coupon:     '优惠券',
	level:      '等级',
	// 后台充值/扣除流水描述(与后端 NounHelper::DICTIONARY 同步;后端写明细用,前端一般不取)
	charge_add:    '后台充值',
	charge_sub:    '后台扣除',
	charge_source: '后台操作',
	// 余额到账流水文案模板(后端 BalanceFund 渲染用,前端不取;占位符见后端 NounHelper::render)
	balance_payout_title:   '{type}{withdraw}',
	balance_payout_content: '{type}{withdraw}至{balance}，{withdraw}单号：{order_no}',
	// 插件词兜底(正常经后端 resolvedMap 注入 setting.nouns;此处仅防配置未加载时露出裸 key)
	'plaza.venue': '商场', // 场所统称,后台可配 商场/社区/园区/市场(见 plaza Plugin::getNouns)
	'dealer.title': '经销商', // 经销体系统称,后台可配 经销商/代理商/渠道商(见 dealer Plugin::getNouns)
	'dealer.center': '代理中心', // C 端代理中心页标题,后台可配 渠道服务中心/精灵中心 等
	'plaza.tenant': '租户', // 承租方主体统称,后台可配 商户/承租方/住户/车主(见 plaza Plugin::getNouns)
};

/**
 * 解析品牌词
 * @param {String} key 标准 key（见 NOUN_DEFAULTS）
 * @return {String} 商户配置词 / 默认词 / key
 */
export function parseNoun(key) {
	const setting = store.state.mall && store.state.mall.setting;
	const nouns = (setting && setting.nouns) || {};
	return nouns[key] || NOUN_DEFAULTS[key] || key;
}

export default { NOUN_DEFAULTS, parseNoun };
