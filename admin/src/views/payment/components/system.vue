<template>
  <div class="app-container">
    <el-card>
      <div slot="header" class="clearfix">
        <span>后台支付设置</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="save" :loading="is_loading">保存</el-button>
      </div>

      <div style="width: 900px">
        <el-form size="mini" ref="form" :model="form" label-width="160px">
          <el-form-item label="是否启用" prop="status">
            <el-switch v-model="form.status" :active-value="1" :inactive-value="0">
            </el-switch>
            <div class="el-form-item__description">控制管理员在订单详情页是否可以看到“确认付款”按钮。</div>
          </el-form-item>
          <el-form-item label="支付图标" prop="logo">
            <single-image-selector v-model="form.logo"></single-image-selector>
          </el-form-item>
          <el-form-item label="支付名称" prop="name">
            <el-input v-model="form.name" placeholder="例如：管理员确认收款"></el-input>
            <div class="el-form-item__description">该名称将显示在订单列表和详情中。</div>
          </el-form-item>
          <el-form-item label="引导文字" prop="desc">
            <el-input v-model="form.desc" placeholder="例如：适用于线下转账等场景"></el-input>
          </el-form-item>
        </el-form>
      </div>
    </el-card>
  </div>
</template>
<script>

export default {
  name: 'payment-system',
  data() {
    return {
      is_loading: false,
      form: {
        name: '后台支付',
        desc: '',
        logo: '',
        status: 0
      }
    }
  },
  created() {
    this.search()
  },
  methods: {
    search() {
      this.is_loading = true
      // [注意] 这里的URL需要和您后台路由匹配
      this.$request({ url: '/mall/payment/system' }).then(res => {
        this.is_loading = false
        if(res.data){
          this.form = res.data
        }
      }).catch(() => { this.is_loading = false });
    },
    save() {
      this.is_loading = true
      this.$request({
        url: '/mall/payment/system',
        data: { setting: this.form },
        method: 'post'
      }).then(() => {
        this.is_loading = false
        this.$message.success('保存成功');
      }).catch(() => { this.is_loading = false });
    }
  }
}
</script>
