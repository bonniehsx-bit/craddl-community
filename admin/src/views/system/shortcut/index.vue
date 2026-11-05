<template>
  <div class="app-container">
    <el-row :gutter="20">
      <!-- 功能字典栏 -->
      <el-col :span="10">
        <el-card shadow="never" class="shortcut-card">
          <div slot="header" class="clearfix">
            <span style="font-weight: bold; font-size: 15px;"><i class="el-icon-menu"></i> 可配置功能库</span>
            <el-tooltip content="只能勾选具体页面（叶子节点），目录不可勾选。" placement="top">
              <i class="el-icon-info" style="color: #909399; margin-left: 5px;"></i>
            </el-tooltip>
            <el-button style="float: right; padding: 3px 0" type="text" @click="expandAll">展开/折叠</el-button>
          </div>
          <el-input v-model="filterText" placeholder="搜索组件名称" clearable size="small" prefix-icon="el-icon-search" style="margin-bottom: 10px;" />
          <div class="tree-container">
            <el-tree
              ref="menuTree"
              :data="menuList"
              :props="defaultProps"
              node-key="menu_id"
              show-checkbox
              check-strictly
              @check="handleTreeCheck"
              :filter-node-method="filterNode"
              :default-expand-all="isExpandAll"
            >
              <span class="custom-tree-node" slot-scope="{ node, data }"
                :class="{ 'is-dir-node': data.children && data.children.length > 0 }">
                <span>
                  <svg-icon :icon-class="data.icon" v-if="data.icon && data.icon !== '#'" style="margin-right: 5px;" />
                  {{ data.menu_name }}
                  <el-tag size="mini" type="info" v-if="data.children && data.children.length > 0" style="margin-left: 5px;">目录</el-tag>
                  <span v-else style="font-size:11px;color:#C0C4CC;margin-left:4px;">页面</span>
                </span>
              </span>
            </el-tree>
          </div>
        </el-card>
      </el-col>

      <!-- 快捷看板栏 -->
      <el-col :span="14">
        <el-card shadow="never" class="shortcut-card" v-loading="loading">
          <div slot="header" class="clearfix">
            <span style="font-weight: bold; font-size: 15px;"><i class="el-icon-star-on" style="color:#E6A23C"></i> 我的常用看板</span>
            <span style="font-size:12px;color:#999;margin-left:10px;">(支持上下拖拽排序)</span>
            <el-button style="float: right; padding: 6px 12px" type="primary" size="mini" @click="saveShortcuts"><i class="el-icon-check"></i> 立即保存</el-button>
          </div>

          <div class="drag-container">
            <draggable v-model="shortcuts" @start="drag=true" @end="drag=false" v-bind="dragOptions">
              <transition-group type="transition" name="flip-list">
                <div v-for="(element, index) in shortcuts" :key="element.path" class="drag-list-item">
                  <div class="item-left">
                    <i class="el-icon-s-operation drag-handle"></i>
                    <svg-icon :icon-class="element.icon" v-if="element.icon && element.icon !== '#'" class="item-icon" />
                    <span class="item-title">{{ element.title }}</span>
                    <el-tag v-if="element._invalid" size="mini" type="danger" style="margin-left:6px;">菜单已隐藏或删除</el-tag>
                      <div style="font-size: 12px; color:#999; margin-left:15px; display:inline-block;">{{ element.path }}</div>
                  </div>
                  <div class="item-right">
                    <el-button type="text" style="color: #F56C6C;" icon="el-icon-delete" @click="removeShortcut(index)">移除</el-button>
                  </div>
                </div>
              </transition-group>
            </draggable>

            <el-empty v-if="shortcuts.length === 0" description="您还没有添加任何常用菜单，请从左侧打勾即可添加"></el-empty>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script>
import draggable from "vuedraggable";

export default {
  name: "ShortcutConfig",
  components: { draggable },
  data() {
    return {
      filterText: "",
      menuList: [],
      // 完整路由路径 → menu_id。用于回显旧数据:早先存的快捷项只有 path 没有 menu_id
      // (标签页「加入常用」那条入口至今如此),靠它反查回节点。
      pathIdMap: {},
      shortcuts: [],
      defaultProps: {
        children: "children",
        label: "menu_name",
        disabled: "disabled"   // el-tree 原生禁用字段，目录节点会被注入 disabled: true
      },
      loading: false,
      isExpandAll: true,
      drag: false
    };
  },
  computed: {
    dragOptions() {
      return {
        animation: 200,
        group: "description",
        disabled: false,
        ghostClass: "ghost"
      };
    }
  },
  watch: {
    filterText(val) {
      this.$refs.menuTree.filter(val);
    }
  },
  created() {
    this.initData();
  },
  methods: {
    initData() {
      this.loading = true;
      const mallIdId = localStorage.getItem('mall_id');
      // 「我的常用」是个人配置:平台侧走不设权限的 shortcut-menus(而非需菜单管理权的 menu-list),
      // 否则子账号无「菜单管理」权限时进本页即被拦「无操作权限」。mall 侧 menu/list 本就不拦。
      const url = mallIdId ? '/mall/menu/list' : '/admin/admin/shortcut-menus';
      // 只列「显示中」的菜单(hidden=0)。隐藏的菜单在左侧导航里本就看不到,
      // 它们多是从列表页跳进去的编辑页(goods-edit / diy-edit / freight-edit 这类),
      // 单独拿出来当常用入口点进去往往缺参数、打不开;把它们摆在候选里只会误导人。
      // 两侧接口共用 MenuListForm,都认这个参数。
      // ⚠ GET 的查询参数要放 data —— request.js 拦截器里是 `config.params = config.data`,
      //   直接写 params 会被这行覆盖成 undefined,参数根本发不出去。
      this.$request({ url: url, method: 'get', data: { hidden: 0 } }).then(res => {
        this.menuList = this.markDirDisabled(this.handleTree(res.data, "menu_id"));
        this.buildPathIdMap(this.menuList, '');

        this.$request({ url: '/admin/admin/shortcut-list', method: 'get' }).then(res2 => {
          // 标记失效项:菜单被隐藏或删除后,对应的快捷项在左树里已经找不到了。
          // 不自动删除 —— 隐藏往往是临时的,替用户做主容易误删他攒的配置;
          // 只在右侧打个标记让他自己决定。
          this.shortcuts = (res2.data.list || []).map(s => {
            const id = s.menu_id || this.pathIdMap[s.path];
            return Object.assign({}, s, { _invalid: !id });
          });
          // 回显按 menu_id;旧数据没有该字段,用完整路径反查。
          // 原实现直接 setCheckedKeys(path) —— 而 path 大量重名(index 有 11 个、setting 有 10 个),
          // el-tree 的 nodesMap 以 node-key 为键,同名节点相互覆盖,回显必然勾错人。
          const checkedIds = this.shortcuts
            .map(s => s.menu_id || this.pathIdMap[s.path])
            .filter(Boolean);
          this.$refs.menuTree.setCheckedKeys(checkedIds);
          this.loading = false;
        });
      });
    },
    // 递归建「完整路由路径 → menu_id」映射。拼法与 SplitNav 一致:
    // 各级 path 用 / 连接、最前面补 /;子级 path 若已是绝对路径则直接用。
    buildPathIdMap(nodes, parentPath) {
      (nodes || []).forEach(n => {
        const seg = String(n.path || '');
        const full = seg.startsWith('/') ? seg : (parentPath + '/' + seg).replace(/\/+/g, '/');
        if (seg) this.pathIdMap[full] = n.menu_id;
        if (n.children && n.children.length) this.buildPathIdMap(n.children, full);
      });
    },
    // 由 menu_id 反推完整路由路径(顺着 el-tree 的 parent 链往上走)
    fullPathOf(menuId) {
      const node = this.$refs.menuTree.getNode(menuId);
      if (!node) return '';
      const parts = [];
      let cur = node;
      while (cur && cur.level > 0 && cur.data) {
        const seg = String(cur.data.path || '');
        if (seg.startsWith('/')) { parts.unshift(seg.replace(/^\/+/, '')); break; }
        if (seg) parts.unshift(seg);
        cur = cur.parent;
      }
      return '/' + parts.join('/');
    },
    filterNode(value, data) {
      if (!value) return true;
      return data.menu_name.indexOf(value) !== -1;
    },
    expandAll() {
      this.isExpandAll = !this.isExpandAll;
      let nodes = this.$refs.menuTree.store._getAllNodes();
      for (let i in nodes) {
        nodes[i].expanded = this.isExpandAll;
      }
    },
    handleTreeCheck(data, checkData) {
      // 目录节点（有子节点）不允许勾选，立即取消并提示
      if (data.children && data.children.length > 0) {
        this.$refs.menuTree.setChecked(data, false, false);
        this.$message({ message: '只能选择具体页面，不能选择目录', type: 'warning', duration: 1800 });
        return;
      }

      // 只取叶子节点（页面），二次过滤以防万一
      const checkedNodes = this.$refs.menuTree.getCheckedNodes(false, false)
        .filter(n => !n.children || n.children.length === 0);
      const newIds = checkedNodes.map(n => n.menu_id);

      // 保留已有顺序，追加新勾选的。比对用 menu_id,不能用 path —— path 大量重名。
      const rebuiltShortcuts = [];
      this.shortcuts.forEach(s => {
        const id = s.menu_id || this.pathIdMap[s.path];
        if (id && newIds.includes(id)) rebuiltShortcuts.push(s);
      });
      checkedNodes.forEach(n => {
        if (!rebuiltShortcuts.find(s => (s.menu_id || this.pathIdMap[s.path]) === n.menu_id)) {
          rebuiltShortcuts.push({
            menu_id: n.menu_id,
            title: n.menu_name,
            // 必须存**完整**路由路径:菜单表的 path 是相对的(商品列表就叫 "index"),
            // 而侧边栏是拿这个值直接当路由跳的 —— 存相对路径会跳到 /index(首页)去。
            path: this.fullPathOf(n.menu_id),
            icon: n.icon,
            name: n.name || ''
          });
        }
      });

      this.shortcuts = rebuiltShortcuts;
    },
    removeShortcut(index) {
      const item = this.shortcuts[index];
      this.shortcuts.splice(index, 1);
      const id = item.menu_id || this.pathIdMap[item.path];
      if (id) this.$refs.menuTree.setChecked(id, false, true);
    },
    saveShortcuts() {
      this.loading = true;
      this.$request({
        url: '/admin/admin/shortcut-update',
        method: 'post',
        data: {
          // _invalid 只是本页的展示标记,不入库
          shortcuts: this.shortcuts.map(s => ({
            menu_id: s.menu_id || '', title: s.title, path: s.path, icon: s.icon, name: s.name || ''
          }))
        }
      }).then(res => {
        this.loading = false;
        if (res.code === 0) {
          this.$modal.msgSuccess("保存常用配置成功，将刷新页面生效");
          setTimeout(() => {
            location.reload();
          }, 1000);
        } else {
          this.$modal.msgError(res.msg);
        }
      });
    },
    // 递归给目录节点注入 disabled: true，让 el-tree 原生禁用其 checkbox
    markDirDisabled(nodes) {
      if (!nodes) return nodes;
      nodes.forEach(node => {
        if (node.children && node.children.length > 0) {
          node.disabled = true;
          this.markDirDisabled(node.children);
        } else {
          node.disabled = false;
        }
      });
      return nodes;
    },
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
    }
  }
};
</script>

<style scoped>
.shortcut-card {
  height: calc(100vh - 120px);
  overflow-y: auto;
}
.shortcut-card ::v-deep .el-card__body {
  padding-top: 10px;
}
.tree-container {
  margin-top: 15px;
}
.drag-container {
  padding-bottom: 30px;
}
.drag-list-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 15px;
  background-color: #f8f9fa;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  margin-bottom: 10px;
  transition: all 0.3s;
}
.drag-list-item:hover {
  background-color: var(--admin-accent-soft);
  border-color: var(--admin-accent-border);
  box-shadow: 0 2px 4px rgba(0,0,0,.05);
}
.item-left {
  display: flex;
  align-items: center;
}
.drag-handle {
  cursor: grab;
  color: #909399;
  font-size: 16px;
  margin-right: 15px;
}
.drag-handle:active {
  cursor: grabbing;
}
.item-icon {
  margin-right: 8px;
  font-size: 16px;
  color: #606266;
}
.item-title {
  font-size: 14px;
  color: #303133;
}
.ghost {
  opacity: 0.5;
  background: var(--admin-accent-soft);
}
.flip-list-move {
  transition: transform 0.5s;
}
/* 目录节点标题色 */
.is-dir-node {
  color: #909399;
}
</style>
