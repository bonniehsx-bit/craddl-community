<template>
  <el-drawer :visible.sync="visible" :with-header="false" size="90%" :before-close="handleClose" append-to-body
    custom-class="detail-drawer-wrapper" :destroy-on-close="true">
    <div class="drawer-close-btn" @click="handleClose">
      <i class="el-icon-close"></i>
    </div>

    <div class="ipad-container" v-loading="loading">
      <div v-if="order" class="main-layout">
        <el-row :gutter="15" class="h-100 row-stretch">

          <el-col :span="6" class="col-panel">
            <div class="glass-card status-panel full-h-card">
              <div class="status-header-modern" :class="'status-' + order.status">
                <div class="icon-bubble">
                  <i :class="getStatusIcon(order)"></i>
                </div>
                <div class="status-title">{{ getStatusText(order) }}</div>
                <div class="status-desc">{{ getSettlementStatusDesc(order) }}</div>
              </div>

              <div class="panel-scrollable hide-scrollbar">
                <div class="info-group">
                  <div class="group-title">基础信息</div>
                  <div class="info-item">
                    <span class="label">订单号</span>
                    <span class="value link-style" @click="copyText(order.order_no)">{{ order.order_no }}</span>
                  </div>
                  <div class="info-item">
                    <span class="label">下单时间</span>
                    <span class="value">{{ order.created_at | formatTime }}</span>
                  </div>
                  <div class="info-item">
                    <span class="label">买家</span>
                    <div class="user-capsule" v-if="user">
                      <el-avatar :size="20" :src="user.avatar_url" icon="el-icon-user-solid"></el-avatar>
                      <span class="ml-5 text-ellipsis" style="max-width: 80px;">{{ user.nickname }}</span>
                    </div>
                  </div>
                  <div class="info-item">
                    <span class="label">配送方式</span>
                    <el-tag effect="dark" size="small" :type="getDeliverTypeTag(order.deliver_type)"
                      style="border-radius: 8px;">
                      {{ getDeliverTypeText(order.deliver_type) }}
                    </el-tag>
                  </div>
                  <div class="info-item">
                    <span class="label">支付方式</span>
                    <span class="value">{{ order.payment_name || '未知' }}</span>
                  </div>
                </div>

                <el-divider class="soft-divider"></el-divider>

                <div class="step-wrapper">
                  <div class="group-title">订单进度</div>
                  <el-steps :active="activeStep" :process-status="order.status === 0 ? 'wait' : 'process'" direction="vertical" class="custom-steps">
                    <el-step title="下单">
                      <template slot="description">
                        <div class="step-time">{{ order.created_at | formatTime }}</div>
                      </template>
                    </el-step>

                    <el-step title="付款">
                      <template slot="description">
                        <div class="step-time" v-if="order.pay_at">{{ order.pay_at | formatTime }}</div>
                        <div class="step-time placeholder" v-else>待付款</div>
                      </template>
                    </el-step>

                    <template v-if="order.deliver_type === 'pickup'">
                      <el-step title="待核销">
                        <template slot="description">
                          <div class="step-time" v-if="order.send_at">{{ order.send_at | formatTime }}</div>
                          <div class="step-time placeholder" v-else>等待商家备货</div>
                        </template>
                      </el-step>
                      <el-step title="已自提">
                        <template slot="description">
                          <div class="step-time" v-if="order.finish_at">{{ order.finish_at | formatTime }}</div>
                          <div class="step-time placeholder" v-else>等待提货</div>
                        </template>
                      </el-step>
                    </template>

                    <template v-else>
                      <el-step title="发货">
                        <template slot="description">
                          <div class="step-time" v-if="order.send_at">{{ order.send_at | formatTime }}</div>
                          <div class="step-time placeholder" v-else>等待发货</div>
                        </template>
                      </el-step>
                      <el-step title="完成">
                        <template slot="description">
                          <div class="step-time" v-if="order.finish_at">{{ order.finish_at | formatTime }}</div>
                          <div class="step-time placeholder" v-else>等待收货</div>
                        </template>
                      </el-step>
                    </template>
                  </el-steps>
                </div>
              </div>
            </div>
          </el-col>

          <el-col :span="12" class="col-panel">
            <div class="glass-card goods-panel full-h-card">
              <div class="panel-header">
                <span class="title">商品清单</span>
                <span class="badge">{{ list.length }}件</span>
              </div>

              <div class="panel-scrollable custom-scrollbar">
                <div class="goods-list">
                  <div class="goods-group" v-for="(item, index) in list" :key="index">
                    <div class="goods-item" :class="{ 'interactive': hasBreakdown(item), 'is-voided': Number(item.status) === 2 }" @click="toggleExpand(item)">
                      <el-image :src="item.goods.cover_pic" fit="cover" class="goods-thumb">
                        <div slot="error" class="image-slot"><i class="el-icon-picture-outline"></i></div>
                      </el-image>
                      <div class="goods-details">
                        <div class="goods-main">
                          <div class="name text-ellipsis-2">{{ item.goods.name }}</div>
                          <div class="tags">
                            <span class="spec-tag" v-for="(spec, idx) in item.attr" :key="idx">{{ spec.value ||
                              spec.attr_name
                              }}</span>
                            <span v-if="itemAfterSale(item)" class="after-sale-tag" :class="itemAfterSale(item).cls">{{ itemAfterSale(item).text }}</span>
                          </div>
                        </div>
                        <div class="goods-price-col">
                          <div class="price">¥{{ item.price }}</div>
                          <div class="count">x{{ item.num }}</div>
                        </div>
                      </div>
                      <div class="goods-right-col">
                        <div class="goods-subtotal">¥{{ item.total_price }}</div>
                        <div v-if="hasBreakdown(item)" class="expand-indicator">
                          <span class="expand-text">{{ item._expanded ? '收起明细' : '查看明细' }}</span>
                          <i class="el-icon-arrow-down" :class="{ 'is-active': item._expanded }"></i>
                        </div>
                      </div>
                    </div>
                    <el-collapse-transition>
                      <div class="settlement-box" v-if="item._expanded && hasBreakdown(item)">
                        <div class="settlement-header"><i class="el-icon-s-finance"></i> 结算明细</div>
                        <div class="breakdown-list">
                          <div class="breakdown-row" v-for="(row, rIndex) in item.mch_income_breakdown" :key="rIndex">
                            <div class="bd-label">{{ row.label }} <span v-if="row.note" class="bd-note">({{
                              row.note
                                }})</span></div>
                            <div class="bd-value"
                              :style="{ color: row.color || '#606266', fontWeight: row.is_bold ? 'bold' : 'normal' }">
                              {{ row.value }}
                            </div>
                          </div>
                        </div>
                      </div>
                    </el-collapse-transition>
                  </div>
                </div>
              </div>

              <div class="bill-footer">
                <div class="bill-detail">
                  <div class="bill-row"><span>商品总额</span><span>¥{{ goodsTotalPrice }}</span></div>
                  <div class="bill-row" v-if="order.express_price > 0"><span>运费</span><span>+ ¥{{
                    order.express_price
                      }}</span></div>
                  <div class="bill-row" v-if="Number(order.member_discount_price) > 0"><span>会员折扣让利</span><span
                      class="discount">-
                      ¥{{ order.member_discount_price }}</span></div>
                  <div class="bill-row" v-if="Number(order.coupon_cut_price) > 0"><span>优惠券</span><span
                      class="discount">- ¥{{
                        order.coupon_cut_price }}</span></div>
                  <div class="bill-row" v-if="Number(order.first_order_discount_price) > 0"><span>首单立减</span><span
                      class="discount">- ¥{{
                        order.first_order_discount_price }}</span></div>
                  <div class="bill-row" v-if="Number(order.integral_cut_price) > 0"><span>积分抵扣</span><span
                      class="discount">- ¥{{
                        order.integral_cut_price }}</span></div>
                  <div class="bill-row" v-if="Number(order.pickup_discount_price) > 0"><span>自提优惠</span><span
                      class="discount">-
                      ¥{{ order.pickup_discount_price }}</span></div>
                  <div class="bill-row" v-if="Number(order.platform_subsidy_random_minus) > 0">
                    <span>支付随机减</span><span class="discount">- ¥{{ order.platform_subsidy_random_minus }}</span>
                  </div>
                </div>
                <div class="total-bar">
                  <div class="label">实付款</div>
                  <div class="amount"><small>¥</small>{{ order.pay_price }}</div>
                </div>
                <!-- 退过款要让人一眼看到:实付款还是原数,不单列的话看不出这单已经退了多少 -->
                <div class="refund-bar" v-if="Number(order.refund_price) > 0">
                  <span>{{ Number(order.refund_price) >= Number(order.pay_price) ? '已全额退款' : '已退款' }}</span>
                  <span>¥{{ order.refund_price }}</span>
                </div>
              </div>
            </div>
          </el-col>

          <el-col :span="6" class="col-panel">
            <div class="right-stack">
              <!-- 通用电商订单：展示配送信息（虚拟商品无联系人时不显示此区块） -->
              <div v-if="(!order.plugin_data || order.plugin_data.type !== 'hotel') && (order.name || order.mobile || order.address || order.deliver_type === 'pickup' || order.deliver_type === 'express' || order.deliver_type === 'city')" class="glass-card address-card">
                <div class="map-bg-decoration"></div>
                <div class="panel-header no-border">
                  <span class="title">{{ order.deliver_type === 'pickup' ? '自提信息' : '配送信息' }}</span>
                  <el-button type="text" v-if="order.status < 30 && order.deliver_type !== 'none'" icon="el-icon-edit" @click.native="openAddressDialog"
                    class="header-btn"></el-button>
                </div>
                <div class="address-content">
                  <div class="contact-row" v-if="order.name || order.mobile">
                    <div class="name text-ellipsis" style="max-width: 100px;">{{ order.name }}</div>
                    <div class="phone">{{ order.mobile }}</div>
                  </div>
                  <div class="address-text" v-if="order.address || (order.mch && order.mch.address)">
                    <i class="el-icon-location-information"></i>
                    {{ order.address || order.mch.address }}
                  </div>
                  <div v-if="order.deliver_type === 'pickup'" class="pickup-highlight">
                    <div class="code-label">核销码</div>
                    <div class="code-val">{{ order.pickup_code || '---' }}</div>
                  </div>
                  <div v-if="order.deliver_type === 'express' && order.expressList && order.expressList.length > 0"
                    class="logistics-mini">
                    <div class="logistics-row" v-for="(express, index) in order.expressList" :key="index">
                      <i class="el-icon-truck"></i>
                      <div class="log-info">
                        <div class="log-name text-ellipsis">{{ express.express_name }}</div>
                        <div class="log-no text-ellipsis">{{ express.express_no }}</div>
                      </div>
                      <el-button v-if="canEditExpress(express)" type="text" size="mini" class="log-edit"
                        @click="openExpressUpdate(express)">修改</el-button>
                      <el-button size="mini" circle icon="el-icon-arrow-right"
                        @click="openExpressTrack(express.express_no, express.express_name)"></el-button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- 动态插件面板：展示各插件专属业务信息 (如酒店入住、团购券码等) -->
              <div v-if="order.plugin_data && order.plugin_data.display_panels && order.plugin_data.display_panels.length > 0">
                <div v-for="(panel, pIndex) in order.plugin_data.display_panels" :key="'panel-'+pIndex" class="glass-card address-card" style="margin-bottom: 15px;">
                  <div class="panel-header no-border">
                    <span class="title">{{ panel.title }}</span>
                  </div>
                  <div class="address-content">
                    <div class="address-text" style="margin-top: 10px;" v-for="(item, iIndex) in panel.items" :key="'item-'+iIndex">
                      <span style="color: #909399; margin-right: 8px;">{{ item.label }}:</span>
                      <span style="color: #303133; font-weight: 500;">{{ item.value }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="glass-card remarks-card">
                <div class="panel-header compact">
                  <span class="title">备注</span>
                  <el-button type="text" icon="el-icon-edit-outline" @click="openRemarksDialog"></el-button>
                </div>
                <div class="remarks-body custom-scrollbar">
                  {{ order.remarks || '无' }}
                </div>
              </div>

              <div class="glass-card remarks-card" v-if="order.no_express_remark">
                <div class="panel-header compact">
                  <span class="title">无需物流备注</span>
                </div>
                <div class="remarks-body custom-scrollbar">
                  {{ order.no_express_remark }}
                </div>
              </div>

              <!-- 售后:这单有哪几张售后、到哪一步,给出去处理的入口。列的就是挡住关单的那几张 -->
              <div v-if="afterSales.length" class="glass-card after-sale-card">
                <div class="panel-header compact"><span class="title">售后</span></div>
                <div class="after-sale-body">
                  <div v-for="a in afterSales" :key="a.id" class="as-row">
                    <el-tag size="mini" :type="a.need_action ? 'warning' : 'info'" effect="plain">{{ a.status_text }}</el-tag>
                    <span class="as-main">{{ a.type_text }} ¥{{ a.refund_price }}</span>
                    <span v-if="list.length > 1 && a.goods_name" class="as-sub">{{ a.goods_name }}</span>
                    <span v-if="a.reason" class="as-sub">{{ a.reason }}</span>
                  </div>
                  <div v-if="afterSaleNote" class="as-note">
                    <i class="el-icon-info"></i> {{ afterSaleNote }}
                  </div>
                  <el-button type="warning" plain size="small" class="as-btn" @click="goAfterSales">
                    {{ afterSalesNeedAction ? '去处理售后' : '查看售后' }}
                  </el-button>
                </div>
              </div>

              <div class="glass-card action-panel">
                <div class="panel-header compact"><span class="title">操作</span></div>
                <div class="action-grid custom-scrollbar">
                  <el-button type="primary" class="action-btn main" v-if="order.status === 10" @click="pay"
                    icon="el-icon-wallet">后台付款
                  </el-button>
                  <el-button type="primary" class="action-btn main"
                    v-if="order.status === 20 && order.deliver_type !== 'pickup' && !order.after_sale_block_reason && !(order.plugin_data && order.plugin_data.action_config && order.plugin_data.action_config.show_ship === false)"
                    @click="showOrderSend = true" icon="el-icon-s-promotion">立即发货
                  </el-button>
                  <el-button type="success" class="action-btn main" v-if="order.status === 30 && !(order.plugin_data && order.plugin_data.action_config && order.plugin_data.action_config.show_confirm === false)" @click="confirm"
                    icon="el-icon-check">确认收货
                  </el-button>
                  <el-button class="action-btn sub" v-if="order.status === 40 && order.settlement_status === 0 && !order.after_sale_block_reason && !(order.plugin_data && order.plugin_data.action_config && order.plugin_data.action_config.show_force_settle === false)"
                    @click="forceSettle" type="danger" icon="el-icon-money">强制结算
                  </el-button>
                  <!-- 有售后还在处理时后端不让关单(after_sale_block_reason),按钮跟着藏,原因写在上面的售后卡片里 -->
                  <el-button class="action-btn sub" v-if="[10, 20].includes(order.status) && !order.after_sale_block_reason" @click="close"
                    icon="el-icon-close">关闭订单
                  </el-button>
                  <el-button class="action-btn sub" @click="handleClose" icon="el-icon-back">返回列表</el-button>
                  <el-button type="success" class="action-btn main" v-if="order.status === 20 && order.deliver_type === 'pickup' && !order.after_sale_block_reason && !(order.plugin_data && order.plugin_data.action_config && order.plugin_data.action_config.show_force_verify === false)" @click="forceVerifyPickup"
                    icon="el-icon-circle-check">强制核销(自提)
                  </el-button>
                  <div v-if="order.plugin_data && order.plugin_data.action_config && order.plugin_data.action_config.custom_tip" class="wait-tip" style="color: #E6A23C; background: rgba(230, 162, 60, 0.1);">
                    <i class="el-icon-info"></i> {{ order.plugin_data.action_config.custom_tip }}
                  </div>
                </div>
              </div>
            </div>
          </el-col>
        </el-row>
      </div>

      <el-dialog title="修改备注" :visible.sync="showRemarks" width="400px" custom-class="rounded-dialog" append-to-body>
        <el-input type="textarea" v-model="tempRemarks" :rows="4" placeholder="请输入备注信息"></el-input>
        <span slot="footer">
          <el-button @click="showRemarks = false" size="small">取消</el-button>
          <el-button type="primary" @click="saveRemarks" size="small" :loading="remarksLoading">保存</el-button>
        </span>
      </el-dialog>

      <el-dialog title="修改收货地址" :visible.sync="showAddressEdit" width="400px" custom-class="rounded-dialog"
        append-to-body :close-on-click-modal="false">
        <el-form :model="addressForm" ref="addressForm" label-width="70px" size="small" class="pt-10">
          <el-form-item label="收货人">
            <el-input v-model="addressForm.name" placeholder="请填写收货人姓名"></el-input>
          </el-form-item>
          <el-form-item label="手机号">
            <el-input v-model="addressForm.mobile" placeholder="请填写手机号码"></el-input>
          </el-form-item>
          <el-form-item label="地址">
            <el-input type="textarea" v-model="addressForm.address" :rows="3" placeholder="请填写详细收货地址"
              resize="none"></el-input>
          </el-form-item>
        </el-form>
        <span slot="footer">
          <el-button @click="showAddressEdit = false" size="small">取消</el-button>
          <el-button type="primary" @click="saveAddress" size="small" :loading="addressLoading">保存修改</el-button>
        </span>
      </el-dialog>

      <OrderSend @refresh="refresh" :visible.sync="showOrderSend" :order-id="orderId"></OrderSend>
      <RefundHandleDialog :visible.sync="showRefundHandle" :order-id="orderId" @changed="onRefundChanged"></RefundHandleDialog>
      <ExpressUpdateDialog :visible.sync="showExpressUpdate" :order-id="orderId" :express="editingExpress" @refresh="refresh"></ExpressUpdateDialog>
    </div>
  </el-drawer>
</template>

<script>
// 确保路径正确
import OrderSend from '@/components/mall/order/OrderSend'
import RefundHandleDialog from '@/components/mall/order/RefundHandleDialog'
import ExpressUpdateDialog from '@/components/mall/order/ExpressUpdateDialog'

export default {
  name: 'OrderDetailDrawer',
  components: { OrderSend, ExpressUpdateDialog, RefundHandleDialog },
  data() {
    return {
      visible: false,
      orderId: null,
      callback: null,

      loading: false,
      list: [],
      order: null,
      user: null,

      showOrderSend: false,
      showRefundHandle: false,
      showExpressUpdate: false,
      editingExpress: null,
      showRemarks: false,
      tempRemarks: '',
      remarksLoading: false,
      showAddressEdit: false,
      addressLoading: false,
      addressForm: { name: '', mobile: '', address: '' }
    }
  },
  computed: {
    activeStep() {
      if (!this.order) return 0
      // 已关闭的按实际走到哪一步画:付过款、发过货的关单(如整单退款)不能退回成「只下了单」
      if (this.order.status === 0) {
        if (Number(this.order.send_at) > 0) return 3
        if (Number(this.order.pay_at) > 0) return 2
        return 1
      }
      if (this.order.status === 40) return 4
      if (this.order.status === 30) return 3
      if (this.order.status === 20) return 2
      if (this.order.status === 10) return 1
      return 0
    },
    // 【修改】这边的 goodsTotalPrice 计算不需要动，因为它算的是打完会员折后的“结算基础总价”
    goodsTotalPrice() {
      if (!this.list || this.list.length === 0) return '0.00'
      const total = this.list.reduce((sum, item) => sum + parseFloat(item.total_price), 0)
      return total.toFixed(2)
    },
    // 这单的售后单(已拒绝、用户撤销的不列)
    afterSales() {
      return this.order && Array.isArray(this.order.after_sales) ? this.order.after_sales : []
    },
    // 还有等后台动手的(待审核 / 处理中 / 用户已发货),按钮说「去处理」;只剩退款中、已完成的说「查看」
    afterSalesNeedAction() {
      return this.afterSales.some(a => a.need_action)
    },
    // 卡片里说清被挡住的是什么(原因由后端给,与拦截同一个判断):售后还在处理 →
    // 待付款 / 待发货的不能关单、不能发货 / 核销,已完成的先不结算。处理完了就都能做,退过款的关单只退剩下的
    afterSaleNote() {
      const o = this.order
      if (!o || !o.after_sale_block_reason) return ''
      return [10, 20].includes(o.status) || (o.status === 40 && o.settlement_status === 0) ? o.after_sale_block_reason : ''
    },
    // 关单确认框:写明会退多少 —— 只退还没退的部分
    closeConfirmText() {
      const o = this.order
      const amount = o ? Number(o.close_refund_amount || 0) : 0
      if (amount <= 0) return '确认关闭订单?'
      // 含「不支持退款」的商品(按下单时的快照):顾客自己申请不了售后,但后台关单是整单退,提醒一句
      const hasNoRefund = (this.list || []).some(i => i.is_can_refund !== null && i.is_can_refund !== undefined && Number(i.is_can_refund) === 0)
      return `确认关闭订单? 关闭后将退还${Number(o.refund_price || 0) > 0 ? '剩余' : ''} ¥${o.close_refund_amount}` +
        (hasNoRefund ? '(订单含不支持退款的商品,关闭后也会一并退款)' : '')
    }
  },
  filters: {
    formatTime(timestamp) {
      if (!timestamp || timestamp == 0) return ''
      if (String(timestamp).length === 10) timestamp = timestamp * 1000
      const date = new Date(timestamp)
      const y = date.getFullYear()
      const m = (date.getMonth() + 1).toString().padStart(2, '0')
      const d = date.getDate().toString().padStart(2, '0')
      const h = date.getHours().toString().padStart(2, '0')
      const min = date.getMinutes().toString().padStart(2, '0')
      const sec = date.getSeconds().toString().padStart(2, '0')
      // 订单详情里的时间通常需要精确到秒
      return `${y}-${m}-${d} ${h}:${min}:${sec}`
    }
  },
  methods: {
    // 换页面时由 components/mall/order/index.js 的 router.afterEach 调:抽屉连同它弹出的窗口一起关
    closeAll() {
      this.visible = false
      this.showOrderSend = false
      this.showRefundHandle = false
      this.showExpressUpdate = false
      this.showRemarks = false
      this.showAddressEdit = false
    },

    open(id, callback) {
      this.orderId = id;
      this.callback = callback;
      this.visible = true;
      this.order = null;
      this.getDetail();
    },

    refresh() {
      this.getDetail();
      if (this.callback && typeof this.callback === 'function') {
        this.callback();
      }
    },

    handleClose() {
      this.visible = false;
    },

    // 原地打开售后处理弹窗(列出这单全部售后),不跳页;处理完刷新本单,再通知打开抽屉的页面(如列表)刷新
    goAfterSales() {
      this.showRefundHandle = true
    },
    onRefundChanged() {
      this.refresh()
      if (typeof this.callback === 'function') this.callback()
    },

    async getDetail() {
      this.loading = true
      try {
        const res = await this.$request({
          url: '/mall/order/detail',
          method: 'get',
          data: { order_id: this.orderId }
        })
        if (res.code === 0) {
          // 这个地方你的 Json 解码可能没完全生效，因为如果是数组，不需要 JSON.parse
          // 我们做一层稳健的兼容
          res.data.list.forEach(item => {
            if (typeof item.attr === 'string') {
              try {
                item.attr = JSON.parse(item.attr)
              } catch (e) {
                item.attr = []
              }
            }
            if (typeof item.mch_income_breakdown === 'string') {
              try {
                item.mch_income_breakdown = JSON.parse(item.mch_income_breakdown)
              } catch (e) {
                item.mch_income_breakdown = []
              }
            }
            this.$set(item, '_expanded', false)
          })
          this.order = res.data.order
          if (res.data.plugin_data) {
              this.$set(this.order, 'plugin_data', res.data.plugin_data);
          }
          this.user = res.data.user
          this.list = res.data.list
        } else {
          this.$message.error(res.msg)
        }
      } finally {
        this.loading = false
      }
    },

    copyText(text) {
      const input = document.createElement('input');
      input.value = text;
      document.body.appendChild(input);
      input.select();
      document.execCommand('Copy');
      document.body.removeChild(input);
      this.$message.success('复制成功');
    },
    // 每件商品的售后标记:确认打款后这件就作废(status=2)—— 钱到账前是「退款中」、到账后「已退款」;
    // 申请了还没确认打款是「售后中」(这时整单也不能发货 / 关单 / 结算,见 after_sale_block_reason);售后完成但没作废 = 换货
    itemAfterSale(item) {
      const rs = Number(item.refund_status)
      if (Number(item.status) === 2) return rs === 20 ? { text: '退款中', cls: 'is-pending' } : { text: '已退款', cls: 'is-refund' }
      if (rs === 10 || rs === 20) return { text: '售后中', cls: 'is-pending' }
      if (rs === 30) return { text: '已换货', cls: 'is-done' }
      return null
    },
    hasBreakdown(item) {
      return item.mch_income_breakdown && Array.isArray(item.mch_income_breakdown) && item.mch_income_breakdown.length > 0
    },
    toggleExpand(item) {
      if (this.hasBreakdown(item)) {
        this.$set(item, '_expanded', !item._expanded)
      }
    },
    // 发货后、买家收货前可改单号;电子面单的单号不能手改(和后端 updateExpress 口径一致)
    canEditExpress(express) {
      const o = this.order
      if (!o || Number(express.is_delete) === 1 || Number(express.eorder_detail_id) > 0) return false
      return [20, 30].includes(Number(o.status)) && Number(o.receipt_status) !== 1
    },
    openExpressUpdate(express) {
      this.editingExpress = express
      this.showExpressUpdate = true
    },
    openExpressTrack(expressNo, expressCompany) {
      if (!expressNo) return
      const query = `${expressCompany || ''} ${expressNo} 快递`.trim()
      window.open(`https://www.baidu.com/s?wd=${encodeURIComponent(query)}`, '_blank')
    },
    getStatusText(order) {
      if (!order) return ''
      // 已关闭优先于售后状态:整单退完会自动关单,这时说「交易关闭」,退了多少写在下面一行
      if (order.status === 0) return '交易关闭'
      // 只有售后还在进行时才让售后盖过订单状态;退完 / 撤销了就回到订单本身(部分退款后剩下的还要发货)
      if ([10, 20].includes(Number(order.refund_status))) {
        return order.refund_status == 10 ? '申请售后' : '售后处理中'
      }
      const map = { 0: '已关闭', 10: '待付款', 20: '待发货', 30: '待收货', 40: '已完成' }
      if (order.status === 20 && order.deliver_type === 'pickup') return '待核销'
      return map[order.status] || ''
    },
    getStatusIcon(order) {
      if (!order) return ''
      const map = {
        10: 'el-icon-wallet',
        20: 'el-icon-box',
        30: 'el-icon-truck',
        40: 'el-icon-circle-check',
        0: 'el-icon-circle-close'
      }
      return map[order.status] || 'el-icon-info'
    },
    getSettlementStatusDesc(order) {
      if (!order) return ''
      if (order.status === 0) {
        const refunded = Number(order.refund_price)
        if (refunded <= 0) return '订单已关闭'
        return refunded >= Number(order.pay_price) ? `已全额退款 ¥${order.refund_price}` : `已退款 ¥${order.refund_price}`
      }
      if ([10, 20].includes(Number(order.refund_status))) {
        return order.refund_status == 10 ? '买家发起申请' : '商家处理中'
      }
      const map = { 10: '等待买家付款', 20: '等待发货/核销', 30: '物流运输中', 40: '交易完成', 0: '订单已关闭' }
      return map[order.status] || ''
    },
    getPayTypeText(type) {
      return { 0: '微信', 1: '余额', 2: '后台', 3: '混合' }[type] || '其他'
    },
    getDeliverTypeText(type) {
      return { express: '快递', pickup: '自提', city: '同城', none: '无' }[type] || '未知'
    },
    getDeliverTypeTag(type) {
      return { express: 'primary', pickup: 'warning', city: 'success' }[type] || 'info'
    },

    async handleAction(url, data, confirmOptions) {
      if (confirmOptions) {
        try {
          await this.$confirm(confirmOptions.content, confirmOptions.title, {
            type: confirmOptions.type || 'warning',
            customClass: 'rounded-dialog'
          })
        } catch {
          return
        }
      }
      this.loading = true
      const res = await this.$request({ url, method: 'post', data })
      this.loading = false
      if (res.code === 0) {
        this.$message.success(res.msg)
        this.refresh()
      } else {
        this.$message.error(res.msg)
      }
    },
    pay() {
      this.handleAction('/mall/order/pay', { order_id: this.orderId }, { content: '确认标记已付款?', title: '确认付款' })
    },
    close() {
      this.handleAction('/mall/order/close', { order_id: this.orderId }, { content: this.closeConfirmText, title: '关闭订单' })
    },
    confirm() {
      this.handleAction('/mall/order/confirm', { order_id: this.orderId }, { content: '确认已收货?', title: '确认收货' })
    },
    forceSettle() {
      this.handleAction('/mall/order/force-settle', { order_id: this.orderId }, {
        content: '强制结算操作不可逆，确定执行?',
        title: '强制结算'
      })
    },
    forceVerifyPickup() {
      this.handleAction('/mall/order/verify-pickup', { order_id: this.orderId }, {
        content: '确认要为买家强制核销此订单？此操作等效于扫码核销。',
        title: '强制核销'
      })
    },

    openAddressDialog() {
      if (!this.order) return
      this.addressForm = { name: this.order.name, mobile: this.order.mobile, address: this.order.address }
      this.showAddressEdit = true
    },
    async saveAddress() {
      if (!this.addressForm.address) {
        this.$message.warning('地址不能为空');
        return
      }
      this.addressLoading = true
      try {
        const res = await this.$request({
          url: '/mall/order/address-modify',
          method: 'post',
          data: {
            order_id: this.orderId,
            name: this.addressForm.name,
            mobile: this.addressForm.mobile,
            address: this.addressForm.address
          }
        })
        if (res.code === 0) {
          this.$message.success(res.msg)
          this.showAddressEdit = false
          this.refresh()
        } else {
          this.$message.error(res.msg)
        }
      } catch (e) {
        this.$message.error('请求失败')
      } finally {
        this.addressLoading = false
      }
    },
    openRemarksDialog() {
      this.tempRemarks = this.order.remarks;
      this.showRemarks = true
    },
    async saveRemarks() {
      this.remarksLoading = true
      await this.handleAction('/mall/order/remarks', { order_id: this.orderId, remarks: this.tempRemarks })
      this.remarksLoading = false;
      this.showRemarks = false
    }
  }
}
</script>
<style scoped lang="scss">
.ipad-container {
  height: 100%;
  background-color: #f6f8fb;
  padding: 15px;
  box-sizing: border-box;
  font-family: -apple-system, BlinkMacSystemFont, "San Francisco", "Helvetica Neue", Helvetica, Arial, sans-serif;
  overflow: hidden;
  position: relative;
}

.main-layout {
  height: 100%;
}

.h-100 {
  height: 100%;
}

.row-stretch {
  display: flex;
  flex-wrap: wrap;
}

.col-panel {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.drawer-close-btn {
  position: absolute;
  top: 15px;
  right: 15px;
  z-index: 9999;
  width: 32px;
  height: 32px;
  background: rgba(0, 0, 0, 0.1);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
  color: #fff;

  &:hover {
    background: rgba(0, 0, 0, 0.3);
  }
}

.glass-card {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.6);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
  transition: all 0.3s ease;

  &.full-h-card {
    flex: 1;
  }
}

.panel-header {
  padding: 12px 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #f0f2f5;
  background-color: #fff;
  z-index: 2;

  &.compact {
    padding: 8px 12px;
  }

  &.no-border {
    border-bottom: none;
    background: transparent;
  }

  .title {
    font-size: 15px;
    font-weight: 600;
    color: #303133;
  }

  .badge {
    background: #f2f6fc;
    color: #909399;
    font-size: 12px;
    padding: 2px 8px;
    border-radius: 10px;
  }
}

.panel-scrollable {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
}

.hide-scrollbar::-webkit-scrollbar {
  display: none;
}

.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #dcdfe6;
  border-radius: 2px;
}

.status-header-modern {
  padding: 24px 16px;
  text-align: center;
  color: #fff;
  position: relative;
  overflow: hidden;

  &.status-10 {
    background: linear-gradient(135deg, #f6a15e 0%, #e6a23c 100%);
  }

  &.status-20 {
    background: linear-gradient(135deg, #409eff 0%, #3a8ee6 100%);
  }

  &.status-30 {
    background: linear-gradient(135deg, #48cae4 0%, #0096c7 100%);
  }

  &.status-40 {
    background: linear-gradient(135deg, #67c23a 0%, #529b2e 100%);
  }

  &.status-0 {
    background: linear-gradient(135deg, #909399 0%, #606266 100%);
  }

  .icon-bubble {
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    margin: 0 auto 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    backdrop-filter: blur(5px);
  }

  .status-title {
    font-size: 20px;
    font-weight: 600;
    margin-bottom: 4px;
  }

  .status-desc {
    font-size: 13px;
    opacity: 0.9;
  }
}

.info-group {
  margin-bottom: 20px;

  .group-title {
    font-size: 14px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 12px;
  }

  .info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    font-size: 13px;

    .label {
      color: #909399;
    }

    .value {
      color: #606266;
      font-weight: 500;
    }

    .link-style {
      color: var(--admin-accent-text);
      cursor: pointer;
    }

    .user-capsule {
      display: flex;
      align-items: center;
      background: #f4f4f5;
      padding: 2px 8px 2px 2px;
      border-radius: 12px;
      color: #606266;
    }
  }
}

.soft-divider {
  margin: 15px 0;
  background-color: #f0f2f5;
}

.custom-steps {
  ::v-deep .el-step__title {
    font-size: 13px;
    font-weight: 500;
  }

  ::v-deep .el-step__description {
    padding-right: 0;
    font-size: 12px;
  }

  ::v-deep .el-step__line {
    background-color: #e4e7ed;
  }

  .step-time {
    color: #909399;
    margin-top: 2px;
  }

  .placeholder {
    color: #c0c4cc;
    font-style: italic;
  }
}

.goods-list {
  padding-bottom: 10px;
}

.goods-group {
  border-bottom: 1px solid #f0f2f5;
  padding: 12px 0;

  &:last-child {
    border-bottom: none;
  }
}

.goods-item {
  display: flex;
  padding: 4px;
  border-radius: 6px;
  transition: background 0.2s;

  &.interactive {
    cursor: pointer;

    &:hover {
      background-color: #f5f7fa;
    }
  }

  .goods-thumb {
    width: 64px;
    height: 64px;
    border-radius: 6px;
    background: #f4f4f5;
    flex-shrink: 0;
    border: 1px solid #eee;
  }

  .goods-details {
    flex: 1;
    margin-left: 12px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-width: 0;

    .goods-main {
      .name {
        font-size: 14px;
        color: #303133;
        line-height: 1.4;
        margin-bottom: 4px;
      }

      .tags {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
      }

      .spec-tag {
        font-size: 11px;
        color: #909399;
        background: #f4f4f5;
        padding: 1px 5px;
        border-radius: 4px;
      }
    }

    .goods-price-col {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;

      .price {
        font-weight: 600;
        color: #303133;
        font-size: 14px;
      }

      .count {
        color: #909399;
        font-size: 12px;
      }
    }
  }

  .goods-right-col {
    width: 80px;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: space-between;
    padding-bottom: 2px;
  }

  .after-sale-tag {
    font-size: 11px;
    padding: 1px 6px;
    border-radius: 4px;
    line-height: 16px;

    &.is-refund { color: #F56C6C; background: #fef0f0; }
    &.is-pending { color: #E6A23C; background: #fdf6ec; }
    &.is-done { color: #909399; background: #f4f4f5; }
  }

  &.is-voided {
    .goods-thumb, .name, .price { opacity: 0.55; }
    .goods-subtotal { color: #C0C4CC; text-decoration: line-through; }
  }

  .goods-subtotal {
    font-weight: 600;
    font-size: 14px;
    color: #303133;
  }

  .expand-indicator {
    font-size: 12px;
    color: #909399;
    display: flex;
    align-items: center;

    .expand-text {
      font-size: 11px;
      margin-right: 2px;
      opacity: 0.8;
    }

    i {
      transition: transform 0.3s;

      &.is-active {
        transform: rotate(180deg);
      }
    }
  }
}

.settlement-box {
  margin-top: 8px;
  margin-left: 80px;
  background: #f8f9fb;
  border-radius: 6px;
  padding: 10px 14px;
  font-size: 12px;
  border: 1px solid #ebeef5;
  box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.02);

  .settlement-header {
    color: #909399;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 4px;
    font-weight: 500;
  }

  .breakdown-list {
    display: flex;
    flex-direction: column;
    gap: 5px;
  }

  .breakdown-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    line-height: 1.4;

    .bd-label {
      color: #606266;
      flex: 1;

      .bd-note {
        font-size: 11px;
        color: #c0c4cc;
        margin-left: 2px;
      }
    }

    .bd-value {
      margin-left: 10px;
      white-space: nowrap;
    }
  }
}

.bill-footer {
  border-top: 1px solid #ebeef5;
  padding: 12px 16px;
  background-color: #fff;
  z-index: 2;

  .bill-detail {
    margin-bottom: 10px;

    .bill-row {
      display: flex;
      justify-content: space-between;
      font-size: 13px;
      color: #606266;
      margin-bottom: 4px;

      .discount {
        color: #f56c6c;
      }
    }
  }

  .refund-bar {
    display: flex;
    justify-content: space-between;
    margin-top: 6px;
    font-size: 13px;
    color: #e6a23c;
  }

  .total-bar {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    padding-top: 8px;
    border-top: 1px dashed #eee;

    .label {
      font-size: 14px;
      font-weight: 600;
      color: #303133;
    }

    .amount {
      color: #f56c6c;
      font-size: 20px;
      font-weight: bold;
      line-height: 1;

      small {
        font-size: 13px;
        font-weight: 600;
        margin-right: 1px;
      }
    }
  }
}

.right-stack {
  display: flex;
  flex-direction: column;
  height: 100%;
  gap: 15px;
}

.address-card {
  min-height: 140px;
  flex-shrink: 0;
  background: linear-gradient(to bottom, #fff 0%, #fdfdfd 100%);

  .map-bg-decoration {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 60px;
    background: radial-gradient(circle at 90% 10%, #f2f6fc 0%, transparent 60%);
    z-index: 1;
    pointer-events: none;
  }

  .address-content {
    padding: 0 16px 16px;
    position: relative;
    z-index: 2;

    .contact-row {
      display: flex;
      align-items: baseline;
      margin-bottom: 8px;

      .name {
        font-size: 16px;
        font-weight: 600;
        color: #303133;
        margin-right: 8px;
      }

      .phone {
        font-size: 14px;
        color: #606266;
        font-family: "Menlo", monospace;
      }
    }

    .address-text {
      font-size: 13px;
      color: #606266;
      line-height: 1.5;
      display: flex;

      i {
        margin-right: 4px;
        margin-top: 2px;
        color: #909399;
      }
    }
  }
}

.pickup-highlight {
  margin-top: 10px;
  background: #fff8e6;
  border: 1px solid #faecd8;
  border-radius: 6px;
  padding: 8px;
  text-align: center;

  .code-label {
    font-size: 11px;
    color: #e6a23c;
  }

  .code-val {
    font-size: 20px;
    font-weight: bold;
    color: #d78f24;
    letter-spacing: 1px;
  }
}

.logistics-mini {
  margin-top: 10px;
  border-top: 1px dashed #eee;
  padding-top: 10px;

  .logistics-row {
    display: flex;
    align-items: center;
    font-size: 12px;
    color: #606266;
    margin-bottom: 6px;

    i {
      color: var(--admin-accent-text);
      margin-right: 6px;
    }

    .log-edit {
      padding: 0;
      margin-right: 8px;
    }

    .log-info {
      flex: 1;
      overflow: hidden;
      margin-right: 4px;
    }

    .log-name {
      font-weight: 500;
    }

    .log-no {
      color: #909399;
      font-size: 11px;
    }
  }
}

.remarks-card {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;

  .remarks-body {
    padding: 12px 16px;
    font-size: 13px;
    color: #606266;
    line-height: 1.6;
    overflow-y: auto;
    flex: 1;
    white-space: pre-wrap;
    background-color: #fff;
  }
}

.after-sale-card {
  flex-shrink: 0;

  .after-sale-body {
    padding: 10px 12px 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-size: 13px;
    color: #606266;
  }

  .as-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 8px;
    line-height: 1.5;
  }

  .as-main {
    color: #303133;
    font-weight: 500;
  }

  .as-sub {
    color: #909399;
    font-size: 12px;
  }

  .as-note {
    font-size: 12px;
    line-height: 1.5;
    color: #e6a23c;
    background: #fdf6ec;
    padding: 6px 8px;
    border-radius: 4px;
  }

  .as-btn {
    width: 100%;
    margin-left: 0;
    border-radius: 8px;
  }
}

.action-panel {
  flex-shrink: 0;
  max-height: 200px;
  display: flex;
  flex-direction: column;

  .action-grid {
    padding: 12px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    overflow-y: auto;
  }

  .action-btn {
    width: 100%;
    margin-left: 0 !important;
    justify-content: center;
    font-weight: 500;
    border-radius: 8px;

    &.main {
      height: 40px;
      font-size: 14px;
      box-shadow: 0 2px 6px rgba(var(--admin-accent-rgb), 0.2);
    }

    &.sub {
      height: 36px;
    }
  }

  .wait-tip {
    text-align: center;
    font-size: 12px;
    color: #e6a23c;
    background: #fdf6ec;
    padding: 6px;
    border-radius: 4px;
    margin-top: 5px;
  }
}

::v-deep .rounded-dialog {
  border-radius: 12px;
  overflow: hidden;

  .el-dialog__header {
    background: #f9fafc;
    padding: 15px 20px;
    border-bottom: 1px solid #ebeef5;
  }

  .el-dialog__title {
    font-size: 16px;
    font-weight: 600;
  }

  .el-dialog__body {
    padding: 20px;
  }

  .el-dialog__footer {
    padding: 10px 20px 20px;
  }
}

.text-ellipsis {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.text-ellipsis-2 {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
}

.ml-5 {
  margin-left: 5px;
}

.pt-10 {
  padding-top: 10px;
}
</style>
