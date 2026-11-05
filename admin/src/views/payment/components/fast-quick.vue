<template>
  <div class="app-container">
    <el-card>
      <div slot="header" class="clearfix">
        <span>快付(FastPay) 快捷支付 (银行卡)</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="save">保存</el-button>
      </div>

      <div style="width: 900px">
        <el-form size="mini" ref="form" :model="form" label-width="160px">
          <el-form-item label="是否启用" prop="status">
            <el-switch v-model="form.status" active-value="1" inactive-value="0">
            </el-switch>
          </el-form-item>
          <el-form-item label="支付图标" prop="logo">
            <single-image-selector v-model="form.logo"></single-image-selector>
          </el-form-item>
          <el-form-item label="支付名称" prop="name">
            <el-input v-model="form.name" placeholder="例如：银行卡快捷支付"></el-input>
          </el-form-item>
          <el-form-item label="引导文字" prop="desc">
            <el-input v-model="form.desc" placeholder="例如：使用储蓄卡/信用卡快捷支付"></el-input>
          </el-form-item>

          <el-divider>接口配置</el-divider>

          <el-form-item label="平台商户号" prop="platformCode">
            <el-input v-model.trim="form.platformCode" placeholder="对应接口 [公共参数] 中的 platformCode"></el-input>
          </el-form-item>
          <el-form-item label="应用APPID" prop="applicationId">
            <el-input v-model.trim="form.applicationId" placeholder="对应接口 [业务参数 data] 中的 applicationId"></el-input>
          </el-form-item>

          <el-form-item label="商户私钥" prop="merchantPrivateKey">
            <el-input type="textarea" :rows="5" v-model="form.merchantPrivateKey"
                      placeholder="用于生成请求签名 sign，请填写您自己的商户私钥 (去除页眉页脚)"></el-input>
          </el-form-item>
          <el-form-item label="快付平台公钥" prop="platformPublicKey">
            <el-input type="textarea" :rows="5" v-model="form.platformPublicKey"
                      placeholder="用于验证回调通知的签名 sign，请填写快付提供的平台公钥 (去除页眉页脚)"></el-input>
          </el-form-item>

          <el-divider>说明</el-divider>
          <el-alert
            type="info"
            :closable="false"
            title="快捷支付 (银行卡) 介绍"
            show-icon>
            <div style="line-height: 1.8; font-size: 12px;">
              用户通过绑定本人银行卡 (储蓄卡或信用卡) 进行支付。用户首次支付前需要在 App/小程序/H5 的
              "我的银行卡" 入口完成绑卡 (填写姓名、证件号、卡号、预留手机号并输入短信验证码)。
              下单时选择本支付方式后, 系统会向预留手机号再下发一次支付短信, 用户输入验证码即可完成扣款。
              <br /><br />
              请确保已在快付平台开通 "快捷支付产品 (KJ_*) " 并获得本商户对应的 applicationId 和签名密钥。
            </div>
          </el-alert>
          <order-type-scope v-model="form.order_types"></order-type-scope>
        </el-form>
      </div>
    </el-card>
  </div>
</template>

<script>
import OrderTypeScope from './OrderTypeScope';

export default {
  components: { OrderTypeScope },
  name: 'payment-fast-quick',
  data() {
    return {
      is_loading: false,
      form: {
        order_types: [],   // 适用订单类型,空 = 不限
        status: 0,
        logo: '',
        name: '银行卡快捷支付',
        desc: '',
        platformCode: '',
        applicationId: '',
        merchantPrivateKey: '',
        platformPublicKey: ''
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
        url: '/mall/payment/fastpay-quick'
      }).then(res => {
        this.is_loading = false
        if (res.data) {
          this.form = Object.assign({ order_types: [] }, res.data)
        }
      }).catch(() => {
        this.is_loading = false
      })
    },
    save() {
      if (this.is_loading) {
        return
      }
      this.is_loading = true
      this.$request({
        url: '/mall/payment/fastpay-quick',
        data: {
          setting: this.form
        },
        method: 'post'
      }).then(e => {
        this.is_loading = false
        this.$message.success(e.msg)
      }).catch(() => {
        this.is_loading = false
      })
    }
  }
}
</script>
