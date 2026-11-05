<template>
  <div class="app-container">
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <span>商品审核管理</span>
      </div>

      <!-- 选项卡：待审核 / 已驳回 / 已通过 -->
      <el-tabs v-model="activeTabName" @tab-click="handleTabClick">
        <el-tab-pane label="待审核" name="pending">
          <span slot="label">待审核 <el-badge v-if="pendingCount > 0" :value="pendingCount" class="item" /></span>
        </el-tab-pane>
        <el-tab-pane label="已驳回" name="rejected"></el-tab-pane>
        <el-tab-pane label="已通过(历史)" name="passed"></el-tab-pane>
      </el-tabs>

      <!-- 搜索栏 -->
      <el-form size="mini" :inline="true" :model="searchForm" class="demo-form-inline">
        <el-form-item label="商品名称">
          <el-input v-model="searchForm.keyword" placeholder="请输入商品名称/ID"></el-input>
        </el-form-item>
        <el-form-item label="商家名称">
          <el-input v-model="searchForm.mch_name" placeholder="请输入商家名称"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="search">查询</el-button>
          <el-button icon="el-icon-refresh" @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 数据表格 -->
      <el-table
        v-loading="loading"
        :data="list"
        border
        size="mini"
        style="width: 100%">

        <el-table-column prop="id" label="ID" width="80" align="center"></el-table-column>

        <el-table-column label="商品信息" min-width="250">
          <template slot-scope="scope">
            <div style="display: flex; align-items: center;">
              <el-image
                style="width: 50px; height: 50px; margin-right: 10px; border-radius: 4px;"
                :src="scope.row.cover_pic"
                :preview-src-list="[scope.row.cover_pic]">
              </el-image>
              <div>
                <div style="font-weight: bold; margin-bottom: 5px;">{{ scope.row.name }}</div>
                <el-tag size="mini" type="info">¥ {{ scope.row.price }}</el-tag>
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="所属商家" min-width="150">
          <template slot-scope="scope">
            <div v-if="scope.row.mch">
              <div>{{ scope.row.mch.mch_name }}</div>
              <div style="color: #909399; font-size: 12px;">ID: {{ scope.row.mch.id }}</div>
            </div>
            <el-tag v-else type="success" size="mini">平台自营</el-tag>
          </template>
        </el-table-column>

        <el-table-column label="分类" prop="cat_name" width="150">
          <template slot-scope="scope">
            {{ scope.row.cat ? scope.row.cat.name : '-' }}
          </template>
        </el-table-column>

        <el-table-column label="提交时间" prop="created_at" width="160">
          <template slot-scope="scope">
            {{ scope.row.created_at }} <!-- 假设后端已格式化，若无请用 filters -->
          </template>
        </el-table-column>

        <!-- 审核状态列 -->
        <el-table-column label="当前状态" width="100" align="center">
          <template slot-scope="scope">
            <el-tag v-if="scope.row.is_check == 0" type="warning">待审核</el-tag>
            <el-tag v-else-if="scope.row.is_check == 1" type="success">已通过</el-tag>
            <el-tag v-else-if="scope.row.is_check == 2" type="danger">已驳回</el-tag>
          </template>
        </el-table-column>

        <!-- 操作列 -->
        <el-table-column label="操作" width="200" fixed="right" align="center">
          <template slot-scope="scope">

            <!-- 待审核状态的操作 -->
            <div v-if="scope.row.is_check == 0">
              <el-button
                type="success"
                size="mini"
                icon="el-icon-check"
                @click="handlePass(scope.row)">
                通过
              </el-button>
              <el-button
                type="danger"
                size="mini"
                icon="el-icon-close"
                @click="openRejectDialog(scope.row)">
                驳回
              </el-button>
            </div>

            <!-- 已驳回状态的操作 -->
            <div v-if="scope.row.is_check == 2">
              <el-button
                type="text"
                size="mini"
                @click="openRejectDialog(scope.row)">
                修改驳回原因
              </el-button>
              <el-button
                type="text"
                size="mini"
                style="color: #F56C6C"
                @click="handleDelete(scope.row)">
                删除
              </el-button>
            </div>

            <!-- 已通过状态的操作 -->
            <div v-if="scope.row.is_check == 1">
              <el-button type="text" disabled>已审核</el-button>
            </div>

          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div style="text-align: right; margin-top: 20px;">
        <el-pagination
          @current-change="pageChange"
          layout="total,prev, pager, next, jumper"
          :page-size="pagination.limit"
          :current-page="pagination.current_page"
          :total="pagination.total_count">
        </el-pagination>
      </div>

      <!-- 驳回弹窗 -->
      <el-dialog title="商品审核驳回" :visible.sync="rejectDialog.visible" width="30%">
        <el-form :model="rejectDialog.form" label-width="80px">
          <el-form-item label="商品名称">
            <el-input v-model="rejectDialog.goodsName" disabled></el-input>
          </el-form-item>
          <el-form-item label="驳回原因">
            <el-input
              type="textarea"
              :rows="4"
              placeholder="请输入驳回原因，商家可见"
              v-model="rejectDialog.form.remark">
            </el-input>
          </el-form-item>
        </el-form>
        <span slot="footer" class="dialog-footer">
          <el-button @click="rejectDialog.visible = false" size="mini">取消</el-button>
          <el-button type="primary" @click="submitReject" :loading="rejectDialog.loading" size="mini">确认驳回</el-button>
        </span>
      </el-dialog>

    </el-card>
  </div>
</template>

<script>
export default {
  name: 'GoodsAudit',
  data() {
    return {
      activeTabName: 'pending', // 默认显示待审核
      loading: false,
      list: [],
      pagination: {
        current_page: 1,
        limit: 10,
        total_count: 0
      },
      pendingCount: 0, // 待审核数量小红点（可选，需要接口支持）
      searchForm: {
        page: 1,
        keyword: '',
        mch_name: '',
        is_check: 0, // 默认查询待审核 (0)
      },
      // 驳回弹窗配置
      rejectDialog: {
        visible: false,
        loading: false,
        goodsName: '',
        form: {
          id: null,
          status: 2, // 2代表驳回
          remark: ''
        }
      }
    };
  },
  mounted() {
    this.getList();
  },
  methods: {
    // 切换标签页
    handleTabClick(tab) {
      if (tab.name === 'pending') {
        this.searchForm.is_check = 0;
      } else if (tab.name === 'rejected') {
        this.searchForm.is_check = 2;
      } else if (tab.name === 'passed') {
        this.searchForm.is_check = 1;
      }
      this.searchForm.page = 1;
      this.getList();
    },

    // 获取列表
    async getList() {
      this.loading = true;
      try {
        const res = await this.$request({
          url: '/mall/goods/list',
          method: 'get',
          data: this.searchForm
        });
        if (res.code === 0) {
          this.list = res.data.list;
          this.pagination = res.data.pagination;
        }
      } catch (e) {
        console.error(e);
      } finally {
        this.loading = false;
      }
    },

    // 搜索
    search() {
      this.searchForm.page = 1;
      this.getList();
    },

    // 重置
    resetSearch() {
      this.searchForm.keyword = '';
      this.searchForm.mch_name = '';
      this.search();
    },

    // 分页切换
    pageChange(page) {
      this.searchForm.page = page;
      this.getList();
    },

    // 通过审核
    handlePass(row) {
      this.$confirm(`确定通过商品 "${row.name}" 的审核吗？`, '审核通过', {
        confirmButtonText: '确定通过',
        cancelButtonText: '取消',
        type: 'success'
      }).then(async () => {
        this.loading = true;
        try {
          const res = await this.$request({
            url: '/mall/goods/audit-submit', // 新的审核接口
            method: 'post',
            data: {
              id: row.id,
              status: 1, // 1代表通过
              remark: '审核通过'
            }
          });
          if (res.code === 0) {
            this.$message.success('审核通过');
            this.getList();
          } else {
            this.$message.error(res.msg);
          }
        } finally {
          this.loading = false;
        }
      }).catch(() => {});
    },

    // 打开驳回弹窗
    openRejectDialog(row) {
      this.rejectDialog.goodsName = row.name;
      this.rejectDialog.form.id = row.id;
      this.rejectDialog.form.remark = ''; // 清空上次记录
      this.rejectDialog.visible = true;
    },

    // 提交驳回
    async submitReject() {
      if (!this.rejectDialog.form.remark) {
        this.$message.warning('请输入驳回原因');
        return;
      }
      this.rejectDialog.loading = true;
      try {
        const res = await this.$request({
          url: '/mall/goods/audit-submit', // 新的审核接口
          method: 'post',
          data: this.rejectDialog.form
        });
        if (res.code === 0) {
          this.$message.success('已驳回');
          this.rejectDialog.visible = false;
          this.getList();
        } else {
          this.$message.error(res.msg);
        }
      } finally {
        this.rejectDialog.loading = false;
      }
    },

    // 删除商品
    handleDelete(row) {
      this.$confirm('删除后无法恢复，确定删除该商品吗?', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        this.$request({
          url: '/mall/goods/delete',
          method: 'post',
          data: { id: row.id }
        }).then(res => {
          if (res.code === 0) {
            this.$message.success(res.msg);
            this.getList();
          } else {
            this.$message.error(res.msg);
          }
        });
      }).catch(() => {});
    }
  }
};
</script>

<style scoped>
.item {
  margin-top: -5px;
  margin-left: 5px;
}
</style>
