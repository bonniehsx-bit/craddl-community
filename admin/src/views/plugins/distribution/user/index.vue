<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never" v-loading="loading">
      <div slot="header" class="page-header">
        <div class="page-title">
          <i class="el-icon-share"></i>
          <span>分销人员管理</span>
        </div>
      </div>

      <el-alert type="info" :closable="false" class="shrink-0" style="margin-bottom: 8px;">
        <div style="line-height: 1.7;">
          <div><b>分销商 = 有推广资格的用户</b>（<code>is_inviter=1</code>，由会员等级驱动，也可后台手动授予）。</div>
          <div style="color: #606266;">分销商<b>等级即会员等级</b>，佣金比例按会员等级在「分销设置 → 按会员等级分润」配置；可单独「取消资格」停止某用户计佣。</div>
        </div>
      </el-alert>

      <el-form :inline="true" size="small" class="filter-form shrink-0" @submit.native.prevent>
        <el-form-item label="关键字">
          <el-input v-model="search.keyword" placeholder="昵称/手机号/ID" clearable style="width: 200px;"
                    @clear="onSearch" @keyup.enter.native="onSearch" />
        </el-form-item>
        <el-form-item label="会员等级">
          <el-select v-model="search.level_id" placeholder="全部" clearable style="width: 140px;" @change="onSearch">
            <el-option label="全部" value="" />
            <el-option label="无等级" value="0" />
            <el-option v-for="lv in levels" :key="lv.id" :label="lv.name" :value="String(lv.id)" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="onSearch">搜索</el-button>
          <el-button icon="el-icon-refresh" @click="onReset">重置</el-button>
        </el-form-item>
      </el-form>

      <div class="table-wrapper flex-1">
        <div class="absolute-table-container">
          <el-table :data="list" border size="small" height="100%"
                    :header-cell-style="{ background: '#F5F7FA', color: '#606266', fontSize: '13px' }"
                    :cell-style="{ fontSize: '13px' }">
            <el-table-column label="用户" min-width="200">
              <template slot-scope="s">
                <div style="display: flex; align-items: center; gap: 10px;">
                  <el-avatar :size="32" :src="s.row.avatar_url || defaultAvatar" style="flex-shrink: 0;" />
                  <div>
                    <div style="font-weight: 500;">{{ s.row.nickname || '(未设置昵称)' }}</div>
                    <div style="font-size: 12px; color: #909399;">ID: {{ s.row.id }}</div>
                  </div>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="mobile" label="手机号" width="130" />
            <el-table-column label="分销状态" width="100" align="center">
              <template slot-scope="s">
                <el-tag v-if="s.row.is_disabled" size="mini" type="danger">已取消</el-tag>
                <el-tag v-else-if="s.row.is_inviter" size="mini" type="success">正常</el-tag>
                <el-tag v-else size="mini" type="info">无推广资格</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="会员等级" width="120" align="center">
              <template slot-scope="s">
                <el-tag v-if="s.row.level_id" size="mini">{{ s.row.level_name || ('等级#' + s.row.level_id) }}</el-tag>
                <span v-else style="color: #C0C4CC;">无等级</span>
              </template>
            </el-table-column>
            <el-table-column label="获得资格时间" width="130">
              <template slot-scope="s">
                <span v-if="s.row.inviter_at_text">{{ s.row.inviter_at_text }}</span>
                <span v-else style="color: #C0C4CC;">—</span>
              </template>
            </el-table-column>
            <el-table-column label="上级" min-width="120" show-overflow-tooltip>
              <template slot-scope="s">
                <span v-if="s.row.parent_id">{{ s.row.parent_nickname || '—' }} <span style="color:#909399;font-size:12px;">#{{ s.row.parent_id }}</span></span>
                <span v-else style="color: #C0C4CC;">无</span>
              </template>
            </el-table-column>
            <el-table-column prop="created_at_text" label="注册时间" width="120" />
            <el-table-column label="操作" width="220" align="center" fixed="right">
              <template slot-scope="s">
                <el-button v-if="s.row.is_disabled" type="text" size="mini" @click="toggleInviter(s.row, 'grant')">恢复资格</el-button>
                <el-button v-else type="text" size="mini" style="color: #F56C6C" @click="toggleInviter(s.row, 'revoke')">取消资格</el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </div>

      <div class="pagination-container shrink-0">
        <el-pagination background layout="total, prev, pager, next, jumper"
                       :current-page="page"
                       :page-size="20"
                       :total="pagination ? pagination.total_count : 0"
                       @current-change="handlePageChange" />
      </div>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'distribution-user-index',
  data() {
    return {
      loading: false,
      list: [],
      levels: [],
      page: 1,
      pagination: null,
      search: { keyword: '', level_id: '' },
      defaultAvatar: 'https://cdn.uviewui.com/uview/album/1.jpg',
    };
  },
  created() { this.loadList(); },
  methods: {
    loadList() {
      this.loading = true;
      this.$request({
        url: 'plugin/distribution/mall/user/index',
        data: { page: this.page, keyword: this.search.keyword, level_id: this.search.level_id }
      }).then(res => {
        this.loading = false;
        if (res.code === 0) {
          this.list = res.data.list || [];
          this.pagination = res.data.pagination;
          this.levels = res.data.levels || [];
        } else {
          this.$message.error(res.msg || '加载失败');
        }
      }).catch(() => { this.loading = false; });
    },
    onSearch() { this.page = 1; this.loadList(); },
    onReset() { this.search = { keyword: '', level_id: '' }; this.page = 1; this.loadList(); },
    handlePageChange(val) { this.page = val; this.loadList(); },
    toggleInviter(row, action) {
      const tip = action === 'grant'
        ? `恢复「${row.nickname}」的分销资格？恢复后将重新参与分销佣金计算。`
        : `取消「${row.nickname}」的分销资格？取消后不再参与分销佣金计算，档案和历史数据保留。`;
      this.$confirm(tip, '提示', { type: action === 'grant' ? 'info' : 'warning' }).then(() => {
        this.$request({ url: 'plugin/distribution/mall/user/toggle-inviter', method: 'post', data: { user_id: row.id, action } })
          .then(res => {
            if (res.code === 0) { this.$message.success(res.msg); this.loadList(); }
            else this.$message.error(res.msg);
          });
      }).catch(() => {});
    },
  }
};
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
.filter-form { flex-shrink: 0; margin-bottom: 8px; ::v-deep .el-form-item { margin-bottom: 8px; } }
.shrink-0 { flex-shrink: 0; }
.flex-1   { flex: 1; }
.table-wrapper { flex: 1; position: relative; min-height: 0; }
.absolute-table-container { position: absolute; top: 0; left: 0; right: 0; bottom: 0; }
.pagination-container { padding-top: 10px; text-align: right; }
code { background: #f5f7fa; padding: 1px 6px; border-radius: 3px; color: #E6A23C; }
</style>
