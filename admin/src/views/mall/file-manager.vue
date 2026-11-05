<template>
  <div class="app-container">
    <el-card shadow="always" :body-style="{ padding: '20px' }">
      <div slot="header">
        <span>文件管理器</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="dialogVisible = true">新建</el-button>
        <el-button style="float: right; padding: 3px 10px" type="text" @click="dialogVisible1 = true">从HTML中获取
        </el-button>
      </div>
      <div v-loading="is_loading">
        <el-form :model="searchForm" ref="form" label-width="160px" :inline="true">

          <el-form-item label="文件分组">
            <el-select v-model="searchForm.group_id" style="width: 100%">
              <el-option label="全部" :value="-1"></el-option>
              <el-option :label="item.name" :value="item.id" :key="item.id" v-if="group.length" v-for="item in group"></el-option>
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="search">搜索</el-button>

          </el-form-item>
        </el-form>

        <el-table size="mini" :data="list" border style="width: 100%">
          <el-table-column prop="id" label="ID" width="100">
          </el-table-column>
          <el-table-column prop="name" label="名称">
          </el-table-column>
          <el-table-column label="预览">
            <template slot-scope='scope'>
              <image-view :src="scope.row.url" v-if="scope.row.type == 'image'">
              </image-view>
              <a :href="scope.row.url" v-if="scope.row.type !== 'image'" target="_blank">
                <el-button type="text">预览</el-button>
              </a>
            </template>
          </el-table-column>
          <el-table-column label="文件类型" width="100">
            <template slot-scope='scope'>
              <el-tag type="success" v-if="scope.row.type == 'image'" size="mini">
                图片
              </el-tag>
              <el-tag type="info" v-if="scope.row.type == 'video'" size="mini">
                视频
              </el-tag>
              <el-tag type="warning" v-if="scope.row.type == 'doc'" size="mini">
                文档
              </el-tag>
              <el-tag type="danger" v-if="scope.row.type == 'audio'" size="mini">
                音频
              </el-tag>

            </template>
          </el-table-column>
          <el-table-column label="上传人员">
            <template slot-scope='scope'>
              <div v-if="scope.row.user_id !== 0" class="flex-y-center">
                <el-tag type="success" size="mini">
                  用户
                </el-tag>
                <div>（ID:{{ scope.row.user_id }}）</div>
              </div>
              <div v-if="scope.row.admin_id !== 0" class="flex-y-center">
                <el-tag size="mini">
                  管理员
                </el-tag>
                <div>（ID:{{ scope.row.admin_id }}）</div>
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="url" label="链接">
            <template slot-scope="scope">
              <a v-clipboard:copy="scope.row.url" v-clipboard:success="onCopy" v-clipboard:error="onError">
                <span class="el-button--text" style="padding-left:3px;">复制链接</span>
              </a>
            </template>
          </el-table-column>
          <el-table-column prop="type" label="存储位置" width="100">
            <template slot-scope='scope'>
              <el-tag v-if="scope.row.location == 'local'" size="mini">
                本地
              </el-tag>
              <el-tag v-if="scope.row.location == 'oss'" size="mini">
                阿里云
              </el-tag>
              <el-tag v-if="scope.row.location == 'cos'" size="mini">
                腾讯云
              </el-tag>
              <el-tag v-if="scope.row.location == 'qiniu'" size="mini">
                七牛云
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="created_time" label="创建时间">
          </el-table-column>
          <el-table-column label="操作">
            <template slot-scope="scope">
              <el-button type="text" @click="edit(scope.row)">编辑</el-button>
              <el-button type="text" @click="deleteItem(scope.row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
        <div style="text-align: right;margin: 20px 0;">
          <pagination :pagination="pagination" v-model="searchForm.page" @pageChange="getList"></pagination>
        </div>
      </div>
    </el-card>
    <el-dialog title="编辑文件" :visible.sync="dialogVisible" width="40%" :before-close="handleClose">
      <el-form size="mini" :model="form" ref="form" label-width="80px" :inline="false">
        <el-form-item label="文件分组">
          <el-select v-model="form.group_id" placeholder="请选择" style="width: 100%;">
            <el-option v-if="group.length" v-for="(item, index) in group" :label="item.name" :value="item.id"
                       :key="item.id">
            </el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="文件名称">
          <el-input v-model="form.name"></el-input>
        </el-form-item>
        <el-form-item label="输入链接">
          <el-input v-model="form.url">
          </el-input>
        </el-form-item>
        <el-form-item label="文件类型">
          <el-radio v-model="form.type" label="image">图片</el-radio>
          <el-radio v-model="form.type" label="video">视频</el-radio>
          <el-radio v-model="form.type" label="doc">文档</el-radio>
          <el-radio v-model="form.type" label="audio">音频</el-radio>
        </el-form-item>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="handleClose">取 消</el-button>
        <el-button type="primary" @click="save">确 定</el-button>
      </span>
    </el-dialog>

    <el-dialog title="从HTML标签中快速导入" :visible.sync="dialogVisible1" width="40%" :before-close="handleClose">
      <el-form size="mini" label-width="80px" :inline="false">
        <el-form-item label="文件分组">
          <el-select v-model="group_id" placeholder="请选择" style="width: 100%;">
            <el-option v-if="group.length" v-for="(item, index) in group" :label="item.name" :value="item.id"
                       :key="item.id">
            </el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="html内容">
          <el-input type="textarea" v-model="html" rows="25"></el-input>
        </el-form-item>

      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="handleClose">取 消</el-button>
        <el-button type="primary" @click="submit">确 定</el-button>
      </span>
    </el-dialog>

  </div>

</template>
<script>
export default {
  name: 'mall-file-manager',
  data() {
    return {
      is_loading: false,
      dialogVisible1: false,
      dialogVisible: false,
      page: 1,
      pagination: null,
      list: [],
      group: [],
      group_id: '',
      html: '',
      searchForm: {
        group_id: '',
        page: 1,

      },
      form: {
        id: '',
        url: '',
        name: '',
        group_id: '',
        type: 'image',
      }
    }
  },
  created() {

    this.getList();
    this.getGroup();
  },
  methods: {
    edit(row) {
      this.form = Object.assign(this.form, row);
      this.dialogVisible = true;
    },
    deleteItem(row) {
      this.$confirm('确定删除:' + row.name, '提示').then(e => {
        this.$request({
          url: '/mall/mall/file-delete',
          data: row,
          method: 'post'
        }).then(res => {
          this.$message.success(res.msg);
          this.getList();
        })
      })
    },
    handleClose(e) {
      this.dialogVisible = false;
      this.dialogVisible1 = false;
      this.form = {
        id: '',
        url: '',
        name: '',
        group_id: '',
      }
      this.html = '';
    },
    search() {
      this.list = [];
      this.searchForm.page = 1;
      this.getList();
    },
    getList() {
      this.is_loading = true;
      this.$request({
        url: '/mall/mall/file-list',
        data: this.searchForm
      }).then(res => {
        this.is_loading = false;
        this.list = res.data.list
        this.pagination = res.data.pagination
      })
    },
    getGroup() {

      this.$request({
        url: '/mall/mall/file-group',

      }).then(res => {

        if (res.code == 0) {
          this.group = res.data.list

        }
      })
    },
    onCopy(e) { // 复制成功
      this.$message({
        message: '复制成功！',
        type: 'success'
      })
    },
    onError(e) { // 复制失败
      this.$message({
        message: '复制失败！',
        type: 'error'
      })
    },
    submit() {
      if (this.is_loading) {
        return;
      }
      this.is_loading = true;
      this.$request({
        url: '/mall/mall/file-html',
        data: {
          html: this.html,
          group_id: this.group_id
        },
        method: 'post'
      }).then(res => {
        this.is_loading = false;
        this.$message.success(res.msg);
        this.handleClose();
        this.getList();
      }).catch(e => {
        this.is_loading = false;
      })

    },
    save() {
      if (this.is_loading) {
        return;
      }
      this.is_loading = true;
      this.$request({
        url: '/mall/mall/file-edit',
        data: this.form,
        method: 'post'
      }).then(res => {
        this.is_loading = false;
        if (res.code == 0) {
          this.$message.success(res.msg);
          this.handleClose();
          this.getList();
        }
      }).catch(e => {
        this.is_loading = false;
      })

    },
  },
}
</script>
<style scoped>
</style>
