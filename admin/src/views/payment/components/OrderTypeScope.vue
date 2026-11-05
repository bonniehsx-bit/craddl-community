<template>
  <el-form-item label="适用订单类型">
    <el-select v-model="inner" multiple filterable clearable collapse-tags
               placeholder="不选 = 不限，所有订单都能用这个支付方式"
               style="width: 100%; max-width: 520px;" @change="emit">
      <el-option-group v-for="(group, label) in grouped" :key="label" :label="label">
        <el-option v-for="t in group" :key="t.key" :label="t.name" :value="t.key"></el-option>
      </el-option-group>
    </el-select>
    <div class="tips">
      限定这个支付方式能给哪些订单用。<b>留空 = 不限</b>（默认，和以前行为一致）。<br/>
      典型用法：余额支付不勾「余额充值」——否则用户能用余额买余额。<br/>
      <span style="color:#E6A23C;">
        ⚠ 只列出本商城<b>已安装插件</b>的订单类型。停用某个插件后，勾选了它的支付方式对那类订单会失效，
        该类型的历史未支付订单将无法用此方式付款。
      </span>
    </div>
  </el-form-item>
</template>

<script>
/**
 * 支付方式的「适用订单类型」选择器。11 个支付网关配置页共用。
 *
 * 数据源是 /mall/order/order-types（OrderTypeRegistry::all()），
 * 里面已经按本商城授权插件过滤过，并且带 plugin_label 供分组展示。
 *
 * 值直接存进各网关的 setting.order_types —— 后端 PaymentForm 的 setting 是 safe 规则，
 * 原样落进 option payments，不需要为这个字段改任何后端代码。
 */
export default {
  name: 'OrderTypeScope',
  props: {
    value: { type: Array, default: () => [] }
  },
  data() {
    return {
      inner: [],
      types: []
    };
  },
  computed: {
    // 按所属插件分组，运营才分得清「物业账单」是谁家的
    grouped() {
      const g = {};
      this.types.forEach(t => {
        const label = t.plugin_label || '其它';
        if (!g[label]) g[label] = [];
        g[label].push(t);
      });
      return g;
    }
  },
  watch: {
    value: {
      immediate: true,
      handler(v) { this.inner = Array.isArray(v) ? v.slice() : []; }
    }
  },
  created() {
    this.getTypes();
  },
  methods: {
    getTypes() {
      this.$request({ url: '/mall/order/order-types', method: 'get' }).then(res => {
        if (res.code === 0 && res.data && Array.isArray(res.data.list)) {
          this.types = res.data.list;
        }
      }).catch(() => {
        // 取不到就是个空下拉，不影响其它配置项保存
      });
    },
    emit() {
      this.$emit('input', this.inner);
    }
  }
};
</script>
