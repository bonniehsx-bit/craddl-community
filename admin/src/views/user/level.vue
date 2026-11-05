<template>
  <div class="app-container">
    <el-card class="box-card" v-loading='is_loading' shadow="never">
      <div slot="header" class="  flex-x-between">
        <span style="font-weight:bold;font-size:15px;">会员等级</span>
        <el-button size="mini" type="primary" icon="el-icon-plus" @click="levelEdit(0)">添加等级</el-button>
      </div>
      <div>
        <el-table :data="list" style="width: 100%" size="small" border stripe
                  :header-cell-style="{background:'#F5F7FA'}"
                  @row-dblclick="onRowDblclick">
          <el-table-column label="等级" width="100" align="center">
            <template slot-scope="scope">
              等级{{ parseInt(scope.row.level) }}
            </template>
          </el-table-column>
          <el-table-column prop="level" label="等级权重" align="center" width="100"></el-table-column>
          <el-table-column prop="name" label="名称" min-width="120"></el-table-column>
          <el-table-column label="图标" align="center" width="100">
            <template slot-scope="scope">
              <el-avatar style="background: none;" size="small" :src="scope.row.pic_url"></el-avatar>
            </template>
          </el-table-column>
          <el-table-column prop="count" label="会员数量" align="center" width="120"></el-table-column>

          <el-table-column label="状态" align="center" width="100">
            <template slot-scope="scope">
              <el-tag v-if="scope.row.enabled == 1" type="success" size="mini">已启用</el-tag>
              <el-tag v-if="scope.row.enabled == 0" type="warning" size="mini">未启用</el-tag>
            </template>
          </el-table-column>

          <el-table-column label="操作" width="150" align="center" fixed="right">
            <template slot-scope="scope">
              <el-button type="text" size="mini" icon="el-icon-edit" @click="levelEdit(scope.row.id)">编辑</el-button>
              <el-tooltip effect="dark" content="删除" placement="top">
                <el-popconfirm confirmButtonText='是' cancelButtonText='否' title="确认删除？"
                               @confirm="levelDelete(scope.row.id)">
                  <el-button type="text" slot="reference" size="mini" icon="el-icon-delete"
                             style="color:#F56C6C;margin-left:10px;">删除
                  </el-button>
                </el-popconfirm>
              </el-tooltip>
            </template>
          </el-table-column>
        </el-table>
      </div>
    </el-card>

    <level-edit-drawer
      :visible.sync="editDrawerVisible"
      :level-id="currentLevelId"
      @success="getList"
    />
  </div>
</template>

<script>
// 引入刚刚新建的组件，路径请根据你的实际项目结构进行修改
import LevelEditDrawer from './LevelEditDrawer.vue'

export default {
  name: 'user-level',
  components: {LevelEditDrawer},
  data() {
    return {
      list: [],
      page: 1,
      is_loading: false,

      // 控制抽屉所需变量
      editDrawerVisible: false,
      currentLevelId: 0
    }
  },
  created() {
    this.getList();
  },
  methods: {
    // 替换原本的跳转逻辑
    levelEdit(id) {
      this.currentLevelId = id;
      this.editDrawerVisible = true;
    },
    // 双击整行 → 打开等级编辑抽屉 (和点行内"编辑"按钮等价)
    onRowDblclick(row) {
      if (row && row.id) this.levelEdit(row.id);
    },

    levelDelete(id) {
      this.$request({
        url: "/mall/user/level-delete",
        data: {id: id},
        method: 'post',
      }).then(res => {
        if (res.code == 0) {
          this.$message.success('删除成功');
          this.getList();
        } else {
          this.$message.error(res.msg);
        }
      })
    },

    getList() {
      this.is_loading = true;
      this.$request({
        url: '/mall/user/level',
        data: {page: this.page}
      }).then(res => {
        this.is_loading = false;
        if (res.code == 0) {
          this.list = res.data.list;
        }
      }).catch(() => {
        this.is_loading = false;
      })
    },
  }
}
</script>

<style scoped>
.app-container {
  padding: 10px;
  background-color: #f0f2f5;
  min-height: calc(100vh - 50px);
}

.box-card {
  border: none;
  border-radius: 6px;
}
</style>
