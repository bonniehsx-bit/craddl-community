<template>

  <div class="app-container">
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <span>支付订单</span>

      </div>
      <div>

        <div style="margin-bottom: 20px;">
          <el-form size="mini" inline>
            <el-form-item label="支付方式" prop="payments">
              <el-select v-model="form.payment" placeholder="请选择支付方式">
                <el-option v-for="item in payments" :key="item.payment_type" :label="item.name"
                  :value="item.payment_type">
                </el-option>
              </el-select>
            </el-form-item>

            <el-form-item label="时间">
              <el-date-picker value-format="yyyy-MM-dd" v-model="form.search_date" type="daterange" range-separator="至"
                start-placeholder="开始日期" end-placeholder="结束日期">
              </el-date-picker>
            </el-form-item>
            <el-form-item label="订单号">
              <el-input v-model="form.order_no" placeholder="请输入订单号"></el-input>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" size="mini" @click="search">搜索</el-button>
            </el-form-item>
          </el-form>
        </div>
        <el-table size="mini" :data="list" border style="width: 100%" v-loading="is_loading">
          <el-table-column prop="id" label="ID" width="100" fixed>
          </el-table-column>
          <el-table-column label="下单人" width="260">
            <template slot-scope="scope">
              <div class="flex-row flex-y-center">
                <el-avatar :src="scope.row.avatar_url" fit="cover">
                </el-avatar>
                <div>
                  {{ scope.row.nickname }}
                </div>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="支付方式" width="150">
            <template slot-scope="scope">
              {{ paymentMap[scope.row.payment] }}
            </template>
          </el-table-column>
          <el-table-column label="订单号" prop="order_no" width="150">
          </el-table-column>
          <el-table-column label="支付金额" prop="pay_price">
          </el-table-column>
          <el-table-column label="支付时间" width="160">
            <template slot-scope="scope">
              {{ scope.row.pay_at ? parseTime(scope.row.pay_at) : '' }}
            </template>
          </el-table-column>
          <el-table-column label="是否退款" prop="is_refund" width="100">
            <template slot-scope="scope">
              <div class="flex-row flex-y-center">
                {{ scope.row.is_refund == 1 ? '是' : '否' }}
              </div>
            </template>
          </el-table-column>
          <el-table-column label="退款时间" prop="refund_at" width="100">
          </el-table-column>
          <el-table-column label="退款金额" prop="refund_price" width="100">
          </el-table-column>
          <el-table-column label="退款异常" prop="refund_remarks">
          </el-table-column>
          <el-table-column label="是否推送到微信" prop="is_push_wechat" width="100">
            <template slot-scope="scope">
              <el-tag v-if="scope.row.is_push_wechat == 1" type="success">是</el-tag>
              <el-tag v-if="scope.row.is_push_wechat == 0">否</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="推送反馈" prop="push_errors">

          </el-table-column>
        </el-table>
        <pagination :pagination="pagination" v-model="page" @pageChange="getList"></pagination>
      </div>
    </el-card>
  </div>
</template>

<script>
import payment from '@/views/mall/payment.vue'
import { parseTime } from '@/utils/jxmall'

export default {
  name: 'payment-order',
  data() {
    return {
      activeTabName: 'all',
      is_loading: false,
      page: 1,
      list: [],
      payments: [],
      pagination: null,
      paymentMap: [],
      form: {
        page: 1,
        order_no: '',
        payment: '',
        search_date: null
      }
    }
  },
  created() {
    this.getPayments()

  },
  mounted() {
    //  this.getList()
  },
  methods: {
    parseTime,
    getPayments() {
      this.$request({
        url: '/mall/payment/payments'
      }).then(res => {
        this.payments = res.data.payments
        this.payments.forEach(item => {
          this.paymentMap[item.payment_type] = item.name
        })
        this.getList()
      })
    },

    search() {
      this.list = []
      this.page = 1
      this.getList()
    },
    getList() {
      this.is_loading = true

      this.form.page = this.page
      this.$request({
        url: '/mall/payment/order',
        data: this.form
      }).then(res => {
        this.is_loading = false
        this.list = res.data.list
        this.pagination = res.data.pagination
      })
    }
  }
}
</script>

<style lang="scss">
.btn {
  margin-right: 20px;

  i {
    font-size: 25px;
  }
}
</style>
