<template>
  <div>
    <el-form-item label="自动完成订单" prop="is_auto_complete">
      <el-switch v-model="form.is_auto_complete" :active-value="1" :inactive-value="0"></el-switch>
      <div class="el-form-item__description" style="color: #909399; font-size: 12px; line-height: 1.5;">
        开启后，用户支付成功即视为订单完成（无需发货环节），适用于虚拟商品、服务权益等。<br/>
        <span style="color: #F56C6C;">注意：此类商品必须单独下单，不可与其他商品合并购买。</span>
      </div>
    </el-form-item>


    <el-form-item label="开启面议" prop="is_negotiable">
      <el-switch v-model="form.is_negotiable" :active-value="1" :inactive-value="0"></el-switch>
    </el-form-item>
    <el-form-item label="上架" prop="status">
      <el-switch v-model="form.status" :active-value="1" :inactive-value="0"></el-switch>
    </el-form-item>
    <el-form-item label="支持退款" prop="is_refund">
      <el-switch v-model="form.is_refund" :active-value="1" :inactive-value="0"></el-switch>
    </el-form-item>
    <el-form-item label="设为推荐" prop="is_recommend">
      <el-switch v-model="form.is_recommend" :active-value="1" :inactive-value="0"></el-switch>
    </el-form-item>

    <el-form-item label="审核通过" prop="is_check">
      <el-switch v-model="form.is_check" :active-value="1" :inactive-value="0"></el-switch>
    </el-form-item>
  </div>
</template>

<script>
export default {
  name: "TabStatus",
  props: {
    form: {
      type: Object,
      required: true
    }
  }
}
</script>
