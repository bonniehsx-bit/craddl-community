<template>
  <div class="app-container">
    <!-- 1. 顶部搜索栏 -->
    <el-form :model="queryParams" ref="queryForm" size="small" :inline="true" v-show="showSearch">
      <el-form-item label="角色名称" prop="role_name">
        <el-input
          v-model="queryParams.role_name"
          placeholder="请输入角色名称"
          clearable
          style="width: 240px"
          @keyup.enter.native="handleQuery"
        />
      </el-form-item>
      <el-form-item label="权限字符" prop="role_key">
        <el-input
          v-model="queryParams.role_key"
          placeholder="请输入权限字符"
          clearable
          style="width: 240px"
          @keyup.enter.native="handleQuery"
        />
      </el-form-item>
      <el-form-item label="创建时间">
        <el-date-picker
          v-model="dateRange"
          style="width: 240px"
          value-format="yyyy-MM-dd"
          type="daterange"
          range-separator="-"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
        ></el-date-picker>
      </el-form-item>
      <el-form-item>
        <el-button type="primary" icon="el-icon-search" size="mini" @click="handleQuery">搜索</el-button>
        <el-button icon="el-icon-refresh" size="mini" @click="resetQuery">重置</el-button>
      </el-form-item>
    </el-form>

    <!-- 2. 功能按钮栏 -->
    <el-row :gutter="10" class="mb8">
      <el-col :span="1.5">
        <el-button
          type="primary"
          plain
          icon="el-icon-plus"
          size="mini"
          @click="handleAdd"
          v-hasPermi="['system:role:add']"
        >新增</el-button>
      </el-col>
      <el-col :span="1.5">
        <el-button
          type="success"
          plain
          icon="el-icon-edit"
          size="mini"
          :disabled="single"
          @click="handleUpdate"
          v-hasPermi="['system:role:edit']"
        >修改</el-button>
      </el-col>
      <el-col :span="1.5">
        <el-button
          type="danger"
          plain
          icon="el-icon-delete"
          size="mini"
          :disabled="multiple"
          @click="handleDelete"
          v-hasPermi="['system:role:remove']"
        >删除</el-button>
      </el-col>

      <right-toolbar :showSearch.sync="showSearch" @queryTable="getList"></right-toolbar>
    </el-row>

    <!-- 3. 数据表格 -->
    <el-table
      v-loading="loading"
      :data="roleList"
      @selection-change="handleSelectionChange"
      border
    >
      <el-table-column type="selection" width="55" align="center" />
      <el-table-column label="角色编号" prop="role_id" width="120" />
      <el-table-column label="角色名称" prop="role_name" :show-overflow-tooltip="true" width="150" />
      <el-table-column label="权限字符" prop="role_key" :show-overflow-tooltip="true" width="150" />
      <el-table-column label="显示顺序" prop="role_sort" width="100" />
      <el-table-column label="状态" align="center" width="100">
        <template slot-scope="scope">
          <el-tag v-if="scope.row.status == 1" type="success" size="mini">启用</el-tag>
          <el-tag v-else type="info" size="mini">停用</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="创建时间" align="center" prop="created_time" width="180">
        <template slot-scope="scope">
          <span>{{ parseTime(scope.row.created_time) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" align="center" class-name="small-padding fixed-width">
        <template slot-scope="scope">
          <el-button
            size="mini"
            type="text"
            icon="el-icon-edit"
            @click="handleUpdate(scope.row)"
            v-hasPermi="['system:role:edit']"
          >修改</el-button>
          <el-button
            size="mini"
            type="text"
            icon="el-icon-delete"
            @click="handleDelete(scope.row)"
            v-hasPermi="['system:role:remove']"
          >删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 4. 分页组件 -->
    <div style="margin-top: 20px; text-align: right;" v-show="total>0">
      <el-pagination
        background
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"
        :current-page="queryParams.page"
        :page-sizes="[10, 20, 30, 50]"
        :page-size="queryParams.limit"
        layout="total, sizes, prev, pager, next, jumper"
        :total="total">
      </el-pagination>
    </div>

    <!-- 5. 添加或修改角色对话框 -->
    <el-dialog :title="title" :visible.sync="open" width="600px" append-to-body>
      <el-form ref="form" :model="form" :rules="rules" label-width="100px">
        <el-form-item label="角色名称" prop="role_name">
          <el-input v-model="form.role_name" placeholder="请输入角色名称" />
        </el-form-item>
        <el-form-item prop="role_key">
          <span slot="label">
            <el-tooltip content="控制器中定义的权限字符，如：admin" placement="top">
              <i class="el-icon-question"></i>
            </el-tooltip>
            权限字符
          </span>
          <el-input v-model="form.role_key" placeholder="请输入权限字符" />
        </el-form-item>
        <el-form-item label="角色顺序" prop="role_sort">
          <el-input-number v-model="form.role_sort" controls-position="right" :min="0" />
        </el-form-item>
        <el-form-item label="状态">
          <el-radio-group v-model="form.status">
            <el-radio :label="1">启用</el-radio>
            <el-radio :label="0">停用</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="菜单权限">
          <el-checkbox v-model="menuExpand" @change="handleCheckedTreeExpand($event)">展开/折叠</el-checkbox>
          <el-checkbox v-model="menuNodeAll" @change="handleCheckedTreeNodeAll($event)">全选/全不选</el-checkbox>
          <!--
            【修复点】：添加 :true-label="1" :false-label="0"
            确保提交给后端的是 int 类型，而不是 boolean
          -->
          <el-checkbox
            v-model="form.menu_check_strictly"
            :true-label="1"
            :false-label="0"
          >父子联动</el-checkbox>

          <!--
            check-strictly: 在显示复选框的情况下，是否严格的遵循父子不互相关联的做法
            如果 menu_check_strictly 为 1 (联动)，则 check-strictly 应为 false (不严格)
            如果 menu_check_strictly 为 0 (不联动)，则 check-strictly 应为 true (严格)
          -->
          <el-tree
            class="tree-border"
            :data="menuOptions"
            show-checkbox
            ref="menu"
            node-key="id"
            :check-strictly="form.menu_check_strictly == 0"
            empty-text="加载中，请稍候"
            :props="defaultProps"
          ></el-tree>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remark" type="textarea" placeholder="请输入内容"></el-input>
        </el-form-item>
      </el-form>
      <div slot="footer" class="dialog-footer">
        <el-button type="primary" @click="submitForm">确 定</el-button>
        <el-button @click="cancel">取 消</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
// 本页面完全独立，不引用外部 API 文件

export default {
  name: "Role",
  data() {
    return {
      // 遮罩层
      loading: true,
      // 选中数组
      ids: [],
      // 非单个禁用
      single: true,
      // 非多个禁用
      multiple: true,
      // 显示搜索条件
      showSearch: true,
      // 总条数
      total: 0,
      // 角色表格数据
      roleList: [],
      // 弹出层标题
      title: "",
      // 是否显示弹出层
      open: false,
      // 菜单展开状态
      menuExpand: false,
      // 菜单全选状态
      menuNodeAll: false,
      // 日期范围
      dateRange: [],
      // 菜单列表
      menuOptions: [],
      // 查询参数
      queryParams: {
        page: 1,
        limit: 10,
        role_name: undefined,
        role_key: undefined,
        status: undefined,
        params: undefined
      },
      // 表单参数
      form: {},
      defaultProps: {
        children: "children",
        label: "label"
      },
      // 表单校验
      rules: {
        role_name: [
          { required: true, message: "角色名称不能为空", trigger: "blur" }
        ],
        role_key: [
          { required: true, message: "权限字符不能为空", trigger: "blur" }
        ],
        role_sort: [
          { required: true, message: "角色顺序不能为空", trigger: "blur" }
        ]
      }
    };
  },
  created() {
    this.getList();
  },
  methods: {
    // =======================================================
    // 接口请求逻辑区域
    // =======================================================

    /** 查询角色列表 */
    getList() {
      this.loading = true;

      // 构造 params JSON 字符串
      if (this.dateRange && this.dateRange.length > 0) {
        this.queryParams.params = JSON.stringify({
          beginTime: this.dateRange[0],
          endTime: this.dateRange[1]
        });
      } else {
        this.queryParams.params = undefined;
      }

      this.$request({
        url: '/admin/admin/roles',
        method: 'get',
        data: this.queryParams
      }).then(response => {
        if (response.code === 0) {
          // 适配后端 ResponseHelper
          const data = response.data;
          this.roleList = data.rows || data.list || [];
          this.total = data.total || 0;
        } else {
          this.$modal.msgError(response.msg);
        }
        this.loading = false;
      }).catch(() => {
        this.loading = false;
      });
    },

    /** 查询角色详情 */
    getRoleDetail(roleId) {
      return this.$request({
        url: '/admin/admin/role',
        method: 'get',
        data: { role_id: roleId }
      });
    },

    /** 提交新增/修改角色 */
    submitRole(data) {
      return this.$request({
        url: '/admin/admin/role-edit',
        method: 'post',
        data: data
      });
    },

    /** 删除角色 */
    deleteRole(roleId) {
      return this.$request({
        url: '/admin/admin/role-delete',
        method: 'post',
        data: { role_id: roleId }
      });
    },

    /** 获取角色-菜单树 (带选中状态回显) */
    getRoleMenuTree(roleId) {
      return this.$request({
        url: '/admin/admin/role-menu-tree-select',
        method: 'get',
        data: { role_id: roleId }
      });
    },

    // =======================================================
    // 页面交互逻辑区域
    // =======================================================

    /** 搜索按钮操作 */
    handleQuery() {
      this.queryParams.page = 1;
      this.getList();
    },

    /** 重置按钮操作 */
    resetQuery() {
      this.dateRange = [];
      this.resetForm("queryForm");
      this.handleQuery();
    },

    /** 多选框选中数据 */
    handleSelectionChange(selection) {
      this.ids = selection.map(item => item.role_id);
      this.single = selection.length != 1;
      this.multiple = !selection.length;
    },

    /** 处理分页大小变化 */
    handleSizeChange(val) {
      this.queryParams.limit = val;
      this.getList();
    },

    /** 处理页码变化 */
    handleCurrentChange(val) {
      this.queryParams.page = val;
      this.getList();
    },

    // 树权限（展开/折叠）
    handleCheckedTreeExpand(value) {
      let treeList = this.menuOptions;
      for (let i = 0; i < treeList.length; i++) {
        this.$refs.menu.store.nodesMap[treeList[i].id].expanded = value;
      }
    },

    // 树权限（全选/全不选）
    handleCheckedTreeNodeAll(value) {
      this.$refs.menu.setCheckedNodes(value ? this.menuOptions : []);
    },

    // 获取所有选中的菜单ID (包含半选)
    getMenuAllCheckedKeys() {
      // 目前被选中的菜单节点
      let checkedKeys = this.$refs.menu.getCheckedKeys();
      // 半选中的菜单节点
      let halfCheckedKeys = this.$refs.menu.getHalfCheckedKeys();
      checkedKeys.unshift.apply(checkedKeys, halfCheckedKeys);
      return checkedKeys;
    },

    /** 取消按钮 */
    cancel() {
      this.open = false;
      this.reset();
    },

    /** 表单重置 */
    reset() {
      if (this.$refs.menu != undefined) {
        this.$refs.menu.setCheckedKeys([]);
      }
      this.menuExpand = false;
      this.menuNodeAll = false;
      this.form = {
        role_id: undefined,
        role_name: undefined,
        role_key: undefined,
        role_sort: 0,
        status: 1, // 默认启用
        menuIds: [],
        menu_check_strictly: 1, // 默认开启联动 (int类型)
        remark: undefined
      };
      this.resetForm("form");
    },

    /** 新增按钮操作 */
    handleAdd() {
      this.reset();
      this.open = true;
      this.title = "添加角色";
      // 新增时 role_id 传 0 获取完整菜单树
      this.getRoleMenuTree(0).then(response => {
        if(response.code === 0) {
          this.menuOptions = response.data.menus;
        }
      });
    },

    /** 修改按钮操作 */
    handleUpdate(row) {
      this.reset();
      // 兼容行点击和多选框
      const roleId = row.role_id || this.ids[0];

      const p1 = this.getRoleMenuTree(roleId);
      const p2 = this.getRoleDetail(roleId);

      Promise.all([p1, p2]).then(([resTree, resDetail]) => {
        if (resTree.code === 0 && resDetail.code === 0) {
          this.form = resDetail.data;

          // 【核心修复】：强制转换为数字，防止后端返回字符串导致类型不匹配
          this.form.status = parseInt(this.form.status);
          this.form.menu_check_strictly = parseInt(this.form.menu_check_strictly);

          this.menuOptions = resTree.data.menus;

          this.open = true;
          this.title = "修改角色";

          this.$nextTick(() => {
            let checkedKeys = resTree.data.checkedKeys;
            checkedKeys.forEach((v) => {
              this.$nextTick(() => {
                // 回显选中状态，第三个参数 false 防止自动联动导致错误
                if (this.$refs.menu) {
                  this.$refs.menu.setChecked(v, true, false);
                }
              });
            });
          });
        }
      });
    },

    /** 提交按钮 */
    submitForm: function() {
      this.$refs["form"].validate(valid => {
        if (valid) {
          // 获取 Tree 选中数据
          this.form.menuIds = this.getMenuAllCheckedKeys();

          const actionText = this.form.role_id ? "修改" : "新增";

          this.submitRole(this.form).then(response => {
            if (response.code === 0) {
              this.$modal.msgSuccess(actionText + "成功");
              this.open = false;
              this.getList();
            } else {
              this.$modal.msgError(response.msg);
            }
          });
        }
      });
    },

    /** 删除按钮操作 */
    handleDelete(row) {
      const roleId = row.role_id || this.ids[0];
      this.$modal.confirm('是否确认删除角色编号为"' + roleId + '"的数据项？').then(() => {
        return this.deleteRole(roleId);
      }).then((response) => {
        if (response.code === 0) {
          this.getList();
          this.$modal.msgSuccess("删除成功");
        } else {
          this.$modal.msgError(response.msg);
        }
      }).catch(() => {});
    },

    // 格式化时间
    parseTime(time, pattern) {
      if (arguments.length === 0 || !time) {
        return null;
      }
      const format = pattern || '{y}-{m}-{d} {h}:{i}:{s}';
      let date;
      if (typeof time === 'object') {
        date = time;
      } else {
        if ((typeof time === 'string') && (/^[0-9]+$/.test(time))) {
          time = parseInt(time);
        }
        if ((typeof time === 'number') && (time.toString().length === 10)) {
          time = time * 1000;
        }
        date = new Date(time);
      }
      const formatObj = {
        y: date.getFullYear(),
        m: date.getMonth() + 1,
        d: date.getDate(),
        h: date.getHours(),
        i: date.getMinutes(),
        s: date.getSeconds(),
        a: date.getDay()
      };
      const time_str = format.replace(/{(y|m|d|h|i|s|a)+}/g, (result, key) => {
        let value = formatObj[key];
        if (key === 'a') { return ['日', '一', '二', '三', '四', '五', '六'][value]; }
        if (result.length > 0 && value < 10) {
          value = '0' + value;
        }
        return value || 0;
      });
      return time_str;
    }
  }
};
</script>
