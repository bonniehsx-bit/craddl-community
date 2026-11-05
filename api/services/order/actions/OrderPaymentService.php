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

namespace app\services\order\actions;

use app\events\OrderEvent;
use app\models\BaseModel;
use app\models\CommonPayOrder;
use app\models\Order;
use app\services\order\factory\OrderHandlerFactory;
use Yii;

/**
 * 订单支付服务
 * 职责：处理支付状态变更、并立即执行支付后的所有副作用（权益发放、自动发货等）。
 */
class OrderPaymentService extends BaseModel
{
    /**
     * 支付订单 (核心状态变更 + 业务闭环)
     * @param Order $order
     * @param CommonPayOrder $commonPayOrder
     * @return bool
     * @throws \Throwable
     */
    public function pay(Order $order, CommonPayOrder $commonPayOrder): bool
    {
        // 1. 状态防御
        if ($order->status !== Order::STATUS_UNPAID) {
            Yii::warning("[OrderPaymentService::pay] 订单 #{$order->id} 状态不是待付款，跳过处理。");
            return true;
        }

        // 2. 解析支付方式名称
        $paymentName = '未知支付';
        $paymentClass = $commonPayOrder->payment;
        if ($paymentClass) {
            try {
                $paymentInstance = Yii::createObject($paymentClass, [$commonPayOrder->mall_id]);
                $paymentName = $paymentInstance->name;
            } catch (\Exception $e) {
                Yii::error("实例化支付类失败: " . $e->getMessage());
                $paymentName = '未知';
            }
        } else {
            $paymentName = '未知';
        }

        // 3. 更新订单数据库状态
        $order->status = Order::STATUS_TO_BE_SHIPPED;
        $order->is_pay = 1;
        $order->pay_status = Order::PAY_STATUS_PAID;
        $order->pay_at = $commonPayOrder->pay_at;
        $order->payment_class = $paymentClass;
        $order->payment_name = $paymentName;

        if (!$order->save()) {
            $this->addError('pay', $this->getErrorMsg($order));
            return false;
        }

        // 4. 触发订单已支付事件 (通知外部系统)
        // [防坑·关键] 必须包 try/catch：某个监听器抛未捕获异常会让 trigger 上抛，
        // 导致后续的 processUserBenefits 与 handleAutoComplete(line 92, 虚拟商品自动完成) 被跳过，
        // 订单停在「待发货 + 已支付 + is_auto_complete=1」永不结算（线上真实案例：订单 #100040）。
        // 监听器各自应自catch，此处再加一层兜底，确保单个监听器失败不阻断支付后主流程。
        try {
            Yii::$app->trigger(Order::EVENT_ORDER_PAID, new OrderEvent(['order' => $order]));
        } catch (\Throwable $e) {
            Yii::error("[OrderPaymentService] EVENT_ORDER_PAID 监听器异常(已兜底不阻断) ID:{$order->id}, Err:" . $e->getMessage());
        }

        // ==============================================================================
        //  [关键合并] 原 paySuccess 逻辑：确保支付状态更新后，立即执行业务副作用
        // ==============================================================================

        // 5. 分发给 Handler 处理差异化逻辑
        try {
            // [重构] 使用工厂获取 Handler
            // 利用新增的 plugin 字段和现有的 type 字段，精准定位
            $handler = OrderHandlerFactory::create(
                $order->plugin,
                $order->type
            );

            $handler->orderPaid($order);

        } catch (\Exception $e) {
            // 捕获异常，防止因为发短信/打印小票失败导致支付流程回滚
            Yii::error("支付后Handler处理异常 ID:{$order->id}, Err:" . $e->getMessage());
        }

        // 6. 处理平台通用权益 (会员升级、分销绑定)
        try {
            $this->processUserBenefits($order);
        } catch (\Exception $e) {
            Yii::error("支付后用户权益处理异常 ID:{$order->id}, Err:" . $e->getMessage());
        }

        // 7. 处理自动发货/完成的特殊商品 (虚拟商品)
        if ($order->is_auto_complete) {
            $this->handleAutoComplete($order);
        }

        return true;
    }

    /**
     * 处理自动完成订单的流转 (虚拟商品)
     * 发货 -> 收货 -> 结算
     */
    private function handleAutoComplete(Order $order)
    {
        Yii::warning("[OrderPaymentService] 订单 #{$order->id} 是自动完成商品，开始自动流转。");

        // 1. 模拟发货 (无需物流)
        $order->send_at = time();
        $order->delivery_status = Order::DELIVERY_STATUS_SHIPPED;
        $order->status = Order::STATUS_SHIPPED;

        if ($order->save()) {
            // 2. 调用结算服务执行：确认收货 + 强制结算
            $settlementService = new OrderSettlementService();

            // 执行确认收货
            if ($settlementService->confirm($order)) {
                try {
                    // 执行强制结算
                    $settlementService->orderSettle($order);
                    Yii::warning("[OrderPaymentService] 订单 #{$order->id} 自动结算完成。");
                } catch (\Throwable $e) {
                    Yii::error("[OrderPaymentService] 自动结算失败: " . $e->getMessage());
                }
            }
        } else {
            Yii::error("[OrderPaymentService] 自动发货状态保存失败");
        }
    }

    /**
     * 处理用户平台权益 (会员升级、分销绑定)
     */
    private function processUserBenefits(Order $order)
    {
        $user = \app\models\User::findOne($order->user_id);
        if (!$user)
            return;

        // 1. 绑定上下级关系
        if ($user->maybe_parent_id) {
            \app\helpers\JobHelper::push(new \app\jobs\user\BindParentJob([
                'user_id' => $user->id,
                'parent_id' => $user->maybe_parent_id
            ]));
        }
        // 2. 统一触发"需要重新评估等级"事件（会员/分销/经销各自的 Listener 自行处理）
        \Yii::$app->trigger(
            \app\events\UserLevelCheckNeededEvent::NAME,
            new \app\events\UserLevelCheckNeededEvent([
                'user_id' => $user->id,
                'trigger' => 'order_paid',
            ])
        );
        // 2.1 支付后同时触发整条上级链重检：下级消费拉高上级团队业绩，团队类条件即时生效（带防抖）
        \app\jobs\user\UserLevelUpgradeJob::dispatchUplineCheck((int)$user->id, 'order_paid_upline');
        // 3. 检查任务
        \app\helpers\JobHelper::push(new \app\jobs\user\CheckInviterJob(['user_id' => $user->id]));
    }
}