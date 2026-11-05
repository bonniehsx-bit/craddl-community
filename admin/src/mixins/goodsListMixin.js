/**
 * 商品列表页通用 CRUD mixin
 *
 * 适用场景：admin 后台所有插件商品列表页 (group/voucher/yuda/lucky/integral)，
 * 它们都用 GoodsEditDrawer 编辑商品，配合一些标准的 CRUD 动作（新增/编辑/删除/上下架）。
 *
 * 提供：
 *  - 通用 data 字段：list/total/listLoading/drawerVisible/editGoodsId
 *  - handleAdd()              打开抽屉新增
 *  - handleEdit(row)          打开抽屉编辑
 *  - handleEditSuccess()      抽屉保存成功后回调（默认刷新列表）
 *  - handleDelete(row)        二次确认 + 删除 + 刷新
 *  - handleStatusChange(row)  上下架切换 + 失败回滚
 *
 * 子组件需要提供：
 *  - loadList()  自己负责实际的列表加载（每个商品类型查询参数不同，无法通用）
 *  - 可选 override goodsApiUrls 计算属性指定 delete/status URL
 *  - 可选 override deleteConfirmText 计算属性指定二次确认文案
 */
export default {
  data() {
    return {
      list: [],
      total: 0,
      listLoading: false,
      drawerVisible: false,
      editGoodsId: ''
    }
  },
  computed: {
    /**
     * 各 CRUD 动作的 API 地址。子组件可覆盖任意一项。
     * 默认走 admin 核心 mall/goods/* 接口。
     */
    goodsApiUrls() {
      return {
        delete: '/mall/goods/delete',
        statusChange: '/mall/goods/status-change'
      }
    },
    /**
     * 删除二次确认文案。子组件可覆盖。
     */
    deleteConfirmText() {
      return '确定删除该商品吗？'
    }
  },
  methods: {
    /**
     * 打开抽屉新增商品
     */
    handleAdd() {
      this.editGoodsId = ''
      this.drawerVisible = true
    },

    /**
     * 打开抽屉编辑商品
     */
    handleEdit(row) {
      this.editGoodsId = row.id
      this.drawerVisible = true
    },

    /**
     * 抽屉保存成功后的回调（关闭抽屉 + 刷新列表）
     */
    handleEditSuccess() {
      this.drawerVisible = false
      if (typeof this.loadList === 'function') {
        this.loadList()
      } else if (typeof this.getList === 'function') {
        this.getList()
      } else if (typeof this.searchList === 'function') {
        this.searchList()
      }
    },

    /**
     * 删除商品（带二次确认）
     */
    handleDelete(row) {
      this.$confirm(this.deleteConfirmText, '提示', {
        type: 'warning'
      }).then(() => {
        this.$request({
          url: this.goodsApiUrls.delete,
          method: 'post',
          data: { id: row.id }
        }).then((res) => {
          if (res.code === 0) {
            this.$message.success(res.msg || '删除成功')
            this.handleEditSuccess() // 复用刷新逻辑
          } else {
            this.$message.error(res.msg)
          }
        })
      }).catch(() => {})
    },

    /**
     * 上下架切换。失败时自动把 row.status 滚回原值。
     */
    handleStatusChange(row) {
      this.$request({
        url: this.goodsApiUrls.statusChange,
        method: 'post',
        data: { id: row.id, status: row.status }
      }).then((res) => {
        if (res.code === 0) {
          this.$message.success(res.msg || '操作成功')
        } else {
          this.$message.error(res.msg)
          row.status = row.status === 1 ? 0 : 1 // 回滚
        }
      }).catch(() => {
        row.status = row.status === 1 ? 0 : 1
      })
    }
  }
}
