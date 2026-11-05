<template>
  <el-dialog
    title="发放优惠券"
    :visible.sync="dialogVisible"
    width="500px"
    @close="handleClose"
    :close-on-click-modal="false"
    append-to-body>

    <el-form :model="form" :rules="rules" ref="formRef" label-width="100px" size="small" v-loading="loading">

      <el-form-item label="优惠券名称">
        <div style="font-weight: bold; font-size: 14px;">{{ couponName }}</div>
      </el-form-item>

      <el-form-item label="选择用户" prop="user">
        <!--
            [核心修改]
            1. 移除 :multiple="true" (默认为单选)
            2. 绑定 form.user (单个对象)
        -->
        <user-selector v-model="form.user"></user-selector>

        <!-- 选中后显示用户信息预览，防止发错人 -->
        <div v-if="form.user" class="user-preview">
          <el-avatar :size="40" :src="form.user.avatar_url" shape="square"></el-avatar>
          <div class="user-info">
            <div class="nickname">{{ form.user.nickname }}</div>
            <div class="uid">ID: {{ form.user.id }}</div>
          </div>
        </div>
      </el-form-item>

      <el-form-item label="备注">
        <div class="form-tip">
          发放后优惠券将立即存入该用户账户，请确认对象无误。
        </div>
      </el-form-item>

    </el-form>

    <div slot="footer">
      <el-button @click="handleClose" size="small">取 消</el-button>
      <el-button type="primary" @click="handleSubmit" :loading="submitLoading" size="small">确 定 发 放</el-button>
    </div>
  </el-dialog>
</template>

<script>
export default {
  name: 'CouponSend',
  props: {
    visible: Boolean,
    couponId: [Number, String],
    couponName: String
  },
  data() {
    return {
      loading: false,
      submitLoading: false,
      form: {
        user: null // 单选：这里是一个对象 {id:1, nickname: 'xx', ...}
      },
      rules: {
        user: [
          { required: true, message: '请选择一位用户', trigger: 'change' }
        ]
      }
    };
  },
  computed: {
    dialogVisible: {
      get() { return this.visible; },
      set(val) { this.$emit('update:visible', val); }
    }
  },
  methods: {
    // 提交前校验
    handleSubmit() {
      this.$refs.formRef.validate(valid => {
        if (valid) {
          if (!this.couponId) {
            this.$message.error('优惠券参数错误');
            return;
          }
          if (!this.form.user || !this.form.user.id) {
            this.$message.error('请选择用户');
            return;
          }

          this.$confirm(`确定向用户 [${this.form.user.nickname}] 发放此优惠券吗？`, '发放确认', {
            confirmButtonText: '确定发放',
            cancelButtonText: '取消',
            type: 'warning'
          }).then(() => {
            this.doSend();
          }).catch(() => {});
        }
      });
    },

    // 执行 API 请求
    doSend() {
      this.submitLoading = true;

      // [关键] 虽然是单发，但为了兼容后端通用的 send 接口(通常设计为支持数组)，我们包装成数组
      const userIds = [this.form.user.id];

      this.$request({
        url: '/plugin/marketing/mall/coupon/send',
        method: 'post',
        data: {
          coupon_id: this.couponId,
          uids: userIds
        }
      }).then(res => {
        if (res.code === 0) {
          // 单发模式下，直接提示成功即可
          this.$message.success('发放成功');
          this.handleClose();
          this.$emit('saved');
        } else {
          this.$message.error(res.msg);
        }
      }).catch(e => {
        this.$message.error(e.message);
      }).finally(() => {
        this.submitLoading = false;
      });
    },

    // 关闭弹窗并重置
    handleClose() {
      this.$refs.formRef.resetFields();
      this.form.user = null;
      this.$emit('update:visible', false);
      this.$emit('closed');
    },
  }
}
</script>

<style scoped>
.form-tip {
  font-size: 12px;
  color: #909399;
  line-height: 1.5;
}
.user-preview {
  margin-top: 10px;
  padding: 10px;
  background-color: #f5f7fa;
  border-radius: 4px;
  display: flex;
  align-items: center;
  border: 1px solid #e4e7ed;
}
.user-info {
  margin-left: 10px;
  line-height: 1.4;
}
.nickname {
  font-weight: bold;
  color: #303133;
}
.uid {
  font-size: 12px;
  color: #909399;
}
</style>
