<template>
  <el-drawer :visible.sync="visible" :with-header="false" size="75%" :before-close="handleClose" append-to-body
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
                  <el-steps :active="activeStep" direction="vertical" class="custom-steps">
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
                    <div class="goods-item" :class="{ 'interactive': hasBreakdown(item) }" @click="toggleExpand(item)">
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
              </div>
            </div>
          </el-col>

          <el-col :span="6" class="col-panel">
            <div class="right-stack">
              <!-- 通用电商订单：展示配送信息 -->
              <div v-if="!order.plugin_data || order.plugin_data.type !== 'hotel'" class="glass-card address-card">
                <div class="map-bg-decoration"></div>
                <div class="panel-header no-border">
                  <span class="title">{{ order.deliver_type === 'pickup' ? '自提信息' : '配送信息' }}</span>
                  <el-button v-if="canEditAddress" type="text" size="mini" @click="openAddressEdit">修改</el-button>
                </div>
                <div class="address-content">
                  <div class="contact-row">
                    <div class="name text-ellipsis" style="max-width: 100px;">{{ order.name || '未知' }}</div>
                    <div class="phone">{{ order.mobile || '未知' }}</div>
                  </div>
                  <div class="address-text">
                    <i class="el-icon-location-information"></i>
                    {{ order.address || (order.mch ? order.mch.address : '未填写地址') }}
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
                  
                </div>
                <div class="remarks-body custom-scrollbar">
                  {{ order.remarks || '无' }}
                </div>
              </div>

              <div class="glass-card action-panel">
                <div class="panel-header compact"><span class="title">操作</span></div>
                <div class="action-grid custom-scrollbar">
                  <!-- 有售后还在处理时不能发货、不能核销(after_sale_block_reason,与后端拦截同一个判断);
                       原来看 refund_status === 0,部分退款后剩下的商品也发不了 -->
                  <div v-if="order.status === 20 && order.after_sale_block_reason" class="wait-tip">
                    <i class="el-icon-warning-outline"></i> {{ order.after_sale_block_reason }}
                    <el-button type="text" size="mini" @click="goAfterSales">去处理售后</el-button>
                  </div>
                  <el-button type="primary" class="action-btn main"
                    v-if="order.status === 20 && order.deliver_type !== 'pickup' && !order.after_sale_block_reason && !(order.plugin_data && order.plugin_data.action_config && order.plugin_data.action_config.show_ship === false)"
                    @click="showOrderSend = true" icon="el-icon-s-promotion">立即发货
                  </el-button>
                  <el-button class="action-btn sub" v-if="[10].includes(order.status)" @click="cancelOrder"
                    icon="el-icon-close">取消订单
                  </el-button>
                  <el-button class="action-btn sub" @click="handleClose" icon="el-icon-back">返回列表</el-button>
                  <el-button type="success" class="action-btn main" v-if="order.status === 20 && !order.after_sale_block_reason && (order.deliver_type === 'pickup' || (order.plugin_data && order.plugin_data.action_config && order.plugin_data.action_config.show_mch_force_verify === true)) && !(order.plugin_data && order.plugin_data.action_config && order.plugin_data.action_config.show_force_verify === false && !order.plugin_data.action_config.show_mch_force_verify)" @click="forceVerifyPickup"
                    icon="el-icon-circle-check">确认核销(PC自提/授权)
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

      

      

      <!-- 发货弹窗要的是 showOrderSend / order / order_detail_list;原来传 visible / order-id(从平台端照搬),
           名字对不上,点「立即发货」弹窗根本不出来 -->
      <OrderSend
        :show-order-send="showOrderSend"
        :order="order"
        :order_detail_list="list"
        @refresh="refresh"
        @orderSendClose="showOrderSend = false"
      />
    </div>

    <el-dialog title="修改收货信息" :visible.sync="addrVisible" width="460px" append-to-body>
      <el-alert type="warning" :closable="false" show-icon class="ae-tip">
        发货后不可再改，请在发货前核对。修改仅影响本单，不改动买家的收货地址簿。
      </el-alert>
      <el-form :model="addrForm" label-width="80px" size="small">
        <el-form-item label="收货人"><el-input v-model="addrForm.name" maxlength="20" /></el-form-item>
        <el-form-item label="联系电话"><el-input v-model="addrForm.mobile" maxlength="11" /></el-form-item>
        <el-form-item label="详细地址">
          <el-input v-model="addrForm.address" type="textarea" :rows="3" maxlength="120" show-word-limit />
        </el-form-item>
      </el-form>
      <div slot="footer">
        <el-button size="small" @click="addrVisible = false">取消</el-button>
        <el-button size="small" type="primary" :loading="addrSaving" @click="saveAddress">保存</el-button>
      </div>
    </el-dialog>
  </el-drawer>
</template>

<script>
// 确保路径正确
import OrderSend from '@/components/mall/order/OrderSend'

export default {
  name: 'OrderDetailDrawer',
  components: { OrderSend },
  data() {
    return {
    addrVisible: false,
    addrSaving: false,
    addrForm: { name: '', mobile: '', address: '' },
      visible: false,
      orderId: null,
      callback: null,

      loading: false,
      list: [],
      order: null,
      user: null,

      showOrderSend: false,
      showRemarks: false,
      tempRemarks: '',
      remarksLoading: false,
      showAddressEdit: false,
      addressLoading: false,
      addressForm: { name: '', mobile: '', address: '' }
    }
  },
  computed: {
    // 发货后包裹已在路上，改地址没有意义;自提单没有收货地址
    canEditAddress() {
      return this.order && Number(this.order.status) === 1 && this.order.deliver_type !== 'pickup'
    },
    activeStep() {
      if (!this.order) return 0
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
    openAddressEdit() {
      this.addrForm = {
        name: this.order.name || '',
        mobile: this.order.mobile || '',
        address: this.order.address || ''
      }
      this.addrVisible = true
    },
    saveAddress() {
      const f = this.addrForm
      if (!f.name.trim() || !f.mobile.trim() || !f.address.trim()) {
        return this.$message.warning('姓名、电话、地址都要填')
      }
      this.addrSaving = true
      // ⚠ 走商家自己的接口。原 OrderAddressInfo.vue 调的是 /mall/order/address-modify，
      //   那是平台端命名空间，走 MallFilter 校验 x-admin-token；商家发的是 x-access-token，
      //   根本调不通 —— 那个组件零引用，是从 admin 复制过来没接上的死代码，已删除。
      this.$request({
        url: '/plugin/mch/admin/order/address-modify',
        method: 'post',
        data: { order_id: this.order.id, name: f.name, mobile: f.mobile, address: f.address }
      }).then(e => {
        if (e.code === 0) {
          this.$message.success('收货信息已更新')
          this.addrVisible = false
          this.getDetail()
        } else {
          this.$message.error(e.msg)
        }
      }).finally(() => { this.addrSaving = false })
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

    // 去售后列表,按订单号筛出这单的售后(售后列表按原订单号也能搜)
    goAfterSales() {
      const orderNo = this.order.order_no
      this.visible = false
      this.$router.push({ path: '/refund/index', query: { keyword: orderNo }})
    },

    async getDetail() {
      this.loading = true
      try {
        const res = await this.$request({
          url: '/plugin/mch/admin/order/detail',
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
    hasBreakdown(item) {
      return item.mch_income_breakdown && Array.isArray(item.mch_income_breakdown) && item.mch_income_breakdown.length > 0
    },
    toggleExpand(item) {
      if (this.hasBreakdown(item)) {
        this.$set(item, '_expanded', !item._expanded)
      }
    },
    openExpressTrack(expressNo, expressCompany) {
      if (!expressNo) return
      const query = `${expressCompany || ''} ${expressNo} 快递`.trim()
      window.open(`https://www.baidu.com/s?wd=${encodeURIComponent(query)}`, '_blank')
    },
    getStatusText(order) {
      if (!order) return ''
      if (order.refund_status > 0) {
        const map = { 10: '申请售后', 20: '售后处理中', 30: '售后完成', 40: '售后关闭' };
        return map[order.refund_status] || '售后中';
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
      if (order.refund_status > 0) {
        const map = { 10: '买家发起申请', 20: '商家处理中', 30: '售后流程结束', 40: '售后申请取消' };
        return map[order.refund_status] || '售后流程进行中';
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
    cancelOrder() {
      this.handleAction('/plugin/mch/admin/order/cancel', { order_id: this.orderId, remark: '店长取消' }, { content: '确认取消订单?', title: '取消订单' })
    },
    forceVerifyPickup() {
      if (!this.order.pickup_code) {
        return this.$message.error('该订单没有核销码');
      }
      this.handleAction('/plugin/mch/admin/order/verify', {
        code: this.order.pickup_code
      }, {
        content: '确认要执行手动核销吗？点击确定立即完成订单闭环。',
        title: '确认手工核销',
        type: 'warning'
      })
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
      color: #409eff;
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
      color: #409eff;
      margin-right: 6px;
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
      box-shadow: 0 2px 6px rgba(64, 158, 255, 0.2);
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
