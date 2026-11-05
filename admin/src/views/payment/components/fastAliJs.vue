<template>
  <div class="app-container">
    <el-card>
      <!-- [修改] 标题更新为 H5 支付 -->
      <div slot="header" class="clearfix">
        <span>快付(FastPay) 支付宝JSAPI支付 (支付宝/H5)</span>
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
            <el-input v-model="form.name" placeholder="例如：支付宝H5"></el-input>
          </el-form-item>
          <el-form-item label="引导文字" prop="desc">
            <el-input v-model="form.desc" placeholder="例如：推荐使用支付宝H5"></el-input>
          </el-form-item>

          <el-divider>接口配置</el-divider>

          <el-form-item label="平台商户号" prop="platformCode">
            <el-input v-model.trim="form.platformCode" placeholder="对应接口 [公共参数] 中的 platformCode"></el-input>
          </el-form-item>
          <el-form-item label="应用APPID" prop="applicationId">
            <el-input v-model.trim="form.applicationId" placeholder="对应接口 [业务参数 data] 中的 applicationId"></el-input>
          </el-form-item>

          <!-- ======================= [核心修改] 开始 ======================= -->
          <el-form-item label="支付宝APPID" prop="appid">
            <el-input v-model.trim="form.appid" placeholder="对应接口 [业务参数 data] 中的 appid (您自己的支付宝ID)"></el-input>
            <div class="el-form-item__info">用于在支付宝内浏览器发起支付，请务必填写支付宝生活号的AppID</div>
          </el-form-item>
          <!-- ======================= [核心修改] 结束 ======================= -->

          <el-form-item label="商户私钥" prop="merchantPrivateKey">
            <el-input @focus="hidden.merchantPrivateKey = false" v-if="hidden.merchantPrivateKey" readonly
                      type="textarea" :rows="5" placeholder="已隐藏内容，点击查看或编辑"
            >
            </el-input>
            <el-input v-else type="textarea" :rows="5" v-model="form.merchantPrivateKey" placeholder="用于生成请求签名 sign，请填写您自己的商户私钥 (去除页眉页脚)"></el-input>
          </el-form-item>
          <el-form-item label="快付平台公钥" prop="platformPublicKey">
            <el-input @focus="hidden.platformPublicKey = false" v-if="hidden.platformPublicKey" readonly
                      type="textarea" :rows="5" placeholder="已隐藏内容，点击查看或编辑"
            >
            </el-input>
            <el-input v-else type="textarea" :rows="5" v-model="form.platformPublicKey" placeholder="用于验证回调通知的签名 sign，请填写快付提供的平台公钥 (去除页眉页脚)"></el-input>
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
  // [修改] 组件名称更新，以反映其用途
  name: 'payment-fastpay-alijs',
  data() {
    return {
      is_loading: false,
      hidden: {
        merchantPrivateKey: true,
        platformPublicKey: true,
      },
      form: {
        order_types: [],   // 适用订单类型,空 = 不限
        status: 0,
        logo: '',
        name: '支付宝H5',
        desc: '',
        platformCode: '',
        applicationId: '',
        appid: '', // 字段名保持不变
        merchantPrivateKey: '',
        platformPublicKey: '',
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
        // ======================= [核心修改] 开始 =======================
        // [修改] API 地址指向我们为 H5 支付创建的后端 Form
        url: '/mall/payment/fastpay-alijs'
        // ======================= [核心修改] 结束 =======================
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
        // ======================= [核心修改] 开始 =======================
        // [修改] API 地址指向我们为 H5 支付创建的后端 Form
        url: '/mall/payment/fastpay-alijs',
        // ======================= [核心修改] 结束 =======================
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
