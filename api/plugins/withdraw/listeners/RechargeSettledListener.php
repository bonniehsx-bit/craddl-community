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

namespace app\plugins\withdraw\listeners;

use app\events\OrderEvent;
use app\listeners\BaseListener;
use app\models\Order;
use app\plugins\withdraw\services\RechargeService;
use Yii;

/**
 * 充值到账监听器 —— 监听 EVENT_ORDER_SETTLED。
 *
 * 为什么挂结算而不是支付(EVENT_ORDER_PAID)：
 *   订单处理器把充值单标了 is_auto_complete=1，核心会在 PAID 的所有 listener
 *   跑完之后统一触发结算。挂在 PAID 上会和 Distribution/Area/Dealer 这些
 *   "同一事件写 pending 记录"的插件抢顺序；挂结算就没有这个竞态。
 *   而且后台手动补结算走的也是这个事件，能天然兜住卡单。
 *
 * 发放本身的幂等在 RechargeService::credit()（抢 status 锁），
 * 所以这个事件被重复触发多少次都只到账一次。
 */
class RechargeSettledListener extends BaseListener
{
    public function register()
    {
        Yii::$app->on(Order::EVENT_ORDER_SETTLED, [$this, 'handle']);
    }

    public function handle(OrderEvent $event)
    {
        $order = $event->order;
        if (!$order || $order->plugin !== 'withdraw') {
            return;
        }

        try {
            $done = RechargeService::credit((int)$order->id);
            if ($done) {
                Yii::warning("[充值] 订单结算触发到账 OrderID:{$order->id}");
            }
        } catch (\Throwable $e) {
            // 不往上抛：结算链上还挂着别的插件，充值发放失败不该把整条链带崩。
            // 失败时 credit() 已经把锁放回 PENDING，后台「重新发放」或补结算能重试。
            Yii::error("[充值] 到账异常 OrderID:{$order->id}: " . $e->getMessage());
        }
    }
}
