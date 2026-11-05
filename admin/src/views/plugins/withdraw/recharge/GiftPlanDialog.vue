<template>
  <el-dialog title="分期赠品" :visible.sync="show" :width="dialogWidth" append-to-body top="6vh"
             :close-on-click-modal="false" @closed="$emit('closed')">
    <el-alert type="info" :closable="false" show-icon style="margin-bottom: 14px;">
      <template slot="title">档位「{{ planTitle || '未命名' }}」的赠品分期方案</template>
      <div class="tip">
        · 到账时间一律<b>从充值成功那一刻算起</b>（充值日 +N 天/周/月），就是天猫那种「09.21 到账 / 10.21 到账」。<br>
        · <b>每一期到账都会给用户推送提醒</b>——分期的价值就在这里，否则只是"少送点"。<br>
        · <span style="color:#E6A23C;">延后发放的期次只能选「领取后 N 天有效」的券。</span>
        固定有效期的券等到该发那天早过期了，用户收到的是点开就是灰的废券，而系统不会报错。
      </div>
    </el-alert>

    <!-- 快速生成 -->
    <div class="gen-bar">
      <span class="gen-label">快速生成</span>
      <el-select v-model="gen.mode" size="mini" style="width: 110px;">
        <el-option label="按天" value="day" />
        <el-option label="按周" value="week" />
        <el-option label="按月" value="month" />
      </el-select>
      <span class="gen-txt">每</span>
      <el-input-number v-model="gen.step" :min="1" :controls="false" size="mini" style="width: 60px;" />
      <span class="gen-txt">{{ gen.mode === 'day' ? '天' : (gen.mode === 'week' ? '周' : '个月') }}一期，共</span>
      <el-input-number v-model="gen.periods" :min="1" :max="36" :controls="false" size="mini" style="width: 60px;" />
      <span class="gen-txt">期，每期</span>
      <el-select v-model="gen.type" size="mini" style="width: 100px;">
        <el-option label="送券" value="coupon" />
        <el-option label="送赠金" value="asset" />
      </el-select>
      <el-select v-if="gen.type === 'coupon'" v-model="gen.couponId" size="mini" filterable
                 placeholder="选券" style="width: 240px;">
        <el-option v-for="c in coupons" :key="c.id" :label="couponLabel(c)" :value="c.id" />
      </el-select>
      <el-input-number v-else v-model="gen.amount" :min="0.01" :precision="2" :controls="false"
                       size="mini" style="width: 90px;" />
      <el-input-number v-if="gen.type === 'coupon'" v-model="gen.num" :min="1" :controls="false"
                       size="mini" style="width: 56px;" />
      <span v-if="gen.type === 'coupon'" class="gen-txt">张</span>
      <el-checkbox v-model="gen.firstImmediate" style="margin-left: 8px;">首期充值立返</el-checkbox>
      <el-button type="primary" size="mini" @click="generate">生成</el-button>
    </div>
    <div class="gen-hint">
      生成的是<b>可再编辑的明细</b>——比如生成 3 期后，把第 1 期单独改成 2 张。
      规则只用来省事，落库的永远是下面这张表。
    </div>

    <el-table :data="rows" border size="mini" empty-text="还没有配置分期赠品，用上面的「快速生成」或「添加一期」">
      <el-table-column label="期次" width="60" align="center">
        <template slot-scope="{ $index }">第 {{ $index + 1 }} 期</template>
      </el-table-column>
      <el-table-column label="赠品类型" width="120" align="center">
        <template slot-scope="{ row }">
          <el-select v-model="row.gift_type" size="mini" style="width: 92px;">
            <el-option label="优惠券" value="coupon" />
            <el-option label="赠金" value="asset" />
          </el-select>
        </template>
      </el-table-column>
      <el-table-column label="赠品内容" min-width="360">
        <template slot-scope="{ row }">
          <template v-if="row.gift_type === 'coupon'">
            <el-select v-model="row.coupon_id" size="mini" filterable placeholder="选择优惠券" style="width: 300px;">
              <el-option v-for="c in coupons" :key="c.id" :label="couponLabel(c)" :value="c.id" />
            </el-select>
            <el-input-number v-model="row.num" :min="1" :controls="false" size="mini" style="width: 56px; margin-left:6px;" />
            <span class="unit">张</span>
            <div v-if="fixedDateWarn(row)" class="row-err">
              ⚠ 这张券是固定有效期，不能用在延后发放的期次上（保存会被拦）
            </div>
          </template>
          <template v-else>
            <el-input-number v-model="row.amount" :min="0.01" :precision="2" :controls="false"
                             size="mini" style="width: 110px;" />
            <span class="unit">{{ assetLabel }}</span>
          </template>
        </template>
      </el-table-column>
      <el-table-column label="到账时机" width="260" align="center">
        <template slot-scope="{ row }">
          <el-select v-model="row.delay_mode" size="mini" style="width: 100px;">
            <el-option label="充值立返" value="immediate" />
            <el-option label="N 天后" value="day" />
            <el-option label="N 周后" value="week" />
            <el-option label="N 个月后" value="month" />
          </el-select>
          <el-input-number v-if="row.delay_mode !== 'immediate'" v-model="row.delay_value"
                           :min="1" :controls="false" size="mini" style="width: 60px; margin-left:6px;" />
        </template>
      </el-table-column>
      <el-table-column label="用户看到的" min-width="200">
        <template slot-scope="{ row }">
          <span class="preview">{{ previewText(row) }}</span>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="90" align="center">
        <template slot-scope="{ $index }">
          <el-button type="text" size="mini" icon="el-icon-top" :disabled="$index === 0" @click="move($index, -1)" />
          <el-button type="text" size="mini" style="color:#f56c6c;" icon="el-icon-delete" @click="rows.splice($index, 1)" />
        </template>
      </el-table-column>
    </el-table>

    <el-button type="primary" plain icon="el-icon-plus" size="mini" style="margin-top: 10px;" @click="addRow">
      添加一期
    </el-button>

    <div class="summary" v-if="rows.length">
      <b>用户在充值页会看到：</b>「赠品分 {{ rows.length }} 期」
      <span class="chips">
        <span class="chip" v-for="(r, i) in rows" :key="i">{{ previewText(r) }}</span>
      </span>
    </div>

    <div slot="footer">
      <el-button size="small" @click="show = false">取 消</el-button>
      <el-button type="primary" size="small" @click="confirm">确 定</el-button>
    </div>
  </el-dialog>
</template>

<script>
export default {
  name: 'GiftPlanDialog',
  props: {
    visible: { type: Boolean, default: false },
    // 档位对象（直接改它的 gifts）
    plan: { type: Object, default: null },
    assetLabel: { type: String, default: '元' }
  },
  data() {
    return {
      dialogWidth: '1180px',
      show: false,
      rows: [],
      coupons: [],
      gen: {
        mode: 'month', step: 1, periods: 3, type: 'coupon',
        couponId: '', num: 1, amount: 10, firstImmediate: true
      }
    }
  },
  computed: {
    planTitle() { return this.plan ? this.plan.title : '' }
  },
  watch: {
    visible(v) {
      this.show = v
      if (v) {
        // ⚠ 每次打开都重算,不能在 data() 里算一次就完
        //   —— 组件跟着页面创建,用户开弹窗前调整过浏览器窗口,那个值就是旧的
        this.dialogWidth = this.calcWidth()
        // 深拷贝：取消时不能把用户的编辑留在档位上
        this.rows = JSON.parse(JSON.stringify((this.plan && this.plan.gifts) || []))
        this.loadCoupons()
      }
    },
    show(v) { if (!v) this.$emit('update:visible', false) }
  },
  methods: {
    // 这张表有 6 列(券下拉 300 + 两个数字框 + 到账时机两个控件),
    // 固定 900px 在 1440 屏上会把「赠品内容」挤到换行。
    // 上限 1400:再宽表格就散开了,视线要跨很远,反而难读
    calcWidth() {
      const w = (typeof window !== 'undefined' && window.innerWidth) || 1440
      return Math.min(1400, Math.max(900, Math.floor(w * 0.9))) + 'px'
    },
    loadCoupons() {
      if (this.coupons.length) return
      this.$request({ url: '/plugin/marketing/mall/coupon/list', method: 'get', data: { page: 1, limit: 500 } })
        .then(res => {
          if (res.code === 0) this.coupons = (res.data && res.data.list) || []
        })
    },
    couponLabel(c) {
      // time_type: 1=固定日期 其它=领取后N天。标出来，运营选的时候就知道能不能用在延后期次
      const t = Number(c.time_type) === 1 ? '固定有效期' : ('领取后' + (c.day || '?') + '天')
      return `${c.name}（${t}） #${c.id}`
    },
    couponOf(id) { return this.coupons.find(c => c.id === Number(id)) },
    // 固定有效期的券 + 延后发放 = 到期就是废券，这里提前红字提示（后端也会拦）
    fixedDateWarn(row) {
      if (row.gift_type !== 'coupon' || row.delay_mode === 'immediate') return false
      const c = this.couponOf(row.coupon_id)
      return !!c && Number(c.time_type) === 1
    },
    previewText(row) {
      const when = row.delay_mode === 'immediate'
        ? '充值立返'
        : `${row.delay_value || 1}${row.delay_mode === 'day' ? '天' : (row.delay_mode === 'week' ? '周' : '个月')}后`
      if (row.gift_type === 'asset') return `${when} ${row.amount || 0}${this.assetLabel}`
      const c = this.couponOf(row.coupon_id)
      return `${when} ${c ? c.name : '优惠券'}×${row.num || 1}`
    },
    blankRow(over) {
      return Object.assign({
        id: 0, gift_type: 'coupon', coupon_id: '', num: 1,
        asset_type: '', amount: 0, delay_mode: 'immediate', delay_value: 1
      }, over || {})
    },
    addRow() { this.rows.push(this.blankRow()) },
    move(i, d) {
      const t = this.rows[i]
      this.$set(this.rows, i, this.rows[i + d])
      this.$set(this.rows, i + d, t)
    },
    generate() {
      const g = this.gen
      if (g.type === 'coupon' && !g.couponId) return this.$message.warning('请先选择要送的优惠券')
      if (g.type === 'asset' && !(g.amount > 0)) return this.$message.warning('赠金金额要大于 0')
      const out = []
      for (let i = 0; i < g.periods; i++) {
        // 首期立返：第 1 期当场发，之后每隔 step 一期。不勾的话第 1 期也要等一个周期
        const immediate = g.firstImmediate && i === 0
        out.push(this.blankRow({
          gift_type: g.type,
          coupon_id: g.type === 'coupon' ? g.couponId : '',
          num: g.num,
          amount: g.type === 'asset' ? g.amount : 0,
          delay_mode: immediate ? 'immediate' : g.mode,
          delay_value: immediate ? 0 : (g.firstImmediate ? i * g.step : (i + 1) * g.step)
        }))
      }
      this.rows = out
      this.$message.success(`已生成 ${out.length} 期，可以再逐期调整`)
    },
    confirm() {
      // 空行在后端也会被丢，但这里先提示，免得运营以为配上了
      const bad = this.rows.filter(r =>
        (r.gift_type === 'coupon' && !r.coupon_id) || (r.gift_type === 'asset' && !(r.amount > 0)))
      if (bad.length) return this.$message.warning(`有 ${bad.length} 期没有填写赠品内容，保存后会被丢弃`)
      const warn = this.rows.filter(r => this.fixedDateWarn(r))
      if (warn.length) return this.$message.error(`有 ${warn.length} 期用了固定有效期的券且是延后发放，保存会被拦下`)

      this.$emit('confirm', JSON.parse(JSON.stringify(this.rows)))
      this.show = false
    }
  }
}
</script>

<style scoped>
.tip { font-size: 12px; line-height: 1.9; margin-top: 4px; }
.gen-bar {
  display: flex; align-items: center; flex-wrap: wrap; gap: 8px;
  padding: 12px 14px; background: #f7f8fa; border-radius: 6px; margin-bottom: 6px;
  /* 宽度够了就一行排开;真放不下(小屏)才允许折行,不至于被裁掉 */
  white-space: nowrap;
}
.gen-label { font-size: 13px; font-weight: 600; color: #606266; margin-right: 4px; }
.gen-txt { font-size: 13px; color: #606266; }
.gen-hint { font-size: 12px; color: #909399; margin: 0 0 12px 2px; }
.unit { font-size: 12px; color: #909399; margin-left: 4px; }
.row-err { font-size: 12px; color: #f56c6c; margin-top: 4px; line-height: 1.6; }
.preview { font-size: 12px; color: var(--admin-accent-text); }
.summary {
  margin-top: 14px; padding: 12px; background: #FFF8F0;
  border-radius: 6px; font-size: 13px; color: #C2410C;
}
.chips { display: inline-flex; flex-wrap: wrap; gap: 6px; margin-left: 6px; }
.chip {
  background: #fff; border: 1px dashed #F0C89A; border-radius: 4px;
  padding: 2px 8px; font-size: 12px; color: #A16207;
}
</style>
