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
use app\models\CommonPayOrder;
use Yii;

class AutoCancelOrderTask extends Task
{
    /**
     * 定时任务规则：每分钟执行一次
     * @return string
     */
    public function rule()
    {
        return "*/1 * * * *";
    }

    /**
     * 任务描述
     * @return string
     */
    public function description()
    {
        return "自动取消已过期且未支付的订单";
    }

    /**
     * 运行定时任务
     * @param mixed $params
     * @return mixed
     */
    public function run($params = null)
    {
        $now = time();

        /**
         * 查找符合以下条件的订单：
         * 1. is_pay = 0 (未支付)
         * 2. is_cancel = 0 (未取消)
         * 3. is_delete = 0 (未删除)
         * 4. expire_at <= 当前时间 (已过期)
         *
         * 注意：expire_at 上有索引，查询效率非常高
         * 限制每次处理 100 条，防止长耗时导致任务堆积
         */
        $orders = CommonPayOrder::find()
            ->where(['is_pay' => 0])
            ->andWhere(['is_cancel' => 0])
            ->andWhere(['is_delete' => 0])
            ->andWhere(['<=', 'expire_at', $now])
            ->limit(100)
            ->all();

        /** @var CommonPayOrder[] $orders */
        foreach ($orders as $order) {
            // 与支付回调(CommonPayOrder::paySuccess)抢同一把锁,锁内按最新数据复检:
            // 列表是循环前一次读出来的,回调可能刚把它付掉;不加锁时关单和入账交错,会出现
            // 「钱收了、订单关了、库存券都退了、却没有任何告警」。拿不到锁说明正在入账,下一轮再看。
            $lockKey = 'COMMON_PAY_ORDER_' . $order->id;
            $lockToken = \app\helpers\LockHelper::lock($lockKey, 300);
            if (!$lockToken) {
                continue;
            }
            try {
                $order->refresh();
                if ((int)$order->is_pay === 1 || (int)$order->is_cancel === 1 || (int)$order->is_delete === 1 || (int)$order->expire_at > $now) {
                    continue;
                }
                $this->closeOne($order, $now);
            } finally {
                \app\helpers\LockHelper::unlock($lockKey, $lockToken);
            }
        }
    }

    private function closeOne(CommonPayOrder $order, int $now): void
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $order->is_cancel = 1;
            $order->cancel_at = $now;

            // 追加备注，方便排查
            $currentRemarks = $order->remarks ? $order->remarks . ';' : '';
            $order->remarks = $currentRemarks . '系统超时自动关闭';

            if ($order->save()) {
                // 触发业务取消(回滚库存/券/积分、关单)。关闭过期未支付的支付单本身永远正确,
                // 故无论业务取消成败都提交;但若业务 cancel 显式返回 false(如插件 checkCancelPermission 拦截
                // 或行锁复检不可取消),打 ERROR 暴露"支付单已关、业务单未取消"的悬挂态,留待人工核查——
                // 不回滚重试,避免对"本就不可取消"的订单无限重试。
                // 不再按 order_class 反射 —— 所有订单都走核心 Order,
                // 反射只是把依赖藏进字符串里(同 CommonPayOrder::paySuccess)。
                // 原来还要 method_exists 探一下 cancel,是因为 Payable 契约里只有 pay;
                // 直连 Order 之后方法一定在,探测也不需要了。
                $bizOrder = \app\models\Order::findOne($order->order_id);
                if ($bizOrder) {
                    if ($bizOrder->cancel($order) === false) {
                        Yii::error("[超时关单] 支付单 ID:{$order->id} 已关闭,但业务订单 order_id:{$order->order_id} cancel 返回 false,可能残留未取消订单(库存/券/积分未回滚),请人工核查");
                    }
                } else {
                    Yii::error("[超时关单] 支付单 ID:{$order->id} 已关闭,但 order_id:{$order->order_id} 查不到对应订单,请人工核查");
                }
                Yii::warning("支付订单 ID:{$order->id} (原订单ID:{$order->order_id}) 已自动关闭");
                $transaction->commit();
            } else {
                Yii::error("支付订单 ID:{$order->id} 自动关闭失败: " . json_encode($order->getErrors()));
                $transaction->rollBack();
            }
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error("订单 ID:{$order->id} 处理异常: " . $e->getMessage());
        }
    }
}
