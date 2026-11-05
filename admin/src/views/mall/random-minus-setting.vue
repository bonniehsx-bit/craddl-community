<template>
  <div class="app-container">
    <el-card class="box-card" v-loading="loading">
      <div slot="header" class="clearfix">
        <span>订单随机减免设置</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="save" :loading="submiting">保存设置</el-button>
      </div>
      <div>
        <el-form size="mini" ref="form" :model="form" label-width="200px">
          <el-col :span="16">

            <el-form-item label="开启随机减免">
              <el-switch v-model="form.is_enabled" active-value="1" inactive-value="0"></el-switch>
              <div class="el-form-item__description">开启后，用户在下单支付时会随机减免一定金额，由平台补贴。</div>
            </el-form-item>

            <template v-if="form.is_enabled ==1">
              <el-divider content-position="left">阶梯规则设置</el-divider>

              <el-form-item label="金额阶梯">
                <div v-for="(tier, index) in form.tiers" :key="index" style="margin-bottom: 15px;">
                  <el-card shadow="never">
                    <div class="tier-item">
                      <span class="prefix-text">订单金额 ≤</span>
                      <el-input class="input-number" type="number" v-model="tier.order_amount_max" min="0.01">
                        <template slot="append">元</template>
                      </el-input>
                      <span class="prefix-text" style="margin-left: 20px;">，随机减免 ≤</span>
                      <el-input class="input-number" type="number" v-model="tier.minus_amount_max" min="0.01">
                        <template slot="append">元</template>
                      </el-input>
                      <el-button
                        v-if="form.tiers.length > 1"
                        class="delete-btn"
                        type="text"
                        @click="deleteTier(index)">删除</el-button>
                    </div>
                  </el-card>
                </div>
                <el-button size="mini" type="primary" @click="addTier">添加一个阶梯</el-button>
                <div class="el-form-item__description" style="margin-top: 10px;">
                  规则将从上到下进行匹配，请将订单金额从小到大排序。例如：第一条设置10元内，第二条设置100元内。
                </div>
              </el-form-item>
            </template>

          </el-col>
        </el-form>
      </div>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'random-minus-setting',
  data() {
    return {
      loading: false,
      submiting: false,
      form: {
        is_enabled: 0,
        tiers: [],
      }
    };
  },
  created() {
    this.getSetting();
  },
  methods: {
    getSetting() {
      this.loading = true;
      this.$request({
        // [注意] 这里的URL需要和您在控制器中定义的 action 匹配
        url: "/mall/mall/random-minus-setting",
      }).then(res => {
        this.loading = false;
        if (res.code === 0) {
          this.form = res.data.setting;
        } else {
          this.$message.error(res.msg);
        }
      }).catch(() => {
        this.loading = false;
      });
    },
    save() {
      // 保存前可以增加校验，例如检查金额是否从小到大排序
      for (let i = 0; i < this.form.tiers.length; i++) {
        if (parseFloat(this.form.tiers[i].order_amount_max) <= 0 || parseFloat(this.form.tiers[i].minus_amount_max) <= 0) {
          this.$message.error(`第 ${i+1} 条规则的金额必须大于0`);
          return;
        }
        if (i > 0 && parseFloat(this.form.tiers[i].order_amount_max) <= parseFloat(this.form.tiers[i-1].order_amount_max)) {
          this.$message.error(`第 ${i+1} 条规则的订单金额必须大于上一条`);
          return;
        }
      }

      this.submiting = true;
      this.$request({
        // [注意] 这里的URL需要和您在控制器中定义的 action 匹配
        url: "/mall/mall/random-minus-setting-save",
        method: 'post',
        data: { form: this.form }
      }).then(res => {
        this.submiting = false;
        if (res.code === 0) {
          this.$message.success(res.msg);
        } else {
          this.$message.error(res.msg);
        }
      }).catch(() => {
        this.submiting = false;
      });
    },
    addTier() {
      const lastTier = this.form.tiers.length > 0 ? this.form.tiers[this.form.tiers.length - 1] : null;
      this.form.tiers.push({
        // 新增时给一个比上一条稍大的默认值，提升体验
        order_amount_max: lastTier ? (parseFloat(lastTier.order_amount_max) * 2).toFixed(2) : '10.00',
        minus_amount_max: lastTier ? (parseFloat(lastTier.minus_amount_max) * 2).toFixed(2) : '0.10'
      });
    },
    deleteTier(index) {
      this.form.tiers.splice(index, 1);
    }
  }
};
</script>

<style scoped>
.el-form-item__description {
  font-size: 12px;
  color: #999;
}
.tier-item {
  display: flex;
  align-items: center;
}
.prefix-text {
  color: #606266;
  margin-right: 10px;
}
.input-number {
  width: 180px;
}
.delete-btn {
  margin-left: auto;
  color: #F56C6C;
}
</style>
