<template>
  <el-card class="chart-card traffic-rank" shadow="never">
    <div slot="header" class="chart-header">
      <div class="traffic-title">
        <span class="header-title"><i class="el-icon-view" /> 商品流量 Top 5</span>
        <el-tooltip content="根据已登录用户足迹累计统计，包含商品详情及好物分享浏览；历史足迹从1次起计。" placement="top">
          <span class="traffic-scope">累计浏览 <i class="el-icon-info" /></span>
        </el-tooltip>
      </div>
      <el-button type="text" @click="$router.push('/goods/traffic')">查看全部 <i class="el-icon-arrow-right" /></el-button>
    </div>
    <div v-if="error" class="traffic-error">
      <span>商品流量加载失败</span>
      <el-button type="text" @click="getList">重新加载</el-button>
    </div>
    <el-table v-else v-loading="loading" :data="list" height="350" size="small" class="rank-table" empty-text="暂无商品流量数据">
      <el-table-column label="排名" width="70" align="center">
        <template slot-scope="scope"><div class="rank-badge" :class="'rank-' + (scope.$index + 1)">{{ scope.$index + 1 }}</div></template>
      </el-table-column>
      <el-table-column label="商品信息" min-width="200" show-overflow-tooltip>
        <template slot-scope="{ row }">
          <div class="goods-info">
            <el-image :src="row.cover_pic" fit="cover" class="goods-img"><div slot="error" class="image-slot"><i class="el-icon-picture-outline" /></div></el-image>
            <span class="goods-name">{{ row.name }}</span>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="浏览次数" width="110" align="right">
        <template slot-scope="{ row }"><span class="sales-text">{{ row.view_count }}</span></template>
      </el-table-column>
      <el-table-column prop="visitor_count" label="浏览人数" width="100" align="right" />
      <el-table-column label="最近浏览时间" width="180" align="center">
        <template slot-scope="{ row }">{{ row.last_view_text || '—' }}</template>
      </el-table-column>
    </el-table>
  </el-card>
</template>

<script>
export default {
  name: 'GoodsTrafficRank',
  data() { return { list: [], loading: false, error: false } },
  created() { this.getList() },
  methods: {
    async getList() {
      if (this.loading) return
      this.loading = true
      this.error = false
      try {
        const res = await this.$request({ url: '/mall/goods/traffic-list', method: 'get', data: { page: 1, limit: 5 }})
        if (res.code === 0) this.list = res.data.list || []
        else this.error = true
      } catch (error) {
        this.error = true
      } finally {
        this.loading = false
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.traffic-rank {
  border: none; border-radius: 12px;
  ::v-deep .el-card__header { padding: 15px 20px; border-bottom: 1px solid #f9fafb; }
}
.chart-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.header-title { font-size: 16px; font-weight: 600; color: #1f2937; i { margin-right: 6px; color: var(--admin-accent-text); } }
.traffic-title { display: flex; align-items: center; gap: 14px; min-width: 0; }
.traffic-scope { color: #909399; font-size: 12px; white-space: nowrap; }
.traffic-error { height: 350px; display: flex; align-items: center; justify-content: center; gap: 12px; color: #909399; }
.image-slot { height: 100%; display: flex; align-items: center; justify-content: center; color: #c0c4cc; background: #f5f7fa; }
.rank-table {
  ::v-deep th { background-color: #f9fafb; color: #6b7280; font-weight: 500; border-bottom: none; }
  ::v-deep td { border-bottom: 1px solid #f3f4f6; padding: 12px 0; }
}
.rank-badge {
  width: 22px; height: 22px; line-height: 22px; text-align: center; border-radius: 6px;
  background: #f3f4f6; color: #6b7280; font-size: 12px; font-weight: bold; margin: 0 auto;
  &.rank-1 { background: linear-gradient(135deg, #ffd700, #fdb931); color: #fff; }
  &.rank-2 { background: linear-gradient(135deg, #e0e0e0, #bdbdbd); color: #fff; }
  &.rank-3 { background: linear-gradient(135deg, #e6a23c, #cd7f32); color: #fff; }
}
.goods-info {
  display: flex; align-items: center; min-width: 0;
  .goods-img { width: 36px; height: 36px; border-radius: 4px; margin-right: 10px; border: 1px solid #f0f0f0; flex-shrink: 0; }
  .goods-name { font-size: 13px; color: #1f2937; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
}
.sales-text { font-variant-numeric: tabular-nums; font-weight: 600; color: #1f2937; }
</style>
