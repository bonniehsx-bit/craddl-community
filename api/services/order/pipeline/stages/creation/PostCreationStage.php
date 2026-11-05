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

namespace app\services\order\pipeline\stages\creation;

use app\core\ApiCode;
use app\events\OrderEvent;
use app\models\Order;
use app\services\CommonPayOrderService;
use app\services\order\pipeline\CreationPayload;
use app\services\order\pipeline\StageInterface;
use Yii;

class PostCreationStage implements StageInterface
{
    public function handle($payload, \Closure $next)
    {
        /** @var CreationPayload $payload */

        // 在事件和零元自动支付之前兜底，合法的 0 元订单仍允许通过。
        \app\services\order\OrderInputGuard::assertOrderAmounts($payload->order);

        // 1. 触发事件
        Yii::$app->trigger(Order::EVENT_ORDER_CREATED, new OrderEvent(['order' => $payload->order]));

        // 2. 生成支付订单
        $payId = 0;
        $payKey = '';
        try {
            $commonOrderService = new CommonPayOrderService();
            $commonOrder = $commonOrderService->createOrder([
                'mall_id' => $payload->mall_id,
                'user_id' => $payload->user->id,
                'order_id' => $payload->order->id,
                'order_no' => $payload->order->order_no,
                'pay_price' => $payload->order->pay_price,
                'order_class' => Order::class,
                'total_price' => $payload->order->total_price,
                'remarks' => '商城下单',
            ]);

            $payId = $commonOrder->id;
            $payKey = $commonOrder->key;
            $payload->payOrder = $commonOrder;

            // 0元订单自动支付
            if ($payload->order->pay_price <= 0) {
                if ($commonOrder->pay()) {
                    Yii::warning("订单 {$payload->order->order_no} 支付金额为 0, 已自动触发支付");
                    
                    // 构建 0 金额直通完毕响应
                    $payload->response = [
                        'code' => ApiCode::CODE_SUCCESS,
                        'msg' => 'success',
                        'data' => [
                            'order_no' => $payload->order->order_no,
                            'pay_order_id' => $payId,
                            'pay_data_key' => $payKey,
                        ]
                    ];
                    return $next($payload);
                } else {
                    Yii::error("0元订单 {$payload->order->order_no} 自动支付失败");
                }
            }

        } catch (\Exception $e) {
            Yii::warning($e);
            Yii::warning("支付单生成失败: " . $e->getMessage());
        }

        // 3. 构建返回结果
        $payload->response = [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => 'success',
            'data' => [
                'order_no' => $payload->order->order_no,
                'pay_order_id' => $payId,
                'pay_data_key' => $payKey,
            ]
        ];

        return $next($payload);
    }
}
