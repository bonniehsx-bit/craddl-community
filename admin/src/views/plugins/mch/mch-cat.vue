<template>
  <div class="app-container">
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <span>商家分类管理</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="handleEdit(null)">添加分类</el-button>
      </div>

      <!-- 数据表格 -->
      <el-table :data="list" v-loading="loading" row-key="id">
        <el-table-column prop="id" label="ID" width="80"></el-table-column>
        <el-table-column label="分类名称">
          <template slot-scope="scope">
            <div class="cat-info">
              <el-avatar shape="square" :size="40" :src="scope.row.cover_pic"></el-avatar>
              <span>{{ scope.row.cat_name }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="order_num" label="排序" align="center" width="100"></el-table-column>
        <el-table-column label="创建时间" width="200">
          <template slot-scope="scope">{{ new Date(scope.row.created_at * 1000).toLocaleString() }}</template>
        </el-table-column>
        <el-table-column label="操作" width="150" align="center">
          <template slot-scope="scope">
            <el-button type="text" @click="handleEdit(scope.row)">编辑</el-button>
            <el-button type="text" class="text-danger" @click="handleDelete(scope.row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 编辑/新增弹窗 -->
    <el-dialog :title="form.id ? '编辑分类' : '添加分类'" :visible.sync="dialogVisible" width="30%"
               @closed="onDialogClosed"
    >
      <el-form :model="form" ref="form" :rules="rules" label-width="100px" size="small">
        <el-form-item label="分类名称" prop="cat_name">
          <el-input v-model="form.cat_name" autocomplete="off"></el-input>
        </el-form-item>
        <el-form-item label="分类图标" prop="cover_pic">
          <single-image-selector v-model="form.cover_pic"></single-image-selector>
        </el-form-item>
        <el-form-item label="排序" prop="order_num">
          <el-input-number v-model="form.order_num" :min="0"></el-input-number>
        </el-form-item>
        <el-form-item label="是否显示">
          <el-radio-group v-model="form.is_show">
            <el-radio :label="0">否</el-radio>
            <el-radio :label="1">是</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <div slot="footer" class="dialog-footer">
        <el-button @click="dialogVisible = false">取 消</el-button>
        <el-button type="primary" @click="submit" :loading="submitLoading">确 定</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
// 假设您有全局的 SingleImageSelector 组件
export default {
  name: 'mch-cat',
  data() {
    return {
      loading: false,
      list: [],
      dialogVisible: false,
      submitLoading: false,
      form: {
        id: null,
        cat_name: '',
        cover_pic: '',
        is_show: 1,
        order_num: 100
      },
      rules: {
        cat_name: [{ required: true, message: '请输入分类名称', trigger: 'blur' }]
      }
    }
  },
  created() {
    this.getList()
  },
  methods: {
    async getList() {
      this.loading = true
      try {
        const res = await this.$request({
          url: '/plugin/mch/mall/mch-cat/index',
          method: 'get',
          data: {}
        })
        if (res.code === 0) {
          // 防御性赋值：防止后端在无数据时返回 null 导致 el-table 的 data 监听器报错
          this.list = res.data.list || []
        } else {
          this.$message.error(res.msg)
        }
      } finally {
        this.loading = false
      }
    },
    handleEdit(row) {
      if (row) {
        this.form = { ...row }
      } else {
        this.form = { id: null, cat_name: '', cover_pic: '', order_num: 100 }
      }
      this.dialogVisible = true
    },
    handleDelete(row) {
      this.$confirm(`确认删除分类 "${row.cat_name}" 吗？`, '提示', {
        type: 'warning'
      }).then(async() => {
        const res = await this.$request({
          url: '/plugin/mch/mall/mch-cat/delete',
          method: 'post',
          data: { id: row.id }
        })
        if (res.code === 0) {
          this.$message.success('删除成功')
          this.getList()
        } else {
          this.$message.error(res.msg)
        }
      }).catch(() => {
      })
    },
    submit() {
      this.$refs.form.validate(async(valid) => {
        if (!valid) return
        this.submitLoading = true
        try {
          const res = await this.$request({
            url: '/plugin/mch/mall/mch-cat/edit',
            method: 'post',
            data: { form: this.form }
          })
          if (res.code === 0) {
            this.$message.success('保存成功')
            this.dialogVisible = false
            this.getList()
          } else {
            this.$message.error(res.msg)
          }
        } finally {
          this.submitLoading = false
        }
      })
    },
    onDialogClosed() {
      this.$refs.form.clearValidate()
    }
  }
}
</script>

<style scoped>
.cat-info {
  display: flex;
  align-items: center;
}

.cat-info span {
  margin-left: 10px;
}

.text-danger {
  color: #F56C6C;
}
</style>
