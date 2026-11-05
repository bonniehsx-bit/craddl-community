<template>
  <div class="app-container">
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <div class="header-container">
          <span>分类列表</span>
          <div class="search-bar">
            <el-input
              v-model="searchParams.keyword"
              placeholder="请输入分类名称搜索"
              clearable
              @clear="handleSearch"
              @keyup.enter.native="handleSearch"
            >
              <el-button slot="append" icon="el-icon-search" @click="handleSearch"></el-button>
            </el-input>
          </div>
          <el-button style="margin-left: 20px;" type="primary" size="small" @click="handleEdit(null)">添加分类</el-button>
        </div>
      </div>
      <div>
        <el-table :data="list" border row-key="id" v-loading="loading">

          <el-table-column type="expand">
            <template slot-scope="scope">
              <div v-if="scope.row.children && scope.row.children.length > 0" class="child-table-container">
                <el-table :data="scope.row.children" border :show-header="false">
                  <el-table-column width="80"></el-table-column>
                  <el-table-column prop="id" label="ID" width="80"></el-table-column>
                  <el-table-column prop="sort" label="排序" width="80"></el-table-column>
                  <el-table-column prop="name" label="名称">
                    <template slot-scope="childScope">
                      <span class="child-name-label">└─</span> {{ childScope.row.name }}
                    </template>
                  </el-table-column>
                  <el-table-column label="缩略图" width="80">
                    <template slot-scope="childScope">
                      <image-view :src="childScope.row.cover_pic" :size="40"></image-view>
                    </template>
                  </el-table-column>


                  <el-table-column label="状态" width="80">
                    <template slot-scope="childScope">
                      <el-switch
                        v-model="childScope.row.is_show"
                        :active-value="1"
                        :inactive-value="0"
                        @change="catShowChange(childScope.row)">
                      </el-switch>
                    </template>
                  </el-table-column>
                  <el-table-column label="操作" width="120">
                    <template slot-scope="{row: childRow}">
                      <el-tooltip content="编辑" placement="top">
                        <el-button type="primary" size="mini" icon="el-icon-edit-outline" circle @click="handleEdit(childRow.id)"></el-button>
                      </el-tooltip>
                      <el-tooltip content="删除" placement="top">
                        <el-button type="danger" size="mini" icon="el-icon-delete-solid" circle @click="catDelete(childRow)"></el-button>
                      </el-tooltip>
                    </template>
                  </el-table-column>
                </el-table>
              </div>
            </template>
          </el-table-column>

          <!-- 一级分类列 -->
          <el-table-column prop="id" label="ID" width="80"></el-table-column>
          <el-table-column prop="sort" label="排序" width="80"></el-table-column>
          <el-table-column prop="name" label="名称"></el-table-column>
          <el-table-column label="缩略图" width="80">
            <template slot-scope="scope">
              <image-view :src="scope.row.cover_pic" :size="40"></image-view>
            </template>
          </el-table-column>


          <el-table-column label="状态" width="80">
            <template slot-scope="scope">
              <el-switch
                v-model="scope.row.is_show"
                :active-value="1"
                :inactive-value="0"
                @change="catShowChange(scope.row)">
              </el-switch>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="120">
            <template slot-scope="{row}">
              <el-tooltip content="编辑" placement="top">
                <el-button type="primary" size="mini" icon="el-icon-edit-outline" circle @click="handleEdit(row.id)"></el-button>
              </el-tooltip>
              <el-tooltip content="删除" placement="top">
                <el-button type="danger" size="mini" icon="el-icon-delete-solid" circle @click="catDelete(row)"></el-button>
              </el-tooltip>
            </template>
          </el-table-column>
        </el-table>
      </div>
    </el-card>

    <cat-edit-dialog
      :visible.sync="dialogVisible"
      :cat-id="currentCatId"
      @closed="onDialogClosed"
      @saved="onSaved"
    ></cat-edit-dialog>
  </div>
</template>

<script>
import CatEditDialog from './CatEditDialog.vue';
// 假设您有一个 ImageViewer 组件
// import ImageView from '@/components/common/ImageView.vue';

export default {
  name: 'goods-cat',
  components: { CatEditDialog /*, ImageView*/ },
  data() {
    return {
      loading: false,
      list: [],
      searchParams: {
        keyword: '',
      },
      dialogVisible: false,
      currentCatId: null,
    };
  },
  mounted() {
    this.getList();
  },
  methods: {
    async getList() {
      this.loading = true;
      try {
        const res = await this.$request({ url: '/mall/goods/cat-tree', data: this.searchParams });
        if (res.code === 0) {
          this.list = res.data.list;
        }
      } finally {
        this.loading = false;
      }
    },
    handleSearch() {
      this.getList();
    },
    async catShowChange(row) {
      const newStatus = row.is_show;
      try {
        const res = await this.$request({
          url: '/mall/goods/cat-show',
          method: 'post',
          data: { id: row.id, is_show: newStatus }
        });
        if (res.code !== 0) {
          row.is_show = newStatus === 1 ? 0 : 1; // 恢复原状
          this.$message.error(res.msg);
        } else {
          this.$message.success('状态更新成功');
        }
      } catch(e) {
        row.is_show = newStatus === 1 ? 0 : 1;
      }
    },
    catDelete(row) {
      this.$confirm(`确定要删除分类 "${row.name}" 吗？`, '提示', { type: 'warning' })
        .then(async () => {
          const res = await this.$request({
            url: '/mall/goods/cat-delete',
            method: 'post',
            data: { id: row.id }
          });
          if (res.code === 0) {
            this.$message.success('删除成功');
            this.getList();
          } else {
            this.$message.error(res.msg);
          }
        }).catch(() => {});
    },
    handleEdit(id) {
      this.currentCatId = id;
      this.dialogVisible = true;
    },
    onDialogClosed() {
      this.currentCatId = null;
    },
    onSaved() {
      this.getList();
    }
  }
}
</script>

<style scoped>
.header-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.search-bar {
  width: 300px;
  margin-left: auto;
}
.child-table-container {
  padding: 10px 0px 10px 50px;
  background-color: #f9f9f9;
}
.child-name-label {
  color: #999;
  margin-right: 5px;
}
.tip-text {
  font-size: 12px;
  color: #C1C1C1;
}
/* [核心新增] 比例信息样式 */
.rate-info {
  font-size: 12px;
  line-height: 1.6;
}
.rate-info > div {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
