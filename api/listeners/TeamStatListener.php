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

namespace app\listeners;

use app\events\CommissionRevertEvent;
use app\events\OrderEvent;
use app\models\Order;
use app\services\TeamStatOutbox;
use app\services\TeamStatUpdater;
use Yii;

/**
 * 团队统计全局监听器
 * 监听订单生命周期事件，增量更新 UserTeamStat
 * 独立于业务逻辑，失败不影响主流程
 */
class TeamStatListener extends BaseListener
{
    public function register()
    {
        Yii::$app->on(Order::EVENT_ORDER_PAID, [$this, 'onOrderPaid']);
        Yii::$app->on(Order::EVENT_ORDER_SETTLED, [$this, 'onOrderSettled']);
        // 大一统:回滚只认统一信号 commission.revert(平台在「取消」和「售后同意」commit 后各触发一次,
        // 按 order_detail_id 分流:0=整单 / >0=明细)。不再监听 order.canceled,否则与 revert(source=cancel)
        // 双回滚;同时这条还补齐了原 canceled-only 漏掉的「结算后售后退款 / 部分退 / settled 口径 / 已购商品」。
        Yii::$app->on(CommissionRevertEvent::NAME, [$this, 'onCommissionRevert']);
    }

    public function onOrderPaid(OrderEvent $event)
    {
        try {
            $order = $event->order;
            $mallId = (int)$order->mall_id;
            // 启用 outbox:记待办交 worker 可靠消费;否则直接增量(现状)
            if (TeamStatOutbox::enabled($mallId)) {
                TeamStatOutbox::emit($mallId, 'order_paid', ['order_id' => (int)$order->id]);
            } else {
                TeamStatUpdater::onOrderPaid($order);
            }
        } catch (\Exception $e) {
            Yii::error("[TeamStatListener] onOrderPaid 失败: " . $e->getMessage());
        }
    }

    public function onOrderSettled(OrderEvent $event)
    {
        try {
            $order = $event->order;
            $mallId = (int)$order->mall_id;
            if (TeamStatOutbox::enabled($mallId)) {
                TeamStatOutbox::emit($mallId, 'order_settled', ['order_id' => (int)$order->id]);
            } else {
                TeamStatUpdater::onOrderSettled($order);
            }
        } catch (\Exception $e) {
            Yii::error("[TeamStatListener] onOrderSettled 失败: " . $e->getMessage());
        }
    }

    public function onCommissionRevert(CommissionRevertEvent $event)
    {
        try {
            $mallId = (int)$event->mall_id;
            if (TeamStatOutbox::enabled($mallId)) {
                TeamStatOutbox::emit($mallId, 'commission_revert', [
                    'order_id'        => (int)$event->order_id,
                    'order_detail_id' => (int)$event->order_detail_id,
                    'source'          => (string)$event->source,
                ]);
            } else {
                TeamStatUpdater::onCommissionRevert(
                    $mallId,
                    (int)$event->order_id,
                    (int)$event->order_detail_id,
                    (string)$event->source
                );
            }
        } catch (\Exception $e) {
            Yii::error("[TeamStatListener] onCommissionRevert 失败: " . $e->getMessage());
        }
    }
}
