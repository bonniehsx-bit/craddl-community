<template>
  <!-- 手机端 tui-icon 选择器(全局注册为 <tui-icon-picker v-model>):点开从图标库里选,不用记英文名。
       图标名写错手机上只会是空白、不报错,所以后台一律用它选,别再给 el-input 手填 -->
  <div class="tui-icon-picker">
    <!-- 当前值 -->
    <div class="picker-trigger" @click="open">
      <i v-if="value" class="tui-icon-preview" :class="'tui-icon-' + value"></i>
      <i v-else class="el-icon-picture-outline placeholder"></i>
      <span class="trigger-text">{{ value || '选择图标' }}</span>
      <i v-if="value" class="el-icon-close clear-btn" @click.stop="$emit('input', '')"></i>
    </div>

    <el-dialog title="选择图标" :visible.sync="visible" width="720px" append-to-body
               :close-on-click-modal="true" custom-class="tui-icon-dialog">
      <el-input v-model="keyword" size="small" placeholder="按名称搜索，如 wechat / kefu / setup"
                prefix-icon="el-icon-search" clearable style="margin-bottom: 12px;" />
      <div class="icon-grid">
        <div v-for="name in filtered" :key="name" class="icon-cell"
             :class="{ 'is-active': name === value }" @click="pick(name)">
          <i class="tui-icon-preview" :class="'tui-icon-' + name"></i>
          <span class="icon-name">{{ name }}</span>
        </div>
        <div v-if="!filtered.length" class="empty">没有匹配「{{ keyword }}」的图标</div>
      </div>
      <div class="picker-tip">
        共 {{ icons.length }} 个。这里预览用的字体和手机端 <code>tui-icon</code> 是<b>同一份</b>，
        所见即所得；颜色按各页面自己的配色设置显示。
      </div>
    </el-dialog>
  </div>
</template>

<script>
import icons from './icons'
import './tui-icons.css'

export default {
  name: 'TuiIconPicker',
  props: {
    value: { type: String, default: '' }
  },
  data() {
    return {
      visible: false,
      keyword: '',
      icons
    }
  },
  computed: {
    filtered() {
      const k = this.keyword.trim().toLowerCase()
      if (!k) return this.icons
      return this.icons.filter(n => n.indexOf(k) !== -1)
    }
  },
  methods: {
    open() {
      this.keyword = ''
      this.visible = true
    },
    pick(name) {
      this.$emit('input', name)
      this.visible = false
    }
  }
}
</script>

<style scoped>
.picker-trigger {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 32px;
  padding: 0 10px;
  border: 1px solid #dcdfe6;
  border-radius: 4px;
  cursor: pointer;
  background: #fff;
  transition: border-color .2s;
}
.picker-trigger:hover { border-color: #c0c4cc; }
.picker-trigger .tui-icon-preview { font-size: 18px; color: #606266; }
.picker-trigger .placeholder { font-size: 18px; color: #c0c4cc; }
.trigger-text { font-size: 13px; color: #606266; max-width: 96px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.clear-btn { font-size: 13px; color: #c0c4cc; }
.clear-btn:hover { color: #f56c6c; }

.icon-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(88px, 1fr));
  gap: 6px;
  max-height: 46vh;
  overflow-y: auto;
  padding-right: 4px;
}
.icon-cell {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 12px 4px;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  cursor: pointer;
  transition: all .15s;
}
.icon-cell:hover { border-color: var(--admin-accent-text); background: var(--admin-accent-soft); }
.icon-cell.is-active { border-color: var(--admin-accent-text); background: var(--admin-accent-soft); box-shadow: 0 0 0 1px var(--admin-accent-text) inset; }
.icon-cell .tui-icon-preview { font-size: 24px; color: #303133; }
.icon-name {
  font-size: 11px;
  color: #909399;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.empty { grid-column: 1 / -1; text-align: center; color: #909399; padding: 40px 0; font-size: 13px; }
.picker-tip { margin-top: 12px; font-size: 12px; color: #909399; line-height: 1.7; }
</style>
