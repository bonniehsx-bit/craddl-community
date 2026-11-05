<template>
  <!-- 没选时值是 0:转成空串,显示占位提示而不是「0」 -->
  <el-select :value="value || ''" filterable clearable placeholder="选择分类" size="small" style="width: 100%"
             :loading="loading" @change="onChange" @visible-change="load">
    <el-option v-for="c in options" :key="c.id" :label="c.label" :value="c.id">
      <span :style="{ paddingLeft: c.level * 14 + 'px' }">{{ c.name }}</span>
    </el-option>
  </el-select>
</template>

<script>
// 分类树整个页面只拉一次,多个下拉共用
let cache = null
let pending = null

/** 一级 / 二级分类都能选的下拉(现有 CatPicker 只能勾二级),选中后同时回传分类名 */
export default {
  name: 'CatSelect',
  props: { value: [Number, String] },
  data() {
    return { options: cache || [], loading: false }
  },
  created() {
    if (this.value && !cache) this.load(true)
  },
  methods: {
    load(open) {
      if (!open || cache) {
        if (cache) this.options = cache
        return
      }
      this.loading = true
      // 分类管理页同一个接口(两级树,子分类在 children);原 CatPicker 调的 /mall/goods/cat/all-list 后端不存在
      pending = pending || this.$request({ url: '/mall/goods/cat-tree' }).then(res => {
        const out = []
        const walk = (list, level) => (list || []).forEach(c => {
          out.push({ id: c.id, name: c.name, level, label: (level ? '— ' : '') + c.name })
          walk(c.childs || c.children, level + 1)
        })
        if (res.code === 0) walk(res.data.list, 0)
        cache = out
        return out
      }).finally(() => { pending = null })
      pending.then(list => { this.options = list }).finally(() => { this.loading = false })
    },
    onChange(id) {
      const c = this.options.find(x => x.id === id)
      this.$emit('input', id || 0)
      this.$emit('change', c ? { id: c.id, name: c.name } : null)
    }
  }
}
</script>
