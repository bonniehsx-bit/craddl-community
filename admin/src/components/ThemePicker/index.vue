<template>
  <el-color-picker :value="theme" :predefine="colors" class="theme-picker"
                   popper-class="theme-picker-dropdown" @change="changeColor" />
</template>
<script>
import { normalizeColor } from '@/utils/admin-theme'
export default {
  data() {
    return { colors: ['#191B20', '#F7931E', '#409EFF', '#1890FF', '#11A983', '#13C2C2', '#6959CD', '#F5222D'] }
  },
  computed: {
    theme() { return this.$store.state.settings.theme }
  },
  methods: {
    changeColor(value) {
      const color = normalizeColor(value)
      if (color) this.$emit('change', color)
    }
  }
}
</script>
<style>
.theme-picker-dropdown { z-index: 99999 !important; }
.theme-picker .el-color-picker__trigger { height: 28px !important; width: 28px !important; padding: 2px; }
.theme-picker-dropdown .el-color-dropdown__link-btn { display: none; }
</style>
