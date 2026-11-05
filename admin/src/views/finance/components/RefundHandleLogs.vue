<template>
  <!-- 退款的人工处理记录(查询结果 / 重新发起 / 登记已退款):「退款失败」处理台和「退款记录」详情共用 -->
  <div class="refund-handle-logs">
    <div class="log-title">处理记录</div>
    <div v-if="!logs.length" class="log-empty">{{ emptyText }}</div>
    <el-timeline v-else>
      <el-timeline-item v-for="(l, i) in logs" :key="i" :timestamp="l.created_at" :type="l.result ? 'success' : 'info'">
        <div>{{ l.operator_name || '系统' }} · {{ l.action_text }}{{ l.result ? '' : '(未成功)' }}</div>
        <div v-if="l.remark" class="log-remark">{{ l.remark }}</div>
        <div v-if="l.detail" class="log-detail">{{ l.detail }}</div>
        <el-image v-if="l.voucher" :src="l.voucher" :preview-src-list="[l.voucher]" class="log-voucher" fit="cover" />
      </el-timeline-item>
    </el-timeline>
  </div>
</template>

<script>
export default {
  name: 'RefundHandleLogs',
  props: {
    logs: { type: Array, default: () => [] },
    emptyText: { type: String, default: '还没有人处理过' }
  }
}
</script>

<style lang="scss" scoped>
.log-title { font-weight: 600; margin: 8px 0 12px; }
.log-empty { color: #909399; font-size: 13px; }
.log-remark { color: #606266; margin-top: 4px; }
.log-detail { color: #909399; font-size: 12px; margin-top: 4px; word-break: break-all; }
.log-voucher { width: 80px; height: 80px; margin-top: 6px; border-radius: 4px; }
</style>
