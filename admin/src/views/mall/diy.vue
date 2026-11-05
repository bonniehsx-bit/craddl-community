<template>
  <div class="app-container">
    <!-- 首页主题切换 ──────────────────────────────────────────── -->
    <el-card class="box-card" style="margin-bottom: 16px;" v-loading="theme_loading">
      <div slot="header" class="clearfix">
        <span>首页主题</span>
        <span style="float: right; color: #909399; font-size: 12px;">
          切到非 DIY 主题后，下方装修配置仍保留，切回 DIY 即可恢复
        </span>
      </div>
      <el-form :inline="true" size="small">
        <el-form-item label="当前主题">
          <el-select v-model="theme_code" style="width: 280px;" @change="onThemeChange">
            <el-option v-for="t in theme_list" :key="t.code"
              :label="t.label + (t.plugin ? '（插件：' + t.plugin + '）' : '')"
              :value="t.code" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="theme_code !== 'diy'">
          <el-tag type="warning">已切换至自定义主题，DIY 渲染暂不生效</el-tag>
        </el-form-item>
      </el-form>
      <div style="font-size: 12px; color: #909399; line-height: 1.6;">
        主题来自插件事件挂载（<code>HomeThemeEvent::EVENT_REGISTER</code>）。
        新增主题流程：插件创建 <code>HomeThemeListener</code> 监听该事件并 push 主题信息 →
        在插件 <code>Plugin.php::getListeners()</code> 注册 →
        前端约定 <code>uniapp/plugins1/&#123;plugin&#125;/home/HomeTheme.vue</code> 即可。
      </div>
    </el-card>

    <el-card class="box-card" v-loading="is_loading">
      <div slot="header" class="clearfix">
        <span>页面列表</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="addPage">新建页面</el-button>
      </div>

      <el-table size="mini"  :data="list" border style="width: 100%"
                @row-dblclick="editPage">
        <template slot="append">
          <div style="text-align:center;color:#c0c4cc;font-size:12px;padding:6px 0;">💡 双击任意行可快速编辑装修</div>
        </template>
        <el-table-column prop="id" label="ID">
        </el-table-column>
        <el-table-column prop="name" label="名称">
        </el-table-column>
        <el-table-column label="页面链接">
          <template slot-scope="scope">
            /pages/diy/diy?id={{scope.row.id}}
          </template>
        </el-table-column>
        <el-table-column label="角色" width="160">
          <template slot-scope="scope">
            <el-tag v-if="scope.row.is_home==1" size="mini" type="success" effect="dark">主页</el-tag>
            <el-tag v-if="scope.row.is_audit_page==1" size="mini" type="warning" effect="dark" style="margin-left:4px;">审核版</el-tag>
            <span v-if="scope.row.is_home==0 && scope.row.is_audit_page!=1" style="color:#909399;font-size:12px;">普通页面</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="260">
          <template slot-scope="scope">
            <el-button type="text" @click="setHome(scope.row)" v-if="scope.row.is_home==0">设为主页</el-button>
            <el-button type="text" @click="setAuditPage(scope.row)" v-if="scope.row.is_audit_page!=1">设为审核版</el-button>
            <el-button type="text" style="color:#909399" @click="unsetAuditPage(scope.row)" v-else>取消审核版</el-button>
            <el-button type="text" @click="editPage(scope.row)">编辑</el-button>
            <el-button type="text" @click="deletePage(scope.row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div style="text-align: right;margin: 20px 0;">
     <pagination :pagination="pagination" v-model="page" @pageChange="getList">
     </pagination>
      </div>
    </el-card>

    <!-- 页面装修抽屉（全屏，替代原跳转 /mall/diy-edit）-->
    <el-drawer
      :visible.sync="diyDrawerVisible"
      direction="rtl"
      size="90%"
      :with-header="false"
      :destroy-on-close="true"
      :wrapper-closable="true"
      custom-class="diy-edit-drawer"
    >
      <diy-edit
        v-if="diyDrawerVisible"
        :embedded="true"
        :page-id="diyEditId"
        @saved="onDiySaved"
        @close="diyDrawerVisible = false"
      />
    </el-drawer>
  </div>
</template>

<script>
  import DiyEdit from './diy-edit.vue'

  export default {
    name: 'mall-diy',
    components: { DiyEdit },
    data() {
      return {
        page: 1,
        list: [],
        is_loading: false,
        pagination: null,
        // 主题切换 —— 主题列表由后端事件聚合（插件挂载 HomeThemeEvent::EVENT_REGISTER）
        theme_loading: false,
        theme_code: 'diy',
        theme_list: [{ code: 'diy', label: 'DIY 装修引擎（默认）', plugin: '' }],
        // 装修编辑抽屉
        diyDrawerVisible: false,
        diyEditId: null,
      }
    },
    created() {
      this.getList();
      this.fetchThemeList();
      this.fetchHomeTheme();
    },
    methods: {
      getList() {
        this.is_loading = true;
        this.$request({
          url: '/mall/mall/diy',
          data: {
            page: this.page,
          }
        }).then(res => {
          this.is_loading = false;


          if (res.code == 0) {
            this.list = res.data.list
            this.pagination = res.data.pagination
          }
        })

      },
      editPage(row) {
        this.diyEditId = row.id;
        this.diyDrawerVisible = true;
      },
      addPage() {
        this.diyEditId = null;
        this.diyDrawerVisible = true;
      },
      // 装修保存成功 → 刷新列表（抽屉保持打开，方便继续编辑）
      onDiySaved() {
        this.getList();
      },
      deletePage(row) {
        this.$confirm('确认删除页面：' + row.name + '？', '提示', {
          type: 'warning'
        }).then(() => {
          this.is_loading = true;
          this.$request({
            url: "/mall/mall/diy-delete",
            data: {
              id: row.id
            },
            method: 'POST'
          }).then(res => {
            this.is_loading = false;
            if (res.code == 0) {
              this.$message.success(res.msg)
              this.getList();
            }
          })
        }).catch(() => {


        })


      },
      fetchHomeTheme() {
        this.theme_loading = true;
        this.$request({ url: '/mall/mall/home-theme-get' }).then(res => {
          this.theme_loading = false;
          if (res.code === 0) this.theme_code = res.data.home_theme || 'diy';
        }).catch(() => { this.theme_loading = false; });
      },
      fetchThemeList() {
        // 通过事件挂载机制聚合：默认 diy + 各插件 Listener 注册的主题
        this.$request({ url: '/mall/mall/home-theme-list' }).then(res => {
          if (res.code === 0 && Array.isArray(res.data.list)) {
            this.theme_list = res.data.list;
          }
        }).catch(() => {});
      },
      onThemeChange(code) {
        this.theme_loading = true;
        this.$request({
          url: '/mall/mall/home-theme-set',
          method: 'POST',
          data: { home_theme: code },
        }).then(res => {
          this.theme_loading = false;
          if (res.code === 0) {
            this.$message.success(res.msg || '已切换');
          } else {
            this.$message.error(res.msg || '切换失败');
            this.fetchHomeTheme();
          }
        }).catch(() => { this.theme_loading = false; this.fetchHomeTheme(); });
      },
      setHome(row) {

        this.$confirm('确定将页面：' + row.name + '设为主页？', '提示', {
          type: 'warning'
        }).then(() => {
          this.is_loading = true;
          this.$request({
            url: "/mall/mall/diy-home",
            data: {
              id: row.id
            },
            method: 'POST'
          }).then(res => {
            this.is_loading = false;
            if (res.code == 0) {
              this.$message.success(res.msg)
              this.getList();
            }
          })
        }).catch(() => {


        })


      },
      setAuditPage(row) {
        this.$confirm(`将页面「${row.name}」设为审核版？\n\n商家版本审核期间（如 App Store / 微信小程序审核），C 端首页将自动返回该页面，用于隐藏敏感入口。\n非审核版本访问时不受影响。`, '设为审核版页面', {
          type: 'warning',
          confirmButtonText: '确定设置',
          dangerouslyUseHTMLString: false,
        }).then(() => {
          this.is_loading = true;
          this.$request({
            url: '/mall/mall/diy-audit-page',
            data: { id: row.id },
            method: 'POST',
          }).then(res => {
            this.is_loading = false;
            if (res.code == 0) {
              this.$message.success(res.msg);
              this.getList();
            } else {
              this.$message.error(res.msg);
            }
          }).catch(() => { this.is_loading = false; });
        }).catch(() => {});
      },
      unsetAuditPage(row) {
        this.$confirm(`取消页面「${row.name}」的审核版标记？\n\n取消后，审核版本访问时将 fallback 到主页（is_home）。`, '取消审核版标记', {
          type: 'info',
        }).then(() => {
          this.is_loading = true;
          this.$request({
            url: '/mall/mall/diy-unset-audit-page',
            data: { id: row.id },
            method: 'POST',
          }).then(res => {
            this.is_loading = false;
            if (res.code == 0) {
              this.$message.success(res.msg);
              this.getList();
            } else {
              this.$message.error(res.msg);
            }
          }).catch(() => { this.is_loading = false; });
        }).catch(() => {});
      },


    }

  }
</script>

<style>
/* 全屏装修抽屉：去掉 body 内边距，让 diy-edit 占满整个抽屉 */
.diy-edit-drawer .el-drawer__body {
  padding: 0;
  height: 100%;
  overflow: hidden;
}
</style>
