<template>
  <div class="app-container">
    <el-card>
      <div slot="header" class="clearfix">
        <span>余额打款</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="save">保存</el-button>
      </div>

      <div style="width: 900px">
        <el-form size="mini" ref="form" :model="form" label-width="160px">
          <el-form-item label="是否启用" prop="status">
            <el-switch v-model="form.status" active-value="1" inactive-value="0">
            </el-switch>
          </el-form-item>
          <el-form-item label="余额打款" prop="name">
            <el-input v-model="form.name"></el-input>
          </el-form-item>

        </el-form>
      </div>
    </el-card>
  </div>
</template>
<script>
export default {
  name: 'fund-balance',
  data() {
    return {
      form: {
        status: 0,
        name: '余额转账',
        remarks: '用户如果提现到余额可以选这个通道直接进入余额账户'
      }
    }
  },
  created() {
    this.search()
  },
  methods: {
    search() {
      this.is_loading = true
      this.$request({
        url: '/mall/fund/balance'
      }).then(res => {
        this.is_loading = false
        if (res.data) {
          this.form = res.data
        }
      }).catch(res => {
        this.is_loading = false

      })
    },
    save() {
      if (this.is_loading) {
        return
      }
      this.is_loading = true
      this.$request({
        url: '/mall/fund/balance',
        data: {
          setting: this.form
        },
        method: 'post'
      }).then(e => {
        this.is_loading = false
        this.$message.success(e.msg)
      })
    }
  }
}
</script>


<style scoped>

</style>
