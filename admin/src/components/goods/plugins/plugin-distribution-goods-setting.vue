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
          <el-radio label="default">使用全局默认</el-radio>
          <el-radio label="custom">商品统一自定义</el-radio>
          <el-radio label="by_level">按会员等级独立配置</el-radio>
        </el-radio-group>
        <div class="form-tips">
          优先级：<b>按会员等级配置</b> > <b>商品统一自定义</b> > <b>会员等级分润配置</b> > <b>全局默认</b>。单层级留空则自动向下回退。
        </div>
      </el-form-item>

      <!-- A. 默认 -->
      <div v-if="form.commission_rule_type === 'default'">
        <el-form-item label="规则说明">
          <el-alert title="将使用 [分销中心 - 基础设置] 中的全局奖励比例"
            type="info" :closable="false" show-icon></el-alert>
        </el-form-item>
      </div>

      <!-- B. 商品统一自定义（所有分销商共用一套） -->
      <div v-if="form.commission_rule_type === 'custom'">
        <el-alert
          title="该比例将覆盖所有分销商（不分会员等级）对此商品的佣金结算"
          type="warning" :closable="false" show-icon style="margin-bottom: 16px; margin-left: 20px;"></el-alert>
        <template v-if="form.commission_rules && form.commission_rules.length > 0">
          <el-form-item v-for="(rule, index) in form.commission_rules" :key="index"
            :label="rule.name + ' 奖励比例'">
            <el-input type="number" v-model="rule.reward_rate"
              :placeholder="'留空则按全局 ' + (rule.global_rate || '0') + '%'"
              min="0" max="100" style="width: 260px">
              <template slot="append">%</template>
            </el-input>
            <span class="form-tips" style="margin-left: 12px;">
              全局默认：<b style="color:#E6A23C;">{{ rule.global_rate || '0' }}%</b>
            </span>
          </el-form-item>
        </template>
        <template v-else>
          <el-form-item>
            <span class="error-text">未检测到分销层级配置，请先在 [分销中心-基础设置] 中配置层级。</span>
          </el-form-item>
        </template>
      </div>

      <!-- C. 按分销商等级独立配置（矩阵表格：行=分销商等级，列=各层级比例） -->
      <div v-if="form.commission_rule_type === 'by_level'">
        <div v-if="!form.dist_levels || form.dist_levels.length === 0" class="tip-box">
          <el-alert type="warning" :closable="false" show-icon
            title="还没有可用的会员等级"
            description="请先到 [会员 - 会员等级] 中配置等级后再来按会员等级配置规则。"></el-alert>
        </div>

        <el-form-item v-else label="等级佣金矩阵">
          <table class="level-matrix">
            <thead>
              <tr>
                <th class="col-level">会员等级</th>
                <th v-for="(tpl, i) in form.level_template" :key="i">
                  {{ tpl.name }}
                  <div class="th-sub">全局默认 {{ tpl.global_rate || '0' }}%</div>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="lv in form.dist_levels" :key="lv.id">
                <td class="col-level">
                  <i class="el-icon-medal" style="color:#E6A23C; margin-right:4px;"></i>{{ lv.name }}
                </td>
                <td v-for="(rule, ridx) in getLevelRules(lv.id)" :key="ridx">
                  <el-input v-model="rule.reward_rate" type="number" min="0" max="100" size="small"
                    :placeholder="(rule.global_rate || '0') + '%'">
                    <template slot="append">%</template>
                  </el-input>
                </td>
              </tr>
            </tbody>
          </table>
          <div class="form-tips" style="margin-top: 8px;">
            每格留空 = 该层级按全局比例结算。例如"高级分销商"只填"一级分销"那格，二/三级仍按全局比例。
          </div>
        </el-form-item>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'plugin-distribution-goods-setting',
  props: {
    value: { type: Object, default: () => ({}) }
  },
  data() {
    return {
      form: {
        is_commission_open: 1, // 与后端一致:没配置过 = 开启(见 GoodsDistributionForm::getSetting)
        commission_rule_type: 'default',
        commission_rules: [],
        level_commission_rules: {},
        dist_levels: [],
        level_template: [],
      },
    };
  },
  watch: {
    value: {
      handler(val) {
        if (val && JSON.stringify(val) !== JSON.stringify(this.form)) {
          const next = JSON.parse(JSON.stringify(val));
          if (!next.commission_rules) next.commission_rules = [];
          if (!next.level_commission_rules || Array.isArray(next.level_commission_rules)) next.level_commission_rules = {};
          if (!next.dist_levels) next.dist_levels = [];
          if (!next.level_template) next.level_template = [];

          // [修复] 预生成所有当前分销商等级对应的规则条目，避免渲染期 $set 副作用
          // 同时裁掉已删除等级的 stale 条目（保存时不再带回后端污染）
          const cleaned = {};
          next.dist_levels.forEach(lv => {
            const key = String(lv.id);
            const existing = next.level_commission_rules[key];
            cleaned[key] = (next.level_template || []).map((t, i) => ({
              level: t.level,
              name: t.name,
              reward_rate: (existing && existing[i] && existing[i].reward_rate !== undefined)
                ? existing[i].reward_rate : '',
              global_rate: t.global_rate || '',
            }));
          });
          // 保留旧 key 的额外字段（如已删除等级）不做迁移 —— 旧数据自然流失
          next.level_commission_rules = cleaned;

          this.form = next;
        }
      },
      immediate: true,
      deep: true
    },
    form: {
      handler(val) { this.$emit('input', val); },
      deep: true
    }
  },
  methods: {
    /**
     * 取某个分销商等级的规则数组（已由 watch 预生成，直接读取无副作用）
     */
    getLevelRules(levelId) {
      return this.form.level_commission_rules[String(levelId)] || [];
    }
  }
};
</script>

<style scoped>
.nested-setting-box {
  background-color: #f8f8f8;
  border: 1px solid #e6e6e6;
  border-radius: 4px;
  padding: 20px 20px 0 0;
  margin-bottom: 20px;
  margin-left: 80px;
}
.form-tips { font-size: 12px; color: #909399; line-height: 1.5; }
.error-text { color: #F56C6C; font-size: 12px; }
.tip-box { padding: 0 20px 20px 20px; }
.level-matrix {
  width: 100%;
  border-collapse: collapse;
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  overflow: hidden;
}
.level-matrix th,
.level-matrix td {
  border: 1px solid #ebeef5;
  padding: 10px 12px;
  font-size: 13px;
  text-align: center;
  vertical-align: middle;
}
.level-matrix th {
  background: #f5f7fa;
  color: #606266;
  font-weight: 600;
  line-height: 1.4;
}
.level-matrix th .th-sub {
  font-size: 11px;
  color: #E6A23C;
  font-weight: normal;
  margin-top: 2px;
}
.level-matrix .col-level {
  text-align: left;
  width: 160px;
  background: #fafafa;
  color: #303133;
  font-weight: 500;
}
.level-matrix tbody tr:hover { background: #fafcff; }
</style>
