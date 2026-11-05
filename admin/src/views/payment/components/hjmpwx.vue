<template>
  <div class="app-container">
    <el-card>
      <div slot="header" class="clearfix">
        <span>微信小程序汇聚支付</span>
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
            <el-input v-model="form.name"></el-input>
          </el-form-item>
          <el-form-item label="引导文字" prop="desc">
            <el-input v-model="form.desc"></el-input>
          </el-form-item>
          <el-form-item label="小程序APPID" prop="app_id">
            <el-input v-model="form.app_id"></el-input>
          </el-form-item>
          <el-form-item label="商户号(mch_id)" prop="mch_id">
            <el-input v-model.trim="form.mch_id"></el-input>
          </el-form-item>
          <el-form-item label="报备商户号" prop="join_trade_merchant_no">
            <el-input v-model.trim="form.mpwx_join_trade_merchant_no"></el-input>
          </el-form-item>
          <el-form-item label="聚合商户编号" prop="join_merchant_no">
            <el-input v-model.trim="form.mpwx_join_merchant_no"></el-input>
          </el-form-item>
          <el-form-item label="商户加密密钥" prop="join_app_secret">
            <el-input v-model.trim="form.mpwx_join_app_secret"></el-input>
          </el-form-item>
          <el-form-item label="平台公钥" prop="join_public_key">
            <el-input type="textarea" :rows="15" v-model.trim="form.mpwx_join_public_key"></el-input>
          </el-form-item>
          <el-form-item label="商户私钥" prop="join_private_key">
            <el-input type="textarea" :rows="15" v-model.trim="form.mpwx_join_private_key"></el-input>
          </el-form-item>
          <el-form-item label="注意事项">
            <div style="color:#ff4455">
              使用汇聚支付，仅支持支付，打款到个人银行卡，不支持打款到用户零钱，如果需要打款到用户零钱，则需要配置好微信原生支付相关参数
            </div>
          </el-form-item>

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
  name: 'payment-mpwx',
  data() {
    return {
      hidden: {
        app_id: true,
        mch_id: false

      },
      form: {
        order_types: [],   // 适用订单类型,空 = 不限
        status: 0,
        name: '',
        desc: '',
        logo: '',
        app_id: '',
        mch_id: '',
        mpwx_join_trade_merchant_no: '',
        mpwx_join_private_key: '',
        mpwx_join_app_secret: '',
        mpwx_join_public_key: '',
        mpwx_join_merchant_no: ''
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
        url: '/mall/payment/hjmpwx'

      }).then(res => {
        this.is_loading = false
        if (res.data) {
          this.form = Object.assign({ order_types: [] }, res.data)

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
        url: '/mall/payment/hjmpwx',
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
