<template>
  <div class="app-container">
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <span>商户商品管理</span>
        <el-button style="float: right; padding: 3px 0" type="text" icon="el-icon-plus" @click="handleAddNewGoods">
          新增商品
        </el-button>
      </div>

      <!-- 筛选区域 -->
      <el-form :inline="true" :model="searchParams" size="mini" @submit.native.prevent="handleSearch">
        <el-form-item label="所属商户">
          <el-input v-model="searchParams.mch_keyword" placeholder="商户ID/名称/手机号" clearable style="width: 220px;"></el-input>
        </el-form-item>
        <el-form-item label="商品信息">
          <el-input v-model="searchParams.keyword" placeholder="商品ID/名称" clearable style="width: 220px;"></el-input>
        </el-form-item>
        <el-form-item label="审核状态">
          <el-select v-model="searchParams.is_check" clearable placeholder="全部">
            <el-option label="待审核" :value="0"></el-option>
            <el-option label="已通过" :value="1"></el-option>
            <el-option label="已驳回" :value="2"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="上架状态">
          <el-select v-model="searchParams.status" clearable placeholder="全部">
            <el-option label="仓库中" :value="0"></el-option>
            <el-option label="销售中" :value="1"></el-option>
            <el-option label="已售罄" :value="2"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="handleSearch">搜索</el-button>
        </el-form-item>
      </el-form>

      <!-- 商品表格 -->
      <el-table
        :data="list"
        v-loading="loading"
        style="width: 100%"
        :header-cell-style="{background:'#fafafa',color:'#606266'}"
        @sort-change="handleSortChange"
      >
        <el-table-column prop="id" label="ID" width="80" sortable="custom"></el-table-column>
        <el-table-column label="商品信息" width="350">
          <template slot-scope="scope">
            <div class="goods-info">
              <el-image :src="scope.row.cover_pic" fit="cover" class="goods-cover" lazy></el-image>
              <div class="goods-details">
                <div class="goods-name">{{ scope.row.name }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="所属商户" width="250">
          <template slot-scope="scope">
            <div class="mch-info" v-if="scope.row.mch">
              <el-avatar shape="square" size="small" :src="scope.row.mch.logo"></el-avatar>
              <div class="mch-name">{{ scope.row.mch.mch_name }}</div>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="price" label="价格" width="100" sortable="custom"></el-table-column>
        <el-table-column prop="stock" label="库存" width="100" sortable="custom"></el-table-column>
        <el-table-column prop="sales" label="销量" width="100" sortable="custom"></el-table-column>
        <el-table-column label="上架状态" align="center">
          <template slot-scope="scope">
            <el-tag :type="scope.row.status === 1 ? 'success' : 'info'" size="small">
              {{ scope.row.status === 1 ? '销售中' : '仓库中' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="审核状态" align="center">
          <template slot-scope="scope">
            <el-tag :type="getCheckStatusTagType(scope.row.is_check)" size="small">
              {{ getCheckStatusText(scope.row.is_check) }}
            </el-tag>
            <el-popover v-if="scope.row.is_check === 2 && scope.row.check_remark" placement="top" trigger="hover" :content="'驳回理由: ' + scope.row.check_remark">
              <i slot="reference" class="el-icon-info icon-tip"></i>
            </el-popover>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="创建时间" width="180" sortable="custom"></el-table-column>
        <el-table-column label="操作" fixed="right" width="220" align="center">
          <template slot-scope="scope">
            <el-button type="text" @click="handleEdit(scope.row)">编辑</el-button>
            <el-button v-if="scope.row.is_check === 0" type="text" @click="openReviewDialog(scope.row)">审核</el-button>
            <el-button v-if="scope.row.is_check === 1 && scope.row.status === 1" type="text" style="color: #E6A23C;" @click="handleStatusChange(scope.row, 0)">强制下架</el-button>
            <el-button type="text" style="color: #F56C6C;" @click="handleDelete(scope.row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div style="text-align: right; margin: 20px 0;">
        <el-pagination
          v-if="pagination"
          background
          :current-page.sync="searchParams.page"
          :page-size="searchParams.limit"
          layout="total, prev, pager, next, jumper"
          :total="pagination.total_count"
          @current-change="pageChange"
        ></el-pagination>
      </div>
    </el-card>

    <!-- 审核弹窗 -->
    <el-dialog title="商品审核" :visible.sync="reviewDialog.visible" width="40%" :close-on-click-modal="false">
      <div v-if="reviewDialog.goods">
        <p><strong>商品名称：</strong> {{ reviewDialog.goods.name }}</p>
        <p><strong>所属商户：</strong> {{ reviewDialog.goods.mch.mch_name }}</p>
        <el-form :model="reviewDialog.form" ref="reviewForm" label-width="80px" size="mini">
          <el-form-item label="审核结果" prop="is_check">
            <el-radio-group v-model="reviewDialog.form.is_check">
              <el-radio :label="1">通过</el-radio>
              <el-radio :label="2">驳回</el-radio>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="驳回理由" v-if="reviewDialog.form.is_check === 2" prop="check_remark">
            <el-input type="textarea" v-model="reviewDialog.form.check_remark" placeholder="请输入驳回理由"></el-input>
          </el-form-item>
        </el-form>
      </div>
      <span slot="footer">
        <el-button @click="reviewDialog.visible = false" size="small">取 消</el-button>
        <el-button type="primary" @click="submitReview" :loading="reviewDialog.loading" size="small">确 定</el-button>
      </span>
    </el-dialog>

    <!-- [重构] 一体化商品编辑弹窗 -->
    <el-dialog
      :title="editDialog.title"
      :visible.sync="editDialog.visible"
      width="80%"
      top="5vh"
      v-if="editDialog.visible"
      :close-on-click-modal="false"
      @closed="closeEditDialog"
    >
      <!-- 前置步骤：仅在“新增”模式下显示 -->
      <div v-if="editDialog.mode === 'add'" class="pre-step-container">
        <el-form label-width="120px" size="mini">
          <el-form-item label="选择所属商户" required>
            <el-select
              v-model="editDialog.mchId"
              filterable
              remote
              :remote-method="searchMch"
              placeholder="请输入商户ID、名称、手机号"
              :loading="editDialog.mchSearchLoading"
              style="width: 50%;"
              @change="onMchSelected"
            >
              <el-option v-for="item in editDialog.mchList" :key="item.id" :label="item.mch_name" :value="item.id">
                <div class="mch-info-select"><el-avatar shape="square" size="small" :src="item.logo"></el-avatar><span>{{ item.mch_name }} (ID: {{ item.id }})</span></div>
              </el-option>
            </el-select>
          </el-form-item>
        </el-form>
      </div>

      <goods-edit-form
        v-if="editDialog.mchId"
        :mch-id="editDialog.mchId"
        :goods-id="editDialog.goodsId"
        :initial-cat-id="editDialog.catId"
        :category-options="editDialog.categoryOptions"
        @success="onEditSuccess"
        @cancel="editDialog.visible = false"
      ></goods-edit-form>
    </el-dialog>

  </div>
</template>

<script>
import GoodsEditForm from './components/GoodsEditForm.vue';

export default {
  name: 'MchGoodsList',
  components: { GoodsEditForm },
  data() {
    return {
      list: [],
      loading: false,
      pagination: null,
      searchParams: { page: 1, limit: 10, keyword: '', mch_keyword: '', status: '', is_check: '', sort_prop: 'id', sort_order: 'descending' },
      reviewDialog: { visible: false, loading: false, goods: null, form: { id: null, is_check: 1, check_remark: '' }},
      editDialog: {
        visible: false,
        mode: 'add',
        title: '新增商品',
        mchId: null,
        goodsId: null,
        catId: null,
        mchList: [],
        mchSearchLoading: false,
        categoryOptions: [],
      }
    };
  },
  created() { this.getList(); },
  methods: {
    async getList() {
      this.loading = true;
      try {
        const res = await this.$request({ url: '/plugin/mch/mall/goods/index', data: this.searchParams });
        if (res.code === 0) {
          this.list = res.data.list;
          this.pagination = res.data.pagination;
        } else {
          this.$message.error(res.msg);
        }
      } finally {
        this.loading = false;
      }
    },
    handleSearch() { this.searchParams.page = 1; this.getList(); },
    pageChange(page) { this.searchParams.page = page; this.getList(); },
    handleSortChange({ prop, order }) {
      this.searchParams.sort_prop = prop;
      this.searchParams.sort_order = order;
      this.handleSearch();
    },
    getCheckStatusText(s) { return { 0: '待审核', 1: '已通过', 2: '已驳回' }[s] || '未知'; },
    getCheckStatusTagType(s) { return { 0: 'warning', 1: 'success', 2: 'danger' }[s] || 'info'; },
    openReviewDialog(row) {
      this.reviewDialog.goods = row;
      this.reviewDialog.form = { id: row.id, is_check: 1, check_remark: '' };
      this.reviewDialog.visible = true;
    },
    async submitReview() {
      if (this.reviewDialog.form.is_check === 2 && !this.reviewDialog.form.check_remark) {
        return this.$message.warning('请填写驳回理由');
      }
      this.reviewDialog.loading = true;
      try {
        const res = await this.$request({ url: '/plugin/mch/mall/goods/review', method: 'post', data: this.reviewDialog.form });
        if (res.code === 0) {
          this.$message.success('审核成功');
          this.reviewDialog.visible = false;
          this.getList();
        } else {
          this.$message.error(res.msg);
        }
      } finally {
        this.reviewDialog.loading = false;
      }
    },
    handleStatusChange(row, status) {
      this.$confirm('确定要强制下架该商品吗?', '提示', { type: 'warning' }).then(async () => {
        const res = await this.$request({ url: '/plugin/mch/mall/goods/update-status', method: 'post', data: { id: row.id, status } });
        if (res.code === 0) {
          this.$message.success('下架成功');
          this.getList();
        } else {
          this.$message.error(res.msg);
        }
      });
    },
    handleDelete(row) {
      this.$confirm(`此操作将永久删除商品 "${row.name}", 是否继续?`, '警告', { type: 'error' }).then(async () => {
        const res = await this.$request({ url: '/plugin/mch/mall/goods/delete', method: 'post', data: { id: row.id } });
        if (res.code === 0) {
          this.$message.success('删除成功!');
          this.getList();
        } else {
          this.$message.error(res.msg);
        }
      });
    },
    handleAddNewGoods() {
      this.editDialog = {
        visible: true, mode: 'add', title: '新增商品', mchId: null, goodsId: null, catId: null,
        mchList: [], mchSearchLoading: false, categoryOptions: []
      };
    },
    async handleEdit(row) {
      this.editDialog = {
        visible: true, mode: 'edit', title: `编辑商品 (ID: ${row.id})`, mchId: row.mch.id,
        goodsId: row.id, catId: row.cat_id, mchList: [], mchSearchLoading: false, categoryOptions: []
      };
      await this.fetchCategoryOptions(row.mch.id);
    },
    async searchMch(keyword) {
      if (!keyword) return;
      this.editDialog.mchSearchLoading = true;
      const res = await this.$request({ url: '/plugin/mch/mall/mch/index', data: { keyword, limit: 50 } });
      this.editDialog.mchSearchLoading = false;
      if (res.code === 0) {
        this.editDialog.mchList = res.data.list;
      }
    },
    async onMchSelected(mchId) {
      if (!mchId) {
        this.editDialog.categoryOptions = []; return;
      }
      await this.fetchCategoryOptions(mchId);
    },
    async fetchCategoryOptions(mchId) {
      try {
        const res = await this.$request({ url: '/plugin/mch/mall/cat/index', data: { mch_id: mchId } });
        this.editDialog.categoryOptions = (res.code === 0) ? res.data.list : [];
        if (res.code !== 0) this.$message.error('加载商户分类失败: ' + res.msg);
      } catch(e) {
        this.editDialog.categoryOptions = [];
      }
    },
    onEditSuccess() {
      this.editDialog.visible = false;
      this.getList();
    },
    closeEditDialog() {}
  }
};
</script>

<style scoped>
.goods-info { display: flex; align-items: center; }
.goods-cover { width: 60px; height: 60px; border-radius: 4px; margin-right: 10px; flex-shrink: 0; background-color: #f5f7fa; }
.goods-details { flex-grow: 1; }
.goods-name { font-size: 14px; color: #303133; display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; }
.mch-info { display: flex; align-items: center; gap: 8px; }
.mch-name { font-size: 13px; }
.mch-info-select { display: flex; align-items: center; gap: 8px; }
.icon-tip { color: var(--admin-accent-text); margin-left: 5px; cursor: pointer; }
.pre-step-container { padding: 10px 20px; border-bottom: 1px solid #DCDFE6; margin-bottom: 20px; }
</style>
