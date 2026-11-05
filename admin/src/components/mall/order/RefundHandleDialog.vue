<template>
  <!-- 售后处理弹窗:订单详情、售后订单列表共用,原地处理,不用跳页。
       传 refund-id 只看这一张售后;传 order-id 列出这一单的全部售后。处理完 emit('changed') 让调用方刷新 -->
  <el-dialog :title="title" :visible.sync="dialogVisible" width="620px" append-to-body top="8vh"
             custom-class="refund-handle-dialog" @open="load" @closed="onClosed">
    <div v-loading="loading" class="rh-body">
      <div v-if="!loading && !list.length" class="rh-empty">这单没有售后</div>

      <div v-for="r in list" :key="r.id" class="rh-card">
        <div class="rh-head">
          <el-tag size="mini" effect="dark" :type="statusTag(r.status)">{{ statusText(r.status) }}</el-tag>
          <el-tag size="mini" effect="plain" :type="typeTag(r.type)">{{ typeText(r.type) }}</el-tag>
          <span class="rh-no">售后单号 {{ r.order_no }}</span>
          <span class="rh-time">{{ r.created_at_text }}</span>
        </div>

        <div class="rh-goods">
          <el-image :src="r.cover_pic" fit="cover" class="rh-thumb">
            <div slot="error" class="rh-thumb-err"><i class="el-icon-picture-outline"></i></div>
          </el-image>
          <div class="rh-goods-main">
            <div class="rh-goods-name">{{ r.goods_name }}</div>
            <div class="rh-goods-sub">
              <span v-for="(a, i) in r.attr" :key="i" class="rh-spec">{{ a.value || a.attr_name }}</span>
              <span class="rh-num">x{{ r.num }}</span>
            </div>
          </div>
          <div class="rh-amount">
            <div class="rh-amount-label">退款金额</div>
            <div class="rh-amount-value">¥{{ r.refund_price }}</div>
          </div>
        </div>

        <div class="rh-info">
          <div class="rh-row"><span class="rh-label">申请人</span>{{ r.nickname || '-' }} {{ r.mobile || '' }}</div>
          <div class="rh-row"><span class="rh-label">原因</span>{{ r.reason || '未填写' }}</div>
          <div v-if="r.content" class="rh-row"><span class="rh-label">补充说明</span>{{ r.content }}</div>
          <div v-if="r.pic_list && r.pic_list.length" class="rh-row">
            <span class="rh-label">凭证</span>
            <el-image v-for="(p, i) in r.pic_list" :key="i" :src="p" :preview-src-list="r.pic_list" fit="cover" class="rh-pic"></el-image>
          </div>
          <div v-if="r.return_address" class="rh-row">
            <span class="rh-label">寄回地址</span>{{ r.return_name }} {{ r.return_mobile }} {{ r.return_address }}
          </div>
          <div v-if="r.user_express_no" class="rh-row">
            <span class="rh-label">顾客寄回</span>{{ r.user_express }} {{ r.user_express_no }}
          </div>
          <div v-if="r.status === 2" class="rh-row"><span class="rh-label">拒绝原因</span>{{ r.remark || '无' }}</div>
          <div v-if="r.status === 6 && r.refund_task" class="rh-row">
            <span class="rh-label">退款进度</span>
            <span :class="'rh-task ' + (r.refund_task.attention ? 'is-attention' : '')">{{ r.refund_task.text }}</span>
            <el-button v-if="r.refund_task.attention" type="text" size="mini" @click="goRefundDesk(r)">去处理台</el-button>
          </div>
        </div>

        <!-- 待审核:同意 / 拒绝 -->
        <div v-if="r.status === 0" class="rh-actions">
          <template v-if="mode[r.id] === 'agree'">
            <div v-if="r.type !== 1" class="rh-form">
              <div class="rh-tip info"><i class="el-icon-info"></i> 顾客需寄回商品,请填写寄回地址</div>
              <el-input v-model="form(r).return_name" size="small" placeholder="收货人"></el-input>
              <el-input v-model="form(r).return_mobile" size="small" placeholder="电话"></el-input>
              <el-input v-model="form(r).return_address" size="small" type="textarea" :rows="2" placeholder="详细地址"></el-input>
            </div>
            <div v-else class="rh-tip warning"><i class="el-icon-warning"></i> 仅退款:同意后还要点「确认打款」才会退钱</div>
            <div class="rh-btns">
              <el-button size="small" @click="setMode(r, null)">取消</el-button>
              <el-button size="small" type="primary" :loading="busy === r.id" @click="agree(r)">确认同意</el-button>
            </div>
          </template>
          <template v-else-if="mode[r.id] === 'reject'">
            <el-input v-model="form(r).remark" size="small" type="textarea" :rows="2" maxlength="200" show-word-limit
                      placeholder="拒绝原因(顾客能看到)"></el-input>
            <div class="rh-btns">
              <el-button size="small" @click="setMode(r, null)">取消</el-button>
              <el-button size="small" type="danger" :loading="busy === r.id" @click="reject(r)">确认拒绝</el-button>
            </div>
          </template>
          <div v-else class="rh-btns">
            <el-button size="small" type="danger" plain @click="setMode(r, 'reject')">拒绝</el-button>
            <el-button size="small" type="primary" @click="setMode(r, 'agree')">同意</el-button>
          </div>
        </div>

        <!-- 处理中 / 顾客已寄回:确认打款 或 完成换货 -->
        <div v-else-if="canProcess(r)" class="rh-actions">
          <div class="rh-tip warning"><i class="el-icon-warning"></i> {{ processTip(r) }}</div>
          <div class="rh-btns">
            <el-button size="small" :type="r.type === 3 ? 'success' : 'warning'" :loading="busy === r.id" @click="process(r)">
              {{ r.type === 3 ? '完成换货' : '确认打款' }}
            </el-button>
          </div>
        </div>
      </div>
    </div>
    <span slot="footer">
      <el-button size="small" @click="dialogVisible = false">关闭</el-button>
    </span>
  </el-dialog>
</template>

<script>
export default {
  name: 'RefundHandleDialog',
  props: {
    visible: { type: Boolean, default: false },
    refundId: { type: [Number, String], default: 0 },
    orderId: { type: [Number, String], default: 0 }
  },
  data() {
    return {
      loading: false,
      busy: 0,
      list: [],
      mode: {},
      forms: {},
      changed: false
    }
  },
  computed: {
    dialogVisible: {
      get() { return this.visible },
      set(v) { this.$emit('update:visible', v) }
    },
    title() {
      return this.refundId ? '处理售后' : '这单的售后'
    }
  },
  methods: {
    async load() {
      this.loading = true
      try {
        const data = { status: -1, limit: 50, page: 1 }
        if (this.refundId) data.id = this.refundId
        else data.order_id = this.orderId
        const res = await this.$request({ url: '/mall/order/refund', method: 'get', data })
        if (res.code === 0) {
          this.list = (res.data.list || []).map(r => ({ ...r, status: Number(r.status), type: Number(r.type), is_refund: Number(r.is_refund) }))
        } else {
          this.$message.error(res.msg)
        }
      } finally {
        this.loading = false
      }
    },
    form(r) {
      if (!this.forms[r.id]) this.$set(this.forms, r.id, { return_name: '', return_mobile: '', return_address: '', remark: '' })
      return this.forms[r.id]
    },
    setMode(r, m) {
      this.$set(this.mode, r.id, m)
    },
    async submit(r, url, data, okText) {
      this.busy = r.id
      try {
        const res = await this.$request({ url, method: 'post', data })
        if (res.code === 0) {
          this.$message.success(res.msg && res.msg !== 'success' ? res.msg : okText)
          this.setMode(r, null)
          this.changed = true
          this.$emit('changed')
          await this.load()
        } else {
          this.$message.error(res.msg)
        }
      } finally {
        this.busy = 0
      }
    },
    agree(r) {
      const f = this.form(r)
      if (r.type !== 1 && (!f.return_name.trim() || !f.return_mobile.trim() || !f.return_address.trim())) {
        this.$message.warning('请填写完整的寄回地址')
        return
      }
      this.submit(r, '/mall/order/refund-status', {
        id: r.id, status: 1, return_name: f.return_name, return_mobile: f.return_mobile, return_address: f.return_address
      }, '已同意')
    },
    reject(r) {
      const f = this.form(r)
      if (!f.remark.trim()) {
        this.$message.warning('请填写拒绝原因')
        return
      }
      this.submit(r, '/mall/order/refund-status', { id: r.id, status: 2, remark: f.remark }, '已拒绝')
    },
    // 处理中(1)或顾客已寄回(5):换货等「完成换货」,退款类还没打款的等「确认打款」
    canProcess(r) {
      if (![1, 5].includes(r.status)) return false
      return r.type === 3 || ((r.type === 1 || r.type === 2) && r.is_refund === 0)
    },
    processTip(r) {
      if (r.type === 3) return '确认已收到退货、并已寄出换货商品?'
      if (r.type === 1) return '确认后立即按原支付方式退款给顾客,这件商品作废,不能撤回'
      return '确认已收到退货?确认后立即退款给顾客,这件商品作废,不能撤回'
    },
    process(r) {
      this.$confirm(this.processTip(r), r.type === 3 ? '完成换货' : '确认打款', { type: 'warning' }).then(() => {
        if (r.type === 3) this.submit(r, '/mall/order/refund-exchange-complete', { id: r.id }, '已完成换货')
        else this.submit(r, '/mall/order/refund-pay', { id: r.id }, '已发起退款')
      }).catch(() => {})
    },
    goRefundDesk(r) {
      this.dialogVisible = false
      this.$router.push({ path: '/finance/refund-fail', query: { keyword: r.refund_task.out_refund_no } })
    },
    onClosed() {
      this.list = []
      this.mode = {}
      this.forms = {}
      this.changed = false
    },
    statusText(s) {
      return { 0: '待审核', 1: '处理中', 2: '已拒绝', 3: '已完成', 4: '已关闭', 5: '顾客已寄回', 6: '退款中' }[s] || '未知'
    },
    statusTag(s) {
      return { 0: 'warning', 1: 'primary', 2: 'danger', 3: 'success', 4: 'info', 5: 'warning', 6: 'warning' }[s] || 'info'
    },
    typeText(t) {
      return { 1: '仅退款', 2: '退货退款', 3: '换货' }[t] || '未知'
    },
    typeTag(t) {
      return { 1: 'warning', 2: 'danger', 3: 'success' }[t] || 'info'
    }
  }
}
</script>

<style lang="scss" scoped>
.rh-body { min-height: 120px; max-height: 68vh; overflow-y: auto; }
.rh-empty { text-align: center; color: #909399; padding: 40px 0; }
.rh-card {
  border: 1px solid #ebeef5;
  border-radius: 8px;
  padding: 12px 14px;
  & + .rh-card { margin-top: 12px; }
}
.rh-head {
  display: flex;
  align-items: center;
  gap: 8px;
  .rh-no { color: #606266; font-size: 12px; }
  .rh-time { margin-left: auto; color: #909399; font-size: 12px; }
}
.rh-goods {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 10px;
  .rh-thumb { width: 52px; height: 52px; border-radius: 6px; flex-shrink: 0; background: #f5f7fa; }
  .rh-thumb-err { display: flex; align-items: center; justify-content: center; height: 100%; color: #c0c4cc; }
  .rh-goods-main { flex: 1; min-width: 0; }
  .rh-goods-name { font-size: 13px; color: #303133; }
  .rh-goods-sub { margin-top: 4px; color: #909399; font-size: 12px; display: flex; gap: 6px; }
  .rh-spec { background: #f4f4f5; padding: 0 5px; border-radius: 4px; }
  .rh-amount { text-align: right; }
  .rh-amount-label { font-size: 12px; color: #909399; }
  .rh-amount-value { font-size: 16px; font-weight: 600; color: #F56C6C; }
}
.rh-info {
  margin-top: 10px;
  background: #fafafa;
  border-radius: 6px;
  padding: 8px 10px;
  font-size: 12px;
  color: #606266;
  .rh-row { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; line-height: 22px; }
  .rh-label { color: #909399; width: 60px; flex-shrink: 0; }
  .rh-pic { width: 44px; height: 44px; border-radius: 4px; }
  .rh-task.is-attention { color: #F56C6C; }
}
.rh-actions {
  margin-top: 10px;
  .rh-form { display: flex; flex-direction: column; gap: 6px; }
  .rh-btns { display: flex; justify-content: flex-end; gap: 8px; margin-top: 8px; }
}
.rh-tip {
  font-size: 12px;
  padding: 6px 10px;
  border-radius: 4px;
  margin-bottom: 6px;
  &.info { color: #409EFF; background: #ecf5ff; }
  &.warning { color: #E6A23C; background: #fdf6ec; }
}
</style>
