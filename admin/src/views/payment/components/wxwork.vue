<template>
  <div class="app-container">
    <el-card>
      <div slot="header" class="clearfix">
        <span>企业微信客服支付</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="save">保存</el-button>
      </div>

      <div style="width: 900px">
        <el-form size="mini" ref="form" :model="form" label-width="180px">
          <!-- 基础状态配置 -->
          <el-form-item label="是否启用" prop="status">
            <el-switch v-model="form.status" active-value="1" inactive-value="0">
            </el-switch>
          </el-form-item>
          <el-form-item label="支付图标" prop="logo">
            <single-image-selector v-model="form.logo"></single-image-selector>
          </el-form-item>
          <el-form-item label="支付名称" prop="name">
            <el-input v-model="form.name" placeholder="例如：企业微信支付"></el-input>
          </el-form-item>
          <el-form-item label="引导文字" prop="desc">
            <el-input v-model="form.desc" placeholder="例如：联系客服获取支付链接"></el-input>
          </el-form-item>

          <el-divider content-position="left">应用配置 (必填)</el-divider>

          <!-- 核心鉴权配置 -->
          <el-form-item label="企业ID (CorpId)" prop="corpId">
            <el-input v-model.trim="form.corpId" placeholder="在【企业微信后台->我的企业】查看"></el-input>
          </el-form-item>

          <el-form-item label="应用Secret (CorpSecret)" prop="corpSecret">
            <!-- 敏感字段隐藏逻辑 -->
            <el-input @focus="hidden.corpSecret = false" v-if="hidden.corpSecret" readonly
                      placeholder="已隐藏内容，点击查看或编辑，在【应用管理->自建应用】查看"
            >
            </el-input>
            <el-input v-else v-model.trim="form.corpSecret" placeholder="请填写自建应用的Secret"></el-input>
          </el-form-item>

          <el-form-item label="客服账号ID (OpenKfId)" prop="kfId">
            <el-input v-model.trim="form.kfId" placeholder="选填，若不填则自动获取第一个有权限的客服账号"></el-input>
          </el-form-item>

          <el-divider content-position="left">回调配置 (接收消息必填)</el-divider>

          <!-- 回调解密配置 -->
          <el-form-item label="回调Token" prop="token">
            <el-input v-model.trim="form.token" placeholder="在【应用管理->API接收消息】中查看"></el-input>
          </el-form-item>

          <el-form-item label="回调EncodingAESKey" prop="encodingAesKey">
            <!-- 敏感字段隐藏逻辑 -->
            <el-input @focus="hidden.encodingAesKey = false" v-if="hidden.encodingAesKey" readonly
                      placeholder="已隐藏内容，点击查看或编辑，在【应用管理->API接收消息】中查看"
            >
            </el-input>
            <el-input v-else v-model.trim="form.encodingAesKey" placeholder="请填写EncodingAESKey"></el-input>
          </el-form-item>

          <el-form-item label="实际收款网关">
            <el-select v-model="form.real_payment" placeholder="快付-微信JS支付（默认）" clearable style="width: 320px;">
              <el-option v-for="p in paymentOptions" :key="p.value" :label="p.label" :value="p.value">
                <span>{{ p.label }}</span>
                <i v-if="p.warning" class="el-icon-warning" style="color:#E6A23C;margin-left:6px;"></i>
              </el-option>
            </el-select>
            <div class="tip">
              企业微信本身<b>不收钱</b>，它只是把 App 里的用户引到微信客服会话，再发一条支付链接。
              点开链接后真正拉起支付的是这里选的网关。
            </div>
            <div class="tip">
              留空 = 沿用历史默认（快付-微信JS支付）。候选只列出<b>本商城已开启且支持微信平台</b>的网关 ——
              中转页在微信浏览器里走 JSAPI，不支持该平台的网关选了也拉不起来。
            </div>
            <div class="tip" v-if="selectedWarning" style="color:#E6A23C;line-height:1.7;">
              {{ selectedWarning }}
            </div>
            <div class="tip" v-if="!paymentOptions.length" style="color:#E6A23C;">
              当前没有可选网关：请先在支付设置里开启一个支持微信平台的支付方式（如「快付-微信JS支付」），否则用户点开支付链接会失败。
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
  name: 'payment-wxwork',
  data() {
    return {
      is_loading: false,
      // 控制敏感信息的显示/隐藏
      hidden: {
        corpSecret: true,
        encodingAesKey: true
      },
      // 「实际收款网关」候选，由后端按「已开启 ∩ 支持微信平台」算出来下发，
      // 前端不写死任何类名 —— 以后新增微信侧网关会自动出现在这里。
      paymentOptions: [],
      // 表单数据，对应 WxWorkPaymentForm 中的 setting 字段
      form: {
        order_types: [],   // 适用订单类型,空 = 不限
        real_payment: '',  // 实际收款网关;空 = 沿用历史默认(快付微信JS)
        status: '0',
        logo: '',
        name: '',
        desc: '',
        corpId: '',
        corpSecret: '',
        kfId: '',
        token: '',
        encodingAesKey: ''
      }
    }
  },
  created() {
    this.search()
  },
  computed: {
    /** 选中网关的 appid 一致性警告(后端算好下发,前端只负责显示) */
    selectedWarning() {
      const hit = this.paymentOptions.find(p => p.value === this.form.real_payment);
      return hit ? (hit.warning || '') : '';
    }
  },
  methods: {
    // 获取配置
    search() {
      this.is_loading = true
      // 假设路由配置为 /mall/payment/wxwork
      this.$request({
        url: '/mall/payment/wxwork'
      }).then(res => {
        this.is_loading = false
        if (res.data) {
          // 后端把配置和「实际收款网关」候选一起下发，一次请求拿全，
          // 所以这里取的是 res.data.setting 而不是 res.data 本身。
          this.form = Object.assign({ order_types: [], real_payment: '' }, res.data.setting || {})
          this.paymentOptions = res.data.payment_options || []
          // 如果后端返回了数据，重置敏感字段的隐藏状态
          if (this.form.corpSecret) this.hidden.corpSecret = true;
          if (this.form.encodingAesKey) this.hidden.encodingAesKey = true;
        }
      }).catch(res => {
        this.is_loading = false
      })
    },
    // 保存配置
    save() {
      if (this.is_loading) {
        return
      }
      this.is_loading = true
      this.$request({
        url: '/mall/payment/wxwork',
        data: {
          setting: this.form
        },
        method: 'post'
      }).then(e => {
        this.is_loading = false
        this.$message.success(e.msg)
      }).catch(e => {
        this.is_loading = false;
        this.$message.error(e.msg);
      })
    }
  }
}
</script>

<style scoped>
/* 保持原有样式 */
</style>
