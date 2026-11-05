<template>
  <div class="app-container">
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <span>全体商户商品分类管理</span>
      </div>

      <!-- 搜索商户 -->
      <el-form :inline="true" :model="searchParams" size="mini" @submit.native.prevent="searchMch">
        <el-form-item label="搜索商户">
          <el-input
            v-model="searchParams.keyword"
            placeholder="输入商户ID、名称、手机号"
            clearable
            style="width: 300px;"
          ></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="searchMch">搜索</el-button>
        </el-form-item>
      </el-form>

      <!-- 商户折叠面板 -->
      <div v-loading="mchListLoading">
        <el-collapse v-model="activeCollapse" @change="handleCollapseChange">
          <el-collapse-item
            v-for="mch in mchList"
            :key="mch.id"
            :name="mch.id"
          >
            <!-- 折叠面板标题 -->
            <template slot="title">
              <div class="collapse-title">
                <span class="mch-name">{{ mch.mch_name }}</span>
                <span class="mch-id"> (ID: {{ mch.id }})</span>
              </div>
            </template>

            <!-- 面板内容：分类表格 -->
            <div class="category-table-wrapper" v-loading="mch.loadingCategories">
              <div style="margin-bottom: 15px; text-align: right;">
                <el-button type="primary" size="mini" icon="el-icon-plus" @click="handleAdd(mch, 0)">
                  添加一级分类
                </el-button>
              </div>
              <el-table
                :data="mch.categories || []"
                style="width: 100%;"
                row-key="id"
                border
                :tree-props="{children: 'children', hasChildren: 'hasChildren'}"
                :header-cell-style="{background:'#fafafa',color:'#606266'}"
                size="small"
              >
                <el-table-column prop="id" label="分类ID" width="100"></el-table-column>
                <el-table-column prop="name" label="分类名称"></el-table-column>
                <el-table-column prop="sort" label="排序" width="100"></el-table-column>
                <el-table-column label="操作" width="300" align="center">
                  <template slot-scope="scope">
                    <el-button v-if="!scope.row.parent_id" type="text" icon="el-icon-plus" @click="handleAdd(mch, scope.row.id, scope.row.name)">
                      添加子分类
                    </el-button>
                    <el-button type="text" icon="el-icon-edit" @click="handleEdit(scope.row, mch)">编辑</el-button>
                    <el-button type="text" icon="el-icon-delete" style="color: #F56C6C;" @click="handleDelete(scope.row, mch)">删除</el-button>
                  </template>
                </el-table-column>
              </el-table>
              <div v-if="mch.categories && mch.categories.length === 0" class="empty-text">
                该商户暂无商品分类
              </div>
            </div>
          </el-collapse-item>
        </el-collapse>
        <div v-if="mchList.length === 0 && !mchListLoading" class="empty-text">
          未找到相关商户
        </div>
      </div>

      <!-- 商户列表分页 -->
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

    <!-- 分类新增/编辑弹窗 -->
    <el-dialog
      :title="dialog.title"
      :visible.sync="dialog.visible"
      width="40%"
      :close-on-click-modal="false"
      @closed="closeDialog"
    >
      <el-form :model="dialog.form" :rules="dialog.rules" ref="form" label-width="100px" size="mini">
        <el-form-item label="分类名称" prop="name">
          <el-input v-model="dialog.form.name" placeholder="请输入分类名称"></el-input>
        </el-form-item>
        <el-form-item label="排序" prop="sort">
          <el-input-number v-model="dialog.form.sort" :min="0" :max="9999"></el-input-number>
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="dialog.visible = false" size="small">取 消</el-button>
        <el-button type="primary" @click="submitForm" :loading="dialog.loading" size="small">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'MchAllCategories',
  data() {
    return {
      mchListLoading: false,
      mchList: [],
      pagination: null,
      searchParams: { page: 1, limit: 10, keyword: '' },
      activeCollapse: [], // 控制展开的面板，可以是数组支持展开多个
      dialog: {
        visible: false,
        loading: false,
        title: '',
        mch: null, // [核心] 记录当前正在操作哪个商户
        form: {},
        rules: {
          name: [{ required: true, message: '分类名称不能为空', trigger: 'blur' }],
        },
      },
    };
  },
  created() {
    this.getMchList();
  },
  methods: {
    // 1. 获取商户列表
    async getMchList() {
      this.mchListLoading = true;
      try {
        const res = await this.$request({
          url: '/plugin/mch/mall/mch/index',
          data: this.searchParams
        });
        if (res.code === 0) {
          // 为每个商户对象添加用于存放分类和加载状态的属性
          res.data.list.forEach(mch => {
            this.$set(mch, 'categories', null); // 初始为null，表示尚未加载
            this.$set(mch, 'loadingCategories', false);
          });
          this.mchList = res.data.list;
          this.pagination = res.data.pagination;
        } else {
          this.$message.error(res.msg);
        }
      } finally {
        this.mchListLoading = false;
      }
    },
    searchMch() {
      this.searchParams.page = 1;
      this.activeCollapse = []; // 重置展开状态
      this.getMchList();
    },
    pageChange(page) {
      this.searchParams.page = page;
      this.activeCollapse = []; // 重置展开状态
      this.getMchList();
    },

    // 2. [核心] 当折叠面板展开/收起时触发
    handleCollapseChange(activeIds) {
      // 遍历当前所有展开的面板ID
      activeIds.forEach(mchId => {
        const mch = this.mchList.find(item => item.id === mchId);
        // 如果找到商户，并且其分类数据尚未加载，则去加载
        if (mch && mch.categories === null) {
          this.loadCategoriesForMch(mch);
        }
      });
    },

    // 3. 为指定的商户加载分类数据
    async loadCategoriesForMch(mch) {
      mch.loadingCategories = true;
      try {
        const res = await this.$request({
          url: '/plugin/mch/mall/cat/index',
          data: { mch_id: mch.id }
        });
        if (res.code === 0) {
          mch.categories = res.data.list || [];
        } else {
          this.$message.error(res.msg);
          mch.categories = []; // 加载失败给个空数组
        }
      } finally {
        mch.loadingCategories = false;
      }
    },

    // 4. 分类 CRUD 操作
    handleAdd(mch, parentId = 0, parentName = '') {
      this.dialog.mch = mch; // 记录商户
      this.dialog.title = parentId === 0 ? '添加一级分类' : `在「${parentName}」下添加子分类`;
      this.dialog.form = {
        id: null,
        mch_id: mch.id,
        name: '',
        parent_id: parentId,
        sort: 100,
      };
      this.dialog.visible = true;
    },
    handleEdit(row, mch) {
      this.dialog.mch = mch; // 记录商户
      this.dialog.title = '编辑分类';
      this.dialog.form = { ...row }; // 复制数据
      this.dialog.visible = true;
    },
    handleDelete(row, mch) {
      this.$confirm(`此操作将永久删除分类 "${row.name}", 是否继续?`, '警告', {
        type: 'error'
      }).then(async () => {
        const res = await this.$request({
          url: '/plugin/mch/mall/cat/delete',
          method: 'post',
          data: { id: row.id, mch_id: mch.id }
        });
        if (res.code === 0) {
          this.$message.success('删除成功!');
          // 重新加载该商户的分类列表
          this.loadCategoriesForMch(mch);
        } else {
          this.$message.error(res.msg);
        }
      }).catch(() => {});
    },

    // 5. 弹窗表单提交
    submitForm() {
      this.$refs.form.validate(async (valid) => {
        if (valid) {
          this.dialog.loading = true;
          try {
            const res = await this.$request({
              url: '/plugin/mch/mall/cat/edit',
              method: 'post',
              data: this.dialog.form
            });
            if (res.code === 0) {
              this.$message.success('保存成功!');
              this.dialog.visible = false;
              // 成功后，重新加载当前操作商户的分类列表
              this.loadCategoriesForMch(this.dialog.mch);
            } else {
              this.$message.error(res.msg);
            }
          } finally {
            this.dialog.loading = false;
          }
        }
      });
    },
    closeDialog() {
      this.$refs.form.resetFields();
      this.dialog.mch = null;
    },
  },
};
</script>

<style scoped>
.collapse-title {
  display: flex;
  align-items: center;
  font-size: 14px;
}
.mch-name {
  font-weight: 500;
  color: #303133;
}
.mch-id {
  color: #909399;
  margin-left: 8px;
}
.category-table-wrapper {
  padding: 10px 15px;
}
.empty-text {
  text-align: center;
  color: #909399;
  padding: 20px 0;
  font-size: 14px;
}
</style>
