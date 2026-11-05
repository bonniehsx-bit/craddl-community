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
use app\models\CommonPayRefund;
use app\models\OrderDetail;

/**
 * 回填「全额退款订单」漏落地的三个字段。
 *
 * ============================================================
 * 为什么会有这批脏数据
 * ============================================================
 * 退款成功后落地业务状态的路只有三条,而它们做的事**不一样**:
 *
 *   来源           handler              标明细   重算收入
 *   after_sales    handleAfterSales      ✅       ✅
 *   yuda_cancel 等 handleGeneric         ✅       ❌
 *   cancel_order   handleCancelOrder     ❌       ❌
 *
 * 同一个提交已经把后两条补齐(见 RefundSuccessDispatcher),但只管新单。
 * 本迁移把存量补上。
 *
 * 实测(修复前):
 *   after_sales  的订单 mch_income 全是 0        ← 正确基准
 *   yuda_cancel  的订单 mch_income 全非 0(测试服 20 单 ￥788)
 *   cancel_order 的订单 明细 100% 停在非 INVALID(测试服 20 单 / bonnie 9 单)
 *
 * ============================================================
 * 为什么改这几个字段是安全的
 * ============================================================
 * · order_detail.status → INVALID:全仓 8 处拿它判「这笔算不算数」(团队业绩 /
 *   用户升级 / syk 业绩区域 / 积分 / pb)。查过了 —— 这些订单**没有任何已结算记录**:
 *   佣金全是 STATUS_INVALID(bonnie 一条都没有)、积分 0 条、syk 0 条,
 *   团队业绩另有独立回滚机制且已回滚。订单 settlement_status 几乎全是「无需结算」,
 *   结算链路压根没往下走。所以不会有人的等级/佣金因此变化。
 *
 * · order_detail.is_mch_settled 全是 0 → 商家货款一分都没打出去过。
 *
 * · order.mch_income / platform_income 只被两处报表 SUM() 读(OrderListForm),
 *   没有任何结算任务按它打款(真正打款走 order_detail.mch_income_price)。
 *   归零是让报表说对话,不动钱。
 *
 * ⚠ 只处理**全额退完**的订单(成功退款流水 >= 支付额)。部分退款的 mch_income
 *   该按剩余明细重算而不是归零,那条路由 recalculateAfterRefund 负责,不在这里做。
 *
 * ⛔ safeDown 是空的 —— 原值没有备份处,回滚不了。真要还原得从备份库取。
 */
class m260824_100000_backfill_cancel_order_refund_landing extends Migration
{
    public function safeUp()
    {
        $orderIds = $this->fullyRefundedOrderIds();
        if (!$orderIds) {
            echo "    > 没有全额退款的订单,跳过\n";
            return;
        }
        echo "    > 全额退款订单: " . count($orderIds) . " 单\n";

        $detailRows = 0;
        $incomeRows = 0;

        // 分批,避免 IN() 过长
        foreach (array_chunk($orderIds, 500) as $chunk) {
            // ① 明细落地。只更新真正有差异的行 —— 重复执行时匹配 0 行,天然幂等
            $detailRows += $this->db->createCommand()->update(
                '{{%order_detail}}',
                [
                    'refund_status' => OrderDetail::REFUND_STATUS_COMPLETE,
                    'status'        => OrderDetail::STATUS_INVALID,
                ],
                [
                    'and',
                    ['order_id' => $chunk],
                    ['is_delete' => 0],
                    [
                        'or',
                        ['!=', 'refund_status', OrderDetail::REFUND_STATUS_COMPLETE],
                        ['!=', 'status', OrderDetail::STATUS_INVALID],
                    ],
                ]
            )->execute();

            // ② 收入归零。范围比 ① 大:yuda_cancel 那批明细本来就对,但收入没归零
            $incomeRows += $this->db->createCommand()->update(
                '{{%order}}',
                ['mch_income' => '0.00', 'platform_income' => '0.00'],
                [
                    'and',
                    ['id' => $chunk],
                    ['or', ['!=', 'mch_income', '0.00'], ['!=', 'platform_income', '0.00']],
                ]
            )->execute();
        }

        echo "    > 明细落地(refund_status=30 / status=INVALID): {$detailRows} 行\n";
        echo "    > 收入归零(mch_income / platform_income)      : {$incomeRows} 行\n";
        if ($detailRows === 0 && $incomeRows === 0) {
            echo "    > 数据已是正确状态,无需回填\n";
        }
    }

    public function safeDown()
    {
        echo "    > 本迁移不可回滚(原值未备份),跳过\n";
        return true;
    }

    /**
     * 全额退完的订单 id —— 判据与 RefundSuccessDispatcher 保持一致:
     * 支付流水的成功退款额 >= 支付额。
     *
     * 用 common_pay_refund 而不是 order_refund:前者是「钱真的退回去了」,
     * 后者只是用户申请额,而且插件直写流水的路径根本不产生 order_refund。
     */
    private function fullyRefundedOrderIds(): array
    {
        $rows = $this->db->createCommand("
            SELECT po.order_id
            FROM {{%common_pay_order}} po
            JOIN (
                SELECT common_pay_order_id, SUM(refund_price) AS refunded
                FROM {{%common_pay_refund}}
                WHERE status = :ok AND is_delete = 0
                GROUP BY common_pay_order_id
            ) r ON r.common_pay_order_id = po.id
            WHERE po.order_id > 0
              AND po.pay_price > 0
              AND r.refunded >= po.pay_price
        ", [':ok' => CommonPayRefund::STATUS_SUCCESS])->queryColumn();

        return array_values(array_unique(array_map('intval', $rows)));
    }
}
