<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="page-header">
        <div class="page-title">
          <i class="el-icon-s-tools"></i>
          <span>提现设置</span>
        </div>
        <el-button v-if="activeTab === 'setting' || activeTab === 'transfer'" type="primary" size="small" :loading="saving" @click="save">保存</el-button>
      </div>

      <el-tabs v-model="activeTab" class="content-tabs">
        <!-- 提现设置 -->
        <el-tab-pane label="提现设置" name="setting">
          <el-form v-loading="loading" :model="form" label-width="120px" size="small" style="max-width: 1080px;">
            <el-form-item label="全局提现开关">
              <el-switch v-model="form.enabled" :active-value="1" :inactive-value="0" />
              <span class="tip">关闭后 C 端提现页停用、所有渠道不可提；不影响已提交的提现单。</span>
            </el-form-item>
            <el-form-item label="各来源提现入口">
              <div class="entry-switches">
                <span class="entry-item" v-for="opt in assetOptions" :key="opt.key">
                  <span>{{ opt.plugin_label ? opt.plugin_label + ' · ' + opt.label : opt.label }}</span>
                  <el-switch v-model="form.entries[opt.key]" :active-value="1" :inactive-value="0" />
                </span>
              </div>
              <div class="tip">控制「余额/佣金/积分中心」页的「提现」按钮是否显示，独立于老 cash 配置。关闭则该资金来源不展示提现入口。</div>
            </el-form-item>
            <el-form-item label="提现须知">
              <el-input
                v-model="form.notice"
                type="textarea"
                :rows="5"
                placeholder="展示在用户提现页底部，如：到账时间 T+1 工作日；单笔最低 10 元；如有疑问请联系客服…"
                maxlength="1000"
                show-word-limit
              />
            </el-form-item>
            <el-form-item label="每日提现次数上限">
              <el-input-number v-model="form.day_limit_count" :min="0" :step="1" />
              <span class="tip">跨所有渠道统一计数，0=不限。如设 1，则用户一天总共只能成功提现一次（渠道里的「当日提现次数」是按单个渠道单独算的，这里是全局合计，两者都生效）。</span>
            </el-form-item>
            <el-form-item label="次数上限拦截文案">
              <el-input v-model="form.day_limit_tip" type="textarea" :rows="2" maxlength="100" show-word-limit
                placeholder="达到每日次数上限时的提示文案" style="max-width: 520px;" />
              <span class="tip">用户当日提现次数达上限、再次提交时的提示文案。可用占位符 <b>{count}</b> 自动替换为上限次数。留空恢复默认。</span>
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <!-- 转赠设置 -->
        <el-tab-pane label="转赠设置" name="transfer">
          <el-form v-loading="loading" :model="form" label-width="140px" size="small" style="max-width: 720px;">
            <el-alert
              type="info"
              :closable="false"
              show-icon
              title="余额 / 积分转赠开关。用户在「余额中心 / 积分中心」是否显示「转赠」按钮，由此控制。支付密码沿用「商城设置」的转账密码开关。"
              style="margin-bottom: 16px;"
            />
            <el-divider content-position="left">余额转赠</el-divider>
            <el-form-item label="开启余额转赠">
              <el-switch v-model="form.finance.balance_transfer_enabled" :active-value="1" :inactive-value="0" />
              <span class="tip">关闭后余额中心不显示转赠入口，接口也拒绝转赠。</span>
            </el-form-item>

            <el-divider content-position="left">积分转赠</el-divider>
            <el-form-item label="开启积分转赠">
              <el-switch v-model="form.finance.integral_transfer_enabled" :active-value="1" :inactive-value="0" />
              <span class="tip">关闭后积分中心不显示转赠入口，接口也拒绝转赠。</span>
            </el-form-item>
            <el-form-item label="积分转赠手续费" v-if="form.finance.integral_transfer_enabled === 1">
              <el-input v-model="form.finance.integral_transfer_fee_rate" type="number" min="0" max="100" style="width: 200px;">
                <template slot="append">%</template>
              </el-input>
              <div class="tip">例：设 20%，A 向 B 转赠 100 积分，A 实扣 100 + 100×20% = 120 积分。0=不收手续费。</div>
            </el-form-item>

            <el-divider content-position="left">余额转积分</el-divider>
            <el-form-item label="开启余额转积分">
              <el-switch v-model="form.finance.balance_to_integral_enabled" :active-value="1" :inactive-value="0" />
              <span class="tip">开启后用户可用余额兑换积分。</span>
            </el-form-item>
            <el-form-item label="兑换比例" v-if="form.finance.balance_to_integral_enabled === 1">
              <span class="ratio-line">1 元余额 =
                <el-input v-model="form.finance.one_rmb_to_integral" type="number" min="0" style="width: 160px; margin: 0 8px;" />
                积分
              </span>
              <div class="tip">例：设 10，用户用 5 元余额可兑换 50 积分。</div>
            </el-form-item>

            <el-divider content-position="left">显示</el-divider>
            <el-form-item label="隐藏余额明细">
              <el-switch v-model="form.finance.hide_balance_detail" :active-value="1" :inactive-value="0" />
              <span class="tip">开启后，余额中心不展示「余额明细」列表。</span>
            </el-form-item>
          </el-form>
        </el-tab-pane>

        <!-- 操作说明 -->
        <el-tab-pane label="操作说明" name="guide">
          <div class="guide-pane">
            <el-alert
              type="info"
              :closable="false"
              show-icon
              title="运营人员必读：下面是提现插件从配置到打款的完整流程"
              style="margin-bottom: 16px;"
            />
            <el-steps direction="vertical" :active="6" space="64px">
              <el-step title="1. 配置提现渠道" description="「提现渠道」新建渠道：选打款网关 + 到账方式，勾选「适用用户（普通用户/商户）」和「资金来源（余额/奖励/积分）」，设费率/限额/是否审核。" />
              <el-step title="2. 控制谁能用、能提什么" description="C 端显示完全由渠道驱动：只建余额渠道→用户就只看到余额提现；商户专属渠道只有商户可见。无需额外开关。" />
              <el-step title="3. 用户填收款资料" description="提现到银行卡/支付宝/微信时，用户需先在 App 填收款账户（走收款资料页）。资料不全会被拦截并提示去完善。提现到余额无需资料。" />
              <el-step title="4. 是否审核（渠道里的“人工审核”开关）" description="开启=提现单先进「待审核」，后台审核通过才打款；关闭=免审核直接进「待打款」。特别地：网关选“余额转账”（提现到商城余额）且关闭审核时，用户提现即时到账（内部转账一步到位，无需审核也无需打款）；外部网关免审后仍需后台触发打款。" />
              <el-step title="5. 审核与打款" description="「提现记录」里：待审核单可通过/驳回（驳回自动退回资产）；通过后「打款」调用网关；手动打款显示「打款中」，线下转账后点「确认到账」并可上传打款凭证。" />
              <el-step title="6. 异步到账自动收口" description="走第三方 API 网关（如快付）的提现，系统每 3 分钟自动查询打款结果，自动转「已到账/已退回」，无需人工盯。手动打款仍需后台确认。" />
            </el-steps>
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'WithdrawSetting',
  data() {
    return {
      activeTab: 'setting',
      loading: false,
      saving: false,
      assetOptions: [],
      form: { enabled: 1, notice: '', entries: {}, day_limit_count: 0, day_limit_tip: '', finance: this.getDefaultFinance() }
    }
  },
  mounted() { this.getData() },
  methods: {
    // finance 全量默认值：与后端 SettingForm::FINANCE_DEFAULTS 对齐。
    // 整体载入/回存，本页只暴露转赠 UI，其余键(余额转积分/佣金自动转余额/隐藏余额明细)原样带回，不被覆盖。
    getDefaultFinance() {
      return {
        balance_transfer_enabled: 0,
        integral_transfer_enabled: 0,
        integral_transfer_fee_rate: '0',
        balance_to_integral_enabled: 0,
        one_rmb_to_integral: 1,
        hide_balance_detail: 0
      }
    },
    getData() {
      this.loading = true
      this.$request({ url: '/plugin/withdraw/admin/setting/index', method: 'get' }).then(res => {
        this.loading = false
        if (res.code === 0) {
          this.assetOptions = res.data.asset_options || []
          const e = res.data.entries || {}
          const entries = {}
          this.assetOptions.forEach(o => { entries[o.key] = Number(e[o.key] ?? 1) })
          const finance = Object.assign(this.getDefaultFinance(), res.data.finance || {})
          finance.balance_transfer_enabled = Number(finance.balance_transfer_enabled)
          finance.integral_transfer_enabled = Number(finance.integral_transfer_enabled)
          finance.balance_to_integral_enabled = Number(finance.balance_to_integral_enabled)
          finance.hide_balance_detail = Number(finance.hide_balance_detail)
          this.form = {
            enabled: Number(res.data.enabled),
            notice: res.data.notice || '',
            entries,
            day_limit_count: Number(res.data.day_limit_count || 0),
            day_limit_tip: res.data.day_limit_tip || '',
            finance
          }
        } else this.$message.error(res.msg)
      }).catch(() => { this.loading = false })
    },
    save() {
      this.saving = true
      this.$request({ url: '/plugin/withdraw/admin/setting/save', method: 'post', data: this.form }).then(res => {
        this.saving = false
        if (res.code === 0) this.$message.success('保存成功')
        else this.$message.error(res.msg)
      }).catch(() => { this.saving = false })
    }
  }
}
</script>

<style scoped lang="scss">
/* ── 标准页面架构 ─────────────────────────────── */
.app-container {
  height: calc(100vh - 85px);
  width: 100%;
  background-color: #f0f2f5;
  padding: 15px;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.box-card {
  flex: 1;
  display: flex;
  flex-direction: column;
  border: none;
  border-radius: 6px;

  ::v-deep .el-card__header {
    padding: 10px 15px;
    border-bottom: 1px solid #ebeef5;
  }

  ::v-deep .el-card__body {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    padding: 12px 15px;
    min-height: 0;
  }
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.page-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 15px;
  font-weight: 600;
  color: #303133;
  i { color: var(--admin-accent-text); font-size: 18px; }
}

/* Tab 内容区撑满剩余空间并内部滚动 */
.content-tabs {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;

  ::v-deep .el-tabs__content {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
    padding-top: 4px;
  }
}

/* ── 业务样式 ────────────────────────────────── */
.guide-pane ::v-deep .el-step__description { padding-right: 10px; font-size: 13px; }
.tip { margin-left: 12px; color: #909399; font-size: 12px; }
.ratio-line { display: inline-flex; align-items: center; color: #606266; }
.entry-switches { display: flex; gap: 28px; }
.entry-item { display: inline-flex; align-items: center; gap: 8px; }
.entry-item > span { color: #606266; }
</style>
