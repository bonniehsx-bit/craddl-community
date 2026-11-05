<template>
  <div class="app-container">
    <el-card class="box-card" v-loading="is_loading">
      <div slot="header" class="clearfix">
        <span>参数设置</span>

        <el-button style="float: right; padding: 3px 20px;" type="text" @click="submit">保存</el-button>
      </div>
      <div>
        <el-row>
          <el-col :span="12">
            <el-form size="mini" ref="form" :model="form" label-width="200px">
              <el-form-item label="app名称">
                <el-input v-model="form.name"></el-input>
              </el-form-item>
              <el-form-item label="APPID">
                <el-input v-model="form.appid"></el-input>
              </el-form-item>
              <el-form-item label="密钥">
                <el-input v-model="form.secret"></el-input>
              </el-form-item>
              <el-form-item label="支付借用方式">
                <div>
                  <el-radio v-model="form.use_payment" label="0">原始支付</el-radio>
                  <el-radio v-model="form.use_payment" label="1">H5支付</el-radio>
                  <el-radio v-model="form.use_payment" label="2">小程序支付</el-radio>
                </div>
              </el-form-item>
              <el-form-item label="借用支付的小程序原始ID">
                <el-input v-model="form.mp_id"></el-input>
              </el-form-item>

              <el-form-item label="App借用小程序支付版本">
                <el-radio v-model="form.mp_type" label="0">正式版</el-radio>
                <el-radio v-model="form.mp_type" label="1">开发版</el-radio>
                <el-radio v-model="form.mp_type" label="2">体验版</el-radio>
              </el-form-item>
              <el-form-item label="支付借用小程序appid">
                <el-input v-model="form.wxapp_pay_mpwx_appid"></el-input>
              </el-form-item>

              <el-form-item label="h5支付logo">
                <single-image-selector size="768*768" v-model="form.h5_pay_logo">
                </single-image-selector>
              </el-form-item>
              <el-form-item label="h5支付标题">
                <el-input v-model="form.h5_pay_title"></el-input>
              </el-form-item>

              <el-form-item label="h5支付小标题">
                <el-input v-model="form.h5_pay_sub_title"></el-input>
              </el-form-item>

              <el-divider content-position="left">Universal Link(iOS 唤起 App)</el-divider>
              <el-form-item label="iOS 应用ID(Team ID)">
                <el-input v-model="form.ios_team_id" placeholder="苹果开发者 Team ID，如 ABCDE12345"></el-input>
              </el-form-item>
              <el-form-item label="包名(Bundle ID)">
                <el-input v-model="form.ios_bundle_id" placeholder="App Bundle ID，如 com.company.app"></el-input>
              </el-form-item>
              <el-form-item label="关联文件地址(AASA)">
                <el-input :value="aasa_url" readonly>
                  <el-button slot="append" icon="el-icon-document-copy" @click="copyAasa">复制</el-button>
                </el-input>
                <div class="aasa-tip">
                  仅 <b>应用ID(Team&nbsp;ID)</b> 与 <b>包名(Bundle&nbsp;ID)</b> 两项为变量，其余为固定模板。
                  <b>保存后</b>会按 <code>Team&nbsp;ID.Bundle&nbsp;ID</code> 生成到站点的
                  <code>.well-known/apple-app-site-association</code>（即上方地址）。<br>
                  iOS 只从这个<b>固定路径</b>拉取关联文件（要求 HTTPS、无跳转、合法 JSON、文件名不带扩展名），放子目录无效。<br>
                  备注：一套后端部署多个 App 属极少数情况，此文件会互相覆盖、需自行按域名区分；单 App 部署无需关心。
                </div>
              </el-form-item>

            </el-form>
          </el-col>
        </el-row>
      </div>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'platform-wxapp',
  data() {
    return {
      is_loading: false,
      aasa_url: '',
      form: {
        name: '',
        appid: '',
        secret: '',
        mp_id: '',
        wxapp_pay_mpwx_appid: '',
        use_payment: '0',
        mp_type: '0',
        h5_pay_logo: '',
        h5_pay_title: '代付订单',
        h5_pay_sub_title: '您有一笔代付订单，去支付',
        ios_team_id: '',
        ios_bundle_id: ''
      }
    }
  },
  created: function() {

    this.getSetting()

  },
  methods: {

    menu() {
      this.$router.push({
        path: '/wechat/menu'
      })

    },
    getSetting() {
      this.is_loading = true
      this.$request({
        url: '/mall/platform/wxapp'
      }).then(res => {
        this.is_loading = false
        this.form = {
          ...this.form,
          ...res.data.wxapp
        }
        this.aasa_url = res.data.aasa_url || ''
      }).catch(e => {
        this.is_loading = false
      })
    },
    copyAasa() {
      if (!this.aasa_url) return
      const ta = document.createElement('textarea')
      ta.value = this.aasa_url
      document.body.appendChild(ta)
      ta.select()
      try {
        document.execCommand('copy')
        this.$message.success('已复制关联文件地址')
      } catch (e) {
        this.$message.warning('复制失败，请手动复制')
      }
      document.body.removeChild(ta)
    },
    submit() {
      this.$request({
        url: '/mall/platform/wxapp',
        data: this.form,
        method: 'post'
      }).then(res => {
        if (res.code == 0) {
          this.$message.success(res.msg)
        }
      })
    }
  }
}
</script>

<style>
.aasa-tip {
  color: #999;
  font-size: 12px;
  line-height: 1.5;
  margin-top: 4px;
}
.aasa-tip code {
  background: #f5f5f5;
  color: #c7254e;
  padding: 0 4px;
  border-radius: 3px;
}
</style>
