/**
 * 订单列表 Shell 共享工具
 */

export function fmtPrice(val) {
    return val ? parseFloat(val).toFixed(2) : '0.00';
}

/** 价格拆分：int/decimal，用于大号金额显示（整数大、小数小）*/
export function formatPricePart(price, type) {
    const p = parseFloat(price || 0).toFixed(2);
    const [intP, dec] = p.split('.');
    return type === 'int' ? intP : dec;
}

/** 规格 JSON/字符串 → '颜色:红; 尺寸:M' 拼接展示 */
export function getSpecString(attr) {
    if (!attr) return '';
    let arr = attr;
    if (typeof arr === 'string') {
        try { arr = JSON.parse(arr); } catch (e) { return attr; }
    }
    if (!Array.isArray(arr)) return '';
    return arr
        .map(a => {
            const k = a.group || a.attr_group_name || '';
            const v = a.value || a.attr_name || '';
            return k ? `${k}:${v}` : v;
        })
        .filter(s => s)
        .join('; ');
}

/**
 * 按 action.data_key 解析 emit 数据
 * 列表场景的 ctx 通常只有 { order }，没有 list，因此 list[0] 不适用
 * data_key='null' → null
 * data_key='order' → ctx.order
 */
export function resolveActionData(action, ctx) {
    const key = action.data_key || 'order';
    if (!key || key === 'null') return null;
    if (key === 'order') return ctx.order;
    // list[0] 列表场景退化为第一条 order item
    if (key === 'list[0]') {
        const items = (ctx.order && ctx.order.items) ? ctx.order.items : [];
        return items[0] || null;
    }
    return ctx[key] || null;
}

/** 兜底状态颜色（后端未提供 status_color 时）*/
export function getStatusColor(order, themeColor = '#E63D3D') {
    const s = Number(order.status);
    if ([10, 20, 30].includes(s)) return themeColor;
    return '#999';
}
