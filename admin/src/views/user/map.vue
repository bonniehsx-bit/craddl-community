<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="map-header">
        <span class="card-title">用户关系图谱</span>
        <span class="card-desc">左侧树点箭头展开下级,支持任意层级;输入 ID/昵称/手机号可定位到具体节点</span>
      </div>

      <!-- 顶部工具栏 -->
      <div class="toolbar">
        <el-input
          v-model="locateInput"
          size="small"
          placeholder="输入用户 ID / 昵称 / 手机号定位"
          clearable
          style="width: 320px;"
          @keyup.enter.native="locate"
          @clear="resetTree"
        >
          <el-button slot="append" icon="el-icon-aim" @click="locate" :loading="locating">定位</el-button>
        </el-input>
        <el-button size="small" icon="el-icon-refresh" @click="resetTree" :loading="loading">刷新根节点</el-button>
        <el-button size="small" icon="el-icon-minus" @click="collapseAll">全部收起</el-button>
        <span v-if="rootTotal > 0" class="root-stat">
          顶级用户:<strong>{{ rootTotal }}</strong>
        </span>
      </div>

      <div class="main-layout">
        <!-- 左侧: 懒加载树 -->
        <div class="tree-container" v-loading="loading">
          <el-tree
            ref="tree"
            :props="treeProps"
            :load="loadNode"
            lazy
            node-key="id"
            :expand-on-click-node="false"
            highlight-current
            :current-node-key="selectedUser ? selectedUser.id : null"
            @node-click="onNodeClick"
          >
            <template slot-scope="{ data }">
              <div class="tree-node-row">
                <el-avatar :size="22" :src="data.avatar_url" class="node-avatar"
                  :class="{ 'node-dimmed': isDeletedNode(data) }"></el-avatar>
                <span class="node-name" :class="{ 'node-dimmed': isDeletedNode(data) }" :title="data.nickname">
                  {{ data.nickname }}
                </span>
                <span class="node-id">#{{ data.id }}</span>
                <!-- 图谱呈现真实结构,注销/拉黑节点照常显示,只做标注 -->
                <el-tag v-if="isDeletedNode(data)" size="mini" type="info" effect="dark" class="node-state">已注销</el-tag>
                <el-tag v-else-if="isBlackNode(data)" size="mini" type="danger" effect="dark" class="node-state">已拉黑</el-tag>
                <el-tag
                  v-if="data.children_count > 0"
                  size="mini"
                  type="info"
                  effect="plain"
                  class="node-badge"
                  :title="`直推 ${data.children_count} · 团队 ${data.team_count}`"
                >
                  直推 {{ data.children_count }} · 团队 {{ data.team_count }}
                </el-tag>
                <el-tag
                  v-else
                  size="mini"
                  type="info"
                  effect="plain"
                  class="node-badge leaf-badge"
                >叶子</el-tag>
              </div>
            </template>
          </el-tree>

          <el-empty v-if="!loading && emptyRoot" description="暂无顶级用户" :image-size="80"></el-empty>
        </div>

        <!-- 右侧: 详情卡片 -->
        <div class="details-panel">
          <el-card v-if="selectedUser" shadow="never" class="details-card">
            <div slot="header" class="details-header">
              <span>用户详情</span>
              <el-button type="text" size="mini" @click="openEdit(selectedUser.id)">
                <i class="el-icon-edit"></i> 编辑
              </el-button>
            </div>
            <div class="avatar-container">
              <el-avatar :size="80" :src="selectedUser.avatar_url"></el-avatar>
              <div class="avatar-name">{{ selectedUser.nickname }}</div>
              <div class="avatar-id">ID: {{ selectedUser.id }}</div>
            </div>
            <el-descriptions :column="1" border size="small">
              <el-descriptions-item label="手机号码">{{ displayMobile(selectedUser) }}</el-descriptions-item>
              <el-descriptions-item label="直推下级">
                <strong>{{ selectedUser.children_count || 0 }}</strong> 人
              </el-descriptions-item>
              <el-descriptions-item label="团队总数">
                <strong>{{ selectedUser.team_count || 0 }}</strong> 人
              </el-descriptions-item>
              <el-descriptions-item label="加入时间">{{ selectedUser.created_time || '—' }}</el-descriptions-item>
              <el-descriptions-item label="是否叶子">
                <el-tag v-if="selectedUser.is_leaf" size="mini" type="info">叶子</el-tag>
                <el-tag v-else size="mini" type="success">有下级</el-tag>
              </el-descriptions-item>
            </el-descriptions>
          </el-card>
          <el-empty v-else description="点击左侧节点查看详情" :image-size="100"></el-empty>
        </div>
      </div>
    </el-card>

    <!-- 复用列表页的编辑抽屉 -->
    <user-edit-drawer :visible.sync="editDrawerVisible" :user-id="currentEditUserId" @success="refreshCurrentNode" />
  </div>
</template>

<script>
import UserEditDrawer from './UserEditDrawer.vue'

export default {
  name: 'user-map',
  components: { UserEditDrawer },
  data() {
    return {
      loading: false,
      locating: false,
      emptyRoot: false,
      rootTotal: 0,
      // 懒加载树的 props 映射
      treeProps: {
        label: 'nickname',
        children: 'children',
        isLeaf: 'is_leaf',
      },
      // 记录每个节点的 resolve 回调, 便于定位时强制重载
      resolveCache: new Map(),
      selectedUser: null,
      locateInput: '',
      // 编辑抽屉
      editDrawerVisible: false,
      currentEditUserId: '',
    };
  },
  methods: {
    /**
     * el-tree 懒加载回调
     * node.level === 0 时为虚拟根, 从 parent_id=0 开始拉
     * 否则拉 parent_id=node.data.id
     */
    loadNode(node, resolve) {
      const parentId = node.level === 0 ? 0 : node.data.id;

      // 缓存 resolve 以便后续定位时重新展开某个节点
      if (node.level > 0) {
        this.resolveCache.set(parentId, resolve);
      }

      if (node.level === 0) this.loading = true;

      this.$request({
        url: '/mall/user/map',
        method: 'get',
        data: { parent_id: parentId, page: 1 },
      }).then(res => {
        if (res.code !== 0) {
          this.$message.error(res.msg || '加载失败');
          resolve([]);
          return;
        }
        const list = res.data.list || [];
        // 根节点时收集统计
        if (node.level === 0) {
          this.rootTotal = res.data.pagination ? res.data.pagination.total_count : list.length;
          this.emptyRoot = list.length === 0;
        }
        resolve(list);
      }).catch(() => {
        this.$message.error('网络错误');
        resolve([]);
      }).finally(() => {
        if (node.level === 0) this.loading = false;
      });
    },

    onNodeClick(data) {
      this.selectedUser = data;
    },

    collapseAll() {
      // 遍历树, 逐层折叠
      if (!this.$refs.tree) return;
      const nodes = this.$refs.tree.store.nodesMap;
      Object.keys(nodes).forEach(k => {
        if (nodes[k].expanded) nodes[k].collapse();
      });
      this.selectedUser = null;
    },

    resetTree() {
      this.selectedUser = null;
      this.rootTotal = 0;
      this.emptyRoot = false;
      this.resolveCache.clear();
      // 强制重建根节点 —— 通过修改 key 的技巧, 或者调 store 的 refresh
      if (this.$refs.tree && this.$refs.tree.store) {
        const rootNode = this.$refs.tree.store.root;
        rootNode.childNodes = [];
        rootNode.loaded = false;
        rootNode.loadData();
      }
    },

    /**
     * 根据用户 ID / 昵称 / 手机号 定位到树节点
     * 1. 先通过 map 搜索接口解析成唯一用户
     * 2. 拿到 user_id 后调 ancestry 接口拿祖先链
     * 3. 从根开始逐层懒加载 + 展开, 最后 setCurrentKey 高亮目标
     */
    async locate() {
      const kw = (this.locateInput || '').trim();
      if (!kw) return this.$message.warning('请输入定位关键字');
      this.locating = true;
      try {
        // 先搜一下, 拿到用户 id
        const res = await this.$request({
          url: '/mall/user/map',
          method: 'get',
          data: { keyword: kw, page: 1 },
        });
        if (res.code !== 0 || !res.data.list || res.data.list.length === 0) {
          this.$message.warning('未找到匹配的用户');
          return;
        }
        if (res.data.list.length > 1) {
          this.$message.info(`匹配到 ${res.data.list.length} 个用户, 已定位到第一个`);
        }
        const targetId = res.data.list[0].id;
        await this.locateById(targetId);
      } catch (e) {
        this.$message.error('定位失败');
      } finally {
        this.locating = false;
      }
    },

    // 图谱显示真实结构:注销/拉黑节点照常渲染,只做标注(与后端 UserMapForm 同口径)
    isDeletedNode(data) {
      return Number(data.is_delete) === 1 || Number(data.status) === 2
    },
    isBlackNode(data) {
      return Number(data.status) === 1
    },
    // 注销后 mobile 被改写成 DEL_<id> 占位号(真号进 backup_mobile),不能当真手机号显示
    displayMobile(u) {
      if (!u || !u.mobile) return '—'
      if (this.isDeletedNode(u) && /^DEL_/.test(u.mobile)) return '已注销（手机号已释放）'
      return u.mobile
    },

    async locateById(userId) {
      // 拿祖先链
      const res = await this.$request({
        url: '/mall/user/ancestry',
        method: 'get',
        data: { user_id: userId },
      });
      if (res.code !== 0) {
        this.$message.error(res.msg || '获取祖先链失败');
        return;
      }
      const chain = res.data.chain || []; // [顶层, ..., 直接上级]

      // 逐层展开 —— el-tree 的 getNode 需要该节点已经被加载过;
      // 所以这里从根开始, 对 chain 上每一层调用 loadData + expand
      this.resetTree();
      await this.$nextTick();

      // 根据 chain 逐层展开
      // 注意: chain 可能是空数组(目标就是顶级用户), 这时跳过展开直接 setCurrentKey
      const tree = this.$refs.tree;
      if (!tree) return;

      // 等根节点加载完成
      await this.waitNodeLoaded(0);

      for (const ancestorId of chain) {
        const node = tree.getNode(ancestorId);
        if (!node) {
          this.$message.warning(`无法展开节点 ${ancestorId}, 可能已被删除`);
          return;
        }
        node.expand();
        await this.waitNodeLoaded(ancestorId);
      }

      // 展开目标节点的直接上级后, 目标节点已经被加载, 高亮它
      tree.setCurrentKey(userId);
      const targetNode = tree.getNode(userId);
      if (targetNode) {
        this.selectedUser = targetNode.data;
        // 滚动到视野
        this.$nextTick(() => {
          const el = this.$el.querySelector(`.el-tree-node[data-key="${userId}"], .el-tree .is-current`);
          if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
      } else {
        this.$message.warning('定位到的节点不在当前分支, 请检查 parent_id 关系');
      }
    },

    /**
     * 等待指定 parent_id 的 loadNode 回调完成
     * (el-tree 懒加载没有 promise API, 这里 poll 节点状态)
     */
    waitNodeLoaded(parentId) {
      return new Promise((resolve) => {
        const tree = this.$refs.tree;
        const check = () => {
          if (parentId === 0) {
            // 根节点: 等 store.root.childNodes 填满
            if (tree && tree.store && tree.store.root && tree.store.root.loaded) {
              return resolve();
            }
          } else {
            const node = tree && tree.getNode(parentId);
            if (node && node.loaded) return resolve();
          }
          setTimeout(check, 50);
        };
        check();
      });
    },

    openEdit(id) {
      this.currentEditUserId = id;
      this.editDrawerVisible = true;
    },

    refreshCurrentNode() {
      // 编辑抽屉保存后, 刷新当前选中节点所在父级的子节点数据
      if (!this.selectedUser) return;
      const tree = this.$refs.tree;
      if (!tree) return;
      const node = tree.getNode(this.selectedUser.id);
      if (!node || !node.parent) return;
      const parent = node.parent;
      parent.loaded = false;
      parent.childNodes = [];
      parent.loadData();
    },
  },
};
</script>

<style scoped>
.map-header {
  display: flex;
  align-items: baseline;
  gap: 16px;
}
.card-title {
  font-weight: bold;
  font-size: 15px;
}
.card-desc {
  font-size: 12px;
  color: #909399;
}

.toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 0 16px;
  border-bottom: 1px dashed #ebeef5;
  margin-bottom: 16px;
}
.root-stat {
  margin-left: auto;
  font-size: 12px;
  color: #606266;
}
.root-stat strong {
  color: #409eff;
  font-weight: 600;
  margin: 0 4px;
}

.main-layout {
  display: flex;
  gap: 20px;
  min-height: 560px;
}

/* 左侧树 */
.tree-container {
  flex: 1;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  padding: 12px 6px;
  max-height: 70vh;
  overflow-y: auto;
}
.tree-node-row {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 2px 0;
}
.node-avatar {
  flex-shrink: 0;
  background: #f0f2f5;
}
.node-name {
  max-width: 140px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 13px;
  color: #303133;
}
.node-id {
  color: #909399;
  font-size: 11px;
}
.node-badge {
  margin-left: 6px;
  font-size: 11px !important;
  transform: scale(0.92);
  transform-origin: left center;
}
/* 注销/拉黑节点:结构照常呈现,视觉上弱化并标注 */
.node-state {
  margin-left: 6px;
  font-size: 11px !important;
  transform: scale(0.88);
  transform-origin: left center;
}
.node-dimmed {
  opacity: 0.45;
}
.node-name.node-dimmed {
  text-decoration: line-through;
  color: #909399;
}
.leaf-badge {
  opacity: 0.6;
}

/* 右侧详情 */
.details-panel {
  width: 360px;
  flex-shrink: 0;
}
.details-card {
  height: 100%;
}
.details-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.avatar-container {
  text-align: center;
  padding: 12px 0 20px;
}
.avatar-name {
  margin-top: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #303133;
}
.avatar-id {
  font-size: 12px;
  color: #909399;
  margin-top: 2px;
}
</style>
