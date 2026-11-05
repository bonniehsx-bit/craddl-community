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
// File: app/plugins/mch/listeners/FirstOrderDiscountListener.php

namespace app\plugins\mch\listeners;

use app\events\OrderEvent;
use app\listeners\BaseListener;
use app\models\OrderDetail;
use app\models\Goods;
use app\models\Order;
use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchFirstOrderDiscountLog;
use Yii;

/**
 * 监听订单支付成功事件，处理首单优惠的相关逻辑
 * 1. 创建优惠使用日志
 * 2. 扣减商家的优惠单数库存
 */
class FirstOrderDiscountListener extends BaseListener
{
    /**
     * 注册事件监听
     */
    public function register()
    {
        Yii::$app->on(Order::EVENT_ORDER_PAID, [$this, 'handle']);
    }

    /**
     * 事件处理方法
     * @param OrderEvent $event
     */
    public function handle(OrderEvent $event)
    {
        $order = $event->order;

        // 检查订单是否使用了首单优惠，并且是商家订单
        if (!isset($order->first_order_discount_price) || $order->first_order_discount_price <= 0 || $order->mch_id <= 0) {
            return;
        }

        // 下单时已经占过名额、写过日志(FirstOrderQuotaService::reserve),这里什么都不用做。
        // 往下的老逻辑只给上线前下单、上线后才付款的老订单兜底。
        if (\app\plugins\mch\services\FirstOrderQuotaService::reservedAtCreation($order)) {
            return;
        }

        Yii::warning("[首单优惠] 监听到订单 {$order->order_no} 支付成功，开始处理相关逻辑...");

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // ========== 逻辑一：创建优惠使用日志 ==========
            $log = new MchFirstOrderDiscountLog();
            $log->mall_id = $order->mall_id;
            $log->mch_id = $order->mch_id;
            $log->user_id = $order->user_id;
            $log->order_id = $order->id;
            $log->discount_amount = $order->first_order_discount_price;
            if (!$log->save()) {
                throw new \Exception("记录首单优惠日志失败: " . json_encode($log->getFirstErrors()));
            }
            Yii::warning("[首单优惠] 订单 {$order->order_no} 的优惠日志记录成功。");


            // ========== 逻辑二：扣减商家优惠单数库存 ==========
            // 1. 计算订单中单装水的总单数
            $totalBarrelsToDeduct = (int)OrderDetail::find()
                ->alias('cod')
                ->innerJoin(['g' => Goods::tableName()], 'cod.goods_id = g.id')
                ->where([
                    'cod.order_no' => $order->order_no,
                    'cod.is_delete' => 0,
                ])
                ->count();

            if ($totalBarrelsToDeduct <= 0) {
                Yii::warning("[首单优惠] 订单 {$order->order_no} 中不包含单装水商品，无需扣减库存。");
                $transaction->commit(); // 即使不扣库存，日志也要保存，所以提交事务
                return;
            }

            // 2. 使用 updateAllCounters 原子性操作来扣减库存
            $updatedRows = Mch::updateAllCounters(
                ['first_order_discount_total_stock' => -$totalBarrelsToDeduct],
                [
                    'and',
                    ['id' => $order->mch_id],
                    ['>=', 'first_order_discount_total_stock', $totalBarrelsToDeduct]
                ]
            );

            if ($updatedRows > 0) {
                Yii::warning("[首单优惠] 成功扣减商家ID:{$order->mch_id} 的优惠单数库存 {$totalBarrelsToDeduct} 单。");
            } else {
                // 如果更新影响的行数为0，说明库存可能已不足或存在并发问题，记录错误日志
                Yii::error("[首单优惠] 扣减商家ID:{$order->mch_id} 的优惠单数失败！可能是库存不足 {$totalBarrelsToDeduct} 单或并发导致。");
            }

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error("[首单优惠] 处理订单 {$order->order_no} 时发生异常: " . $e->getMessage());
        }
    }
}
