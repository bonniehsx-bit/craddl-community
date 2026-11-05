<?php
/**
 * Copyright (c) 2026 佛山今日鼎盛信息科技有限公司
 * 官网：https://www.craddl.cn
 * The contents of this file are subject to the Common Public Attribution License Version 1.0 (the
 * “License”); you may not use this file except in compliance with the License. You may obtain a
 * copy of the License from LICENSE in the source package or
 * https://opensource.org/license/cpal-1.0. The License is based on the Mozilla Public License
 * Version 1.1 but Sections 14 and 15 have been added to cover use of software over a computer
 * network and provide for limited attribution for the Original Developer. In addition, Exhibit A
 * has been modified to be consistent with Exhibit B.
 * Software distributed under the License is distributed on an “AS IS” basis, WITHOUT WARRANTY OF
 * ANY KIND, either express or implied. See the License for the specific language governing rights
 * and limitations under the License.
 * The Original Code is Craddl Community Edition.
 * The Original Developer is the Initial Developer.
 * The Initial Developer of the Original Code is 佛山今日鼎盛信息科技有限公司. All portions of the code written
 * by 佛山今日鼎盛信息科技有限公司 are Copyright (c) 2026. All Rights Reserved.
 */

use app\core\Migration;

/**
 * 回填 order.refund_price —— 历史上这个字段从来没被写过。
 *
 * 唯一的赋值是下单时初始化 '0.00'(InitBasicDataStage),退款流程只改
 * pay_status/status/refund_status,从不回写金额。实测:测试服 49 个有成功
 * 退款流水的订单全是 0.00,bonnie 12 个也全是 0.00。
 *
 * 首页「今日销售额」按 `pay_price - IFNULL(refund_price,0)` 计算,减数恒为 0,
 * 于是插件直写流水的退款(如 yuda_cancel,退了钱但不改 pay_status)会一直
 * 按全额挂在营业额里 —— 实测测试服 3 单、虚增 ¥50。
 *
 * 口径与 RefundSuccessDispatcher::syncOrderRefundPrice() 完全一致:
 * 取 common_pay_refund 里 status=SUCCESS 的金额合计,按 common_pay_order.order_id 归集。
 * 用 common_pay_refund 而不是 order_refund,因为前者是"钱真的退回去了",
 * 后者只是用户申请额;且三条退款路径都会落 common_pay_refund。
 *
 * 只 UPDATE 差异行,重复执行安全。
 */
class m260823_180000_backfill_order_refund_price extends Migration
{
    public function safeUp()
    {
        $order  = $this->db->tablePrefix . 'order';
        $payO   = $this->db->tablePrefix . 'common_pay_order';
        $payR   = $this->db->tablePrefix . 'common_pay_refund';

        // common_pay_refund.status: 2 = 退款成功(CommonPayRefund::STATUS_SUCCESS)
        $sql = <<<SQL
UPDATE {$order} o
JOIN (
    SELECT po.order_id AS oid, ROUND(SUM(r.refund_price), 2) AS refunded
    FROM {$payR} r
    JOIN {$payO} po ON po.id = r.common_pay_order_id AND po.is_delete = 0
    WHERE r.is_delete = 0 AND r.status = 2 AND po.order_id > 0
    GROUP BY po.order_id
) t ON t.oid = o.id
SET o.refund_price = t.refunded
WHERE o.is_delete = 0 AND IFNULL(o.refund_price, 0) <> t.refunded
SQL;

        $affected = $this->db->createCommand($sql)->execute();
        echo "    > 回填 order.refund_price: {$affected} 行\n";
    }

    public function safeDown()
    {
        // 不回滚 —— 原值全是错的 0.00,退回去等于重新制造这个 bug。
        echo "m260823_180000_backfill_order_refund_price 不支持回滚(原值是错误数据)\n";
        return true;
    }
}
