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
use app\helpers\OptionHelper;
use app\models\Order;
use app\models\OrderRefund;
use app\services\order\OrderBehaviorRegistry;
use app\services\OrderService;

// 引入 Service
use Yii;

class OrderSettleTask extends Task
{
    /**
     * [并发保护] 禁止多实例同时运行
     *
     * 原因：如果上一轮执行尚未完成，下一轮被触发时会抢同一批 PENDING 订单，
     * 导致同一笔订单被并发 orderSettle() → EVENT_ORDER_SETTLED 触发两次 →
     * 分销/区域/经销商 listener 对同一条 pending commission_log 可能重复结算，
     * 造成资金双发（CommissionService::settle 的状态检查是 PHP 层 TOCTOU）。
     */
    public $allowMultiInstance = false;

    /**
     * 这一轮该结算的订单:[已完成 OR 已关闭] 且 [待结算] 且 [超过售后/结算期]。
     *
     * [重要排除] 声明了 self_settled 的订单类型 —— 它们自主推进 settlement_status，核心不得插手。
     *    · 典型：收银台(checkout) 的 flow_to 配置（balance/price/split）只能由
     *      CashierSettlementService 正确处理；若被核心 orderSettle 抢走，flow_to 永久失效，
     *      balance/split 模式会资金错账。
     * 排除清单由各插件 Plugin::getOrderBehaviors() 声明，核心不再硬编码任何插件名/订单类型。
     * 其他走专属结算的业态（yuda/hotel 等）订单 status 通常不是 COMPLETED（卡在业态动作前），
     * 天然不会被本查询命中。
     *
     * [等售后] 已完成的单有售后还在处理(OrderService::afterSaleBlockReason 同一口径)就先不结:
     *    售后期最后一天才申请、商家还没处理时,原来照结 —— 佣金、货款先发出去,退款成功后再扣回(可能扣成负数)。
     *    处理完了下一轮照结:同意退款的那件已作废不参与,拒绝的整单照结。已关闭的单结算只是标「无需结算」,不用等。
     */
    public static function dueQuery(int $mallId, int $afterSalePeriod, int $now)
    {
        $query = Order::find()->where([
            'mall_id' => $mallId,
            'status' => [Order::STATUS_COMPLETED, Order::STATUS_CLOSED],
            'settlement_status' => Order::SETTLEMENT_STATUS_PENDING,
        ])
            ->andWhere(['<=', 'finish_at', $now - $afterSalePeriod]);

        // 双查口径与 NULL 安全性统一由 excludeCondition() 兜住（plugin 是 nullable 列，
        // 手写 `plugin NOT IN (...)` 会把 plugin IS NULL 的订单静默排除掉 → 永不结算）
        if ($cond = OrderBehaviorRegistry::excludeCondition('self_settled')) {
            $query->andWhere($cond);
        }

        // 与 OrderService::afterSaleBlockReason 同一口径:订单上的售后标记(申请中 / 售后中)或售后单本身,任一在处理就等
        $inProgress = OrderRefund::find()->select('order_id')
            ->where(['mall_id' => $mallId, 'is_delete' => 0, 'status' => OrderRefund::IN_PROGRESS_STATUS]);
        $query->andWhere(['or', ['status' => Order::STATUS_CLOSED], ['and',
            ['not in', 'refund_status', [Order::REFUND_STATUS_APPLIED, Order::REFUND_STATUS_PROCESSING]],
            ['not in', 'id', $inProgress],
        ]]);

        return $query;
    }

    /**
     * 运行定时任务
     * @param mixed|null $params
     */
    public function run($params = null)
    {
        $logPrefix = "[OrderSettleTask] ";

        try {
            // 1. 获取商城ID (假设任务按商城运行)
            $mallId = Yii::$app->mall->id;
            if (!$mallId) {
                Yii::error($logPrefix . "无法确定商城ID，任务终止。");
                return;
            }

            // 2. 获取配置
            $setting = OptionHelper::get('mall_setting', $mallId);
            $settleDays = $setting['order_settle_day'] ?? 0;
            if ($settleDays <= 0) {
                $settleDays = 0;
            }
            $afterSalePeriod = $settleDays * 86400; // 转换为秒

            // 3. 构建查询(见 dueQuery)
            $query = self::dueQuery((int)$mallId, (int)$afterSalePeriod, time());

            $total = $query->count();

            if ($total == 0) {
                return;
            }

            $successCount = 0;
            $failCount = 0;

            // 实例化 Service (由于 OrderService 继承自 BaseModel，它可能包含状态，建议在循环内new或者确保无副作用)
            // 在这里，orderSettle 是无状态操作，但在循环外实例化更高效
            $orderService = new OrderService();

            // 4. 批量处理
            foreach ($query->batch(100) as $orders) {
                /** @var Order $order */
                foreach ($orders as $order) {
                    try {
                        // === 核心修改：直接调用 Service 方法 ===
                        // orderSettle 内部自带 事务控制、状态判断、事件触发

                        $orderService->clearErrors(); // 复用实例:清掉上一单的错误,避免失败日志里 errors 逐单累加
                        $result = $orderService->orderSettle($order);

                        if ($result) {
                            $successCount++;
                            // 如果需要详细日志，orderSettle 内部已经有了 warning 日志
                        } else {
                            $failCount++;
                            // 获取 Service 内部产生的错误信息
                            $errors = json_encode($orderService->errors, JSON_UNESCAPED_UNICODE);
                            Yii::error($logPrefix . "订单 #{$order->id} 结算失败: {$errors}");
                        }

                    } catch (\Throwable $e) {
                        // 捕获 unexpected error (虽然 orderSettle 内部catch了大部分，但防止实例化等问题)
                        $failCount++;
                        Yii::error($logPrefix . "处理订单 #{$order->id} 时发生未捕获异常: " . $e->getMessage());
                    }
                }

                // 建议：每批次处理完，清理一下内存（针对长驻进程）
                unset($orders);
            }
        } catch (\Throwable $e) {
            // \Throwable 含 \Error：查询/实例化阶段抛 TypeError 等也要兜住，避免逃逸成未捕获致命错误
            Yii::error($logPrefix . "任务执行时发生顶层异常: " . $e->getMessage());
        } finally {
            Yii::warning($logPrefix . "订单结算任务执行完毕。");
        }
    }

    /**
     * 定时任务规则
     * 每分钟执行
     * @return string
     */
    public function rule()
    {
        return "*/1 * * * *";
    }

    /**
     * 任务描述
     * @return string
     */
    public function description()
    {
        return "订单自动结算任务";
    }
}