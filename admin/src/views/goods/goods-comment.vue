<template>
  <div class="app-container">
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <span>评价管理</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="openDialog()">新增虚拟评价</el-button>
      </div>

      <!-- 搜索与筛选 -->
      <el-form :model="searchParams" ref="searchForm" :inline="true" label-width="80px" size="small">
        <el-form-item label="关键词">
          <el-input v-model="searchParams.keyword" placeholder="商品名/用户昵称/评价内容" clearable
            style="width: 250px;"></el-input>
        </el-form-item>
        <el-form-item label="评价等级">
          <el-select v-model="searchParams.grade_level" placeholder="全部等级" clearable style="width: 120px;">
            <el-option label="全部等级" value=""></el-option>
            <el-option label="5 星" :value="5"></el-option>
            <el-option label="4 星" :value="4"></el-option>
            <el-option label="3 星" :value="3"></el-option>
            <el-option label="2 星" :value="2"></el-option>
            <el-option label="1 星" :value="1"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="评价时间">
          <el-date-picker v-model="searchParams.search_date" type="daterange" value-format="yyyy-MM-dd"
            range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期">
          </el-date-picker>
        </el-form-item>
        <el-form-item>
          <el-button type='primary' icon='el-icon-search' @click='search'>搜索</el-button>
          <el-button type='warning' icon='el-icon-refresh-left' @click='resetSearch'>重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 结果表格 -->
      <el-table :data="list" style="width: 100%" v-loading="loading" size="medium"
        :header-cell-style="{ background: '#fafafa', color: '#606266' }">
        <el-table-column prop="id" label="ID" width="80"></el-table-column>

        <el-table-column label="商品信息" width="300">
          <template slot-scope='scope'>
            <div class="goods-cell">
              <el-image :src="scope.row.cover_pic" fit="cover" class="goods-cover"></el-image>
              <div class="goods-name text-ellipsis-2">{{ scope.row.name }}</div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="评价用户" width="200">
          <template slot-scope='scope'>
            <div class="user-cell">
              <el-avatar :size="40" :src="scope.row.avatar_url"></el-avatar>
              <div class="user-name">{{ scope.row.nickname }}</div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="评价内容" min-width="250">
          <template slot-scope="scope">
            <div class="comment-cell">
              <el-rate :value="scope.row.grade_level" disabled show-score text-color="#ff9900"
                score-template="{value} 分"></el-rate>
              <div class="content-text">{{ scope.row.content }}</div>

              <div class="pic-list" v-if="scope.row.pic_list && scope.row.pic_list.length > 0">
                <el-image :src="scope.row.pic_list[0]" fit="cover" class="pic-item"
                  :preview-src-list="scope.row.pic_list">
                </el-image>
                <div v-if="scope.row.pic_list.length > 1" class="pic-count">共{{ scope.row.pic_list.length }}张</div>
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="评价类型" align="center" width="100">
          <template slot-scope='scope'>
            <el-tag :type="scope.row.is_second ? 'success' : ''" size="small">{{ scope.row.is_second ? '追评' : '首评'
              }}</el-tag>
          </template>
        </el-table-column>

        <el-table-column prop="created_time" label="评价时间" align="center" width="160"></el-table-column>

        <el-table-column label="操作" width="100" fixed="right" align="center">
          <template slot-scope="{row}">
            <el-popconfirm confirm-button-text='确定' cancel-button-text='取消' icon="el-icon-info" icon-color="red"
              title="确认删除这条评价吗？" @confirm="commentDelete(row.id)">
              <el-button type='text' size='mini' class="text-danger" slot="reference">删除</el-button>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>

      <div style="text-align: right;margin: 20px 0;">
        <el-pagination v-if="pagination" background :current-page.sync="searchParams.page"
          :page-size="pagination.pageSize" layout="total, prev, pager, next, jumper" :total="pagination.totalCount"
          @current-change="pageChange">
        </el-pagination>
      </div>
    </el-card>

    <!-- 新增/编辑弹窗 -->
    <el-dialog title="新增虚拟评价" :visible.sync="dialogVisible" width="50%" :close-on-click-modal="false"
      @close="resetForm">
      <el-form :model="form" ref="form" label-width="100px" size="small">
        <el-form-item label="商品" prop="goods_id" :rules="{ required: true, message: '请选择商品', trigger: 'change' }">
          <div v-if="form.goods" class="goods-cell" style="margin-bottom: 10px;">
            <el-image :src="form.goods.cover_pic" fit="cover" class="goods-cover"></el-image>
            <div class="goods-name text-ellipsis-2">{{ form.goods.name }}</div>
            <el-button type="text" style="margin-left: 10px;"
              @click="form.goods = null; form.goods_id = ''">重选</el-button>
          </div>
          <goods-picker v-else :status="-1" @selected="selectGoods">
            <el-button size="small" icon="el-icon-plus">选择商品</el-button>
          </goods-picker>
        </el-form-item>
        <el-form-item label="用户" prop="user_id" :rules="{ required: true, message: '请选择用户', trigger: 'change' }">
          <user-selector v-model="form.user" @input="selectUser" placeholder="选择评价用户"></user-selector>
        </el-form-item>
        <el-form-item label="评价分数" prop="grade_level">
          <el-rate v-model="form.grade_level" :max="5" show-text></el-rate>
        </el-form-item>
        <el-form-item label="评价内容" prop="content" :rules="{ required: true, message: '请输入评价内容', trigger: 'blur' }">
          <el-input type="textarea" :rows="4" v-model="form.content" placeholder="请输入评价内容"></el-input>
        </el-form-item>
        <el-form-item label="评价图片">
          <images-selector :count="6" v-model="form.pic_list"></images-selector>
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="dialogVisible = false" size="small">取 消</el-button>
        <el-button type="primary" @click="submit" :loading="submitLoading" size="small">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'goods-goods-comment',
  components: {
    GoodsPicker: () => import('@/components/common/GoodsPicker'),
    UserSelector: () => import('@/components/mall/UserSelector'),
    ImagesSelector: () => import('@/components/mall/ImagesSelector')
  },
  data() {
    const initialSearchParams = {
      page: 1,
      keyword: '',
      grade_level: '',
      search_date: []
    };
    return {
      loading: false,
      list: [],
      pagination: null,
      searchParams: { ...initialSearchParams },
      initialSearchParams,

      dialogVisible: false,
      submitLoading: false,
      form: {
        user_id: '',
        content: '',
        goods_id: '',
        grade_level: 5,
        pic_list: [],
        goods: null, // [新增] 用于存储选中的商品对象
        user: null   // [新增] 用于存储选中的用户对象
      }
    }
  },
  created() {
    this.getList();
  },
  methods: {
    async getList() {
      this.loading = true;
      try {
        let params = { ...this.searchParams };
        if (params.search_date && params.search_date.length === 2) {
          params.start_at = params.search_date[0];
          params.end_at = params.search_date[1];
        }
        delete params.search_date;

        const res = await this.$request({
          url: '/mall/goods/comment-list',
          data: params
        });
        if (res.code === 0) {
          this.list = res.data.list;
          this.pagination = res.data.pagination;
        }
      } finally {
        this.loading = false;
      }
    },
    search() {
      this.searchParams.page = 1;
      this.getList();
    },
    resetSearch() {
      this.searchParams = { ...this.initialSearchParams };
      this.getList();
    },
    pageChange(page) {
      this.searchParams.page = page;
      this.getList();
    },
    openDialog() {
      this.resetForm();
      this.dialogVisible = true;
    },
    resetForm() {
      this.form = {
        user_id: '',
        content: '',
        goods_id: '',
        grade_level: 5,
        pic_list: [],
        goods: null,
        user: null
      };
      if (this.$refs.form) {
        this.$refs.form.clearValidate();
      }
    },
    submit() {
      this.$refs.form.validate(async (valid) => {
        if (valid) {
          this.submitLoading = true;
          try {
            await this.$request({
              url: "/mall/goods/comment",
              method: "post",
              data: { ...this.form, pic_list: JSON.stringify(this.form.pic_list) }
            });
            this.$message.success('新增评价成功');
            this.dialogVisible = false;
            this.getList();
          } finally {
            this.submitLoading = false;
          }
        }
      });
    },
    selectGoods(list) {
      if (list && list.length > 0) {
        this.form.goods = list[0];
        this.form.goods_id = list[0].id;
        if (this.$refs.form) {
          this.$refs.form.validateField('goods_id');
        }
      }
    },
    selectUser(user) {
      if (user) {
        this.form.user_id = user.id;
        if (this.$refs.form) {
          this.$refs.form.validateField('user_id');
        }
      } else {
        this.form.user_id = '';
      }
    },
    async commentDelete(id) {
      this.loading = true;
      try {
        await this.$request({
          url: '/mall/goods/comment-delete',
          method: 'post',
          data: { id }
        });
        this.$message.success('删除成功');
        // 优化：如果当前页只剩一条数据，删除后请求前一页
        if (this.list.length === 1 && this.searchParams.page > 1) {
          this.searchParams.page--;
        }
        this.getList();
      } finally {
        this.loading = false;
      }
    }
  }
}
</script>

<style lang="scss" scoped>
.box-card {
  min-height: calc(100vh - 124px);
}

.el-form--inline .el-form-item {
  margin-bottom: 18px;
}

.el-table {
  margin-top: 10px;
}

.text-danger {
  color: #F56C6C;
}

// 表格单元格样式
.goods-cell {
  display: flex;
  align-items: center;

  .goods-cover {
    width: 60px;
    height: 60px;
    border-radius: 4px;
    flex-shrink: 0;
  }

  .goods-name {
    margin-left: 10px;
    font-size: 13px;
    color: #303133;
    line-height: 1.5;
    // ... 省略号样式 ...
  }
}

.user-cell {
  display: flex;
  align-items: center;

  .user-name {
    margin-left: 10px;
    font-size: 13px;
  }
}

.comment-cell {
  .content-text {
    font-size: 13px;
    line-height: 1.6;
    color: #606266;
    margin-top: 5px;
  }

  .pic-list {
    margin-top: 10px;
    display: flex;
    align-items: center;

    .pic-item {
      width: 60px;
      height: 60px;
      border-radius: 4px;
      margin-right: 10px;
      cursor: pointer;
      flex-shrink: 0;
    }

    .pic-count {
      color: #909399;
      font-size: 12px;
    }
  }
}
</style>
