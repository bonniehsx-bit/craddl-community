<template>
  <div>



    <el-form-item label="商品重量" prop="weight">
      <el-input v-model="form.weight" placeholder="请填写重量" type="number" step="0.01"></el-input>
    </el-form-item>
    <el-form-item label="总库存" prop="total_stock">
      <el-input type="number" v-model="form.total_stock"></el-input>
    </el-form-item>
    <el-form-item label="当前库存" prop="stock">
      <el-input type="number" v-model="form.stock" :disabled="form.is_attr == 1"></el-input>
    </el-form-item>
    <el-form-item label="库存预警" prop="warning_stock">
      <el-input type="number" v-model="form.warning_stock"></el-input>
    </el-form-item>

  </div>
</template>

<script>
export default {
  name: "TabNumber",
  props: {
    form: {
      type: Object,
      required: true
    }
  }
}
</script>
