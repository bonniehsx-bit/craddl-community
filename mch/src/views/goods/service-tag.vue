<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="page-header">
        <span>服务标签</span>
        <div>
          <span class="header-tip">这里是本店自建的标签；平台还有 {{ platformCount }} 个通用标签，上传商品时一样能勾</span>
          <el-button type="primary" size="small" icon="el-icon-plus" @click="openEdit(null)">新增标签</el-button>
        </div>
      </div>

      <el-alert type="warning" :closable="false" class="shrink-0 tag-alert" show-icon>
        标签只是展示文字，不参与任何计算 —— 给商品勾了「全国包邮」，结算该收的运费照样收，运费以商品的运费设置为准。详情页最多显示 {{ showLimit }} 个标签。
      </el-alert>

      <div class="table-wrapper flex-1">
        <div class="absolute-table-container">
          <el-table height="100%" size="small" border :data="list" v-loading="loading" @row-dblclick="openEdit">
            <el-table-column prop="name" label="标签名称" min-width="180" show-overflow-tooltip />
            <el-table-column prop="sort" label="排序" width="90" align="center" />
            <el-table-column label="启用" width="90" align="center">
              <template slot-scope="{ row }">
                <el-switch :value="row.status" :active-value="1" :inactive-value="0"
                           @change="toggleStatus(row, $event)" />
              </template>
            </el-table-column>
            <el-table-column label="操作" width="130" align="center" fixed="right">
              <template slot-scope="{ row }">
                <el-button type="text" @click="openEdit(row)">编辑</el-button>
                <el-button type="text" class="text-danger" @click="remove(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </div>
    </el-card>

    <el-drawer :title="form.id ? '编辑标签' : '新增标签'" :visible.sync="drawerVisible"
               direction="rtl" size="420px" :wrapper-closable="true">
      <el-form ref="form" :model="form" :rules="rules" label-width="90px" size="small" class="drawer-form">
        <el-form-item label="标签名称" prop="name">
          <el-input v-model="form.name" maxlength="12" show-word-limit placeholder="如：正品保障" />
          <div class="field-tip">详情页一行放不下就会折行，建议 4-6 个字</div>
        </el-form-item>
        <el-form-item label="排序" prop="sort">
          <el-input-number v-model="form.sort" :min="0" :max="9999" controls-position="right" />
          <div class="field-tip">小的排前面</div>
        </el-form-item>
        <el-form-item label="启用">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
          <div class="field-tip">停用后详情页不再显示，已经勾了它的商品也不显示</div>
        </el-form-item>
      </el-form>
      <div class="drawer-footer">
        <el-button @click="drawerVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submit">保存</el-button>
      </div>
    </el-drawer>
  </div>
</template>

<script>
export default {
  name: 'GoodsServiceTag',
  data() {
    return {
      loading: false,
      submitting: false,
      drawerVisible: false,
      showLimit: 6,
      platformCount: 0,
      list: [],
      form: this.emptyForm(),
      rules: {
        name: [{ required: true, message: '请填写标签名称', trigger: 'blur' }]
      }
    }
  },
  created() {
    this.getList()
  },
  methods: {
    emptyForm() {
      return { id: 0, name: '', sort: 0, status: 1 }
    },
    async getList() {
      this.loading = true
      try {
        const res = await this.$request({ url: '/plugin/mch/admin/goods/service-tag-list', method: 'get', data: {} })
        if (res.code === 0) {
          this.list = res.data.list || []
          this.showLimit = res.data.show_limit || 6
          this.platformCount = res.data.platform_count || 0
        } else {
          this.$message.error(res.msg)
        }
      } finally {
        this.loading = false
      }
    },
    openEdit(row) {
      this.form = row ? { ...row } : this.emptyForm()
      this.drawerVisible = true
      this.$nextTick(() => this.$refs.form && this.$refs.form.clearValidate())
    },
    submit() {
      this.$refs.form.validate(async(valid) => {
        if (!valid) return
        this.submitting = true
        try {
          const res = await this.$request({ url: '/plugin/mch/admin/goods/service-tag-edit', method: 'post', data: { ...this.form } })
          if (res.code === 0) {
            this.$message.success('保存成功')
            this.drawerVisible = false
            this.getList()
          } else {
            this.$message.error(res.msg)
          }
        } finally {
          this.submitting = false
        }
      })
    },
    // 行内开关：先发请求，成功了再改本地值，失败保持原状（不用 v-model 免得回滚不掉）
    async toggleStatus(row, status) {
      const res = await this.$request({
        url: '/plugin/mch/admin/goods/service-tag-edit',
        method: 'post',
        data: { id: row.id, name: row.name, sort: row.sort, status }
      })
      if (res.code === 0) {
        row.status = status
      } else {
        this.$message.error(res.msg)
      }
    },
    remove(row) {
      this.$confirm(`确认删除标签「${row.name}」吗？已经勾了它的商品，详情页会少这一个标签。`, '提示', { type: 'warning' })
        .then(async() => {
          const res = await this.$request({ url: '/plugin/mch/admin/goods/service-tag-delete', method: 'post', data: { id: row.id } })
          if (res.code === 0) {
            this.$message.success('删除成功')
            this.getList()
          } else {
            this.$message.error(res.msg)
          }
        }).catch(() => {})
    }
  }
}
</script>

<style lang="scss" scoped>
.app-container { height: calc(100vh - 85px); display: flex; flex-direction: column;
  overflow: hidden; padding: 15px; box-sizing: border-box; background: #f0f2f5; }
.box-card { flex: 1; display: flex; flex-direction: column; border: none; }
.box-card ::v-deep .el-card__body { flex: 1; display: flex; flex-direction: column;
  overflow: hidden; min-height: 0; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.header-tip { color: #909399; font-size: 12px; margin-right: 16px; }
.tag-alert { margin-bottom: 10px; }
.shrink-0 { flex-shrink: 0; }
.flex-1 { flex: 1; }
.table-wrapper { flex: 1; position: relative; min-height: 0; }
.absolute-table-container { position: absolute; top: 0; left: 0; right: 0; bottom: 0; }
.text-danger { color: #F56C6C; }
.drawer-form { padding: 10px 20px; }
.field-tip { color: #909399; font-size: 12px; line-height: 1.5; margin-top: 4px; }
.drawer-footer { padding: 12px 20px; border-top: 1px solid #ebeef5; text-align: right;
  position: absolute; bottom: 0; left: 0; right: 0; background: #fff; }
</style>
