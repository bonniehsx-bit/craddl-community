<template>
  <div class="app-container">
    <el-card>
      <div slot="header" class="clearfix">
        <span>余额支付</span>
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
  name: 'payment-balance',
  data() {
    return {
      hidden: {
        app_id: true
      },
      form: {
        order_types: [],   // 适用订单类型,空 = 不限
        name: '',
        desc: '',
        logo: '',
        app_id: '',
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
      this.$request({
        url: '/mall/payment/balance'

      }).then(res => {
        this.is_loading = false
        if(res.data){
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
        url: '/mall/payment/balance',
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
