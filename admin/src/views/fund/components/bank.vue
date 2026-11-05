<template>
  <div class="app-container">
    <el-card>
      <div slot="header" class="clearfix">
        <span>银行卡打款设置</span>
        <el-button style="float: right; padding: 3px 0" type="text" @click="save" :loading="is_loading">保存</el-button>
      </div>

      <div style="width: 900px">
        <el-form size="mini" ref="form" :model="form" label-width="160px">

          <!-- 启用开关 -->
          <el-form-item label="是否启用" prop="status">
            <el-switch v-model="form.status" active-value="1" inactive-value="0">
            </el-switch>
          </el-form-item>

          <!-- 显示名称 -->
          <el-form-item label="渠道名称" prop="name">
            <el-input v-model="form.name" placeholder="例如：银行卡转账"></el-input>
          </el-form-item>

          <!-- 备注说明 -->
          <el-form-item label="操作说明" prop="desc">
            <el-input
              type="textarea"
              v-model="form.desc"
              placeholder="请输入给管理员看的备注"
              :rows="3">
            </el-input>
            <div style="font-size: 12px; color: #909399; margin-top: 5px;">
              提示：此通道为“线下/手动”打款模式。开启后，用户在前端需填写开户行、卡号和姓名。提现申请提交后，管理员需根据信息手动转账，并在后台点击“确认打款”。
            </div>
          </el-form-item>

        </el-form>
      </div>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'fund-bank',
  data() {
    return {
      is_loading: false,
      form: {
        status: '0',
        name: '银行卡转账',
        desc: ''
      }
    }
  },
  created() {
    this.search()
  },
  methods: {
    // 获取配置
    search() {
      this.is_loading = true
      this.$request({
        url: '/mall/fund/bank'
      }).then(res => {
        this.is_loading = false
        if (res.code === 0 && res.data) {
          this.form = res.data
        }
      }).catch(res => {
        this.is_loading = false
      })
    },
    // 保存配置
    save() {
      if (this.is_loading) {
        return
      }
      this.is_loading = true
      this.$request({
        url: '/mall/fund/bank',
        data: {
          setting: this.form
        },
        method: 'post'
      }).then(e => {
        this.is_loading = false
        if (e.code === 0) {
          this.$message.success(e.msg)
        } else {
          this.$message.error(e.msg)
        }
      }).catch(() => {
        this.is_loading = false
      })
    }
  }
}
</script>

<style scoped>
</style>
