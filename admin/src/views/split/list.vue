<template>
  <div class="app-container">
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <span>分账进件记录</span>
      </div>
      <div>
        <!-- 搜索表单 -->
        <el-form size="mini" ref="searchForm" :model="searchParams" :inline="true" label-width="100px">

          <el-form-item label="用户ID">
            <el-input v-model="searchParams.user_id" placeholder="请输入用户ID" clearable @keyup.enter.native="search"
            ></el-input>
          </el-form-item>
          <el-form-item label="外部商户号">
            <el-input v-model="searchParams.out_mer_code" placeholder="请输入外部商户号" clearable
                      @keyup.enter.native="search"
            ></el-input>
          </el-form-item>
          <el-form-item label="进件状态">
            <el-select v-model="searchParams.status" placeholder="请选择状态" clearable>
              <el-option label="处理中" value="PENDING"></el-option>
              <el-option label="审核中" value="DOING"></el-option>
              <el-option label="成功" value="SUCCESS"></el-option>
              <el-option label="失败" value="FAIL"></el-option>
            </el-select>
          </el-form-item>
          <el-form-item label="申请时间">
            <el-date-picker
              v-model="searchParams.search_date"
              type="daterange"
              value-format="yyyy-MM-dd"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              style="width: 240px"
            >
            </el-date-picker>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" icon="el-icon-search" @click="search">搜索</el-button>
            <el-button @click="resetSearch">重置</el-button>
          </el-form-item>
        </el-form>

        <!-- 表格 -->
        <el-table size="mini" :data="list" v-loading="loading" style="width: 100%;">
          <el-table-column prop="id" label="ID" width="80" fixed></el-table-column>
          <el-table-column label="申请用户" width="220">
            <template slot-scope="scope">
              <div v-if="scope.row.user" class="user-info-cell">
                <el-avatar :size="40" :src="scope.row.user.avatar_url"></el-avatar>
                <div class="user-details">
                  <div class="nickname" :title="scope.row.user.nickname">{{ scope.row.user.nickname }}</div>
                  <div class="user-id">ID: {{ scope.row.user.id }}</div>
                </div>
              </div>
              <div v-else>-</div>
            </template>
          </el-table-column>

          <el-table-column prop="out_mer_code" label="外部商户号" width="180"></el-table-column>

          <el-table-column label="进件状态" align="center">
            <template slot-scope="scope">
              <el-tag type="info" size="mini" v-if="scope.row.status=='WAIT'">未提交资料</el-tag>
              <el-tag type="info" size="mini" v-if="scope.row.status=='PENDING'">处理中</el-tag>
              <el-tag type="warning" size="mini" v-if="scope.row.status=='DOING'">审核中</el-tag>
              <el-tag type="success" size="mini" v-if="scope.row.status=='SUCCESS'">成功</el-tag>
              <el-tag type="danger" size="mini" v-if="scope.row.status=='FAIL'">失败</el-tag>
            </template>
          </el-table-column>

          <el-table-column prop="created_time_str" label="申请时间" align="center" width="160"></el-table-column>

          <el-table-column label="备注" prop="remark"></el-table-column>

          <el-table-column label="操作" fixed="right" align="center" width="100">
            <template slot-scope="scope">
              <el-button type="text" @click="showDetails(scope.row)">查看详情</el-button>
            </template>
          </el-table-column>
        </el-table>

        <!-- 分页 -->
        <div style="text-align: right;margin: 20px 0;">
          <el-pagination
            v-if="pagination"
            background
            :current-page.sync="searchParams.page"
            :page-size="searchParams.limit"
            layout="total, prev, pager, next, jumper"
            :total="pagination.total_count"
            @current-change="pageChange"
          >
          </el-pagination>
        </div>
      </div>

      <!-- 详情弹窗 -->
      <el-dialog title="进件详情" :visible.sync="detailDialogVisible" width="50%">
        <div v-if="detailItem">
          <el-descriptions :column="2" border>
            <el-descriptions-item label="记录ID">{{ detailItem.id }}</el-descriptions-item>
            <el-descriptions-item label="状态">
              <el-tag type="info" size="mini" v-if="detailItem.status=='WAIT'">等待资料提交</el-tag>
              <el-tag type="info" size="mini" v-if="detailItem.status=='PENDING'">处理中</el-tag>
              <el-tag type="warning" size="mini" v-if="detailItem.status=='DOING'">审核中</el-tag>
              <el-tag type="success" size="mini" v-if="detailItem.status=='SUCCESS'">成功</el-tag>
              <el-tag type="danger" size="mini" v-if="detailItem.status=='FAIL'">失败</el-tag>
            </el-descriptions-item>

            <el-descriptions-item label="用户ID">{{ detailItem.user_id }}</el-descriptions-item>
            <el-descriptions-item label="外部商户号">{{ detailItem.out_mer_code }}</el-descriptions-item>
            <el-descriptions-item label="第三方平台用户ID">{{ detailItem.platform_user_id }}</el-descriptions-item>
          </el-descriptions>
          <el-card header="请求报文" class="json-card">
            <pre>{{ JSON.stringify(JSON.parse(detailItem.request_data || '{}'), null, 2) }}</pre>
          </el-card>
          <el-card header="响应报文" class="json-card">
            <pre>{{ JSON.stringify(JSON.parse(detailItem.response_data || '{}'), null, 2) }}</pre>
          </el-card>
          <el-card header="异步回调" class="json-card">
            <pre>{{ JSON.stringify(JSON.parse(detailItem.notify_data || '{}'), null, 2) }}</pre>
          </el-card>
        </div>
        <span slot="footer" class="dialog-footer">
          <el-button @click="detailDialogVisible = false">关 闭</el-button>
        </span>
      </el-dialog>

    </el-card>
  </div>
</template>

<script>
export default {
  name: 'split-onboarding-log-list',
  data() {
    return {
      list: [],
      loading: false,
      pagination: null,
      searchParams: {
        page: 1,
        limit: 10,
        mch_id: '',
        user_id: '',
        status: '',
        out_mer_code: '',
        search_date: null
      },
      detailDialogVisible: false,
      detailItem: null
    }
  },
  created() {
    this.getList()
  },
  methods: {
    async getList() {
      this.loading = true
      try {
        const res = await this.$request({
          // [关键] 这里的URL要和后端控制器方法对应
          url: '/mall/split/onboarding-log-list',
          method: 'get',
          data: this.searchParams
        })
        if (res.code === 0) {
          this.list = res.data.list
          this.pagination = res.data.pagination
        } else {
          this.$message.error(res.msg)
        }
      } finally {
        this.loading = false
      }
    },
    search() {
      this.searchParams.page = 1
      this.getList()
    },
    resetSearch() {
      this.searchParams = {
        page: 1,
        limit: 10,
        mch_id: '',
        user_id: '',
        status: '',
        out_mer_code: '',
        search_date: null
      }
      this.getList()
    },
    pageChange(page) {
      this.searchParams.page = page
      this.getList()
    },
    showDetails(row) {
      this.detailItem = row
      this.detailDialogVisible = true
    }
  }
}
</script>

<style lang="scss" scoped>
.user-info-cell {
  display: flex;
  align-items: center;

  .user-details {
    margin-left: 10px;
  }

  .nickname {
    font-size: 13px;
    color: #333;
  }

  .user-id {
    font-size: 12px;
    color: #999;
  }
}

.json-card {
  margin-top: 15px;

  pre {
    background-color: #f5f5f5;
    padding: 10px;
    border-radius: 4px;
    white-space: pre-wrap;
    word-break: break-all;
  }
}
</style>
