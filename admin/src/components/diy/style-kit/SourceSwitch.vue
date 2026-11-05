<template>
  <div class="source-switch" :class="'size-' + size">
    <span
      v-for="o in options"
      :key="o.value"
      class="opt"
      :class="{ on: value === o.value || (!value && o.value === options[0].value) }"
      @click="pick(o.value)"
    >{{ o.label }}</span>
  </div>
</template>

<script>
/**
 * 「手动挑选 / 按分类自动」二选一。
 * 不用 el-radio-group:它放在 draggable 列表的标签卡片里时,切换后旧选项底下的原生 radio
 * 仍是 checked,Element 按 :checked 上色,结果两个按钮同时高亮。这里样式只看数据,不会错位。
 */
export default {
  name: 'SourceSwitch',
  props: {
    value: String,
    size: { type: String, default: 'small' },
    options: {
      type: Array,
      default: () => [{ value: 'manual', label: '手动挑选' }, { value: 'cat', label: '按分类自动' }]
    }
  },
  methods: {
    pick(v) {
      if (v === this.value) return
      this.$emit('input', v)
      this.$emit('change', v)
    }
  }
}
</script>

<style lang="scss" scoped>
.source-switch {
  display: inline-flex;
  border: 1px solid #dcdfe6;
  border-radius: 4px;
  overflow: hidden;
}

.opt {
  padding: 0 15px;
  line-height: 30px;
  font-size: 12px;
  color: #606266;
  background: #fff;
  cursor: pointer;
  user-select: none;

  & + .opt {
    border-left: 1px solid #dcdfe6;
  }

  &:hover {
    color: var(--admin-accent-text);
  }

  &.on {
    color: var(--admin-on-primary);
    background: var(--admin-primary);
  }
}

.size-mini .opt {
  padding: 0 12px;
  line-height: 26px;
}
</style>
