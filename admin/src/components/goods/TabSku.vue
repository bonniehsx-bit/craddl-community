<template>
  <div class="tab-number-container">
    <el-row :gutter="20">
      
      <!-- 左侧区域：价格与库存基础配置 -->
      <el-col :span="disableMultiSpec ? 24 : 7" class="left-panel">
        <el-divider content-position="left" class="custom-divider">基础价格与库存</el-divider>
        <div class="number-grid-section">
          <el-row :gutter="24">
            <el-col :span="disableMultiSpec ? 6 : 24" :xs="12" :sm="disableMultiSpec ? 8 : 24">
              <el-form-item label="售价" prop="price" :label-width="disableMultiSpec ? '' : '90px'">
                <el-input type="number" v-model="form.price" step="0.01" min="0" placeholder="0.00">
                  <template slot="append">元</template>
                </el-input>
              </el-form-item>
            </el-col>
            
            <el-col :span="disableMultiSpec ? 6 : 24" :xs="12" :sm="disableMultiSpec ? 8 : 24">
              <el-form-item label="原价" prop="origin_price" :label-width="disableMultiSpec ? '' : '90px'">
                <el-input type="number" v-model="form.origin_price" step="0.01" min="0" placeholder="0.00">
                  <template slot="append">元</template>
                </el-input>
              </el-form-item>
            </el-col>
            
            <el-col :span="disableMultiSpec ? 6 : 24" :xs="12" :sm="disableMultiSpec ? 8 : 24">
              <el-form-item label="成本价" prop="cost_price" :label-width="disableMultiSpec ? '' : '90px'">
                <el-input type="number" v-model="form.cost_price" step="0.01" min="0" placeholder="0.00">
                  <template slot="append">元</template>
                </el-input>
              </el-form-item>
            </el-col>

            <el-col :span="disableMultiSpec ? 6 : 24" :xs="12" :sm="disableMultiSpec ? 8 : 24">
              <el-form-item label="采购价" prop="purchase_price" :label-width="disableMultiSpec ? '' : '90px'">
                <el-input type="number" v-model="form.purchase_price" step="0.01" min="0" placeholder="0.00">
                  <template slot="append">元</template>
                </el-input>
                <div class="form-tip" v-if="disableMultiSpec">门店采购拿货价，开启「加入采购池」后门店按此价进货</div>
              </el-form-item>
            </el-col>

            <el-col :span="disableMultiSpec ? 6 : 24" :xs="12" :sm="disableMultiSpec ? 8 : 24" v-if="!(form.mch_id > 0)">
              <el-form-item label="加入采购池" prop="is_purchase" :label-width="disableMultiSpec ? '' : '90px'">
                <el-switch v-model="form.is_purchase" :active-value="1" :inactive-value="0" />
                <div class="form-tip" v-if="disableMultiSpec">开启后该商品出现在门店采购页，门店可按采购价向平台进货</div>
              </el-form-item>
            </el-col>

            <el-col :span="disableMultiSpec ? 6 : 24" :xs="12" :sm="disableMultiSpec ? 8 : 24" v-if="form.mch_id > 0">
              <el-form-item label="平台抽点" prop="concession_rate" :label-width="disableMultiSpec ? '' : '90px'">
                <el-input type="number" v-model="form.concession_rate" step="0.01" min="0" max="100" placeholder="留空默认全局">
                  <template slot="append">%</template>
                </el-input>
                <div class="form-tip" v-if="disableMultiSpec">单独设置该商品的平台服务费率，留空则继承商户全局费率</div>
              </el-form-item>
            </el-col>

            <el-col :span="disableMultiSpec ? 6 : 24" :xs="12" :sm="disableMultiSpec ? 8 : 24">
              <el-form-item label="当前库存" prop="stock" :label-width="disableMultiSpec ? '' : '90px'">
                <el-input type="number" v-model="form.stock" min="0" placeholder="0">
                  <template slot="append">件</template>
                </el-input>
              </el-form-item>
            </el-col>
            <el-col :span="8" class="hidden-sm-and-down" v-if="disableMultiSpec && !(form.mch_id > 0)"></el-col>

            <el-col :span="disableMultiSpec ? 6 : 24" :xs="12" :sm="disableMultiSpec ? 8 : 24">
              <el-form-item label="真实销量" prop="sales" :label-width="disableMultiSpec ? '' : '90px'">
                <el-input type="number" v-model="form.sales" step="1" :disabled="true" class="readonly-input">
                  <template slot="append">件</template>
                </el-input>
              </el-form-item>
            </el-col>
            
            <el-col :span="disableMultiSpec ? 6 : 24" :xs="12" :sm="disableMultiSpec ? 8 : 24">
              <el-form-item label="虚拟销量" prop="virtual_sales" :label-width="disableMultiSpec ? '' : '90px'">
                <el-input type="number" v-model="form.virtual_sales" step="1" min="0" placeholder="0">
                  <template slot="append">件</template>
                </el-input>
              </el-form-item>
            </el-col>
          </el-row>
        </div>
      </el-col>

      <!-- 右侧区域：SKU 规格配置生成器 -->
      <el-col :span="17" v-if="!disableMultiSpec" class="right-panel">
        <el-divider content-position="left" class="custom-divider">多规格 SKU 配置</el-divider>
        <!-- label-width="0" 让 SkuGenerator 获得全宽，更美观不局促 -->
        <el-form-item label-width="0" prop="specs_data">
          <sku-generator v-model="form.specs_data"></sku-generator>
        </el-form-item>
      </el-col>

    </el-row>
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

/* 左右分栏后的右侧边距和分割线 */
.right-panel {
  border-left: 1px dashed #ebeef5;
  padding-left: 24px;
  min-height: 500px;
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
