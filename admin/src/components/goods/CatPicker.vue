<template>
  <div class="cat-picker-wrapper">
    <!-- 入口 -->
    <el-button v-if="!value || !value.id" type="primary" @click="openDialog" :disabled="loading">
      选择分类
    </el-button>
    <el-button type="text" v-else @click="openDialog">{{ value.name || '选择分类' }}</el-button>

    <!-- 分类选择对话框 -->
    <el-dialog
      :visible.sync="dialogVisible"
      title="选择分类"
      width="600px"
      @open="handleOpen"
      @close="handleClose"
      :before-close="handleBeforeClose"
      append-to-body
    >
      <div class="cat-picker" v-loading="loading" element-loading-text="加载中...">
        <!-- 左侧：一级分类 -->
        <div class="cat-level-1">
          <template v-if="catList.length > 0">
            <el-menu
              :default-active="String(activeL1Id)"
              @select="handleSelectLevel1"
              background-color="#f5f7fa"
              text-color="#303133"
              active-text-color="var(--admin-accent-text)"
            >
              <el-menu-item
                v-for="cat in catList"
                :key="cat.id"
                :index="String(cat.id)"
                :class="{ 'is-finally-selected': selectedCat.id === cat.id }"
                @click="selectLevel1(cat)"
              >
                {{ cat.name }}
              </el-menu-item>
            </el-menu>
          </template>
          <template v-else-if="!loading">
            <div class="empty-state">
              <el-empty description="暂无一级分类数据"></el-empty>
            </div>
          </template>
        </div>

        <!-- 右侧：二级分类 -->
        <div class="cat-level-2">
          <template v-if="activeL2List.length > 0">
            <div
              v-for="cat in activeL2List"
              :key="cat.id"
              class="cat-item-row"
              :class="{ active: selectedCat.id === cat.id }"
              @click="selectLevel2(cat)"
            >
              {{ cat.name }}
            </div>
          </template>
          <template v-else-if="activeL1Id && !loading">
            <div class="empty-state">
              <el-empty description="该分类下暂无二级分类"></el-empty>
            </div>
          </template>
          <template v-else-if="!loading">
            <div class="empty-state">
              <el-empty description="请先选择左侧一级分类"></el-empty>
            </div>
          </template>
        </div>
      </div>

      <!-- 底部按钮 -->
      <span slot="footer" class="dialog-footer">
        <el-button @click="handleClose">取消</el-button>
        <el-button
          type="primary"
          @click="confirmSelection"
          :disabled="!selectedCat.id"
        >
          确定
        </el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'CatPicker',
  props: {
    value: {
      type: Object, // v-model 值，结构 {id, name}
      default: () => ({ id: null, name: '' }),
    },
    apiUrl: {
      type: String,
      default: '/mall/goods/cat-tree'
    }
  },
  data() {
    return {
      loading: false,
      dialogVisible: false,
      catList: [],
      activeL1Id: null, // 仅用于高亮左侧菜单的“当前展开项”
      selectedCat: { id: null, name: '' }, // 最终确认的选择，可能是一级或二级
      initialSelectedCat: { id: null, name: '' }, // 用于对比是否有未保存的修改
    }
  },
  computed: {
    // 根据当前展开的一级分类，计算其对应的二级分类列表
    activeL2List() {
      if (!this.activeL1Id) return []
      // 后端 cat-tree 的子分类 key 是 children(不是 childs);id 统一按数字比较,避免接口返回字符串 id 时严格相等失配
      const activeL1 = this.catList.find(cat => Number(cat.id) === Number(this.activeL1Id))
      return (activeL1 && activeL1.children) || []
    },
    // 判断是否有未保存的修改
    hasUnsavedChanges() {
      return this.selectedCat.id !== this.initialSelectedCat.id
    }
  },
  methods: {
    openDialog() {
      this.dialogVisible = true
    },
    async handleOpen() {
      // 首次打开或数据为空时加载数据
      if (this.catList.length === 0) {
        try {
          await this.getCatList()
        } catch (error) {
          this.$message.error('分类数据加载失败，请稍后重试')
          this.dialogVisible = false; // 加载失败直接关闭
          return;
        }
      }
      // 初始化选择状态
      this.findAndSetSelected(this.value);
      this.initialSelectedCat = { ...this.selectedCat };
    },
    handleClose() {
      this.dialogVisible = false
    },
    // 关闭前检查是否有未保存的修改
    handleBeforeClose(done) {
      if (this.hasUnsavedChanges) {
        this.$confirm('您有未保存的选择，确定要关闭吗？')
          .then(() => {
            done()
          })
          .catch(() => {})
      } else {
        done()
      }
    },
    confirmSelection() {
      if (this.selectedCat.id) {
        this.$emit('input', { ...this.selectedCat })
        this.$emit('change', { ...this.selectedCat })
        this.$message.success('分类选择成功')
      }
      this.handleClose()
    },
    async getCatList() {
      this.loading = true
      try {
        const res = await this.$request({ url: this.apiUrl })
        if (res.code === 0) {
          this.catList = res.data?.list || []
        } else {
          this.$message.error(res.msg || '获取分类数据失败')
          this.catList = []
        }
      } catch (error) {
        this.$message.error('网络错误，获取分类数据失败')
        this.catList = []
        throw error
      } finally {
        this.loading = false
      }
    },
    // 根据传入的v-model值，反向查找并设置当前的选择状态
    findAndSetSelected(value) {
      if (!value || !value.id) {
        this.activeL1Id = this.catList.length > 0 ? this.catList[0].id : null;
        this.selectedCat = { id: null, name: '' };
        return;
      }

      let found = false;
      for (const l1 of this.catList) {
        // 检查是否是一级分类
        if (Number(l1.id) === Number(value.id)) {
          this.activeL1Id = l1.id;
          this.selectedCat = { id: l1.id, name: l1.name };
          found = true;
          break;
        }
        // 检查是否是其下的二级分类(后端 key 是 children)
        const l2 = (l1.children || []).find(c => Number(c.id) === Number(value.id));
        if (l2) {
          this.activeL1Id = l1.id;
          this.selectedCat = { id: l2.id, name: l2.name };
          found = true;
          break;
        }
      }
      // 如果没找到，则重置
      if (!found) {
        this.activeL1Id = this.catList.length > 0 ? this.catList[0].id : null;
        this.selectedCat = { id: null, name: '' };
      }
    },
    // el-menu的select事件，仅用于更新左侧展开项
    handleSelectLevel1(l1Id) {
      this.activeL1Id = Number(l1Id);
    },
    // 点击一级分类
    selectLevel1(cat) {
      // 同时将它设为最终选择
      this.selectedCat = { id: cat.id, name: cat.name };
    },
    // 点击二级分类
    selectLevel2(cat) {
      this.selectedCat = { id: cat.id, name: cat.name };
    }
  }
}
</script>

<style scoped>
.cat-picker {
  display: flex;
  min-height: 300px;
  height: 400px;
  overflow: hidden;
  border: 1px solid #DCDFE6;
  border-radius: 4px;
}

.cat-level-1 {
  width: 200px;
  height: 100%;
  overflow-y: auto;
  background-color: #f5f7fa;
}

/* 覆盖el-menu默认的右边框 */
.cat-level-1 .el-menu {
  border-right: 1px solid #e6e6e6;
}

/* [新增] 当一级分类是最终选择时的高亮样式 */
.el-menu-item.is-finally-selected {
  background-color: var(--admin-accent-soft) !important;
  font-weight: bold;
}

.cat-level-2 {
  flex: 1;
  padding: 10px;
  height: 100%;
  overflow-y: auto;
}

/* [修改] 二级分类项目样式，一行一个 */
.cat-item-row {
  padding: 10px 15px;
  border: 1px solid transparent;
  border-radius: 4px;
  cursor: pointer;
  user-select: none;
  transition: all 0.2s;
  white-space: nowrap;
  margin-bottom: 5px; /* 增加行间距 */
}

.cat-item-row:hover {
  background-color: #f5f7fa;
}

.cat-item-row.active {
  background-color: var(--admin-primary);
  color: var(--admin-on-primary);
  font-weight: 500;
}
.cat-item-row.active:hover {
  background-color: var(--admin-primary-hover); color: var(--admin-on-primary);
}


.empty-state {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* 滚动条美化 */
.cat-level-1::-webkit-scrollbar,
.cat-level-2::-webkit-scrollbar {
  width: 6px;
  height: 6px;
}

.cat-level-1::-webkit-scrollbar-thumb,
.cat-level-2::-webkit-scrollbar-thumb {
  border-radius: 3px;
  background-color: #ddd;
}

.cat-level-1::-webkit-scrollbar-track,
.cat-level-2::-webkit-scrollbar-track {
  background: transparent;
}
</style>
