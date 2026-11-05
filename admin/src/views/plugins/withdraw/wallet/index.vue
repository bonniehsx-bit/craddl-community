<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="page-header">
        <div class="page-title"><i class="el-icon-tickets"></i><span>钱包流水</span></div>
        <span class="tip">通用钱包(各插件账户)的入账 / 扣减明细。核心余额/佣金/积分/货款的流水在各自页面。</span>
        <el-button type="primary" size="small" icon="el-icon-plus" style="margin-left: auto;" @click="openAdjust">手动充值/扣除</el-button>
      </div>

      <div class="filter-bar">
        <el-select v-model="searchForm.wallet_key" placeholder="钱包类型" clearable size="small" style="width: 180px;" @change="search">
          <el-option
            v-for="w in walletOptions"
            :key="w.key"
            :label="(w.plugin_label ? w.plugin_label + ' · ' : '') + w.name"
            :value="w.key" />
        </el-select>
        <el-select v-model="searchForm.type" placeholder="类型" clearable size="small" style="width: 110px;" @change="search">
          <el-option label="入账" :value="1" />
          <el-option label="扣减" :value="2" />
        </el-select>
        <el-input v-model="searchForm.user_keyword" placeholder="用户昵称/手机号" clearable size="small" style="width: 180px;"
          @keyup.enter.native="search" @clear="search" />
        <el-button type="primary" icon="el-icon-search" size="small" @click="search">查询</el-button>
        <el-button icon="el-icon-refresh" size="small" @click="reset">重置</el-button>
      </div>

      <div class="table-wrap">
        <el-table v-loading="loading" :data="list" border height="100%">
          <el-table-column label="用户" min-width="180">
            <template slot-scope="{ row }">
              <div class="user-cell">
                <img v-if="row.user_avatar" :src="row.user_avatar" class="avatar" />
                <div>
                  <div>{{ row.user_nickname || '—' }}</div>
                  <div class="sub">{{ row.user_mobile }} · ID {{ row.user_id }}</div>
                </div>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="钱包" min-width="130">
            <template slot-scope="{ row }"><el-tag size="mini" type="info">{{ row.wallet_label }}</el-tag></template>
          </el-table-column>
          <el-table-column label="类型" width="80" align="center">
            <template slot-scope="{ row }">
              <el-tag size="mini" :type="row.type === 1 ? 'success' : 'danger'">{{ row.type_text }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="金额" width="120" align="right">
            <template slot-scope="{ row }">
              <span :style="{ color: row.type === 1 ? '#67c23a' : '#f56c6c', fontWeight: 600 }">
                {{ row.type === 1 ? '+' : '-' }}{{ row.amount }}
              </span>
            </template>
          </el-table-column>
          <el-table-column label="变动后余额" prop="balance_after" width="110" align="right" />
          <el-table-column label="标题/备注" min-width="200">
            <template slot-scope="{ row }">
              <div>{{ row.title || '—' }}</div>
              <div class="sub" v-if="row.remark">{{ row.remark }}</div>
            </template>
          </el-table-column>
          <el-table-column label="来源" prop="source" min-width="130" show-overflow-tooltip />
          <el-table-column label="时间" prop="created_at" width="150" />
        </el-table>
      </div>

      <div class="pager">
        <el-pagination background layout="total, prev, pager, next, jumper"
          :total="pagination.total_count" :current-page="pagination.current_page"
          :page-size="pagination.page_size" @current-change="pageChange" />
      </div>
    </el-card>

    <!-- 手动充值/扣除 -->
    <el-dialog title="手动充值 / 扣除" :visible.sync="adjust.visible" width="460px" :close-on-click-modal="false" @closed="resetAdjust" append-to-body>
      <el-form :model="adjust.form" label-width="70px" size="small">
        <el-form-item label="用户" required>
          <el-select v-model="adjust.form.user_id" filterable remote :remote-method="searchUser" :loading="adjust.userLoading"
            placeholder="输入昵称/手机号搜索" style="width: 100%;">
            <el-option v-for="u in adjust.userOptions" :key="u.id"
              :label="(u.nickname || '未命名') + (u.mobile ? ' / ' + u.mobile : '') + ' (ID:' + u.id + ')'" :value="u.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="钱包" required>
          <el-select v-model="adjust.form.wallet_key" placeholder="选择钱包" style="width: 100%;">
            <el-option v-for="w in walletOptions" :key="w.key"
              :label="(w.plugin_label ? w.plugin_label + ' · ' : '') + w.name" :value="w.key" />
          </el-select>
        </el-form-item>
        <el-form-item label="操作" required>
          <el-radio-group v-model="adjust.form.type">
            <el-radio-button :label="1">增加</el-radio-button>
            <el-radio-button :label="2">扣除</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="金额" required>
          <el-input-number v-model="adjust.form.amount" :min="0.01" :step="1" :precision="2" style="width: 160px;" />
          <span class="sub" style="margin-left: 8px;">元</span>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="adjust.form.remark" type="textarea" :rows="2" maxlength="100" show-word-limit
            placeholder="会记入钱包流水,如:活动补偿 / 误操作修正" />
        </el-form-item>
      </el-form>
      <div slot="footer">
        <el-button @click="adjust.visible = false">取消</el-button>
        <el-button type="primary" :loading="adjust.saving" @click="submitAdjust">确定</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'WithdrawWalletLog',
  data() {
    return {
      loading: false,
      list: [],
      walletOptions: [],
      searchForm: { wallet_key: '', type: '', user_keyword: '' },
      pagination: { current_page: 1, page_size: 20, total_count: 0 },
      adjust: {
        visible: false, saving: false, userLoading: false, userOptions: [],
        form: { user_id: '', wallet_key: '', type: 1, amount: null, remark: '' }
      }
    }
  },
  mounted() { this.getList() },
  methods: {
    getList() {
      this.loading = true
      this.$request({
        url: '/plugin/withdraw/admin/wallet/index',
        method: 'get',
        data: {
          page: this.pagination.current_page,
          limit: this.pagination.page_size,
          wallet_key: this.searchForm.wallet_key,
          type: this.searchForm.type,
          user_keyword: this.searchForm.user_keyword
        }
      }).then(res => {
        this.loading = false
        if (res.code === 0) {
          this.list = res.data.list || []
          if (res.data.pagination) this.pagination = res.data.pagination
          if (res.data.wallet_options) this.walletOptions = res.data.wallet_options
        } else this.$message.error(res.msg)
      }).catch(() => { this.loading = false })
    },
    search() { this.pagination.current_page = 1; this.getList() },
    reset() { this.searchForm = { wallet_key: '', type: '', user_keyword: '' }; this.search() },
    pageChange(p) { this.pagination.current_page = p; this.getList() },

    openAdjust() { this.resetAdjust(); this.adjust.visible = true },
    resetAdjust() {
      this.adjust.form = { user_id: '', wallet_key: '', type: 1, amount: null, remark: '' }
      this.adjust.userOptions = []
    },
    searchUser(kw) {
      kw = (kw || '').trim()
      if (!kw) { this.adjust.userOptions = []; return }
      this.adjust.userLoading = true
      this.$request({ url: '/plugin/withdraw/admin/wallet/search-user', method: 'get', data: { keyword: kw } }).then(res => {
        this.adjust.userLoading = false
        if (res.code === 0) this.adjust.userOptions = res.data.list || []
      }).catch(() => { this.adjust.userLoading = false })
    },
    submitAdjust() {
      const f = this.adjust.form
      if (!f.user_id) return this.$message.warning('请选择用户')
      if (!f.wallet_key) return this.$message.warning('请选择钱包')
      if (!f.amount || f.amount <= 0) return this.$message.warning('请输入金额')
      this.adjust.saving = true
      this.$request({
        url: '/plugin/withdraw/admin/wallet/adjust', method: 'post',
        data: { user_id: f.user_id, wallet_key: f.wallet_key, type: f.type, amount: f.amount, remark: f.remark }
      }).then(res => {
        this.adjust.saving = false
        if (res.code === 0) {
          this.$message.success('操作成功')
          this.adjust.visible = false
          this.getList()
        } else this.$message.error(res.msg || '操作失败')
      }).catch(() => { this.adjust.saving = false })
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
.page-header { display: flex; align-items: center; }
.page-title { display: flex; align-items: center; gap: 8px; font-size: 15px; font-weight: 600; color: #303133; i { color: var(--admin-accent-text); font-size: 18px; } }
.tip { margin-left: 12px; color: #909399; font-size: 12px; }
.filter-bar { display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
.table-wrap { flex: 1; min-height: 0; }
.pager { padding-top: 10px; text-align: right; }
.user-cell { display: flex; align-items: center; gap: 8px; .avatar { width: 30px; height: 30px; border-radius: 50%; } }
.sub { color: #909399; font-size: 12px; }
</style>
