<template>
  <!--
    「修改密码」独立弹窗。

    原本它是「我的资料」抽屉里的一个 tab —— 但改密码是安全操作、目的极明确,
    埋在「展开菜单 → 我的资料 → 切 tab」三层下面并不好找;而且它和「基本资料」
    提交的是完全不同的东西、各有各的按钮,挤在同一块区域反而让人不知道该点哪个。
    拆成独立入口后,抽屉那边只剩一条线:看资料 → 改资料 → 一个保存按钮。

    没有复用 views/system/user/profile/resetPwd.vue:那个组件的按钮长在表单里、
    「关闭」调的是 $tab.closePage()(关页签,在弹窗里是错的)。校验规则和接口是照它抄的,
    保持一致;原页面继续用它,不受影响。
  -->
  <el-dialog
    title="修改密码"
    :visible.sync="visible"
    width="420px"
    append-to-body
    :close-on-click-modal="false"
    @open="reset"
    @closed="reset">
    <el-form ref="form" :model="form" :rules="rules" label-width="82px" @submit.native.prevent>
      <el-form-item label="旧密码" prop="oldPassword">
        <el-input v-model="form.oldPassword" placeholder="请输入旧密码" type="password" show-password />
      </el-form-item>
      <el-form-item label="新密码" prop="newPassword">
        <el-input v-model="form.newPassword" placeholder="6 到 20 个字符" type="password" show-password />
      </el-form-item>
      <el-form-item label="确认密码" prop="confirmPassword">
        <el-input v-model="form.confirmPassword" placeholder="请再次输入新密码" type="password" show-password />
      </el-form-item>
    </el-form>

    <!-- 改完密码后端会让当前会话失效,这里先说清楚,免得用户改完继续操作时突然被踢 -->
    <div class="pd-tip">
      <i class="el-icon-info"></i>
      <span>密码修改成功后当前登录会失效，需要用新密码重新登录。</span>
    </div>

    <span slot="footer">
      <el-button size="small" @click="visible = false">取 消</el-button>
      <el-button size="small" type="primary" :loading="submitting" @click="submit">确 定</el-button>
    </span>
  </el-dialog>
</template>

<script>
import { updateUserPwd } from '@/api/system/user'

export default {
  name: 'PasswordDialog',
  data() {
    const equalToPassword = (rule, value, callback) => {
      if (this.form.newPassword !== value) {
        callback(new Error('两次输入的密码不一致'))
      } else {
        callback()
      }
    }
    return {
      visible: false,
      submitting: false,
      form: {
        oldPassword: undefined,
        newPassword: undefined,
        confirmPassword: undefined
      },
      rules: {
        oldPassword: [
          { required: true, message: '旧密码不能为空', trigger: 'blur' }
        ],
        newPassword: [
          { required: true, message: '新密码不能为空', trigger: 'blur' },
          { min: 6, max: 20, message: '长度在 6 到 20 个字符', trigger: 'blur' }
        ],
        confirmPassword: [
          { required: true, message: '确认密码不能为空', trigger: 'blur' },
          { required: true, validator: equalToPassword, trigger: 'blur' }
        ]
      }
    }
  },
  methods: {
    show() {
      this.visible = true
    },
    // 开和关都清一次:密码框里留着上次输入的内容,既不安全也容易误提交
    reset() {
      this.form.oldPassword = undefined
      this.form.newPassword = undefined
      this.form.confirmPassword = undefined
      this.submitting = false
      this.$nextTick(() => {
        this.$refs.form && this.$refs.form.clearValidate()
      })
    },
    submit() {
      this.$refs.form.validate(valid => {
        if (!valid) return
        this.submitting = true
        updateUserPwd(this.form.oldPassword, this.form.newPassword).then(() => {
          this.visible = false
          // 后端 PasswordChangeForm 改完会调 Admin::invalidateAuth(),token 当场作废。
          // 不主动送去登录页的话,用户下一次点击就会撞上 401 被动跳转,看起来像是系统出错。
          this.$alert('密码已修改，请使用新密码重新登录。', '修改成功', {
            confirmButtonText: '去登录',
            type: 'success',
            showClose: false,
            callback: () => {
              this.$store.dispatch('LogOut').then(() => {
                window.location.href = '/admin'
              }).catch(() => {
                window.location.href = '/admin'
              })
            }
          })
        }).catch(() => {
          this.submitting = false
        })
      })
    }
  }
}
</script>

<style lang="scss" scoped>
.pd-tip {
  display: flex;
  align-items: flex-start;
  margin-top: 2px;
  padding: 8px 10px;
  border-radius: 4px;
  background: #f4f4f5;
  font-size: 12px;
  line-height: 1.6;
  color: #909399;

  i {
    margin-right: 6px;
    margin-top: 2px;
  }
}
</style>
