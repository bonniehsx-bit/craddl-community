<template>
  <div class="app-container">
    <el-card class="box-card" v-loading="loading">
      <div slot="header" class="clearfix">
        <span>移动端商户工作台自定义配置</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="save" :loading="submitLoading">
          保存全部配置
        </el-button>
        <el-button style="float: right; padding: 3px 0; margin-right: 15px; color: #F56C6C;" type="text" @click="resetCurrent">
          重置当前端
        </el-button>
      </div>

      <el-alert
        title="功能说明"
        type="info"
        description="可自定义手机端商户大厅的【顶部固定卡片】(销售看板/订单/商品管理) 和【常用功能】入口。每项都可以按主体类型(线上/门店/个人租户)勾选适用范围，例如个人租户不需要看到商品管理 — 取消勾选「商品管理」适用主体里的「个人」即可。"
        show-icon
        style="margin-bottom: 20px;"
      ></el-alert>

      <el-tabs v-model="activeTab">

        <!-- ======================= 固定卡片配置 ======================= -->
        <el-tab-pane label="顶部固定卡片" name="cards">
          <el-alert
            title="商户中心台顶部固定卡片可见性配置"
            description="每张卡片可独立选择对哪些主体类型可见。例如个人租户无需「商品管理」卡片，取消勾选「个人租户」即可。空选 = 全部主体可见。"
            type="info" :closable="false" show-icon style="margin-bottom: 20px;"></el-alert>

          <div class="cards-grid">
            <div class="card-config-item" v-for="row in cardsRows" :key="row.key">
              <!-- 左侧：卡片预览 -->
              <div class="card-preview" :class="'preview-' + row.key">
                <div class="preview-icon">
                  <i :class="cardIcon(row.key)"></i>
                </div>
                <div class="preview-label">{{ row.label }}</div>
                <div class="preview-key">{{ row.key }}</div>
              </div>

              <!-- 右侧：主体类型勾选 -->
              <div class="card-types">
                <div class="types-title">适用主体类型</div>
                <el-checkbox-group v-model="row.types" class="types-group">
                  <el-checkbox-button :label="1">
                    <i class="el-icon-mobile-phone"></i> 线上商家
                  </el-checkbox-button>
                  <el-checkbox-button :label="2">
                    <i class="el-icon-shop"></i> 线下门店
                  </el-checkbox-button>
                  <el-checkbox-button :label="4">
                    <i class="el-icon-user"></i> 个人租户
                  </el-checkbox-button>
                </el-checkbox-group>
                <div class="types-hint">
                  <i class="el-icon-info"></i>
                  <span v-if="row.types.length === 0">空选：所有主体类型都将看到该卡片</span>
                  <span v-else>仅勾选的主体登录后会看到该卡片</span>
                </div>
              </div>
            </div>
          </div>
        </el-tab-pane>

        <!-- ======================= 店长端配置 ======================= -->
        <el-tab-pane label="店长端应用池 (更高权限)" name="manager">
          <div style="margin-bottom: 15px;">
            <el-button type="primary" size="small" icon="el-icon-plus" @click="openDialog('manager')">添加店长应用</el-button>
          </div>
          <el-table :data="form.manager_actions" border style="width: 100%">
            <el-table-column label="应用名称" prop="name" width="160"></el-table-column>
            <el-table-column label="图标/图片" width="80" align="center">
              <template slot-scope="scope">
                <el-image v-if="scope.row.pic_url" :src="scope.row.pic_url" style="width: 40px; height: 40px; border-radius: 8px;"></el-image>
                <div v-else :style="{ background: scope.row.bgColor || '#f0f2f5', color: scope.row.color || '#333', width: '40px', height: '40px', borderRadius: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto' }">
                  <i class="el-icon-menu"></i>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="适用主体" width="200">
              <template slot-scope="scope">
                <el-tag v-for="t in getTypesDisplay(scope.row)" :key="t.value" size="small" :type="t.tag" effect="plain" class="type-tag">
                  <i :class="t.icon"></i> {{ t.label }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="跳转路径" prop="url" min-width="160"></el-table-column>
            <el-table-column label="操作" width="220" align="center">
              <template slot-scope="scope">
                <el-button type="text" size="small" @click="editAction('manager', scope.$index)">编辑</el-button>
                <el-button type="text" size="small" :disabled="scope.$index === 0" @click="moveAction('manager', scope.$index, -1)">上移</el-button>
                <el-button type="text" size="small" :disabled="scope.$index === form.manager_actions.length - 1" @click="moveAction('manager', scope.$index, 1)">下移</el-button>
                <el-button type="text" size="small" style="color: #F56C6C;" @click="removeAction('manager', scope.$index)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- ======================= 员工端配置 ======================= -->
        <el-tab-pane label="员工端应用池 (基础权限)" name="employee">
          <div style="margin-bottom: 15px;">
            <el-button type="primary" size="small" icon="el-icon-plus" @click="openDialog('employee')">添加员工应用</el-button>
          </div>
          <el-table :data="form.employee_actions" border style="width: 100%">
            <el-table-column label="应用名称" prop="name" width="160"></el-table-column>
            <el-table-column label="图标/图片" width="80" align="center">
              <template slot-scope="scope">
                <el-image v-if="scope.row.pic_url" :src="scope.row.pic_url" style="width: 40px; height: 40px; border-radius: 8px;"></el-image>
                <div v-else :style="{ background: scope.row.bgColor || '#f0f2f5', color: scope.row.color || '#333', width: '40px', height: '40px', borderRadius: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center', margin: '0 auto' }">
                  <i class="el-icon-menu"></i>
                </div>
              </template>
            </el-table-column>
            <el-table-column label="适用主体" width="200">
              <template slot-scope="scope">
                <el-tag v-for="t in getTypesDisplay(scope.row)" :key="t.value" size="small" :type="t.tag" effect="plain" class="type-tag">
                  <i :class="t.icon"></i> {{ t.label }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="跳转路径" prop="url" min-width="160"></el-table-column>
            <el-table-column label="操作" width="220" align="center">
              <template slot-scope="scope">
                <el-button type="text" size="small" @click="editAction('employee', scope.$index)">编辑</el-button>
                <el-button type="text" size="small" :disabled="scope.$index === 0" @click="moveAction('employee', scope.$index, -1)">上移</el-button>
                <el-button type="text" size="small" :disabled="scope.$index === form.employee_actions.length - 1" @click="moveAction('employee', scope.$index, 1)">下移</el-button>
                <el-button type="text" size="small" style="color: #F56C6C;" @click="removeAction('employee', scope.$index)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

      </el-tabs>
    </el-card>

    <!-- 应用编辑弹窗 -->
    <el-dialog :title="dialogIndex === -1 ? '添加应用' : '编辑应用'" :visible.sync="dialogVisible" width="600px">
      <el-form ref="actionForm" :model="currentAction" label-width="140px" size="small" @submit.native.prevent>
        <el-form-item label="应用名称" required>
          <el-input v-model="currentAction.name" placeholder="如：财务对账"></el-input>
        </el-form-item>
        <el-form-item label="自定义图片图标" required>
          <single-image-selector v-model="currentAction.pic_url"></single-image-selector>
          <div class="el-form-item__description">请选择自定义图片。建议图片源尺寸 100x100 像素，透明底。</div>
        </el-form-item>
        <el-form-item label="跳转路径" required>
          <link-picker v-model="currentAction.url"></link-picker>
          <div class="el-form-item__description">请选择移动端端页面的路由。</div>
        </el-form-item>
        <el-form-item label="适用主体类型" required>
          <el-checkbox-group v-model="currentAction.types">
            <el-checkbox :label="1">线上商家</el-checkbox>
            <el-checkbox :label="2">线下门店</el-checkbox>
            <el-checkbox :label="4">个人租户</el-checkbox>
          </el-checkbox-group>
          <div class="el-form-item__description">未勾选任何选项 = 所有主体可见。勾选后仅勾选的主体类型登录后才能在工作台看到该入口。</div>
        </el-form-item>
        <el-row>
          <el-col :span="12">
            <el-form-item label="图标主色">
              <el-color-picker v-model="currentAction.color" show-alpha></el-color-picker>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="图标底纹">
              <el-color-picker v-model="currentAction.bgColor" show-alpha></el-color-picker>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button @click="dialogVisible = false" size="small">取消</el-button>
        <el-button type="primary" @click="confirmAction" size="small">确 定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'workspace',
  data() {
    return {
      loading: false,
      submitLoading: false,
      activeTab: 'cards',
      form: {
        employee_actions: [],
        manager_actions: [],
        cards_config: {}
      },
      // 固定卡片可视化配置（cards_config 转 table 行）
      // 前端兜底默认值：避免接口没返回 cards_config 时 tab 空白
      cardsRows: [
        { key: 'stats', label: '销售/营业额统计', types: [1, 2] },
        { key: 'order', label: '订单管理',       types: [1, 2] },
        { key: 'goods', label: '商品管理',       types: [1, 2] }
      ],
      dialogVisible: false,
      dialogType: 'manager', // manager | employee
      dialogIndex: -1, // -1 means add
      currentAction: {
        name: '',
        icon: '',
        pic_url: '',
        color: '#2979FF',
        bgColor: '#ECF3FF',
        url: '',
        types: []  // 适用主体类型多选 [1=线上, 2=门店, 4=个人]，空=全主体可见
      }
    }
  },
  created() {
    this.loadData();
  },
  methods: {
    async loadData() {
      this.loading = true;
      try {
        const res = await this.$request({
          url: '/plugin/mch/mall/setting/workspace',
          method: 'get'
        });
        if (res.code === 0) {
          const { data } = res;
          this.$set(this.form, 'employee_actions', data.employee_actions || []);
          this.$set(this.form, 'manager_actions', data.manager_actions || []);
          this.$set(this.form, 'cards_config', data.cards_config || {});

          // 把 cards_config 对象拍平为表格行（保证渲染顺序）
          // 兜底：接口未返回 cards_config 时使用前端硬编码默认（已在 data() 中初始化）
          const defaults = {
            stats: { key: 'stats', label: '销售/营业额统计', types: [1, 2] },
            order: { key: 'order', label: '订单管理',       types: [1, 2] },
            goods: { key: 'goods', label: '商品管理',       types: [1, 2] }
          };
          const order = ['stats', 'order', 'goods'];
          this.cardsRows = order.map(k => {
            const remote = data.cards_config && data.cards_config[k];
            if (remote) {
              return {
                key: k,
                label: remote.label || defaults[k].label,
                types: Array.isArray(remote.types) ? [...remote.types] : []
              };
            }
            return { ...defaults[k] };
          });
        } else {
          this.$message.error(res.msg);
        }
      } catch (e) {
        console.error(e);
      } finally {
        this.loading = false;
      }
    },
    async save() {
      // 把 table 编辑过的 cardsRows 同步回 form.cards_config
      const merged = {};
      for (const row of this.cardsRows) {
        merged[row.key] = {
          key: row.key,
          label: row.label,
          types: Array.isArray(row.types) ? row.types : []
        };
      }
      this.$set(this.form, 'cards_config', merged);

      this.submitLoading = true;
      try {
        const res = await this.$request({
          url: '/plugin/mch/mall/setting/workspace',
          method: 'post',
          data: this.form
        });
        if (res.code === 0) {
          this.$message.success('配置已保存至云端并在全网动态生效');
        } else {
          this.$message.error(res.msg);
        }
      } catch (e) {
        console.error(e);
      } finally {
        this.submitLoading = false;
      }
    },
    openDialog(type) {
      this.dialogType = type;
      this.dialogIndex = -1;
      this.currentAction = {
        name: '',
        icon: 'star',
        pic_url: '',
        color: '#2979FF',
        bgColor: '#ECF3FF',
        url: '',
        types: []  // 默认空数组 = 全主体可见
      };
      this.dialogVisible = true;
      this.$nextTick(() => {
        if (this.$refs.actionForm) this.$refs.actionForm.clearValidate();
      });
    },
    editAction(type, index) {
      this.dialogType = type;
      this.dialogIndex = index;
      const targetList = type === 'manager' ? this.form.manager_actions : this.form.employee_actions;
      const action = JSON.parse(JSON.stringify(targetList[index]));
      // 老格式回填：storeOnly/supplierOnly → types，方便保存后统一新格式
      if (!action.types) {
        if (action.storeOnly) action.types = [2];
        else if (action.supplierOnly) action.types = [1];
        else action.types = [];
      }
      this.currentAction = action;
      this.dialogVisible = true;
      this.$nextTick(() => {
        if (this.$refs.actionForm) this.$refs.actionForm.clearValidate();
      });
    },

    // 卡片预览图标
    cardIcon(key) {
      const map = {
        stats: 'el-icon-data-line',
        order: 'el-icon-tickets',
        goods: 'el-icon-goods'
      };
      return map[key] || 'el-icon-menu';
    },

    // 表格行渲染：把 types 数组 + 老 storeOnly/supplierOnly 统一展示为 tag 列表
    getTypesDisplay(row) {
      const map = {
        1: { value: 1, label: '线上', tag: '', icon: 'el-icon-mobile-phone' },
        2: { value: 2, label: '门店', tag: 'success', icon: 'el-icon-shop' },
        4: { value: 4, label: '个人', tag: 'warning', icon: 'el-icon-user' }
      };
      let types = [];
      if (Array.isArray(row.types) && row.types.length > 0) {
        types = row.types;
      } else {
        // 老格式回退
        if (row.storeOnly) types = [2];
        else if (row.supplierOnly) types = [1];
      }
      if (types.length === 0) {
        return [{ value: 0, label: '全部', tag: 'info', icon: 'el-icon-circle-check' }];
      }
      return types.map(t => map[t]).filter(Boolean);
    },
    confirmAction() {
      if (!this.currentAction.name || !this.currentAction.url) {
        return this.$message.warning('应用名称和跳转路径不能为空');
      }

      // link-picker 组件可能返回数组对象配置，将它扁平化为纯字符串，兼容移动端现有的 go() 路由解析器
      let processedUrl = this.currentAction.url;
      if (Array.isArray(processedUrl) && processedUrl.length > 0) {
        processedUrl = processedUrl[0].new_link_url;
      }
      this.currentAction.url = processedUrl;

      const targetList = this.dialogType === 'manager' ? this.form.manager_actions : this.form.employee_actions;

      const actionData = JSON.parse(JSON.stringify(this.currentAction));
      // 用 types 多选新格式后，清除老的 storeOnly/supplierOnly 避免冲突
      if (Array.isArray(actionData.types) && actionData.types.length > 0) {
        delete actionData.storeOnly;
        delete actionData.supplierOnly;
      }
      if (this.dialogIndex === -1) {
        targetList.push(actionData);
      } else {
        this.$set(targetList, this.dialogIndex, actionData);
      }
      this.dialogVisible = false;
    },
    removeAction(type, index) {
      this.$confirm('确定要移除该应用入口吗？', '系统警告', {
        type: 'warning'
      }).then(() => {
        const targetList = type === 'manager' ? this.form.manager_actions : this.form.employee_actions;
        targetList.splice(index, 1);
      }).catch(() => {});
    },
    resetCurrent() {
      const label = this.activeTab === 'manager' ? '店长端' : '员工端';
      this.$confirm(`确定要将【${label}】恢复为系统默认菜单吗？此操作会立即生效。`, '恢复默认', { type: 'warning' }).then(async () => {
        this.submitLoading = true;
        try {
          // 两端都置为 null，让后端 fallback 到默认菜单
          const resetData = {
            manager_actions: this.activeTab === 'manager' ? null : this.form.manager_actions,
            employee_actions: this.activeTab === 'employee' ? null : this.form.employee_actions
          };
          // 如果两端都是默认/null 状态，整体清空
          if (resetData.manager_actions === null && resetData.employee_actions === null) {
            resetData.manager_actions = null;
            resetData.employee_actions = null;
          }
          const res = await this.$request({ url: '/plugin/mch/mall/setting/workspace', method: 'post', data: resetData });
          if (res.code === 0) {
            this.$message.success(`${label}已恢复系统默认`);
            this.loadData();
          } else {
            this.$message.error(res.msg);
          }
        } finally {
          this.submitLoading = false;
        }
      }).catch(() => {});
    },
    moveAction(type, index, delta) {
      const targetList = type === 'manager' ? this.form.manager_actions : this.form.employee_actions;
      const newIndex = index + delta;
      if (newIndex < 0 || newIndex >= targetList.length) return;
      const temp = targetList[index];
      this.$set(targetList, index, targetList[newIndex]);
      this.$set(targetList, newIndex, temp);
    }
  }
}
</script>

<style scoped>
.el-form-item__description {
  font-size: 12px;
  color: #999;
  line-height: 1.4;
  margin-top: 4px;
}
.card-meta {
  font-size: 12px;
  color: #999;
  margin-top: 4px;
  font-family: monospace;
}

/* 顶部固定卡片配置 */
.cards-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}

.card-config-item {
  display: flex;
  align-items: stretch;
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 12px;
  overflow: hidden;
  transition: all 0.2s;
}
.card-config-item:hover {
  border-color: #c0c4cc;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

/* 左侧卡片预览 */
.card-preview {
  flex-shrink: 0;
  width: 180px;
  padding: 24px 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  position: relative;
}
.card-preview.preview-stats {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #fff;
}
.card-preview.preview-order {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  color: #fff;
}
.card-preview.preview-goods {
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
  color: #fff;
}
.preview-icon {
  font-size: 40px;
  margin-bottom: 12px;
  opacity: 0.95;
}
.preview-label {
  font-size: 16px;
  font-weight: bold;
  margin-bottom: 4px;
}
.preview-key {
  font-size: 12px;
  font-family: monospace;
  opacity: 0.7;
  background: rgba(255, 255, 255, 0.18);
  padding: 2px 8px;
  border-radius: 4px;
}

/* 右侧 types 勾选 */
.card-types {
  flex: 1;
  padding: 24px 28px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}
.types-title {
  font-size: 14px;
  color: #303133;
  font-weight: 600;
  margin-bottom: 12px;
}
.types-group {
  margin-bottom: 12px;

  .el-checkbox-button {
    margin-right: 8px;
    margin-bottom: 8px;
  }
  .el-checkbox-button__inner {
    border-radius: 6px !important;
    border-left: 1px solid #dcdfe6 !important;
  }
}
.types-hint {
  font-size: 12px;
  color: #909399;
  line-height: 1.5;

  i {
    margin-right: 4px;
    color: var(--admin-accent-text);
  }
}

/* 适用主体 tag (manager/employee 表格列) */
.type-tag {
  margin-right: 6px;
  margin-bottom: 4px;
  border-radius: 12px;

  i {
    margin-right: 2px;
    font-size: 12px;
  }
}

/* 弹窗内 types 复选框 */
::v-deep .el-dialog .el-checkbox-group {
  .el-checkbox {
    margin-right: 16px;
  }
}
</style>
