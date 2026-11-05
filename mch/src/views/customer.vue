<template>
  <div class="app-container">
    <el-card shadow="never">
      <!-- 选项卡与搜索栏 -->
      <div slot="header" class="clearfix">
        <el-tabs v-model="search.type" @tab-click="handleTabClick"
          style="display: inline-block; vertical-align: middle; margin-right: 20px;">
          <el-tab-pane label="本店消费客户" name="in_store"></el-tab-pane>
          <el-tab-pane label="锁粉推荐客户" name="recommended"></el-tab-pane>
        </el-tabs>

        <div style="float: right; margin-top: 5px;">
          <el-input v-model="search.keyword" placeholder="搜客户昵称/ID/手机号" size="small"
            style="width: 250px; margin-right: 10px;" @keyup.enter.native="searchList" clearable
            @clear="searchList"></el-input>
          <el-button type="primary" size="small" @click="searchList">搜索</el-button>
        </div>
      </div>

      <!-- 客户列表 -->
      <el-table :data="list" v-loading="loading" style="width: 100%" border size="small">
        <el-table-column prop="id" label="客户ID" width="100" align="center"></el-table-column>

        <el-table-column label="客户信息" min-width="200">
          <template slot-scope="scope">
            <div style="display: flex; align-items: center;">
              <el-avatar :size="40" :src="scope.row.avatar_url" style="margin-right: 15px;"></el-avatar>
              <div>
                <div style="font-weight: bold; margin-bottom: 5px;">{{ scope.row.nickname }}</div>
                <div style="font-size: 12px; color: #909399;"><i class="el-icon-mobile-phone"></i> {{ scope.row.mobile
                  || '未绑定手机号' }}</div>
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column v-if="search.type === 'in_store'" prop="total_consumption" label="本店消费总额" width="150"
          align="center">
          <template slot-scope="scope">
            <span style="color: #F56C6C; font-weight: bold;">￥{{ scope.row.total_consumption }}</span>
          </template>
        </el-table-column>

        <el-table-column prop="remark" label="商家备注" min-width="150">
          <template slot-scope="scope">
            <div style="color: #E6A23C;" v-if="scope.row.remark">{{ scope.row.remark }}</div>
            <div style="color: #C0C4CC;" v-else>暂无备注</div>
          </template>
        </el-table-column>

        <el-table-column prop="created_at" label="注册时间" width="180" align="center"></el-table-column>

        <el-table-column label="操作" width="120" align="center" fixed="right">
          <template slot-scope="scope">
            <el-button type="text" size="small" icon="el-icon-edit"
              @click="openRemarkDialog(scope.row)">修改备注</el-button>
          </template>
        </el-table-column>
      </el-table>

      <pagination v-show="total > 0" :total="total" :page.sync="search.page" :limit.sync="search.limit"
        @pagination="loadData" />
    </el-card>

    <!-- 修改备注弹窗 -->
    <el-dialog title="编辑客户备注" :visible.sync="dialogVisible" width="400px">
      <el-form :model="remarkForm" label-width="80px">
        <el-form-item label="客户昵称">
          <span>{{ remarkForm.nickname }}</span>
        </el-form-item>
        <el-form-item label="备注内容">
          <el-input type="textarea" :rows="4" placeholder="请输入对该客户的备注信息" v-model="remarkForm.remark" maxlength="200"
            show-word-limit></el-input>
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="dialogVisible = false" size="small">取 消</el-button>
        <el-button type="primary" :loading="saving" @click="saveRemark" size="small">确 定</el-button>
      </span>
    </el-dialog>

  </div>
</template>

<script>
export default {
  name: 'CustomerIndex',
  data() {
    return {
      loading: false,
      list: [],
      total: 0,
      search: {
        keyword: '',
        type: 'in_store',
        page: 1,
        limit: 15
      },

      dialogVisible: false,
      saving: false,
      remarkForm: {
        user_id: '',
        nickname: '',
        remark: ''
      }
    }
  },
  created() {
    this.loadData();
  },
  methods: {
    handleTabClick() {
      this.search.page = 1;
      this.loadData();
    },
    searchList() {
      this.search.page = 1;
      this.loadData();
    },
    loadData() {
      this.loading = true;
      this.$request({
        url: '/plugin/mch/admin/member/index',
        method: 'get',
        data: this.search
      }).then(res => {
        this.loading = false;
        if (res.code === 0) {
          this.list = res.data.list;
          if (res.data.pagination) {
            this.total = parseInt(res.data.pagination.totalCount || res.data.pagination.total_count || 0);
          }
        } else {
          this.$message.error(res.msg);
        }
      }).catch(() => {
        this.loading = false;
      });
    },

    openRemarkDialog(row) {
      this.remarkForm.user_id = row.id;
      this.remarkForm.nickname = row.nickname;
      this.remarkForm.remark = row.remark || '';
      this.dialogVisible = true;
    },

    saveRemark() {
      this.saving = true;
      this.$request({
        url: '/plugin/mch/admin/member/update-remark',
        method: 'post',
        data: {
          user_id: this.remarkForm.user_id,
          remark: this.remarkForm.remark
        }
      }).then(res => {
        this.saving = false;
        if (res.code === 0) {
          this.$message.success('备注保存成功');
          this.dialogVisible = false;

          // 本地更新列表数据，避免重新刷新整个列表
          const index = this.list.findIndex(item => item.id === this.remarkForm.user_id);
          if (index > -1) {
            this.$set(this.list[index], 'remark', this.remarkForm.remark);
          }
        } else {
          this.$message.error(res.msg);
        }
      }).catch(() => {
        this.saving = false;
      });
    }
  }
}
</script>
