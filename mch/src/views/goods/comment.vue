<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="page-header">
        <div class="page-title">
          <i class="el-icon-chat-dot-square"></i>
          <span>商品评价</span>
        </div>
        <div class="header-tip">
          评价由买家在订单完成后提交，此处<b>只读</b>。如遇恶意评价，请联系平台运营处理。
        </div>
      </div>

      <el-form size="small" :inline="true" class="filter-form shrink-0" @submit.native.prevent>
        <el-form-item>
          <el-input v-model="search.keyword" placeholder="商品名 / 评价内容" clearable style="width:220px"
                    @clear="getList" @keyup.enter.native="getList">
            <el-button slot="append" icon="el-icon-search" @click="getList"></el-button>
          </el-input>
        </el-form-item>
        <el-form-item>
          <el-radio-group v-model="search.grade_level" size="small" @change="getList">
            <el-radio-button label="">全部</el-radio-button>
            <el-radio-button :label="1">好评 {{ gradeCount.good }}</el-radio-button>
            <el-radio-button :label="2">中评 {{ gradeCount.medium }}</el-radio-button>
            <el-radio-button :label="3">差评 {{ gradeCount.bad }}</el-radio-button>
          </el-radio-group>
        </el-form-item>
      </el-form>

      <div class="absolute-table-container">
        <el-table v-loading="loading" :data="list" border stripe size="small" style="width:100%" height="100%">
          <el-table-column label="商品" min-width="220" show-overflow-tooltip>
            <template slot-scope="{ row }">
              <div class="g-cell">
                <el-image v-if="row.goods_pic" :src="row.goods_pic" fit="cover" class="g-pic" />
                <span class="g-name">{{ row.goods_name }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="买家" width="150" show-overflow-tooltip>
            <template slot-scope="{ row }">
              <div class="u-cell">
                <el-avatar :size="24" :src="row.avatar_url" />
                <span class="u-name">{{ row.nickname }}</span>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="评级" width="90" align="center">
            <template slot-scope="{ row }">
              <el-tag size="mini" :type="gradeType(row.grade_level)">{{ row.grade_text }}</el-tag>
            </template>
          </el-table-column>

          <el-table-column label="评价内容" min-width="260">
            <template slot-scope="{ row }">
              <div class="c-text">{{ row.content || '（该买家未填写文字）' }}</div>
              <div v-if="row.pic_list && row.pic_list.length" class="c-pics">
                <el-image v-for="(p, i) in row.pic_list" :key="i" :src="p" fit="cover"
                          class="c-pic" :preview-src-list="row.pic_list" />
              </div>
            </template>
          </el-table-column>

          <el-table-column label="追评" width="70" align="center">
            <template slot-scope="{ row }">
              <el-tag v-if="row.is_second === 1" size="mini" type="warning">追评</el-tag>
              <span v-else class="muted">—</span>
            </template>
          </el-table-column>

          <el-table-column prop="created_text" label="评价时间" width="150" />
        </el-table>
      </div>

      <div class="pagination-wrap">
        <el-pagination background layout="total, prev, pager, next"
                       :total="pagination.count || 0"
                       :page-size="pagination.pageSize || 20"
                       :current-page="pagination.page || 1"
                       @current-change="onPage" />
      </div>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'GoodsComment',
  data() {
    return {
      loading: false,
      list: [],
      pagination: {},
      gradeCount: { good: 0, medium: 0, bad: 0 },
      search: { keyword: '', grade_level: '' }
    }
  },
  created() {
    this.getList()
  },
  methods: {
    gradeType(level) {
      return level === 3 ? 'danger' : (level === 2 ? 'warning' : 'success')
    },
    onPage(page) {
      this.getList(page)
    },
    getList(page = 1) {
      this.loading = true
      this.$request({
        url: '/plugin/mch/admin/goods/comment-list',
        method: 'get',
        data: {
          page,
          limit: 20,
          keyword: this.search.keyword,
          grade_level: this.search.grade_level
        }
      }).then(e => {
        if (e.code === 0) {
          this.list = e.data.list || []
          this.pagination = e.data.pagination || {}
          this.gradeCount = e.data.grade_count || { good: 0, medium: 0, bad: 0 }
        } else {
          this.$message.error(e.msg)
        }
      }).finally(() => {
        this.loading = false
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.app-container { display: flex; flex-direction: column; height: 100%; }
.box-card { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
::v-deep .el-card__body { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
.page-header { display: flex; align-items: center; justify-content: space-between; }
.page-title { font-size: 15px; }
.header-tip { font-size: 12px; color: #909399; }
.shrink-0 { flex-shrink: 0; }
.absolute-table-container { flex: 1; position: relative; overflow: hidden; }
.pagination-wrap { padding-top: 10px; text-align: right; flex-shrink: 0; }

.g-cell { display: flex; align-items: center; }
.g-pic { width: 34px; height: 34px; border-radius: 4px; margin-right: 8px; flex-shrink: 0; }
.g-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.u-cell { display: flex; align-items: center; }
.u-name { margin-left: 6px; }
.c-text { line-height: 1.6; }
.c-pics { margin-top: 6px; }
.c-pic { width: 46px; height: 46px; border-radius: 4px; margin-right: 6px; }
.muted { color: #C0C4CC; }
</style>
