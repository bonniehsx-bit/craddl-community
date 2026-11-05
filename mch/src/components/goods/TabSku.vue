<template>
  <div class="tab-number-container">
    <el-divider content-position="left" class="custom-divider">价格信息</el-divider>

    <div class="number-grid-section">
      <el-row :gutter="24">
        <el-col :span="6" :xs="12" :sm="8">
          <el-form-item label="售价" prop="price">
            <el-input type="number" v-model="form.price" step="0.01" min="0" placeholder="0.00">
              <template slot="append">元</template>
            </el-input>
          </el-form-item>
        </el-col>
        <el-col :span="6" :xs="12" :sm="8">
          <el-form-item label="原价" prop="origin_price">
            <el-input type="number" v-model="form.origin_price" step="0.01" min="0" placeholder="0.00">
              <template slot="append">元</template>
            </el-input>
          </el-form-item>
        </el-col>
        <el-col :span="6" :xs="12" :sm="8">
          <el-form-item label="成本价" prop="cost_price">
            <el-input type="number" v-model="form.cost_price" step="0.01" min="0" placeholder="0.00">
              <template slot="append">元</template>
            </el-input>
          </el-form-item>
        </el-col>

        <el-col :span="6" :xs="12" :sm="8">
          <el-form-item label="当前库存" prop="stock">
            <el-input type="number" v-model="form.stock" min="0" placeholder="0">
              <template slot="append">件</template>
            </el-input>
          </el-form-item>
        </el-col>
        <el-col :span="8" class="hidden-sm-and-down"></el-col>

        <el-col :span="6" :xs="12" :sm="8">
          <el-form-item label="真实销量" prop="sales">
            <el-input type="number" v-model="form.sales" step="1" :disabled="true" class="readonly-input">
              <template slot="append">件</template>
            </el-input>
          </el-form-item>
        </el-col>
        <el-col :span="6" :xs="12" :sm="8">
          <el-form-item label="虚拟销量" prop="virtual_sales">
            <el-input type="number" v-model="form.virtual_sales" step="1" min="0" placeholder="0">
              <template slot="append">件</template>
            </el-input>
          </el-form-item>
        </el-col>

      </el-row>
    </div>

    <template v-if="!disableMultiSpec">
      <el-divider content-position="left" class="custom-divider">SKU 规格配置</el-divider>

      <el-row>
        <el-col :span="24">
          <el-form-item label="商品规格" prop="specs_data">
            <sku-generator v-model="form.specs_data"></sku-generator>
          </el-form-item>
        </el-col>
      </el-row>
    </template>

  </div>
</template>

<script>
import SkuGenerator from "./SkuGenerator.vue";

export default {
  name: "TabSku",
  components: { SkuGenerator },
  props: {
    form: {
      type: Object,
      required: true
    },
    disableMultiSpec: {
      type: Boolean,
      default: false
    }
  },
  watch: {
    // 强制防呆保底：如果是已被锁定单规格的插件业务（禁用了 SKU 生成器）
    // 则强行将顶部外露的表单基础值同步到底层真正的单级 sku_list[0] 中，防止数据断层或拦截错误
    'form.price'(newVal) {
      if (this.disableMultiSpec && this.form.specs_data && this.form.specs_data.sku_list && this.form.specs_data.sku_list.length > 0) {
        this.form.specs_data.sku_list[0].price = newVal;
      }
    },
    'form.origin_price'(newVal) {
      if (this.disableMultiSpec && this.form.specs_data && this.form.specs_data.sku_list && this.form.specs_data.sku_list.length > 0) {
        this.form.specs_data.sku_list[0].origin_price = newVal;
      }
    },
    'form.cost_price'(newVal) {
      if (this.disableMultiSpec && this.form.specs_data && this.form.specs_data.sku_list && this.form.specs_data.sku_list.length > 0) {
        this.form.specs_data.sku_list[0].cost_price = newVal;
      }
    },
    'form.stock'(newVal) {
      if (this.disableMultiSpec && this.form.specs_data && this.form.specs_data.sku_list && this.form.specs_data.sku_list.length > 0) {
        this.form.specs_data.sku_list[0].stock = newVal;
      }
    }
  }
}
</script>

<style scoped lang="scss">
.tab-number-container {
  padding-right: 20px; /* 右侧留点白，防止贴边 */
}

/* 隐藏输入框原生的上下微调箭头，保持极简 */
::v-deep input[type=number]::-webkit-inner-spin-button,
::v-deep input[type=number]::-webkit-outer-spin-button {
  -webkit-appearance: none;
  appearance: none;
  margin: 0;
}
::v-deep input[type=number] {
  -moz-appearance: textfield;
}

/* 只读输入框样式微调，让禁止修改的销量看起来更干净 */
::v-deep .readonly-input.is-disabled .el-input__inner {
  background-color: #f8f9fb;
  color: #909399;
  border-color: #ebeef5;
}

/* 分割线样式 */
.custom-divider {
  margin: 30px 0 20px 0;

  ::v-deep .el-divider__text {
    color: #909399;
    font-size: 13px;
    background-color: #fff; /* 确保跟卡片底色融为一体 */
  }
}
</style>
