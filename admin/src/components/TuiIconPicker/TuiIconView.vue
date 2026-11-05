<template>
  <!-- 后台里「画出」一个手机端 tui-icon:列表预览、效果预览用。字体与手机端同一份(见 tui-icons.css) -->
  <i class="tui-icon-preview" :class="'tui-icon-' + name" :style="iconStyle" :title="name"></i>
</template>

<script>
import './tui-icons.css'

export default {
  name: 'TuiIconView',
  props: {
    name: { type: String, default: '' },
    size: { type: [Number, String], default: 18 },
    color: { type: String, default: '' }
  },
  computed: {
    iconStyle() {
      const size = typeof this.size === 'number' || /^\d+$/.test(this.size) ? this.size + 'px' : this.size
      return { fontSize: size, color: this.color || 'inherit', lineHeight: 1 }
    }
  }
}
</script>
