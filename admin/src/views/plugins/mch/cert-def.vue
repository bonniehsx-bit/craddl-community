<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="page-header">
        <span>证照配置</span>
        <div>
          <span class="header-tip">商家入驻时按此清单上传证照,可设是否必填 / 是否采集有效期</span>
          <el-button type="primary" size="small" icon="el-icon-plus" @click="openEdit(null)">新增证照</el-button>
        </div>
      </div>

      <div class="table-wrapper flex-1">
        <div class="absolute-table-container">
          <el-table height="100%" size="small" border :data="list" v-loading="loading"
                    @row-dblclick="openEdit">
            <el-table-column prop="name" label="证照名称" min-width="160" show-overflow-tooltip />
            <el-table-column label="适用主体" min-width="180">
              <template slot-scope="{ row }">
                <el-tag v-for="t in row.types" :key="t" size="mini" effect="plain" class="type-tag">
                  {{ typeMap[t] || t }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="必填" width="80" align="center">
              <template slot-scope="{ row }">
                <el-tag :type="row.is_required ? 'danger' : 'info'" size="mini" effect="plain">
                  {{ row.is_required ? '必填' : '选填' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="有效期" width="90" align="center">
              <template slot-scope="{ row }">
                <el-tag :type="row.need_validity ? 'warning' : 'info'" size="mini" effect="plain">
                  {{ row.need_validity ? '采集' : '不采集' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="来源" width="90" align="center">
              <template slot-scope="{ row }">
                <el-tag :type="row.is_system ? 'success' : ''" size="mini">
                  {{ row.is_system ? '预置' : '自定义' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="排序" prop="sort" width="70" align="center" />
            <el-table-column label="状态" width="90" align="center">
              <template slot-scope="{ row }">
                <el-switch :value="row.is_enabled" :active-value="1" :inactive-value="0"
                           @change="toggleEnabled(row, $event)" />
              </template>
            </el-table-column>
            <el-table-column label="操作" width="120" align="center" fixed="right">
              <template slot-scope="{ row }">
                <el-button type="text" @click="openEdit(row)">编辑</el-button>
                <el-button v-if="!row.is_system" type="text" class="text-danger" @click="remove(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </div>
    </el-card>

    <el-drawer :title="form.id ? '编辑证照' : '新增证照'" :visible.sync="drawerVisible"
               direction="rtl" size="460px" :wrapper-closable="true">
      <el-form ref="form" :model="form" :rules="rules" label-width="110px" size="small" class="drawer-form">
        <el-form-item label="证照名称" prop="name">
          <el-input v-model="form.name" maxlength="32" show-word-limit placeholder="如:食品经营许可证" />
        </el-form-item>
        <el-form-item label="适用主体" prop="types">
          <el-checkbox-group v-model="form.types">
            <el-checkbox v-for="(label, val) in typeMap" :key="val" :label="Number(val)">{{ label }}</el-checkbox>
          </el-checkbox-group>
          <div class="field-tip">勾选哪些入驻类型在上传该证照</div>
        </el-form-item>
        <el-form-item label="是否必填">
          <el-switch v-model="form.is_required" :active-value="1" :inactive-value="0" :disabled="!form.is_enabled" />
          <span v-if="!form.is_enabled" class="field-tip">停用的证照不参与必填</span>
        </el-form-item>
        <el-form-item label="采集有效期">
          <el-switch v-model="form.need_validity" :active-value="1" :inactive-value="0" />
          <div class="field-tip">开启后商家上传证照时需填写有效期(或勾选长期)</div>
        </el-form-item>
        <el-form-item label="是否启用">
          <el-switch v-model="form.is_enabled" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort" :min="0" :max="9999" controls-position="right" />
          <div class="field-tip">数字小的排在前面</div>
        </el-form-item>
        <el-alert v-if="form.is_system" type="info" :closable="false" show-icon class="sys-alert"
                  title="预置证照可调整配置,但不可删除(可改为停用)" />
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
  name: 'MchCertDef',
  data() {
    return {
      loading: false,
      submitting: false,
      list: [],
      drawerVisible: false,
      typeMap: { 1: '线上商家', 2: '门店商家', 4: '个人租户' },
      form: this.emptyForm(),
      rules: {
        name: [{ required: true, message: '请输入证照名称', trigger: 'blur' }],
        types: [{ type: 'array', required: true, message: '请选择适用主体', trigger: 'change' }]
      }
    }
  },
  created() {
    this.getList()
  },
  methods: {
    emptyForm() {
      return { id: 0, name: '', types: [], is_required: 1, need_validity: 0, is_enabled: 1, sort: 0 }
    },
    async getList() {
      this.loading = true
      try {
        const res = await this.$request({ url: '/plugin/mch/mall/apply-config/cert-list', method: 'get', data: {} })
        if (res.code === 0) {
          this.list = res.data.list || []
        } else {
          this.$message.error(res.msg)
        }
      } finally {
        this.loading = false
      }
    },
    openEdit(row) {
      this.form = row ? { ...row, types: [...(row.types || [])] } : this.emptyForm()
      this.drawerVisible = true
      this.$nextTick(() => this.$refs.form && this.$refs.form.clearValidate())
    },
    submit() {
      this.$refs.form.validate(async(valid) => {
        if (!valid) return
        this.submitting = true
        try {
          const res = await this.$request({
            url: '/plugin/mch/mall/apply-config/cert-save',
            method: 'post',
            data: { ...this.form }
          })
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
    async toggleEnabled(row, val) {
      const res = await this.$request({
        url: '/plugin/mch/mall/apply-config/cert-save',
        method: 'post',
        data: { ...row, is_enabled: val }
      })
      if (res.code === 0) {
        row.is_enabled = val
        this.$message.success(val ? '已启用' : '已停用')
      } else {
        this.$message.error(res.msg)
        this.getList()
      }
    },
    remove(row) {
      this.$confirm(`确认删除证照「${row.name}」吗?`, '提示', { type: 'warning' }).then(async() => {
        const res = await this.$request({ url: '/plugin/mch/mall/apply-config/cert-delete', method: 'post', data: { id: row.id } })
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
.flex-1 { flex: 1; }
.table-wrapper { flex: 1; position: relative; min-height: 0; }
.absolute-table-container { position: absolute; top: 0; left: 0; right: 0; bottom: 0; }
.type-tag { margin-right: 4px; }
.text-danger { color: #F56C6C; }
.drawer-form { padding: 10px 20px; }
.field-tip { color: #909399; font-size: 12px; line-height: 1.5; margin-top: 4px; }
.sys-alert { margin-top: 10px; }
.drawer-footer { padding: 12px 20px; border-top: 1px solid #ebeef5; text-align: right;
  position: absolute; bottom: 0; left: 0; right: 0; background: #fff; }
</style>
