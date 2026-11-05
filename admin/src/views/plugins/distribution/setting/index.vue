<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never" v-loading="loading">
      <div slot="header" class="page-header">
        <div class="page-title">
          <i class="el-icon-s-tools"></i>
          <span>分销中心 · 基础设置</span>
        </div>
        <el-button type="primary" size="small" icon="el-icon-check" :loading="submitLoading" @click="save">
          保存配置
        </el-button>
      </div>

      <el-tabs v-model="activeName" class="content-tabs">
        <!-- 分销设置 -->
        <el-tab-pane label="分销设置" name="basic">
          <el-form v-if="form" :model="form" ref="formRef" label-width="220px" size="small" style="max-width: 680px;">
            <el-divider content-position="left">基础配置</el-divider>
            <el-form-item label="开启分销">
              <el-switch v-model="form.is_enable" :active-value="1" :inactive-value="0" />
              <div class="form-tip">关闭后，所有新的订单将不再计算和产生分销佣金。</div>
            </el-form-item>

            <template v-if="form.is_enable == 1">
              <el-form-item label="佣金结算节点">
                <el-radio-group v-model="form.settlement_timing" :disabled="Number(form.settle_delay_days) > 0">
                  <el-radio label="completed">订单完成后</el-radio>
                  <el-radio label="payment">支付完成后</el-radio>
                  <el-radio label="manual">手动结算</el-radio>
                </el-radio-group>
                <div class="form-tip">
                  决定分销佣金何时进入"已结算"状态，可以被发起提现。<br>
                  - <b>订单完成后</b>：更安全，可以避免为已退款的订单结算佣金。<br>
                  - <b>支付完成后</b>：激励性更强，但平台需承担风险。<br>
                  - <b>手动结算</b>：只计提不发放，由运营在【分销记录】页逐条确认。<br>
                  <span v-if="Number(form.settle_delay_days) > 0" style="color:#E6A23C;">
                    已设置延迟天数，本项不生效，统一由到期任务发放。
                  </span>
                </div>
              </el-form-item>

              <el-form-item label="佣金落账账户">
                <el-select v-model="form.payout_target" size="small" style="width:220px;">
                  <el-option value="price" label="收入（需提现）" />
                  <el-option value="balance" label="余额（可直接消费）" />
                  <el-option value="integral" label="积分" />
                </el-select>
                <div class="form-tip">
                  决定分销佣金发到分销员的哪个账户。<br>
                  该账户会随每一笔分润快照保存，<b>后台改了只影响之后新产生的佣金</b>，
                  已计提的老佣金仍按当初的账户结算与退款追回，不会串账。
                </div>
              </el-form-item>

              <el-form-item label="延迟结算天数">
                <el-input-number v-model="form.settle_delay_days" :min="0" :max="365" size="small" />
                <div class="form-tip">
                  0 = 不延迟（按上面的结算节点）；大于 0 则佣金计提后满 N 天由每日任务自动发放，
                  期间订单退款会自动作废该笔，不会发出去。
                </div>
              </el-form-item>

              <el-form-item label="佣金计算基数">
                <el-select v-model="form.commission_base" style="width: 220px;">
                  <el-option label="商品金额 = 原价小计（默认）" :value="0" />
                  <el-option label="利润（商品金额 − 成本）" :value="1" />
                  <el-option label="商品成本" :value="2" />
                  <el-option label="商品现价" :value="3" />
                </el-select>
                <div class="form-tip">
                  <b>⚠ 「商品金额」= 单价 × 件数，是原价小计，<u>不扣优惠券和积分抵扣</u></b>。
                  举例：商品 199 元、用券后实付 149，按此档仍按 <b>199</b> 计佣。
                  这是系统一贯口径（区域代理、经销商同此），等于优惠成本由平台承担、不影响推广积极性。<br>

                  同一套比例，算在不同的钱上结果差很远：按实付＝让利给团队，按利润＝只分赚到的那部分。<br>
                  成本取商品的成本价 × 件数；<b>按利润算时若成本高于实付，该商品行不产生佣金</b>（不会算出负数）。
                </div>
              </el-form-item>

              <el-form-item label="自购返佣">
                <el-switch v-model="form.self_buy" :active-value="1" :inactive-value="0" />
                <div class="form-tip">
                  开启后分销员自己下单也能拿佣金：<b>自己占一级，上级依次顺延</b>（原直推上级变二级），
                  整条链发出去的总比例不变，不是额外多发一份。<br>
                  被取消分销资格的人自购同样不返。
                </div>
              </el-form-item>

              <el-divider content-position="left">全局默认比例</el-divider>
              <el-form-item label="分销层级">
                <el-select v-model="form.level_count" style="width: 140px;" @change="handleLevelChange">
                  <el-option label="一级分销" :value="1" />
                  <el-option label="二级分销" :value="2" />
                  <el-option label="三级分销" :value="3" />
                </el-select>
                <div class="form-tip">最高支持三级分销。开启不同层级时，下方的奖励比例设置会自动调整。</div>
              </el-form-item>
              <el-form-item v-for="(level, index) in form.levels" :key="index" :label="level.name + '奖励比例'">
                <el-input type="number" v-model="level.reward_rate" min="0" max="100" style="width: 200px;">
                  <template slot="append">%</template>
                </el-input>
              </el-form-item>
            </template>
          </el-form>
        </el-tab-pane>

        <!-- 按会员等级分润（分销商等级 = 会员等级） -->
        <el-tab-pane v-if="form.is_enable == 1" label="按会员等级分润" name="member_level">
          <el-form label-width="150px" size="small" @submit.native.prevent>
            <el-form-item label="启用按等级分润">
              <el-switch v-model="form.is_dist_level_enabled" :active-value="1" :inactive-value="0" />
              <span style="margin-left:10px; color:#909399; font-size:12px;">
                关闭时，<b>下方按等级配置的比例一律不生效</b>，全部按上方「全局默认」发放。
              </span>
            </el-form-item>
          </el-form>

          <el-alert v-if="Number(form.is_dist_level_enabled) !== 1" type="warning" :closable="false" show-icon
            style="margin-bottom: 14px;"
            title="当前未启用按等级分润：下面填的比例不会生效，实际按上方「全局默认」发放。要让它生效，请打开上面的开关。" />

          <div class="form-tip" style="margin: 4px 0 14px;">
            分销商等级已统一为<b>会员等级</b>。可为每个会员等级单独配置各级佣金比例，
            <b>留空则该等级走上方「全局默认」</b>。佣金比例由<b>每个分销人「各自所属的会员等级」</b>决定——
            即一级上级按自己的等级取「一级」比例、二级上级按自己的等级取「二级」比例，依此类推；
            <b>某会员等级未在此配置（如 VIP）则按全局默认</b>（全局为 0 时即该等级不参与分销）。
          </div>
          <el-alert v-if="!memberLevels.length" type="info" :closable="false" show-icon
            title="暂无可用会员等级，请先到「会员 → 会员等级」中配置等级；未配置时所有分销商按全局默认比例发佣。" />
          <el-table v-else :data="memberLevels" border size="small" style="max-width: 760px;">
            <el-table-column label="会员等级" min-width="150">
              <template slot-scope="{ row }">
                <span style="font-weight:600;">{{ row.name }}</span>
                <el-tag size="mini" type="info" style="margin-left:6px;">权重 {{ row.level }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column v-for="(g, ti) in form.levels" :key="ti" :label="g.name + ' (%)'" width="150">
              <template slot-scope="{ row }">
                <el-input size="mini" type="number" :value="getRate(row.id, ti)"
                  :placeholder="'默认 ' + (g.reward_rate || '0')"
                  @input="v => setRate(row.id, ti, v)" />
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'distribution-setting',
  data() {
    return {
      loading: false,
      submitLoading: false,
      activeName: 'basic',
      memberLevels: [],
      form: {
        is_enable: 0,
        level_count: 1,
        settlement_timing: 'completed',
        levels: [{ level: 1, name: '一级分销', reward_rate: '10.00' }],
        // 保存是整表覆盖：这两项必须在 form 里，漏了每次保存都会被重置
        commission_base: 0,
        self_buy: 0,
        settle_delay_days: 0,
        payout_target: 'price',
        // 关闭时按等级配置不生效、一律走全局默认。默认 0 = 与历史行为一致：
        // 这个 key 曾在 2026-06-01 的重构里从界面删掉、但后端 6-02 又把依赖加了回来，
        // 于是之后新建的商城一直处于「关」且无从开启。默认填 0 保证任何商城的发放金额不变，
        // 真正修的是「让它可见、可控」，要不要开由运营自己决定。
        is_dist_level_enabled: 0,
        // 按会员等级覆盖比例：{ [会员level_id]: [{level,name,reward_rate}, ...] }
        level_rules: {},
      },
    }
  },
  created() { this.loadSetting(); },
  methods: {
    async loadSetting() {
      this.loading = true;
      try {
        const res = await this.$request({ url: 'plugin/distribution/mall/setting/index', method: 'get' });
        if (res.code === 0) {
          this.form = this.mergeRecursive(this.form, res.data.setting || {});
          // 开关统一成数字:存下来的可能是数字也可能是字符串("1"),和开关的值类型对不上时会一直显示成关着
          ['is_enable', 'self_buy', 'is_dist_level_enabled'].forEach(k => { this.form[k] = Number(this.form[k]) === 1 ? 1 : 0 })
          if (!this.form.level_rules || Array.isArray(this.form.level_rules)) this.form.level_rules = {};
          if (!res.data.setting.level_count && this.form.levels && this.form.levels.length > 0) {
            this.form.level_count = Math.min(3, this.form.levels.length);
          }
          // 后端存的是整数,选项也用数字;类型对不上时下拉框只显示一个光秃秃的「3」
          this.form.level_count = Number(this.form.level_count) || 1;
          this.memberLevels = res.data.member_levels || [];
        } else {
          this.$message.error(res.msg);
        }
      } catch (e) {
        console.error('加载分销配置失败', e);
      } finally {
        this.loading = false;
      }
    },
    save() {
      this.submitLoading = true;
      const payload = JSON.parse(JSON.stringify(this.form));
      // 清理：某会员等级各级比例全空则不下发，保持配置干净
      const lr = payload.level_rules || {};
      Object.keys(lr).forEach(k => {
        const rows = lr[k] || [];
        const hasVal = rows.some(r => r && r.reward_rate !== '' && r.reward_rate !== null && r.reward_rate !== undefined);
        if (!hasVal) delete lr[k];
      });
      payload.level_rules = lr;
      this.$request({ url: 'plugin/distribution/mall/setting/index', method: 'post', data: { form: payload } })
        .then(res => {
          if (res.code === 0) { this.$message.success('保存成功'); }
          else { this.$message.error(res.msg); }
        }).finally(() => { this.submitLoading = false; });
    },
    handleLevelChange(val) {
      const currentLen = this.form.levels.length;
      const levelNames = ['', '一', '二', '三'];
      if (val > currentLen) {
        for (let i = currentLen + 1; i <= val; i++) {
          this.form.levels.push({ level: i, name: `${levelNames[i]}级分销`, reward_rate: '0.00' });
        }
      } else if (val < currentLen) {
        this.form.levels.splice(val, currentLen - val);
      }
    },
    // 取某会员等级第 ti 级的比例
    getRate(levelId, ti) {
      const rows = this.form.level_rules[levelId];
      return (rows && rows[ti] && rows[ti].reward_rate !== undefined) ? rows[ti].reward_rate : '';
    },
    // 设某会员等级第 ti 级的比例（Vue2 需 $set 保证响应式）
    setRate(levelId, ti, val) {
      let rows = this.form.level_rules[levelId];
      if (!rows) {
        rows = this.form.levels.map((g, i) => ({ level: i + 1, name: g.name, reward_rate: '' }));
        this.$set(this.form.level_rules, levelId, rows);
      }
      // 保证长度与当前层级数对齐
      while (rows.length < this.form.levels.length) {
        rows.push({ level: rows.length + 1, name: this.form.levels[rows.length].name, reward_rate: '' });
      }
      this.$set(rows, ti, { level: ti + 1, name: this.form.levels[ti].name, reward_rate: val });
    },
    mergeRecursive(target, source) {
      for (const key in source) {
        if (Object.prototype.hasOwnProperty.call(source, key)) {
          if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
            if (!target[key]) Object.assign(target, { [key]: {} });
            this.mergeRecursive(target[key], source[key]);
          } else {
            Object.assign(target, { [key]: source[key] });
          }
        }
      }
      return target;
    }
  }
}
</script>

<style scoped lang="scss">
.app-container {
  height: calc(100vh - 85px); width: 100%; background-color: #f0f2f5;
  padding: 15px; box-sizing: border-box; display: flex; flex-direction: column; overflow: hidden;
}
.box-card {
  flex: 1; display: flex; flex-direction: column; border: none; border-radius: 6px;
  ::v-deep .el-card__header { padding: 10px 15px; border-bottom: 1px solid #ebeef5; }
  ::v-deep .el-card__body { flex: 1; display: flex; flex-direction: column; overflow: hidden; padding: 12px 15px; min-height: 0; }
}
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-title { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; color: #303133; i { color: var(--admin-accent-text); font-size: 18px; } }
.content-tabs {
  flex: 1; display: flex; flex-direction: column; min-height: 0; overflow: hidden;
  ::v-deep .el-tabs__content { flex: 1; overflow-y: auto; min-height: 0; padding-top: 8px; }
}
.form-tip { font-size: 12px; color: #909399; line-height: 1.5; margin-top: 4px; }
</style>
