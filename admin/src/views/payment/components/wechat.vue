<template>
  <div class="app-container">
    <el-card>
      <div slot="header" class="clearfix">
        <span>微信公众号官方支付</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="save">保存</el-button>
      </div>

      <div style="width: 900px">
        <el-alert type="warning" :closable="false" show-icon class="cfg-guide-alert">
          <template slot="title"><b>配置说明（请认真填写，缺一项都可能收不到支付/回调）</b></template>
          <div class="cfg-guide">
            <p>1. <b>公众号APPID / secret</b>：微信公众平台 → 基本配置。</p>
            <p>2. <b>微信支付商户号(mch_id)</b>：微信支付商户平台首页右上角的商户号。</p>
            <p>3. <b style="color:#f56c6c;">API KEY(V3)（必填 · 32位）</b>：商户平台 → 账户中心 → API安全 → 设置「APIv3密钥」。
              <span style="color:#f56c6c;">⚠️ 支付回调体用它解密，不填 / 填错会导致"支付成功却收不到通知、订单不发货(Decrypt failed)"。</span></p>
            <p>4. <b>证书序列号 / apiclient_cert.pem / apiclient_key.pem</b>：商户平台 → 账户中心 → API安全 → 申请API证书，下载后填入证书全文（含 -----BEGIN----- 行）。</p>
            <p>5. <b>平台公钥ID / 平台公钥</b>：使用微信支付「公钥模式」时填（商户平台 API安全 下载平台公钥）；用证书模式可留空。</p>
            <p>6. <b>支付回调地址</b>：系统自动用「当前站点域名」拼接，无需手填。但务必保证<b>当前后台 / 下单 / 数据库是同一套环境，且该域名公网可访问 https</b>——多环境（生产/测试）切勿混用域名，否则回调会发到另一套环境。</p>
          </div>
        </el-alert>

        <el-alert type="error" :closable="false" show-icon class="cfg-guide-alert">
          <template slot="title"><b>微信后台「外部配置」——这些填不进本页，但漏一项就支付不了 / 登录不了（最易漏，务必逐项核对）</b></template>
          <div class="cfg-guide">
            <p>A. <b style="color:#f56c6c;">IP 白名单</b>：把<b>本服务器公网出口 IP</b> 加到「微信公众平台 → 设置与开发 → 基本配置 → IP白名单」。
              <span style="color:#f56c6c;">⚠️ 漏配 → 拿不到 access_token / jsapi_ticket（错误 40164 invalid ip）→ H5 一键登录、分享、公众号/JSAPI 支付全失败，前端报“调用支付JSAPI缺少参数: appId”。</span></p>
            <p>B. <b style="color:#f56c6c;">支付授权目录</b>：把<b>发起支付页面所在的目录</b>（到目录级、结尾带“/”，如 https://你的域名/h5/）加到「微信支付商户平台 → 产品中心 → 开发配置 → 支付配置 → 公众号支付 → 支付授权目录」。
              <span style="color:#f56c6c;">⚠️ 漏配 / 目录不匹配 → 调起支付直接失败（getBrandWCPayRequest:fail，提示“当前页面 URL 未注册”）。</span></p>
            <p>C. <b>JS接口安全域名</b>：把 <b>H5 域名</b>（只填域名，不带 http、不带路径）加到「微信公众平台 → 设置与开发 → 公众号设置 → 功能设置 → JS接口安全域名」。漏配 → 前端 wx.config 失败，JSSDK 调起支付/分享报错。</p>
            <p>D. <b>网页授权域名</b>：同上「功能设置」里把 H5 域名加到「网页授权域名」，否则公众号静默登录拿不到 openid（JSAPI 支付必须先有 openid）。</p>
            <p style="color:#909399;">说明：A / C / D 在<b>公众号</b>后台（mp.weixin.qq.com），B 在<b>微信支付商户</b>后台（pay.weixin.qq.com）；生产 / 测试是不同域名和服务器，各自的 IP、目录、域名都要分别配，切勿只配一套。</p>
          </div>
        </el-alert>
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
          <el-form-item label="公众号APPID" prop="app_id">
            <el-input v-model="form.app_id"></el-input>

          </el-form-item>
          <el-form-item label="公众号secret" prop="secret">
            <el-input v-model="form.secret"></el-input>

          </el-form-item>
          <el-form-item label="微信支付商户号" prop="mch_id">
            <el-input v-model.trim="form.mch_id"></el-input>
          </el-form-item>
          <el-form-item label="支付秘钥API KEY(V2)" prop="key">
            <el-input @focus="hidden.api_v2_key = false" v-if="hidden.api_v2_key" readonly
                      placeholder="已隐藏内容，点击查看或编辑"
            >
            </el-input>
            <el-input v-else v-model.trim="form.api_v2_key"></el-input>
          </el-form-item>
          <el-form-item label="支付秘钥API KEY(V3)" prop="key">
            <el-input @focus="hidden.api_v3_key = false" v-if="hidden.api_v3_key" readonly
                      placeholder="已隐藏内容，点击查看或编辑"
            >
            </el-input>
            <el-input v-else v-model.trim="form.api_v3_key"></el-input>
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
          <el-form-item label="微信支付apiclient_cert.pem" prop="cert_path">
            <el-input @focus="hidden.cert_pem = false" v-if="hidden.cert_pem" readonly
                      type="textarea" :rows="5" placeholder="已隐藏内容，点击查看或编辑"
            >
            </el-input>
            <el-input v-else type="textarea" :rows="5" v-model="form.cert_pem">
            </el-input>
          </el-form-item>
          <el-form-item label="微信支付apiclient_key.pem" prop="key_pem">
            <el-input @focus="hidden.key_pem = false" v-if="hidden.key_pem" readonly
                      type="textarea" :rows="5" placeholder="已隐藏内容，点击查看或编辑"
            >
            </el-input>
            <el-input v-else type="textarea" :rows="5" v-model="form.key_pem">
            </el-input>
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
  name: 'payment-wechat',
  data() {
    return {
      hidden: {
        app_id: true,
        mch_id: false,
        cert_pem: true,
        key_pem: true,
        api_v3_key: true,
        api_v2_key: true
      },
      form: {
        order_types: [],   // 适用订单类型,空 = 不限
        secret: '',
        pub_key: '',
        pub_key_id: '',
        name: '',
        desc: '',
        logo: '',
        app_id: '',
        mch_id: '',
        api_v2_key: '',
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
        url: '/mall/payment/wechat'

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
      // 【强制校验】启用时 APIv3 密钥必填且必须 32 位：
      // 微信 V3 支付回调体用 APIv3 密钥(AES-256-GCM)加密，缺失/错误会导致回调解密失败(Decrypt failed)，
      // 表现为"支付成功但系统收不到通知、订单不发货"。务必在微信商户平台「API安全」设置后填入。
      if (String(this.form.status) === '1') {
        const v3 = (this.form.api_v3_key || '').trim()
        if (!v3) {
          this.$message.error('启用微信支付必须配置「支付秘钥 API KEY(V3)」，否则无法解密支付回调，会导致支付成功却收不到通知！')
          return
        }
        if (v3.length !== 32) {
          this.$message.error('「API KEY(V3)」必须为 32 位（当前 ' + v3.length + ' 位）。请到微信商户平台「账户中心 → API安全 → APIv3密钥」设置后填入')
          return
        }
        if (!String(this.form.mch_id || '').trim()) {
          this.$message.error('请填写「微信支付商户号」')
          return
        }
      }
      this.is_loading = true
      this.$request({
        url: '/mall/payment/wechat',
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
.cfg-guide-alert { margin-bottom: 16px; align-items: flex-start; }
.cfg-guide { font-size: 12px; line-height: 1.8; color: #606266; }
.cfg-guide p { margin: 2px 0; }
</style>
