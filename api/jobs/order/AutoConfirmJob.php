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

namespace app\jobs\order;
// 请确保命名空间正确

use app\core\Job;
use app\models\Order;
use app\services\OrderService;
use yii\queue\Queue;
use Yii;

class AutoConfirmJob extends Job
{
    public $order_id;

    public function execute($params = null)
    {
        // 使用 findOne($id) 性能更高
        $order = Order::findOne($this->order_id);

        // 检查订单是否存在且有效
        if (!$order || $order->is_delete) {
            Yii::warning("[AutoConfirmJob] 任务启动，但未找到订单ID: {$this->order_id} 或订单已删除，任务终止。");
            return;
        }

        // 检查订单状态是否为“待收货” (STATUS_SHIPPED)
        if ($order->status != Order::STATUS_SHIPPED) {
            Yii::warning("[AutoConfirmJob] 订单 #{$order->id} (订单号: {$order->order_no}) 当前状态码为 [{$order->status}]，不是预期的“待收货”[" . Order::STATUS_SHIPPED . "]，任务跳过。可能是用户已手动收货或订单状态已改变。");
            return;
        }

        // 所有条件满足，记录日志并调用 Service
        Yii::warning("[AutoConfirmJob] [执行] 开始为订单自动确认收货。
            - 订单ID: {$order->id}
            - 订单号: {$order->order_no}
            - 发货时间: " . date('Y-m-d H:i:s', $order->send_at));

        try {
            $orderService = new OrderService();
            $result = $orderService->confirm($order);

            if ($result) {
                Yii::warning("[AutoConfirmJob] [成功] 订单 #{$order->id} 已成功自动确认收货。");
            } else {
                // 如果 service 返回 false，记录错误信息
                $errors = $orderService->getErrors();
                $errorMsg = $errors ? current($errors) : '未知错误';
                Yii::error("[AutoConfirmJob] [失败] 订单 #{$order->id} 自动确认收货失败。原因: " . (is_array($errorMsg) ? json_encode($errorMsg) : $errorMsg));
            }
        } catch (\Exception $e) {
            Yii::error("[AutoConfirmJob] [异常] 订单 #{$order->id} 在执行自动确认收货时发生异常: " . $e->getMessage());
            // 如果希望任务失败后能重试，可以重新抛出异常
            // throw $e;
        }
    }
}