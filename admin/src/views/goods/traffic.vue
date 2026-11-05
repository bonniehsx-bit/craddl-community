<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="page-header">
        <span class="page-title">商品流量</span>
        <span class="sort-tip"><i class="el-icon-sort-down" /> 按累计浏览次数从高到低排列</span>
      </div>
      <el-form :inline="true" :model="filters" size="small" class="filter-form shrink-0" @submit.native.prevent="search">
        <el-form-item>
          <el-input v-model="filters.keyword" clearable maxlength="100" placeholder="商品名称 / 商品ID" class="keyword-input" @keyup.enter.native="search" />
        </el-form-item>
        <el-form-item>
          <el-select v-model="filters.status" placeholder="商品状态" class="status-input">
            <el-option label="全部状态" :value="-1" />
            <el-option label="上架" :value="1" />
            <el-option label="下架" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="search">搜索</el-button>
          <el-button icon="el-icon-refresh" @click="reset">重置</el-button>
        </el-form-item>
      </el-form>
      <div class="scope-tip shrink-0">
        <i class="el-icon-info" /> 根据已登录用户足迹统计，包含商品详情与好物分享浏览；重复浏览累计计数，历史足迹从 1 次起计。
      </div>
      <div class="table-wrapper">
        <div class="absolute-table-container">
          <el-table v-loading="loading" :data="list" height="100%" size="small" border stripe empty-text="暂无符合条件的商品">
            <el-table-column label="排名" width="72" align="center">
              <template slot-scope="scope"><span class="rank" :class="{ 'rank-top': (page - 1) * limit + scope.$index < 3 }">{{ (page - 1) * limit + scope.$index + 1 }}</span></template>
            </el-table-column>
            <el-table-column prop="id" label="商品ID" width="110" align="center" />
            <el-table-column label="图片" width="76" align="center">
              <template slot-scope="{ row }"><el-image :src="row.cover_pic" fit="cover" class="goods-image"><span slot="error" class="image-empty"><i class="el-icon-picture-outline" /></span></el-image></template>
            </el-table-column>
            <el-table-column prop="name" label="商品名称" min-width="240" show-overflow-tooltip />
            <el-table-column label="状态" width="90" align="center">
              <template slot-scope="{ row }"><el-tag :type="row.status === 1 ? 'success' : 'info'" size="mini">{{ row.status === 1 ? '上架' : '下架' }}</el-tag></template>
            </el-table-column>
            <el-table-column label="累计浏览次数" width="140" align="right">
              <template slot-scope="{ row }"><span class="view-count">{{ row.view_count }}</span></template>
            </el-table-column>
            <el-table-column prop="visitor_count" label="浏览人数" width="110" align="right" />
            <el-table-column label="最近浏览时间" width="180" align="center">
              <template slot-scope="{ row }">{{ row.last_view_text || '—' }}</template>
            </el-table-column>
          </el-table>
        </div>
      </div>
      <div class="pagination-container shrink-0">
        <el-pagination :current-page="page" :page-size="limit" :page-sizes="[20, 50, 100]" :total="total_count" layout="total, sizes, prev, pager, next, jumper" @current-change="changePage" @size-change="changeSize" />
      </div>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'GoodsTraffic',
  data() {
    return { filters: { keyword: '', status: -1 }, page: 1, limit: 20, total_count: 0, list: [], loading: false }
  },
  created() { this.getList() },
  methods: {
    search() { this.page = 1; this.getList() },
    reset() { this.filters = { keyword: '', status: -1 }; this.search() },
    changePage(page) { this.page = page; this.getList() },
    changeSize(limit) { this.limit = limit; this.search() },
    async getList() {
      const requestId = (this._requestId || 0) + 1
      this._requestId = requestId
      this.loading = true
      try {
        const res = await this.$request({ url: '/mall/goods/traffic-list', method: 'get', data: { ...this.filters, page: this.page, limit: this.limit }})
        if (requestId !== this._requestId) return
        if (res.code === 0) {
          this.list = res.data.list || []
          this.total_count = res.data.pagination.total_count
        } else { this.$message.error(res.msg || '商品流量加载失败') }
      } catch (error) {
        if (requestId === this._requestId) this.$message.error('商品流量加载失败，请重试')
      } finally {
        if (requestId === this._requestId) this.loading = false
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.app-container { height: calc(100vh - 85px); display: flex; flex-direction: column; overflow: hidden; padding: 15px; box-sizing: border-box; background: #f0f2f5; }
.box-card { flex: 1; display: flex; flex-direction: column; border: none; min-height: 0; }
.box-card ::v-deep .el-card__body { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0; }
.page-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.page-title { font-size: 16px; font-weight: 600; color: #303133; }
.sort-tip, .scope-tip { color: #909399; font-size: 12px; }
.filter-form ::v-deep .el-form-item { margin-bottom: 14px; }
.keyword-input { width: 260px; }
.status-input { width: 130px; }
.scope-tip { padding-bottom: 14px; line-height: 20px; }
.shrink-0 { flex-shrink: 0; }
.table-wrapper { flex: 1; position: relative; min-height: 0; }
.absolute-table-container { position: absolute; inset: 0; }
.goods-image { width: 42px; height: 42px; border-radius: 6px; vertical-align: middle; }
.image-empty { display: flex; align-items: center; justify-content: center; height: 100%; color: #c0c4cc; background: #f5f7fa; }
.rank { color: #909399; font-variant-numeric: tabular-nums; }
.rank-top { font-weight: 600; color: #303133; }
.view-count { font-weight: 600; color: #303133; font-variant-numeric: tabular-nums; }
.pagination-container { padding-top: 16px; text-align: right; }
</style>
