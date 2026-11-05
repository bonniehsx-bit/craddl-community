/**
 * 后台词库（名词配置）—— 与 uniapp/utils/nouns.js 同构的单一解析实现
 *
 * 三级兜底：商户配置值（服务端下发） → 本表默认词 → key 本身
 *
 * ⚠️ 权威值来自服务端 `NounHelper::resolvedMap()`（已把「配置值 ?: 默认词」算好，含插件词）。
 *    下面的 NOUN_DEFAULTS 只是**首屏兜底子集**（词表还没拉回来时避免露出英文 key），
 *    不是权威源，也不承担"与后端字典同步"的义务——只放后台真正会显示的核心词。
 *
 * 取词请用全局 `this.$noun('balance')`（见 src/mixins/noun.js），不要直接 import 本文件。
 */

export const NOUN_DEFAULTS = {
  balance: '余额',
  integral: '积分',
  commission: '佣金',
  withdraw: '提现',
  coupon: '优惠券',
  level: '等级'
}

/**
 * 解析品牌词
 * @param {String} key   词条 key（核心如 'balance'；插件词带前缀如 'withdraw.title_wallet'）
 * @param {Object} nouns 服务端下发的完整词表 key => 最终值
 * @returns {String}
 */
export function parseNoun(key, nouns) {
  if (!key) return ''
  const map = nouns || {}
  return map[key] || NOUN_DEFAULTS[key] || key
}

export default { NOUN_DEFAULTS, parseNoun }
