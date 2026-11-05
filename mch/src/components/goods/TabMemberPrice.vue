<template>
  <div style="height: 700px; overflow-y: auto; padding: 10px;">
    <el-row>
      <el-col :span="16">

        <el-form-item label="开启会员价" prop="is_member_price">
          <el-radio-group v-model="form.is_member_price" @change="memberPriceChange">
            <el-radio :label="0">不开启</el-radio>
            <el-radio :label="1">开启</el-radio>
          </el-radio-group>
        </el-form-item>

        <div v-if="form.is_member_price == 1" style="background: #f7f7f7; padding: 20px; border-radius: 5px;">
          <div style="font-size: 12px; color: #999; line-height: 1.5; margin-bottom: 15px;">
            <span>
              适用多规格商品。例如输入 <b>9.5</b>，代表打95折。最终价格 = 对应规格原价 × 0.95
            </span>
          </div>

          <el-divider></el-divider>

          <div class="flex-row" style="font-weight: bold; display: flex; margin-bottom: 10px;">
            <div style="width: 150px;">等级名称</div>
            <div style="width: 250px;">折扣率 (0.1 ~ 10)</div>
          </div>

          <div class="flex-row" style="display: flex; margin-bottom: 15px; align-items: center;"
               v-if="form.level_price.length > 0"
               v-for="(item, i) in form.level_price" :key="i">

            <div style="width: 150px; line-height: 28px;">{{ item.level_name }}</div>

            <div style="width: 250px;">
              <el-input
                v-model="item.discount"
                type="number"
                min="0.1"
                max="10"
                step="0.1"
                placeholder="如 9.5"
                size="small"
                @input="validateDiscount(item)"
              >
                <template slot="append">折</template>
              </el-input>
            </div>
          </div>

          <el-button type="text" size="mini" @click="freshMemberPrice" style="color: #F56C6C;">
            <i class="el-icon-refresh"></i> 重置配置
          </el-button>
        </div>

      </el-col>
    </el-row>
  </div>
</template>

<script>
export default {
  name: "TabMemberPrice",
  props: {
    form: {
      type: Object,
      required: true
    },
    levelList: {
      type: Array,
      default: () => []
    }
  },

  methods: {
    memberPriceChange(val) {
      if (val === 1) {
        this.initPriceData();
      }
    },
    // 限制折扣输入范围
    validateDiscount(item) {
      let val = parseFloat(item.discount);
      if (val > 10) item.discount = 10;
      if (val < 0.1) item.discount = 0.1; // 修复了原版逻辑中 val < 0 却赋值 0.1 的小瑕疵
    },
    freshMemberPrice() {
      this.form.level_price = [];
      this.initPriceData();
    },
    initPriceData() {
      if (this.levelList.length === 0) {
        this.$message.warning('暂无会员等级数据，请先配置等级');
        this.form.is_member_price = 0;
        return;
      }

      if (this.form.level_price.length === 0) {
        let newPrices = [];
        this.levelList.forEach((item) => {
          if (item.id !== '0') {
            newPrices.push({
              level_id: item.id,
              level_name: item.name,
              level: item.level,
              discount: ''    // 仅保留 discount
            });
          }
        });
        this.form.level_price = newPrices;
      } else {
        this.form.level_price.forEach(item => {
          if (!item.hasOwnProperty('discount')) {
            this.$set(item, 'discount', '');
          }
        });
      }
    }
  }
}
</script>
