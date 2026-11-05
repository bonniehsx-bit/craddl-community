<template>
  <div class="app-container user-mgr-page">
    <!-- 1. 搜索栏 -->
    <el-form :model="queryParams" ref="queryForm" size="small" :inline="true" v-show="showSearch" class="filter-bar">
      <el-form-item label="登录账号" prop="username">
        <el-input
          v-model="queryParams.username"
          placeholder="员工登录用的账号"
          clearable
          style="width: 180px"
          @keyup.enter.native="handleQuery"
        />
      </el-form-item>
      <el-form-item label="显示昵称" prop="nickname">
        <el-input
          v-model="queryParams.nickname"
          placeholder="界面展示名 / 真实姓名"
          clearable
          style="width: 180px"
          @keyup.enter.native="handleQuery"
        />
      </el-form-item>
      <el-form-item label="手机号" prop="mobile">
        <el-input
          v-model="queryParams.mobile"
          placeholder="请输入手机号"
          clearable
          style="width: 180px"
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

    <!-- 2. 工具栏 -->
    <el-row :gutter="10" class="mb8">
      <el-col :span="1.5">
        <el-button
          type="primary"
          plain
          icon="el-icon-plus"
          size="mini"
          @click="handleAdd"
          v-hasPermi="['system:user:add']"
        >新增员工</el-button>
      </el-col>
      <el-col :span="1.5">
        <el-button
          type="success"
          plain
          icon="el-icon-edit"
          size="mini"
          :disabled="single"
          @click="handleUpdateSelected"
          v-hasPermi="['system:user:edit']"
        >修改</el-button>
      </el-col>
      <el-col :span="1.5">
        <el-button
          type="danger"
          plain
          icon="el-icon-delete"
          size="mini"
          :disabled="multiple"
          @click="handleBatchDelete"
          v-hasPermi="['system:user:remove']"
        >批量删除</el-button>
      </el-col>
      <right-toolbar :showSearch.sync="showSearch" @queryTable="getList"></right-toolbar>
    </el-row>

    <!-- 3. 表格数据 -->
    <el-table
      v-loading="loading"
      :data="userList"
      @selection-change="handleSelectionChange"
      border
      stripe
      :row-class-name="rowClassName"
    >
      <el-table-column type="selection" width="42" align="center" />
      <el-table-column label="ID" align="center" prop="id" width="60" />

      <el-table-column min-width="200">
        <template slot="header">
          <span>员工</span>
          <el-tooltip placement="top" effect="light">
            <div slot="content">
              <div><b>显示昵称</b>：界面展示用</div>
              <div><b>登录账号</b>：员工登录系统用（修改后旧 Token 失效，需用新账号重登）</div>
            </div>
            <i class="el-icon-info" style="color:#909399;margin-left:4px;cursor:help"></i>
          </el-tooltip>
        </template>
        <template slot-scope="scope">
          <div class="user-cell">
            <div class="user-cell-name">{{ scope.row.nickname || scope.row.username }}</div>
            <div class="user-cell-sub">
              <i class="el-icon-user-solid"></i> 登录: <span class="mono-text">{{ scope.row.username }}</span>
            </div>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="手机号" align="center" prop="mobile" width="120" />
      <el-table-column label="邮箱" align="center" prop="email" min-width="160" :show-overflow-tooltip="true">
        <template slot-scope="scope">
          <span v-if="scope.row.email">{{ scope.row.email }}</span>
          <span v-else class="text-placeholder">—</span>
        </template>
      </el-table-column>

      <el-table-column label="角色" align="center" width="120">
        <template slot-scope="scope">
          <el-tag v-if="scope.row.role_name" size="mini" type="info">{{ scope.row.role_name }}</el-tag>
          <span v-else class="text-placeholder">未分配</span>
        </template>
      </el-table-column>

      <el-table-column label="账号状态" align="center" width="100">
        <template slot-scope="scope">
          <el-tag v-if="scope.row.account_status === 'expired'" size="mini" type="danger">已停用</el-tag>
          <el-tag v-else-if="scope.row.is_has_expire == 1" size="mini" type="warning">限时</el-tag>
          <el-tag v-else size="mini" type="success">正常</el-tag>
        </template>
      </el-table-column>

      <el-table-column label="最近登录 IP" align="center" prop="login_ip" width="130">
        <template slot-scope="scope">
          <span v-if="scope.row.login_ip" class="mono-text">{{ scope.row.login_ip }}</span>
          <span v-else class="text-placeholder">—</span>
        </template>
      </el-table-column>

      <el-table-column label="创建时间" align="center" prop="created_time" width="155">
        <template slot-scope="scope">
          {{ parseTime(scope.row.created_time) }}
        </template>
      </el-table-column>

      <el-table-column label="操作" align="center" width="200" fixed="right" class-name="small-padding fixed-width">
        <template slot-scope="scope">
          <el-button size="mini" type="text" icon="el-icon-edit" @click="handleUpdate(scope.row)" v-hasPermi="['system:user:edit']">编辑</el-button>
          <el-button size="mini" type="text" icon="el-icon-key" @click="handleResetPassword(scope.row)" v-hasPermi="['system:user:edit']">重置密码</el-button>
          <el-button size="mini" type="text" icon="el-icon-delete" style="color:#F56C6C" @click="handleDelete(scope.row)" v-hasPermi="['system:user:remove']">删除</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 4. 分页 -->
    <div class="pager-wrap" v-show="total > 0">
      <el-pagination
        background
        @size-change="handleSizeChange"
        @current-change="handleCurrentChange"
        :current-page="queryParams.page"
        :page-sizes="[10, 20, 30, 50]"
        :page-size="queryParams.limit"
        layout="total, sizes, prev, pager, next, jumper"
        :total="total"
      ></el-pagination>
    </div>

    <!-- 5. 添加 / 修改对话框 -->
    <el-dialog :title="title" :visible.sync="open" width="720px" append-to-body :close-on-click-modal="false" @close="cancel">
      <el-form ref="form" :model="form" :rules="rules" label-width="100px" size="medium">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item prop="username">
              <template slot="label">
                登录账号
                <el-tooltip content="员工登录系统时输入的账号" placement="top" effect="light">
                  <i class="el-icon-info field-info-icon"></i>
                </el-tooltip>
              </template>
              <el-input v-model="form.username" placeholder="员工登录用，例如 zhangsan" maxlength="30">
                <i slot="prefix" class="el-input__icon el-icon-key"></i>
              </el-input>
              <div v-if="!form.id" class="field-hint">用于登录系统，建议用英文/数字组合</div>
              <div v-else-if="form.username !== originalUsername" class="field-hint field-hint--warn">
                <i class="el-icon-warning"></i> 登录账号将从 <b>{{ originalUsername }}</b> 改为 <b>{{ form.username }}</b>，保存后该员工旧 Token 失效，需用新账号重新登录
              </div>
              <div v-else class="field-hint">可修改；修改后员工旧 Token 失效，需用新账号重登</div>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item prop="nickname">
              <template slot="label">
                显示昵称
                <el-tooltip content="界面展示用，建议填真实姓名或工号" placement="top" effect="light">
                  <i class="el-icon-info field-info-icon"></i>
                </el-tooltip>
              </template>
              <el-input v-model="form.nickname" placeholder="界面展示用，例如 张三" maxlength="30">
                <i slot="prefix" class="el-input__icon el-icon-user"></i>
              </el-input>
              <div class="field-hint">界面展示用，可随时修改</div>
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="手机号" prop="mobile">
              <el-input v-model="form.mobile" placeholder="11 位手机号" maxlength="11" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="邮箱" prop="email">
              <el-input v-model="form.email" placeholder="选填" maxlength="50" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item v-if="!form.id" label="初始密码" prop="password">
              <el-input v-model="form.password" placeholder="6-20 位" type="password" maxlength="20" show-password />
            </el-form-item>
            <el-form-item v-else label="密码">
              <div class="readonly-tip">编辑时不允许直接改密码，请用列表中的「重置密码」按钮</div>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="归属角色" prop="roleIds">
              <el-select v-model="form.roleIds" multiple placeholder="请选择角色" style="width:100%">
                <el-option
                  v-for="item in roleOptions"
                  :key="item.role_id || item.id"
                  :label="item.role_name"
                  :value="item.role_id || item.id"
                  :disabled="item.status == 0"
                ></el-option>
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="20">
          <el-col :span="24">
            <el-form-item label="账号有效期">
              <el-radio-group v-model="form.is_has_expire">
                <el-radio :label="0">永久有效</el-radio>
                <el-radio :label="1">指定过期 / 停用</el-radio>
              </el-radio-group>
              <div v-if="form.is_has_expire == 1" style="margin-top:8px;">
                <el-date-picker
                  v-model="form.end_at_ms"
                  type="datetime"
                  value-format="timestamp"
                  placeholder="到期时间（过此时间账号自动停用）"
                  style="width: 100%"
                ></el-date-picker>
                <div class="form-tip">
                  <i class="el-icon-info"></i> 设置一个过去的时间即可"立即停用"该账号；员工已发出的 Token 会在过期后失效。
                </div>
              </div>
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="20">
          <el-col :span="24">
            <el-form-item label="备注">
              <el-input v-model="form.remark" type="textarea" :rows="2" placeholder="选填，最多 200 字" maxlength="200" show-word-limit />
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>

      <div slot="footer" class="dialog-footer">
        <el-button @click="cancel">取 消</el-button>
        <el-button type="primary" :loading="submitting" @click="submitForm">确 定</el-button>
      </div>
    </el-dialog>

    <!-- 6. 重置密码弹窗 -->
    <el-dialog title="重置密码" :visible.sync="resetPwdOpen" width="460px" append-to-body :close-on-click-modal="false" @close="resetPwdReset">
      <el-form ref="resetPwdForm" :model="resetPwd" :rules="resetPwdRules" label-width="100px" size="medium">
        <el-form-item label="目标员工">
          <div class="readonly-cell">
            <div><i class="el-icon-user"></i> 昵称：<b>{{ resetPwd.nickname }}</b></div>
            <div class="reset-pwd-loginline"><i class="el-icon-key"></i> 登录账号：<span class="mono-text">{{ resetPwd.username }}</span></div>
          </div>
        </el-form-item>
        <el-form-item label="新密码" prop="password">
          <el-input v-model="resetPwd.password" type="password" placeholder="6-20 位" maxlength="20" show-password />
        </el-form-item>
        <el-form-item label="确认密码" prop="passwordConfirm">
          <el-input v-model="resetPwd.passwordConfirm" type="password" placeholder="再次输入" maxlength="20" show-password />
        </el-form-item>
        <div class="form-tip" style="margin-left: 100px;">
          <i class="el-icon-warning"></i> 重置后该账号的所有登录 Token 将被强制失效，员工需重新登录。
        </div>
      </el-form>
      <div slot="footer" class="dialog-footer">
        <el-button @click="resetPwdOpen = false">取 消</el-button>
        <el-button type="primary" :loading="resetPwdSubmitting" @click="submitResetPwd">确 定</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'User',
  data() {
    const validatePasswordConfirm = (rule, value, callback) => {
      if (value !== this.resetPwd.password) {
        callback(new Error('两次密码不一致'))
      } else {
        callback()
      }
    }
    return {
      loading: true,
      submitting: false,
      ids: [],
      single: true,
      multiple: true,
      showSearch: true,
      total: 0,
      userList: [],
      title: '',
      open: false,
      dateRange: [],
      roleOptions: [],
      originalUsername: '', // 编辑时记录原登录账号，用于变更高亮提示

      queryParams: {
        page: 1,
        limit: 10,
        username: undefined,
        nickname: undefined,
        mobile: undefined,
        params: undefined
      },

      form: {},

      rules: {
        username: [
          { required: true, message: '用户名不能为空', trigger: 'blur' },
          { min: 2, max: 20, message: '长度必须介于 2-20 之间', trigger: 'blur' }
        ],
        nickname: [{ required: true, message: '昵称不能为空', trigger: 'blur' }],
        password: [
          { required: true, message: '密码不能为空', trigger: 'blur' },
          { min: 6, max: 20, message: '长度必须介于 6-20 之间', trigger: 'blur' }
        ],
        email: [{ type: 'email', message: '请输入正确的邮箱', trigger: ['blur', 'change'] }],
        mobile: [
          { required: true, message: '手机号不能为空', trigger: 'blur' },
          { pattern: /^1[3-9]\d{9}$/, message: '手机号格式不正确', trigger: 'blur' }
        ],
        roleIds: [{ required: true, message: '请至少选择一个角色', trigger: 'change' }]
      },

      // 重置密码弹窗
      resetPwdOpen: false,
      resetPwdSubmitting: false,
      resetPwd: { id: null, username: '', nickname: '', password: '', passwordConfirm: '' },
      resetPwdRules: {
        password: [
          { required: true, message: '请输入新密码', trigger: 'blur' },
          { min: 6, max: 20, message: '长度必须介于 6-20 之间', trigger: 'blur' }
        ],
        passwordConfirm: [
          { required: true, message: '请再次输入', trigger: 'blur' },
          { validator: validatePasswordConfirm, trigger: 'blur' }
        ]
      }
    }
  },
  created() {
    this.getList()
  },
  methods: {
    // ============ 接口层 ============
    getList() {
      this.loading = true
      if (this.dateRange && this.dateRange.length > 0) {
        this.queryParams.params = JSON.stringify({
          beginTime: this.dateRange[0],
          endTime: this.dateRange[1]
        })
      } else {
        this.queryParams.params = undefined
      }
      this.$request({ url: '/admin/admin/user-list', method: 'get', data: this.queryParams })
        .then(response => {
          if (response.code === 0) {
            this.userList = response.data.rows || []
            this.total = response.data.total || 0
          } else {
            this.$modal.msgError(response.msg)
          }
        })
        .finally(() => { this.loading = false })
    },

    getUserDetail(id) {
      return this.$request({ url: '/admin/admin/user', method: 'get', data: { id } })
    },

    getRoleList() {
      return this.$request({ url: '/admin/admin/roles', method: 'get', data: { page: 1, limit: 500 } })
    },

    submitUser(data) {
      return this.$request({ url: '/admin/admin/user-edit', method: 'post', data })
    },

    deleteUser(id) {
      return this.$request({ url: '/admin/admin/delete', method: 'post', data: { id } })
    },

    resetPassword(id, password) {
      return this.$request({ url: '/admin/admin/password-reset', method: 'post', data: { id, password } })
    },

    // ============ 页面逻辑 ============
    handleQuery() { this.queryParams.page = 1; this.getList() },

    resetQuery() {
      this.dateRange = []
      this.resetForm('queryForm')
      this.handleQuery()
    },

    handleSelectionChange(selection) {
      this.ids = selection.map(item => item.id)
      this.single = selection.length !== 1
      this.multiple = !selection.length
    },

    handleSizeChange(val) { this.queryParams.limit = val; this.getList() },
    handleCurrentChange(val) { this.queryParams.page = val; this.getList() },

    rowClassName({ row }) {
      return row.account_status === 'expired' ? 'row-expired' : ''
    },

    cancel() { this.open = false; this.reset() },

    reset() {
      this.form = {
        id: undefined,
        username: undefined,
        nickname: undefined,
        password: undefined,
        mobile: undefined,
        email: undefined,
        remark: undefined,
        roleIds: [],
        admin_type: 2,
        is_has_expire: 0,
        end_at: 0,
        end_at_ms: ''
      }
      this.originalUsername = ''
      this.resetForm('form')
    },

    handleAdd() {
      this.reset()
      this.getRoleList().then(response => {
        if (response.code === 0) {
          this.roleOptions = response.data.rows || response.data.list || []
          this.open = true
          this.title = '新增员工'
        } else {
          this.$modal.msgError('无法获取角色列表')
        }
      })
    },

    handleUpdateSelected() {
      if (this.ids.length === 1) {
        this.handleUpdate({ id: this.ids[0] })
      }
    },

    handleUpdate(row) {
      this.reset()
      const id = row.id
      this.getUserDetail(id).then(response => {
        if (response.code === 0) {
          const data = response.data
          this.form = data.data
          // 修复 Vue2 响应式丢失
          this.$set(this.form, 'roleIds', data.roleIds || [])
          this.$set(this.form, 'password', '')
          this.$set(this.form, 'is_has_expire', parseInt(this.form.is_has_expire || 0))
          this.$set(this.form, 'end_at', parseInt(this.form.end_at || 0))
          this.$set(this.form, 'end_at_ms', this.form.end_at ? this.form.end_at * 1000 : '')
          this.originalUsername = this.form.username || ''
          this.roleOptions = data.roles || []
          this.open = true
          this.title = '编辑员工'
        } else {
          this.$modal.msgError(response.msg)
        }
      })
    },

    submitForm() {
      this.$refs['form'].validate(valid => {
        if (!valid) return
        if (this.form.is_has_expire === 1 && !this.form.end_at_ms) {
          this.$modal.msgWarning('请选择过期时间')
          return
        }
        const usernameChanged = this.form.id && this.originalUsername && this.form.username !== this.originalUsername
        if (usernameChanged) {
          this.$modal.confirm(`确定将登录账号从「${this.originalUsername}」改为「${this.form.username}」吗？\n\n该员工旧 Token 将立即失效，必须用新账号重新登录。`)
            .then(() => this.doSubmit())
            .catch(() => {})
        } else {
          this.doSubmit()
        }
      })
    },

    doSubmit() {
      const payload = { ...this.form }
      payload.end_at = this.form.is_has_expire === 1 && this.form.end_at_ms
        ? Math.floor(this.form.end_at_ms / 1000)
        : 0
      delete payload.end_at_ms
      // 编辑模式不带 password（避免误覆盖；改密码走"重置密码"按钮）
      if (payload.id) delete payload.password

      this.submitting = true
      const actionText = payload.id ? '修改' : '新增'
      this.submitUser(payload)
        .then(response => {
          if (response.code === 0) {
            this.$modal.msgSuccess(actionText + '成功')
            this.open = false
            this.getList()
          } else {
            this.$modal.msgError(response.msg)
          }
        })
        .finally(() => { this.submitting = false })
    },

    handleDelete(row) {
      const label = row.nickname || row.username || ('编号 ' + row.id)
      this.$modal.confirm(`确定删除员工「${label}」吗？删除后无法恢复。`)
        .then(() => this.deleteUser(row.id))
        .then(response => {
          if (response.code === 0) {
            this.$modal.msgSuccess('删除成功')
            this.getList()
          } else {
            this.$modal.msgError(response.msg)
          }
        }).catch(() => {})
    },

    handleBatchDelete() {
      const ids = this.ids
      if (!ids.length) return
      this.$modal.confirm(`确定批量删除 ${ids.length} 个员工账号吗？删除后无法恢复。`)
        .then(async () => {
          let success = 0
          let failed = []
          for (const id of ids) {
            try {
              const res = await this.deleteUser(id)
              if (res.code === 0) success++
              else failed.push(`#${id}: ${res.msg}`)
            } catch (e) {
              failed.push(`#${id}: 网络错误`)
            }
          }
          if (failed.length) {
            this.$modal.msgWarning(`成功 ${success} 个，失败 ${failed.length} 个：${failed.join('；')}`)
          } else {
            this.$modal.msgSuccess(`已删除 ${success} 个`)
          }
          this.getList()
        }).catch(() => {})
    },

    // ============ 重置密码 ============
    handleResetPassword(row) {
      this.resetPwd = {
        id: row.id,
        username: row.username,
        nickname: row.nickname || row.username,
        password: '',
        passwordConfirm: ''
      }
      this.resetPwdOpen = true
      this.$nextTick(() => {
        if (this.$refs.resetPwdForm) this.$refs.resetPwdForm.clearValidate()
      })
    },

    resetPwdReset() {
      this.resetPwd = { id: null, username: '', nickname: '', password: '', passwordConfirm: '' }
      if (this.$refs.resetPwdForm) this.$refs.resetPwdForm.resetFields()
    },

    submitResetPwd() {
      this.$refs['resetPwdForm'].validate(valid => {
        if (!valid) return
        this.resetPwdSubmitting = true
        this.resetPassword(this.resetPwd.id, this.resetPwd.password)
          .then(response => {
            if (response.code === 0) {
              this.$modal.msgSuccess('重置成功，员工需重新登录')
              this.resetPwdOpen = false
            } else {
              this.$modal.msgError(response.msg)
            }
          })
          .finally(() => { this.resetPwdSubmitting = false })
      })
    },

    parseTime(time, pattern) {
      if (!time) return ''
      const format = pattern || '{y}-{m}-{d} {h}:{i}:{s}'
      let date
      if (typeof time === 'object') {
        date = time
      } else {
        if ((typeof time === 'string') && (/^[0-9]+$/.test(time))) time = parseInt(time)
        if ((typeof time === 'number') && (time.toString().length === 10)) time = time * 1000
        date = new Date(time)
      }
      const fmt = {
        y: date.getFullYear(), m: date.getMonth() + 1, d: date.getDate(),
        h: date.getHours(), i: date.getMinutes(), s: date.getSeconds()
      }
      return format.replace(/{(y|m|d|h|i|s)+}/g, (result, key) => {
        let v = fmt[key]
        if (result.length > 0 && v < 10) v = '0' + v
        return v || 0
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.user-mgr-page {
  .filter-bar {
    background: #fafbfc;
    padding: 12px 16px 0;
    border-radius: 4px;
    margin-bottom: 12px;
  }
  .user-cell {
    text-align: left;
    line-height: 1.3;
    .user-cell-name { font-weight: 600; color: #303133; }
    .user-cell-sub { font-size: 12px; color: #909399; margin-top: 2px; }
  }
  .mono-text {
    font-family: 'SF Mono', Menlo, Consolas, monospace;
    font-size: 12px;
    color: #606266;
  }
  .text-placeholder { color: #c0c4cc; }
  .pager-wrap { margin-top: 20px; text-align: right; }
  .form-tip {
    color: #909399;
    font-size: 12px;
    line-height: 1.4;
    margin-top: 6px;
    i { margin-right: 4px; color: #e6a23c; }
  }
  .readonly-tip {
    color: #909399;
    font-size: 13px;
    line-height: 36px;
  }
  .readonly-cell {
    color: #303133;
    font-weight: 500;
    line-height: 1.7;
    background: #fafbfc;
    border: 1px solid #ebeef5;
    border-radius: 4px;
    padding: 8px 12px;
    i { color: #909399; margin-right: 4px; }
  }
  .reset-pwd-loginline { color: #606266; font-weight: normal; font-size: 13px; margin-top: 2px; }

  /* 字段标签后的 info 图标 */
  .field-info-icon {
    color: #909399;
    margin-left: 4px;
    cursor: help;
    font-size: 14px;
  }

  /* 字段下方的提示文字 */
  .field-hint {
    color: #909399;
    font-size: 12px;
    line-height: 1.5;
    margin-top: 2px;
    b { color: #e6a23c; font-weight: 600; }
    &--locked {
      color: #c0c4cc;
      i { margin-right: 4px; }
    }
    &--warn {
      color: #e6a23c;
      background: #fdf6ec;
      border-left: 3px solid #e6a23c;
      padding: 6px 10px;
      border-radius: 2px;
      i { margin-right: 4px; }
    }
  }
}
</style>

<style lang="scss">
/* 表格行：已停用的账号置灰 */
.user-mgr-page .el-table .row-expired {
  background-color: #fef0f0 !important;
  color: #909399;
  .user-cell-name { color: #909399; }
}
</style>
