<template>
  <div class="app-container">
    <el-card>
      <div slot="header">
        <span>用户结算信息管理</span>
      </div>

      <!-- 搜索栏 -->
      <el-form :model="searchParams" ref="searchForm" size="mini" :inline="true" label-width="100px">
        <el-form-item label="提交日期">
          <el-date-picker
            v-model="searchParams.date_range"
            type="daterange"
            value-format="yyyy-MM-dd"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期">
          </el-date-picker>
        </el-form-item>
        <el-form-item label="进件状态">
          <el-select v-model="searchParams.fund_status" placeholder="请选择状态" clearable>
            <el-option label="全部" value=""></el-option>
            <el-option label="处理中" value="pending"></el-option>
            <el-option label="待验证" value="pending_verify"></el-option>
            <el-option label="成功" value="success"></el-option>
            <el-option label="失败" value="failed"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="关键词">
          <el-input v-model="searchParams.keyword" placeholder="用户ID/昵称/姓名/手机号" clearable @keyup.enter.native="search"></el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" icon="el-icon-search" @click="search">搜索</el-button>
          <el-button @click="resetSearch">重置</el-button>
        </el-form-item>
      </el-form>

      <!-- 数据表格 -->
      <el-table :data="list" border v-loading="loading" size="medium" style="width: 100%">

        <!-- 展开行：显示多渠道的详细数据 -->
        <el-table-column type="expand">
          <template slot-scope="props">
            <div class="detail-container">
              <div v-if="props.row.channel_data && Object.keys(props.row.channel_data).length > 0">
                <div v-for="(channelDetails, channelClass) in props.row.channel_data" :key="channelClass" class="channel-detail-block">
                  <el-descriptions class="margin-top" :title="getChannelName(channelClass)" :column="2" border size="small">

                    <el-descriptions-item v-for="(value, key) in channelDetails" :key="key" :label="getSettlementLabel(key)">
                      <!-- 如果值是图片链接，显示预览 -->
                      <template v-if="isImageUrl(value)">
                        <el-image style="width: 80px; height: 80px" :src="value" :preview-src-list="[value]"></el-image>
                      </template>
                      <!-- 否则显示文本 -->
                      <template v-else>
                        {{ value || '-' }}
                      </template>
                    </el-descriptions-item>

                  </el-descriptions>
                </div>
              </div>
              <div v-else>
                <el-tag type="info">该用户暂无渠道专属结算数据</el-tag>
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column prop="id" label="ID" width="80" align="center"></el-table-column>

        <el-table-column label="用户信息" width="250">
          <template slot-scope="scope">
            <div class="user-cell">
              <el-avatar :size="40" :src="scope.row.user_info.avatar_url"></el-avatar>
              <div class="user-details">
                <div>{{ scope.row.user_info.nickname }}</div>
                <div class="user-id">(ID: {{ scope.row.user_id }})</div>
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="基础信息" width="200">
          <template slot-scope="scope">
            <div><strong>姓名:</strong> {{ scope.row.real_name }}</div>
            <div><strong>手机:</strong> {{ scope.row.mobile }}</div>
          </template>
        </el-table-column>

        <!-- 进件状态列：显示所有渠道的状态 -->
        <el-table-column label="渠道进件状态" min-width="300">
          <template slot-scope="scope">
            <div v-if="scope.row.onboarding_status && scope.row.onboarding_status.length > 0">
              <div v-for="status in scope.row.onboarding_status" :key="status.channel_class" class="status-row">
                <span class="channel-name-label">{{ status.channel_name }}:</span>

                <el-tag :type="getStatusTagType(status.status)" size="small" style="margin-right: 5px;">{{ status.status_text }}</el-tag>

                <el-tooltip v-if="status.remark" class="item" effect="dark" :content="status.remark" placement="top">
                  <i class="el-icon-info"></i>
                </el-tooltip>

                <!-- 重置按钮：允许管理员重置状态，让用户重新提交 -->
                <el-button
                  v-if="status.status === 'success' || status.status === 'failed'"
                  type="text"
                  size="mini"
                  class="reset-btn"
                  @click="handleResetStatus(scope.row.id, status.channel_class)">
                  重置
                </el-button>

              </div>
            </div>
            <div v-else>
              <el-tag type="info" size="small">未签约</el-tag>
            </div>
          </template>
        </el-table-column>

        <el-table-column prop="updated_time" label="最后更新" width="160" align="center"></el-table-column>
      </el-table>

      <!-- 分页 -->
      <div style="text-align: right; margin: 20px 0;">
        <el-pagination
          v-if="pagination"
          background
          :current-page.sync="searchParams.page"
          :page-size="searchParams.limit"
          layout="total, prev, pager, next, jumper"
          :total="pagination.total_count"
          @current-change="pageChange"
        ></el-pagination>
      </div>
    </el-card>
  </div>
</template>

<script>
export default {
  name: 'UserSettlementList',
  data() {
    return {
      loading: false,
      list: [],
      pagination: null,
      searchParams: {
        page: 1,
        limit: 20,
        keyword: '',
        fund_status: '',
        date_range: [],
      },
      // 字段翻译字典，用于美化 JSON 数据的显示
      settlementLabels: {
        real_name: '真实姓名',
        identity_no: '身份证号',
        mobile: '手机号',
        bank_account_name: '户名',
        bank_account_no: '银行卡号',
        bank_card_no: '银行卡号',
        bank_name: '开户行',
        bank_code: '银行编码',
        account_type: '账户类型',
        alipay_account: '支付宝账号',
        account: '账号',
        bank_branch_no: '联行号',
        use_desc: '用途',
        id_card_front: '身份证人像面',
        id_card_back: '身份证国徽面'
      }
    };
  },
  created() {
    this.getList();
  },
  methods: {
    // 获取列表
    getList() {
      this.loading = true;
      this.$request({
        url: '/mall/fund/user-settle-list',
        method: 'get',
        data: this.searchParams,
      }).then(res => {
        if (res.code === 0) {
          this.list = res.data.list;
          this.pagination = res.data.pagination;
        } else {
          this.$message.error(res.msg);
        }
      }).finally(() => {
        this.loading = false;
      });
    },
    search() {
      this.searchParams.page = 1;
      this.getList();
    },
    resetSearch() {
      this.searchParams = {
        page: 1,
        limit: 20,
        keyword: '',
        fund_status: '',
        date_range: [],
      };
      this.getList();
    },
    pageChange(page) {
      this.searchParams.page = page;
      this.getList();
    },

    // 状态标签颜色
    getStatusTagType(status) {
      const map = {
        'none': 'info',
        'pending': 'primary',
        'pending_verify': 'warning',
        'success': 'success',
        'failed': 'danger',
      };
      return map[status] || 'info';
    },

    // 获取字段显示名
    getSettlementLabel(key) {
      return this.settlementLabels[key] || key;
    },

    // 判断是否为图片链接
    isImageUrl(value) {
      if (typeof value !== 'string') return false;
      return value.startsWith('http') && /\.(jpeg|jpg|gif|png)$/i.test(value);
    },

    // 辅助：获取渠道名称 (Fallback)
    getChannelName(classPath) {
      // 这里的逻辑主要作为 backup，PHP 接口通常已经返回了 channel_name
      // 如果需要可以在这里做额外的映射
      if (!classPath) return '未知渠道';
      const parts = classPath.split('\\');
      let name = parts[parts.length - 1];
      if (name.includes('Bank')) return '银行卡渠道';
      if (name.includes('Alipay')) return '支付宝渠道';
      if (name.includes('Wechat')) return '微信渠道';
      return name;
    },

    // [操作] 重置用户状态
    handleResetStatus(settlement_id, channel_class) {
      this.$confirm('确定要重置此渠道的状态吗？重置后用户需重新提交或系统将视其为未进件。', '警告', {
        type: 'warning',
        confirmButtonText: '确定重置',
        cancelButtonText: '取消'
      }).then(() => {
        this.loading = true;
        this.$request({
          url: '/mall/fund/user-settle-status-update',
          method: 'post',
          data: {
            user_settlement_id: settlement_id,
            channel_class: channel_class,
            status: 'failed' // 强制置为失败，让用户重新填
          }
        }).then(res => {
          if (res.code === 0) {
            this.$message.success('状态已重置');
            this.getList();
          } else {
            this.$message.error(res.msg);
          }
        }).finally(() => {
          this.loading = false;
        });
      }).catch(() => {});
    }
  },
};
</script>

<style scoped>
.user-cell {
  display: flex;
  align-items: center;
}
.user-details {
  margin-left: 10px;
}
.user-id {
  font-size: 12px;
  color: #999;
}
.status-row {
  display: flex;
  align-items: center;
  margin-bottom: 6px;
  padding-bottom: 6px;
  border-bottom: 1px dashed #f0f0f0;
}
.status-row:last-child {
  margin-bottom: 0;
  padding-bottom: 0;
  border-bottom: none;
}
.channel-name-label {
  font-weight: bold;
  font-size: 13px;
  color: #606266;
  margin-right: 8px;
  min-width: 80px; /* 对齐 */
}
.el-icon-info {
  margin-left: 5px;
  color: #909399;
  cursor: pointer;
}
.detail-container {
  padding: 10px;
  background-color: #fcfcfc;
}
.channel-detail-block {
  margin-bottom: 15px;
  background: #fff;
  padding: 10px;
  border: 1px solid #ebeef5;
  border-radius: 4px;
}
.channel-detail-block:last-child {
  margin-bottom: 0;
}
.reset-btn {
  margin-left: auto; /* 推到右边 */
  color: #E6A23C;
  padding: 0 5px;
}
.reset-btn:hover {
  color: #faad14;
}
</style>
