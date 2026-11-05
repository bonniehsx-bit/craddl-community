<template>
  <div class="app-container">
    <el-card class="box-card">
      <div slot="header" class="clearfix">
        <span>后台充值记录</span>
        <!-- <el-button style="float: right; padding: 3px 0" type="text"> </el-button> -->
      </div>

      <div>
        <div class="flex-y-center">
          <el-input v-model='nickname' placeholder="用户昵称" style="width: 250px;margin-right: 20px;"></el-input>
          <el-input v-model='user_id' placeholder="用户ID" style="width: 250px;margin-right: 20px;"></el-input>

          <div class="flex-y-center" style="margin-right: 20px;">
            <span>时间：</span>
            <el-date-picker value-format="yyyy-MM-dd HH:mm:ss" v-model="search_date" type="datetimerange"
                            range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期">
            </el-date-picker>
          </div>
          <el-button type='primary' style="margin-left: 10px;" icon='el-icon-search' @click='search'>搜索
          </el-button>
          <el-button type='primary' style="margin-left: 10px;" icon='el-icon-search' @click='exportExcel'>导出
          </el-button>
        </div>

        <el-tabs v-model="activeName" @tab-click="handleClick">
          <el-tab-pane label="全部" name="all"></el-tab-pane>
          <el-tab-pane label="余额" name="balance"></el-tab-pane>
          <el-tab-pane label="积分" name="integral"></el-tab-pane>
          <el-tab-pane label="佣金" name="price"></el-tab-pane>
        </el-tabs>
        <el-table size="mini"  :data="list" style="width: 100%" v-loading="is_loading">
          <el-table-column prop="id" label="ID" width="50px">
          </el-table-column>
          <el-table-column label="用户信息">
            <template slot-scope="scope">
              <div class="flex-y-center">
                <el-avatar :src="scope.row.avatar_url"></el-avatar>
                <div style="padding: 0 10px;">{{ scope.row.nickname }}</div>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="充值类型">
            <template slot-scope='scope'>
              <div v-if="scope.row.type == 0">余额</div>
              <div v-if="scope.row.type == 1">积分</div>
              <div v-if="scope.row.type == 2">佣金</div>
            </template>
          </el-table-column>
          <el-table-column prop="admin" label="操作人员">
          </el-table-column>
          <el-table-column prop="num" label="金额">
          </el-table-column>
          <el-table-column prop="before_num" label="充值之前">
          </el-table-column>
          <el-table-column prop="after_num" label="充值之后">
          </el-table-column>
          <el-table-column prop="remarks" label="备注">
          </el-table-column>
          <el-table-column prop="created_at" label="操作时间">
          </el-table-column>
        </el-table>
        <pagination :pagination="pagination" v-model="page" @pageChange="getList"></pagination>
      </div>

    </el-card>

  </div>
</template>

<script>
export default {

  data() {

    return {
      activeName: 'all',
      search_date: null,
      is_loading: false,
      page: 1,
      nickname: '',
      user_id: '',
      pagination: null,
      list: [],
      type: ''
    }
  },
  created() {
    this.getList()
  },
  methods: {

    exportExcel() {
      const header = ['ID', '用户昵称', '用户ID', '创建时间', '备注', '金额'];
      // 上面设置Excel的表格第一行的标题
      const fields = ['id', 'nickname', 'user_id', 'created_at', 'remarks', 'num'];
      // 上面的index、nickName、name是tableData里对象的属性
      this.$request({
        url: "/mall/finance/charge",
        data: {
          page: this.page,
          nickname: this.nickname,
          user_id: this.user_id,
          type: this.type,
          search_date: this.search_date,
          is_export: 1
        }
      }).then(res => {
        if (res.code == 0) {
          let list = res.data.list;
          this.$export(header, fields, list, '充值记录');
        }
      })
    },
    handleClick(e) {
      if (e.name == 'all') {
        this.type = '';
        this.activeName = 'all'
      }
      if (e.name == 'balance') {
        this.type = 0;
        this.activeName = 'balance'
      }
      if (e.name == 'integral') {
        this.type = 1;
        this.activeName = 'integral'
      }
      this.page = 1;
      this.list = [];
      this.getList();
    },
    search() {
      this.list = [];
      this.page = 1;
      this.pagination = null;
      this.getList();
    },
    getList() {
      this.is_loading = true;
      this.$request({
        url: "/mall/finance/charge",
        data: {
          page: this.page,
          nickname: this.nickname,
          user_id: this.user_id,
          type: this.type,
          search_date: this.search_date
        }
      }).then(res => {
        this.is_loading = false;
        if (res.code == 0) {
          this.list = res.data.list;
          this.pagination = res.data.pagination
        }
      }).catch(e => {
        this.is_loading = false;
      })
    },

  }

}
</script>

<style>
</style>
