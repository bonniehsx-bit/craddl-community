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

namespace app\plugins\mch\listeners;

use app\events\OrderEvent;
use app\listeners\BaseListener;
use app\models\Order;
use app\plugins\mch\services\MchSettlementService;
use app\services\order\OrderBehaviorRegistry;
use Yii;

class OrderListener extends BaseListener
{
    /**
     * 注册监听器
     * 监听系统触发的订单最终结算事件
     */
    public function register()
    {
        Yii::$app->on(Order::EVENT_ORDER_SETTLED, [$this, 'handle']);
    }

    /**
     * 处理订单结算事件
     * @param OrderEvent $event
     */
    public function handle(OrderEvent $event)
    {
        $order = $event->order;

        Yii::warning("[商家结算监听器] 收到 EVENT_ORDER_SETTLED, OrderID:{$order->id}, mch_id:{$order->mch_id}, plugin:{$order->plugin}");

        // 只处理商家订单，排除「商家货款另有归属」的业态。名单由各插件
        // Plugin::getOrderBehaviors() 的 mch_settlement_exempt 声明，不再硬编码插件名：
        //   yuda     独立对拼结算
        //   massage  收益全归技师，由其 SettlementService 单独打款
        //   cashier  货款由 cashier 插件的 CashierSettlementService 结算
        //
        // ⚠️ checkout 此前不在排除之列，靠 CashierSettlementService「先标 is_mch_settled=1，
        //    再调 orderSettle(force)」的执行顺序才没重复发钱 —— 那是竞态型防线。
        //    现改为语义型：收银台订单根本不进入本监听器，两行代码调换也不会重复发钱。
        if ($order->mch_id <= 0 || OrderBehaviorRegistry::isMchSettlementExempt($order)) {
            Yii::warning("[商家结算监听器] 跳过: mch_id={$order->mch_id}, plugin={$order->plugin}, type={$order->type}");
            return;
        }

        Yii::warning("[商家结算监听器] 监听到订单 #{$order->id} 已结算，准备处理货款与运费...");

        try {
            // 实例化并调用统一的结算服务
            $service = new MchSettlementService();
            $service->settleByOrder($order);
        } catch (\Exception $e) {
            // 记录严重错误日志，以便排查问题。兜底任务会处理失败的记录。
            Yii::error("[商家结算监听器] 处理订单 #{$order->id} 时发生严重错误: " . $e->getMessage());
        }

        Yii::warning("[商家结算监听器] 订单 #{$order->id} 处理完毕。");
    }
}
