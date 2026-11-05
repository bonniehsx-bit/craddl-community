<template>
  <div style="text-align: right;">
    <!-- 增加 v-if 判断内部属性是否存在，防止报错 -->
    <!-- 强制转换类型为 Number，防止后端传字符串 -->
    <el-pagination
      style="margin-top: 10px"
      v-if="pagination && pagination.total_count >= 0"
      background
      :layout="finalLayout"
      :page-sizes="pageSizes"
      :page-size="currentSize"
      :current-page="Number(pagination.current_page) || 1"
      :total="Number(pagination.total_count) || 0"
      @current-change="pageChange"
      @size-change="sizeChange"
    >
    </el-pagination>
  </div>
</template>

<script>
export default {
  name: 'Pagination',
  props: {
    pagination: {
      type: Object,
      default: () => ({
        pageSize: 10,
        current_page: 1,
        total_count: 0
      })
    },
    layout: {
      type: String,
      default: 'total, prev, pager, next, jumper'
    },
    /**
     * 每页条数档位。**传了才会出现「每页 N 条」选择器**。
     *
     * 做成 opt-in 而不是改 layout 默认值:现有调用方绝大多数没接 @sizeChange,
     * 默认打开等于给它们凭空多一个点了没反应的控件。
     * 传了本 prop 的页面必须接 @sizeChange(val),自己更新 limit、回到第 1 页并重新取数。
     */
    pageSizes: {
      type: Array,
      default: () => []
    }
  },
  computed: {
    // 传了 pageSizes 才把 sizes 插进 layout;插在 prev 前面,与 order/index.vue 等页面的观感一致
    finalLayout() {
      const parts = String(this.layout).split(',').map(x => x.trim()).filter(Boolean)
      if (!this.pageSizes.length || parts.includes('sizes')) {
        return parts.join(', ')
      }
      const at = parts.indexOf('prev')
      parts.splice(at === -1 ? parts.length : at, 0, 'sizes')
      return parts.join(', ')
    },
    // 后端的分页对象两种命名都有(BasePagination 同时给 pageSize 和 page_size,
    // 手拼 pagination 数组的接口往往只给 page_size)。少认一种,选择器就会显示成默认的 10。
    currentSize() {
      const p = this.pagination || {}
      return Number(p.pageSize) || Number(p.page_size) || Number(this.pageSizes[0]) || 10
    }
  },
  methods: {
    pageChange(val) {
      // 1. 支持 v-model 的写法 (如果父组件用 v-model="page")
      this.$emit('input', val);

      // 2. 统一发送 change 事件，且只发送数字，方便父组件直接使用
      // 建议不要包成对象 {page: val}，直接发 val 更通用
      this.$emit('pageChange', val);

      // 3. 甚至可以发射 update:currentPage 以支持 .sync 修饰符 (Vue 2.3+)
      // this.$emit('update:currentPage', val);
    },
    sizeChange(val) {
      // 1. 支持 v-model 的写法 (如果父组件用 v-model="page")
      this.$emit('input', 1);

      // 2. 统一发送 change 事件，且只发送数字，方便父组件直接使用
      // 建议不要包成对象 {page: val}，直接发 val 更通用
      this.$emit('sizeChange', val);
    }
  }
}
</script>
