<template>
  <el-dialog title="修改物流" :visible="visible" width="420px" append-to-body :close-on-click-modal="false" @close="close">
    <el-alert type="info" :closable="false" show-icon class="tip"
      title="只改快递公司和单号，不会通知买家；买家收货后不能再改。" />
    <el-form ref="form" :model="form" label-width="80px" size="small">
      <el-form-item label="原物流">
        <span class="old">{{ express ? `${express.express_name} ${express.express_no}` : '' }}</span>
      </el-form-item>
      <el-form-item label="快递公司" prop="express_name" :rules="{ required: true, message: '请选择快递公司', trigger: 'change' }">
        <el-select v-model="form.express_name" placeholder="请选择快递公司" filterable style="width: 100%;" @change="expressSelect">
          <el-option v-for="item in expressList" :key="item.id" :label="item.name" :value="item.name" />
        </el-select>
      </el-form-item>
      <el-form-item label="快递单号" prop="express_no" :rules="{ required: true, message: '请输入快递单号', trigger: 'blur' }">
        <el-input v-model="form.express_no" maxlength="64" placeholder="请输入正确的快递单号" />
      </el-form-item>
    </el-form>
    <span slot="footer">
      <el-button size="small" @click="close">取消</el-button>
      <el-button type="primary" size="small" :loading="loading" @click="submit">保存</el-button>
    </span>
  </el-dialog>
</template>

<script>
export default {
  name: 'ExpressUpdateDialog',
  props: {
    visible: { type: Boolean, default: false },
    orderId: { type: [Number, String], default: null },
    // 要修改的那条物流记录(express_log):id / express_name / express_code / express_no
    express: { type: Object, default: null }
  },
  data() {
    return {
      loading: false,
      expressList: [],
      form: { express_name: '', express_code: '', express_no: '' }
    }
  },
  watch: {
    visible(val) {
      if (!val) return
      const e = this.express || {}
      this.form = { express_name: e.express_name || '', express_code: e.express_code || '', express_no: e.express_no || '' }
      this.$nextTick(() => this.$refs.form && this.$refs.form.clearValidate())
      if (!this.expressList.length) this.loadExpressList()
    }
  },
  methods: {
    async loadExpressList() {
      const res = await this.$request({ url: '/common/express/express' })
      if (res.code === 0) this.expressList = res.data.list || []
    },
    expressSelect(name) {
      const selected = this.expressList.find(item => item.name === name)
      this.form.express_code = selected ? selected.key : ''
    },
    close() {
      this.$emit('update:visible', false)
    },
    submit() {
      this.$refs.form.validate(async valid => {
        if (!valid) return
        this.loading = true
        try {
          const res = await this.$request({
            url: '/mall/order/express-update',
            method: 'post',
            data: {
              order_id: this.orderId,
              express_log_id: this.express.id,
              express_name: this.form.express_name,
              express_code: this.form.express_code,
              express_no: this.form.express_no.trim()
            }
          })
          if (res.code !== 0) {
            this.$message.error(res.msg)
            return
          }
          const wechat = (res.data && res.data.wechat) || {}
          if (wechat.status === 'failed') {
            // 单号已经改好了,只是微信那边没同步上 —— 要让操作人知道买家在微信里看到的可能还是旧单号
            this.$alert(`物流已修改。${wechat.message}`, '微信同步未成功', { type: 'warning' })
          } else {
            this.$message.success(wechat.status === 'synced' ? '修改成功，已同步到微信' : '修改成功')
          }
          this.close()
          this.$emit('refresh')
        } finally {
          this.loading = false
        }
      })
    }
  }
}
</script>

<style scoped>
.tip { margin-bottom: 16px; }
.old { color: #909399; }
</style>
