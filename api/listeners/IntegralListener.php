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
use app\services\IntegralService;
use app\models\IntegralLog;
use app\models\IntegralSendLog;
use app\models\Order;
use app\models\OrderDetail;
use Yii;

/**
 * 购物赠送积分:按商品配置的「赠送时机」发放,并在退款/关闭时追回。
 *
 *   · 结算后赠送(timing=0,默认):order.settled 时发放;结算在售后窗口之后,正常不涉及追回
 *   · 支付即赠送(timing=1):order.paid 时发放;退款/订单关闭(order.commission.revert)时按明细追回,
 *     积分不足强制扣成负数(IntegralService::consume ignoreBalance=true)
 *
 * 每笔发放落 IntegralSendLog(精细到 order_detail_id)做追回锚点;发放幂等靠 IntegralLog.idempotency_key
 * 与本锚点双保险,追回幂等靠锚点 status(granted→reverted)与 sub 的幂等键双保险。
 */
class IntegralListener extends BaseListener
{
    public function register()
    {
        Yii::$app->on(Order::EVENT_ORDER_PAID, [$this, 'onOrderPaid']);
        Yii::$app->on(Order::EVENT_ORDER_SETTLED, [$this, 'onOrderSettled']);
        // 大一统退款回滚信号:取消(detail=0 整单)/ 售后(detail>0 部分),用于追回"支付即赠"的积分
        Yii::$app->on(CommissionRevertEvent::NAME, [$this, 'onRevert']);
    }

    /** 支付成功:发放「支付即赠」时机的商品积分 */
    public function onOrderPaid(OrderEvent $event)
    {
        $this->grantForTiming($event->order, IntegralSendLog::TIMING_PAY);
    }

    /** 订单结算:发放「结算后赠送」时机的商品积分(默认时机,兼容现状) */
    public function onOrderSettled(OrderEvent $event)
    {
        $this->grantForTiming($event->order, IntegralSendLog::TIMING_SETTLE);
    }

    /**
     * 退款 / 订单关闭:追回已发放的赠送积分(按 order_detail_id)。
     * 仅追回 status=已赠送 的记录;整单回滚(order_detail_id=0)追回该订单全部,部分售后只追对应明细。
     */
    public function onRevert(CommissionRevertEvent $event)
    {
        $query = IntegralSendLog::find()->where([
            'order_id' => $event->order_id,
            'status'   => IntegralSendLog::STATUS_GRANTED,
            'is_delete' => 0,
        ]);
        if ((int)$event->order_detail_id > 0) {
            $query->andWhere(['order_detail_id' => (int)$event->order_detail_id]);
        }

        /** @var IntegralSendLog[] $records */
        $records = $query->all();
        foreach ($records as $rec) {
            // 扣回积分与锚点翻 REVERTED 同样要原子:sub 已提交、锚点 save 失败会留下脏 GRANTED,
            // 每次 revert 事件重入都白跑一遍(sub 有幂等键防重扣、不资损,但状态长期不一致)。
            $tx = Yii::$app->db->beginTransaction();
            try {
                // 走核心积分网关(业务类型 order_gift,与发放同类,便于报表按"购物赠送"归口)。
                // ⚠ 刻意用 consume(ignoreBalance=true) 而非 IntegralService::revert():
                //   本链路语义是「按发放锚点全额追回、积分不足扣成负数」;而网关 revert 在有效期开启时
                //   只回收批次存活部分,口径不同,换成 revert 会静默少追回(平台吃亏)。
                (new IntegralService())->consume(
                    $rec->user_id,
                    $rec->integral,
                    'order_gift',
                    [
                        'idempotencyKey' => 'order_revert_integral_' . $rec->order_detail_id, // 同一明细只追一次
                        'title'          => '赠送追回',
                        'content'        => "订单退款/关闭,追回赠送积分(订单#{$rec->order_id})",
                    ],
                    true // ignoreBalance:积分不足强制扣成负数
                );
                $rec->status = IntegralSendLog::STATUS_REVERTED;
                $rec->reverted_at = time();
                $rec->save(false);
                $tx->commit();
                Yii::warning("[积分追回] 订单#{$rec->order_id} 明细#{$rec->order_detail_id} 追回 {$rec->integral}");
            } catch (\Throwable $e) {
                $tx->rollBack();
                Yii::error("[积分追回] 失败 明细#{$rec->order_detail_id}: " . $e->getMessage());
            }
        }
    }

    /**
     * 发放指定时机的赠送积分。遍历订单明细,仅处理快照时机匹配、且金额>0 的明细;
     * 结算时机额外跳过已退款失效明细(STATUS_INVALID)。
     */
    private function grantForTiming(Order $order, int $timing)
    {
        /** @var OrderDetail[] $details */
        $details = $order->orderDetails;
        if (empty($details)) {
            return;
        }

        foreach ($details as $detail) {
            if ($detail->send_integral <= 0) {
                continue;
            }
            // 时机以下单快照为准,避免商家事后改配置导致发放漂移
            if ((int)$detail->send_integral_timing !== $timing) {
                continue;
            }
            // 结算后赠送:已退款失效的明细不发(支付即赠已在支付时发过,退款走追回)
            if ($timing === IntegralSendLog::TIMING_SETTLE && (int)$detail->status === OrderDetail::STATUS_INVALID) {
                continue;
            }
            // 锚点幂等:同一明细只发一次
            if (IntegralSendLog::find()->where(['order_detail_id' => $detail->id, 'is_delete' => 0])->exists()) {
                continue;
            }

            $goodsName = $detail->goods?->name ?? '商品';
            $title = '购物赠送';
            $content = $timing === IntegralSendLog::TIMING_PAY
                ? "支付成功,购买商品[{$goodsName}]赠送"
                : "订单结算,购买商品[{$goodsName}]赠送";

            $key = 'order_send_integral_' . $detail->id; // 与历史一致,保证已发放的结算积分仍幂等
            // 加积分与追回锚点(IntegralSendLog)必须原子:否则 add() 已提交、锚点 save 失败时,
            // 积分已落账却无 GRANTED 锚点,退款 onRevert 查不到→永久追不回(资损)。
            // 外层事务把两步包住,锚点失败连 add() 一并回滚(add 内层事务在 enableSavepoint 下退化为 savepoint)。
            $tx = Yii::$app->db->beginTransaction();
            try {
                // 走核心积分网关:业务类型 order_gift(商品配置的"购物赠送积分")
                $logId = (new IntegralService())->grant(
                    $order->user_id,
                    $detail->send_integral,
                    'order_gift',
                    [
                        'idempotencyKey' => $key,
                        'title'          => $title,
                        'content'        => $content,
                    ]
                );
                // add() 正常返回流水ID;并发幂等命中现也回真实ID(可能为数字字符串)。
                // 防御性:非 int 时按幂等键回查真实ID,保证审计字段不为空。
                if (!is_int($logId)) {
                    $logId = (int)IntegralLog::find()->select('id')->where(['idempotency_key' => $key])->scalar();
                }

                $rec = new IntegralSendLog();
                $rec->mall_id = $order->mall_id;
                $rec->user_id = $order->user_id;
                $rec->order_id = $order->id;
                $rec->order_detail_id = $detail->id;
                $rec->goods_id = (int)$detail->goods_id;
                $rec->integral = $detail->send_integral;
                $rec->timing = $timing;
                $rec->status = IntegralSendLog::STATUS_GRANTED;
                $rec->integral_log_id = (int)$logId;
                $rec->granted_at = time();
                $rec->save(false);

                $tx->commit();
                Yii::warning("[积分赠送] 订单#{$order->id} 明细#{$detail->id} 发放 {$detail->send_integral} (时机={$timing})");
            } catch (\Throwable $e) {
                $tx->rollBack();
                Yii::error("[积分赠送] 失败 明细#{$detail->id}: " . $e->getMessage());
            }
        }
    }
}
