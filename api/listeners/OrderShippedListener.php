<?php

/**
 * Copyright (c) 2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: Refactored Version
 * Date: 2026-02-15
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
use app\helpers\JobHelper;
use app\helpers\OptionHelper;
use app\models\Order;
use app\services\order\factory\OrderHandlerFactory;
use Yii;

/**
 * 订单发货相关监听器 (路由调度版)
 * * 架构职责：
 * 1. 监听包裹发出 (PACKAGE_SHIPPED) -> 委托 Handler 发送包裹动态。
 * 2. 监听整单发出 (SHIPPED) -> 委托 Handler 发送终极通知，并投递平台级的自动收货任务。
 * 绝对不在此处写死任何业务特有的文案。
 */
class OrderShippedListener extends BaseListener
{
    /**
     * 注册监听器
     */
    public function register()
    {
        Yii::$app->on(Order::EVENT_ORDER_PACKAGE_SHIPPED, [$this, 'handlePackageShipped']);
        Yii::$app->on(Order::EVENT_ORDER_SHIPPED, [$this, 'handleAllShipped']);
    }

    /**
     * 【处理包裹发货】
     * 仅负责路由，委托 Handler 通知用户“包裹动态”。
     * @param OrderEvent $event
     */
    public function handlePackageShipped(OrderEvent $event)
    {
        $order = $event->order;
        $expressLogId = $event->express_log_id ?? null;

        Yii::warning("OrderShippedListener: 处理【包裹】发货事件，订单ID：{$order->id}");

        try {
            // 委托给对应的 Handler 处理特有动作
            $handler = OrderHandlerFactory::create($order->plugin, $order->type);

            if (method_exists($handler, 'sendPackageShippedNotification')) {
                $handler->sendPackageShippedNotification($order, $expressLogId);
            } else {
                Yii::warning("订单类型 {$order->type} 未实现 sendPackageShippedNotification 钩子。");
            }
        } catch (\Exception $e) {
            Yii::error('包裹发货通知派发失败: 订单ID ' . $order->id . ' - ' . $e->getMessage());
        }

        // ⛔️ 严禁在这里调用 startAutoConfirmJob()，防止队列风暴！
    }

    /**
     * 【处理整单发货】
     * 当订单所有商品都发出时调用。委托 Handler 发送终极通知，并启动“自动确认收货”倒计时。
     * @param OrderEvent $event
     */
    public function handleAllShipped(OrderEvent $event)
    {
        $order = $event->order;
        Yii::warning("OrderShippedListener: 处理【整单】发货事件，订单ID：{$order->id}");

        // --- 1. 委托发送“整单发货”通知 ---
        try {
            $handler = OrderHandlerFactory::create($order->plugin, $order->type);

            if (method_exists($handler, 'sendShippedNotification')) {
                $handler->sendShippedNotification($order);
            } else {
                Yii::warning("订单类型 {$order->type} 未实现 sendShippedNotification 钩子。");
            }
        } catch (\Exception $e) {
            Yii::error('整单发货通知派发失败: 订单ID ' . $order->id . ' - ' . $e->getMessage());
        }

        // --- 2. 平台级通用动作：启动自动确认收货延时任务 (仅在此处执行一次) ---
        $this->startAutoConfirmJob($order);
    }

    /**
     * 启动自动确认收货任务
     */
    private function startAutoConfirmJob(Order $order)
    {
        // 仅快递和同城配送需要自动确认收货 (自提靠核销，收银台当场完成，虚拟商品自动流转)
        if (!in_array($order->deliver_type, [Order::DELIVER_TYPE_EXPRESS, Order::DELIVER_TYPE_CITY])) {
            return;
        }

        $setting = OptionHelper::get('mall_setting', $order->mall_id, []);
        $delayInDays = (int) ($setting['auto_confirm_day'] ?? 7);

        if ($delayInDays > 0) {
            $delayInSeconds = $delayInDays * 24 * 60 * 60;
            // 投递延时队列
            JobHelper::push(new \app\jobs\order\AutoConfirmJob(['order_id' => $order->id]), $delayInSeconds);
            Yii::warning("OrderShippedListener: 已为订单 {$order->id} 推入 {$delayInDays} 天后自动确认收货的任务。");
        }
    }
}
