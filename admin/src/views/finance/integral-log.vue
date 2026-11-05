<template>
  <div class="app-container">
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <span>积分明细</span>
      </div>

      <el-form ref="searchForm" :model="searchForm" :inline="true" label-width="80px" @submit.native.prevent="handleSearch">
        <el-form-item label="用户昵称" prop="nickname">
          <el-input v-model="searchForm.nickname" placeholder="请输入用户昵称" clearable />
        </el-form-item>
        <el-form-item label="用户ID" prop="user_id">
          <el-input v-model="searchForm.user_id" placeholder="请输入用户ID" clearable />
        </el-form-item>
        <el-form-item label="关键词" prop="keyword">
          <el-input v-model="searchForm.keyword" placeholder="搜索标题或详情" clearable />
        </el-form-item>
        <el-form-item label="业务类型" prop="biz_type">
          <el-select v-model="searchForm.biz_type" placeholder="全部类型" clearable style="width: 180px;">
            <el-option v-for="(name, key) in bizTypes" :key="key" :label="name" :value="key" />
          </el-select>
        </el-form-item>
        <el-form-item label="创建时间" prop="search_date">
          <el-date-picker
            v-model="searchForm.search_date"
            type="datetimerange"
            value-format="yyyy-MM-dd HH:mm:ss"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            :default-time="['00:00:00', '23:59:59']"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="handleSearch">搜索</el-button>
          <el-button icon="el-icon-refresh" @click="resetForm">重置</el-button>
          <el-button type="success" icon="el-icon-download" :loading="exportLoading" @click="exportExcel">导出</el-button>
        </el-form-item>
      </el-form>

      <el-table v-loading="isLoading" :data="list" style="width: 100%" border stripe>
        <el-table-column prop="id" label="ID" width="80" align="center" />
        <el-table-column label="用户信息" width="250">
          <template slot-scope="scope">
            <div class="user-info">
              <el-avatar :size="40" :src="scope.row.avatar_url" />
              <div class="user-details">
                <div class="nickname">{{ scope.row.nickname }}</div>
                <div class="user-id">ID: {{ scope.row.user_id }}</div>
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="变动积分" prop="integral" align="center" width="120">
          <template slot-scope="scope">
            <span :class="scope.row.type === 1 ? 'integral-income' : 'integral-expense'">
              {{ scope.row.type === 1 ? '+' : '-' }}{{ scope.row.integral }}
            </span>
          </template>
        </el-table-column>

        <el-table-column label="变动后余额" prop="current" align="center" width="120" />

        <el-table-column label="收支" align="center" width="90">
          <template slot-scope="scope">
            <el-tag v-if="scope.row.type === 1" type="success" effect="dark">收入</el-tag>
            <el-tag v-else-if="scope.row.type === 0" type="danger" effect="dark">支出</el-tag>
            <el-tag v-else type="info">未知</el-tag>
          </template>
        </el-table-column>

        <el-table-column label="业务类型" align="center" width="130">
          <template slot-scope="scope">
            <el-tag v-if="scope.row.biz_type_name" size="mini" effect="plain">{{ scope.row.biz_type_name }}</el-tag>
            <span v-else class="biz-empty" title="历史数据或未经积分网关记录的流水">—</span>
          </template>
        </el-table-column>

        <el-table-column label="标题" prop="title" width="200" />

        <el-table-column label="详情" prop="content" show-overflow-tooltip />

        <el-table-column prop="created_at" label="创建时间" width="180" align="center" />
      </el-table>

      <pagination v-model="page" :pagination="pagination" @pageChange="getList" />
    </el-card>
  </div>
</template>

<script>
export default {
  data() {
    return {
      isLoading: false,
      exportLoading: false,
      page: 1,
      pagination: null,
      list: [],
      // 业务类型字典(后端按插件注册表下发,前端不硬编码)
      bizTypes: {},
      // 【优化】将所有搜索条件整合到一个对象中
      searchForm: {
        keyword: '',
        user_id: '',
        nickname: '',
        biz_type: '',
        search_date: []
      }
    }
  },
  created() {
    if (this.$route.query && this.$route.query.user_id) {
      this.searchForm.user_id = this.$route.query.user_id
    }
    this.getList()
  },
  methods: {
    // 【优化】提炼出获取请求参数的公共方法
    getSearchParams(isExport = 0) {
      return {
        page: this.page,
        is_export: isExport,
        ...this.searchForm
      }
    },
    // 【优化】搜索处理函数
    handleSearch() {
      this.page = 1 // 搜索时重置到第一页
      this.getList()
    },
    // 【新增】重置表单
    resetForm() {
      this.$refs.searchForm.resetFields()
      this.handleSearch()
    },
    // 【优化】获取列表
    getList() {
      this.isLoading = true
      this.$request({
        url: '/mall/finance/integral-log',
        method: 'get', // 建议明确请求方法
        data: this.getSearchParams() // get请求使用params
      }).then(res => {
        if (res.code === 0) {
          this.list = res.data.list
          this.pagination = res.data.pagination
          this.bizTypes = res.data.biz_types || {}
        }
      }).finally(() => {
        this.isLoading = false
      })
    },
    // 【优化】导出Excel
    exportExcel() {
      this.exportLoading = true
      this.$request({
        url: '/mall/finance/integral-log',
        method: 'get',
        data: this.getSearchParams(1)
      }).then(res => {
        if (res.code === 0) {
          // 假设后端直接返回可供导出的数据
          const header = ['ID', '用户昵称', '用户ID', '创建时间', '业务类型', '标题', '详情', '变动积分', '变动后余额']
          const fields = ['id', 'nickname', 'user_id', 'created_at', 'biz_type_name', 'title', 'content', 'integral', 'current']
          this.$export(header, fields, res.data.list, '积分明细记录')
        }
      }).finally(() => {
        this.exportLoading = false
      })
    }
  }
}
</script>

<style scoped>
/* 用户信息单元格样式 */
.user-info {
  display: flex;
  align-items: center;
}
.user-details {
  margin-left: 10px;
}
.nickname {
  font-weight: 500;
  color: #303133;
}
.user-id {
  font-size: 12px;
  color: #909399;
}

/* 积分样式 */
.integral-income {
  color: #67C23A; /* 绿色代表收入 */
  font-weight: bold;
}
.integral-expense {
  color: #F56C6C; /* 红色代表支出 */
  font-weight: bold;
}
/* 业务类型为空(历史数据 / 未经积分网关记录的流水) */
.biz-empty {
  color: #c0c4cc;
  cursor: help;
}
</style>
