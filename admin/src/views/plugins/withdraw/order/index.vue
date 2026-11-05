<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="page-header">
        <div class="page-title">
          <i class="el-icon-bank-card"></i>
          <span>提现记录</span>
        </div>
      </div>

      <el-form :inline="true" size="small" :model="searchForm" @submit.native.prevent class="filter-form shrink-0">
          <el-form-item label="提现单号">
            <el-input v-model="searchForm.order_no" placeholder="单号" clearable style="width: 180px;" @clear="search" @keyup.enter.native="search" />
          </el-form-item>
          <el-form-item label="用户">
            <el-input v-model="searchForm.user_keyword" placeholder="昵称/手机号" clearable style="width: 160px;" @clear="search" @keyup.enter.native="search" />
          </el-form-item>
          <el-form-item label="状态">
            <el-select v-model="searchForm.status" placeholder="全部" clearable style="width: 120px;" @change="search">
              <el-option v-for="(t, k) in statusMap" :key="k" :label="t" :value="Number(k)" />
            </el-select>
          </el-form-item>
          <el-form-item label="提现渠道">
            <el-select v-model="searchForm.channel_id" placeholder="全部" clearable filterable style="width: 150px;" @change="search">
              <el-option v-for="c in channelOptions" :key="c.id" :label="c.name" :value="c.id" />
            </el-select>
          </el-form-item>
          <!-- 资金类型 = 从哪个账户扣的钱(佣金/余额/积分/插件扩展钱包),与「提现渠道」(打到哪去)是两个维度 -->
          <el-form-item label="资金类型">
            <el-select v-model="searchForm.asset_type" placeholder="全部" clearable filterable style="width: 150px;" @change="search">
              <el-option v-for="o in assetOptions" :key="o.key"
                :label="o.plugin_label ? o.plugin_label + ' · ' + o.label : o.label" :value="o.key" />
            </el-select>
          </el-form-item>
          <el-form-item label="用户类型">
            <el-select v-model="searchForm.user_type" placeholder="全部" clearable style="width: 120px;" @change="search">
              <el-option label="普通用户" value="user" />
              <el-option label="商户" value="merchant" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" icon="el-icon-search" @click="search">查询</el-button>
            <el-button icon="el-icon-refresh" @click="resetSearch">重置</el-button>
            <el-button type="success" icon="el-icon-download" @click="exportList">导出</el-button>
          </el-form-item>
        </el-form>

      <div class="table-wrapper flex-1">
        <div class="absolute-table-container">
          <el-table v-loading="loading" :data="list" border height="100%" @row-dblclick="openDetail">
        <el-table-column label="提现单号" prop="order_no" min-width="180" />
        <el-table-column label="用户" min-width="200">
          <template slot-scope="{ row }">
            <div class="user-cell">
              <el-avatar :size="38" :src="row.user_avatar" icon="el-icon-user-solid" class="user-cell__avatar" />
              <div class="user-cell__info">
                <div class="user-cell__name">
                  {{ row.user_nickname || '未设昵称' }}
                  <el-tag size="mini" :type="row.user_type === 'merchant' ? 'warning' : 'info'">
                    {{ row.user_type === 'merchant' ? '商户' : '用户' }}
                  </el-tag>
                </div>
                <div class="user-cell__meta">ID: {{ row.user_id }}<span v-if="row.user_mobile"> · {{ row.user_mobile }}</span></div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="提现渠道" min-width="150">
          <template slot-scope="{ row }">
            <div>{{ row.channel_name }}</div>
            <!-- 资金类型:这笔钱从哪个账户扣(佣金/余额/积分/扩展钱包),与上面的渠道是两个维度 -->
            <el-tag size="mini" type="info" style="margin-top:2px;">{{ row.asset_label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="申请/到账" min-width="130">
          <template slot-scope="{ row }">
            <div>申请 {{ row.amount }}</div>
            <div style="color:#67c23a;font-size:12px;">到账 {{ row.actual_amount }}</div>
          </template>
        </el-table-column>
        <el-table-column label="手续费" width="110">
          <template slot-scope="{ row }">{{ row.fee }}<span v-if="Number(row.tax) > 0">（含税 {{ row.tax }}）</span></template>
        </el-table-column>
        <el-table-column label="打款账户信息" min-width="240">
          <template slot-scope="{ row }">
            <div class="payout-head">
              <span class="dest-tag" :class="row.destination">{{ destText(row.destination) }}</span>
              <span v-if="row.fund_channel_name" class="provider">（{{ row.fund_channel_name }}）</span>
            </div>
            <div v-if="row.payout_list && row.payout_list.length" class="payout-list">
              <div v-for="(it, i) in row.payout_list" :key="i" class="payout-item">
                <span class="pl-label">{{ it.label }}:</span>
                <span class="pl-value">{{ it.value }}</span>
              </div>
            </div>
            <div v-else-if="row.destination === 'balance'" class="text-gray">转入余额，无需账户</div>
            <div v-else class="text-warn">未填收款资料</div>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="110" align="center">
          <template slot-scope="{ row }">
            <el-tag size="mini" :type="statusType(row.status)">{{ row.status_text }}</el-tag>
            <el-tooltip v-if="row.fail_reason" effect="dark" placement="top">
              <div slot="content" style="max-width:320px;">{{ row.fail_reason }}</div>
              <i class="el-icon-warning-outline" style="color:#f56c6c;margin-left:4px;cursor:pointer;vertical-align:middle;"></i>
            </el-tooltip>
          </template>
        </el-table-column>
        <el-table-column label="申请时间" prop="created_at" width="150" />
        <el-table-column label="操作" width="200" align="center" fixed="right">
          <template slot-scope="{ row }">
            <template v-if="row.status === 0">
              <el-button type="text" @click="doAction('audit-pass', row)">通过</el-button>
              <el-button type="text" style="color:#f56c6c;" @click="promptReject(row)">驳回</el-button>
            </template>
            <template v-else-if="row.status === 1">
              <el-button type="text" @click="doAction('payout', row)">打款</el-button>
              <!-- 线下打款:强制走 ManualFund,不经线上网关。仅在「资金渠道→手动打款」已开启时出现 -->
              <el-button v-if="offlineEnabled" type="text" @click="promptOffline(row)">线下打款</el-button>
            </template>
            <template v-else-if="row.status === 2">
              <el-button type="text" @click="openConfirm(row)">确认到账</el-button>
              <!-- 这个按钮走的是 mark-failed → markFailed(),结果是「已退回(6) + 原路退款」的终态,
                   不是置为「打款失败(5)」。原来叫「标记失败」有歧义:运营容易以为是"先冻着待会重试",
                   实际点下去钱就退回用户、单子终结(6 态不可 retry)。与 5 态同一动作统一叫「退回」。 -->
              <el-button type="text" style="color:#f56c6c;" @click="promptFail(row)">退回</el-button>
            </template>
            <template v-else-if="row.status === 5">
              <el-button type="text" @click="promptRetry(row)">重试</el-button>
              <!-- 线上打不出去时的兜底:不必先「重试」绕回待打款,可直接线下转账留痕 -->
              <el-button v-if="offlineEnabled" type="text" @click="promptOffline(row)">线下打款</el-button>
              <el-button type="text" style="color:#f56c6c;" @click="promptFail(row)">退回</el-button>
            </template>
            <el-button v-if="row.receipt" type="text" @click="previewReceipt(row.receipt)">凭证</el-button>
            <el-button type="text" @click="openDetail(row)">详情</el-button>
          </template>
        </el-table-column>
          </el-table>
        </div>
      </div>

      <div class="pagination-container shrink-0">
        <el-pagination background layout="total, prev, pager, next, jumper" :total="pagination.total_count" :current-page="pagination.current_page" :page-size="pagination.page_size" @current-change="pageChange" />
      </div>
    </el-card>

    <el-drawer title="提现单详情" :visible.sync="detail.visible" size="600px" :wrapper-closable="true" :destroy-on-close="true">
      <div v-if="detail.data" v-loading="detail.loading" class="drawer-body">
        <el-descriptions :column="1" border size="small">
          <el-descriptions-item label="提现单号">{{ detail.data.order_no }}</el-descriptions-item>
          <el-descriptions-item label="状态"><el-tag size="mini" :type="statusType(detail.data.status)">{{ detail.data.status_text }}</el-tag></el-descriptions-item>
          <el-descriptions-item label="用户">
            <el-avatar :size="24" :src="detail.data.user_avatar" icon="el-icon-user-solid" style="vertical-align:middle;margin-right:6px;" />
            {{ detail.data.user_nickname }}（ID:{{ detail.data.user_id }}）/ {{ detail.data.user_mobile }} ({{ detail.data.user_type === 'merchant' ? '商户' : '用户' }})
          </el-descriptions-item>
          <el-descriptions-item label="提现渠道">{{ detail.data.channel_name }}</el-descriptions-item>
          <el-descriptions-item label="资金类型">
            <el-tag size="mini" type="info">{{ detail.data.asset_label }}</el-tag>
            <span style="color:#909399;font-size:12px;margin-left:6px;">该笔提现从此账户扣款</span>
          </el-descriptions-item>
          <el-descriptions-item label="到账去向">
            <span class="dest-tag" :class="detail.data.destination">{{ destText(detail.data.destination) }}</span>
          </el-descriptions-item>
          <el-descriptions-item label="申请金额">{{ detail.data.amount }}</el-descriptions-item>
          <el-descriptions-item label="手续费">
            {{ detail.data.fee }}<span v-if="Number(detail.data.tax) > 0">（含税 {{ detail.data.tax }}）</span>
          </el-descriptions-item>
          <el-descriptions-item label="实际到账">{{ detail.data.actual_amount }}</el-descriptions-item>
          <el-descriptions-item label="打款单号">{{ detail.data.payout_no || '-' }}</el-descriptions-item>
          <el-descriptions-item label="审核备注">{{ detail.data.audit_remark || '-' }}</el-descriptions-item>
          <el-descriptions-item label="失败原因">{{ detail.data.fail_reason || '-' }}</el-descriptions-item>
          <el-descriptions-item label="申请时间">{{ detail.data.created_at }}</el-descriptions-item>
          <el-descriptions-item label="完成时间">{{ detail.data.finish_at || '-' }}</el-descriptions-item>
          <el-descriptions-item v-if="detail.data.receipt" label="打款凭证">
            <el-image :src="detail.data.receipt" :preview-src-list="[detail.data.receipt]" style="width:80px;height:80px;" fit="cover" />
          </el-descriptions-item>
        </el-descriptions>
        <div v-if="detail.data.payout_info && detail.data.payout_info.length" style="margin-top:12px;">
          <div class="blk-title">收款信息</div>
          <el-descriptions :column="1" border size="small">
            <el-descriptions-item v-for="(p, i) in detail.data.payout_info" :key="i" :label="p.label || p.name">{{ p.value }}</el-descriptions-item>
          </el-descriptions>
        </div>
        <div v-if="[2, 3].includes(detail.data.status)" style="margin-top:12px;">
          <div class="blk-title">打款凭证（可补传/更新）</div>
          <single-image-uploader v-model="detail.receipt" />
          <el-button type="primary" size="small" :loading="detail.saving" @click="saveReceipt">保存凭证</el-button>
        </div>
      </div>
    </el-drawer>

    <!-- 确认到账 + 上传打款凭证 -->
    <el-dialog title="确认到账" :visible.sync="confirm.visible" width="460px" :close-on-click-modal="false">
      <el-form label-width="90px" size="small">
        <el-form-item label="打款凭证">
          <single-image-uploader v-model="confirm.receipt" />
          <div style="color:#909399;font-size:12px;">线下/手动打款后上传转账截图存证（可选）</div>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="confirm.remark" placeholder="可填网关单号等" />
        </el-form-item>
      </el-form>
      <div slot="footer">
        <el-button @click="confirm.visible = false">取消</el-button>
        <el-button type="primary" :loading="confirm.saving" @click="submitConfirm">确认到账</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'WithdrawOrderIndex',
  data() {
    return {
      loading: false,
      list: [],
      statusMap: {},
      assetOptions: [],     // 资金类型筛选项(核心3:佣金/余额/积分 + 插件扩展钱包)
      channelOptions: [],   // 提现渠道筛选项,后端 channel_options 下发(与后台「提现渠道」配置页同一概念)
      // 「线下打款」是否可用(手动打款网关已在资金渠道里开启)。仅控制按钮显隐,
      // 真正的闸门在后端 WithdrawService::payout —— 前端藏了不等于打得出去。
      offlineEnabled: false,
      searchForm: { order_no: '', user_keyword: '', status: '', channel_id: '', asset_type: '', user_type: '' },
      pagination: { current_page: 1, page_size: 20, total_count: 0 },
      detail: { visible: false, loading: false, saving: false, data: null, receipt: '' },
      confirm: { visible: false, saving: false, id: 0, receipt: '', remark: '' },
      exportTimer: null
    }
  },
  mounted() { this.getList() },
  beforeDestroy() { if (this.exportTimer) clearInterval(this.exportTimer) },
  methods: {
    statusType(s) { return { 0: 'info', 1: 'primary', 2: 'warning', 3: 'success', 4: 'danger', 5: 'danger', 6: 'info' }[s] || 'info' },
    destText(d) { return { bank: '银行卡', alipay: '支付宝', wechat: '微信零钱', balance: '商城余额' }[d] || (d || '-') },
    getList() {
      this.loading = true
      this.$request({
        url: '/plugin/withdraw/admin/order/index',
        method: 'get',
        data: {
          page: this.pagination.current_page, limit: this.pagination.page_size,
          order_no: this.searchForm.order_no, user_keyword: this.searchForm.user_keyword,
          status: this.searchForm.status, channel_id: this.searchForm.channel_id,
          asset_type: this.searchForm.asset_type, user_type: this.searchForm.user_type
        }
      }).then(res => {
        this.loading = false
        if (res.code === 0) {
          this.list = res.data.list || []
          if (res.data.pagination) this.pagination = res.data.pagination
          if (res.data.status_map) this.statusMap = res.data.status_map
          if (res.data.asset_options) this.assetOptions = res.data.asset_options
          if (res.data.channel_options) this.channelOptions = res.data.channel_options
          this.offlineEnabled = !!res.data.offline_payout_enabled
        } else { this.$message.error(res.msg) }
      }).catch(() => { this.loading = false })
    },
    search() { this.pagination.current_page = 1; this.getList() },
    exportList() {
      this.$confirm('按当前筛选条件导出提现记录？任务后台运行，数据量大时请耐心等待。', '导出确认', {
        confirmButtonText: '确定导出', cancelButtonText: '取消', type: 'warning'
      }).then(() => {
        const loading = this.$loading({ lock: true, text: '正在创建导出任务...', spinner: 'el-icon-loading', background: 'rgba(0,0,0,0.7)' })
        this.$request({
          url: '/plugin/withdraw/admin/order/async-export',
          method: 'get',
          data: {
            order_no: this.searchForm.order_no, status: this.searchForm.status,
            channel_id: this.searchForm.channel_id, asset_type: this.searchForm.asset_type,
            user_type: this.searchForm.user_type,
            user_keyword: this.searchForm.user_keyword
          }
        }).then(res => {
          if (res.code === 0) this.pollExport(res.data.token, loading)
          else { loading.close(); this.$message.error(res.msg) }
        }).catch(() => { loading.close(); this.$message.error('导出请求失败，请重试') })
      }).catch(() => {})
    },
    pollExport(token, loading) {
      if (this.exportTimer) clearInterval(this.exportTimer)
      this.exportTimer = setInterval(() => {
        this.$request({ url: '/plugin/withdraw/admin/order/export-status', method: 'get', data: { token }}).then(res => {
          if (res.code !== 0) { clearInterval(this.exportTimer); loading.close(); this.$message.error(res.msg); return }
          const d = res.data
          if (d.percentage !== undefined) loading.text = `正在导出: ${d.percentage}% (请勿关闭页面)`
          if (d.status === 'success') {
            clearInterval(this.exportTimer); loading.close()
            this.$message.success('导出成功，正在下载...')
            window.location.href = d.url
          } else if (d.status === 'failed') {
            clearInterval(this.exportTimer); loading.close()
            this.$message.error('导出失败: ' + (d.msg || '未知错误'))
          }
        }).catch(() => { clearInterval(this.exportTimer); loading.close(); this.$message.error('查询导出进度失败') })
      }, 1500)
    },
    resetSearch() { this.searchForm = { order_no: '', user_keyword: '', status: '', channel_id: '', asset_type: '', user_type: '' }; this.search() },
    pageChange(p) { this.pagination.current_page = p; this.getList() },
    openDetail(row) {
      this.detail.visible = true
      this.detail.loading = true
      this.detail.data = null
      this.$request({ url: '/plugin/withdraw/admin/order/detail', method: 'get', data: { id: row.id }}).then(res => {
        this.detail.loading = false
        if (res.code === 0) { this.detail.data = res.data; this.detail.receipt = res.data.receipt || '' } else { this.$message.error(res.msg); this.detail.visible = false }
      }).catch(() => { this.detail.loading = false })
    },
    doAction(action, row, remark, extra) {
      const data = Object.assign({ id: row.id, remark: remark || '' }, extra || {})
      this.$request({ url: '/plugin/withdraw/admin/order/' + action, method: 'post', data }).then(res => {
        if (res.code === 0) { this.$message.success(res.msg); this.getList() } else this.$message.error(res.msg)
      })
    },
    promptReject(row) {
      this.$prompt('请输入驳回原因', '审核驳回', { inputType: 'textarea' }).then(({ value }) => {
        this.doAction('audit-reject', row, value)
      }).catch(() => {})
    },
    // 「打款中(2)」与「打款失败(5)」共用:都走 mark-failed → 已退回(6) + 原路退款。
    // 这是终态,退回后不能再「重试」(retryPayout 只接受 5 态),所以标题与正文都把后果写清楚。
    promptFail(row) {
      this.$prompt('请输入退回原因。资产将原路退回用户，且订单终结、之后无法再重试打款。', '退回并退款', { inputType: 'textarea' }).then(({ value }) => {
        this.doAction('mark-failed', row, value)
      }).catch(() => {})
    },
    // 线下打款:绕开线上网关，直接生成一张手动打款单，订单进入「打款中」。
    // 运营线下转账完成后，再点「确认到账」并上传凭证收尾。
    promptOffline(row) {
      const reason = row.fail_reason ? ('\n\n当前失败原因：' + row.fail_reason) : ''
      this.$confirm(
        '将不经线上网关，直接生成一张线下打款单，订单转为“打款中”。\n' +
        '请在实际线下转账完成后，再点「确认到账」并上传转账凭证。' + reason,
        '线下打款',
        { type: 'warning', confirmButtonText: '确认线下打款', cancelButtonText: '取消' }
      ).then(() => {
        this.doAction('payout', row, '', { offline: 1 })
      }).catch(() => {})
    },
    promptRetry(row) {
      const reason = row.fail_reason ? ('\n失败原因：' + row.fail_reason) : ''
      this.$confirm('确认重试打款？订单将退回“待打款”，资金保持冻结不动。请先确保失败原因已修复（如快付 IP 白名单）后再重新发起打款。' + reason, '重试打款', {
        type: 'warning', confirmButtonText: '确认重试', cancelButtonText: '取消'
      }).then(() => {
        this.doAction('retry', row)
      }).catch(() => {})
    },
    openConfirm(row) {
      this.confirm = { visible: true, saving: false, id: row.id, receipt: '', remark: '' }
    },
    submitConfirm() {
      this.confirm.saving = true
      this.$request({
        url: '/plugin/withdraw/admin/order/mark-success',
        method: 'post',
        data: { id: this.confirm.id, receipt: this.confirm.receipt || '', remark: this.confirm.remark || '' }
      }).then(res => {
        this.confirm.saving = false
        if (res.code === 0) { this.$message.success(res.msg); this.confirm.visible = false; this.getList() } else this.$message.error(res.msg)
      }).catch(() => { this.confirm.saving = false })
    },
    previewReceipt(url) {
      // 用 el-image-viewer 预览；简单起见新窗口打开
      window.open(url, '_blank')
    },
    saveReceipt() {
      if (!this.detail.receipt) return this.$message.warning('请先上传凭证')
      this.detail.saving = true
      this.$request({
        url: '/plugin/withdraw/admin/order/upload-receipt',
        method: 'post',
        data: { id: this.detail.data.id, receipt: this.detail.receipt }
      }).then(res => {
        this.detail.saving = false
        if (res.code === 0) {
          this.$message.success('凭证已保存')
          this.detail.data.receipt = this.detail.receipt
          this.getList()
        } else this.$message.error(res.msg)
      }).catch(() => { this.detail.saving = false })
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
.user-cell { display: flex; align-items: center; }
.user-cell__avatar { flex-shrink: 0; background: #f0f2f5; }
.user-cell__info { margin-left: 10px; min-width: 0; }
.user-cell__name { font-size: 13px; color: #303133; line-height: 1.4; }
.user-cell__meta { font-size: 12px; color: #909399; margin-top: 2px; }
.drawer-body { padding: 0 20px 40px; overflow-y: auto; height: calc(100vh - 90px); }
.blk-title { font-weight: 600; margin-bottom: 8px; }
.payout-head { margin-bottom: 4px; }
.dest-tag { display: inline-block; padding: 0 8px; height: 20px; line-height: 20px; border-radius: 3px; font-size: 12px; background: #f4f4f5; color: #909399; }
.dest-tag.bank    { background: #e8f0ff; color: #133A94; }
.dest-tag.alipay  { background: #e6f7ff; color: #00a0e9; }
.dest-tag.wechat  { background: #e8fff0; color: #07c160; }
.dest-tag.balance { background: #fff0f0; color: #E43D3D; }
.provider { font-size: 12px; color: #909399; }
.payout-list { margin-top: 2px; }
.payout-item { font-size: 12px; line-height: 1.7; }
.payout-item .pl-label { color: #909399; margin-right: 4px; }
.payout-item .pl-value { color: #333; }
.text-gray { color: #909399; font-size: 12px; }
.text-warn { color: #E6A23C; font-size: 12px; }
</style>
