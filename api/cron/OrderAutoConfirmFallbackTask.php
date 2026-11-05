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

namespace app\cron;

use app\core\Task;
use app\helpers\OptionHelper;
use app\models\Order;
use app\services\OrderService;
use Yii;

/**
 * 订单自动确认收货 - 兜底任务
 *
 * Class OrderAutoConfirmFallbackTask
 * @package app\cron
 */
class OrderAutoConfirmFallbackTask extends Task
{
    /**
     * 声明这是一个多商城任务
     */

    /**
     * 运行任务
     * @param mixed|null $params
     */
    public function run($params=null)
    {
        $mall = Yii::$app->mall;
        $logPrefix = "[" . date('Y-m-d H:i:s') . "] [AutoConfirmFallbackTask] [MallID: {$mall->id}] ";

        try {
            $confirmDays = $this->confirmDays((int)$mall->id);

            // 如果未配置或设置为0，则不执行
            if ($confirmDays <= 0) {
                // 这不是错误，只是一个正常的跳过，所以用 warning 级别的日志
                Yii::warning($logPrefix . "自动确认收货周期设置为0天或更少，兜底任务跳过。");
                return;
            }

            $query = $this->missedOrderQuery((int)$mall->id, $confirmDays);

            $total = $query->count();
            if ($total == 0) {
                // 正常情况下，这个任务应该找不到订单，因为都被队列处理了
                Yii::warning($logPrefix . "未发现被遗漏的待收货订单，系统运行正常。");
                return;
            }

            Yii::warning($logPrefix . "【警告】发现 " . $total . " 个被队列遗漏的待收货订单，开始执行兜底处理...");

            $successCount = 0;
            $failCount = 0;

            foreach ($query->batch(50) as $orders) {
                foreach ($orders as $order) {
                    /** @var Order $order */
                    Yii::warning($logPrefix . "正在为遗漏的订单 #{$order->id} (订单号: {$order->order_no}) 执行自动确认收货...");

                    try {
                        $orderService = new OrderService();
                        if ($orderService->confirm($order)) {
                            $successCount++;
                            Yii::warning($logPrefix . "订单 #{$order->id} 兜底处理成功。");
                        } else {
                            $failCount++;
                            $errors = $orderService->getErrors();
                            $errorMsg = $errors ? current($errors) : '未知错误';
                            Yii::error($logPrefix . "订单 #{$order->id} 兜底处理失败。原因: " . (is_array($errorMsg) ? json_encode($errorMsg) : $errorMsg));
                        }
                    } catch (\Exception $e) {
                        $failCount++;
                        Yii::error($logPrefix . "订单 #{$order->id} 在执行兜底处理时发生异常: " . $e->getMessage());
                    }
                }
            }

            Yii::warning($logPrefix . "兜底任务处理完毕。成功: {$successCount}，失败: {$failCount}");

        } catch (\Exception $e) {
            Yii::error($logPrefix . "兜底任务执行时发生顶层异常: " . $e->getMessage());
        }
    }

    /**
     * 本商城的自动确认收货天数,口径同 OrderShippedListener(0 = 不自动确认)。
     * 原来不传商城号,读的是平台级配置(没有这一行),一律按 7 天:商城设了 0 也照样确认,
     * 设了 15 天第 7 天就提前确认了
     */
    private function confirmDays(int $mallId): int
    {
        $setting = OptionHelper::get('mall_setting', $mallId, []);
        return (int)($setting['auto_confirm_day'] ?? 7);
    }

    /**
     * 被队列漏掉的待收货订单:发货时间超过「自动收货周期 + 1 小时宽限期」还没确认的。
     * 范围必须和 OrderShippedListener 排自动确认的一致 —— 只有快递、同城配送。别的配送方式不是漏了,
     * 是本来就不自动确认:自提靠核销、采购单(无需物流)由采购插件自己收货入库,替它们确认会绕过这些流程。
     * 没有发货时间的单不按时间推算,一律跳过。
     */
    private function missedOrderQuery(int $mallId, int $confirmDays)
    {
        $gracePeriod = 3600; // 给队列任务留的宽限期,避免和它同时处理同一单
        return Order::find()->where([
            'mall_id' => $mallId,
            'is_delete' => 0,
            'status' => Order::STATUS_SHIPPED,
            'deliver_type' => [Order::DELIVER_TYPE_EXPRESS, Order::DELIVER_TYPE_CITY],
        ])->andWhere(['>', 'send_at', 0])
            ->andWhere(['<=', 'send_at', time() - ($confirmDays * 86400 + $gracePeriod)]);
    }

    /**
     * 定时任务规则
     * 建议每天执行一次，时间可以比结算任务晚一些，比如凌晨4点
     * @return string
     */
    public function rule()
    {
        return "0 4 * * *";
    }

    /**
     * 任务描述
     * @return string
     */
    public function description()
    {
        return "【多商城】订单自动确认收货（兜底任务）";
    }
}