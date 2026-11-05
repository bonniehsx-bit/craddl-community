<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="page-header">
        <div class="page-title">
          <i class="el-icon-setting"></i>
          <span>提现渠道</span>
        </div>
        <el-button type="primary" size="small" icon="el-icon-plus" @click="handleCreate">新建渠道</el-button>
      </div>

      <el-form :inline="true" size="small" :model="searchForm" @submit.native.prevent class="filter-form shrink-0">
          <el-form-item label="渠道名称">
            <el-input
              v-model="searchForm.keyword"
              placeholder="渠道名称"
              clearable
              style="width: 180px;"
              @clear="search"
              @keyup.enter.native="search"
            />
          </el-form-item>
          <el-form-item label="用户类型">
            <el-select v-model="searchForm.user_type" placeholder="全部" clearable style="width: 140px;" @change="search">
              <el-option label="普通用户" value="user" />
              <el-option label="商户" value="merchant" />
            </el-select>
          </el-form-item>
          <el-form-item label="状态">
            <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 120px;" @change="search">
              <el-option label="启用" :value="1" />
              <el-option label="停用" :value="0" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" icon="el-icon-search" @click="search">查询</el-button>
            <el-button icon="el-icon-refresh" @click="resetSearch">重置</el-button>
          </el-form-item>
        </el-form>

      <div class="table-wrapper flex-1">
        <div class="absolute-table-container">
          <el-table v-loading="loading" :data="list" border height="100%" @row-dblclick="handleEdit">
        <el-table-column label="渠道名称" min-width="160">
          <template slot-scope="{ row }">
            <div class="ch-name">
              <span class="dot" :style="{ background: row.icon ? '' : '#dcdfe6' }" />
              <span>{{ row.name }}</span>
            </div>
            <div v-if="row.subtitle" class="ch-sub">{{ row.subtitle }}</div>
          </template>
        </el-table-column>
        <el-table-column label="打款网关" min-width="150">
          <template slot-scope="{ row }">
            {{ fundName(row.fund_class) }}
          </template>
        </el-table-column>
        <el-table-column label="适用用户" width="140">
          <template slot-scope="{ row }">
            <el-tag v-for="t in row.user_types" :key="t" size="mini" style="margin-right: 4px;">
              {{ userTypeText(t) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="资金来源" width="170">
          <template slot-scope="{ row }">
            <el-tag v-for="a in row.asset_types" :key="a" size="mini" type="info" style="margin-right: 4px;">
              {{ assetText(a) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="费率/税率" width="110">
          <template slot-scope="{ row }">
            {{ ratePct(row.fee_rate) }} / {{ ratePct(row.tax_rate) }}
          </template>
        </el-table-column>
        <el-table-column label="到账时效" width="110" prop="arrival_desc" />
        <el-table-column label="审核" width="70" align="center">
          <template slot-scope="{ row }">
            <el-tag size="mini" :type="row.need_audit ? 'warning' : 'success'">
              {{ row.need_audit ? '需审' : '免审' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="排序" width="70" prop="sort" align="center" />
        <el-table-column label="状态" width="80" align="center">
          <template slot-scope="{ row }">
            <el-switch :value="row.status === 1" @change="toggleStatus(row)" />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="130" align="center" fixed="right">
          <template slot-scope="{ row }">
            <el-button type="text" @click="handleEdit(row)">编辑</el-button>
            <el-button type="text" style="color: #f56c6c;" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
          </el-table>
        </div>
      </div>

      <div class="pagination-container shrink-0">
        <el-pagination
          background
          layout="total, prev, pager, next, jumper"
          :total="pagination.total_count"
          :current-page="pagination.current_page"
          :page-size="pagination.page_size"
          @current-change="pageChange"
        />
      </div>
    </el-card>

    <!-- 编辑 Drawer：点遮罩关闭 -->
    <el-drawer
      :title="form.id ? '编辑渠道' : '新建渠道'"
      :visible.sync="drawer.visible"
      size="920px"
      :wrapper-closable="true"
      :destroy-on-close="true"
    >
      <div v-loading="drawer.loading" class="drawer-body">
        <el-form ref="form" :model="form" :rules="rules" label-width="110px" size="small">
          <el-divider content-position="left">基础信息</el-divider>
          <el-form-item label="渠道名称" prop="name">
            <el-input v-model="form.name" placeholder="如：提现到银行卡" maxlength="50" show-word-limit />
          </el-form-item>
          <el-form-item label="副标题">
            <el-input v-model="form.subtitle" placeholder="展示给用户的说明文字" />
          </el-form-item>
          <el-form-item label="图标">
            <single-image-selector v-model="form.icon" />
          </el-form-item>
          <el-form-item label="主题色">
            <el-input v-model="form.color" placeholder="如 #07c160（可选）" style="width: 200px;" />
          </el-form-item>
          <el-form-item label="排序">
            <el-input-number v-model="form.sort" :min="0" :step="1" />
          </el-form-item>

          <el-divider content-position="left">打款网关（解决“资料配网关”）</el-divider>
          <el-form-item label="打款网关" prop="fund_class">
            <el-select
              v-model="form.fund_class"
              placeholder="选择执行打款的网关"
              style="width: 100%;"
              @change="onFundChange"
            >
              <el-option v-for="f in funds" :key="f.class" :label="f.name" :value="f.class">
                <span>{{ f.name }}</span>
                <span style="float: right; color: #909399; font-size: 12px;">{{ f.channels.join('/') }}</span>
              </el-option>
            </el-select>
          </el-form-item>
          <el-form-item label="到账方式" prop="destination">
            <el-select v-model="form.destination" placeholder="选择网关后可选" style="width: 100%;" :disabled="!form.fund_class">
              <el-option v-for="d in destinationOptions" :key="d" :label="destText(d)" :value="d" />
            </el-select>
            <div class="tip">该方式必须在所选网关支持范围内（payout 时传给网关）</div>
          </el-form-item>
          <el-form-item label="所需资料">
            <div v-if="onboardingFields.length === 0" class="tip">该网关无需额外资料，或选择网关后加载</div>
            <el-table v-else :data="onboardingFields" border size="mini">
              <el-table-column label="字段" prop="label" min-width="120" />
              <el-table-column label="标识" prop="key" min-width="120" />
              <el-table-column label="类型" prop="type" width="100" />
              <el-table-column label="必填" width="70" align="center">
                <template slot-scope="{ row }">
                  <el-tag size="mini" :type="row.required ? 'danger' : 'info'">
                    {{ row.required ? '是' : '否' }}
                  </el-tag>
                </template>
              </el-table-column>
            </el-table>
            <div class="tip" style="margin-top: 6px;">
              资料字段由所选网关定义（继承网关默认）。如需按渠道裁剪，后续版本支持覆盖配置。
            </div>
          </el-form-item>

          <el-divider content-position="left">适用范围（解决“谁能用”）</el-divider>
          <el-form-item label="适用用户" prop="user_types">
            <el-checkbox-group v-model="form.user_types">
              <el-checkbox label="user">普通用户</el-checkbox>
              <el-checkbox label="merchant">商户</el-checkbox>
            </el-checkbox-group>
            <div class="tip">哪类账号能在 App 看到并使用本渠道。只勾商户=仅商户可见。</div>
          </el-form-item>
          <el-form-item label="资金来源" prop="asset_types">
            <el-checkbox-group v-model="form.asset_types">
              <el-checkbox v-for="opt in assetOptions" :key="opt.key" :label="opt.key">{{ opt.plugin_label ? opt.plugin_label + ' · ' + opt.label : opt.label }}</el-checkbox>
            </el-checkbox-group>
            <div class="tip">本渠道支持从哪些钱包提现。C 端「只显示有可用渠道的资金来源」——只建余额渠道，用户就只看到余额提现。</div>
          </el-form-item>

          <el-divider content-position="left">费规与限额</el-divider>
          <el-form-item label="手续费率">
            <el-input-number v-model="form.fee_rate" :min="0" :max="1" :step="0.001" :precision="4" />
            <span class="tip"> 小数，如 0.006 = 0.6%</span>
          </el-form-item>
          <el-form-item label="税率">
            <el-input-number v-model="form.tax_rate" :min="0" :max="1" :step="0.001" :precision="4" />
          </el-form-item>
          <el-form-item label="最小提现额">
            <el-input-number v-model="form.min_amount" :min="0" :step="1" :precision="2" />
            <span class="tip"> 元</span>
          </el-form-item>
          <el-form-item label="单笔上限">
            <el-input-number v-model="form.max_amount" :min="0" :step="1" :precision="2" />
            <span class="tip"> 元，0=不限</span>
          </el-form-item>
          <el-form-item label="提现倍数">
            <el-input-number v-model="form.multiple" :min="0" :step="1" :precision="2" />
            <span class="tip"> 0=不限，如 100=只能提100的整数倍</span>
          </el-form-item>
          <el-form-item label="单日限额">
            <el-input-number v-model="form.day_limit_amount" :min="0" :step="100" :precision="2" />
            <span class="tip"> 元，0=不限</span>
          </el-form-item>
          <el-form-item label="单日次数">
            <el-input-number v-model="form.day_limit_count" :min="0" :step="1" />
            <span class="tip"> 0=不限</span>
          </el-form-item>
          <el-form-item label="到账时效">
            <el-input v-model="form.arrival_desc" placeholder="如 T+1工作日 / 实时到账" style="width: 240px;" />
          </el-form-item>
          <el-form-item label="人工审核">
            <el-switch v-model="form.need_audit" :active-value="1" :inactive-value="0" />
            <span class="tip"> {{ form.need_audit ? '需审核' : '免审核' }}</span>
            <div class="tip" style="line-height:1.7;margin-top:4px;">
              · <b>开启</b>：提现单先进「待审核」，后台审核通过后才进入打款<br>
              · <b>关闭</b>：免审核，直接进「待打款」由后台打款；<br>
              &nbsp;&nbsp;其中<b style="color:#67c23a;">提现到商城余额（网关选“余额转账”）</b>会<b style="color:#67c23a;">即时到账</b>——内部转账一步到位，无需审核也无需打款；<br>
              &nbsp;&nbsp;外部网关（银行卡/支付宝等）免审后仍需后台触发打款，保留人工出金控制点。
            </div>
          </el-form-item>
          <el-form-item label="状态">
            <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
          </el-form-item>
          <el-form-item label="提现规则">
            <el-input v-model="form.rules" type="textarea" :rows="6"
              placeholder="一行一条，留空则使用系统默认规则（会根据本渠道的起提额/费率/到账时效自动生成）" />
            <div class="form-tip">
              展示在 C 端提现页底部。<strong>留空</strong>=使用系统默认规则；<strong>填写后将全量覆盖</strong>默认规则，
              起提额、手续费、到账时效等条款需自行写入。
            </div>
          </el-form-item>
          <el-form-item label="备注">
            <el-input v-model="form.remark" type="textarea" :rows="2" />
          </el-form-item>
        </el-form>
      </div>

      <div class="drawer-footer">
        <el-button @click="drawer.visible = false">取消</el-button>
        <el-button type="primary" :loading="drawer.saving" @click="submit">保存</el-button>
      </div>
    </el-drawer>
  </div>
</template>

<script>
export default {
  name: 'WithdrawChannelIndex',
  data() {
    return {
      loading: false,
      list: [],
      funds: [],
      assetOptions: [], // 资金来源选项(核心4 + 授权插件可提现钱包),loadFunds 时拉取
      searchForm: { keyword: '', user_type: '', status: '' },
      pagination: { current_page: 1, page_size: 20, total_count: 0 },
      onboardingFields: [],
      drawer: { visible: false, loading: false, saving: false },
      form: this.emptyForm(),
      rules: {
        name: [{ required: true, message: '请输入渠道名称', trigger: 'blur' }],
        fund_class: [{ required: true, message: '请选择打款网关', trigger: 'change' }],
        destination: [{ required: true, message: '请选择到账方式', trigger: 'change' }],
        user_types: [{ type: 'array', required: true, message: '请至少选择一种适用用户', trigger: 'change' }],
        asset_types: [{ type: 'array', required: true, message: '请至少选择一种资金来源', trigger: 'change' }]
      }
    }
  },
  computed: {
    destinationOptions() {
      const f = this.funds.find(x => x.class === this.form.fund_class)
      return f ? f.channels : []
    }
  },
  mounted() {
    this.loadFunds()
    this.getList()
  },
  methods: {
    destText(d) { return { bank: '银行卡', alipay: '支付宝', wechat: '微信零钱', balance: '商城余额' }[d] || d },
    emptyForm() {
      return {
        id: 0, name: '', subtitle: '', icon: '', color: '', sort: 0,
        fund_class: '', destination: '', fund_config: {}, onboarding_override: [],
        user_types: [], asset_types: [],
        fee_rate: 0, tax_rate: 0, min_amount: 0, max_amount: 0, multiple: 0,
        day_limit_amount: 0, day_limit_count: 0, arrival_desc: '',
        need_audit: 1, status: 1, remark: '', rules: ''
      }
    },

    // ---------- 字典 ----------
    fundName(cls) {
      const f = this.funds.find(x => x.class === cls)
      return f ? f.name : (cls || '-')
    },
    userTypeText(t) { return { user: '普通用户', merchant: '商户' }[t] || t },
    assetText(a) { const o = this.assetOptions.find(x => x.key === a); return o ? o.label : a },
    ratePct(v) { return (Number(v) * 100).toFixed(2) + '%' },

    // ---------- 列表 ----------
    loadFunds() {
      this.$request({ url: '/plugin/withdraw/admin/channel/funds', method: 'get' }).then(res => {
        if (res.code === 0) {
          this.funds = res.data.funds || []
          this.assetOptions = res.data.asset_options || []
        }
      })
    },
    getList() {
      this.loading = true
      this.$request({
        url: '/plugin/withdraw/admin/channel/index',
        method: 'get',
        data: {
          page: this.pagination.current_page,
          limit: this.pagination.page_size,
          keyword: this.searchForm.keyword,
          user_type: this.searchForm.user_type,
          status: this.searchForm.status
        }
      }).then(res => {
        this.loading = false
        if (res.code === 0) {
          this.list = res.data.list || []
          if (res.data.pagination) this.pagination = res.data.pagination
        } else {
          this.$message.error(res.msg)
        }
      }).catch(() => { this.loading = false })
    },
    search() { this.pagination.current_page = 1; this.getList() },
    resetSearch() { this.searchForm = { keyword: '', user_type: '', status: '' }; this.search() },
    pageChange(p) { this.pagination.current_page = p; this.getList() },

    // ---------- 网关资料 ----------
    onFundChange(cls) {
      // 切换网关后，校验/重置到账方式（仅保留新网关支持的）
      const opts = this.destinationOptions
      if (!opts.includes(this.form.destination)) {
        this.form.destination = opts.length === 1 ? opts[0] : ''
      }
      this.loadOnboarding(cls)
    },
    loadOnboarding(cls) {
      if (!cls) { this.onboardingFields = []; return }
      this.$request({
        url: '/plugin/withdraw/admin/channel/funds',
        method: 'get',
        data: { fund_class: cls }
      }).then(res => {
        if (res.code === 0) this.onboardingFields = res.data.onboarding_fields || []
      })
    },

    // ---------- 增改 ----------
    handleCreate() {
      this.form = this.emptyForm()
      this.onboardingFields = []
      this.drawer.visible = true
      this.$nextTick(() => this.$refs.form && this.$refs.form.clearValidate())
    },
    handleEdit(row) {
      this.drawer.visible = true
      this.drawer.loading = true
      this.onboardingFields = []
      this.$request({
        url: '/plugin/withdraw/admin/channel/detail',
        method: 'get',
        data: { id: row.id }
      }).then(res => {
        this.drawer.loading = false
        if (res.code === 0) {
          const d = res.data
          this.form = {
            id: d.id, name: d.name, subtitle: d.subtitle, icon: d.icon, color: d.color, sort: d.sort,
            fund_class: d.fund_class, destination: d.destination || '', fund_config: d.fund_config || {}, onboarding_override: d.onboarding_override || [],
            user_types: d.user_types || [], asset_types: d.asset_types || [],
            fee_rate: Number(d.fee_rate), tax_rate: Number(d.tax_rate),
            min_amount: Number(d.min_amount), max_amount: Number(d.max_amount), multiple: Number(d.multiple),
            day_limit_amount: Number(d.day_limit_amount), day_limit_count: Number(d.day_limit_count),
            arrival_desc: d.arrival_desc, need_audit: d.need_audit, status: d.status, remark: d.remark, rules: d.rules || ''
          }
          this.onboardingFields = d.fund_onboarding_fields || []
          this.$nextTick(() => this.$refs.form && this.$refs.form.clearValidate())
        } else {
          this.$message.error(res.msg)
          this.drawer.visible = false
        }
      }).catch(() => { this.drawer.loading = false })
    },
    submit() {
      this.$refs.form.validate(valid => {
        if (!valid) return
        this.drawer.saving = true
        this.$request({
          url: '/plugin/withdraw/admin/channel/save',
          method: 'post',
          data: this.form
        }).then(res => {
          this.drawer.saving = false
          if (res.code === 0) {
            this.$message.success('保存成功')
            this.drawer.visible = false
            this.getList()
          } else {
            this.$message.error(res.msg)
          }
        }).catch(() => { this.drawer.saving = false })
      })
    },
    toggleStatus(row) {
      this.$request({
        url: '/plugin/withdraw/admin/channel/switch',
        method: 'post',
        data: { id: row.id }
      }).then(res => {
        if (res.code === 0) { row.status = res.data.status } else this.$message.error(res.msg)
      })
    },
    handleDelete(row) {
      this.$confirm(`确认删除渠道「${row.name}」？`, '提示', { type: 'warning' }).then(() => {
        this.$request({
          url: '/plugin/withdraw/admin/channel/delete',
          method: 'post',
          data: { id: row.id }
        }).then(res => {
          if (res.code === 0) { this.$message.success('删除成功'); this.getList() } else this.$message.error(res.msg)
        })
      }).catch(() => { })
    }
  }
}
</script>

<style scoped lang="scss">
/* ── 标准列表页布局 ─────────────────────────────── */
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

.filter-form {
  flex-shrink: 0;
  margin-bottom: 8px;
  ::v-deep .el-form-item { margin-bottom: 8px; }
}

.shrink-0 { flex-shrink: 0; }
.flex-1   { flex: 1; }

.table-wrapper {
  flex: 1;
  position: relative;
  min-height: 0;
}

.absolute-table-container {
  position: absolute;
  top: 0; left: 0; right: 0; bottom: 0;
}

.pagination-container {
  padding-top: 10px;
  text-align: right;
}

/* ── 其余业务样式 ────────────────────────────────── */
.ch-name { display: flex; align-items: center; font-weight: 500; }
.ch-name .dot { width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; }
.ch-sub { color: #909399; font-size: 12px; margin-top: 2px; }
.tip { color: #909399; font-size: 12px; }
.drawer-body { padding: 0 20px 80px; overflow-y: auto; height: calc(100vh - 130px); }
.drawer-footer {
  position: absolute; bottom: 0; left: 0; right: 0;
  padding: 12px 20px; border-top: 1px solid #ebeef5; background: #fff; text-align: right;
}
</style>
