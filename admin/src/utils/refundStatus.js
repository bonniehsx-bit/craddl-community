export const REFUND_STATUS = {
  PENDING: { value: 0, text: '待审核', type: 'primary' },
  PROCESSING: { value: 1, text: '已同意(处理中)', type: 'warning' },
  REJECTED: { value: 2, text: '已拒绝', type: 'info' },
  COMPLETED: { value: 3, text: '已完成', type: 'success' },
  CLOSED: { value: 4, text: '已关闭', type: 'danger' },
  // 【新增】用户已发货状态
  USER_SHIPPED: { value: 5, text: '用户已发货', type: 'warning' }
};

// 为了方便在模板中使用
// 这部分代码无需任何改动，它会自动将上面新增的状态包含进去
export const statusMap = Object.values(REFUND_STATUS).reduce((acc, current) => {
  acc[current.value] = {
    text: current.text,
    type: current.type
  };
  return acc;
}, {});
