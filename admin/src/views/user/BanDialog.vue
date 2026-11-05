<template>
  <el-dialog
    :title="dialogTitle"
    :visible.sync="dialogVisible"
    width="480px"
    :close-on-click-modal="false"
    @closed="onClosed"
  >
    <el-form ref="form" :model="form" label-width="90px" size="small">
      <el-form-item label="用户">
        <span class="user-label">
          <el-avatar :size="24" :src="user && user.avatar_url" style="vertical-align: middle; margin-right: 6px;"></el-avatar>
          <span>{{ user && user.nickname }}</span>
          <span class="user-meta">ID: {{ user && user.id }}</span>
        </span>
      </el-form-item>

      <template v-if="mode === 'ban'">
        <el-form-item label="封禁原因" required>
          <el-input
            type="textarea"
            :rows="3"
            v-model="form.reason"
            placeholder="必填,C 端登录被拒时会看到这个原因"
            maxlength="255"
            show-word-limit
          />
        </el-form-item>

        <el-form-item label="解封时间">
          <el-radio-group v-model="durationMode" @change="onDurationChange">
            <el-radio label="permanent">永久</el-radio>
            <el-radio label="1d">1 天</el-radio>
            <el-radio label="7d">7 天</el-radio>
            <el-radio label="30d">30 天</el-radio>
            <el-radio label="custom">自定义</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-form-item v-if="durationMode === 'custom'" label="自定义日期">
          <el-date-picker
            v-model="customUntil"
            type="datetime"
            placeholder="选择到期时间"
            value-format="timestamp"
            :picker-options="{ disabledDate: disabledPastDate }"
            style="width: 100%;"
          />
        </el-form-item>

        <div v-if="resolvedUntilText" class="until-preview">
          <i class="el-icon-info"></i>
          到期时间: <strong>{{ resolvedUntilText }}</strong>
        </div>
      </template>

      <template v-else>
        <el-form-item label="解封备注">
          <el-input
            type="textarea"
            :rows="3"
            v-model="form.reason"
            placeholder="可选,留作审计"
            maxlength="255"
            show-word-limit
          />
        </el-form-item>
        <div v-if="user && user.ban_reason" class="until-preview">
          <i class="el-icon-info"></i>
          当前封禁原因: <strong>{{ user.ban_reason }}</strong>
        </div>
      </template>
    </el-form>

    <span slot="footer">
      <el-button @click="dialogVisible = false" size="small">取消</el-button>
      <el-button
        type="primary"
        size="small"
        :loading="submitting"
        @click="submit"
      >
        {{ submitText }}
      </el-button>
    </span>
  </el-dialog>
</template>

<script>
export default {
  name: 'user-ban-dialog',
  props: {
    visible: { type: Boolean, default: false },
    // mode: 'ban' | 'unban'
    mode: { type: String, default: 'ban' },
    // 被操作的用户对象 { id, nickname, avatar_url, ban_reason }
    user: { type: Object, default: null }
  },
  data() {
    return {
      submitting: false,
      durationMode: 'permanent',
      customUntil: null,
      form: {
        reason: ''
      }
    };
  },
  computed: {
    dialogVisible: {
      get() { return this.visible; },
      set(v) { this.$emit('update:visible', v); }
    },
    dialogTitle() {
      return this.mode === 'ban' ? '拉黑用户' : '解封用户';
    },
    submitText() {
      return this.mode === 'ban' ? '确认拉黑' : '确认解封';
    },
    resolvedUntil() {
      // 返回 banned_until 时间戳 (秒) 或 null = 永久
      if (this.durationMode === 'permanent') return null;
      if (this.durationMode === 'custom') {
        return this.customUntil ? Math.floor(this.customUntil / 1000) : null;
      }
      const days = { '1d': 1, '7d': 7, '30d': 30 }[this.durationMode] || 0;
      if (!days) return null;
      return Math.floor(Date.now() / 1000) + days * 86400;
    },
    resolvedUntilText() {
      if (!this.resolvedUntil) return '';
      const d = new Date(this.resolvedUntil * 1000);
      const pad = (n) => String(n).padStart(2, '0');
      return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }
  },
  watch: {
    visible(val) {
      if (val) {
        this.reset();
      }
    }
  },
  methods: {
    reset() {
      this.form.reason = '';
      this.durationMode = 'permanent';
      this.customUntil = null;
      this.submitting = false;
    },
    onDurationChange() {
      if (this.durationMode !== 'custom') this.customUntil = null;
    },
    disabledPastDate(date) {
      return date.getTime() < Date.now() - 86400000;
    },
    onClosed() {
      this.reset();
    },
    async submit() {
      if (this.mode === 'ban') {
        if (!this.form.reason || !this.form.reason.trim()) {
          this.$message.warning('请填写封禁原因');
          return;
        }
        if (this.durationMode === 'custom' && !this.customUntil) {
          this.$message.warning('请选择自定义到期时间');
          return;
        }
      }

      this.submitting = true;
      try {
        const url = this.mode === 'ban' ? '/mall/user/ban' : '/mall/user/unban';
        const data = {
          user_id: this.user.id,
          reason: this.form.reason.trim()
        };
        if (this.mode === 'ban' && this.resolvedUntil) {
          data.banned_until = this.resolvedUntil;
        }
        const res = await this.$request({ url, method: 'post', data });
        if (res.code === 0) {
          this.$message.success(res.msg || '操作成功');
          this.dialogVisible = false;
          this.$emit('success');
        } else {
          this.$message.error(res.msg || '操作失败');
        }
      } catch (e) {
        this.$message.error('请求失败');
      } finally {
        this.submitting = false;
      }
    }
  }
};
</script>

<style scoped>
.user-label {
  display: inline-flex;
  align-items: center;
  font-size: 13px;
}
.user-meta {
  color: #909399;
  font-size: 12px;
  margin-left: 8px;
}
.until-preview {
  font-size: 12px;
  color: #606266;
  background: #f5f7fa;
  padding: 8px 12px;
  border-radius: 4px;
  margin-left: 90px;
}
.until-preview strong {
  color: #303133;
}
</style>
