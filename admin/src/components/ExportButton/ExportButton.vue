<template>
  <div style="display: inline-block;">
    <!-- 组件的主体就是一个按钮 -->
    <el-button
      :type="buttonType"
      :icon="buttonIcon"
      @click="openExportDialog"
      :loading="loading"
    >
      {{ buttonText }}
    </el-button>

    <!-- 内部管理的弹窗 -->
    <el-dialog title="选择导出字段" :visible.sync="dialogVisible" width="600px">
      <el-checkbox :indeterminate="isIndeterminate" v-model="checkAll" @change="handleCheckAllChange">全选</el-checkbox>
      <div style="margin: 15px 0;"></div>
      <el-checkbox-group v-model="checkedExportFields" @change="handleCheckedFieldsChange">
        <el-checkbox v-for="field in allExportFields" :label="field.prop" :key="field.prop" style="margin-bottom: 10px; width: 150px;">
          {{ field.label }}
        </el-checkbox>
      </el-checkbox-group>
      <span slot="footer" class="dialog-footer">
        <el-button @click="dialogVisible = false">取 消</el-button>
        <el-button type="primary" @click="confirmExport" :loading="loading">确 定 导 出</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'ExportButton',
  props: {
    // 按钮文本
    buttonText: {
      type: String,
      default: '导出'
    },
    // 按钮类型
    buttonType: {
      type: String,
      default: 'primary'
    },
    // 按钮图标
    buttonIcon: {
      type: String,
      default: 'el-icon-download'
    },
    // 【核心】所有可选的字段定义
    allExportFields: {
      type: Array,
      required: true,
      validator: (arr) => arr.every(item => 'label' in item && 'prop' in item)
    },
    // 【核心】获取数据的API地址
    apiUrl: {
      type: String,
      required: true
    },
    // 【核心】随API请求发送的参数（通常是搜索表单）
    apiParams: {
      type: Object,
      default: () => ({})
    },
    // 导出的文件名
    fileName: {
      type: String,
      default: '导出文件'
    },
    // 【核心】自定义数据格式化函数
    formatData: {
      type: Function,
      default: (list) => list // 默认不进行任何格式化
    }
  },
  data() {
    return {
      loading: false,
      dialogVisible: false,
      checkAll: true,
      isIndeterminate: false,
      checkedExportFields: []
    }
  },
  methods: {
    openExportDialog() {
      // 每次打开弹窗时，重置勾选状态为全部选中
      this.checkedExportFields = this.allExportFields.map(field => field.prop);
      this.checkAll = true;
      this.isIndeterminate = false;
      this.dialogVisible = true;
    },

    handleCheckAllChange(val) {
      this.checkedExportFields = val ? this.allExportFields.map(field => field.prop) : [];
      this.isIndeterminate = false;
    },

    handleCheckedFieldsChange(value) {
      const checkedCount = value.length;
      this.checkAll = checkedCount === this.allExportFields.length;
      this.isIndeterminate = checkedCount > 0 && checkedCount < this.allExportFields.length;
    },

    confirmExport() {
      if (this.checkedExportFields.length === 0) {
        this.$message.warning('请至少选择一个要导出的字段！');
        return;
      }
      this.doExport();
    },

    async doExport() {
      this.loading = true;
      this.$message.info('正在生成导出文件，请稍候...');

      // 1. 根据用户选择动态生成 header 和 fields
      const selectedFieldsData = this.allExportFields.filter(field => this.checkedExportFields.includes(field.prop));
      const header = selectedFieldsData.map(item => item.label);
      const fields = selectedFieldsData.map(item => item.prop);

      try {
        // 2. 发起请求获取全部数据
        const res = await this.$request({
          url: this.apiUrl,
          data: {
            ...this.apiParams,
            page: 1,
            list_rows: 99999, // 确保获取所有数据
          }
        });

        if (res.code !== 0) {
          throw new Error(res.msg || '获取导出数据失败');
        }

        let list = res.data.list;

        // 3. 【关键】使用传入的 formatData 函数处理数据
        const formattedList = this.formatData(list);

        // 4. 调用全局导出方法
        this.$export(header, fields, formattedList, this.fileName);

        this.dialogVisible = false;
        this.$emit('success');

      } catch (err) {
        console.error('导出失败', err);
        this.$message.error(err.message || '导出失败，请稍后重试');
        this.$emit('error', err);
      } finally {
        this.loading = false;
      }
    }
  }
}
</script>

<style scoped>
/* 保持组件的行内块特性，方便和其他按钮并排 */
.el-button {
  margin-left: 10px;
}
</style>
