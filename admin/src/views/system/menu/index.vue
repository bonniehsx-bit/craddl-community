<template>
  <div class="app-container">
    <!-- 顶部提示 -->
    <el-alert title="菜单装修说明（仅影响本店）" type="info" show-icon close-text="知道了" style="margin-bottom: 15px;"
      description="此处是【本店专属】的菜单装修：可对本店后台菜单重命名、调整层级/排序、隐藏、设为主页。所有改动只影响当前商城的菜单展示，不会改动真实路由，也不会影响其他商城或系统。底层路由由系统托管保护，不会因装修而 404。">
    </el-alert>

    <!-- 操作栏 -->
    <el-row :gutter="10" class="mb8">
      <el-col :span="1.5">
        <el-button type="info" plain icon="el-icon-sort" size="mini" @click="toggleExpandAll">展开/折叠</el-button>
      </el-col>
      <el-col :span="1.5">
        <el-button type="primary" icon="el-icon-refresh" size="mini" @click="getList">刷新列表</el-button>
      </el-col>
      <el-col :span="1.5">
        <el-button type="danger" plain icon="el-icon-warning" size="mini" @click="handleReset">恢复系统默认布局</el-button>
      </el-col>
    </el-row>

    <!-- 表格 -->
    <el-table v-if="refreshTable" v-loading="loading" :data="menuList" row-key="menu_id"
      :default-expand-all="isExpandAll" :tree-props="{ children: 'children', hasChildren: 'hasChildren' }" border>
      <el-table-column prop="menu_name" label="显示名称" :show-overflow-tooltip="true" width="240">
        <template slot-scope="scope">
          <span>{{ scope.row.menu_name }}</span>
          <el-tag size="mini" effect="dark" type="danger" v-if="scope.row.is_home == 1" style="margin-left: 5px;"><i
              class="el-icon-s-home"></i> 主页</el-tag>
          <el-tag size="mini" effect="plain" v-if="scope.row.is_customized && scope.row.is_home != 1"
            style="margin-left: 5px;">已装修</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="icon" label="图标" align="center" width="60">
        <template slot-scope="scope">
          <i v-if="scope.row.icon && scope.row.icon.indexOf('el-icon') > -1" :class="scope.row.icon" style="font-size:18px"></i>
          <svg-icon v-else-if="scope.row.icon" :icon-class="scope.row.icon" />
          <span v-else style="color:#ccc">-</span>
        </template>
      </el-table-column>
      <el-table-column prop="path" label="路由地址" :show-overflow-tooltip="true"></el-table-column>
      <el-table-column prop="order_num" label="排序" align="center" width="60"></el-table-column>
      <el-table-column prop="hidden" label="状态" width="80" align="center">
        <template slot-scope="scope">
          <el-tag v-if="scope.row.hidden == 0" type="success" size="mini">显示</el-tag>
          <el-tag v-else type="info" size="mini">隐藏</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" align="center" width="100">
        <template slot-scope="scope">
          <el-button size="mini" type="text" icon="el-icon-edit" @click="handleEdit(scope.row)">装修</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 装修对话框 -->
    <el-dialog :title="title" :visible.sync="open" width="600px" append-to-body>
      <el-form ref="form" :model="form" :rules="rules" label-width="100px">

        <el-form-item label="系统原名">
          <span style="color: #909399">{{ form.original_name_display }}</span>
        </el-form-item>

        <!-- 上级菜单选择 -->
        <el-form-item label="上级菜单" prop="custom_parent_id">
          <treeselect v-model="form.custom_parent_id" :options="menuOptions" :normalizer="normalizer" :show-count="true"
            placeholder="选择上级菜单" />
        </el-form-item>

        <el-form-item label="显示名称" prop="custom_name">
          <el-input v-model="form.custom_name" placeholder="留空则使用系统原名" />
        </el-form-item>

        <el-form-item label="路由地址" prop="custom_path">
          <el-input v-model="form.custom_path" disabled placeholder="路由底层已被系统托管保护" />
          <div style="font-size: 12px; color: #67C23A;">
            <i class="el-icon-lock"></i> 该路由组已被系统沙盒高强度保护。您可以随意移动它的【上级菜单】进行重新排版，绝对不会发生 404 故障报错！
          </div>
        </el-form-item>

        <el-form-item label="显示排序" prop="custom_sort">
          <el-input-number v-model="form.custom_sort" controls-position="right" :min="-99" />
        </el-form-item>

        <el-form-item label="显示状态">
          <el-radio-group v-model="form.is_hide">
            <el-radio :label="0">显示</el-radio>
            <el-radio :label="1">隐藏</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item label="默认主页" v-if="form.menu_type === 'C'">
          <el-switch v-model="form.is_home" :active-value="1" :inactive-value="0" active-text="设为主页"
            inactive-text="普通菜单"></el-switch>
          <div style="font-size: 12px; color: #E6A23C;">
            <i class="el-icon-info"></i> 开启后，商户进入控制台将自动着陆于此模块。该操作互斥，全站唯一。仅建议对顶层功能大类设置主页。
          </div>
        </el-form-item>

      </el-form>
      <div slot="footer" class="dialog-footer">
        <el-button type="primary" @click="submitForm">保存配置</el-button>
        <el-button @click="open = false">取 消</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
/**
 * ⚠️ 架构说明（务必先读，避免误改）：
 *
 * 本页是【本店菜单装修】，不是【平台全局菜单树编辑】，两者千万别混：
 *
 *  ┌ 本页 system/menu/index.vue ────────────────────────────────────┐
 *  │  接口：/mall/menu/list、/mall/menu/option-save、/mall/menu/option-reset
 *  │  落库：MallMenuOption（按 mall_id 隔离的「覆盖项」）
 *  │  能改：重命名 / 调层级 / 排序 / 隐藏 / 设主页 —— 仅叠加在全局菜单之上
 *  │  锁死：真实路由(path/component) 不可改（系统托管，防 404）
 *  │  影响范围：★ 只影响当前商城 ★ 不碰全局菜单、不影响其它商城/系统
 *  │  谁能用：商城主账号(FOUNDER/店主) 即可，本就是给店主装修自己后台的
 *  └──────────────────────────────────────────────────────────────┘
 *
 *  ┌ 平台全局菜单树（另一个入口，勿与本页混淆）────────────────────┐
 *  │  入口：entry.vue 顶部「菜单管理」按钮 → SystemMenuManager 组件
 *  │  接口：/admin/admin/menu-edit、/admin/admin/menu-delete
 *  │  落库：jxmall_menu（全局共享行，所有商城公用）
 *  │  谁能用：仅平台超管(SUPER)，MenuEditForm/MenuDeleteForm 后端已强制 admin_type=SUPER
 *  └──────────────────────────────────────────────────────────────┘
 *
 * 结论：本页对店主开放是正确的（本店映射，互不影响）。不要因为它叫"菜单管理"
 *       就误判为会影响全局而去给它加超管限制/隐藏 —— 那是错的。
 */
import Treeselect from "@riophae/vue-treeselect";
import "@riophae/vue-treeselect/dist/vue-treeselect.css";

export default {
  name: "MenuSetup",
  components: { Treeselect },
  data() {
    return {
      loading: true,
      menuList: [],
      menuOptions: [], // 树形下拉选项
      isExpandAll: false,
      refreshTable: true,
      open: false,
      title: "菜单装修",

      form: {
        menu_id: null,
        original_name_display: '',
        custom_name: '',
        custom_path: '',
        custom_parent_id: '0',
        custom_sort: 0,
        is_hide: 0,
        is_home: 0,
        menu_type: ''
      },
      rules: {
        custom_sort: [{ required: true, message: "排序不能为空", trigger: "blur" }]
      }
    };
  },
  created() {
    this.getList();
  },
  methods: {
    getList() {
      this.loading = true;
      this.$request({
        url: '/mall/menu/list',
        method: 'get'
      }).then(response => {
        // 构建表格树
        this.menuList = this.handleTree(response.data, "menu_id");

        // 构建下拉树 (包含一个根节点)
        this.menuOptions = [];
        const menu = { menu_id: '0', menu_name: '主类目', children: [] };
        menu.children = this.handleTree(response.data, "menu_id");
        this.menuOptions.push(menu);

        this.loading = false;
      });
    },

    /** 构造树形结构 (通用方法) */
    handleTree(data, id, parentId, children, rootId) {
      id = id || 'id'
      parentId = parentId || 'parent_id'
      children = children || 'children'
      rootId = rootId || '0'
      const cloneData = JSON.parse(JSON.stringify(data))
      const treeData = cloneData.filter(father => {
        let branchArr = cloneData.filter(child => {
          return String(father[id]) === String(child[parentId])
        });
        if (branchArr.length > 0) {
          father[children] = branchArr;
        }
        return String(father[parentId]) === String(rootId);
      });
      return treeData != '' ? treeData : data;
    },

    /** TreeSelect 数据转换 */
    normalizer(node) {
      if (node.children && !node.children.length) {
        delete node.children;
      }
      return {
        id: node.menu_id,
        label: node.menu_name,
        children: node.children
      };
    },

    toggleExpandAll() {
      this.refreshTable = false;
      this.isExpandAll = !this.isExpandAll;
      this.$nextTick(() => {
        this.refreshTable = true;
      });
    },

    handleEdit(row) {
      // 这里的 row 已经是混合了商户配置的数据
      // 我们需要推断哪些是自定义的
      let realOriginalName = row.menu_name;
      let currentCustomName = '';

      // 如果被标记为已装修，尝试还原
      if (row.is_customized) {
        if (row.original_name) realOriginalName = row.original_name;
        // 如果当前显示名 != 原名，说明自定义了名称
        if (row.menu_name !== row.original_name) currentCustomName = row.menu_name;
      }

      this.form = {
        menu_id: row.menu_id,
        original_name_display: realOriginalName,

        // 如果当前名字和原名不一样，回显；否则留空
        custom_name: currentCustomName,

        // 路由地址：如果需要区分原路径，后端需要返回 original_path。
        // 这里暂时直接回显当前的 path，提交时如果和系统一样，后端会存null
        custom_path: row.path,

        custom_parent_id: row.parent_id,
        custom_sort: row.order_num,
        is_hide: row.hidden,
        is_home: row.is_home || 0,
        menu_type: row.menu_type
      };

      this.open = true;
    },

    submitForm() {
      // 防止自己选自己
      if (this.form.custom_parent_id == this.form.menu_id) {
        this.$modal.msgError("父级菜单不能选择自己");
        return;
      }

      this.$request({
        url: '/mall/menu/option-save',
        method: 'post',
        data: this.form
      }).then(res => {
        if (res.code === 0) {
          this.$modal.msgSuccess("配置已保存");
          this.open = false;
          this.getList();
          this.$confirm('结构已变更，立即刷新页面以应用？', '提示', {
            type: 'success'
          }).then(() => {
            location.reload();
          }).catch(() => { });
        } else {
          this.$modal.msgError(res.msg);
        }
      });
    },

    handleReset() {
      this.$confirm('此操作将永久清空您对菜单的所有 DIY 装修（包括自定义排序、层级移动、重命名等），彻底恢复为系统默认的原生状态，是否继续？', '严重警告', {
        confirmButtonText: '确定重置',
        cancelButtonText: '取消',
        type: 'warning'
      }).then(() => {
        this.loading = true;
        this.$request({
          url: '/mall/menu/option-reset',
          method: 'post'
        }).then(res => {
          if (res.code === 0) {
            this.$modal.msgSuccess(res.msg);
            setTimeout(() => {
              location.reload();
            }, 1000);
          } else {
            this.$modal.msgError(res.msg);
            this.loading = false;
          }
        }).catch(() => {
          this.loading = false;
        });
      }).catch(() => { });
    }
  }
};
</script>
