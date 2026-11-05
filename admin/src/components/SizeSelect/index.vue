<template>
  <el-dropdown trigger="click" placement="bottom-end" @command="handleSetSize">
    <!-- 触发按钮：仅显示图标 -->
    <div class="size-trigger">
      <svg-icon icon-class="size" class="size-icon" />
    </div>

    <el-dropdown-menu slot="dropdown" class="size-dropdown-menu">
      <el-dropdown-item
        v-for="item of sizeOptions"
        :key="item.value"
        :command="item.value"
        :class="{ 'is-current': size === item.value }"
      >
        <div class="size-option">
          <div class="size-option-left">
            <i v-if="size === item.value" class="el-icon-check check-icon"></i>
            <i v-else class="placeholder-icon"></i>
            <span class="size-option-name">{{ item.label }}</span>
          </div>
          <span class="size-option-desc">{{ item.desc }}</span>
        </div>
      </el-dropdown-item>
    </el-dropdown-menu>
  </el-dropdown>
</template>

<script>
const SIZE_OPTIONS = [
  { label: '默认', value: 'default', desc: '偏大，阅读舒适' },
  { label: '中等', value: 'medium',  desc: '推荐，均衡布局' },
  { label: '小号', value: 'small',   desc: '紧凑，信息更多' },
  { label: '迷你', value: 'mini',    desc: '极密，专业用户' },
]

export default {
  name: 'SizeSelect',
  data() {
    return { sizeOptions: SIZE_OPTIONS }
  },
  computed: {
    size() {
      return this.$store.getters.size || 'medium'
    },
  },
  methods: {
    handleSetSize(size) {
      if (size === this.size) return
      this.$ELEMENT.size = size
      this.$store.dispatch('app/setSize', size)
      this.refreshView()
      this.$message({ message: '布局尺寸已切换', type: 'success', duration: 1500 })
    },
    refreshView() {
      this.$store.dispatch('tagsView/delAllCachedViews', this.$route)
      const { fullPath } = this.$route
      this.$nextTick(() => {
        this.$router.replace({ path: '/redirect' + fullPath })
      })
    }
  }
}
</script>

<style lang="scss" scoped>
/* 触发按钮 */
.size-trigger {
  display: flex;
  align-items: center;
  cursor: pointer;
  padding: 0 4px;
  border-radius: 4px;
  transition: background 0.2s;
  height: 100%;

  &:hover {
    background: rgba(0, 0, 0, 0.05);
  }

  .size-icon {
    font-size: 18px;
    vertical-align: middle;
  }

}

/* 选项行 */
.size-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-width: 160px;

  .size-option-left {
    display: flex;
    align-items: center;

    .check-icon {
      color: var(--admin-accent-text);
      font-size: 13px;
      width: 16px;
    }

    .placeholder-icon {
      display: inline-block;
      width: 16px;
    }

    .size-option-name {
      font-size: 13px;
      color: #303133;
      font-weight: 500;
    }
  }

  .size-option-desc {
    font-size: 11px;
    color: #909399;
    margin-left: 12px;
  }
}

/* 当前选中项高亮 */
.size-dropdown-menu {
  .el-dropdown-menu__item.is-current {
    color: var(--admin-accent-text);
    background-color: var(--admin-accent-soft);

    .size-option-name {
      color: var(--admin-accent-text);
    }
  }

  .el-dropdown-menu__item:not(.is-current):hover {
    background-color: #f5f7fa;
  }
}
</style>
