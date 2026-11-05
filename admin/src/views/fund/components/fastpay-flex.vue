<template>
  <div class="app-container">
    <el-card class="box-card" v-loading="loading">
      <div slot="header" class="clearfix">
        <span>快付灵活用工配置</span>
      </div>
      <el-form :model="form" ref="formRef" label-width="150px" size="small">
        <el-row>
          <el-col :span="12">
            <el-form-item label="启用渠道">
              <el-switch v-model="form.status" active-value="1" inactive-value="0"></el-switch>
            </el-form-item>
            <el-form-item label="渠道名称" required>
              <el-input v-model="form.name" placeholder="例如：快付灵活用工"></el-input>
            </el-form-item>
            <el-form-item label="渠道编号" required>
              <el-input v-model="form.channelCode" placeholder="请输入快付提供的渠道编号"></el-input>
            </el-form-item>
            <el-form-item label="应用APPID" required>
              <el-input v-model="form.applicationId" placeholder="请输入快付提供的应用APPID"></el-input>
            </el-form-item>
            <el-form-item label="平台商户号" required>
              <el-input v-model="form.platformCode" placeholder="请输入快付提供的平台商户号"></el-input>
            </el-form-item>

            <!-- [核心新增] 灵活用工专属字段 -->
            <el-form-item label="代征主体编号" required>
              <el-input v-model="form.mainstayNo" placeholder="请输入快付提供的代征主体编号"></el-input>
            </el-form-item>

            <el-form-item label="商户私钥" required>
              <el-input type="textarea" :rows="5" v-model="form.merchantPrivateKey" placeholder="请输入您的商户私钥"
              ></el-input>
            </el-form-item>
            <el-form-item label="快付平台公钥" required>
              <el-input type="textarea" :rows="5" v-model="form.platformPublicKey" placeholder="请输入快付平台公钥"
              ></el-input>
            </el-form-item>

            <el-form-item>
              <el-button type="primary" @click="save" :loading="submitLoading">保存</el-button>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'FastPayFlexibleLaborFundConfig',
  data() {
    return {
      loading: false,
      submitLoading: false,
      form: {
        status: 0,
        name: '快付灵活用工',
        applicationId: '',
        platformCode: '',
        mainstayNo: '', // [新增]
        merchantPrivateKey: '',
        platformPublicKey: '',
        channelCode:'',
      }
    }
  },
  created() {
    this.loadData()
  },
  methods: {
    async loadData() {
      this.loading = true
      try {
        const res = await this.$request({
          // [核心改造] 调用新的接口地址
          url: '/mall/fund/fast-pay-flexible-labor-fund',
          method: 'get'
        })
        if (res.code === 0) {
          // 使用 Object.assign 合并数据，确保所有字段都存在
          this.form = Object.assign(this.form, res.data)
        } else {
          this.$message.error(res.msg)
        }
      } finally {
        this.loading = false
      }
    },
    async save() {
      this.submitLoading = true
      try {
        const res = await this.$request({
          url: '/mall/fund/fast-pay-flexible-labor-fund',
          method: 'post',
          data: this.form // 后端 Form 中 setting 字段接收整个对象
        })
        if (res.code === 0) {
          this.$message.success('保存成功')
          await this.loadData()
        } else {
          this.$message.error(res.msg)
        }
      } finally {
        this.submitLoading = false
      }
    }
  }
}
</script>

<style scoped>
/* 可根据需要添加样式 */
</style>
