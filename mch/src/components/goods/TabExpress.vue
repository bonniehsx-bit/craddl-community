<template>
  <div style="height: 700px; overflow-y: auto;">
    <el-form-item label="运费设置" prop="express_type">
      <el-radio-group v-model="form.express_type">
        <el-radio :label="0">包邮</el-radio>
        <el-radio :label="1">统一设置</el-radio>
        <el-radio :label="2">运费模板</el-radio>
      </el-radio-group>

      <div style="margin-top: 15px;">
        <!-- 统一运费 -->
        <el-input v-if="form.express_type == 1"
                  placeholder="设置统一运费"
                  v-model="form.freight_price"
                  style="width: 300px;">
          <template slot="append">元</template>
        </el-input>

        <!-- 运费模板选择 -->
        <el-select v-model="selectedFreight"
                   value-key="id"
                   placeholder="请选择运费模板"
                   v-if="form.express_type == 2"
                   @change="handleFreightChange"
                   style="width: 300px;">
          <el-option v-for="item in freightList"
                     :key="item.id"
                     :label="item.name"
                     :value="item">
          </el-option>
        </el-select>
      </div>
    </el-form-item>
  </div>
</template>

<script>
export default {
  name: "TabExpress",
  props: {
    form: {
      type: Object,
      required: true
    },
    freightList: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      selectedFreight: null
    };
  },
  watch: {
    // 当 form 数据加载回来，或者包含 freight 信息时，回显下拉框
    'form.freight': {
      handler(val) {
        if (val && val.id) {
          this.selectedFreight = val;
          // 确保ID同步
          this.form.freight_id = val.id;
        }
      },
      deep: true,
      immediate: true
    }
  },
  methods: {
    handleFreightChange(item) {
      this.selectedFreight = item;
      // 关键：将选中的 ID 赋值给 form.freight_id，这是后端需要的字段
      this.form.freight_id = item.id;
      // 同时也更新 form.freight 对象，以便后续操作或回显
      this.form.freight = item;
    }
  }
}
</script>
