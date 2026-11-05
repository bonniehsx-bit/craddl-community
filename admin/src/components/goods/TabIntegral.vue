<template>
  <div style="height: 700px; overflow-y: auto; padding: 10px;">
    <el-row>
      <el-col :span="24">
        <h4 style="margin-bottom: 20px; border-left: 3px solid var(--admin-accent-text); padding-left: 10px;">购物赠送</h4>
      </el-col>
      <el-col :span="24">
        <el-form-item label="赠送方式" prop="send_integral_type">
          <el-radio-group v-model="form.send_integral_type">
            <el-radio :label="0">固定积分</el-radio>
            <el-radio :label="1">按实付金额比例</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-col>
      <el-col :span="24">
        <el-form-item label="赠送时机" prop="send_integral_timing">
          <el-radio-group v-model="form.send_integral_timing">
            <el-radio :label="0">订单结算后赠送</el-radio>
            <el-radio :label="1">支付成功即赠送</el-radio>
          </el-radio-group>
          <div style="font-size: 12px; color: #909399; line-height: 1.5;">
            <template v-if="form.send_integral_timing === 1">
              用户<b>支付成功</b>立即到账;若发生退款或订单关闭,已赠送积分按订单明细<b>自动追回</b>（余额不足扣为负）。
            </template>
            <template v-else>
              订单<b>结算后</b>（售后期满）才赠送，已退款明细不赠送，无需追回。
            </template>
          </div>
        </el-form-item>
      </el-col>
      <el-col :span="12">
        <el-form-item :label="form.send_integral_type === 1 ? '赠送比例' : '赠送积分'" prop="send_integral">
          <el-input v-model="form.send_integral" type="number" min="0">
            <template slot="append">{{ form.send_integral_type === 1 ? '%' : '积分' }}</template>
          </el-input>
          <div style="font-size: 12px; color: #909399; line-height: 1.5;">
            <template v-if="form.send_integral_type === 1">
              按用户该商品<b>实付金额</b>的百分比赠送积分（向下取整）。例：填 5，实付 100 元 → 送 5 积分。
            </template>
            <template v-else>
              用户每购买 1 件该商品获得的积分数量（实际赠送 = 此值 × 购买数量）。
            </template>
          </div>
        </el-form-item>
      </el-col>
    </el-row>

    <el-row style="margin-top: 30px;">
      <el-col :span="24">
        <h4 style="margin-bottom: 20px; border-left: 3px solid var(--admin-accent-text); padding-left: 10px;">积分抵扣</h4>
      </el-col>

      <el-col :span="24">
        <el-form-item label="是否开启抵扣" prop="is_integral">
          <el-switch
            v-model="form.is_integral"
            :active-value="1"
            :inactive-value="0">
          </el-switch>
        </el-form-item>
      </el-col>

      <template v-if="form.is_integral === 1">
        <el-col :span="24">
          <el-form-item label="抵扣方式" prop="use_integral_type">
            <el-radio-group v-model="form.use_integral_type">
              <el-radio :label="0">固定值抵扣</el-radio>
              <el-radio :label="1">按比例抵扣</el-radio>
            </el-radio-group>
          </el-form-item>
        </el-col>

        <el-col :span="24" v-if="form.use_integral_type === 0">
          <el-form-item label="抵扣规则" required>
            <div style="display: flex; align-items: center;">
              <span style="margin-right: 10px;">使用</span>
              <el-input
                v-model="form.use_integral"
                type="number"
                style="width: 150px;"
                placeholder="积分数量">
                <template slot="append">积分</template>
              </el-input>

              <span style="margin: 0 10px;">抵扣</span>

              <el-input
                v-model="form.integral_price"
                type="number"
                style="width: 150px;"
                placeholder="金额">
                <template slot="append">元</template>
              </el-input>
            </div>
            <div style="font-size: 12px; color: #909399;">
              示例：配置使用 100 积分抵扣 10 元。
            </div>
          </el-form-item>
        </el-col>

        <el-col :span="12" v-if="form.use_integral_type === 1">
          <el-form-item label="最大抵扣比例">
            <el-input
              v-model="form.use_integral"
              type="number"
              min="0"
              max="100"
              placeholder="0-100">
              <template slot="append">%</template>
            </el-input>
            <div style="font-size: 12px; color: #909399;">
              示例：填写 50，则用户最多可以使用积分抵扣商品价格的 50%。
              <br/>具体消耗积分数量取决于商城的【积分汇率】设置。
            </div>
          </el-form-item>
        </el-col>
      </template>
    </el-row>
  </div>
</template>

<script>
export default {
  name: "TabIntegral",
  props: {
    form: {
      type: Object,
      required: true
    }
  },
  created() {
    const f = this.form;
    // 归一成数字(匹配 radio/switch 的 :label / :active-value 为数字 0/1);缺失/空则给默认值。
    const num = (v, d) => (v === undefined || v === null || v === '') ? d : Number(v);
    // 选择型字段必须有初始值,否则新建商品时 form 缺键 → 绑定 undefined,radio/switch 不工作甚至报错。
    this.$set(f, 'send_integral_type', num(f.send_integral_type, 0));   // 0=固定积分 1=按比例
    this.$set(f, 'send_integral_timing', num(f.send_integral_timing, 0)); // 0=结算后赠 1=支付即赠
    this.$set(f, 'is_integral', num(f.is_integral, 0));                  // 抵扣开关
    this.$set(f, 'use_integral_type', num(f.use_integral_type, 0));      // 0=固定值 1=按比例
    // 数值型字段:仅在缺失时补空串占位,避免绑定 undefined;已有值不覆盖。
    if (f.send_integral === undefined || f.send_integral === null) this.$set(f, 'send_integral', '');
    if (f.use_integral === undefined || f.use_integral === null) this.$set(f, 'use_integral', '');
    if (f.integral_price === undefined || f.integral_price === null) this.$set(f, 'integral_price', '');
  },
  watch: {
    // 监听开关，关闭时清空或重置数据（可选）
    'form.is_integral': function(val) {
      if (val === 0) {
        // 你可以在这里决定是否要重置数值，或者保留以便下次开启
        // this.form.use_integral = 0;
        // this.form.integral_price = 0;
      }
    }
  }
}
</script>

<style scoped>
/* 简单的样式微调 */
.el-form-item {
  margin-bottom: 22px;
}
</style>
