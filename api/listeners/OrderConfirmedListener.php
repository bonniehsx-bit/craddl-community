<?php

/**
 * Copyright (c) 2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: Refactored Version
 * Date: 2026-02-14
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

namespace app\listeners;

use app\events\OrderEvent;
use app\models\Order;
use app\services\order\factory\OrderHandlerFactory;
use Yii;

/**
 * 确认收货(履约完成)监听器
 * * 架构职责：作为“调度中心”，捕获确认收货事件，并将后续的副作用（如发通知）
 * 动态委托给对应的业务 Handler 去处理。
 * 绝对不在此处写死任何业务特有的文案或逻辑，严格遵守开闭原则 (OCP)。
 */
class OrderConfirmedListener extends BaseListener
{
    /**
     * 注册监听器
     */
    public function register()
    {
        // 坚守 EVENT_ORDER_CONFIRMED 是物理履约终点的原则
        Yii::$app->on(Order::EVENT_ORDER_CONFIRMED, [$this, 'handle']);
    }

    /**
     * 事件处理主入口
     * @param OrderEvent $event
     */
    public function handle(OrderEvent $event)
    {
        $order = $event->order;
        Yii::warning("OrderConfirmedListener: 订单确认收货，订单ID：{$order->id}，开始触发后置动作。");

        // ====================================================================
        // 1. 委托给对应的 Handler 处理业务特有动作 (例如：发送通知文案)
        // ====================================================================
        try {
            $handler = OrderHandlerFactory::create($order->plugin, $order->type);

            // 检查 Handler 中是否存在该钩子方法（兼容性保护）
            if (method_exists($handler, 'sendConfirmedNotification')) {
                $handler->sendConfirmedNotification($order);
            } else {
                Yii::warning("订单类型 {$order->type} 的 Handler 未实现 sendConfirmedNotification 钩子。");
            }
        } catch (\Exception $e) {
            // 即使发送通知报错，也绝对不能影响订单的主状态流转
            Yii::error("OrderConfirmedListener: 订单ID {$order->id} 的 Handler 后置动作执行失败 - " . $e->getMessage());
        }

        // ====================================================================
        // 2. 平台级的通用副作用 (未来可在此处扩展，或新写 Listener 监听同个事件)
        // ====================================================================
        // 例如：触发打印机、通知ERP系统减总库存等。
        // 但注意：送积分、算佣金，建议由独立的 Listener 来处理，保持单一职责。
    }
}
