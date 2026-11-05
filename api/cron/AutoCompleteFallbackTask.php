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

namespace app\cron;

use app\core\Task;
use app\helpers\LockHelper;
use app\models\Order;
use app\services\order\actions\OrderSettlementService;
use Yii;

/**
 * 「支付后自动完成」订单兜底（主框架级，适用于所有 is_auto_complete 虚拟/无物流订单）
 *
 * 正常路径：支付成功后核心 OrderPaymentService::handleAutoComplete 会在所有 EVENT_ORDER_PAID
 * 监听器执行完毕后自动「发货 → 确认收货 → 结算」，把虚拟订单（课程/会员/卡券/积分商品等）
 * 推到「已完成」。但若该步出现异常（订单 save 校验失败 / 结算抛错 / 某监听器中断流程等），
 * 订单会卡在「待发货(20)」或「待收货(30)」且 is_auto_complete=1，永远不结算。
 *
 * 本任务周期性兜底，复刻同一流转：
 *   - 仅处理：已支付 + is_auto_complete=1 + 未退款 + 仍停在 待发货/待收货 + 未结算
 *   - 复刻 handleAutoComplete 的 发货 → 确认收货 → 结算，单条加锁、幂等安全
 *   - 不重发 EVENT_ORDER_PAID（否则分销/会员等监听器会重复触发），只补「支付后的完成+结算」
 *   - confirm() 内部已守护 yuda/hotel 等线下核销业务（返回 false 跳过），不会误结算
 *
 * 调度：Manager::run 按商城循环执行（已注入 Yii::$app->mall 与 $params['mall']），故按 mall_id 过滤。
 */
class AutoCompleteFallbackTask extends Task
{
    public function run($params = null)
    {
        $mall = $params['mall'] ?? Yii::$app->mall ?? null;
        if (!$mall) {
            return true;
        }

        $list = Order::find()
            ->where([
                'mall_id'          => $mall->id,
                'is_delete'        => 0,
                'is_auto_complete' => 1,
                'pay_status'       => Order::PAY_STATUS_PAID,
                'refund_status'    => Order::REFUND_STATUS_NO_REFUND,
            ])
            ->andWhere(['in', 'status', [Order::STATUS_TO_BE_SHIPPED, Order::STATUS_SHIPPED]])
            ->andWhere(['<>', 'settlement_status', Order::SETTLEMENT_STATUS_SETTLED])
            // 留 2 分钟缓冲，避开刚支付、实时流转尚在进行中的订单
            ->andWhere(['<', 'updated_at', time() - 120])
            ->limit(100)
            ->all();

        if (!$list) {
            return true;
        }

        $settlement = new OrderSettlementService();

        foreach ($list as $order) {
            $lockKey = 'auto_complete_fallback_' . $order->id;
            if (!LockHelper::lock($lockKey, 300)) {
                continue; // 正被其它进程/实时流程处理，跳过
            }

            try {
                // 1. 补「发货」（无物流虚拟订单）
                //    条件更新:$order 是开头批量查出来的,处理到这张时它可能已被取消 / 退款 / 申请售后,
                //    只在仍是「待发货、已支付、无售后」时才补发货。直接 save() 会把刚关掉的单改回待收货,
                //    下面接着确认收货、结算(save 只按 id 更新,又总补写时间戳,返回值看不出覆盖了别人)
                if ($order->status == Order::STATUS_TO_BE_SHIPPED) {
                    $shipped = Order::updateAll([
                        'send_at'         => $order->send_at ?: time(),
                        'delivery_status' => Order::DELIVERY_STATUS_SHIPPED,
                        'status'          => Order::STATUS_SHIPPED,
                    ], [
                        'id'            => $order->id,
                        'status'        => Order::STATUS_TO_BE_SHIPPED,
                        'pay_status'    => Order::PAY_STATUS_PAID,
                        'refund_status' => Order::REFUND_STATUS_NO_REFUND,
                    ]);
                    if (!$shipped) {
                        Yii::warning("[自动完成兜底] #{$order->id} 已不是待发货(期间被取消 / 退款 / 申请售后),跳过", 'cron');
                        continue;
                    }
                    $order->refresh();
                }

                // 2. 确认收货（status=SHIPPED 时无需 force；confirm 内部会推到「已完成」）
                if ($settlement->confirm($order)) {
                    // 3. 结算
                    $settlement->orderSettle($order);
                    Yii::warning("[自动完成兜底] 订单 #{$order->id}（{$order->type}）已补「完成+结算」", 'cron');
                } else {
                    // confirm 返回 false：可能是 yuda/hotel 等被守护的业务，或校验未过——记录便于排查
                    Yii::error("[自动完成兜底] #{$order->id} 确认收货失败: " . json_encode($settlement->getFirstErrors(), JSON_UNESCAPED_UNICODE), 'cron');
                }
            } catch (\Throwable $e) {
                Yii::error("[自动完成兜底] 处理 #{$order->id} 异常: " . $e->getMessage(), 'cron');
            }
        }

        return true;
    }

    public function rule()
    {
        // 每 5 分钟兜底一次
        return '*/5 * * * *';
    }

    public function description()
    {
        return '虚拟/自动完成订单 支付后完成兜底';
    }
}
