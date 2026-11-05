<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="page-header">
        <div class="page-title">
          <i class="el-icon-sort-up" />
          <span>升级日志</span>
          <span class="tip">用户会员等级升级记录:升级前后等级 / 触发原因 / 说明</span>
        </div>
        <el-button type="primary" size="small" icon="el-icon-refresh" @click="getList">刷新</el-button>
      </div>

      <el-form :inline="true" class="filter-form shrink-0" size="small">
        <el-form-item>
          <el-input
            v-model="searchParams.keyword"
            placeholder="昵称 / 手机号 / 用户ID"
            clearable
            style="width: 220px;"
            @keyup.enter.native="search"
          />
        </el-form-item>
        <el-form-item>
          <el-select v-model="searchParams.reason_by" placeholder="升级原因" clearable style="width: 150px;">
            <el-option label="消费满额" :value="0" />
            <el-option label="购买商品" :value="1" />
            <el-option label="直推会员" :value="2" />
            <el-option label="后台调整" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="search">查询</el-button>
          <el-button icon="el-icon-refresh" @click="reset">重置</el-button>
        </el-form-item>
      </el-form>

      <div class="table-wrapper flex-1">
        <div class="absolute-table-container">
          <el-table
            v-loading="loading"
            :data="list"
            height="100%"
            size="small"
            border
            style="width: 100%;"
          >
            <el-table-column label="用户" min-width="200">
              <template slot-scope="{ row }">
                <div class="user-cell">
                  <img v-if="row.avatar_url" :src="row.avatar_url" class="avatar">
                  <span v-else class="avatar avatar-ph"><i class="el-icon-user" /></span>
                  <div class="user-info">
                    <div class="nick">{{ row.nickname || '未命名' }}</div>
                    <div class="sub">ID {{ row.user_id }} · {{ row.mobile || '-' }}</div>
                  </div>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="等级变化" width="210" align="center">
              <template slot-scope="{ row }">
                <el-tag size="mini" type="info">{{ row.before_level_name }}</el-tag>
                <i class="el-icon-right" style="margin: 0 6px; color: #67c23a;" />
                <el-tag size="mini" type="success">{{ row.after_level_name }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="升级原因" width="110" align="center">
              <template slot-scope="{ row }">
                <el-tag size="mini" :type="reasonTagType(row.reason_by)">{{ row.reason_text }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="说明" prop="marks" min-width="260" show-overflow-tooltip />
            <el-table-column label="升级时间" prop="created_time" width="160" align="center" show-overflow-tooltip />
          </el-table>
        </div>
      </div>

      <div class="pagination-container shrink-0">
        <el-pagination
          background
          layout="total, sizes, prev, pager, next, jumper"
          :total="pagination ? pagination.total_count : 0"
          :page-sizes="[10, 20, 50, 100]"
          :page-size="searchParams.limit"
          :current-page.sync="searchParams.page"
          @size-change="onSizeChange"
          @current-change="onPageChange"
        />
      </div>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'UserUpgradeLog',
  data() {
    return {
      loading: false,
      list: [],
      pagination: null,
      searchParams: { page: 1, limit: 20, keyword: '', reason_by: '' }
    }
  },
  created() {
    this.getList()
  },
  methods: {
    getList() {
      this.loading = true
      this.$request({ url: '/mall/user/upgrade-log', method: 'get', data: this.searchParams }).then(res => {
        if (res.code === 0) {
          this.list = res.data.list
          this.pagination = res.data.pagination
        }
      }).finally(() => {
        this.loading = false
      })
    },
    search() {
      this.searchParams.page = 1
      this.getList()
    },
    reset() {
      this.searchParams = { page: 1, limit: 20, keyword: '', reason_by: '' }
      this.getList()
    },
    onPageChange(p) {
      this.searchParams.page = p
      this.getList()
    },
    onSizeChange(s) {
      this.searchParams.limit = s
      this.searchParams.page = 1
      this.getList()
    },
    reasonTagType(by) {
      return { 0: 'primary', 1: 'warning', 2: 'success', 3: 'info' }[by] || 'info'
    }
  }
}
</script>

<style lang="scss" scoped>
/* —— §3 列表页标准布局(全屏 Flex + 表格内部滚动)—— */
.app-container {
  height: calc(100vh - 85px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  padding: 15px;
  box-sizing: border-box;
  background: #f0f2f5;
}
.box-card {
  flex: 1;
  display: flex;
  flex-direction: column;
  border: none;
}
.box-card ::v-deep .el-card__body {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  min-height: 0;
}
.shrink-0 { flex-shrink: 0; }
.flex-1 { flex: 1; }
.table-wrapper { flex: 1; position: relative; min-height: 0; }
.absolute-table-container { position: absolute; top: 0; left: 0; right: 0; bottom: 0; }
.pagination-container { padding-top: 10px; text-align: right; }

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

  .tip {
    font-size: 12px;
    font-weight: 400;
    color: #909399;
  }
}
.filter-form { margin-bottom: 8px; }

/* —— 单元格 —— */
.user-cell {
  display: flex;
  align-items: center;

  .avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    margin-right: 8px;
    object-fit: cover;
    flex-shrink: 0;
  }
  .avatar-ph {
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f0f2f5;
    color: #c0c4cc;
    font-size: 18px;
  }
  .user-info {
    min-width: 0;

    .nick { font-size: 13px; color: #303133; }
    .sub { font-size: 12px; color: #909399; }
  }
}
</style>
