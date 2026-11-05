<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="page-header">
        <div>
          <span class="page-title">退款失败处理</span>
          <span class="page-tip">退款失败且系统不再自动重试的、余额不足等充值的、处理中超过 30 分钟没结果的、已退款但后续没做完的，都在这里；处理后约 1 分钟内生效</span>
        </div>
        <div>
          <el-button size="small" icon="el-icon-refresh" @click="getList">刷新</el-button>
          <el-button size="small" icon="el-icon-download" :loading="exportLoading" @click="exportExcel">导出</el-button>
        </div>
      </div>

      <el-form :inline="true" size="small" class="filter-form shrink-0" @submit.native.prevent="search">
        <el-form-item label="搜索">
          <el-input v-model="query.keyword" placeholder="订单号/退款单号/昵称/手机号" clearable style="width: 220px" @keyup.enter.native="search" />
        </el-form-item>
        <el-form-item label="情况">
          <el-select v-model="query.kind" placeholder="全部" clearable style="width: 170px">
            <el-option v-for="o in kindOptions" :key="o.value" :label="o.label" :value="o.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="来源">
          <el-select v-model="query.source_type" placeholder="全部" clearable style="width: 150px">
            <el-option v-for="o in sourceOptions" :key="o.value" :label="o.label" :value="o.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="创建时间">
          <el-date-picker v-model="query.search_date" type="daterange" value-format="yyyy-MM-dd" range-separator="至"
                          start-placeholder="开始日期" end-placeholder="结束日期" style="width: 240px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="search">搜索</el-button>
        </el-form-item>
      </el-form>

      <div class="table-wrapper flex-1">
        <div class="absolute-table-container">
          <el-table v-loading="loading" :data="list" height="100%" size="small" border @row-dblclick="openDetail">
            <el-table-column prop="id" label="ID" width="70" align="center" />
            <el-table-column label="情况" width="150" align="center">
              <template slot-scope="{ row }">
                <el-tag size="mini" :type="kindTag(row.kind)">{{ row.kind_text }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="order_no" label="订单号" min-width="170" show-overflow-tooltip />
            <el-table-column label="用户" min-width="150" show-overflow-tooltip>
              <template slot-scope="{ row }">{{ row.nickname || '—' }} {{ row.mobile }}</template>
            </el-table-column>
            <el-table-column label="退款金额" width="100" align="right">
              <template slot-scope="{ row }"><span class="money">¥{{ row.refund_price }}</span></template>
            </el-table-column>
            <el-table-column prop="payment_name" label="支付方式" width="130" show-overflow-tooltip />
            <el-table-column prop="source_text" label="来源" width="130" show-overflow-tooltip />
            <el-table-column label="原因" min-width="220" show-overflow-tooltip>
              <template slot-scope="{ row }">
                {{ row.reason }}<span v-if="row.next_retry_text" class="retry-tip"> · {{ row.next_retry_text }} 自动再试</span>
              </template>
            </el-table-column>
            <el-table-column prop="updated_at" label="最后尝试" width="160" align="center" />
            <el-table-column label="操作" width="260" align="center" fixed="right">
              <template slot-scope="{ row }">
                <el-button v-if="row.can_query" type="text" @click="doQuery(row)">查询结果</el-button>
                <el-button v-if="row.can_retry" type="text" @click="openRetry(row)">重新发起</el-button>
                <el-button v-if="row.can_mark" type="text" @click="openMark(row)">登记已退款</el-button>
                <el-button type="text" @click="openDetail(row)">详情{{ row.logs.length ? `(${row.logs.length})` : '' }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </div>

      <div class="pagination-container shrink-0">
        <el-pagination background layout="total, prev, pager, next" :total="pagination.total_count" :page-size="query.limit"
                       :current-page.sync="query.page" @current-change="getList" />
      </div>
    </el-card>

    <!-- 详情:原因、建议、失败原文、处理记录 -->
    <el-drawer title="退款详情" :visible.sync="detailVisible" size="560px" :wrapper-closable="true">
      <div v-if="current" class="drawer-body">
        <el-descriptions :column="1" size="small" border>
          <el-descriptions-item label="情况"><el-tag size="mini" :type="kindTag(current.kind)">{{ current.kind_text }}</el-tag></el-descriptions-item>
          <el-descriptions-item label="订单号">{{ current.order_no }}</el-descriptions-item>
          <el-descriptions-item label="退款单号">{{ current.out_refund_no }}</el-descriptions-item>
          <el-descriptions-item label="用户">{{ current.nickname || '—' }} {{ current.mobile }}</el-descriptions-item>
          <el-descriptions-item label="退款金额">¥{{ current.refund_price }}(实付 ¥{{ current.pay_price }})</el-descriptions-item>
          <el-descriptions-item label="支付方式">{{ current.payment_name }}</el-descriptions-item>
          <el-descriptions-item label="来源">{{ current.source_text }}</el-descriptions-item>
          <el-descriptions-item label="原因">{{ current.reason }}</el-descriptions-item>
          <el-descriptions-item label="建议">{{ current.advice }}</el-descriptions-item>
          <el-descriptions-item label="失败原文">{{ current.error_msg || '—' }}</el-descriptions-item>
          <el-descriptions-item label="已尝试">{{ current.retry_count }} 次</el-descriptions-item>
          <el-descriptions-item v-if="current.next_retry_text" label="下次自动重试">{{ current.next_retry_text }}(充值后可以不等,直接「重新发起」)</el-descriptions-item>
          <el-descriptions-item label="创建时间">{{ current.created_at }}</el-descriptions-item>
        </el-descriptions>
        <div class="drawer-actions">
          <el-button v-if="current.can_query" size="small" @click="doQuery(current)">查询结果</el-button>
          <el-button v-if="current.can_retry" size="small" type="primary" @click="openRetry(current)">重新发起</el-button>
          <el-button v-if="current.can_mark" size="small" type="warning" @click="openMark(current)">登记已退款</el-button>
        </div>
        <refund-handle-logs :logs="current.logs" />
      </div>
    </el-drawer>

    <!-- 重新发起 -->
    <el-dialog title="重新发起退款" :visible.sync="retryVisible" width="480px" :close-on-click-modal="false">
      <div v-if="current">
        <p class="dialog-text">将按原支付方式({{ current.payment_name }})再退 <b>¥{{ current.refund_price }}</b>,约 1 分钟内执行;还不成功会重新出现在这里。</p>
        <el-alert v-if="current.need_verify" type="warning" :closable="false" show-icon
                  :title="current.verify_reason + '。请先到支付平台核实这笔确实没有退过,否则会退两次。'" />
        <el-form label-width="80px" size="small" class="dialog-form">
          <el-form-item v-if="current.need_verify" label="核实">
            <el-checkbox v-model="retryForm.verified">我已到支付平台核实,这笔没有退过</el-checkbox>
          </el-form-item>
          <el-form-item :label="current.need_verify ? '核实情况' : '备注'">
            <el-input v-model="retryForm.remark" type="textarea" :rows="3" maxlength="255" show-word-limit
                      :placeholder="current.need_verify ? '必填:在哪核实的、看到了什么' : '选填'" />
          </el-form-item>
        </el-form>
      </div>
      <span slot="footer">
        <el-button size="small" @click="retryVisible = false">取消</el-button>
        <el-button size="small" type="primary" :loading="submitting" :disabled="current && current.need_verify && (!retryForm.verified || !retryForm.remark.trim())"
                   @click="submit('retry', retryForm)">确认重新发起</el-button>
      </span>
    </el-dialog>

    <!-- 登记已退款 -->
    <el-dialog title="登记已退款" :visible.sync="markVisible" width="480px" :close-on-click-modal="false">
      <div v-if="current">
        <el-alert type="warning" :closable="false" show-icon
                  title="登记后系统不会再自动退这笔。请确认钱确实已经退给顾客(在支付平台退了,或线下转账了)。" />
        <el-form label-width="80px" size="small" class="dialog-form">
          <el-form-item label="退款金额"><b>¥{{ current.refund_price }}</b></el-form-item>
          <el-form-item label="怎么退的">
            <el-input v-model="markForm.remark" type="textarea" :rows="3" maxlength="255" show-word-limit
                      placeholder="必填,如:已在微信商户平台退款 / 已线下转账给顾客" />
          </el-form-item>
          <el-form-item label="凭证">
            <single-image-selector v-model="markForm.voucher" />
          </el-form-item>
        </el-form>
      </div>
      <span slot="footer">
        <el-button size="small" @click="markVisible = false">取消</el-button>
        <el-button size="small" type="warning" :loading="submitting" :disabled="!markForm.remark.trim()"
                   @click="submit('mark_refunded', markForm)">确认登记</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import RefundHandleLogs from './components/RefundHandleLogs'

export default {
  name: 'FinanceRefundFail',
  components: { RefundHandleLogs },
  data() {
    return {
      loading: false,
      exportLoading: false,
      submitting: false,
      list: [],
      pagination: { total_count: 0 },
      kindOptions: [],
      sourceOptions: [],
      query: { keyword: '', kind: '', source_type: '', search_date: [], page: 1, limit: 20 },
      current: null,
      detailVisible: false,
      retryVisible: false,
      markVisible: false,
      retryForm: { verified: false, remark: '' },
      markForm: { remark: '', voucher: '' }
    }
  },
  created() {
    // 从售后列表「去处理」跳过来会带上退款单号,直接定位到那一笔
    if (this.$route.query.keyword) this.query.keyword = String(this.$route.query.keyword)
  },
  mounted() {
    this.getList()
  },
  watch: {
    // 页面被标签页缓存时,再从售后列表跳过来不会重新创建,按新的单号重查
    '$route.query.keyword'(k) {
      if (k && this.$route.path === '/finance/refund-fail') {
        this.query.keyword = String(k)
        this.search()
      }
    }
  },
  methods: {
    kindTag(kind) {
      return { fail: 'danger', stuck: 'warning', unsynced: 'info' }[kind] || 'info'
    },
    params(isExport) {
      const p = { ...this.query }
      if (!p.search_date || p.search_date.length !== 2) delete p.search_date
      if (isExport) p.is_export = 1
      return p
    },
    search() {
      this.query.page = 1
      this.getList()
    },
    async getList() {
      this.loading = true
      try {
        const res = await this.$request({ url: '/mall/finance/refund-fail', method: 'get', data: this.params() })
        if (res.code === 0) {
          this.list = res.data.list
          this.pagination = res.data.pagination || { total_count: res.data.list.length }
          this.kindOptions = res.data.kind_options || []
          this.sourceOptions = res.data.source_options || []
          if (this.current) {
            this.current = this.list.find(r => r.id === this.current.id) || null
            if (!this.current) this.detailVisible = false
          }
        } else {
          this.$message.error(res.msg)
        }
      } finally {
        this.loading = false
      }
    },
    openDetail(row) {
      this.current = row
      this.detailVisible = true
    },
    openRetry(row) {
      this.current = row
      this.retryForm = { verified: false, remark: '' }
      this.retryVisible = true
    },
    openMark(row) {
      this.current = row
      this.markForm = { remark: '', voucher: '' }
      this.markVisible = true
    },
    doQuery(row) {
      this.current = row
      this.$confirm(`向网关查询这笔退款(¥${row.refund_price})现在的结果?`, '查询结果', { type: 'info' })
        .then(() => this.submit('query', {}))
        .catch(() => {})
    },
    async submit(action, form) {
      this.submitting = true
      try {
        const res = await this.$request({
          url: '/mall/finance/refund-handle',
          method: 'post',
          data: {
            id: this.current.id,
            action,
            remark: (form.remark || '').trim(),
            voucher: form.voucher || '',
            verified: form.verified ? 1 : 0
          }
        })
        if (res.code === 0) {
          this.$message.success(res.msg)
          this.retryVisible = false
          this.markVisible = false
          this.getList()
        } else {
          this.$message.error(res.msg)
        }
      } finally {
        this.submitting = false
      }
    },
    async exportExcel() {
      this.exportLoading = true
      try {
        const res = await this.$request({ url: '/mall/finance/refund-fail', method: 'get', data: this.params(true) })
        if (res.code !== 0 || !res.data.list.length) {
          this.$message.warning(res.code === 0 ? '没有可导出的数据' : res.msg)
          return
        }
        const header = ['ID', '情况', '订单号', '退款单号', '用户', '手机号', '退款金额', '支付方式', '来源', '原因', '失败原文', '已尝试次数', '下次自动重试', '创建时间', '最后尝试']
        const fields = ['id', 'kind_text', 'order_no', 'out_refund_no', 'nickname', 'mobile', 'refund_price', 'payment_name', 'source_text', 'reason', 'error_msg', 'retry_count', 'next_retry_text', 'created_at', 'updated_at']
        this.$export(header, fields, res.data.list, '退款失败处理')
      } finally {
        this.exportLoading = false
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.app-container { height: calc(100vh - 85px); display: flex; flex-direction: column; overflow: hidden; padding: 15px; box-sizing: border-box; background: #f0f2f5; }
.box-card { flex: 1; display: flex; flex-direction: column; border: none; }
.box-card ::v-deep .el-card__body { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0; }
.page-header { display: flex; justify-content: space-between; align-items: center; }
.page-title { font-size: 16px; font-weight: 600; }
.page-tip { margin-left: 12px; font-size: 12px; color: #909399; }
.shrink-0 { flex-shrink: 0; }
.flex-1 { flex: 1; }
.table-wrapper { flex: 1; position: relative; min-height: 0; }
.absolute-table-container { position: absolute; top: 0; left: 0; right: 0; bottom: 0; }
.pagination-container { padding-top: 10px; text-align: right; }
.money { color: #F56C6C; font-weight: 600; }
.retry-tip { color: #909399; font-size: 12px; }
.drawer-body { padding: 0 20px 20px; overflow-y: auto; height: 100%; box-sizing: border-box; }
.drawer-actions { margin: 16px 0; }
.dialog-text { margin: 0 0 12px; line-height: 1.6; }
.dialog-form { margin-top: 12px; }
</style>
