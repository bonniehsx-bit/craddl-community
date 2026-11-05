/**
 * 订单详情 Shell 共享工具函数
 */

export function fmtPrice(val) {
    return val ? parseFloat(val).toFixed(2) : '0.00';
}

export function formatTime(timestamp) {
    if (!timestamp) return '';

    // 如果后端已经格式化过（字符串，非纯数字），直接透传
    // 典型情况：`OrderForm::detail` 已调 `date('Y-m-d H:i:s', ...)`，
    //           此时二次 `new Date(str)` 在 iOS/小程序会变 Invalid Date → 三行时间全 NaN
    if (typeof timestamp === 'string' && !/^\d+$/.test(timestamp)) {
        return timestamp;
    }

    // 纯时间戳场景（10 位秒或 13 位毫秒）
    let ts = Number(timestamp);
    if (String(Math.floor(ts)).length === 10) ts *= 1000;
    const d = new Date(ts);
    if (isNaN(d.getTime())) return String(timestamp);

    const pad = n => n.toString().padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export function hexToRgba(hex, alpha = 1) {
    if (!hex || hex.length < 7) return `rgba(230,61,61,${alpha})`;
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r},${g},${b},${alpha})`;
}

export function copyText(text) {
    uni.setClipboardData({
        data: text,
        success: () => uni.showToast({ title: '复制成功', icon: 'none' })
    });
}

/**
 * 按 action.data_key 从当前订单上下文解析 emit 数据
 * data_key 约定：
 *   · 'order'     → 整单
 *   · 'list[0]'   → 第一条 order_detail
 *   · 'null' / 空 → 传 null（如 express 查物流不需要参数）
 *   · 其他        → ctx[data_key]
 */
export function resolveActionData(action, ctx) {
    const key = action.data_key || 'order';
    if (!key || key === 'null') return null;
    if (key === 'order') return ctx.order;
    if (key === 'list[0]') return (ctx.list && ctx.list[0]) || null;
    return ctx[key] || null;
}

/**
 * 兜底状态文案（仅当后端未通过 mobile_actions 下发时使用）
 */
export function fallbackStatusInfo(order) {
    if (!order) return { text: '', desc: '' };
    const map = {
        0: { text: '交易关闭', desc: '订单已取消或关闭' },
        10: { text: '待付款',   desc: '请尽快完成支付' },
        20: { text: '待发货',   desc: '商家正在打包中' },
        30: { text: '待收货',   desc: '商品运输中' },
        40: { text: '交易完成', desc: '期待再次光临' },
    };
    return map[order.status] || { text: '订单详情', desc: '' };
}
