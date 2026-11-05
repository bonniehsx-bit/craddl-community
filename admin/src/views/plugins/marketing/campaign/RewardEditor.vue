<template>
  <div class="reward-editor">
    <div v-for="(r, i) in value" :key="i" class="reward-row">
      <el-select :value="r.reward_type" size="small" class="type-col" @change="v => update(i, { reward_type: v, coupon_id: '', amount: 0, num: 1 })">
        <el-option :label="$noun('coupon')" value="coupon"></el-option>
        <el-option :label="$noun('integral')" value="integral"></el-option>
        <el-option :label="$noun('balance')" value="balance"></el-option>
      </el-select>

      <template v-if="r.reward_type === 'coupon'">
        <el-select :value="r.coupon_id" filterable size="small" :placeholder="'选择平台' + $noun('coupon')" class="grow"
          @change="v => update(i, { coupon_id: v })">
          <el-option v-for="opt in couponOptions" :key="opt.coupon_id" :label="opt.name + ' (' + opt.desc + ')'" :value="opt.coupon_id"></el-option>
        </el-select>
        <el-input-number :value="r.num" :min="1" size="small" class="num-col" @change="v => update(i, { num: v })"></el-input-number>
        <span class="unit">张</span>
      </template>
      <template v-else>
        <el-input-number :value="r.amount" :min="0" :precision="r.reward_type === 'balance' ? 2 : 0" :controls="false" size="small" class="grow"
          :placeholder="r.reward_type === 'balance' ? '金额' : '数量'" @change="v => update(i, { amount: v })"></el-input-number>
        <span class="unit">{{ r.reward_type === 'balance' ? '元' : $noun('integral') }}</span>
      </template>

      <el-button type="text" size="mini" class="del" @click="remove(i)">删除</el-button>
    </div>
    <div v-if="!value.length" class="empty">暂无奖励</div>
    <el-button type="text" size="mini" icon="el-icon-plus" @click="add">添加奖励</el-button>
  </div>
</template>

<script>
export default {
  name: 'RewardEditor',
  props: {
    value: { type: Array, default: () => [] },
    couponOptions: { type: Array, default: () => [] },
  },
  methods: {
    update(i, patch) {
      this.$emit('input', this.value.map((r, idx) => (idx === i ? Object.assign({}, r, patch) : r)));
    },
    remove(i) {
      this.$emit('input', this.value.filter((r, idx) => idx !== i));
    },
    add() {
      this.$emit('input', this.value.concat([{ reward_type: 'coupon', coupon_id: '', amount: 0, num: 1 }]));
    },
  },
};
</script>

<style lang="scss" scoped>
.reward-editor { display: flex; flex-direction: column; gap: 8px; }
.reward-row {
  display: flex; align-items: center; gap: 8px;
  background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 12px;
}
.type-col { width: 100px; flex-shrink: 0; }
.grow { flex: 1; min-width: 0; }
.num-col { width: 110px; flex-shrink: 0; }
.unit { font-size: 13px; color: #475569; white-space: nowrap; }
.del { color: #F56C6C; flex-shrink: 0; }
.empty { font-size: 12px; color: #94a3b8; }
</style>
