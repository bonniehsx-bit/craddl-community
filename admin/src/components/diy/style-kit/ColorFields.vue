<template>
  <div class="color-fields">
    <label v-for="f in fields" :key="f.key">
      <el-color-picker :value="value[f.key]" show-alpha size="mini" @input="set(f.key, $event)"></el-color-picker>
      {{ f.label }}
    </label>
  </div>
</template>

<script>
/** 风格组件里成组的颜色选择:fields = [{ key: 'bg_color', label: '背景' }, …] */
export default {
  name: 'ColorFields',
  props: {
    value: { type: Object, required: true },
    fields: { type: Array, required: true }
  },
  methods: {
    set(key, val) {
      this.$set(this.value, key, val || '')
      this.$set(this.value, 'preset', 'custom')
    }
  }
}
</script>

<style lang="scss" scoped>
.color-fields {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;

  label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #606266;
  }
}
</style>
