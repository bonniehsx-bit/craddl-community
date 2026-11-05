<template>
  <div>
    <el-card class="box-card" shadow="never">
      <div slot="header" class="clearfix">
        <span>用户领取记录</span>
      </div>

      <!-- 搜索栏 -->
      <el-form :inline="true" size="small" class="search-bar" @submit.native.prevent>
        <el-form-item>
          <el-input v-model="search.user_keyword" placeholder="用户昵称 / 手机号 / ID" clearable style="width: 200px"
            @keyup.enter.native="toSearch" @clear="toSearch"></el-input>
        </el-form-item>
        <el-form-item>
          <el-input v-model="search.coupon_keyword" :placeholder="$noun('coupon') + '名称'" clearable style="width: 160px"
            @keyup.enter.native="toSearch" @clear="toSearch"></el-input>
        </el-form-item>
        <el-form-item>
          <el-select v-model="search.status" placeholder="状态" clearable style="width: 110px" @change="toSearch">
            <el-option label="未使用" :value="0"></el-option>
            <el-option label="已使用" :value="1"></el-option>
            <el-option label="已过期" :value="2"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-select v-model="search.type" placeholder="类型" clearable style="width: 110px" @change="toSearch">
            <el-option label="满减券" :value="0"></el-option>
            <el-option label="折扣券" :value="1"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-select v-model="search.owner" placeholder="所属" clearable style="width: 110px" @change="toSearch">
            <el-option label="平台券" value="platform"></el-option>
            <el-option label="商家券" value="mch"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-date-picker v-model="search.date" type="daterange" value-format="yyyy-MM-dd" range-separator="至"
            start-placeholder="领取开始" end-placeholder="领取结束" style="width: 250px" @change="toSearch"></el-date-picker>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="toSearch">搜索</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 数据表格 -->
      <el-table size="small" :data="list" style="width: 100%" v-loading="loading" border stripe>
        <el-table-column prop="id" label="ID" width="70" align="center"></el-table-column>

        <!-- 用户信息列 -->
        <el-table-column label="持有用户" min-width="150">
          <template slot-scope="scope">
            <div style="display: flex; align-items: center;">
              <el-avatar :size="30" :src="scope.row.user_avatar" icon="el-icon-user-solid" style="margin-right: 10px; flex-shrink: 0;"></el-avatar>
              <div style="overflow: hidden;">
                <div style="text-overflow: ellipsis; white-space: nowrap;">{{ scope.row.user_name }}</div>
                <div style="font-size: 12px; color: #909399;">ID: {{ scope.row.user_id }}</div>
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column prop="coupon_name" label="优惠券名称" min-width="150" show-overflow-tooltip></el-table-column>

        <el-table-column label="优惠内容" min-width="160">
          <template slot-scope="scope">
            <div v-if="scope.row.type == 0">
              <el-tag size="mini" effect="plain">满减</el-tag>
              满 {{ scope.row.min_price }} 减 <span style="color: #F56C6C; font-weight: bold;">{{ scope.row.sub_price }}</span>
            </div>
            <div v-if="scope.row.type == 1">
              <el-tag size="mini" type="warning" effect="plain">折扣</el-tag>
              满 {{ scope.row.min_price }} 打 <span style="color: #E6A23C; font-weight: bold;">{{ scope.row.discount }}</span> 折
            </div>
          </template>
        </el-table-column>

        <el-table-column label="有效期" width="280" align="center">
          <template slot-scope="scope">
            <span style="font-size: 12px;">{{ scope.row.time_text }}</span>
          </template>
        </el-table-column>

        <el-table-column label="领取时间" width="160" align="center" prop="created_at"></el-table-column>

        <el-table-column label="使用时间" width="160" align="center">
          <template slot-scope="scope">
            {{ scope.row.status == 1 ? scope.row.used_at : '-' }}
          </template>
        </el-table-column>

        <el-table-column label="状态" width="100" align="center">
          <template slot-scope="scope">
            <el-tag size="small" type="success" v-if="scope.row.status == 0">未使用</el-tag>
            <el-tag size="small" type="info" v-if="scope.row.status == 1">已使用</el-tag>
            <el-tag size="small" type="danger" v-if="scope.row.status == 2">已过期</el-tag>
          </template>
        </el-table-column>

        <el-table-column label="允许转赠" width="80" align="center">
          <template slot-scope="scope">
            <span v-if="scope.row.is_transferable == 1">是</span>
            <span v-else style="color: #ccc;">否</span>
          </template>
        </el-table-column>

        <el-table-column label="操作" fixed="right" width="80" align="center">
          <template slot-scope="scope">
            <el-popconfirm
              confirm-button-text="确定"
              cancel-button-text="取消"
              icon="el-icon-info"
              icon-color="red"
              title="确定作废（删除）该用户的这张券吗？"
              @confirm="deleteItem(scope.row.id)"
            >
              <el-button type="text" size="small" slot="reference" style="color: #F56C6C;">作废</el-button>
            </el-popconfirm>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div style="text-align: right; margin-top: 20px;">
        <el-pagination
          v-if="pagination"
          background
          :current-page.sync="page"
          :page-size="pagination.pageSize"
          layout="total, prev, pager, next, jumper"
          :total="pagination.total_count"
          @current-change="pageChange"
        ></el-pagination>
      </div>

    </el-card>
  </div>
</template>

<script>
const emptySearch = () => ({ user_keyword: '', coupon_keyword: '', status: '', type: '', owner: '', date: null });

export default {
  name: "UserCouponList",
  data() {
    return {
      loading: false,
      list: [],
      pagination: null,
      page: 1,
      search: emptySearch()
    };
  },
  created() {
    this.getList();
  },
  methods: {
    // 搜索
    toSearch() {
      this.page = 1;
      this.getList();
    },

    resetSearch() {
      this.search = emptySearch();
      this.toSearch();
    },

    // 翻页
    pageChange(page) {
      this.page = page;
      this.getList();
    },

    // 获取列表数据
    getList() {
      this.loading = true;
      this.$request({
        url: '/plugin/marketing/mall/user-coupon/list',
        method: 'get',
        data: {
          page: this.page,
          user_keyword: this.search.user_keyword,
          coupon_keyword: this.search.coupon_keyword,
          status: this.search.status,
          type: this.search.type,
          owner: this.search.owner,
          date_start: this.search.date ? this.search.date[0] : '',
          date_end: this.search.date ? this.search.date[1] : ''
        }
      }).then(res => {
        this.loading = false;
        if (res.code === 0) {
          this.list = res.data.list;
          this.pagination = res.data.pagination;
        } else {
          this.$message.error(res.msg);
        }
      }).catch(() => {
        this.loading = false;
      });
    },

    // 删除/作废
    deleteItem(id) {
      this.loading = true;
      this.$request({
        url: '/plugin/marketing/mall/user-coupon/delete',
        method: 'post',
        data: {id: id}
      }).then(res => {
        this.loading = false;
        if (res.code === 0) {
          this.$message.success('作废成功');
          this.getList(); // 刷新列表
        } else {
          this.$message.error(res.msg);
        }
      }).catch(() => {
        this.loading = false;
      });
    }
  }
};
</script>

<style scoped>
.search-bar { margin-bottom: 4px; }
/* 可以在这里添加一些样式微调 */
</style>
