<template>
  <el-drawer
    title="系统菜单结构管理"
    :visible.sync="dialogVisible"
    size="1200px"
    direction="rtl"
    append-to-body
    :wrapper-closable="true"
  >
    <div class="drawer-wrap">
      <div class="drawer-body">
    <el-card shadow="never" style="margin-bottom: 15px; border: none;" :body-style="{ padding: '10px' }">
      <el-row type="flex" justify="space-between" align="middle">
        <el-col :span="18">
          <el-form :inline="true" size="small">
            <el-form-item label="菜单名称">
              <el-input
                v-model="queryParams.menu_name"
                placeholder="输入菜单名称筛选"
                clearable
                prefix-icon="el-icon-search"
                @keyup.enter.native="getList"
              />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" icon="el-icon-search" @click="getList">搜索</el-button>
              <el-button icon="el-icon-refresh-left" @click="resetQuery">重置</el-button>
            </el-form-item>
          </el-form>
        </el-col>
        <el-col :span="6" style="text-align: right;">
          <el-button type="info" plain icon="el-icon-sort" @click="toggleExpandAll" size="small">展开/折叠</el-button>
        </el-col>
      </el-row>
    </el-card>

    <el-table
      v-if="refreshTable"
      v-loading="loading"
      :data="menuList"
      row-key="menu_id"
      :default-expand-all="isExpandAll"
      :tree-props="{children: 'children', hasChildren: 'hasChildren'}"
      border
      height="calc(100vh - 280px)"
      size="small"
    >
      <el-table-column prop="menu_name" label="菜单名称" min-width="180" show-overflow-tooltip>
        <template slot-scope="scope">
          <span style="font-weight: bold;">{{ scope.row.menu_name }}</span>
        </template>
      </el-table-column>
      <el-table-column prop="icon" label="图标" align="center" width="70">
        <template slot-scope="scope">
          <svg-icon :icon-class="scope.row.icon" v-if="scope.row.icon"/>
        </template>
      </el-table-column>
      <el-table-column prop="order_num" label="排序" width="70" align="center"></el-table-column>
      <el-table-column prop="path" label="路由地址" min-width="150" show-overflow-tooltip></el-table-column>
      <el-table-column prop="component" label="组件路径" min-width="180" show-overflow-tooltip>
        <template slot-scope="scope">
          <span v-if="scope.row.component" style="color: #909399;">{{ scope.row.component }}</span>
          <span v-else style="color: #C0C4CC;">-</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" align="center" width="100" fixed="right">
        <template slot-scope="scope">
          <el-button size="mini" type="text" icon="el-icon-edit" @click="handleUpdate(scope.row)">修 改</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-drawer
      :title="title"
      :visible.sync="open"
      size="780px"
      direction="rtl"
      append-to-body
      :wrapper-closable="false"
    >
      <div class="drawer-wrap">
        <div class="drawer-body">
      <el-form ref="form" :model="form" :rules="rules" label-width="100px" size="small">

        <el-divider content-position="left"><i class="el-icon-s-grid"></i> 基础结构</el-divider>
        <el-row :gutter="20">
          <el-col :span="24">
            <el-form-item label="上级菜单" prop="parent_id">
              <treeselect
                v-model="form.parent_id"
                :options="menuOptions"
                :normalizer="normalizer"
                placeholder="系统级菜单节点已锁定"
                append-to-body
                z-index="3500"
                disabled
              />
            </el-form-item>
          </el-col>
          <el-col :span="24">
            <el-form-item label="菜单类型" prop="menu_type">
              <el-radio-group v-model="form.menu_type" disabled>
                <el-radio-button label="M">目录</el-radio-button>
                <el-radio-button label="C">菜单</el-radio-button>
                <el-radio-button label="F">按钮</el-radio-button>
              </el-radio-group>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="菜单名称" prop="menu_name">
              <el-input v-model="form.menu_name" placeholder="请输入菜单名称"/>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="显示排序" prop="order_num">
              <el-input-number v-model="form.order_num" controls-position="right" :min="0" style="width: 100%"/>
            </el-form-item>
          </el-col>
        </el-row>

        <template v-if="form.menu_type !== 'F'">
          <el-divider content-position="left"><i class="el-icon-connection"></i> 路由配置</el-divider>
          <el-row :gutter="20">
            <el-col :span="12">
              <el-form-item label="菜单图标">
                <el-popover placement="bottom-start" width="460" trigger="click" @show="$refs['iconSelect'] && $refs['iconSelect'].reset()">
                  <IconSelect ref="iconSelect" @selected="selected"/>
                  <el-input slot="reference" v-model="form.icon" placeholder="点击选择图标" readonly>
                    <svg-icon v-if="form.icon" slot="prefix" :icon-class="form.icon" style="width: 16px;"/>
                    <i v-else slot="prefix" class="el-icon-search el-input__icon"/>
                  </el-input>
                </el-popover>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="所属插件">
                <el-select v-model="form.plugin" placeholder="系统预设插件" style="width: 100%" disabled>
                  <el-option label="基础商城" value="mall"></el-option>
                  <el-option v-for="item in pluginList" :key="item.name" :label="item.label" :value="item.name"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item prop="path">
                <span slot="label">路由地址</span>
                <el-input v-model="form.path" placeholder="由系统迁移文件托管" disabled/>
              </el-form-item>
            </el-col>
            <template v-if="form.menu_type === 'C'">
              <el-col :span="12">
                <el-form-item prop="component">
                  <span slot="label">组件路径</span>
                  <el-input v-model="form.component" placeholder="由系统迁移文件托管" disabled/>
                </el-form-item>
              </el-col>
              <el-col :span="12" v-if="form.is_frame === 0">
                <el-form-item prop="name">
                  <span slot="label">组件名称</span>
                  <el-input v-model="form.name" placeholder="由系统迁移文件托管" disabled/>
                </el-form-item>
              </el-col>
            </template>
          </el-row>

          <el-divider content-position="left"><i class="el-icon-setting"></i> 功能设置</el-divider>
          <el-row :gutter="20">
            <el-col :span="8">
              <el-form-item label="显示状态">
                <el-switch v-model="form.hidden" :active-value="0" :inactive-value="1" active-text="显" inactive-text="隐"></el-switch>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="外链跳转">
                <el-switch v-model="form.is_frame" :active-value="1" :inactive-value="0"></el-switch>
              </el-form-item>
            </el-col>
            <template v-if="form.menu_type === 'C'">
              <el-col :span="8">
                <el-form-item label="页面缓存">
                  <el-switch v-model="form.is_cache" :active-value="1" :inactive-value="0"></el-switch>
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item label="Tag视图">
                  <el-switch v-model="form.show_tag" :active-value="1" :inactive-value="0"></el-switch>
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item label="面包屑">
                  <el-switch v-model="form.breadcrumb" :active-value="1" :inactive-value="0"></el-switch>
                </el-form-item>
              </el-col>
            </template>
          </el-row>

          <el-row v-if="form.hidden === 1">
            <el-col :span="24">
              <el-form-item label="高亮关联" prop="active_menu">
                <el-input v-model="form.active_menu" placeholder="侧边栏需要高亮的菜单路由"/>
              </el-form-item>
            </el-col>
          </el-row>
        </template>
      </el-form>
        </div>
        <div class="drawer-footer">
          <el-button size="small" @click="cancel">取 消</el-button>
          <el-button size="small" type="primary" @click="submitForm" :loading="submitLoading">保 存</el-button>
        </div>
      </div>
    </el-drawer>

      </div>
      <div class="drawer-footer">
        <el-button size="small" type="primary" @click="dialogVisible = false">确 定</el-button>
      </div>
    </div>
  </el-drawer>
</template>

<script>
import Treeselect from "@riophae/vue-treeselect";
import "@riophae/vue-treeselect/dist/vue-treeselect.css";
import IconSelect from "@/components/IconSelect";

export default {
  name: "SystemMenuManager",
  components: { Treeselect, IconSelect },
  props: {
    value: { type: Boolean, default: false }
  },
  computed: {
    dialogVisible: {
      get() { return this.value; },
      set(val) { this.$emit('input', val); }
    }
  },
  watch: {
    dialogVisible(val) {
      if (val) {
        this.getList();
        this.getPlugins();
      }
    }
  },
  data() {
    return {
      loading: true,
      submitLoading: false,
      menuList: [],
      menuOptions: [],
      pluginList: [],
      title: "",
      open: false,
      isExpandAll: false,
      refreshTable: true,
      queryParams: { menu_name: undefined },
      form: {},
      rules: {
        menu_name: [{ required: true, message: "菜单名称不能为空", trigger: "blur" }],
        order_num: [{ required: true, message: "菜单顺序不能为空", trigger: "blur" }],
        path: [{ required: true, message: "路由地址不能为空", trigger: "blur" }]
      }
    };
  },
  methods: {
    getPlugins() {
      this.$request({ url: '/admin/admin/plugin-map', method: 'get' }).then(res => {
        if (res.code === 0) this.pluginList = res.data.list || [];
      });
    },
    selected(name) {
      this.form.icon = name;
    },
    getList() {
      this.loading = true;
      this.$request({
        url: '/admin/admin/menu-list',
        method: 'get',
        data: this.queryParams
      }).then(response => {
        this.menuList = this.handleTree(response.data, "menu_id");
        this.loading = false;
      }).catch(() => { this.loading = false; });
    },
    handleTree(data, id, parentId = 'parent_id', children = 'children', rootId = '0') {
      const cloneData = JSON.parse(JSON.stringify(data));
      return cloneData.filter(father => {
        let branchArr = cloneData.filter(child => String(father[id]) === String(child[parentId]));
        if (branchArr.length > 0) father[children] = branchArr;
        return String(father[parentId]) === String(rootId);
      });
    },
    normalizer(node) {
      if (node.children && !node.children.length) delete node.children;
      return { id: node.menu_id, label: node.menu_name, children: node.children };
    },
    getTreeselect() {
      this.menuOptions = [{ menu_id: '0', menu_name: '主类目', children: this.menuList }];
    },
    cancel() {
      this.open = false;
      this.reset();
    },
    reset() {
      this.form = {
        menu_id: undefined,
        parent_id: '0',
        menu_name: undefined,
        icon: undefined,
        menu_type: "M",
        order_num: 0,
        is_frame: 0,
        is_cache: 1,
        hidden: 0,
        path: '',
        component: '',
        name: '',
        plugin: 'mall',
        breadcrumb: 1,
        show_tag: 1,
        active_menu: ''
      };
      if (this.$refs.form) this.$refs.form.clearValidate();
    },
    resetQuery() {
      this.queryParams = { menu_name: undefined };
      this.getList();
    },
    handleAdd(row) {
      this.reset();
      this.getTreeselect();
      this.form.parent_id = (row && row.menu_id) ? row.menu_id : '0';
      this.open = true;
      this.title = "添加系统菜单";
    },
    toggleExpandAll() {
      this.refreshTable = false;
      this.isExpandAll = !this.isExpandAll;
      this.$nextTick(() => { this.refreshTable = true; });
    },
    handleUpdate(row) {
      this.reset();
      this.getTreeselect();
      this.$request({
        url: '/admin/admin/menu',
        method: 'get',
        data: { menu_id: row.menu_id }
      }).then(response => {
        this.form = response.data;
        ['hidden', 'is_cache', 'is_frame', 'breadcrumb', 'show_tag'].forEach(prop => {
          if (this.form[prop] !== undefined) this.form[prop] = parseInt(this.form[prop]);
        });
        this.open = true;
        this.title = "修改系统菜单";
      });
    },
    submitForm() {
      this.$refs["form"].validate(valid => {
        if (!valid) return;
        if (this.form.menu_id && this.form.parent_id === this.form.menu_id) {
          this.$modal.msgError("父级菜单不能选择自己");
          return;
        }
        this.submitLoading = true;
        this.$request({
          url: '/admin/admin/menu-edit',
          method: 'post',
          data: this.form
        }).then(response => {
          if (response.code === 0) {
            this.$modal.msgSuccess("保存成功");
            this.open = false;
            this.getList();
          } else {
            this.$modal.msgError(response.msg);
          }
        }).finally(() => { this.submitLoading = false; });
      });
    },
    handleDelete(row) {
      this.$modal.confirm('确定要删除 "' + row.menu_name + '" 吗？').then(() => {
        return this.$request({
          url: '/admin/admin/menu-delete',
          method: 'post',
          data: { menu_id: row.menu_id }
        });
      }).then((res) => {
        if (res.code === 0) {
          this.getList();
          this.$modal.msgSuccess("删除成功");
        } else {
          this.$modal.msgError(res.msg);
        }
      }).catch(() => {});
    }
  }
};
</script>

<style scoped>
/* el-drawer__body 默认 overflow:auto 会把 footer 一起卷走，这里关掉外层滚动，让内部 .drawer-body 负责滚 */
::v-deep .el-drawer__body {
  padding: 0;
  overflow: hidden;
}

/* drawer 三段式 */
.drawer-wrap { display: flex; flex-direction: column; height: 100%; }
.drawer-body {
  flex: 1;
  overflow-y: auto;
  padding: 20px 24px;
}
.drawer-body::-webkit-scrollbar { width: 6px; }
.drawer-body::-webkit-scrollbar-thumb { background: #dcdfe6; border-radius: 3px; }
.drawer-footer {
  flex-shrink: 0;
  padding: 12px 24px;
  border-top: 1px solid #EBEDF0;
  text-align: right;
  background: #fff;
}
</style>
