<template>
  <div class="app-container">
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <span>版本审核配置</span>
      </div>

      <el-form size="small" ref="form" :model="form" label-width="160px" style="max-width: 600px; margin-top: 20px;">
        <el-form-item label="微信小程序版本号:" prop="mpwx">
          <el-input v-model="form.mpwx" placeholder="请输入微信小程序正在审核的版本号"></el-input>
        </el-form-item>
        <el-form-item label="iOS 版本号:" prop="ios">
          <el-input v-model="form.ios" placeholder="请输入 iOS 正在审核的版本号"></el-input>
        </el-form-item>
        <el-form-item label="Android 版本号:" prop="android">
          <el-input v-model="form.android" placeholder="请输入 Android 正在审核的版本号"></el-input>
        </el-form-item>
        <el-form-item label="接口根地址:" prop="api_url">
          <el-input v-model="form.api_url" placeholder="例如: https://api.example.com"></el-input>
        </el-form-item>
        <el-form-item label="审核测试 Token:" prop="token">
          <el-input v-model="form.token" placeholder="提供给审核员的测试号 Token" type="textarea" :rows="2"></el-input>
        </el-form-item>
        <el-form-item label="强制开启审核模式:" prop="is_enabled">
          <el-switch v-model="form.is_enabled" active-value="1" inactive-value="0"></el-switch>
        </el-form-item>

        <el-form-item>
          <el-button type="primary" @click="save" size="small">保存配置</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>

</template>

<script>
export default {
  name: 'mall-checking',
  data() {

    return {
      form: {
        ios: '',
        android: '',
        mpwx: '',
        api_url: '',
        is_enabled: '0',
        token: '',
      },
    }
  },
  created() {
    this.getSetting();
  },
  methods: {

    save() {
      this.$request({
        url: '/mall/mall/checking',
        data: this.form,
        method: 'post'
      }).then(res => {
        if (res.code == 0) {
          this.$message.success(res.msg)
        }
      })


    },
    getSetting() {
      this.$request({
        url: '/mall/mall/checking',
      }).then(res => {
        if (res.code == 0) {
          this.form = Object.assign(this.form, res.data.versions)
          // 开关绑的是字符串 "1"/"0",存下来的可能是数字,统一成字符串,否则开关永远显示关
          this.form.is_enabled = String(Number(this.form.is_enabled) === 1 ? 1 : 0)
        }
      })
    },
  }
}
</script>

<style></style>
