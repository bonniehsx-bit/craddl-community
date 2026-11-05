<template>
  <div class="app-container">
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <span>操作日志</span>
        <span class="header-tip">双击任意一行查看变更详情</span>
      </div>

      <el-form size="mini" ref="searchForm" :inline="true" label-width="68px">
        <el-form-item label="模型">
          <el-input v-model="searchForm.model" placeholder="如 User / Order" clearable style="width: 150px"
            @keyup.enter.native="search" />
        </el-form-item>
        <el-form-item label="数据ID">
          <el-input v-model="searchForm.model_id" placeholder="数据ID" clearable style="width: 110px"
            @keyup.enter.native="search" />
        </el-form-item>
        <el-form-item label="操作人ID">
          <el-input v-model="searchForm.admin_id" placeholder="管理员ID" clearable style="width: 110px"
            @keyup.enter.native="search" />
        </el-form-item>
        <el-form-item label="关键字">
          <el-input v-model="searchForm.keyword" placeholder="备注 / 变更内容" clearable style="width: 180px"
            @keyup.enter.native="search" />
        </el-form-item>
        <el-form-item label="创建时间">
          <el-date-picker value-format="yyyy-MM-dd HH:mm:ss" v-model="searchForm.search_date" type="datetimerange"
            range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="search">搜索</el-button>
          <el-button icon="el-icon-refresh-left" @click="reset">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 列表只留可扫读的列:before/after 是大段 JSON,直接铺进表格会把行撑爆、
           而且一眼也看不出改了什么,统一挪进 drawer 里做字段级对比。 -->
      <el-table size="mini" :data="list" style="width: 100%" v-loading="is_loading"
        @row-dblclick="openDetail" class="log-table" empty-text="暂无日志">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="操作人" width="140" show-overflow-tooltip>
          <template slot-scope="s">
            {{ s.row.admin_name || '-' }}
            <span v-if="s.row.admin_id" class="dim">#{{ s.row.admin_id }}</span>
          </template>
        </el-table-column>
        <el-table-column label="模型" width="150" show-overflow-tooltip>
          <template slot-scope="s">
            <span :title="s.row.model">{{ s.row.model_short || s.row.model || '-' }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="model_id" label="数据ID" width="100" />
        <el-table-column prop="remarks" label="备注" width="110" show-overflow-tooltip />
        <el-table-column label="变更字段" min-width="220" show-overflow-tooltip>
          <template slot-scope="s">
            <span v-if="changedKeys(s.row).length" class="changed-keys">{{ changedKeys(s.row).join('、') }}</span>
            <span v-else class="dim">—</span>
          </template>
        </el-table-column>
        <el-table-column prop="created_time" label="时间" width="160" />
        <el-table-column label="操作" width="70" align="center">
          <template slot-scope="s">
            <el-button type="text" size="mini" @click="openDetail(s.row)">详情</el-button>
          </template>
        </el-table-column>
      </el-table>

      <pagination :pagination="pagination" v-model="searchForm.page" @pageChange="getList" />
    </el-card>

    <log-detail-drawer :visible.sync="detailVisible" :row="current" type="admin" />
  </div>
</template>

<script>
import LogDetailDrawer from './LogDetailDrawer'

export default {
  components: { LogDetailDrawer },
  data() {
    return {
      is_loading: false,
      searchForm: {
        page: 1,
        search_date: null,
        model: '',
        model_id: '',
        admin_id: '',
        keyword: ''
      },
      pagination: null,
      list: [],
      detailVisible: false,
      current: null
    }
  },
  created() {
    this.getList()
  },
  methods: {
    search() {
      this.searchForm.page = 1   // 换条件必须回第一页,否则会停在旧页码上看到空列表
      this.list = []
      this.pagination = null
      this.getList()
    },
    reset() {
      this.searchForm = { page: 1, search_date: null, model: '', model_id: '', admin_id: '', keyword: '' }
      this.pagination = null
      this.getList()
    },
    openDetail(row) {
      this.current = row
      this.detailVisible = true
    },
    // 列表里直接标出改了哪些字段 —— 不点开也能扫读,这是查日志最常用的信息
    changedKeys(row) {
      const b = this.safeParse(row.before_update)
      const a = this.safeParse(row.after_update)
      const keys = Array.from(new Set([...Object.keys(b), ...Object.keys(a)]))
      return keys.filter(k => JSON.stringify(b[k]) !== JSON.stringify(a[k]))
    },
    safeParse(str) {
      if (!str) return {}
      if (typeof str === 'object') return str
      try {
        const o = JSON.parse(str)
        return (o && typeof o === 'object' && !Array.isArray(o)) ? o : {}
      } catch (e) {
        return {}   // 历史数据格式不保证,解析失败降级成空,不能让整个列表崩掉
      }
    },
    getList() {
      this.is_loading = true
      this.$request({
        url: '/mall/log/admin',
        data: this.searchForm
      }).then(res => {
        this.is_loading = false
        this.list = res.data.list
        this.pagination = res.data.pagination
      }).catch(() => {
        this.is_loading = false
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.header-tip { float: right; font-size: 12px; color: #909399; }
.dim { color: #c0c4cc; font-size: 12px; }
.changed-keys { color: var(--admin-accent-text); font-size: 12px; }
.log-table ::v-deep tbody tr { cursor: pointer; }
</style>
