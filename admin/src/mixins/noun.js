import { parseNoun } from '@/utils/nouns'

/**
 * 全局词库 mixin —— 给所有组件注入 `$noun(key)`，与 uniapp 端用法一致。
 *
 * 用法（无需 import）：
 *   模板  {{ $noun('balance') }}          → 余额 / 储值（商户改了就跟着变）
 *   模板  {{ $noun('integral') }}明细      → 原子词拼接
 *   JS    this.$noun('commission')
 *
 * 响应式：读 store.state.noun.nouns，词表拉回来后（SET_NOUNS）自动重渲染。
 * 数据源见 src/store/modules/noun.js；权威值由服务端 resolvedMap 算好。
 */
export default {
  methods: {
    $noun(key) {
      return parseNoun(key, this.$store.state.noun.nouns)
    }
  }
}
