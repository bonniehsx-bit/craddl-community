<template>
  <div class="mch-filter" style="margin-bottom: 20px;">
    <el-form size="mini" :inline="true" :model="params" @submit.native.prevent="handleSearch">
      <el-form-item label="关键词">
        <el-input v-model="params.keyword" placeholder="商户ID/名称/店长ID/昵称/手机号" clearable style="width: 250px;"></el-input>
      </el-form-item>
      <el-form-item label="商家类型">
        <el-select v-model="params.type" clearable placeholder="请选择类型">
          <el-option label="线上商家" :value="1"></el-option>
          <el-option label="线下门店" :value="2"></el-option>
          <!-- 酒店民宿(type=3) 已废弃，新流程不再创建；保留筛选项以便管理员查找存量数据 -->
          <el-option label="酒店民宿(存量)" :value="3"></el-option>
          <el-option label="个人租户" :value="4"></el-option>
        </el-select>
      </el-form-item>
      <el-form-item label="审核状态">
        <el-select v-model="params.status" clearable placeholder="请选择状态">
          <el-option label="待审核" :value="0"></el-option>
          <el-option label="已通过" :value="1"></el-option>
          <el-option label="已驳回" :value="2"></el-option>
          <el-option label="已禁用" :value="3"></el-option>
        </el-select>
      </el-form-item>
      <el-form-item label="入驻时间">
        <el-date-picker v-model="params.date_range" type="daterange" value-format="yyyy-MM-dd"
                        range-separator="至" start-placeholder="开始日期" end-placeholder="结束日期">
        </el-date-picker>
      </el-form-item>
      <el-form-item>
        <el-button type="primary" icon="el-icon-search" @click="handleSearch">搜索</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<script>
export default {
  name: 'MchFilter',
  props: {
    // 接收父组件传入的参数对象
    value: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      params: this.value
    }
  },
  watch: {
    value: {
      handler(val) {
        this.params = val;
      },
      deep: true
    }
  },
  methods: {
    handleSearch() {
      // 更新父组件的 v-model，并触发搜索
      this.$emit('input', this.params);
      this.$emit('search');
    }
  }
}
</script>
