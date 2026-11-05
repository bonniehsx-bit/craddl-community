<template>
  <div class="app-container" :class="{ 'is-component': isComponent }">
    <div class="stats-compact-wrapper" v-if="stats">
      <div class="stat-mini-item">
        <div class="s-label">
          商家预计收入
          <el-link type="primary" :underline="false" style="font-size:12px;margin-left:6px;vertical-align:baseline"
                   @click="settleHelpVisible = true">结算说明</el-link>
        </div>
        <div class="s-val text-green">¥{{ stats.total_mch_income || '0.00' }}</div>
      </div>
      <div class="stat-mini-item">
        <div class="s-label">实收汇总</div>
        <div class="s-val text-main">¥{{ stats.total_pay_price || '0.00' }}</div>
      </div>
      <div class="stat-mini-item">
        <div class="s-label">商品总额</div>
        <div class="s-val text-sub">¥{{ stats.total_goods_price || '0.00' }}</div>
      </div>
      <div class="stat-mini-item">
        <div class="s-label">邮费收入</div>
        <div class="s-val text-sub">¥{{ stats.total_express_price || '0.00' }}</div>
      </div>
      <div class="stat-mini-item">
        <div class="s-label">抵扣(积分/自提等)</div>
        <div class="s-val text-orange">-¥{{ (Number(stats.total_integral_cut) +
          Number(stats.total_pickup_discount_price)).toFixed(2) }}</div>
      </div>
    </div>

    <el-card class="box-card table-card" shadow="never">
      <div class="filter-wrapper">
        <el-form size="mini" :inline="true" :model="search" class="search-form" label-width="0" @submit.native.prevent>
          <div class="filter-row" style="flex-wrap: nowrap; overflow-x: auto;">
            <el-form-item style="margin-right: 5px;">
              <el-select v-model="search.keyword_type" placeholder="搜索类型" style="width: 110px;">
                <el-option label="订单号" value="order_no"></el-option>
                <el-option label="用户ID" value="user_id"></el-option>
                <el-option label="商品名称" value="goods_name"></el-option>
                <el-option label="收件人" value="name"></el-option>
                <el-option label="手机号" value="mobile"></el-option>
              </el-select>
            </el-form-item>
            <el-form-item style="margin-right: 5px;">
              <el-input v-model="search.keyword" placeholder="请输入关键词" clearable style="width: 200px;"
                @keyup.enter.native="searchList" @clear="searchList"></el-input>
            </el-form-item>

            <el-form-item style="margin-right: 5px;">
              <div class="custom-input-group" style="display: flex; align-items: center;">
                <el-select v-model="search.search_date_type" class="group-select"
                  style="width: 90px; margin-right: -1px; z-index: 2;" @change="searchList">
                  <el-option label="下单" value="created_at"></el-option>
                  <el-option label="支付" value="pay_at"></el-option>
                  <el-option label="完成" value="finish_at"></el-option>
                </el-select>
                <el-date-picker v-model="search.search_date" type="daterange" value-format="yyyy-MM-dd"
                  range-separator="-" start-placeholder="开始" end-placeholder="结束" class="group-picker"
                  style="width: 200px;" :picker-options="pickerOptions" @change="searchList" />
              </div>
            </el-form-item>

            <el-form-item style="margin-right: 5px;">
              <el-select v-model="search.plugin" placeholder="所属业务" clearable style="width: 100px;"
                @change="searchList">
                <el-option label="全部基础" value=""></el-option>
                <el-option label="拼团订单" value="group"></el-option>
                <el-option label="宇达订单" value="yuda"></el-option>
              </el-select>
            </el-form-item>

            <el-form-item style="margin-right: 5px;">
              <el-select v-model="search.type" placeholder="常规场景" clearable style="width: 100px;" @change="searchList">
                <el-option label="普通发货" value="normal"></el-option>
                <el-option label="前端收银" value="cashier"></el-option>
                <el-option label="积分当面付" value="integral"></el-option>
              </el-select>
            </el-form-item>

            <el-form-item style="margin-right: 5px;">
              <el-select v-model="search.deliver_type" placeholder="配送方式" clearable style="width: 100px;"
                @change="searchList">
                <el-option label="快递" value="express"></el-option>
                <el-option label="核销" value="pickup"></el-option>
                <el-option label="同城" value="city"></el-option>
              </el-select>
            </el-form-item>

            <el-form-item style="margin-right: 10px;">
              <el-select v-model="search.payment" placeholder="支付网关" clearable style="width: 100px;"
                @change="searchList">
                <el-option v-for="item in payment_list" :key="item.value" :label="item.label"
                  :value="item.value"></el-option>
              </el-select>
            </el-form-item>

            <el-form-item class="action-btn-group" style="margin-right: 0; flex-shrink: 0;">
              <el-button type="primary" icon="el-icon-search" @click="searchList" circle></el-button>
              <el-button icon="el-icon-refresh" @click="resetSearch" circle plain></el-button>
              <el-button type="text" icon="el-icon-download" @click="openExportDialog" class="export-btn"
                style="margin-left: 5px;">导出</el-button>
            </el-form-item>
          </div>
        </el-form>
      </div>

      <el-tabs v-model="activeStatus" @tab-click="handleTabClick" class="custom-tabs">
        <el-tab-pane label="全部" name="-1"></el-tab-pane>
        <el-tab-pane label="待付款" name="10"></el-tab-pane>
        <el-tab-pane label="待发货" name="20"></el-tab-pane>
        <el-tab-pane label="待收货" name="30"></el-tab-pane>
        <el-tab-pane label="已完成" name="40"></el-tab-pane>
        <el-tab-pane label="已关闭" name="0"></el-tab-pane>
        <el-tab-pane label="售后中" name="refund"></el-tab-pane>
      </el-tabs>

      <div class="table-flex-container">
        <el-table v-loading="loading" :data="list" border stripe height="100%" size="small" class="order-table"
          @row-dblclick="handleRowDblclick" @selection-change="handleSelectionChange" :row-key="getRowKey"
          :header-cell-style="{ background: '#F5F7FA', color: '#606266', fontWeight: '600', height: '40px', padding: '6px 0', fontSize: '13px' }"
          :cell-style="{ padding: '6px 0', fontSize: '13px' }">

          <el-table-column type="selection" :reserve-selection="true" width="55" align="center"></el-table-column>
          <el-table-column width="80" align="center" prop="id" label="ID"></el-table-column>

          <el-table-column label="订单信息" width="220">
            <template slot-scope="scope">
              <div class="info-wrapper">
                <div class="order-no-row">
                  <el-button type="text" size="mini" class="order-no" style="padding: 0; text-align: left;"
                    @click="copyText(scope.row.order_no, '订单号')" title="点击复制">{{ scope.row.order_no }}</el-button>
                </div>
                <div class="meta-row">
                  <span class="shop-badge">客户订单</span>
                  <span class="time">{{ formatTimeShort(scope.row.created_time || scope.row.created_at) }}</span>
                </div>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="商品明细" min-width="240">
            <template slot-scope="scope">
              <div class="goods-list-container custom-scrollbar">
                <div v-for="(item, index) in scope.row.orderDetails" :key="index" class="goods-item">
                  <el-image :src="item.goods.cover_pic" class="goods-thumb" fit="cover">
                    <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
                  </el-image>
                  <div class="goods-detail">
                    <div class="g-name text-ellipsis" :title="item.goods.name">{{ item.goods.name }}</div>
                    <div class="g-spec text-ellipsis" v-if="item.attr">
                      <span v-for="(spec, si) in item.attr.attr_list" :key="si" style="margin-right:4px;">
                        {{ spec.attr_group_name }}: {{ spec.attr_name }}
                      </span>
                    </div>
                  </div>
                  <div class="goods-price-num">
                    <span class="price">¥{{ item.unit_price || item.price }}</span>
                    <span class="num">x{{ item.num }}</span>
                  </div>
                </div>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="用户信息" width="220">
            <template slot-scope="scope">
              <div class="user-wrapper" v-if="scope.row.user">
                <el-avatar :size="30" :src="scope.row.user.avatar_url" shape="circle" icon="el-icon-user-solid"
                  style="flex-shrink:0"></el-avatar>
                <div class="u-info">
                  <div class="u-row-1">
                    <span class="u-name text-ellipsis">{{ scope.row.user.nickname }}</span>
                  </div>
                  <div class="u-row-2">
                    <span class="u-id">ID:
                      <el-button type="text" size="mini" style="padding: 0; font-size: 12px; color: #999;"
                        @click="copyText(scope.row.user_id, '买家ID')" title="点击复制">{{ scope.row.user_id }}</el-button>
                    </span>
                    <el-tooltip v-if="scope.row.words" :content="scope.row.words" placement="top">
                      <i class="el-icon-chat-dot-round remark-icon"></i>
                    </el-tooltip>
                  </div>
                </div>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="实付金额" width="140" align="center">
            <template slot-scope="scope">
              <div class="price-display">
                <div
                  v-if="Number(scope.row.total_pay_price || scope.row.pay_price) > 0 && Number(scope.row.use_integral) > 0">
                  <span class="cash">¥{{ scope.row.total_pay_price || scope.row.pay_price }}</span>
                  <span class="plus">+</span>
                  <span class="point">{{ scope.row.use_integral }}积分</span>
                </div>
                <div
                  v-else-if="Number(scope.row.total_pay_price || scope.row.pay_price) == 0 && Number(scope.row.use_integral) > 0">
                  <span class="point">{{ scope.row.use_integral }}积分</span>
                </div>
                <div v-else>
                  <span class="cash">¥{{ scope.row.total_pay_price || scope.row.pay_price }}</span>
                </div>
                <div class="express-txt">
                  (含运费:¥{{ scope.row.express_price }})
                </div>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="支付" width="80" align="center">
            <template slot-scope="scope">
              <div class="pay-text">
                {{ scope.row.payment_name || '其他' }}
              </div>
            </template>
          </el-table-column>

          <el-table-column label="状态" width="100" align="center">
            <template slot-scope="scope">
              <el-tag :type="getStatusType(scope.row)" effect="plain" size="mini"
                style="border:none; padding:0 4px; margin-bottom: 3px;">
                {{ getStatusText(scope.row) }}
              </el-tag>

              <div v-if="scope.row.refund_status > 0">
                <el-tag type="danger" effect="dark" size="mini" style="border:none; padding:0 4px;">内含售后</el-tag>
              </div>
            </template>
          </el-table-column>

          <el-table-column label="发货方式" min-width="180">
            <template slot-scope="scope">
              <div class="deliver-simple">
                <div class="d-row">
                  <el-tag size="mini" :type="getDeliverTypeTag(scope.row.deliver_type)" effect="light" class="d-tag">
                    {{ getDeliverTypeText(scope.row.deliver_type) }}
                  </el-tag>
                  <el-button type="text" size="mini" class="d-name" style="padding: 0;"
                    @click="copyText(scope.row.name, '收件人')" title="点击复制">{{ scope.row.name }}</el-button>
                  <el-button type="text" size="mini" class="d-phone" style="padding: 0;"
                    @click="copyText(scope.row.mobile, '收件手机')" title="点击复制">{{ scope.row.mobile }}</el-button>
                </div>
                <div class="d-address text-ellipsis" :title="scope.row.address"
                  @click="copyText(scope.row.address, '收货地址')" style="cursor: pointer;">
                  {{ scope.row.address }}
                </div>
              </div>
            </template>
          </el-table-column>

          <!-- 商家备注:仅商家可见,不覆盖买家备注(后端写 seller_remarks) -->
          <el-table-column label="商家备注" min-width="150" show-overflow-tooltip>
            <template slot-scope="scope">
              <el-input v-if="editingRemarkId === scope.row.id" v-model="remarkText" size="mini"
                        maxlength="200" show-word-limit ref="remarkInput"
                        @blur="saveRemark(scope.row)" @keyup.enter.native="saveRemark(scope.row)" />
              <span v-else class="remark-cell" @click="startEditRemark(scope.row)">
                {{ scope.row.seller_remarks || '点击添加' }}
              </span>
            </template>
          </el-table-column>

          <el-table-column label="操作" width="100" align="center" fixed="right">
            <template slot-scope="scope">
              <order-trigger :order-id="scope.row.id" @refresh="loadData">
                <el-button type="text" size="mini" style="padding: 0;">详情</el-button>
              </order-trigger>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <div class="pagination-container">
        <el-pagination background small :current-page.sync="search.page" :page-size="search.limit"
          :page-sizes="[10, 20, 50, 100]" layout="total, sizes, prev, pager, next, jumper" :total="total"
          @size-change="handleSizeChange" @current-change="pageChange">
        </el-pagination>
      </div>
    </el-card>

    <el-dialog title="自定义导出字段" :visible.sync="exportDialogVisible" width="550px" append-to-body top="8vh"
      custom-class="styled-export-dialog">
      <div class="export-header-tip">
        <i class="el-icon-info"></i> 请勾选需要导出的数据列，可上下调整顺序
      </div>
      <div class="export-body-styled custom-scrollbar">
        <div v-for="(field, index) in exportFields" :key="field.key" class="field-item-styled"
          :class="{ 'is-active': field.selected }" @click="field.selected = !field.selected">
          <div class="f-left">
            <el-checkbox v-model="field.selected" @click.native.stop></el-checkbox>
            <span class="f-label">{{ field.label }}</span>
          </div>
          <div class="f-right" @click.stop>
            <el-tooltip content="上移" placement="top" :open-delay="800">
              <div class="sort-btn" :class="{ disabled: index === 0 }" @click="moveField(index, -1)">
                <i class="el-icon-arrow-up"></i>
              </div>
            </el-tooltip>
            <el-tooltip content="下移" placement="top" :open-delay="800">
              <div class="sort-btn" :class="{ disabled: index === exportFields.length - 1 }"
                @click="moveField(index, 1)">
                <i class="el-icon-arrow-down"></i>
              </div>
            </el-tooltip>
          </div>
        </div>
      </div>
      <div slot="footer" class="dialog-footer">
        <div class="footer-left">
          <el-checkbox :indeterminate="isIndeterminate" v-model="checkAll" @change="handleCheckAllChange">全选
          </el-checkbox>
        </div>
        <div>
          <el-button size="medium" @click="exportDialogVisible = false">取 消</el-button>
          <el-button size="medium" type="primary" @click="confirmExport" icon="el-icon-download">确认导出</el-button>
        </div>
      </div>
    </el-dialog>


    <el-dialog title="货款什么时候到账" :visible.sync="settleHelpVisible" width="720px" append-to-body top="6vh">
      <el-alert type="info" :closable="false" show-icon class="mb12">
        <div slot="title">
          <b>到店核销的单：核销当场就结算</b>，不用等收货、也不走售后期。<br>
          其余方式（快递 / 同城 / 无需物流）要先走完「确认收货 → 售后期」两步，
          下面是完整流程，对照着看就能知道自己的单卡在哪一步。
        </div>
      </el-alert>

      <el-steps direction="vertical" :active="5" finish-status="success" space="72px">
        <el-step title="① 买家付款">
          <div slot="description" class="sh-d">订单进入<b>待发货</b>。此时货款在平台，尚未计入你的可提现余额。</div>
        </el-step>
        <el-step title="② 你发货 / 买家取货">
          <div slot="description" class="sh-d">
            不同配送方式这一步差别很大:<br>
            · <b>快递配送</b> — 你填单号发货，订单转「待收货」，继续走 ③④<br>
            · <b>同城配送</b> — 骑手送达，订单转「待收货」，继续走 ③④<br>
            · <b>无需物流</b> — 虚拟商品，付款后直接进入 ③<br>
            · <span class="sh-ok"><b>到店核销</b> — 买家到店你扫码核销，订单<b>直接完成并当场结算</b>，
              跳过下面的 ③④ 两步</span>
          </div>
        </el-step>
        <el-step title="③ 确认收货">
          <div slot="description" class="sh-d">
            买家主动确认，<b>或</b>发货后满 {{ settleHelp.confirmDay }} 天系统自动确认。<br>
            <span class="sh-warn">这一步最常卡住 —— 买家不点确认，就得等自动确认到期。</span><br>
            <span class="sh-mute">（到店核销的单不经过这一步）</span>
          </div>
        </el-step>
        <el-step title="④ 售后期">
          <div slot="description" class="sh-d">
            确认收货后还要再等 <b>{{ settleHelp.settleDay }} 天</b>售后期，期间买家仍可申请退款，所以不能先把钱结给你。<br>
            <span class="sh-mute">（到店核销的单不经过这一步）</span>
          </div>
        </el-step>
        <el-step title="⑤ 结算到账">
          <div slot="description" class="sh-d">
            售后期一过，系统自动把<b>货款 + 运费</b>结算到你的账户，订单状态变「已结算」，随后即可提现。<br>
            结算任务<b>每分钟</b>扫一次，不需要人工催。
          </div>
        </el-step>
      </el-steps>

      <el-divider content-position="left">还没结算？先对照这几条</el-divider>
      <ul class="sh-list">
        <li><b>还没确认收货</b> — 最常见。等买家确认，或等自动确认到期。</li>
        <li><b>刚确认收货</b> — 售后期还没过，属正常等待。</li>
        <li><b>有退款/售后在处理</b> — 结算会等售后结束，避免钱结了又要退。</li>
        <li><b>到店核销的单还没核销</b> — 没核销就不算交易完成；<b>一旦核销即刻结算</b>，无需等待。</li>
        <li><b>订单已关闭</b> — 不会结算，状态显示为「无需结算」。</li>
      </ul>
      <div class="sh-tip">
        以上时长由平台统一设置，如与实际不符请联系平台运营核对。
      </div>

      <div slot="footer">
        <el-button type="primary" @click="settleHelpVisible = false">我知道了</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'MchOrderList',
  props: {
    isComponent: { type: Boolean, default: false }
  },
  data() {
    return {
      loading: false,
      multipleSelection: [],
      list: [],
      search: {
        page: 1,
        limit: 20,
        status: '-1',
        keyword: '',
        keyword_type: 'order_no',
        search_date_type: 'created_at',
        search_date: [],
        plugin: '',
        type: '',
        deliver_type: '',
        payment: ''
      },
      stats: null,
      editingRemarkId: null,
      remarkText: '',
      settleHelpVisible: false,
      settleHelp: { confirmDay: 7, settleDay: 0 },
      payment_list: [],
      activeStatus: '-1',
      total: 0,
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
      exportDialogVisible: false,
      checkAll: true,
      isIndeterminate: false,
      exportTimer: null,
      exportFields: [
        { key: 'order_no', label: '订单号', selected: true },
        { key: 'transaction_id', label: '交易单号', selected: true },
        { key: 'mch_name', label: '所属商家', selected: true },
        { key: 'user_info', label: '买家信息', selected: true },
        { key: 'name', label: '收货人', selected: true },
        { key: 'mobile', label: '电话', selected: true },
        { key: 'address', label: '收货地址', selected: true },
        { key: 'status_text', label: '订单状态', selected: true },
        { key: 'pay_type', label: '支付方式', selected: true },
        { key: 'created_at', label: '下单时间', selected: true },
        { key: 'pay_at', label: '支付时间', selected: true },
        { key: 'pay_price', label: '实付金额', selected: true },
        { key: 'express_price', label: '运费', selected: true },
        { key: 'goods_name', label: '商品名称', selected: true },
        { key: 'attr', label: '规格', selected: true },
        { key: 'price', label: '单价', selected: true },
        { key: 'num', label: '数量', selected: true },
        { key: 'total_price', label: '商品总价', selected: true },
        { key: 'is_send', label: '发货状态', selected: true },
        { key: 'remarks', label: '订单备注', selected: true }
      ]
    }
  },
  watch: {
    exportFields: {
      handler(val) {
        const checkedCount = val.filter(f => f.selected).length;
        this.checkAll = checkedCount === val.length;
        this.isIndeterminate = checkedCount > 0 && checkedCount < val.length;
      },
      deep: true
    }
  },
  created() {
    this.loadFieldPreference();
    this.loadData();
  },
  beforeDestroy() {
    if (this.exportTimer) clearInterval(this.exportTimer)
  },
  methods: {
    // --- 导出逻辑 ---
    openExportDialog() {
      this.exportDialogVisible = true;
    },
    handleCheckAllChange(val) {
      this.exportFields.forEach(f => f.selected = val);
      this.isIndeterminate = false;
    },
    moveField(index, direction) {
      const target = index + direction;
      if (target < 0 || target >= this.exportFields.length) return;
      const temp = this.exportFields[index];
      this.$set(this.exportFields, index, this.exportFields[target]);
      this.$set(this.exportFields, target, temp);
      this.saveFieldPreference();
    },
    saveFieldPreference() {
      localStorage.setItem('mch_order_export_styled', JSON.stringify(this.exportFields));
    },
    loadFieldPreference() {
      const saved = localStorage.getItem('mch_order_export_styled');
      if (saved) {
        try {
          const savedArr = JSON.parse(saved);
          const newFields = savedArr.map(s => {
            const exist = this.exportFields.find(f => f.key === s.key);
            return exist ? { ...exist, selected: s.selected } : null;
          }).filter(Boolean);
          this.exportFields.forEach(f => {
            if (!newFields.find(nf => nf.key === f.key)) newFields.push(f);
          });
          this.exportFields = newFields;
        } catch (e) { }
      }
    },
    confirmExport() {
      const selectedFields = this.exportFields.filter(f => f.selected).map(f => f.key);
      if (!selectedFields.length) return this.$message.warning('请至少选择一个字段');
      this.exportDialogVisible = false;
      this.saveFieldPreference();

      const selectedIds = this.multipleSelection.map(item => item.id);

      this.$confirm(`确认导出 ${selectedIds.length > 0 ? selectedIds.length + ' 条' : '所有筛选条件的'}订单？`, '导出', { type: 'info' }).then(() => {
        const loading = this.$loading({ text: '正在创建任务...', background: 'rgba(255,255,255,0.7)' });
        const data = { ...this.search, export_fields: JSON.stringify(selectedFields) };
        if (selectedIds.length > 0) data.order_ids = JSON.stringify(selectedIds);

        this.$request({ url: '/plugin/mch/admin/order/async-export', method: 'get', data }).then(res => {
          if (res.code === 0) {
            this.pollExportStatus(res.data.token, loading);
          } else {
            loading.close();
            this.$message.error(res.msg);
          }
        }).catch(() => loading.close());
      });
    },
    pollExportStatus(token, loading) {
      if (this.exportTimer) clearInterval(this.exportTimer);
      this.exportTimer = setInterval(() => {
        this.$request({ url: '/plugin/mch/admin/order/export-status', method: 'get', data: { token } }).then(res => {
          if (res.code === 0 && res.data.status === 'success') {
            clearInterval(this.exportTimer);
            loading.close();
            window.location.href = res.data.url;
          } else if (res.code !== 0 || res.data.status === 'failed') {
            clearInterval(this.exportTimer);
            loading.close();
            this.$message.error('导出失败');
          } else if (res.code === 0) {
            loading.text = res.data.msg || '正在生成Excel...';
          }
        }).catch(() => {
          clearInterval(this.exportTimer);
          loading.close();
        });
      }, 1500);
    },


    getRowKey(row) {
      return row.id
    },
    handleSelectionChange(val) {
      this.multipleSelection = val;
    },
    // 搜索
    searchList() {
      this.search.page = 1;
      this.loadData();
    },

    // 重置
    resetSearch() {
      this.search = {
        page: 1,
        limit: 20,
        status: this.activeStatus,
        keyword: '',
        keyword_type: 'order_no',
        search_date_type: 'created_at',
        search_date: [],
        plugin: '',
        type: '',
        deliver_type: '',
        payment: ''
      };
      this.loadData();
    },

    // 切换 Tab
    handleTabClick(tab) {
      this.search.status = tab.name;
      this.search.page = 1;
      this.loadData();
    },

    // 双击表格行打开详情抽屉
    handleRowDblclick(row) {
      if (this.$previewOrder) {
        this.$previewOrder(row.id, () => {
          this.loadData();
        });
      }
    },

    // 加载数据 (核心)
    startEditRemark(row) {
        this.editingRemarkId = row.id
        this.remarkText = row.seller_remarks || ''
        this.$nextTick(() => this.$refs.remarkInput && this.$refs.remarkInput.focus())
    },
    saveRemark(row) {
        const val = this.remarkText
        this.editingRemarkId = null
        if (val === (row.seller_remarks || '')) return
        this.$request({
            url: '/plugin/mch/admin/order/remarks',
            method: 'post',
            data: { order_id: row.id, remarks: val }
        }).then(e => {
            if (e.code === 0) {
                row.seller_remarks = val
                this.$message.success('备注已保存')
            } else {
                this.$message.error(e.msg)
            }
        })
    },
    loadData() {
      this.loading = true;
      this.$request({
        url: '/plugin/mch/admin/order/index',
        method: 'get',
        data: this.search
      }).then(res => {
        this.loading = false;
        if (res.code === 0) {
          this.list = res.data.list;
          this.stats = res.data.stats || null;
          if (res.data.settle_help) {
            this.settleHelp = {
              confirmDay: res.data.settle_help.confirm_day,
              settleDay: res.data.settle_help.settle_day
            };
          }
          this.payment_list = res.data.payment_list || [];
          if (res.data.pagination) {
            this.total = parseInt(res.data.pagination.totalCount || res.data.pagination.total_count || 0);
          }
        } else {
          this.$message.error(res.msg);
        }
      }).catch(err => {
        this.loading = false;
      });
    },

    // 翻页处理
    pageChange(page) {
      this.search.page = page;
      this.loadData();
    },
    handleSizeChange(val) {
      this.search.limit = val;
      this.loadData();
    },

    // 复制单号
    copyText(text, label = '内容') {
      if (!text) return;
      const input = document.createElement('textarea');
      input.value = text;
      input.style.position = 'fixed';
      input.style.opacity = '0';
      document.body.appendChild(input);
      input.select();
      try {
        if (document.execCommand('copy')) {
          this.$message.success(`${label} ${text} 已复制到剪贴板`);
        }
      } catch (err) {
        this.$message.error('浏览器不支持快捷复制，请手动复制');
      }
      document.body.removeChild(input);
    },

    // 跳转详情
    handleDetail(row) {
      this.$router.push({
        path: '/mch/order/detail',
        query: { id: row.id }
      });
    },



    // 导出 (预留)
    exportOrder() {
      this.$message.success('通用导出逻辑已触发，如需增强可接入高级对话框');
    },

    // UI 显示辅助
    getStatusText(o) {
      if (o.refund_status === 10) return "售后待处理";
      if (o.refund_status === 20) return "售后处理中";
      if (o.refund_status === 30) return "售后已完成";

      const map = {
        0: '已关闭',
        10: '待付款',
        20: (o.deliver_type === 'pickup' || o.deliver_type === 'none') ? '待核销' : '待发货',
        30: '待收货',
        40: '已完成'
      }
      return map[o.status] || '-'
    },
    getStatusType(o) {
      if (o.refund_status > 0) return 'danger'
      const map = { 0: 'info', 10: 'danger', 20: 'primary', 30: 'warning', 40: 'success' }
      return map[o.status] || 'info'
    },
    getDeliverTypeText(t) {
      const map = { express: '快递', pickup: '核销订单', city: '同城', none: '无' };
      return map[t] || '未知';
    },
    getDeliverTypeTag(t) {
      return t === 'express' ? 'success' : (t === 'pickup' ? 'warning' : 'primary');
    },
    formatTimeShort(timeStr) {
      if (!timeStr) return '';
      timeStr = String(timeStr);
      // 如果是个纯时间戳数字
      if (!isNaN(timeStr) && timeStr.length === 10) {
        let d = new Date(parseInt(timeStr) * 1000);
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const h = String(d.getHours()).padStart(2, '0');
        const min = String(d.getMinutes()).padStart(2, '0');
        return `${m}-${day} ${h}:${min}`;
      }
      return timeStr.length > 16 ? timeStr.substring(5, 16) : timeStr;
    }
  }
}
</script>

<style lang="scss" scoped>
/* 颜色变量 - 统一白色底风格 */
$primary: #409EFF;
$success: #67C23A;
$warning: #E6A23C;
$danger: #F56C6C;
$info: #909399;
$text-main: #303133;
$text-sub: #606266;
$border-color: #EBEEF5;

.app-container {
  height: calc(100vh - 85px);
  width: 100%;
  background-color: #f0f2f5;
  padding: 15px;
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  overflow: hidden;

  &.is-component {
    height: 100% !important;
    padding: 0 !important;
    background-color: transparent !important;
  }
}

/* 数据看板样式 */
.stats-compact-wrapper {
  flex-shrink: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 12px;
}

.stats-compact-wrapper .stat-mini-item {
  flex: 1;
  min-width: 160px;
  max-width: 220px;
  background: #fff;
  padding: 8px 12px;
  border-radius: 4px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  border: 1px solid #eef0f4;
  display: flex;
  flex-direction: row;
  align-items: center;
  justify-content: space-between;
}

.stats-compact-wrapper .stat-mini-item .s-label {
  font-size: 13px;
  color: #909399;
}

.stats-compact-wrapper .stat-mini-item .s-val {
  font-size: 16px;
  font-weight: bold;
  font-family: 'DIN', sans-serif;
  white-space: nowrap;
}

/* 颜色辅助类 */
.text-blue {
  color: $primary;
}

.text-green {
  color: $success;
}

.text-main {
  color: $text-main;
}

.text-info {
  color: $info;
}

.text-sub {
  color: $text-sub;
}

.text-orange {
  color: $warning;
}

/* 1. 表格卡片 */
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
    min-height: 0;
  }
}

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
    margin-right: 12px;
  }
}

.custom-tabs {
  flex-shrink: 0;
  margin-bottom: 5px;

  ::v-deep .el-tabs__item {
    font-size: 13px;
    font-weight: 500;
    height: 36px;
    line-height: 36px;
  }
}

/* 2. 表格外层弹性自适应 */
.table-flex-container {
  flex: 1;
  min-height: 200px;
  /* 保证有一个底线高度 */
  overflow: hidden;
  /* 让原生 table 接管内部细分滚动 */
  border-radius: 4px;
}

.order-table {
  border-radius: 4px;

  ::v-deep .el-table__body-wrapper {
    overflow-y: auto;

    /* 极致定制滚动条样式 */
    &::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }

    &::-webkit-scrollbar-thumb {
      border-radius: 3px;
      background: rgba(144, 147, 153, 0.3);
    }

    &::-webkit-scrollbar-track {
      border-radius: 3px;
      background: transparent;
    }
  }

  ::v-deep td {
    vertical-align: top;
    /* 表格垂直排版采用置顶，防止信息被挤压 */
  }
}

/* 3. 单元格业务逻辑组件 */
.text-ellipsis {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.info-wrapper {
  .order-no-row {
    margin-bottom: 4px;

    .order-no {
      font-family: Consolas, Monaco, monospace;
      font-size: 13px;
      font-weight: 600;
      color: $text-main;
      transition: color 0.2s;

      &:hover {
        color: $primary;
        text-decoration: underline;
      }
    }
  }

  .meta-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;

    .shop-badge {
      display: inline-block;
      padding: 0 5px;
      background: #f0f9eb;
      border: 1px solid #e1f3d8;
      border-radius: 2px;
      color: $success;
      font-size: 11px;
      line-height: 16px;
    }

    .time {
      font-size: 12px;
      color: $info;
    }
  }
}

.goods-list-container {
  max-height: 120px;
  /* 当商品超过3个左右时开始容器内滑屏 */
  overflow-y: auto;
  padding-right: 4px;

  .goods-item {
    display: flex;
    padding: 6px 0;
    border-bottom: 1px dashed #f0f2f5;

    &:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }

    .goods-thumb {
      width: 44px;
      height: 44px;
      border-radius: 4px;
      border: 1px solid $border-color;
      background-color: #fafafa;
      margin-right: 8px;
      flex-shrink: 0;

      .image-slot {
        display: flex;
        justify-content: center;
        align-items: center;
        width: 100%;
        height: 100%;
        color: #c0c4cc;
        font-size: 16px;
      }
    }

    .goods-detail {
      flex: 1;
      min-width: 0;
      display: flex;
      flex-direction: column;
      justify-content: space-between;

      .g-name {
        font-size: 12px;
        color: $text-main;
        line-height: 1.4;
      }

      .g-spec {
        font-size: 11px;
        color: $info;
        margin-top: 2px;
      }
    }

    .goods-price-num {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      min-width: 60px;
      margin-left: 6px;

      .price {
        font-size: 12px;
        color: $text-main;
        font-family: DIN;
      }

      .num {
        font-size: 11px;
        color: $info;
        margin-top: 2px;
      }
    }
  }
}

.user-wrapper {
  display: flex;
  align-items: flex-start;

  .u-info {
    margin-left: 8px;
    flex: 1;
    min-width: 0;

    .u-row-1 {
      display: flex;
      align-items: center;
      margin-bottom: 2px;

      .u-name {
        font-size: 13px;
        color: $text-main;
        font-weight: 500;
        max-width: 120px;
      }
    }

    .u-row-2 {
      display: flex;
      align-items: center;

      .u-id {
        font-size: 12px;
        color: $info;
      }

      .remark-icon {
        margin-left: 6px;
        color: $primary;
        font-size: 14px;
        cursor: pointer;
        transition: transform 0.2s;

        &:hover {
          transform: scale(1.1);
        }
      }
    }
  }
}

.price-display {
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  height: 100%;

  .cash {
    font-size: 14px;
    font-weight: bold;
    color: $danger;
    font-family: DIN;
  }

  .express-txt {
    font-size: 11px;
    color: $info;
    margin-top: 2px;
  }
}

.deliver-simple {
  .d-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 4px;

    .d-tag {
      border: none;
      padding: 0 4px;
      height: 18px;
      line-height: 18px;
    }

    .d-name {
      font-size: 13px;
      color: $text-main;
      font-weight: 500;

      &:hover {
        color: $primary;
      }
    }

    .d-phone {
      font-size: 12px;
      color: $info;
      font-family: DIN;

      &:hover {
        color: $primary;
      }
    }
  }

  .d-address {
    font-size: 12px;
    color: $text-sub;
    line-height: 1.4;
    transition: color 0.2s;

    &:hover {
      color: $primary;
    }
  }
}

.pagination-container {
  flex-shrink: 0;
  margin-top: 12px;
  padding-top: 12px;
  display: flex;
  justify-content: flex-end;
  border-top: 1px solid #EBEEF5;
}

/* 微小滚动条 */
.custom-scrollbar {
  &::-webkit-scrollbar {
    width: 4px;
  }

  &::-webkit-scrollbar-thumb {
    border-radius: 2px;
    background: rgba(144, 147, 153, 0.3);
  }

  &::-webkit-scrollbar-track {
    border-radius: 2px;
    background: transparent;
  }

  &:hover::-webkit-scrollbar-thumb {
    background: rgba(144, 147, 153, 0.6);
  }
}

/* 导出弹窗 */
.export-body-styled {
  max-height: 380px;
  overflow-y: auto;
  padding: 2px;

  .field-item-styled {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 12px;
    margin-bottom: 6px;
    background: #fff;
    border: 1px solid #ebeef5;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;

    &:hover {
      border-color: #c6e2ff;
      background-color: #ecf5ff;
    }

    &.is-active {
      background-color: #e6f7ff;
      border-color: #91d5ff;

      .f-label {
        color: $primary;
        font-weight: 600;
      }
    }

    .f-label {
      margin-left: 8px;
      font-size: 13px;
      color: #606266;
    }

    .sort-btn {
      width: 24px;
      height: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-left: 6px;
      border-radius: 4px;
      color: #909399;
      cursor: pointer;

      &:hover {
        background: #fff;
        color: $primary;
      }
    }
  }
}

.styled-export-dialog {
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);

  .el-dialog__header {
    background-color: #f8f9fb;
    border-bottom: 1px solid #ebeef5;
    padding: 20px 24px;

    .el-dialog__title {
      font-size: 16px;
      font-weight: 600;
      color: #303133;
    }
  }

  .el-dialog__body {
    padding: 20px 24px;
    background-color: #fff;
  }

  .el-dialog__footer {
    border-top: 1px solid #ebeef5;
    padding: 16px 24px;
    background-color: #fafbfc;
  }

  .export-header-tip {
    font-size: 13px;
    color: #909399;
    background: #fdf6ec;
    border: 1px solid #faecd8;
    padding: 10px 14px;
    border-radius: 4px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;

    i {
      color: #e6a23c;
      font-size: 16px;
      margin-right: 8px;
    }
  }

  .dialog-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
  }
}

.mb12 { margin-bottom: 12px; }
.sh-d { color: #606266; font-size: 13px; line-height: 1.8; }
.sh-warn { color: #E6A23C; }
.sh-ok { color: #67C23A; }
.sh-mute { color: #909399; font-size: 12px; }
.sh-list { margin: 8px 0 0; padding-left: 20px; color: #606266; font-size: 13px; line-height: 2; }
.sh-tip { margin-top: 12px; color: #909399; font-size: 12px; }

.remark-cell { cursor: pointer; color: #606266; display: inline-block; min-width: 60px; }
.remark-cell:hover { color: #409EFF; }
</style>
