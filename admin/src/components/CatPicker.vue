<template>
  <el-dialog
    title="选择商品分类"
    :visible.sync="dialogVisible"
    width="60%"
    :close-on-click-modal="false"
    @close="handleClose"
    @open="handleOpen"
    append-to-body
    class="cat-picker-dialog"
  >
    <div class="cat-picker-container" v-loading="loading">
      <!-- 一级分类列表 -->
      <div class="level-1-list">
        <div
          v-for="catL1 in catList"
          :key="catL1.id"
          class="level-1-item"
          :class="{ active: activeL1Id === catL1.id }"
          @click="selectLevel1(catL1)"
        >
          {{ catL1.name }}
        </div>
      </div>

      <!-- 二级分类列表 -->
      <div class="level-2-list">
        <el-checkbox-group v-if="activeL2List.length > 0" v-model="selectedL2Ids">
          <el-checkbox
            v-for="catL2 in activeL2List"
            :key="catL2.id"
            :label="catL2.id"
            class="level-2-item"
          >
            {{ catL2.name }}
          </el-checkbox>
        </el-checkbox-group>
        <el-empty v-else description="该分类下暂无二级分类" :image-size="80"></el-empty>
      </div>
    </div>

    <span slot="footer" class="dialog-footer">
      <div class="selected-info">
        已选择 {{ selectedL2Ids.length }} 个分类
      </div>
      <div>
        <el-button @click="handleClose" size="small">取 消</el-button>
        <el-button type="primary" @click="confirmSelection" size="small">确 定</el-button>
      </div>
    </span>
  </el-dialog>
</template>

<script>
export default {
  name: 'CatPicker',
  props: {
    visible: {
      type: Boolean,
      required: true,
    },
    // 接收父组件已选择的ID，用于回显
    selectedIds: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      loading: false,
      dialogVisible: false,
      catList: [], // 存储从API获取的完整树状分类列表
      activeL1Id: null, // 当前选中的一级分类ID
      selectedL2Ids: [], // 用户勾选的二级分类ID数组
    };
  },
  computed: {
    // 根据选中的一级分类，动态计算出要显示的二级分类列表
    activeL2List() {
      if (!this.activeL1Id) return [];
      const activeL1 = this.catList.find(cat => cat.id === this.activeL1Id);
      return activeL1 ? activeL1.childs : [];
    },
  },
  watch: {
    visible(newVal) {
      this.dialogVisible = newVal;
    },
  },
  methods: {
    // 弹窗打开时的回调
    async handleOpen() {
      // 只有在分类列表为空时才去请求，避免重复加载
      if (this.catList.length === 0) {
        await this.getCatList();
      }
      // 回显父组件传递过来的已选ID
      this.selectedL2Ids = [...this.selectedIds];
      // 默认选中第一个一级分类
      if (this.catList.length > 0 && !this.activeL1Id) {
        this.selectLevel1(this.catList[0]);
      }
    },
    // 关闭弹窗
    handleClose() {
      this.$emit('update:visible', false);
    },
    // 从后端API获取分类数据
    async getCatList() {
      this.loading = true;
      try {
        const res = await this.$request({ url: '/mall/goods/cat/all-list' });
        if (res.code === 0) {
          this.catList = res.data.list;
        }
      } finally {
        this.loading = false;
      }
    },
    // 点击一级分类
    selectLevel1(cat) {
      this.activeL1Id = cat.id;
    },
    // 点击“确定”按钮
    confirmSelection() {
      // 找到所有被选中的二级分类的完整对象 {id, name}
      const selectedCats = [];
      const flatAllL2Cats = this.catList.flatMap(l1 => l1.childs);
      this.selectedL2Ids.forEach(id => {
        const cat = flatAllL2Cats.find(l2 => l2.id === id);
        if (cat) {
          selectedCats.push({ id: cat.id, name: cat.name });
        }
      });

      // 通过 $emit 将选中的结果返回给父组件
      this.$emit('selected', selectedCats);
      this.handleClose();
    },
  },
};
</script>

<style lang="scss" scoped>
// 为 el-dialog 的内部内容区域设置样式
::v-deep .el-dialog__body {
  padding: 10px 20px;
}

.cat-picker-container {
  display: flex;
  height: 400px; // 固定高度
  border: 1px solid #ebeef5;
  border-radius: 4px;
}

.level-1-list {
  width: 180px;
  height: 100%;
  border-right: 1px solid #ebeef5;
  overflow-y: auto;
  flex-shrink: 0;

  .level-1-item {
    padding: 12px 15px;
    cursor: pointer;
    font-size: 14px;
    border-bottom: 1px solid #f7f7f7;

    &:hover {
      background-color: #f5f7fa;
    }

    &.active {
      background-color: var(--admin-accent-soft);
      color: var(--admin-accent-text);
      font-weight: bold;
    }
  }
}

.level-2-list {
  flex: 1;
  padding: 15px;
  height: 100%;
  overflow-y: auto;

  .level-2-item {
    display: block; // 让每个 checkbox 独占一行
    margin-bottom: 12px;
  }
}

.dialog-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}
.selected-info {
  font-size: 13px;
  color: #909399;
}
</style>
