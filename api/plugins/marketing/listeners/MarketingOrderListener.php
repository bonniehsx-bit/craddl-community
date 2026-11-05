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

namespace app\plugins\marketing\listeners;

use app\events\CommissionRevertEvent;
use app\events\OrderEvent;
use app\listeners\BaseListener;
use app\models\Order;
use app\plugins\marketing\services\CumulativeService;
use app\plugins\marketing\services\OrderRewardService;
use app\plugins\marketing\services\TriggerService;
use Yii;

/**
 * 订单生命周期监听(按平台「奖励类插件只认 paid / settled / revert」约定)
 *  - paid    :首单/满额/复购奖励(单笔订单奖)
 *  - settled :累计下单阶梯奖(只算已结算订单)
 *  - revert  :取消/售后/插件退款统一信号 → 不再达标的奖励回收
 */
class MarketingOrderListener extends BaseListener
{
    public function register()
    {
        Yii::$app->on(Order::EVENT_ORDER_PAID, [$this, 'onOrderPaid']);
        Yii::$app->on(Order::EVENT_ORDER_SETTLED, [$this, 'onOrderSettled']);
        Yii::$app->on(CommissionRevertEvent::NAME, [$this, 'onRevert']);
    }

    public function onOrderPaid(OrderEvent $event)
    {
        try {
            TriggerService::onOrderPaid($event->order);
        } catch (\Throwable $e) {
            Yii::error('[营销奖励] onOrderPaid 失败 OrderID:' . ($event->order->id ?? 0) . ' - ' . $e->getMessage());
        }
    }

    public function onOrderSettled(OrderEvent $event)
    {
        try {
            CumulativeService::onOrderSettled($event->order);
        } catch (\Throwable $e) {
            Yii::error('[营销奖励] 累计奖 onOrderSettled 失败 OrderID:' . ($event->order->id ?? 0) . ' - ' . $e->getMessage());
        }
        try {
            OrderRewardService::onOrderSettled($event->order);
        } catch (\Throwable $e) {
            Yii::error('[营销奖励] 复购/邀请 onOrderSettled 失败 OrderID:' . ($event->order->id ?? 0) . ' - ' . $e->getMessage());
        }
    }

    public function onRevert(CommissionRevertEvent $event)
    {
        try {
            CumulativeService::onRevert($event);
        } catch (\Throwable $e) {
            Yii::error('[营销奖励] 累计奖 onRevert 失败 OrderID:' . (int)$event->order_id . ' - ' . $e->getMessage());
        }
        try {
            $order = Order::findOne(['id' => (int)$event->order_id, 'mall_id' => (int)$event->mall_id]);
            if ($order) {
                OrderRewardService::onRevert($order);
            }
        } catch (\Throwable $e) {
            Yii::error('[营销奖励] 复购/邀请 onRevert 失败 OrderID:' . (int)$event->order_id . ' - ' . $e->getMessage());
        }
    }
}
