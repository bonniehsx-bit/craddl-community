<template>
  <div class="app-container">
    <el-card shadow="always" :body-style="{ padding: '20px' }">
      <div slot="header">
        <span>
          <!--标题-->
          数据大屏
        </span>
        <div style="float: right; padding: 3px 0" class="flex-y-center">
          <el-button type="text" @click="save">保存</el-button>
          <el-button type="text">
            <a style="float: right; padding: 3px 0" target="_blank" href="/screen">
              预览
            </a>
          </el-button>
        </div>
      </div>
      <div>
        <!--body-->

        <div style="width: 50%">
         <el-form size="mini" :model="form" ref="searchForm" label-width="100px" :inline="false" >
            <el-form-item label="使用模拟数据">
              <el-radio v-model="form.is_mock" label="0">否</el-radio>
              <el-radio v-model="form.is_mock" label="1">是</el-radio>
            </el-form-item>
            <el-form-item label="季度1">
              <el-input v-model="form.jd1"></el-input>
            </el-form-item>
            <el-form-item label="季度2">
              <el-input v-model="form.jd2"></el-input>
            </el-form-item>
            <el-form-item label="季度3">
              <el-input v-model="form.jd3"></el-input>
            </el-form-item>
            <el-form-item label="季度4">
              <el-input v-model="form.jd4"></el-input>
            </el-form-item>
            <el-form-item label="今日销售额">
              <el-input v-model="form.DaySalesData[0]"></el-input>
            </el-form-item>
            <el-form-item label="昨日销售额">
              <el-input v-model="form.DaySalesData[1]"></el-input>
            </el-form-item>
            <el-form-item label="本周销售额">
              <el-input v-model="form.DaySalesData[2]"></el-input>
            </el-form-item>


            <el-form-item label="近半年销售额">
              <div class="flex-y-center">
                <el-input v-model="form.SalePrices[0]"></el-input>
                <el-input v-model="form.SalePrices[1]"></el-input>
                <el-input v-model="form.SalePrices[2]"></el-input>
                <el-input v-model="form.SalePrices[3]"></el-input>
                <el-input v-model="form.SalePrices[4]"></el-input>
                <el-input v-model="form.SalePrices[5]"></el-input>
              </div>
            </el-form-item>


            <el-form-item label="总销售额">
              <div class="flex-y-center">
                <el-input v-model="form.zongxiaoshoue"></el-input>
              </div>
            </el-form-item>

            <el-form-item label="总会员人数">
              <div class="flex-y-center">

                <el-input v-model="form.zonghuiyuan"></el-input>

              </div>
            </el-form-item>
            <el-form-item label="总订单">
              <div class="flex-y-center">
                <el-input v-model="form.zongdingdan"></el-input>
              </div>
            </el-form-item>


            <el-form-item label="总佣金">
              <div class="flex-y-center">
                <el-input v-model="form.total_price"></el-input>
              </div>
            </el-form-item>
            <el-form-item label="提现中">
              <div class="flex-y-center">
                <el-input v-model="form.cashing"></el-input>
              </div>
            </el-form-item>
            <el-form-item label="已提现">
              <div class="flex-y-center">
                <el-input v-model="form.cashed"></el-input>
              </div>
            </el-form-item>
            <el-form-item label="近7天销量走势">
              <div class="flex-y-center">
                <el-input v-model="form.Sales7[0]"></el-input>
                <el-input v-model="form.Sales7[1]"></el-input>
                <el-input v-model="form.Sales7[2]"></el-input>
                <el-input v-model="form.Sales7[3]"></el-input>
                <el-input v-model="form.Sales7[4]"></el-input>
                <el-input v-model="form.Sales7[5]"></el-input>
                <el-input v-model="form.Sales7[6]"></el-input>

              </div>
            </el-form-item>
            <el-form-item label="销量前四的商品">
              <div class="flex-y-center">
                <el-input placeholder="名称" v-model="form.GoodsInfo4[0].name"></el-input>
                <el-input placeholder="销量" v-model="form.GoodsInfo4[0].sales"></el-input>
              </div>
              <div class="flex-y-center">
                <el-input placeholder="名称" v-model="form.GoodsInfo4[1].name"></el-input>
                <el-input placeholder="销量" v-model="form.GoodsInfo4[1].sales"></el-input>
              </div>
              <div class="flex-y-center">
                <el-input placeholder="名称" v-model="form.GoodsInfo4[2].name"></el-input>
                <el-input placeholder="销量" v-model="form.GoodsInfo4[2].sales"></el-input>
              </div>
              <div class="flex-y-center">
                <el-input placeholder="名称" v-model="form.GoodsInfo4[3].name"></el-input>
                <el-input placeholder="销量" v-model="form.GoodsInfo4[3].sales"></el-input>
              </div>
            </el-form-item>
            <el-form-item label="会员新增量">
              <div class="flex-y-center">
                <el-input v-model="form.UserNewData7[0]"></el-input>
                <el-input v-model="form.UserNewData7[1]"></el-input>
                <el-input v-model="form.UserNewData7[2]"></el-input>
                <el-input v-model="form.UserNewData7[3]"></el-input>
                <el-input v-model="form.UserNewData7[4]"></el-input>
                <el-input v-model="form.UserNewData7[5]"></el-input>
                <el-input v-model="form.UserNewData7[6]"></el-input>
              </div>
            </el-form-item>
            <el-form-item label="待支付订单">
              <div class="flex-y-center">
                <el-input v-model="form.waitPay"></el-input>
              </div>
            </el-form-item>
            <el-form-item label="已支付订单">
              <div class="flex-y-center">
                <el-input v-model="form.isPay"></el-input>
              </div>
            </el-form-item>
            <el-form-item label="已完成订单">
              <div class="flex-y-center">
                <el-input v-model="form.isOk"></el-input>
              </div>
            </el-form-item>
            <el-form-item label="月成交量">
              <div class="flex-y-center">
                <el-input v-model="form.YearSales[0]"></el-input>
                <el-input v-model="form.YearSales[1]"></el-input>
                <el-input v-model="form.YearSales[2]"></el-input>
                <el-input v-model="form.YearSales[3]"></el-input>
                <el-input v-model="form.YearSales[4]"></el-input>
                <el-input v-model="form.YearSales[5]"></el-input>
                <el-input v-model="form.YearSales[6]"></el-input>
                <el-input v-model="form.YearSales[7]"></el-input>
                <el-input v-model="form.YearSales[8]"></el-input>
                <el-input v-model="form.YearSales[9]"></el-input>
                <el-input v-model="form.YearSales[10]"></el-input>
                <el-input v-model="form.YearSales[11]"></el-input>
              </div>
            </el-form-item>
            <el-form-item label="会员走势">
              <div class="flex-y-center">
                <el-input v-model="form.MemberList[0]"></el-input>
                <el-input v-model="form.MemberList[1]"></el-input>
                <el-input v-model="form.MemberList[2]"></el-input>
                <el-input v-model="form.MemberList[3]"></el-input>
                <el-input v-model="form.MemberList[4]"></el-input>
                <el-input v-model="form.MemberList[5]"></el-input>
                <el-input v-model="form.MemberList[6]"></el-input>
                <el-input v-model="form.MemberList[7]"></el-input>
                <el-input v-model="form.MemberList[8]"></el-input>
                <el-input v-model="form.MemberList[9]"></el-input>
                <el-input v-model="form.MemberList[10]"></el-input>
                <el-input v-model="form.MemberList[11]"></el-input>
              </div>
            </el-form-item>
          </el-form>

        </div>

      </div>
    </el-card>
  </div>
</template>
<script>
export default {
  name: 'mall-noun',
  data() {
    return {
      is_loading: false,
      list: [],
      form: {
        is_mock: 0,
        jd1: 0,
        jd2: 0,
        jd3: 0,
        jd4: 0,
        zongxiaoshoue: 0,
        zongdingdan: 0,
        zonghuiyuan: 0,
        SalePrices: [0, 0, 0, 0, 0, 0],
        DaySalesData: [0, 0, 0],
        total_price: 0,
        total: 0,
        cashing: 0,
        cashed: 0,
        Sales7: [0, 0, 0, 0, 0, 0, 0],

        GoodsInfo4: [
          {name: '商品1', sales: 0},
          {name: '商品2', sales: 0},
          {name: '商品3', sales: 0},
          {name: '商品4', sales: 0}
        ],
        UserNewData7: [0, 0, 0, 0, 0, 0],
        waitPay: 0,
        isPay: 0,
        isOk: 0,
        YearSales: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        MemberList: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]
      }
    }
  },
  created() {
    this.search();
  },
  methods: {
    search() {
      if (this.is_loading) {
        return;
      }
      this.is_loading = true;
      this.$request({
        url: "/mall/mall/screen",
      }).then(res => {
        this.is_loading = false;
        this.form = Object.assign(this.form, res.data)

      })
    },
    save() {
      this.is_loading = true;
      this.$request({
        url: "/mall/mall/screen",
        data: {
          form: this.form
        },
        method: "post"
      }).then(res => {
        this.is_loading = false;

        this.$message.success(res.msg);
      })
    }
  },
}
</script>
<style scoped>

</style>
