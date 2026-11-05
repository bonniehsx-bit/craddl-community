<template>
  <div>
    <!-- 1. 已选用户展示和操作区域 -->
    <div v-if="selectedUser" class="user-display">
      <el-avatar :size="40" :src="selectedUser.avatar_url"></el-avatar>
      <div class="user-info">
        <div>{{ selectedUser.nickname }}</div>
        <div class="user-id">ID: {{ selectedUser.id }}</div>
      </div>
      <div class="user-actions">
        <el-button type="text" @click="openDialog">更换</el-button>
        <el-button type="text" @click="clearSelection" style="color: #F56C6C;">清除</el-button>
      </div>
    </div>

    <!-- 2. 未选用户时，显示选择按钮 -->
    <div v-else>
      <el-button @click="openDialog" icon="el-icon-plus" plain>{{ placeholder }}</el-button>
    </div>

    <!-- 3. 用户选择弹窗 (内部逻辑和之前一样) -->
    <el-dialog title="选择用户" :visible.sync="dialogVisible" width="60%" @opened="onOpened" append-to-body>
      <div>
        <el-form :model="searchParams" :inline="true" label-width="100px">
          <!-- 所有搜索字段... -->
          <el-form-item label="用户ID"><el-input v-model="searchParams.user_id"></el-input></el-form-item>
          <el-form-item label="用户昵称"><el-input v-model="searchParams.nickname"></el-input></el-form-item>
          <el-form-item label="手机号"><el-input v-model="searchParams.mobile"></el-input></el-form-item>
          <el-form-item>
            <el-button type="primary" @click="searchUser">搜索</el-button>
          </el-form-item>
        </el-form>

        <scroll-bar :height="400">
          <el-table :data="list" style="width: 100%" v-loading="isLoading">
            <el-table-column prop="id" width="100" label="用户ID"></el-table-column>
            <el-table-column label="用户">
              <template slot-scope="scope">
                <div style="display: flex; align-items: center;">
                  <el-avatar :src="scope.row.avatar_url"></el-avatar>
                  <div style="padding-left: 10px;">{{ scope.row.nickname }}</div>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="level" label="会员等级"></el-table-column>
            <el-table-column prop="created_at" label="注册时间"></el-table-column>
            <el-table-column label="操作">
              <template slot-scope="scope">
                <el-button type="text" @click="confirmSelection(scope.row)">选择</el-button>
              </template>
            </el-table-column>
          </el-table>
        </scroll-bar>

        <div style="text-align: right;margin: 20px 0;">
          <pagination :pagination="pagination" @pageChange="getList" v-model="page"></pagination>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'UserSelector',
  // 定义 v-model 的 prop 和 event
  model: {
    prop: 'value',
    event: 'input'
  },
  props: {
    // 接收 v-model 传入的值（可以是 null 或一个用户对象）
    value: {
      type: Object,
      default: null
    },
    placeholder: {
      type: String,
      default: '选择用户'
    }
  },
  data() {
    return {
      dialogVisible: false,
      // 弹窗内部数据
      pagination: null,
      isLoading: false,
      page: 1,
      list: [],
      searchParams: {
        user_id: '',
        nickname: '',
        mobile: '',
        parent_id: '',
        level_id: '',
        top_parent_id: '',
      },
    };
  },
  computed: {
    // 使用计算属性来同步 prop 和内部状态，避免直接修改 prop
    selectedUser: {
      get() {
        return this.value;
      },
      set(val) {
        // 通过 emit input 事件来更新父组件的 v-model
        this.$emit('input', val);
      }
    }
  },
  methods: {
    // 打开弹窗
    openDialog() {
      this.dialogVisible = true;
    },
    // 清除选择
    clearSelection() {
      this.selectedUser = null;
    },
    // 弹窗打开时，如果列表为空，则加载数据
    onOpened() {
      if (!this.list.length) {
        this.getList();
      }
    },
    // 搜索
    searchUser() {
      this.page = 1;
      this.list = [];
      this.getList();
    },
    // 在弹窗中确认选择
    confirmSelection(user) {
      this.selectedUser = user; // 更新计算属性，将触发 set 方法，从而 emit input 事件
      this.dialogVisible = false; // 关闭弹窗
    },
    // 获取列表
    getList() {
      this.isLoading = true;
      this.$request({
        url: "/mall/user/index",
        data: {
          page: this.page,
          ...this.searchParams
        }
      }).then(res => {
        if (res.code === 0) {
          this.list = res.data.list;
          this.pagination = res.data.pagination;
        }
      }).finally(() => {
        this.isLoading = false;
      });
    },
  }
}
</script>

<style scoped>
.user-display {
  display: flex;
  align-items: center;
  padding: 8px;
  border: 1px solid #DCDFE6;
  border-radius: 4px;
}
.user-info {
  margin-left: 10px;
  flex-grow: 1;
}
.user-id {
  color: #999;
  font-size: 12px;
}
.user-actions {
  margin-left: 10px;
}
</style>
