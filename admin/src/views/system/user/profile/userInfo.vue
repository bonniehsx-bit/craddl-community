<template>
  <el-form ref="form" :model="user" :rules="rules" label-width="80px">
    <el-form-item label="用户昵称" prop="nickname">
      <el-input v-model="user.nickname" maxlength="30" />
    </el-form-item>

    <div style="margin: 20px 0; border-top: 1px dashed #e4e7ed; padding-top: 20px;">
        <span style="font-weight: bold; color: #606266; margin-bottom: 15px; display: inline-block;">客服调度专属配置 (Customer Service)</span>
        <el-form-item label="前台真身" prop="bind_uid">
            <el-input v-model="user.bind_uid" placeholder="请输入用于私聊的前端商城对应自身 UID (如 889)" maxlength="11" />
            <div style="font-size: 12px; color: #999;">绑定后，买家消息将投递到此微信号/手机端对应的小程序身份上。</div>
        </el-form-item>
        
        <el-form-item label="客服状态" prop="is_kefu">
            <el-switch v-model="user.is_kefu" :active-value="1" :inactive-value="0" active-text="正在接客" inactive-text="下线休息"></el-switch>
            <div style="font-size: 12px; color: #999; margin-top: 5px;">下线状态下，系统不会再将新客分配给您。</div>
        </el-form-item>
    </div>

    <el-form-item>
      <el-button type="primary" size="mini" @click="submit">保存</el-button>
      <!--
        close() 调的是 $tab.closePage() —— 关闭当前页签。在原页面里合理,
        但本组件被抽屉复用时点它会把整个页签关掉(抽屉自己有右上角 ×)。
        所以给个开关,抽屉传 false 即可;默认 true,原页面行为不变。
      -->
      <el-button v-if="showClose" type="danger" size="mini" @click="close">关闭</el-button>
    </el-form-item>
  </el-form>
</template>

<script>
  import {
    updateUserProfile
  } from "@/api/system/user";

  export default {
    props: {
      user: {
        type: Object
      },
      // 见模板里的说明:抽屉复用本组件时传 false,避免「关闭」把整个页签关掉
      showClose: {
        type: Boolean,
        default: true
      }
    },
    data() {
      return {
        // 表单校验（后台管理账号只保留昵称必填；邮箱/手机号是 C 端会员概念，后台不需要）
        rules: {
          nickname: [{
            required: true,
            message: "用户昵称不能为空",
            trigger: "blur"
          }]
        }
      };
    },
    methods: {
      submit() {
        this.$refs["form"].validate(valid => {
          if (valid) {
            updateUserProfile(this.user).then(response => {
              this.$modal.msgSuccess("修改成功");
            });
          }
        });
      },
      close() {
        this.$tab.closePage();
      }
    }
  };
</script>
