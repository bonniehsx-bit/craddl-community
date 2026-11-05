<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="card-header-flex">
        <span>批量导入发货</span>
        <div>
          <el-button size="mini" type="success" icon="el-icon-download" @click="downloadTemplate">下载发货模板</el-button>
        </div>
      </div>
      <el-row>
        <el-col :span="24">
          <el-upload
            class="upload-demo"
            action=""
            multiple
            :limit="1"
            :on-change="upload"
            :auto-upload="false"
            :file-list="fileList"
            accept=".xls,.xlsx"
          >
            <el-button size="small" type="primary">点击上传 Excel</el-button>
            <div slot="tip" class="el-upload__tip">
              只能上传 xlsx 文件，且不超过 500kb。请确保包含列：收货地址、快递单号、快递名称。
            </div>
          </el-upload>
          <div style="margin-top: 15px;">
            <el-button type="danger" size="small" :loading="is_loading" @click="confirmSend">核对无误，确认发货</el-button>
          </div>
        </el-col>
      </el-row>

      <div style="margin-top: 20px;">
        <el-table :data="list" border style="width: 100%" v-loading="is_loading" stripe>
          <el-table-column prop="订单ID" label="订单ID" width="100"></el-table-column>
          <el-table-column prop="订单号" label="订单号" width="180"></el-table-column>
          <el-table-column prop="收货人" label="收货人" width="120"></el-table-column>
          <el-table-column prop="电话" label="电话" width="130"></el-table-column>
          <el-table-column prop="收货地址" label="收货地址" min-width="200"></el-table-column>
          <el-table-column prop="快递单号" label="快递单号" width="180"></el-table-column>
          <el-table-column prop="快递名称" label="快递名称" width="150"></el-table-column>
        </el-table>
      </div>
    </el-card>
  </div>
</template>

<script>
// 注意：根据您的引入方式，如果xlsx/xlsx.mjs报错，可以尝试 import * as XLSX from 'xlsx'
import * as XLSX from 'xlsx/xlsx.mjs'

export default {
  name: 'BatchSend',
  data() {
    return {
      is_loading: false,
      fileList: [],
      list: [],
    }
  },
  methods: {
    /**
     * 下载模板逻辑
     * 使用 XLSX 在前端直接生成
     */
    downloadTemplate() {
      // 1. 定义模板表头（对应表格 prop）
      const templateData = [
        {
          "订单ID": "",
          "订单号": "",
          "收货人": "",
          "电话": "",
          "收货地址": "",
          "快递单号": "",
          "快递名称": ""
        }
      ];

      // 2. 创建工作簿和工作表
      const worksheet = XLSX.utils.json_to_sheet(templateData);
      const workbook = XLSX.utils.book_new();

      // 3. 将工作表添加到工作簿
      XLSX.utils.book_append_sheet(workbook, worksheet, "发货模板");

      // 4. 输出并下载
      // writeFile 会自动触发浏览器下载
      XLSX.writeFile(workbook, `发货批量导入模板_${new Date().getTime()}.xlsx`);
    },

    /**
     * 确认发货提交
     */
    confirmSend() {
      if (this.list.length === 0) {
        this.$message.error('请先上传并解析数据');
        return;
      }

      this.is_loading = true;
      this.$request({
        url: "/mall/order/excel-send",
        method: 'post',
        data: {
          excel_data: JSON.stringify(this.list)
        }
      }).then(res => {
        this.is_loading = false;
        if (res.code === 0) {
          this.$message.success(res.msg);
          this.list = [];
          this.fileList = [];
        } else {
          this.$message.error(res.msg);
        }
      }).catch(() => {
        this.is_loading = false;
      });
    },

    /**
     * 上传组件状态改变
     */
    upload(file, fileList) {
      if (file) {
        this.readExcel(file.raw);
      }
    },

    /**
     * 解析 Excel 数据
     */
    readExcel(file) {
      if (!file) return;
      if (!/\.(xls|xlsx)$/.test(file.name.toLowerCase())) {
        this.$message.error('上传格式不正确，请上传 xls 或者 xlsx 格式');
        return false;
      }

      const fileReader = new FileReader();
      fileReader.onload = (ev) => {
        try {
          const data = ev.target.result;
          // 以二进制方式读取
          const workbook = XLSX.read(data, {
            type: 'binary'
          });

          const wsname = workbook.SheetNames[0]; // 取第一张表
          const ws = XLSX.utils.sheet_to_json(workbook.Sheets[wsname]); // 生成 JSON 内容

          if (ws.length === 0) {
            this.$message.warning('表格内容为空');
            return;
          }

          // 简单校验表头（可选）
          const firstRow = ws[0];
          if (!firstRow.hasOwnProperty('快递单号')) {
            this.$message.error('未检测到“快递单号”列，请检查模板是否正确');
            return;
          }

          this.list = ws;
          this.$message.success(`成功解析 ${ws.length} 条数据`);
        } catch (e) {
          console.error(e);
          this.$message.error('解析 Excel 失败，请重试');
        }
      };
      fileReader.readAsBinaryString(file);
    },
  }
}
</script>

<style scoped>
.app-container {
  padding: 20px;
}
.box-card {
  border-radius: 8px;
}
.el-upload__tip {
  color: #f56c6c;
}
</style>
