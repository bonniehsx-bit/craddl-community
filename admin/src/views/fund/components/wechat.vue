<template>

  <div class="app-container">
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <span>微信打款到零钱</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="save">保存</el-button>
      </div>

      <div style="width: 900px">
       <el-form size="mini" ref="form" :model="form" label-width="200px">
          <el-form-item label="是否启用" prop="status">
            <el-switch v-model="form.status" active-value="1" inactive-value="0">
            </el-switch>
          </el-form-item>
          <el-form-item label="打款名称" prop="name">
            <el-input v-model="form.name"></el-input>
          </el-form-item>
          <el-form-item label="公众号">
            <el-form-item label="app_id">
              <el-input v-model="form.wechat.app_id"></el-input>
            </el-form-item>
            <el-form-item label="secret">
              <el-input v-model="form.wechat.secret"></el-input>
            </el-form-item>
            <el-form-item label="商户号" style="margin-top: 10px">
              <el-input v-model="form.wechat.mch_id"></el-input>
            </el-form-item>
            <el-form-item label="微信支付秘钥API KEY(V2)" style="margin-top: 10px">
              <el-input v-model.trim="form.wechat.api_v2_key"></el-input>
            </el-form-item>
            <el-form-item label="微信支付秘钥API KEY(V3)" style="margin-top: 10px">
              <el-input v-model.trim="form.wechat.api_v3_key"></el-input>
            </el-form-item>
            <el-form-item label="商户API证书序列号" style="margin-top: 10px">
              <el-input v-model="form.wechat.mch_serial_no"></el-input>
            </el-form-item>
            <el-form-item label="平台证书序列号" style="margin-top: 10px">
              <el-input v-model="form.wechat.platform_serial_no"></el-input>
            </el-form-item>
            <el-form-item label="场景ID" style="margin-top: 10px">
              <el-input v-model.trim="form.wechat.transfer_scene_id"></el-input>
            </el-form-item>
            <el-form-item label="场景名称" style="margin-top: 10px">
              <el-input v-model.trim="form.wechat.transfer_scene_name"></el-input>
            </el-form-item>
            <el-form-item label="平台公钥ID" style="margin-top: 10px">
              <el-input v-model.trim="form.wechat.pub_key_id"></el-input>
            </el-form-item>
            <el-form-item label="平台公钥" style="margin-top: 10px">
              <el-input type="textarea" v-model.trim="form.wechat.pub_key"></el-input>
            </el-form-item>
            <el-form-item label="微信支付apiclient_cert.pem" prop="cert_path" style="margin-top: 10px">
              <el-input type="textarea" :rows="5" v-model="form.wechat.cert_pem">
              </el-input>
            </el-form-item>

          </el-form-item>
          <el-form-item label="小程序" style="margin-top: 10px">
            <el-form-item label="app_id">
              <el-input v-model="form.mpwx.app_id"></el-input>
            </el-form-item>
            <el-form-item label="secret">
              <el-input v-model="form.mpwx.secret"></el-input>
            </el-form-item>
            <el-form-item label="商户号" style="margin-top: 10px">
              <el-input v-model="form.mpwx.mch_id"></el-input>
            </el-form-item>
            <el-form-item label="微信支付秘钥API KEY(V2)" style="margin-top: 10px">
              <el-input v-model.trim="form.mpwx.api_v2_key"></el-input>
            </el-form-item>
            <el-form-item label="微信支付秘钥API KEY(V3)" style="margin-top: 10px">
              <el-input v-model.trim="form.mpwx.api_v3_key"></el-input>
            </el-form-item>
            <el-form-item label="商户API证书序列号" style="margin-top: 10px">
              <el-input v-model="form.mpwx.mch_serial_no"></el-input>
            </el-form-item>
            <el-form-item label="平台证书序列号" style="margin-top: 10px">
              <el-input v-model="form.mpwx.platform_serial_no"></el-input>
            </el-form-item>
            <el-form-item label="场景ID" style="margin-top: 10px">
              <el-input v-model.trim="form.mpwx.transfer_scene_id"></el-input>
            </el-form-item>
            <el-form-item label="场景名称" style="margin-top: 10px">
              <el-input v-model.trim="form.mpwx.transfer_scene_name"></el-input>
            </el-form-item>
            <el-form-item label="平台公钥ID" style="margin-top: 10px">
              <el-input v-model.trim="form.mpwx.pub_key_id"></el-input>
            </el-form-item>
            <el-form-item label="平台公钥" style="margin-top: 10px">
              <el-input type="textarea" v-model.trim="form.mpwx.pub_key"></el-input>
            </el-form-item>
            <el-form-item label="微信支付apiclient_cert.pem" prop="cert_path" style="margin-top: 10px">
              <el-input type="textarea" :rows="5" v-model="form.mpwx.cert_pem">
              </el-input>
            </el-form-item>
            <el-form-item label="微信支付apiclient_key.pem" style="margin-top: 10px">
              <el-input type="textarea" :rows="5" v-model="form.mpwx.key_pem">
              </el-input>
            </el-form-item>
          </el-form-item>
          <el-form-item label="注意事项">
            <div style="color:#ff4455">
              <el-input type="textarea" :rows="15" v-model.trim="form.remarks"></el-input>
            </div>
          </el-form-item>
        </el-form>
      </div>
    </el-card>
  </div>
</template>
<script>
export default {
  name: 'fund-bs',
  data() {
    return {

      form: {
        status: 0,
        name: '微信打款',
        wechat: {
          transfer_scene_id: '1005',
          transfer_scene_name: '佣金报酬',
          secret: "",
          app_id: '',
          mch_id: '',
          api_v2_key: '',
          api_v3_key: '',
          cert_pem: '',
          key_pem: '',
          pub_key_id: '',
          pub_key: '',
          mch_serial_no: '',
          platform_serial_no: '',
        },
        mpwx: {
          transfer_scene_id: '1005',
          transfer_scene_name: '佣金报酬',
          app_id: '',
          secret: "",
          mch_id: '',
          api_v2_key: '',
          api_v3_key: '',
          cert_pem: '',
          pub_key_id: '',
          pub_key: '',
          key_pem: '',
          mch_serial_no: '',
          platform_serial_no: '',
        },
        remarks: '',

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
        url: '/mall/fund/wechat'

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
        url: '/mall/fund/wechat',
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
