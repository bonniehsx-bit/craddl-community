<template>
  <div class="app-container">
    <el-card class="box-card" shadow="never">
      <div slot="header" class="clearfix">
        <span>综合分润日志</span>
        <div style="float: right; font-size: 14px; color: #909399;">
          财务对账核心凭证
        </div>
      </div>

      <div class="search-container">
        <el-form :inline="true" :model="searchForm" size="small" @submit.native.prevent class="search-form">
          <el-form-item label="佣金类型">
            <el-select v-model="searchForm.type" placeholder="全部类型" clearable style="width: 150px;">
              <el-option v-for="item in typeOptions" :key="item.value" :label="item.label" :value="item.value"></el-option>
            </el-select>
          </el-form-item>
          <el-form-item label="结算状态">
            <el-select v-model="searchForm.status" placeholder="全部状态" clearable style="width: 120px;">
              <el-option label="待结算" :value="0"></el-option>
              <el-option label="已结算" :value="1"></el-option>
              <el-option label="已失效" :value="2"></el-option>
            </el-select>
          </el-form-item>
          <el-form-item label="获得者">
            <el-input v-model="searchForm.user_keyword" placeholder="用户ID/昵称" style="width: 180px;"></el-input>
          </el-form-item>
          <el-form-item label="来源订单">
            <el-input v-model="searchForm.keyword" placeholder="请输入订单号" style="width: 180px;"></el-input>
          </el-form-item>
          <el-form-item label="记录时间">
            <el-date-picker
              v-model="searchForm.date_range"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              value-format="yyyy-MM-dd"
              style="width: 260px;">
            </el-date-picker>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" icon="el-icon-search" @click="handleSearch">搜索</el-button>
            <el-button icon="el-icon-refresh-left" @click="resetSearch">重置</el-button>
            <el-button type="success" icon="el-icon-download" @click="handleExport" :loading="exportLoading">导出报表</el-button>
          </el-form-item>
        </el-form>
      </div>

      <el-table
        :data="list"
        v-loading="loading"
        border
        stripe
        highlight-current-row
        style="width: 100%"
        :header-cell-style="{background:'#F5F7FA',color:'#606266'}">

        <el-table-column type="expand">
          <template slot-scope="props">
            <el-form label-position="left" inline class="demo-table-expand">
              <el-form-item label="分佣备注:">
                <span>{{ props.row.remark || '无' }}</span>
              </el-form-item>
              <el-form-item label="交易原额:" v-if="props.row.calculation_price">
                <span>¥{{ props.row.calculation_price }}</span>
              </el-form-item>
              <el-form-item :label="(getExtra(props.row, 'rate_label') || getTypeMeta(props.row.type).rate_label || '佣金比例') + ':'" v-if="props.row.commission_rate">
                <span>{{ props.row.commission_rate }}%（{{ getExtra(props.row, 'rate_desc') || getRateDesc(props.row) }}）</span>
              </el-form-item>
              <el-form-item label="结算时间:">
                <span>{{ props.row.settled_at_text }}</span>
              </el-form-item>
              <el-form-item label="技术调试信息:" style="width: 100%;">
                <pre class="json-code">{{ parseExtraData(props.row.extra_data) }}</pre>
              </el-form-item>
            </el-form>
          </template>
        </el-table-column>

        <el-table-column prop="id" label="流水ID" width="80" align="center"></el-table-column>

        <el-table-column label="业务类型" width="140" align="center">
          <template slot-scope="scope">
            <el-tag effect="light" :type="getTypeMeta(scope.row.type).tag_type || ''">{{ scope.row.type_text }}</el-tag>
          </template>
        </el-table-column>

        <el-table-column label="获得者 (收款人)" min-width="180">
          <template slot-scope="scope">
            <div class="user-cell" v-if="scope.row.user">
              <el-avatar shape="square" :size="36" :src="scope.row.user_avatar"></el-avatar>
              <div class="user-info">
                <div class="nickname">{{ scope.row.user_nickname }}</div>
                <div class="uid">ID: {{ scope.row.user.id }}</div>
              </div>
            </div>
            <span v-else class="text-gray">用户已注销</span>
          </template>
        </el-table-column>

        <el-table-column label="分佣金额" width="140" align="right">
          <template slot-scope="scope">
            <span class="money-text">+{{ parseFloat(scope.row.commission_amount).toFixed(2) }}</span>
          </template>
        </el-table-column>

        <el-table-column label="结算状态" width="100" align="center">
          <template slot-scope="scope">
            <el-tag v-if="scope.row.status === 1" type="success" size="small" effect="dark">已结算</el-tag>
            <el-tag v-else-if="scope.row.status === 0" type="warning" size="small" effect="plain">待结算</el-tag>
            <el-tag v-else type="info" size="small">已失效</el-tag>
          </template>
        </el-table-column>

        <el-table-column label="来源溯源" min-width="220">
          <template slot-scope="scope">
            <div class="source-box">
              <div v-if="scope.row.order_no">
                <span class="label">订单:</span>
                <el-link type="primary" :underline="false" style="font-size: 12px;">{{ scope.row.order_no }}</el-link>
              </div>
              <div v-if="scope.row.buyUser" style="margin-top: 4px;">
                <span class="label">买家:</span>
                <span>{{ scope.row.buyUser.nickname }} (ID:{{scope.row.buyUser.id}})</span>
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="创建时间" prop="created_at_text" width="160" align="center"></el-table-column>
      </el-table>

      <div style="margin-top: 20px; text-align: right;">
        <el-pagination
          v-if="pagination"
          @current-change="handlePageChange"
          :current-page="pagination.current_page"
          :page-size="pagination.pageSize"

          layout="total, prev, pager, next, jumper"
          :total="pagination.total_count">
        </el-pagination>
      </div>
    </el-card>

    <el-dialog
      title="导出进度"
      :visible.sync="exportVisible"
      width="400px"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      :show-close="false"
      append-to-body>
      <div style="text-align: center; padding: 20px 0;">
        <el-progress
          type="circle"
          :percentage="exportPercentage"
          :status="exportStatus === 'success' ? 'success' : (exportStatus === 'failed' ? 'exception' : '')"
        ></el-progress>
        <div style="margin-top: 15px; font-size: 14px; color: #606266;">{{ exportMsg }}</div>
      </div>
      <span slot="footer" class="dialog-footer" v-if="exportStatus === 'success' || exportStatus === 'failed'">
        <el-button type="primary" size="small" @click="exportVisible = false">关闭窗口</el-button>
        <el-button v-if="exportStatus === 'success'" type="success" size="small" @click="downloadFile">下载文件</el-button>
      </span>
    </el-dialog>

  </div>
</template>

<script>
export default {
  name: 'CommissionLog',
  data() {
    return {
      loading: false,
      list: [],
      pagination: null,
      searchForm: {
        keyword: '',
        user_keyword: '',
        type: '',
        page: 1,
        status: '',
        date_range: [],
      },
      typeOptions: [],
      typeMetaMap: {},

      // --- 异步导出相关 ---
      exportLoading: false,
      exportVisible: false,
      exportPercentage: 0,
      exportMsg: '正在准备...',
      exportStatus: 'processing',
      exportToken: '',
      exportFileUrl: '',
      exportTimer: null
    };
  },
  created() {
    this.getList();
  },
  beforeDestroy() {
    this.clearExportTimer();
  },
  methods: {
    // 基础参数组装
    getApiParams() {
      const params = { ...this.searchForm };
      if (!params.date_range) params.date_range = [];
      return params;
    },

    async getList() {
      this.loading = true;
      try {
        const res = await this.$request({
          url: '/mall/finance/commission-log', // 对应列表接口
          method: 'get',
          data: this.getApiParams()
        });
        if (res.code === 0) {
          this.list = res.data.list;
          this.pagination = res.data.pagination;
          this.typeOptions = res.data.type_options || [];
          // 构建 type → meta 映射，供 getTagType/getRateLabel 等方法消费（替代硬编码）
          const map = {};
          this.typeOptions.forEach(opt => { map[opt.value] = opt; });
          this.typeMetaMap = map;
        } else {
          this.$message.error(res.msg);
        }
      } finally {
        this.loading = false;
      }
    },

    handleSearch() {
      this.searchForm.page = 1;
      this.getList();
    },

    resetSearch() {
      this.searchForm = { keyword: '', user_keyword: '', type: '', status: '', date_range: [], page: 1 };
      this.handleSearch();
    },

    handlePageChange(page) {
      this.searchForm.page = page;
      this.getList();
    },

    // 元数据驱动（后端下发，新增类型无需修改前端）
    getTypeMeta(type) {
      return this.typeMetaMap[type] || { tag_type: '', rate_label: '佣金比例', rate_desc_mode: 'commission' };
    },

    // 从 extra_data JSON 中读取字段
    getExtra(row, key) {
      try {
        if (!row.extra_data) return ''
        const obj = typeof row.extra_data === 'string' ? JSON.parse(row.extra_data) : row.extra_data
        return obj[key] || ''
      } catch (e) { return '' }
    },

    // 金额描述：按后端下发的 rate_desc_mode 决定
    //  - platform_fee: 服务费 = 交易原额 - 实际结算
    //  - commission:  直接展示佣金（默认）
    getRateDesc(row) {
      const meta = this.getTypeMeta(row.type);
      if (meta.rate_desc_mode === 'platform_fee') {
        const fee = (parseFloat(row.calculation_price || 0) - parseFloat(row.commission_amount || 0)).toFixed(2);
        return '服务费 ¥' + fee;
      }
      return '佣金 ¥' + row.commission_amount;
    },

    // JSON 解析
    parseExtraData(jsonStr) {
      try {
        if (!jsonStr) return '无';
        const obj = JSON.parse(jsonStr);
        return JSON.stringify(obj, null, 2);
      } catch (e) {
        return jsonStr;
      }
    },

    // --- 异步导出核心逻辑 ---
    async handleExport() {
      try {
        await this.$confirm('确认导出当前筛选条件下的所有记录？数据量较大时请耐心等待。', '导出确认', { type: 'warning' });
        this.exportLoading = true;

        // 1. 请求异步导出接口，获取 Token
        const res = await this.$request({
          url: '/mall/finance/commission-log-async-export', // 触发接口
          method: 'get',
          data: this.getApiParams()
        });

        if (res.code === 0) {
          this.exportToken = res.data.token;
          this.initExportDialog();
          this.startExportPolling();
        } else {
          this.$message.error(res.msg);
        }
      } catch (error) {
        if (error !== 'cancel') {
          this.$message.error('提交导出任务失败');
        }
      } finally {
        this.exportLoading = false;
      }
    },

    initExportDialog() {
      this.exportVisible = true;
      this.exportPercentage = 0;
      this.exportMsg = '正在提交任务...';
      this.exportStatus = 'processing';
      this.exportFileUrl = '';
    },

    startExportPolling() {
      this.clearExportTimer();

      // 每 1.5 秒轮询一次
      this.exportTimer = setInterval(async () => {
        try {
          const res = await this.$request({
            url: '/mall/finance/commission-log-export-status', // 查状态接口
            method: 'get',
            data: { token: this.exportToken }
          });

          if (res.code === 0 && res.data) {
            const data = res.data;
            this.exportPercentage = data.percentage || 0;
            this.exportMsg = data.msg || '正在处理中...';
            this.exportStatus = data.status;

            if (data.status === 'success') {
              this.clearExportTimer();
              this.exportFileUrl = data.url;
              this.$message.success('导出完成！');
              // 自动弹出下载
              window.open(this.exportFileUrl);
            } else if (data.status === 'failed') {
              this.clearExportTimer();
              this.$message.error(data.msg || '导出失败');
            }
          }
        } catch (e) {
          this.clearExportTimer();
          this.exportStatus = 'failed';
          this.exportMsg = '网络请求异常';
        }
      }, 1500);
    },

    clearExportTimer() {
      if (this.exportTimer) {
        clearInterval(this.exportTimer);
        this.exportTimer = null;
      }
    },

    downloadFile() {
      if (this.exportFileUrl) {
        window.open(this.exportFileUrl);
      } else {
        this.$message.warning('文件链接无效');
      }
    }
  },
};
</script>

<style lang="scss" scoped>
/* 搜索区域 */
.search-container {
  background: #f8f8f8;
  padding: 18px 18px 0;
  border-radius: 4px;
  margin-bottom: 20px;
}

/* 用户信息单元格 */
.user-cell {
  display: flex;
  align-items: center;
  .user-info {
    margin-left: 10px;
    line-height: 1.4;
    .nickname {
      font-weight: 500;
      color: #303133;
    }
    .uid {
      font-size: 12px;
      color: #909399;
    }
  }
}

/* 金额样式 */
.money-text {
  color: #F56C6C;
  font-weight: bold;
  font-family: Arial, sans-serif;
  font-size: 15px;
}

/* 来源信息样式 */
.source-box {
  font-size: 13px;
  .label {
    color: #909399;
    margin-right: 4px;
  }
}

/* 展开行样式 */
.demo-table-expand {
  font-size: 0;
  padding: 10px 20px;
}
.demo-table-expand label {
  width: 90px;
  color: #99a9bf;
}
.demo-table-expand .el-form-item {
  margin-right: 0;
  margin-bottom: 0;
  width: 50%;
}

/* JSON 代码块样式 */
.json-code {
  background: #2d2d2d;
  color: #ccc;
  padding: 10px;
  border-radius: 4px;
  font-family: Consolas, Monaco, 'Andale Mono', monospace;
  font-size: 12px;
  max-height: 200px;
  overflow: auto;
  margin-top: 5px;
}

.text-gray {
  color: #C0C4CC;
}
</style>
