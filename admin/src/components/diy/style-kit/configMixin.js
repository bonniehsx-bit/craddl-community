/**
 * 风格组件配置面板的公共逻辑。
 *
 * 组件刚拖进画布时 data 是 null,这里把 defaults() 的默认配置回填上去;
 * 老页面里缺新字段的(组件后来加了配置项),也按默认值补齐,预览和手机端都不用再判空。
 *
 *   mixins: [configMixin],
 *   methods: { defaults() { return { title: '…' } } }
 *   模板里直接 v-model="form.title"
 */
export default {
  props: { value: Object },
  computed: {
    form() {
      return this.value || this.defaults()
    }
  },
  watch: {
    value: {
      immediate: true,
      handler(val) {
        const def = this.defaults()
        if (!val) {
          this.$emit('input', def)
          return
        }
        const missing = Object.keys(def).filter(k => !(k in val))
        if (missing.length) {
          const next = Object.assign({}, val)
          missing.forEach(k => { next[k] = def[k] })
          this.$emit('input', next)
        }
      }
    }
  },
  methods: {
    defaults() {
      return {}
    },
    // 预设条 / 需要整体替换时用
    replace(next) {
      this.$emit('input', next)
    }
  }
}
