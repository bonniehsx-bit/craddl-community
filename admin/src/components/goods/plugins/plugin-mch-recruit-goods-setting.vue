<template>
  <div class="plugin-mch-recruit-goods-setting">

    <el-form-item label="开启商家推荐奖">
      <el-switch v-model="form.is_enable" :active-value="1" :inactive-value="0">
      </el-switch>
      <div class="form-tips">开启后，商家购买此商品时，其推荐商家可获得商家推荐奖。</div>
    </el-form-item>

    <div v-if="form.is_enable === 1" class="nested-setting-box">

      <el-form-item label="奖励比例">
        <el-radio-group v-model="form.rule_type">
          <el-radio :label="1">使用全局比例</el-radio>
          <el-radio :label="2">单独设置比例</el-radio>
        </el-radio-group>
      </el-form-item>

      <!-- 1. 全局比例展示 -->
      <div v-if="form.rule_type === 1">
        <el-form-item label="当前全局比例">
          <el-tag size="small" type="info">商家推荐奖：{{ globalSetting.recruit_rate }}%</el-tag>
          <div class="form-tips">如需修改，请前往 [多商户插件 - 设置 - 商家推荐奖]</div>
        </el-form-item>
      </div>

      <!-- 2. 单独设置 -->
      <div v-if="form.rule_type === 2">
        <el-form-item label="独立奖励比例">
          <el-input type="number" v-model="form.rate" min="0" max="100" style="width: 200px;">
            <template slot="append">%</template>
          </el-input>
          <div class="form-tips">仅本商品使用此比例。推荐关系：下单商家的直属上级（parent_id）也是商家时才发放。</div>
        </el-form-item>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'plugin-mch-recruit-goods-setting',
  props: {
    value: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      form: {
        is_enable: 0,
        rule_type: 1,
        rate: ''
      },
      globalSetting: {
        recruit_rate: '0'
      }
    };
  },
  watch: {
    value: {
      handler(val) {
        if (!val) return;
        // global_setting 不属于提交内容，分离出来
        const { global_setting, ...formData } = val;
        if (global_setting) {
          this.globalSetting = global_setting;
        }
        if (JSON.stringify(formData) !== JSON.stringify(this.form)) {
          this.form = Object.assign({}, this.form, formData);
        }
      },
      immediate: true,
      deep: true
    },
    form: {
      handler(val) {
        this.$emit('input', val);
      },
      deep: true
    }
  }
};
</script>

<style scoped>
.nested-setting-box {
  background-color: #f8f8f8;
  border: 1px solid #e6e6e6;
  border-radius: 4px;
  padding: 20px;
  margin-bottom: 20px;
  margin-left: 80px;
}

.form-tips {
  font-size: 12px;
  color: #909399;
  line-height: 1.5;
  margin-top: 5px;
}
</style>
