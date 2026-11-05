<template>
  <div style="height: 700px; overflow-y: auto; padding: 10px;">
    <el-row>
      <el-col :span="16">

        <el-form-item label="开启会员价" prop="is_member_price">
          <el-radio-group v-model="form.is_member_price" @change="memberPriceChange">
            <el-radio :label="0">不开启</el-radio>
            <el-radio :label="1">开启</el-radio>
          </el-radio-group>
        </el-form-item>

        <div v-if="form.is_member_price == 1" style="background: #f7f7f7; padding: 20px; border-radius: 5px;">
          <div style="font-size: 12px; color: #999; line-height: 1.5; margin-bottom: 15px;">
            <span>
              适用多规格商品。例如输入 <b>9.5</b>，代表打95折。最终价格 = 对应规格原价 × 0.95
            </span>
          </div>

          <div class="dc-calc">
            <div class="dc-title">
              <i class="el-icon-s-opportunity"></i> 折扣计算器
              <span class="dc-sub">不知道折扣填多少？填原价和想卖的价，这里帮你算</span>
            </div>
            <div class="dc-body">
              <span class="dc-text">原价</span>
              <el-input class="dc-num" v-model="calc.origin" type="number" min="0" size="small" placeholder="200" />
              <span class="dc-text">元，想卖</span>
              <el-input class="dc-num" v-model="calc.target" type="number" min="0" size="small" placeholder="100" />
              <span class="dc-text">元 →</span>

              <template v-if="calcResult.ok">
                <span class="dc-out">折扣填 <b>{{ calcResult.discount }}</b> 折</span>
                <el-select class="dc-sel" v-model="calc.levelId" size="small" placeholder="填入哪个等级">
                  <el-option v-for="l in form.level_price" :key="l.level_id"
                             :label="l.level_name" :value="l.level_id"></el-option>
                </el-select>
                <el-button type="primary" size="mini" @click="applyDiscount">填入</el-button>
              </template>
              <span v-else-if="calcResult.msg" class="dc-err">{{ calcResult.msg }}</span>
            </div>
            <div v-if="calcResult.ok && calcResult.diff" class="dc-warn">
              注意：折扣按 2 位小数计，实际售价是 <b>{{ calcResult.real }}</b> 元，
              与你想要的 {{ calc.target }} 元差 {{ calcResult.diff }} 元。
              想卖得刚好，就调整一下想卖的价格。
            </div>
          </div>

          <el-divider></el-divider>

          <div class="flex-row" style="font-weight: bold; display: flex; margin-bottom: 10px;">
            <div style="width: 150px;">等级名称</div>
            <div style="width: 250px;">折扣率 (0.1 ~ 10)</div>
          </div>

          <div class="flex-row" style="display: flex; margin-bottom: 15px; align-items: center;"
               v-if="form.level_price.length > 0"
               v-for="(item, i) in form.level_price" :key="i">

            <div style="width: 150px; line-height: 28px;">{{ item.level_name }}</div>

            <div style="width: 250px;">
              <el-input
                v-model="item.discount"
                type="number"
                min="0.1"
                max="10"
                step="0.1"
                placeholder="如 9.5"
                size="small"
                @input="validateDiscount(item)"
              >
                <template slot="append">折</template>
              </el-input>
            </div>
          </div>

          <el-button type="text" size="mini" @click="freshMemberPrice" style="color: #F56C6C;">
            <i class="el-icon-refresh"></i> 重置配置
          </el-button>
        </div>

      </el-col>
    </el-row>
  </div>
</template>

<script>
export default {
  name: "TabMemberPrice",
  props: {
    form: {
      type: Object,
      required: true
    },
    levelList: {
      type: Array,
      default: () => []
    }
  },

  data() {
    return {
      calc: {origin: '', target: '', levelId: ''}
    }
  },

  computed: {
    /**
     * 折扣 = 想卖的价 / 原价 × 10。
     * 折扣保留 2 位小数后，实际售价未必等于想卖的价（后端算价是
     * 原价 × 折扣/10，bcdiv 取 4 位小数），所以把实际售价一并算出来提示，
     * 否则用户填完以为能卖 100，实收 100.60 却不知道为什么。
     */
    calcResult() {
      const o = parseFloat(this.calc.origin)
      const t = parseFloat(this.calc.target)
      if (!(o > 0) || this.calc.target === '' || isNaN(t)) {
        return {ok: false, msg: ''}
      }
      if (t < 0) {
        return {ok: false, msg: '想卖的价不能是负数'}
      }
      if (t > o) {
        return {ok: false, msg: '想卖的价比原价还高，不是折扣'}
      }
      const raw = t / o * 10
      if (raw < 0.1) {
        return {ok: false, msg: '折扣低于 0.1 折，系统支持的最低折扣是 0.1'}
      }
      if (raw > 10) {
        return {ok: false, msg: '折扣超过 10 折'}
      }

      // 后端算价：ratio = 折扣/10 取 6 位小数(截断) → 价格 = 原价 × ratio 取 2 位(截断)。
      // 这里必须**同口径反推**，不能简单四舍五入 —— 折扣位数少一位，
      // 299 想卖 199 就会变成 199.01 或 198.98，永远差那一分钱。
      const priceOf = (d) => {
        const ratio = Math.floor(d / 10 * 1e6) / 1e6
        return Math.floor(o * ratio * 100) / 100
      }

      // 从理论折扣出发，在 5 位小数的步长上向两侧微调，取最接近目标价的那个。
      // 截断(而非四舍五入)会让理论值本身偏高半档，所以要往下试。
      const step = 1e-5
      let best = null
      for (let k = -3; k <= 3; k++) {
        const d = Math.round((raw + k * step) / step) * step
        if (d < 0.1 || d > 10) continue
        const dd = parseFloat(d.toFixed(5))
        const p = priceOf(dd)
        const gap = Math.abs(p - t)
        if (best === null || gap < best.gap - 1e-9) {
          best = {d: dd, p: p, gap: gap}
        }
      }
      if (best === null) {
        return {ok: false, msg: '算不出合适的折扣'}
      }

      const diff = Math.round(best.gap * 100) / 100
      return {
        ok: true,
        discount: best.d,
        real: best.p.toFixed(2),
        diff: diff > 0 ? diff.toFixed(2) : 0
      }
    }
  },

  methods: {
    /** 把算出来的折扣填进选中的等级 */
    applyDiscount() {
      if (!this.calcResult.ok) return
      if (!this.calc.levelId) {
        this.$message.warning('请选择要填入哪个等级')
        return
      }
      const row = this.form.level_price.find(x => x.level_id === this.calc.levelId)
      if (!row) {
        this.$message.error('没找到该等级')
        return
      }
      row.discount = this.calcResult.discount
      this.$message.success(`已填入「${row.level_name}」：${this.calcResult.discount} 折`)
    },
    memberPriceChange(val) {
      if (val === 1) {
        this.initPriceData();
      }
    },
    // 限制折扣输入范围
    validateDiscount(item) {
      let val = parseFloat(item.discount);
      if (val > 10) item.discount = 10;
      if (val < 0.1) item.discount = 0.1; // 修复了原版逻辑中 val < 0 却赋值 0.1 的小瑕疵
    },
    freshMemberPrice() {
      this.form.level_price = [];
      this.initPriceData();
    },
    initPriceData() {
      if (this.levelList.length === 0) {
        this.$message.warning('暂无会员等级数据，请先配置等级');
        this.form.is_member_price = 0;
        return;
      }

      if (this.form.level_price.length === 0) {
        let newPrices = [];
        this.levelList.forEach((item) => {
          if (item.id !== '0') {
            newPrices.push({
              level_id: item.id,
              level_name: item.name,
              level: item.level,
              discount: ''    // 仅保留 discount
            });
          }
        });
        this.form.level_price = newPrices;
      } else {
        this.form.level_price.forEach(item => {
          if (!item.hasOwnProperty('discount')) {
            this.$set(item, 'discount', '');
          }
        });
      }
    }
  }
}
</script>

<style scoped>
.dc-calc {
  background: #fff;
  border: 1px solid #ebeef5;
  border-radius: 4px;
  padding: 12px 14px;
  margin-bottom: 15px;
}

.dc-title {
  font-size: 13px;
  font-weight: 600;
  color: #303133;
  margin-bottom: 10px;
}

.dc-title i {
  color: var(--admin-accent-text);
}

.dc-sub {
  font-weight: normal;
  font-size: 12px;
  color: #909399;
  margin-left: 6px;
}

.dc-body {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}

.dc-text {
  font-size: 13px;
  color: #606266;
}

.dc-num {
  width: 100px;
}

.dc-sel {
  width: 130px;
}

.dc-out {
  font-size: 13px;
  color: #303133;
}

.dc-out b {
  color: #F56C6C;
  font-size: 16px;
  margin: 0 2px;
}

.dc-err {
  font-size: 12px;
  color: #F56C6C;
}

.dc-warn {
  margin-top: 8px;
  font-size: 12px;
  color: #E6A23C;
  line-height: 1.6;
}
</style>
