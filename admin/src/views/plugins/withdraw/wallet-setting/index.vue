<template>
  <div class="app-container page-flex">
    <el-alert
      type="info"
      :closable="false"
      show-icon
      class="mb-12"
      title="扩展钱包由插件声明,这里控制本商城是否挂载、以及能否作为提现来源。"
      description="关闭后:该钱包不再出现在用户资产、提现来源等处,分润也不会再打进去(已在里面的余额不受影响,退款冲账仍可正常扣回)。">
    </el-alert>

    <el-card shadow="never" class="table-card">
      <div slot="header" class="card-header-flex">
        <span style="font-weight:bold;">扩展钱包</span>
        <el-button
          type="primary"
          size="mini"
          :loading="saving"
          icon="el-icon-check"
          @click="save">保存</el-button>
      </div>

      <el-table :data="list" v-loading="loading" height="100%" style="width:100%">
        <el-table-column label="钱包" min-width="180">
          <template slot-scope="{ row }">
            <span style="font-weight:500;">{{ row.name }}</span>
            <div style="font-size:12px;color:#909399;">{{ row.key }}</div>
          </template>
        </el-table-column>

        <el-table-column label="来源插件" width="150" align="center">
          <template slot-scope="{ row }">
            <el-tag size="mini" type="info">{{ row.plugin_label || row.plugin }}</el-tag>
          </template>
        </el-table-column>

        <el-table-column label="启用" width="130" align="center">
          <template slot-scope="{ row }">
            <el-switch v-model="row.enabled" :active-value="1" :inactive-value="0" />
          </template>
        </el-table-column>

        <el-table-column label="可提现" width="150" align="center">
          <template slot-scope="{ row }">
            <el-switch
              v-model="row.withdrawable"
              :active-value="1"
              :inactive-value="0"
              :disabled="row.enabled !== 1" />
            <div v-if="row.enabled !== 1" style="font-size:11px;color:#C0C4CC;">需先启用</div>
          </template>
        </el-table-column>

        <el-table-column label="状态" min-width="200">
          <template slot-scope="{ row }">
            <span v-if="row.enabled !== 1" style="color:#F56C6C;">已关闭,不挂载</span>
            <span v-else-if="row.withdrawable !== 1" style="color:#E6A23C;">已启用,但不可提现</span>
            <span v-else style="color:#67C23A;">已启用,可提现</span>
            <el-tag v-if="!row.configured" size="mini" type="info" style="margin-left:6px;">默认值</el-tag>
          </template>
        </el-table-column>
      </el-table>

      <div v-if="!loading && !list.length" class="empty-tip">
        当前商城没有已授权插件声明扩展钱包。<br />
        扩展钱包由插件通过 getWallets() 声明,安装并授权相应插件后会出现在这里。
      </div>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'WithdrawWalletSetting',
  data() {
    return {
      loading: false,
      saving: false,
      list: []
    }
  },
  created() {
    this.loadData()
  },
  methods: {
    loadData() {
      this.loading = true
      this.$request({ url: '/plugin/withdraw/admin/wallet-setting/config', method: 'get' })
        .then(res => {
          this.list = res.code === 0 ? (res.data.list || []) : []
        })
        .finally(() => { this.loading = false })
    },
    save() {
      this.saving = true
      this.$request({
        url: '/plugin/withdraw/admin/wallet-setting/config',
        method: 'post',
        data: {
          list: this.list.map(r => ({
            key: r.key,
            enabled: r.enabled,
            // 未启用时「可提现」无意义,统一落 0,免得后台看着是关的、库里却存着 1
            withdrawable: r.enabled === 1 ? r.withdrawable : 0
          }))
        }
      }).then(res => {
        if (res.code === 0) {
          this.$modal.msgSuccess('保存成功')
          this.loadData()
        }
      }).finally(() => { this.saving = false })
    }
  }
}
</script>

<style scoped>
/* 整页不滚动、表格内部滚动(§14.1.1 列表页标准布局),避免抽屉内下拉错位 */
.page-flex {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 84px);
  overflow: hidden;
}
.mb-12 { margin-bottom: 12px; }
.table-card {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.table-card >>> .el-card__body {
  flex: 1;
  overflow: hidden;
  padding: 0 20px 20px;
}
.empty-tip {
  padding: 40px 20px;
  text-align: center;
  color: #909399;
  font-size: 13px;
  line-height: 1.8;
}
</style>
