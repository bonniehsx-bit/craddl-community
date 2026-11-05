<template>
  <div style="height: 700px; overflow-y: auto; padding: 10px;">
    <el-row>
      <el-col :span="24">
        <h4 style="margin-bottom: 20px; border-left: 3px solid #409EFF; padding-left: 10px;">购物赠送</h4>
      </el-col>
      <el-col :span="12">
        <el-form-item label="赠送积分" prop="send_integral">
          <el-input v-model="form.send_integral" type="number" min="0">
            <template slot="append">积分</template>
          </el-input>
          <div style="font-size: 12px; color: #909399; line-height: 1.5;">
            用户购买该商品后获得的积分数量
          </div>
        </el-form-item>
      </el-col>
    </el-row>

    <el-row style="margin-top: 30px;">
      <el-col :span="24">
        <h4 style="margin-bottom: 20px; border-left: 3px solid #409EFF; padding-left: 10px;">积分抵扣</h4>
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
