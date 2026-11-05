<template>
  <div class="app-container">
    <el-card class="box-card" v-loading="loading">
      <!-- 标题与按钮同一行垂直居中:原来按钮 float 右浮动,比标题高、顶对齐后下沿压出标题栏 -->
      <div slot="header" class="card-header-bar">
        <span>设置页菜单</span>
        <div class="header-actions">
          <el-button size="small" @click="load">撤销修改</el-button>
          <el-button size="small" @click="resetDefault">恢复默认菜单</el-button>
          <el-button type="primary" size="small" :loading="saving" @click="save">保存配置</el-button>
        </div>
      </div>

      <el-alert type="info" :closable="false" show-icon style="margin-bottom: 16px;">
        <template slot="title">对应手机端「我的 → 设置」页的菜单</template>
        <div class="alert-body">
          · <b>内置项</b>（灰色标记）只能改名、换图标、调顺序、开关显隐，<b>不能删</b>——
          它们的点击行为写在前端（比如「支付密码」要打开本页的弹窗），删了就没法调用了。<br>
          · 想加自己的入口，用每组下方的「添加自定义菜单」，只能配跳转链接。<br>
          · 带 <i class="el-icon-lock" style="color:#E6A23C;"></i> 的项是<b>上架合规必需项</b>，
          显隐开关已锁死。隐藏它们会导致 App Store / 应用市场审核被拒，而且当时不会有任何报错。
        </div>
      </el-alert>

      <draggable v-model="form.groups" handle=".group-drag" animation="150">
        <div v-for="(group, gi) in form.groups" :key="group.key || gi" class="group-box">
          <div class="group-head">
            <i class="el-icon-rank group-drag" />
            <el-input v-model="group.name" size="small" class="group-name" placeholder="分组名称" maxlength="20" />
            <div class="group-right">
              <span class="group-meta">{{ group.list.length }} 项</span>
              <el-switch v-model="group.is_show" :active-value="1" :inactive-value="0" />
              <span class="group-meta">显示</span>
              <el-button v-if="canDeleteGroup(group)" type="text" class="del-text"
                         icon="el-icon-delete" @click="delGroup(gi)">删除分组</el-button>
            </div>
          </div>

          <draggable v-model="group.list" handle=".item-drag" animation="150" group="menu-items">
            <div v-for="(item, ii) in group.list" :key="item.key || ('c' + ii)" class="item-row">
              <i class="el-icon-rank item-drag" />

              <div class="item-icon">
                <img v-if="item.pic" :src="item.pic" class="pic-thumb">
                <i v-else-if="item.icon" class="tui-icon-preview" :class="'tui-icon-' + item.icon"></i>
                <i v-else class="el-icon-picture-outline" style="color:#c0c4cc;"></i>
              </div>

              <el-input v-model="item.name" size="small" class="item-name" placeholder="菜单名称" maxlength="20" />

              <el-tag v-if="item.type === 'builtin'" size="mini" type="info" class="item-tag">内置</el-tag>
              <el-tag v-else size="mini" type="success" class="item-tag">自定义</el-tag>
              <i v-if="item.locked" class="el-icon-lock lock-icon" title="合规必需项，显隐已锁定"></i>

              <div class="item-actions">
                <el-button type="text" size="mini" @click="toggleEdit(gi, ii)">
                  <i :class="isEditing(gi, ii) ? 'el-icon-arrow-up' : 'el-icon-edit'" />
                  {{ isEditing(gi, ii) ? '收起' : '编辑' }}
                </el-button>
                <el-tooltip :disabled="!item.locked" placement="top"
                            content="上架合规必需项，隐藏会导致审核被拒，因此锁定">
                  <span>
                    <el-switch v-model="item.is_show" :active-value="1" :inactive-value="0"
                               :disabled="!!item.locked" />
                  </span>
                </el-tooltip>
                <el-button v-if="item.type !== 'builtin'" type="text" size="mini" class="del-text"
                           icon="el-icon-delete" @click="delItem(gi, ii)" />
                <el-tooltip v-else placement="top" content="内置项不能删除，点击行为写在前端，删了就调不到了">
                  <i class="el-icon-delete disabled-del" />
                </el-tooltip>
              </div>

              <!-- 展开编辑 -->
              <div v-if="isEditing(gi, ii)" class="edit-panel">
                <div class="edit-row">
                  <span class="edit-label">兼容图标</span>
                  <tui-icon-picker v-model="item.icon" />
                  <span class="edit-hint">未设置下方图片或 Tabler 图标时使用，跟随商城主题色</span>
                </div>
                <div class="edit-row">
                  <span class="edit-label">菜单图标</span>
                  <single-image-selector v-model="item.pic" width="40px" height="40px" :allow-icons="true" :icon-scale="75" />
                  <span class="edit-hint">支持图片或 Tabler 图标，优先展示；Tabler 可在图标库中设置颜色和占比，清除后使用兼容图标</span>
                </div>
                <div class="edit-row">
                  <span class="edit-label">右侧文字</span>
                  <el-input v-model="item.value" size="small" style="width:220px;" maxlength="20"
                            placeholder="选填，如「管理」" />
                  <span class="edit-hint" v-if="dynamicValueKeys.indexOf(item.key) !== -1">
                    ⚠ 这一项右侧显示的是<b>实时状态</b>（{{ dynamicValueDesc[item.key] }}），这里填了也会被覆盖
                  </span>
                </div>
                <div class="edit-row" v-if="item.type !== 'builtin'">
                  <span class="edit-label">跳转链接</span>
                  <div style="flex:1; max-width: 520px;">
                    <link-picker v-model="item.url" :text="item.url || '选择跳转链接'" />
                  </div>
                </div>
                <div class="edit-row" v-else>
                  <span class="edit-label">点击行为</span>
                  <span class="builtin-behavior">{{ behaviorDesc[item.key] || '内置行为' }}（内置项，不可修改）</span>
                </div>
                <div class="edit-row" v-if="item.key === 'block_list'">
                  <span class="edit-label"></span>
                  <el-alert type="warning" :closable="false" show-icon style="flex:1;">
                    <template slot="title">这一项跳的是「按摩」插件的屏蔽列表页</template>
                    <div style="font-size:12px;line-height:1.7;">
                      <b>没装该插件的商城请关掉它</b>——用户点进去是空白页。<br>
                      <b>但只要你的商城有「屏蔽用户」功能，就必须开着</b>：Apple Guideline 1.2 要求
                      屏蔽之后必须能看到并取消，少了这个入口会被拒审。
                    </div>
                  </el-alert>
                </div>
              </div>
            </div>
          </draggable>

          <div class="add-row" @click="addItem(gi)">
            <i class="el-icon-plus" /> 添加自定义菜单
          </div>
        </div>
      </draggable>

      <div class="add-group-row" @click="addGroup">
        <i class="el-icon-plus" /> 添加分组
      </div>
    </el-card>
  </div>
</template>

<script>
import draggable from 'vuedraggable'

export default {
  name: 'mall-setting-page',
  components: { draggable },
  data() {
    return {
      loading: false,
      saving: false,
      activeEdit: null,
      form: { groups: [] },
      // 这几项右侧是运行时算出来的状态，不是文案，后台填了也会被前端覆盖
      dynamicValueKeys: ['mobile', 'wx_notify', 'upgrade'],
      dynamicValueDesc: {
        mobile: '已绑手机号显示掩码，未绑显示「去绑定」',
        wx_notify: '显示「已绑定」或「去绑定」',
        upgrade: '显示当前 App 版本号'
      },
      behaviorDesc: {
        mobile: '打开「更换手机号」弹窗',
        login_pwd: '打开「登录密码」弹窗',
        pay_pwd: '打开「支付密码」弹窗',
        address: '跳转地址管理',
        wx_notify: '发起微信通知绑定（受商城「微信通知」开关控制，关了这一项自动不显示）',
        about: '跳转关于我们',
        feedback: '跳转意见反馈',
        privacy: '打开隐私政策',
        protocol: '打开用户协议',
        upgrade: '跳转检查更新',
        clear_cache: '清理本地缓存并退出登录',
        block_list: '跳转屏蔽列表',
        account_deletion: '跳转注销账号'
      }
    }
  },
  created() {
    this.load()
  },
  methods: {
    load() {
      this.loading = true
      this.$request({ url: '/mall/mall/setting-page', method: 'get' })
        .then(res => {
          this.loading = false
          if (res.code === 0) {
            this.form = res.data.page_data || { groups: [] }
            this.activeEdit = null
          }
        }).catch(() => { this.loading = false })
    },
    isEditing(gi, ii) {
      return this.activeEdit === gi + '_' + ii
    },
    toggleEdit(gi, ii) {
      const k = gi + '_' + ii
      this.activeEdit = this.activeEdit === k ? null : k
    },
    // 只有「一个内置项都没有」的分组才允许删——内置项没地方放会被后端补回来，
    // 界面上却看不到，运营会以为删失败了
    canDeleteGroup(group) {
      return !group.list.some(i => i.type === 'builtin')
    },
    addItem(gi) {
      this.form.groups[gi].list.push({
        type: 'link', key: '', name: '', icon: '', pic: '', value: '', url: '', is_show: 1, locked: 0
      })
      this.activeEdit = gi + '_' + (this.form.groups[gi].list.length - 1)
    },
    delItem(gi, ii) {
      this.form.groups[gi].list.splice(ii, 1)
      this.activeEdit = null
    },
    addGroup() {
      this.form.groups.push({ key: 'custom_' + Date.now(), name: '新分组', is_show: 1, list: [] })
    },
    delGroup(gi) {
      this.form.groups.splice(gi, 1)
      this.activeEdit = null
    },
    resetDefault() {
      this.$confirm(
        '会清空当前配置，把菜单恢复成系统默认的样子（自定义菜单项会全部丢失）。确定吗？',
        '恢复默认菜单', { type: 'warning' }
      ).then(() => {
        this.$request({ url: '/mall/mall/setting-page-reset', method: 'post' })
          .then(res => {
            if (res.code === 0) {
              this.form = res.data.page_data
              this.activeEdit = null
              this.$message.success(res.msg || '已恢复默认菜单')
            } else {
              this.$message.error(res.msg)
            }
          })
      }).catch(() => {})
    },
    save() {
      // 自定义项没配链接的话，前端根本渲染不出来（点了没反应），保存前先提醒
      const bad = []
      this.form.groups.forEach(g => g.list.forEach(i => {
        if (i.type !== 'builtin' && !i.url) bad.push(i.name || '未命名')
      }))
      if (bad.length) {
        this.$message.warning(`自定义菜单「${bad.join('、')}」没有配置跳转链接，保存后不会显示`)
        return
      }
      this.saving = true
      this.$request({
        url: '/mall/mall/setting-page',
        method: 'post',
        data: { page_data: JSON.stringify(this.form) }
      }).then(res => {
        this.saving = false
        if (res.code === 0) {
          this.$message.success('保存成功')
          this.load()   // 回读一次：后端会补回内置项、强制合规项显示，界面要如实反映落库结果
        } else {
          this.$message.error(res.msg)
        }
      }).catch(() => { this.saving = false })
    }
  }
}
</script>

<style scoped>
.alert-body { font-size: 12px; line-height: 1.9; margin-top: 4px; }

.group-box {
  border: 1px solid #ebeef5;
  border-radius: 6px;
  margin-bottom: 14px;
  background: #fff;
}
.group-head {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  background: #f7f8fa;
  border-bottom: 1px solid #ebeef5;
  border-radius: 6px 6px 0 0;
}
.group-drag, .item-drag { cursor: move; color: #c0c4cc; }
.group-name { width: 200px; }
.group-right { margin-left: auto; display: flex; align-items: center; gap: 8px; }
.group-meta { font-size: 12px; color: #909399; }

.item-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  border-bottom: 1px solid #f5f7fa;
  flex-wrap: wrap;
}
.item-row:last-child { border-bottom: none; }
.item-icon {
  width: 32px; height: 32px;
  display: flex; align-items: center; justify-content: center;
  border: 1px solid #ebeef5; border-radius: 4px; flex-shrink: 0;
}
.item-icon .tui-icon-preview { font-size: 18px; color: #606266; }
.pic-thumb { width: 24px; height: 24px; object-fit: contain; }
.item-name { width: 200px; }
.item-tag { flex-shrink: 0; }
.lock-icon { color: #E6A23C; }
.item-actions { margin-left: auto; display: flex; align-items: center; gap: 10px; }
.app-container .el-button.del-text:not(.is-disabled) { color: #f56c6c; }
.disabled-del { color: #dcdfe6; cursor: not-allowed; font-size: 14px; }

.edit-panel {
  flex-basis: 100%;
  margin-top: 10px;
  padding: 14px;
  background: #fafbfc;
  border-radius: 6px;
}
.edit-row { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.edit-row:last-child { margin-bottom: 0; }
.edit-label { width: 68px; flex-shrink: 0; font-size: 13px; color: #606266; text-align: right; }
.edit-hint { font-size: 12px; color: #909399; }
.builtin-behavior { font-size: 13px; color: #606266; }

.add-row, .add-group-row {
  text-align: center;
  padding: 10px;
  color: var(--admin-accent-text);
  cursor: pointer;
  font-size: 13px;
  border-top: 1px dashed #ebeef5;
}
.add-group-row {
  border: 1px dashed #dcdfe6;
  border-radius: 6px;
  margin-top: 6px;
}
.add-row:hover, .add-group-row:hover { background: var(--admin-accent-soft); }
.card-header-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.header-actions {
  display: flex;
  gap: 10px;
}
.header-actions .el-button + .el-button {
  margin-left: 0;
}
</style>
