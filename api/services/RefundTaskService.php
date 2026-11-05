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

namespace app\services;

use app\models\BaseModel;
use app\models\CommonPayOrder;
use app\models\CommonPayRefund;
use Yii;
use yii\db\Exception;

class RefundTaskService extends BaseModel
{
    /**
     * 创建退款任务 (写入流水表)
     *
     * @param string $order_no 订单号
     * @param float $refund_price 本次申请退款金额
     * @param string $source_type 来源 (CommonPayRefund::SOURCE_AFTER_SALES 等)
     * @param int $source_id 来源ID (如 order_refund_id)
     * @return bool
     * @throws Exception
     */
    public function createRefundTask($order_id, $refund_price, $source_type, $source_id)
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 1. 【核心】查询支付主单，并加排他锁 (FOR UPDATE)
            // 这行代码执行后，其他并发请求如果也想操作这行订单，必须等待当前事务提交
            /** @var CommonPayOrder $payOrder */
            $payOrder = CommonPayOrder::find()
                ->where(['order_id' => $order_id, 'is_delete' => 0])
                ->forUpdate()
                ->one();

            if (!$payOrder) {
                throw new Exception("支付订单不存在: {$order_id}");
            }
            if ($payOrder->is_pay != 1) {
                throw new Exception("该订单尚未支付，无法退款");
            }

            // 2. 计算金额风控
            // 已占用额度 (已退 + 处理中 + 待处理)
            $refundedAmount = $payOrder->getRefundedAmount();

            // 本次申请后的总额
            $totalRequest = bcadd($refundedAmount, $refund_price, 2);
            $payPrice = (float) $payOrder->pay_price;

            // 如果 (已退 + 本次) > 支付总额，报错
            if (bccomp($totalRequest, $payPrice, 2) === 1) {
                throw new Exception("退款金额超限! 订单支付:{$payPrice}, 已占用:{$refundedAmount}, 本次申请:{$refund_price}");
            }

            // 3. 检查是否重复创建 (幂等性检查)
            // 同一个来源(source_type + source_id) 不应该重复生成退款任务
            $exists = CommonPayRefund::find()->where([
                'source_type' => $source_type,
                'source_id' => $source_id,
                'is_delete' => 0
            ])->andWhere(['!=', 'status', CommonPayRefund::STATUS_FAIL])->exists();

            if ($exists) {
                // 如果已经存在且不是失败状态，直接返回true，视为成功（幂等处理）
                $transaction->commit();
                return true;
            }

            // 4. 写入退款流水
            $refundLog = new CommonPayRefund();
            $refundLog->mall_id = $payOrder->mall_id;
            $refundLog->user_id = $payOrder->user_id;
            $refundLog->common_pay_order_id = $payOrder->id;
            $refundLog->order_no = $payOrder->order_no;
            // 关键新增：记录支付时的交易单号（与网关交互用），而 order_no 仅作为系统单号
            $refundLog->out_trade_no = $payOrder->out_trade_no;
            $refundLog->out_refund_no = CommonPayRefund::generateRefundNo(); // 生成新单号
            $refundLog->refund_price = $refund_price;
            $refundLog->source_type = $source_type;
            $refundLog->source_id = $source_id;
            $refundLog->status = CommonPayRefund::STATUS_PENDING; // 初始状态：待处理

            if (!$refundLog->save()) {
                throw new Exception($this->getErrorMsg($refundLog));
            }

            $transaction->commit();
            return true;

        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error("创建退款任务失败 OrderNo:{$order_id} - " . $e->getMessage());
            // 抛出异常给上层业务捕获
            throw $e;
        }
    }
}