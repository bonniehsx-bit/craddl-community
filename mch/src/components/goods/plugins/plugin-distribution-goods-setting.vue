<template>
  <div class="plugin-distribution-goods-setting">
    <!-- 1. 核心开关 -->
    <el-form-item label="是否开启分销">
      <el-switch
        v-model="form.is_commission_open"
        :active-value="1"
        :inactive-value="0">
      </el-switch>
      <div class="form-tips">开启后，购买此商品可产生分销佣金。</div>
    </el-form-item>

    <!-- 2. 详细配置区域 (仅开启时显示) -->
    <div v-if="form.is_commission_open === 1" class="nested-setting-box">

      <!-- 规则类型选择 -->
      <el-form-item label="佣金规则">
        <el-radio-group v-model="form.commission_rule_type">
          <el-radio label="default">使用全局默认规则</el-radio>
          <el-radio label="custom">单独设置规则</el-radio>
        </el-radio-group>
      </el-form-item>

      <!-- A. 默认规则展示 -->
      <div v-if="form.commission_rule_type === 'default'">
        <el-form-item label="默认规则说明">
          <el-alert
            title="将使用 [分销中心 - 基础设置] 中的全局奖励比例"
            type="info"
            :closable="false"
            show-icon>
          </el-alert>
        </el-form-item>
      </div>

      <!-- B. 自定义规则设置 -->
      <div v-if="form.commission_rule_type === 'custom'">
        <!-- 循环后端返回的规则列表 -->
        <template v-if="form.commission_rules && form.commission_rules.length > 0">
          <div
            v-for="(rule, index) in form.commission_rules"
            :key="index"
            class="rule-item"
          >
            <el-form-item :label="rule.name + ' 比例'">
              <el-input
                type="number"
                v-model="rule.reward_rate"
                placeholder="0"
                min="0"
                max="100"
                style="width: 200px"
              >
                <template slot="append">%</template>
              </el-input>
            </el-form-item>
          </div>
        </template>

        <!-- 异常兜底提示 -->
        <template v-else>
          <el-form-item>
            <span class="error-text">
              未检测到分销等级配置，请先在 [分销中心-基础设置] 中配置等级。
            </span>
          </el-form-item>
        </template>
      </div>

    </div>
  </div>
</template>

// plugin-distribution-goods-setting.vue 的 script 部分

<script>
export default {
  name: 'plugin-distribution-goods-setting',
  props: {
    value: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      form: {
        is_commission_open: 1, // 与后端一致:没配置过 = 开启(见 GoodsDistributionForm::getSetting)
        commission_rule_type: 'default',
        commission_rules: []
      }
    };
  },
  watch: {
    // 接收父组件数据 (回显)
    value: {
      handler(val) {
        if (val) {
          // [重要] 只有数据不一样时才赋值，防止死循环
          // 这里的 JSON.stringify 比对非常关键
          if (JSON.stringify(val) !== JSON.stringify(this.form)) {
            // 深拷贝赋值
            this.form = JSON.parse(JSON.stringify(val));

            // 兜底保证数组存在
            if (!this.form.commission_rules) {
              this.$set(this.form, 'commission_rules', []);
            }
          }
        }
      },
      immediate: true,
      deep: true
    },
    // 发送数据给父组件 (更新)
    form: {
      handler(val) {
        // [重要] 必须发出 input 事件
        this.$emit('input', val);
      },
      deep: true
    }
  }
};
</script>
<style scoped>
.nested-setting-box {
  background-color: #f8f8f8; /* 浅灰背景，体现层级 */
  border: 1px solid #e6e6e6;
  border-radius: 4px;
  padding: 20px 20px 0 0; /* 这里的 padding-right 0 是因为 el-form-item 自带 margin */
  margin-bottom: 20px;
  margin-left: 80px; /* 稍微缩进，对应父级 label 的宽度 */
}

.form-tips {
  font-size: 12px;
  color: #909399;
  line-height: 1.5;
}

.error-text {
  color: #F56C6C;
  font-size: 12px;
}

.rule-item {
  margin-bottom: 5px;
}
</style>
