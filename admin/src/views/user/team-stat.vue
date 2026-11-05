<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="page-header">
        <div class="page-title">
          <i class="el-icon-s-data" />
          <span>团队数据</span>
          <span class="tip">展示 user_team_stat:团队人数 / 消费 / 等级分布 / 各直推线,下单或改等级后刷新即可核对</span>
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
          <el-select v-model="searchParams.order_by" style="width: 150px;">
            <el-option label="团队人数 ↓" value="team_count" />
            <el-option label="团队消费 ↓" value="team_spend" />
            <el-option label="最近更新 ↓" value="updated" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-select v-model="searchParams.dirty" placeholder="全部状态" clearable style="width: 130px;">
            <el-option label="待重检" :value="1" />
            <el-option label="已同步" :value="0" />
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
            @row-dblclick="openDetail"
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
            <el-table-column label="等级" prop="level_name" width="90" align="center" show-overflow-tooltip />
            <el-table-column label="直推" prop="direct_count" width="66" align="center" />
            <el-table-column label="团队人数" prop="team_count" width="90" align="center" />
            <el-table-column label="个人消费(实付/已结)" width="160" align="center">
              <template slot-scope="{ row }">¥{{ row.personal_spend_paid }} / {{ row.personal_spend_settled }}</template>
            </el-table-column>
            <el-table-column label="团队消费(实付/已结)" width="170" align="center">
              <template slot-scope="{ row }"><b>¥{{ row.team_spend_paid }}</b> / {{ row.team_spend_settled }}</template>
            </el-table-column>
            <el-table-column label="订单数" prop="personal_order_count" width="66" align="center" />
            <el-table-column label="状态" width="90" align="center">
              <template slot-scope="{ row }">
                <el-tag v-if="row.dirty" type="warning" size="mini">待重检</el-tag>
                <el-tag v-else type="success" size="mini">已同步</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="更新时间" prop="updated_time" width="150" align="center" show-overflow-tooltip />
            <el-table-column label="操作" width="76" align="center" fixed="right">
              <template slot-scope="{ row }">
                <el-button type="text" @click="openDetail(row)">详情</el-button>
              </template>
            </el-table-column>
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

    <!-- 详情:各 map 明细 -->
    <el-drawer :title="detail.title" :visible.sync="detail.visible" size="480px" :wrapper-closable="true">
      <div v-if="detail.row" class="detail-body">
        <div class="d-section">
          <div class="d-title">直推等级分布</div>
          <div v-if="mapLen(detail.row.direct_level_map)" class="chips">
            <el-tag v-for="(c, lid) in detail.row.direct_level_map" :key="'d' + lid" size="small">等级{{ lid }}: {{ c }}人</el-tag>
          </div>
          <div v-else class="empty">无</div>
        </div>
        <div class="d-section">
          <div class="d-title">团队等级分布</div>
          <div v-if="mapLen(detail.row.team_level_map)" class="chips">
            <el-tag v-for="(c, lid) in detail.row.team_level_map" :key="'t' + lid" size="small" type="info">等级{{ lid }}: {{ c }}人</el-tag>
          </div>
          <div v-else class="empty">无</div>
        </div>
        <div class="d-section">
          <div class="d-title">各直推线(legs)</div>
          <el-table v-if="mapLen(detail.row.team_legs_map)" :data="legsRows(detail.row.team_legs_map)" size="mini" border>
            <el-table-column label="直推用户ID" prop="uid" align="center" />
            <el-table-column label="该线团队人数" prop="count" align="center" />
            <el-table-column label="该线最高等级" prop="max_level" align="center" />
          </el-table>
          <div v-else class="empty">无</div>
        </div>
        <div class="d-section">
          <div class="d-title">已购商品数量</div>
          <div v-if="mapLen(detail.row.purchased_goods_map)" class="chips">
            <el-tag v-for="(qty, gid) in detail.row.purchased_goods_map" :key="'g' + gid" size="small" type="warning">商品{{ gid }}: {{ qty }}件</el-tag>
          </div>
          <div v-else class="empty">无</div>
        </div>
      </div>
    </el-drawer>
  </div>
</template>

<script>
export default {
  name: 'UserTeamStat',
  data() {
    return {
      loading: false,
      list: [],
      pagination: null,
      searchParams: { page: 1, limit: 20, keyword: '', order_by: 'team_count', dirty: '' },
      detail: { visible: false, title: '', row: null }
    }
  },
  created() {
    this.getList()
  },
  methods: {
    getList() {
      this.loading = true
      this.$request({ url: '/mall/user/team-stat', method: 'get', data: this.searchParams }).then(res => {
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
      this.searchParams = { page: 1, limit: 20, keyword: '', order_by: 'team_count', dirty: '' }
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
    openDetail(row) {
      this.detail = { visible: true, title: (row.nickname || ('ID' + row.user_id)) + ' 的团队明细', row }
    },
    mapLen(m) {
      return m && Object.keys(m).length > 0
    },
    legsRows(m) {
      return Object.keys(m).map(uid => ({ uid, count: (m[uid] || {}).count, max_level: (m[uid] || {}).max_level }))
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

/* —— 详情抽屉 —— */
.detail-body {
  padding: 0 20px 20px;

  .d-section {
    margin-bottom: 20px;

    .d-title {
      font-size: 13px;
      font-weight: 600;
      color: #303133;
      margin-bottom: 8px;
      padding-left: 8px;
      border-left: 3px solid var(--admin-accent-text);
    }
    .chips {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
    .empty {
      color: #c0c4cc;
      font-size: 12px;
    }
  }
}
</style>
