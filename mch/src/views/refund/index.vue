<template>
  <div class="app-container">
    <!-- 1. 顶部卡片 (自动占满剩余高度) -->
    <el-card class="box-card table-card" shadow="never">

      <!-- 筛选区域 (固定高度) -->
      <div class="filter-wrapper">
        <el-form size="small" :inline="true" :model="searchParams" class="search-form" label-width="0" @submit.native.prevent>
          <div class="filter-row">
            <el-form-item>
              <el-input
                v-model="searchParams.keyword"
                placeholder="售后单号/订单号/昵称/手机"
                style="width: 240px;"
                clearable
                @keyup.enter.native="handleSearch"
              >
                <i slot="prefix" class="el-input__icon el-icon-search"></i>
              </el-input>
            </el-form-item>

            <el-form-item>
              <el-date-picker
                v-model="searchParams.search_date"
                type="daterange"
                range-separator=":"
                start-placeholder="申请开始"
                end-placeholder="结束"
                value-format="yyyy-MM-dd"
                style="width: 220px;"
                :picker-options="pickerOptions"
              ></el-date-picker>
            </el-form-item>

            <el-form-item class="action-btn-group">
              <el-button type="primary" icon="el-icon-search" @click="handleSearch">搜索</el-button>
              <el-button icon="el-icon-refresh" @click="resetSearch" plain>重置</el-button>
              <el-button type="text" icon="el-icon-download" @click="exportExcel" :loading="exportLoading"
                         class="export-btn">导出
              </el-button>
            </el-form-item>
          </div>
        </el-form>
      </div>

      <!-- Tab区域 (固定高度) -->
      <el-tabs v-model="activeTabName" @tab-click="handleTabClick" class="custom-tabs">
        <el-tab-pane label="全部" name="all"></el-tab-pane>
        <el-tab-pane label="待审核" name="0"></el-tab-pane>
        <el-tab-pane label="处理中" name="1"></el-tab-pane>
        <el-tab-pane label="用户已发货" name="5"></el-tab-pane>
        <el-tab-pane label="退款中" name="6"></el-tab-pane>
        <el-tab-pane label="已完成" name="3"></el-tab-pane>
        <el-tab-pane label="已拒绝" name="2"></el-tab-pane>
        <el-tab-pane label="已关闭" name="4"></el-tab-pane>
      </el-tabs>

      <!-- 表格容器 (自动占满剩余高度) -->
      <div class="table-flex-container">
        <el-table
          :data="list"
          v-loading="loading"
          border
          stripe
          height="100%"
          size="small"
          class="order-table"
          :header-cell-style="{background:'#F5F7FA', color:'#606266', fontWeight:'600', height:'36px', padding:'4px 0'}"
          :cell-style="{padding: '4px 0'}"
        >
          <!-- 商品信息：紧凑双行 -->
          <el-table-column label="商品信息" min-width="260">
            <template slot-scope="scope">
              <div class="goods-item-compact">
                <el-image
                  class="g-thumb"
                  :src="scope.row.cover_pic || scope.row.goods_cover"
                  :preview-src-list="[scope.row.cover_pic || scope.row.goods_cover]"
                >
                  <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
                </el-image>
                <div class="g-info">
                  <!-- Line 1: 商品名称 -->
                  <div class="g-name text-ellipsis" :title="scope.row.goods_name">{{ scope.row.goods_name }}</div>
                  <!-- Line 2: 规格 + 数量 + 原订单链接 -->
                  <div class="g-meta text-ellipsis">
                    <span class="spec-text">{{ scope.row.attr_text }} x{{ scope.row.num }}</span>
                    <el-divider direction="vertical"></el-divider>
                    <order-trigger :order-id="scope.row.order_id || scope.row.id">
                       <span class="link-text">
                      原单<i class="el-icon-right"></i>
                    </span>
                    </order-trigger>
                  </div>
                </div>
              </div>
            </template>
          </el-table-column>

          <!-- 用户信息：紧凑双行 -->
          <el-table-column label="用户信息" width="250">
            <template slot-scope="scope">
              <div class="user-wrapper" v-if="scope.row.user || scope.row.nickname">
                <el-avatar :size="30" :src="scope.row.user ? scope.row.user.avatar_url : scope.row.avatar_url" shape="circle" icon="el-icon-user-solid"
                           class="u-avatar"></el-avatar>
                <div class="u-info">
                  <div class="u-name text-ellipsis" :title="scope.row.user ? scope.row.user.nickname : scope.row.nickname">{{ scope.row.user ? scope.row.user.nickname : scope.row.nickname }}</div>
                  <div class="u-contact text-ellipsis" :title="scope.row.mobile || '无联系方式'">
                    {{ scope.row.mobile || scope.row.real_name || '-' }}
                  </div>
                </div>
              </div>
            </template>
          </el-table-column>

          <!-- 类型/金额 -->
          <el-table-column label="类型/金额" width="100" align="center">
            <template slot-scope="scope">
              <div class="type-row">
                <el-tag effect="plain" size="mini" :type="getRefundTypeTag(scope.row.type)"
                        style="border:none; padding:0;">
                  {{ getRefundTypeText(scope.row.type) }}
                </el-tag>
              </div>
              <div class="price-row">¥{{ scope.row.refund_price }}</div>
            </template>
          </el-table-column>

          <!-- 原因/凭证：双行 -->
          <el-table-column label="原因/凭证" width="180">
            <template slot-scope="scope">
              <div class="reason-compact">
                <!-- Line 1: 原因文字 -->
                <div class="r-text text-ellipsis" :title="scope.row.reason || scope.row.content">
                  <span class="label">原因:</span>{{ scope.row.reason || scope.row.content || '未填写' }}
                </div>
                <!-- Line 2: 图片列表 (限制高度) -->
                <div class="r-pics" v-if="scope.row.pic_list && scope.row.pic_list.length">
                  <el-image
                    v-for="(img, idx) in scope.row.pic_list.slice(0, 4)"
                    :key="idx"
                    :src="img"
                    class="pic-thumb"
                    :preview-src-list="scope.row.pic_list"
                  ></el-image>
                  <span v-if="scope.row.pic_list.length > 4"
                        class="pic-more">+{{ scope.row.pic_list.length - 4 }}</span>
                </div>
              </div>
            </template>
          </el-table-column>

          <!-- 状态/物流：双行 -->
          <el-table-column label="状态/进度" min-width="200">
            <template slot-scope="scope">
              <div class="status-compact">
                <!-- Line 1: 状态标签 + 时间 -->
                <div class="s-row">
                  <el-tag :type="getStatusTag(scope.row.status)" size="mini" effect="dark" class="s-tag">
                    {{ getStatusText(scope.row.status) }}
                  </el-tag>
                  <span v-if="scope.row.is_refund == 1" class="s-sub-tag success">已打款</span>
                  <span class="s-time">{{ formatTimeShort(scope.row.created_at_text || scope.row.created_at) }}</span>
                </div>

                <!-- Line 2: 动态信息 -->
                <div class="s-detail-row text-ellipsis">
                  <!-- 退款中:确认打款后由平台的定时任务去退,显示这笔退款现在的进度 -->
                  <template v-if="scope.row.status == 6 && scope.row.refund_task">
                    <el-tooltip placement="top" :content="scope.row.refund_task.text">
                      <span class="info-highlight" :class="taskColor(scope.row.refund_task.state)">
                        <i :class="scope.row.refund_task.attention ? 'el-icon-warning-outline' : 'el-icon-time'"></i> {{ scope.row.refund_task.text }}
                      </span>
                    </el-tooltip>
                  </template>
                  <template v-else-if="scope.row.user_express_no">
                    <el-tooltip placement="top"
                                :content="`快递: ${scope.row.user_express} 单号: ${scope.row.user_express_no}`">
                      <span class="info-highlight blue">
                        <i class="el-icon-truck"></i> {{ scope.row.user_express }} {{ scope.row.user_express_no }}
                      </span>
                    </el-tooltip>
                    <i class="el-icon-document-copy copy-btn" v-clipboard:copy="scope.row.user_express_no"></i>
                  </template>
                  <template
                    v-else-if="scope.row.status == 1 && scope.row.return_address && (scope.row.type == 2 || scope.row.type == 3)">
                    <el-tooltip placement="top"
                                :content="`寄回: ${scope.row.return_name} ${scope.row.return_mobile} ${scope.row.return_address}`">
                      <span class="info-highlight orange">
                        <i class="el-icon-location-information"></i> 待寄回: {{ scope.row.return_address }}
                      </span>
                    </el-tooltip>
                  </template>
                  <template v-else-if="scope.row.status == 2">
                    <el-tooltip placement="top" :content="`拒绝原因: ${scope.row.remark || '无'}`">
                      <span class="info-highlight red">
                        <i class="el-icon-warning-outline"></i> 拒因: {{ scope.row.remark || '无' }}
                      </span>
                    </el-tooltip>
                  </template>
                  <span v-else class="info-gray">售后单号: {{ scope.row.order_no }}</span>
                </div>
              </div>
            </template>
          </el-table-column>

          <!-- 优化后的操作列 -->
          <el-table-column label="操作" width="160" align="center" fixed="right">
            <template slot-scope="scope">
              <div class="action-wrapper">

                <!-- 场景A：待审核 (显示同意/拒绝) -->
                <template v-if="scope.row.status === 0">
                  <el-button type="text" size="mini" @click="openAgreeDialog(scope.row)">同意</el-button>
                  <el-button type="text" size="mini" class="text-danger" @click="handleReject(scope.row)">拒绝
                  </el-button>
                </template>

                <!-- 场景B：处理中 (动态显示打款或完成换货) -->
                <template v-else-if="isShowProcessAction(scope.row)">
                  <el-popconfirm
                    :title="getProcessActionTitle(scope.row)"
                    @confirm="handleProcessAction(scope.row)"
                  >
                    <el-button
                      slot="reference"
                      type="text"
                      size="mini"
                      :class="getProcessActionClass(scope.row)"
                    >
                      {{ getProcessActionText(scope.row) }}
                    </el-button>
                  </el-popconfirm>
                </template>

                <!-- 场景C：通用详情 (始终显示，用分割线隔开) -->
                <div class="divider" v-if="scope.row.status === 0 || isShowProcessAction(scope.row)"></div>
                <order-trigger :order-id="scope.row.order_id || scope.row.id">
                  <el-button type="text" size="mini" class="text-info">详情</el-button>
                </order-trigger>

              </div>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <!-- 分页 (固定底部) -->
      <div class="pagination-container">
        <el-pagination
          v-if="pagination.total_count > 0"
          background
          small
          @current-change="handlePageChange"
          :current-page="pagination.current_page"
          :page-size="pagination.pageSize"
          layout="total, prev, pager, next, jumper"
          :total="pagination.total_count">
        </el-pagination>
      </div>
    </el-card>

    <!-- 同意售后弹窗 -->
    <el-dialog title="同意售后" :visible.sync="dialog.agreeVisible" width="450px" append-to-body top="15vh"
               custom-class="compact-dialog">
      <el-form :model="agreeForm" :rules="agreeRules" ref="agreeForm" label-width="80px" size="small">
        <div v-if="agreeForm.type !== 1">
          <div class="dialog-tip-box info">
            <i class="el-icon-info"></i> 用户需寄回商品，请确认收货地址
          </div>
          <el-form-item label="收货人" prop="return_name">
            <el-input v-model="agreeForm.return_name" placeholder="姓名"></el-input>
          </el-form-item>
          <el-form-item label="电话" prop="return_mobile">
            <el-input v-model="agreeForm.return_mobile" placeholder="电话"></el-input>
          </el-form-item>
          <el-form-item label="地址" prop="return_address">
            <el-input type="textarea" :rows="2" v-model="agreeForm.return_address" placeholder="详细地址"></el-input>
          </el-form-item>
        </div>
        <div v-else>
          <div class="dialog-tip-box warning">
            <i class="el-icon-warning"></i> 仅退款申请，同意后请手动点击“确认打款”
          </div>
        </div>
      </el-form>
      <span slot="footer" class="dialog-footer">
        <el-button size="small" @click="dialog.agreeVisible = false">取消</el-button>
        <el-button size="small" type="primary" :loading="dialog.loading" @click="submitAgree">确定</el-button>
      </span>
    </el-dialog>

  </div>
</template>

<script>
export default {
  name: 'MchRefundList',
  data() {
    return {
      loading: false,
      exportLoading: false,
      activeTabName: 'all',
      list: [],
      pagination: {current_page: 1, pageSize: 20, total_count: 0},

      pickerOptions: {
        shortcuts: [{
          text: '近一周',
          onClick(picker) {
            const end = new Date();
            const start = new Date();
            start.setTime(start.getTime() - 3600 * 1000 * 24 * 7);
            picker.$emit('pick', [start, end]);
          }
        }]
      },

      searchParams: {
        page: 1,
        status: 'all',
        keyword: '',
        search_date: []
      },

      dialog: {agreeVisible: false, loading: false},
      agreeForm: {
        id: null,
        type: 0,
        return_name: '',
        return_mobile: '',
        return_address: ''
      },
      agreeRules: {
        return_name: [{required: true, message: '必填', trigger: 'blur'}],
        return_mobile: [{required: true, message: '必填', trigger: 'blur'}],
        return_address: [{required: true, message: '必填', trigger: 'blur'}]
      }
    };
  },
  watch: {
    // 页面被标签页缓存时,再从订单详情跳过来不会重新创建,按新的单号重查
    '$route.query.keyword'(k) {
      if (k && this.$route.path === '/refund/index') {
        this.applyRouteKeyword(k);
        this.handleSearch();
      }
    }
  },
  created() {
    // 从订单详情「去处理售后」跳过来会带上订单号,直接筛出那一单的售后
    this.applyRouteKeyword(this.$route.query.keyword);
    this.getList();
  },
  methods: {
    // 带单号进来就只按单号找:页签回到「全部」、清掉日期,不让之前的筛选把它挡住
    applyRouteKeyword(k) {
      if (!k) return;
      this.searchParams.keyword = String(k);
      this.searchParams.search_date = [];
      this.activeTabName = 'all';
      this.searchParams.status = 'all';
    },
    async getList() {
      this.loading = true;
      let params = JSON.parse(JSON.stringify(this.searchParams));
      if (params.status === 'all') params.status = -1;
      if (params.search_date && params.search_date.length) {
        params.start_at = params.search_date[0];
        params.end_at = params.search_date[1];
      }
      delete params.search_date;

      try {
        const res = await this.$request({url: '/plugin/mch/admin/order/refunds', method: 'get', data: params});
        if (res.code === 0) {
          this.list = res.data.list;
          if (res.data.pagination) {
            this.pagination.total_count = parseInt(res.data.pagination.totalCount || res.data.pagination.total_count || 0);
            this.pagination.current_page = parseInt(res.data.pagination.current_page || 1);
            this.pagination.pageSize = parseInt(res.data.pagination.pageSize || 20);
          }
        }
      } catch (e) {
        console.error(e);
      }
      this.loading = false;
    },

    openAgreeDialog(row) {
      this.agreeForm.id = row.id;
      this.agreeForm.type = parseInt(row.type);
      this.dialog.agreeVisible = true;
    },

    submitAgree() {
      this.$refs.agreeForm.validate(async valid => {
        if (valid) {
          this.dialog.loading = true;
          try {
            const res = await this.$request({
              url: '/plugin/mch/admin/order/refund-action',
              method: 'post',
              data: {
                id: this.agreeForm.id,
                status: 1,
                return_name: this.agreeForm.return_name,
                return_mobile: this.agreeForm.return_mobile,
                return_address: this.agreeForm.return_address
              }
            });
            if (res.code === 0) {
              this.$message.success('操作成功');
              this.dialog.agreeVisible = false;
              this.getList();
            } else {
              this.$message.error(res.msg);
            }
          } finally {
            this.dialog.loading = false;
          }
        }
      });
    },

    handleReject(row) {
      this.$prompt('请输入拒绝原因', '拒绝申请', {
        confirmButtonText: '拒绝',
        cancelButtonText: '取消',
        inputPattern: /\S/,
        inputErrorMessage: '原因不能为空'
      }).then(async ({value}) => {
        const res = await this.$request({
          url: '/plugin/mch/admin/order/refund-action',
          method: 'post',
          data: {id: row.id, status: 2, remark: value}
        });
        if (res.code === 0) {
          this.$message.success('已拒绝');
          this.getList();
        } else {
          this.$message.error(res.msg);
        }
      }).catch(() => {
      });
    },

    async handleRefundPay(row) {
      try {
        const res = await this.$request({url: '/plugin/mch/admin/order/refund-pay', method: 'post', data: {id: row.id}});
        if (res.code === 0) {
          this.$message.success(res.msg || '已发起退款');
          this.getList();
        } else {
          this.$message.error(res.msg);
        }
      } catch (e) {
      }
    },

    async handleExchangeComplete(row) {
      try {
        const res = await this.$request({
          url: '/plugin/mch/admin/order/refund-exchange-complete',
          method: 'post',
          data: {id: row.id}
        });
        if (res.code === 0) {
          this.$message.success('已完成');
          this.getList();
        } else {
          this.$message.error(res.msg);
        }
      } catch (e) {
      }
    },

    // --- 核心优化逻辑：判断显示哪些操作按钮 ---
    isShowProcessAction(row) {
      // 必须是处理中(1)或已发货(5)
      if (![1, 5].includes(parseInt(row.status))) return false;
      const type = parseInt(row.type);
      // 1. 换货类型(3)
      if (type === 3) return true;
      // 2. 仅退款(1)或退货退款(2) 且 未打款(is_refund=0)
      if ((type === 1 || type === 2) && parseInt(row.is_refund) === 0) return true;
      return false;
    },
    getProcessActionText(row) {
      return parseInt(row.type) === 3 ? '完成换货' : '确认打款';
    },
    getProcessActionClass(row) {
      return parseInt(row.type) === 3 ? 'text-success' : 'text-warning';
    },
    getProcessActionTitle(row) {
      const type = parseInt(row.type);
      if (type === 3) return '确认收到退货并已寄出换货商品？';
      if (type === 1) return '确认直接退款给用户？';
      return '确认已收到退货并执行打款？';
    },
    handleProcessAction(row) {
      if (parseInt(row.type) === 3) this.handleExchangeComplete(row);
      else this.handleRefundPay(row);
    },
    // ----------------------------------------

    // 搜索与分页
    handleSearch() {
      this.searchParams.page = 1;
      this.getList();
    },
    resetSearch() {
      this.searchParams.keyword = '';
      this.searchParams.search_date = [];
      this.activeTabName = 'all';
      this.searchParams.status = 'all';
      this.handleSearch();
    },
    handleTabClick(tab) {
      this.searchParams.status = tab.name;
      this.handleSearch();
    },
    handlePageChange(page) {
      this.searchParams.page = page;
      this.getList();
    },
    exportExcel() {
      this.$message.success('导出任务已添加，请稍候再试');
    },

    // UI Helpers
    getRefundTypeTag(type) {
      return {1: 'warning', 2: 'danger', 3: 'success'}[type] || 'info';
    },
    getRefundTypeText(type) {
      return {1: '仅退款', 2: '退货退款', 3: '换货'}[type] || '未知';
    },
    getStatusTag(status) {
      return {0: 'info', 1: 'primary', 2: 'danger', 3: 'success', 4: 'info', 5: 'warning', 6: 'warning'}[status] || 'info';
    },
    taskColor(state) {
      return {processing: 'blue', retrying: 'orange', attention: 'red'}[state] || 'blue';
    },
    getStatusText(status) {
      return {0: '待审核', 1: '处理中', 2: '已拒绝', 3: '已完成', 4: '已关闭', 5: '已发货', 6: '退款中'}[status] || '未知';
    },
    formatTimeShort(str) {
      return str ? str.substring(5, 16) : '';
    }
  }
};
</script>

<style lang="scss" scoped>
/* 全局变量 */
$primary: #409EFF;
$success: #67C23A;
$warning: #E6A23C;
$danger: #F56C6C;
$text-main: #303133;
$text-sub: #606266;
$text-light: #909399;

/* 核心布局 */
.app-container {
  height: calc(100vh - 85px);
  width: 100%;
  background-color: #f0f2f5;
  padding: 15px;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* 卡片容器 */
.table-card {
  flex: 1;
  display: flex;
  flex-direction: column;
  border: none;
  box-shadow: 0 1px 4px rgba(0, 21, 41, 0.05);
  overflow: hidden;
  min-height: 0;

  ::v-deep .el-card__body {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 15px;
    box-sizing: border-box;
    overflow: hidden;
  }
}

/* 筛选区 */
.filter-wrapper {
  flex-shrink: 0;
  margin-bottom: 10px;
  padding-bottom: 5px;
  border-bottom: 1px dashed #f0f0f0;

  .filter-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
  }

  .el-form-item {
    margin-bottom: 8px;
    margin-right: 10px;
  }

  .export-btn {
    margin-left: auto;
    color: $success;
  }
}

/* Tabs */
.custom-tabs {
  flex-shrink: 0;
  margin-bottom: 5px;

  ::v-deep .el-tabs__item {
    font-size: 13px;
    height: 36px;
    line-height: 36px;
  }

  ::v-deep .el-tabs__nav-wrap::after {
    background-color: #f2f2f2;
    height: 1px;
  }
}

/* 表格容器 */
.table-flex-container {
  flex: 1;
  overflow: hidden;
  position: relative;
}

/* 表格内部样式 */
.order-table {
  /* 商品紧凑模式 */
  .goods-item-compact {
    display: flex;
    align-items: center;

    .g-thumb {
      width: 36px;
      height: 36px;
      border-radius: 3px;
      border: 1px solid #f0f0f0;
      margin-right: 8px;
      flex-shrink: 0;
    }

    .g-info {
      flex: 1;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .g-name {
      font-size: 12px;
      color: $text-main;
      line-height: 1.3;
      margin-bottom: 2px;
    }

    .g-meta {
      font-size: 12px;
      color: $text-light;
      transform: scale(0.95);
      transform-origin: left center;
      line-height: 1.2;

      .spec-text {
        margin-right: 5px;
      }

      .link-text {
        color: $primary;
        cursor: pointer;

        &:hover {
          text-decoration: underline;
        }
      }
    }
  }

  /* 用户紧凑模式 */
  .user-wrapper {
    display: flex;
    align-items: center;

    .u-avatar {
      flex-shrink: 0;
      margin-right: 6px;
    }

    .u-info {
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }

    .u-name {
      font-size: 12px;
      font-weight: 500;
      color: $text-main;
      line-height: 1.3;
    }

    .u-contact {
      font-size: 12px;
      color: $text-light;
      transform: scale(0.95);
      transform-origin: left center;
    }
  }

  /* 类型金额 */
  .type-row {
    margin-bottom: 2px;
  }

  .price-row {
    font-weight: bold;
    font-size: 13px;
    color: $danger;
    font-family: 'DIN', sans-serif;
  }

  /* 原因/凭证 */
  .reason-compact {
    .r-text {
      font-size: 12px;
      color: $text-sub;
      margin-bottom: 3px;

      .label {
        color: $text-light;
        margin-right: 2px;
      }
    }

    .r-pics {
      display: flex;

      .pic-thumb {
        width: 20px;
        height: 20px;
        margin-right: 3px;
        border-radius: 2px;
        border: 1px solid #eee;
        cursor: pointer;
      }

      .pic-more {
        font-size: 10px;
        color: $text-light;
        line-height: 20px;
      }
    }
  }

  /* 状态/物流 */
  .status-compact {
    .s-row {
      display: flex;
      align-items: center;
      margin-bottom: 4px;
    }

    .s-tag {
      margin-right: 5px;
      height: 18px;
      line-height: 18px;
      padding: 0 4px;
    }

    .s-sub-tag {
      font-size: 10px;
      padding: 0 2px;
      border-radius: 2px;
      margin-right: 5px;

      &.success {
        background: #f0f9eb;
        color: $success;
      }
    }

    .s-time {
      font-size: 11px;
      color: #aaa;
    }

    .s-detail-row {
      font-size: 12px;
      transform: scale(0.95);
      transform-origin: left center;

      .info-highlight {
        display: inline-flex;
        align-items: center;
        padding: 1px 4px;
        border-radius: 2px;

        i {
          margin-right: 3px;
        }

        &.blue {
          background: #ecf5ff;
          color: $primary;
        }

        &.orange {
          background: #fdf6ec;
          color: $warning;
        }

        &.red {
          background: #fef0f0;
          color: $danger;
        }
      }

      .copy-btn {
        margin-left: 4px;
        color: $primary;
        cursor: pointer;
      }

      .info-gray {
        color: #ccc;
      }
    }
  }

  /* 操作栏容器布局 (优化) */
  .action-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px; /* 按钮间距 */

    .divider {
      width: 1px;
      height: 12px;
      background-color: #E4E7ED;
    }

    .text-danger {
      color: $danger;

      &:hover {
        color: #f78989;
      }
    }

    .text-success {
      color: $success;

      &:hover {
        color: #85ce61;
      }
    }

    .text-warning {
      color: $warning;

      &:hover {
        color: #ebb563;
      }
    }

    .text-info {
      color: $text-light;

      &:hover {
        color: #a6a9ad;
      }
    }

    .el-button--text {
      padding: 0;
      font-size: 12px;
    }
  }
}

/* 分页 */
.pagination-container {
  flex-shrink: 0;
  display: flex;
  justify-content: flex-end;
  margin-top: 10px;
  padding-right: 5px;
}

/* 弹窗提示 */
.dialog-tip-box {
  padding: 8px 10px;
  border-radius: 4px;
  font-size: 12px;
  margin-bottom: 15px;

  &.info {
    background: #f4f4f5;
    color: $text-sub;
  }

  &.warning {
    background: #fdf6ec;
    color: $warning;
  }

  i {
    margin-right: 4px;
  }
}

/* 通用工具类 */
.text-ellipsis {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
