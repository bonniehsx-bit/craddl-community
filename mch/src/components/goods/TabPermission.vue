<template>
  <div>
    <div style="width: 90%;height: 40px;background-color:#F4F6F8 ;color: #000000;font-weight: bold;line-height: 40px;padding: 0 20px; margin-bottom: 20px;">
      商品浏览权限
    </div>
    <el-form-item label="会员等级" prop="permission.explode_is_level">
      <el-radio-group v-model="form.permission.explode_is_level">
        <el-radio :label="0">不限制会员等级</el-radio>
        <el-radio :label="1">指定会员可见</el-radio>
      </el-radio-group>
    </el-form-item>
    <el-form-item v-if="form.permission.explode_is_level == 1">
      <el-checkbox-group v-model="form.permission.explode_level_ids">
        <el-checkbox :label="level.id" v-for="level in validLevelList" :key="level.id">
          {{ level.name }}
        </el-checkbox>
      </el-checkbox-group>
    </el-form-item>

    <div style="width: 90%;height: 40px;background-color:#F4F6F8 ;color: #000000;font-weight: bold;line-height: 40px;padding: 0 20px; margin: 20px 0;">
      商品购买权限
    </div>
    <el-form-item label="会员等级" prop="permission.buy_is_level">
      <el-radio-group v-model="form.permission.buy_is_level">
        <el-radio :label="0">不限制会员等级</el-radio>
        <el-radio :label="1">指定会员可购买</el-radio>
      </el-radio-group>
    </el-form-item>
    <el-form-item v-if="form.permission.buy_is_level == 1">
      <el-checkbox-group v-model="form.permission.buy_level_ids">
        <el-checkbox :label="level.id" v-for="level in validLevelList" :key="level.id">
          {{ level.name }}
        </el-checkbox>
      </el-checkbox-group>
    </el-form-item>
  </div>
</template>

<script>
export default {
  name: "TabPermission",
  props: {
    form: {
      type: Object,
      required: true
    },
    levelList: {
      type: Array,
      default: () => []
    }
  },
  computed: {
    // 过滤掉 ID 为 0 的普通用户（通常权限设置针对的是升级后的会员）
    validLevelList() {
      return this.levelList.filter(item => item.id !== '0');
    }
  }
}
</script>
