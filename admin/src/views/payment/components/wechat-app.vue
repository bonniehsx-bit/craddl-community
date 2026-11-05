<template>
  <div class="app-container">
    <el-card>
      <div slot="header" class="clearfix">
        <span>App 微信原生支付（App 端唤起微信客户端付款）</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="save">保存</el-button>
      </div>

      <div style="width: 900px">
        <el-alert type="warning" :closable="false" show-icon class="cfg-guide-alert">
          <template slot="title"><b>配置说明（App 原生支付与公众号/小程序支付是不同 appid，务必分清）</b></template>
          <div class="cfg-guide">
            <p>1. <b style="color:#f56c6c;">移动应用APPID（app_id）</b>：填【微信开放平台 open.weixin.qq.com → 管理中心 → 移动应用】里那个 App 的 AppID（形如 wx 开头）。
              <span style="color:#f56c6c;">⚠️ 不是公众号 appid，也不是小程序 appid。</span>它必须与 App 打包时 manifest 里配的微信 SDK appid 完全一致。</p>
            <p>2. <b>微信支付商户号(mch_id)</b>：微信支付商户平台首页右上角的商户号。可与公众号/小程序支付共用同一个商户。</p>
            <p>3. <b style="color:#f56c6c;">API KEY(V3)（必填 · 32位）</b>：商户平台 → 账户中心 → API安全 → 「APIv3密钥」。回调体用它 AES-256-GCM 解密，不填/填错会「支付成功却不发货(Decrypt failed)」。</p>
            <p>4. <b>证书序列号 / apiclient_cert.pem / apiclient_key.pem</b>：商户平台 → 账户中心 → API安全 → 申请API证书，下载后填全文（含 -----BEGIN----- 行）。与公众号支付同商户时，填一样的证书即可。</p>
            <p>5. <b>平台公钥ID / 平台公钥</b>：用「公钥模式」时填；证书模式可留空。</p>
          </div>
        </el-alert>

        <el-alert type="error" :closable="false" show-icon class="cfg-guide-alert">
          <template slot="title"><b>微信后台前置绑定（漏一项 App 就调不起支付，最易漏，务必逐项核对）</b></template>
          <div class="cfg-guide">
            <p>A. <b style="color:#f56c6c;">移动应用需绑定商户号</b>：微信支付商户平台 → 产品中心 → AppID账号管理 → 关联「开放平台移动应用 appid」。未关联 → 下单报「appid 与 mch_id 不匹配」。</p>
            <p>B. <b style="color:#f56c6c;">开通「APP支付」产品</b>：商户平台 → 产品中心 → APP支付 → 申请开通并签约。未开通 → 下单直接失败。</p>
            <p>C. <b>移动应用需通过开放平台审核且已上线</b>：开放平台移动应用状态为「已通过」，否则拿不到可用 appid。</p>
            <p style="color:#909399;">说明：App 原生支付不需要 JS 安全域名 / 网页授权域名 / 支付授权目录（那些是公众号/H5 才要的）。</p>
          </div>
        </el-alert>

        <el-form size="mini" ref="form" :model="form" label-width="190px">
          <el-form-item label="是否启用" prop="status">
            <el-switch v-model="form.status" active-value="1" inactive-value="0"></el-switch>
          </el-form-item>
          <el-form-item label="支付图标" prop="logo">
            <single-image-selector v-model="form.logo"></single-image-selector>
          </el-form-item>
          <el-form-item label="支付名称" prop="name">
            <el-input v-model="form.name" placeholder="如：微信支付"></el-input>
          </el-form-item>
          <el-form-item label="引导文字" prop="desc">
            <el-input v-model="form.desc"></el-input>
          </el-form-item>
          <el-form-item label="移动应用APPID" prop="app_id">
            <el-input v-model.trim="form.app_id" placeholder="开放平台移动应用 appid（非公众号/小程序）"></el-input>
          </el-form-item>
          <el-form-item label="微信支付商户号" prop="mch_id">
            <el-input v-model.trim="form.mch_id"></el-input>
          </el-form-item>
          <el-form-item label="支付秘钥API KEY(V3)" prop="api_v3_key">
            <el-input @focus="hidden.api_v3_key = false" v-if="hidden.api_v3_key" readonly
                      placeholder="已隐藏内容，点击查看或编辑">
            </el-input>
            <el-input v-else v-model.trim="form.api_v3_key"></el-input>
          </el-form-item>
          <el-form-item label="支付秘钥API KEY(V2)" prop="api_v2_key">
            <el-input @focus="hidden.api_v2_key = false" v-if="hidden.api_v2_key" readonly
                      placeholder="已隐藏内容，点击查看或编辑（可留空）">
            </el-input>
            <el-input v-else v-model.trim="form.api_v2_key"></el-input>
          </el-form-item>
          <el-form-item label="微信支付证书序列号" prop="serial_no">
            <el-input v-model.trim="form.serial_no"></el-input>
          </el-form-item>
          <el-form-item label="平台公钥ID">
            <el-input v-model.trim="form.pub_key_id"></el-input>
          </el-form-item>
          <el-form-item label="平台公钥">
            <el-input type="textarea" v-model.trim="form.pub_key"></el-input>
          </el-form-item>
          <el-form-item label="apiclient_cert.pem" prop="cert_pem">
            <el-input @focus="hidden.cert_pem = false" v-if="hidden.cert_pem" readonly
                      type="textarea" :rows="5" placeholder="已隐藏内容，点击查看或编辑">
            </el-input>
            <el-input v-else type="textarea" :rows="5" v-model="form.cert_pem"></el-input>
          </el-form-item>
          <el-form-item label="apiclient_key.pem" prop="key_pem">
            <el-input @focus="hidden.key_pem = false" v-if="hidden.key_pem" readonly
                      type="textarea" :rows="5" placeholder="已隐藏内容，点击查看或编辑">
            </el-input>
            <el-input v-else type="textarea" :rows="5" v-model="form.key_pem"></el-input>
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
  name: 'payment-wechat-app',
  data() {
    return {
      is_loading: false,
      hidden: {
        cert_pem: true,
        key_pem: true,
        api_v3_key: true,
        api_v2_key: true
      },
      form: {
        order_types: [],   // 适用订单类型,空 = 不限
        pub_key: '',
        pub_key_id: '',
        name: '',
        desc: '',
        logo: '',
        app_id: '',
        mch_id: '',
        api_v2_key: '',
        api_v3_key: '',
        cert_pem: '',
        status: 0,
        key_pem: '',
        serial_no: ''
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
        url: '/mall/payment/wechat-app'
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
      // 【强制校验】启用时 APIv3 密钥必填且必须 32 位：回调体用 APIv3 密钥(AES-256-GCM)加密，
      // 缺失/错误会导致「支付成功却收不到通知、订单不发货(Decrypt failed)」。
      if (String(this.form.status) === '1') {
        if (!String(this.form.app_id || '').trim()) {
          this.$message.error('请填写「移动应用APPID」（开放平台移动应用 appid，非公众号/小程序 appid）')
          return
        }
        const v3 = (this.form.api_v3_key || '').trim()
        if (!v3) {
          this.$message.error('启用 App 微信支付必须配置「支付秘钥 API KEY(V3)」，否则无法解密支付回调！')
          return
        }
        if (v3.length !== 32) {
          this.$message.error('「API KEY(V3)」必须为 32 位（当前 ' + v3.length + ' 位）')
          return
        }
        if (!String(this.form.mch_id || '').trim()) {
          this.$message.error('请填写「微信支付商户号」')
          return
        }
      }
      this.is_loading = true
      this.$request({
        url: '/mall/payment/wechat-app',
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

<style scoped>
.cfg-guide-alert { margin-bottom: 16px; align-items: flex-start; }
.cfg-guide { font-size: 12px; line-height: 1.8; color: #606266; }
.cfg-guide p { margin: 2px 0; }
</style>
