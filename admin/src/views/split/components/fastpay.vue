<template>
  <div class="app-container">
    <!-- 配置卡片 -->
    <el-card>
      <div slot="header" class="clearfix">
        <span>快付分账配置</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="save">保存</el-button>
      </div>
      <div style="width: 900px">
        <el-form size="mini" ref="form" :model="form" label-width="180px">
          <el-form-item label="是否启用" prop="status">
            <el-switch v-model="form.status" active-value="1" inactive-value="0"></el-switch>
          </el-form-item>
          <el-form-item label="签约手续费(rate)" prop="rate">
            <el-input v-model.trim="form.rate" placeholder="签约的手续费比例">
              <template slot="append">%</template>
            </el-input>
          </el-form-item>
          <el-form-item label="渠道名称" prop="name">
            <el-input v-model="form.name" placeholder="例如：快付分账"></el-input>
          </el-form-item>
          <el-form-item label="渠道编号(channelCode)" prop="channelCode">
            <el-input v-model.trim="form.channelCode" placeholder="快付提供的渠道编号，如 shengpay"></el-input>
          </el-form-item>
          <el-form-item label="平台商户号(platformCode)" prop="platformCode">
            <el-input v-model.trim="form.platformCode"></el-input>
          </el-form-item>
          <el-form-item label="应用APPID(applicationId)" prop="applicationId">
            <el-input v-model.trim="form.applicationId"></el-input>
          </el-form-item>
          <el-form-item label="商户私钥(merchantPrivateKey)" prop="merchantPrivateKey">
            <el-input type="textarea" :rows="8" v-model.trim="form.merchantPrivateKey"
                      placeholder="请填写去除头尾和换行符的私钥字符串"
            ></el-input>
          </el-form-item>
          <el-form-item label="快付平台公钥(platformPublicKey)" prop="platformPublicKey">
            <el-input type="textarea" :rows="5" v-model.trim="form.platformPublicKey"
                      placeholder="请填写去除头尾和换行符的公钥字符串"
            ></el-input>
          </el-form-item>
        </el-form>
      </div>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'split-fastpay',
  data() {
    return {
      is_loading: false,
      form: {
        status: 0,
        rate: '',
        name: '快付分账',
        platformCode: '',
        applicationId: '',
        merchantPrivateKey: '',
        platformPublicKey: '',
        channelCode: ''
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
        url: '/mall/split/fast-pay'
      }).then(res => {
        this.is_loading = false
        if (res.code === 0 && res.data) {
          this.form = res.data
        }
      }).catch(err => {
        this.is_loading = false
      })
    },
    save() {
      if (this.is_loading) return
      this.is_loading = true
      this.$request({
        url: '/mall/split/fast-pay',
        method: 'post',
        data: {
          setting: this.form
        }
      }).then(res => {
        this.is_loading = false
        if (res.code === 0) {
          this.$message.success(res.msg)
        } else {
          this.$message.error(res.msg)
        }
      }).catch(err => {
        this.is_loading = false
      })
    }
  }
}
</script>

<style scoped>
.app-container {
  padding: 20px;
}

.result-box {
  margin-top: 20px;
  padding: 15px;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  background-color: #f9fafc;
}

.tip {
  font-size: 12px;
  color: #909399;
  margin-top: 10px;
}
</style>

