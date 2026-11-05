<template>
  <div>
    <el-dialog
      title="选择用户"
      :visible.sync="dialogVisible"
      width="65%"
      :before-close="handleClose"
      append-to-body
      custom-class="user-picker-dialog"
      :close-on-click-modal="false"
    >
      <div class="dialog-inner" v-loading="is_loading">

        <div class="filter-container">
          <el-form ref="searchForm" :inline="true" size="mini" class="compact-search-form">
            <el-form-item>
              <el-input v-model="search_user_id" placeholder="用户ID" clearable class="filter-input"></el-input>
            </el-form-item>
            <el-form-item>
              <el-input v-model="nickname" placeholder="用户昵称" clearable class="filter-input"></el-input>
            </el-form-item>
            <el-form-item>
              <el-input v-model="mobile" placeholder="手机号" clearable class="filter-input"></el-input>
            </el-form-item>
            <el-form-item>
              <el-input v-model="parent_id" placeholder="推荐人ID" clearable class="filter-input"></el-input>
            </el-form-item>
            <el-form-item>
              <el-input v-model="top_parent_id" placeholder="团队长ID" clearable class="filter-input"></el-input>
            </el-form-item>
            <el-form-item>
              <el-input v-model="level_id" placeholder="等级ID" clearable class="filter-input-small"></el-input>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" icon="el-icon-search" @click="searchUser">搜索</el-button>
            </el-form-item>
          </el-form>
        </div>

        <scroll-bar :height="400">
          <el-table
            :data="list"
            style="width: 100%"
            size="mini"
            border
            stripe
            :header-cell-style="{background:'#F5F7FA', color:'#606266', fontWeight:'600'}"
          >
            <el-table-column prop="id" width="70" label="ID" align="center"></el-table-column>

            <el-table-column label="用户信息" min-width="180">
              <template slot-scope="scope">
                <el-popover placement="right-start" width="220" trigger="hover" popper-class="user-popover">
                  <div class="popover-details">
                    <div class="detail-row">
                      <span class="label">ID:</span>
                      <span class="value">{{ scope.row.id }}</span>
                    </div>
                    <div class="detail-row">
                      <span class="label">手机号:</span>
                      <span class="value">{{ scope.row.mobile || '未绑定' }}</span>
                    </div>
                    <div class="detail-row">
                      <span class="label">推荐人ID:</span>
                      <span class="value">{{ scope.row.parent_id }}</span>
                    </div>
                    <div class="detail-row">
                      <span class="label">推荐人:</span>
                      <div class="value flex-y-center">
                        <el-avatar size="mini" :src="scope.row.parent_avatar" class="mini-avatar"></el-avatar>
                        <span class="ml-5">{{ scope.row.parent_name || '无' }}</span>
                      </div>
                    </div>
                    <div class="detail-row">
                      <span class="label">状态:</span>
                      <span class="value text-success">正常</span>
                    </div>
                  </div>

                  <div slot="reference" class="flex-row flex-y-center user-reference">
                    <el-avatar :src="scope.row.avatar_url" size="small"></el-avatar>
                    <div class="user-info-text">
                      <div class="nickname text-ellipsis" :title="scope.row.nickname">{{ scope.row.nickname }}</div>
                      <div v-if="scope.row.platform_logo">
                        <el-avatar style="background: none;" size="mini" :src="scope.row.platform_logo"></el-avatar>
                      </div>
                    </div>
                  </div>
                </el-popover>
              </template>
            </el-table-column>

            <el-table-column prop="level" label="会员等级" width="100" align="center"></el-table-column>

            <el-table-column prop="created_at" label="注册时间" width="140" align="center"></el-table-column>

            <el-table-column label="成交数据" width="130">
              <template slot-scope="scope">
                <div class="data-row">订单：<span class="text-warning">{{ scope.row.order_num }}</span></div>
                <div class="data-row">金额：<span class="text-warning">¥{{ scope.row.total_order_price }}</span></div>
              </template>
            </el-table-column>

            <el-table-column label="佣金数据" width="130">
              <template slot-scope="scope">
                <div class="data-row">可用：<span class="text-warning">¥{{ scope.row.price }}</span></div>
                <div class="data-row">累计：<span class="text-warning">¥{{ scope.row.total_price }}</span></div>
              </template>
            </el-table-column>

            <el-table-column label="操作" width="90" align="center" fixed="right">
              <template slot-scope="scope">
                <el-button
                  type="text"
                  size="mini"
                  v-if="scope.$index !== select_user_index"
                  @click="selectUser(scope.$index, scope.row.id)"
                >选择</el-button>
                <el-button
                  type="success"
                  size="mini"
                  plain
                  v-if="scope.$index === select_user_index"
                >已选</el-button>
              </template>
            </el-table-column>
          </el-table>
        </scroll-bar>

        <div class="pagination-wrapper">
          <pagination :pagination="pagination" @pageChange="getList" v-model="page"></pagination>
        </div>
      </div>

      <span slot="footer" class="dialog-footer">
        <el-button size="small" @click="dialogVisible = false">取 消</el-button>
        <el-button size="small" type="primary" @click="confirmSelect">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'UserPicker',
  props: {
    show: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      pagination: null,
      is_loading: false,
      page: 1,
      list: [],
      dialogVisible: false,
      select_user_index: -1,
      select_user_id: 0,
      keyword: '',
      search_user_id: '',
      nickname: '',
      mobile: '',
      parent_id: '',
      level_id: '',
      top_parent_id: '',
    };
  },
  watch: {
    show(newVal) {
      if (newVal) {
        this.dialogVisible = true;
      } else {
        this.dialogVisible = false;
      }
    },
    dialogVisible(newVal) {
      if (!this.dialogVisible) {
        this.$emit('close');
      }
    }
  },
  created() {
    this.page = 1;
    this.list = [];
    this.select_user_id = 0;
    this.select_user_index = -1;
    this.getList();
  },
  methods: {
    searchUser() {
      this.page = 1;
      this.list = [];
      this.select_user_id = 0;
      this.select_user_index = -1;
      this.getList();
    },
    selectUser(index, user_id) {
      this.select_user_index = index;
      this.select_user_id = user_id;
    },
    confirmSelect() {
      if (this.select_user_id === 0) {
        this.$message.warning('请先选择一个用户');
        return;
      }
      let row = this.list[this.select_user_index];
      row.user_id = this.select_user_id;
      this.$emit('selected', row);
    },
    handleClose() {
      this.dialogVisible = false;
    },
    getList() {
      this.is_loading = true;
      this.$request({
        url: "/mall/user/index",
        data: {
          page: this.page,
          keyword: this.keyword,
          user_id: this.search_user_id,
          nickname: this.nickname,
          mobile: this.mobile,
          parent_id: this.parent_id,
          top_parent_id: this.top_parent_id,
          level_id: this.level_id
        }
      }).then(res => {
        this.is_loading = false;
        if (res.code === 0) {
          this.list = res.data.list;
          this.pagination = res.data.pagination;
        }
      }).catch(() => {
        this.is_loading = false;
      });
    }
  }
}
</script>

<style scoped lang="scss">
/* 修复弹窗默认样式 */
::v-deep .user-picker-dialog {
  border-radius: 6px;
  .el-dialog__header {
    border-bottom: 1px solid #ebeef5;
    padding: 15px 20px;
  }
  .el-dialog__body {
    padding: 15px 20px;
  }
  .el-dialog__footer {
    border-top: 1px solid #ebeef5;
    padding: 12px 20px;
  }
}

/* 搜索栏紧凑排版 */
.filter-container {
  background: #fafafa;
  padding: 12px 12px 0 12px;
  border-radius: 4px;
  border: 1px solid #ebeef5;
  margin-bottom: 15px;
}
.compact-search-form {
  .el-form-item {
    margin-bottom: 12px;
    margin-right: 10px;
  }
  .filter-input {
    width: 130px;
  }
  .filter-input-small {
    width: 90px;
  }
}

/* 悬浮弹窗详情 */
.popover-details {
  font-size: 12px;
  color: #606266;
  .detail-row {
    display: flex;
    margin-bottom: 8px;
    align-items: center;
    &:last-child {
      margin-bottom: 0;
    }
    .label {
      width: 70px;
      text-align: right;
      padding-right: 10px;
      color: #909399;
    }
    .value {
      flex: 1;
    }
  }
  .mini-avatar {
    vertical-align: middle;
  }
}

/* 表格内展示 */
.user-reference {
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  transition: background-color 0.2s;
  &:hover {
    background-color: #f5f7fa;
  }
  .user-info-text {
    padding-left: 10px;
    flex: 1;
    overflow: hidden;
  }
  .nickname {
    font-size: 13px;
    color: #303133;
    margin-bottom: 2px;
  }
}

/* 数据展示 */
.data-row {
  font-size: 12px;
  color: #606266;
  line-height: 1.6;
}

/* 分页 */
.pagination-wrapper {
  margin-top: 15px;
  display: flex;
  justify-content: flex-end;
}

/* 通用工具类 */
.flex-row { display: flex; }
.flex-y-center { display: flex; align-items: center; }
.ml-5 { margin-left: 5px; }
.text-warning { color: #FF9952; font-weight: bold; }
.text-success { color: #67C23A; }
.text-ellipsis { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>
