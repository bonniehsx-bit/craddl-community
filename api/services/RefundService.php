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

use app\events\RefundEvent;
use app\models\BaseModel;
use app\models\CommonPayRefund;
use app\models\OrderDetail;
use app\models\CommonPayOrder;
use app\models\Order;
use app\models\OrderRefund;
use app\models\UserCoupon;
use app\models\Goods;
use app\models\Attr;
use Yii;
use yii\db\Exception;

/**
 * Class RefundService
 * @package app\services
 *
 * 售后服务层 - 核心业务逻辑
 * 负责处理：申请售后、审核、退货、打款、库存回滚、状态同步
 */
class RefundService extends BaseModel
{
    // 定义售后类型常量 (与前端保持一致)
    const TYPE_REFUND_ONLY = 1;       // 仅退款
    const TYPE_RETURN_AND_REFUND = 2; // 退货退款
    const TYPE_EXCHANGE = 3;          // 换货

    /**
     * 用户发起售后申请
     * @param int $order_detail_id 订单详情ID
     * @param int $userId 用户ID
     * @param array $params [type, refund_price, reason, content, pic_list]
     * @return OrderRefund|null
     */
    public function apply(int $order_detail_id, int $userId, array $params): ?OrderRefund
    {
        /** @var OrderDetail $orderDetail */
        $orderDetail = OrderDetail::find()->with(['order', 'goods'])
            ->where(['id' => $order_detail_id, 'user_id' => $userId, 'is_delete' => 0])
            ->one();

        if (!$orderDetail) {
            $this->addError('order_detail', '订单商品不存在');
            return null;
        }

        $order = $orderDetail->order;

        // --- 1. 基础校验 ---
        if (!$order) {
            $this->addError('order', '关联的主订单不存在');
            return null;
        }
        // 插件声明不走通用售后的订单(有自己的申诉 / 取消退款流程):以前只靠前端藏按钮,接口直接调照样能申请
        if (!empty(\app\services\order\OrderBehaviorRegistry::ofOrder($order)['no_user_after_sale'])) {
            $this->addError('order', '该订单不支持申请售后,请在订单页按提示处理');
            return null;
        }
        // 必须是已付款状态
        if ($order->pay_status == Order::PAY_STATUS_UNPAID) {
            $this->addError('status', '未付款订单无法申请售后');
            return null;
        }
        // 允许申请的状态：待发货、已发货、已完成
        if (!in_array($order->status, [Order::STATUS_TO_BE_SHIPPED, Order::STATUS_SHIPPED, Order::STATUS_COMPLETED])) {
            $this->addError('status', '当前订单状态不支持申请售后');
            return null;
        }
        // 订单结算后不能再申请售后(2026-09-28 定):结算时分润、货款、积分都已经按这单发出去了
        if ((int)$order->settlement_status === Order::SETTLEMENT_STATUS_SETTLED) {
            $this->addError('status', '订单已结算，不能再申请售后');
            return null;
        }
        // 售后类型只认现在这三种。2026-01 改过编号(原来 0 = 退货退款、1 = 换货),老版本传来的 0 会落成后台处理不了的单
        $applyType = isset($params['type']) ? (int) $params['type'] : self::TYPE_REFUND_ONLY;
        if (!in_array($applyType, [self::TYPE_REFUND_ONLY, self::TYPE_RETURN_AND_REFUND, self::TYPE_EXCHANGE], true)) {
            $this->addError('type', '售后类型不对,请重新选择');
            return null;
        }
        // 商品是否支持售后:认下单时的快照,不认商品现在的开关(见 OrderDetail::canRefund)
        if (!$orderDetail->goods || !$orderDetail->canRefund()) {
            $this->addError('goods', '此商品不支持售后服务');
            return null;
        }
        // 这件已经退过款(售后已完成)或已作废:不能再申请。下面防重复只拦「处理中」的,退完的同一件原来还能再申请一次 ——
        // 能退的上限排除了这件自己的旧售后单(为了被拒 / 撤销后能重新申请),连已经退成功的那次也排除了,
        // 等于拿别的商品的钱再退这件(A 60 + B 40 退完 A 再申请 A:退 40,顾客留着 B 拿回全款),这件还会再发一次作废信号。
        // 申请页预览本来就拦了,拦不住的是直接调接口。换货完成的那件售后标记恢复正常,照样能再申请
        if ((int)$orderDetail->refund_status === OrderDetail::REFUND_STATUS_COMPLETE || (int)$orderDetail->status === OrderDetail::STATUS_INVALID) {
            $this->addError('refund', '这件商品已经退过款了');
            return null;
        }
        // 检查是否重复申请 (排除已完成、已拒绝、已取消的)
        if (
            OrderRefund::find()->where(['order_detail_id' => $order_detail_id, 'is_delete' => 0])
                ->andWhere(['not in', 'status', [OrderRefund::STATUS_COMPLETED, OrderRefund::STATUS_REJECTED, OrderRefund::STATUS_CLOSED]])
                ->exists()
        ) {
            $this->addError('refund', '该商品已在售后流程中，请勿重复申请');
            return null;
        }
        // 【新增】检查支付流水层面的剩余可退额度
        // 背景：插件（如 yuda 取消）可能直接调 RefundTaskService 写 common_pay_refund，
        //      这类退款不会创建 OrderRefund，也不会更新 order_detail.refund_status，
        //      导致上面的 4 道守卫全部通过，但 pay 阶段才超限报错。
        $payOrder = \app\models\CommonPayOrder::find()
            ->where(['order_id' => $order->id, 'is_delete' => 0])
            ->one();
        if ($payOrder) {
            $refundedAmount = $payOrder->getRefundedAmount();
            $remaining = bcsub((string)$payOrder->pay_price, (string)$refundedAmount, 2);
            if (bccomp($remaining, '0.01', 2) < 0) {
                $this->addError('refund', '订单支付额度已全部退回，无需再次申请');
                return null;
            }
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // --- 2. 组装售后单数据 ---
            $refund = new OrderRefund();
            $refund->mall_id = $order->mall_id;
            $refund->mch_id = $order->mch_id;
            $refund->user_id = $userId;
            $refund->order_id = $order->id;
            $refund->order_detail_id = $orderDetail->id;
            $refund->order_no = OrderRefund::generateOrderNo($userId); // 需在Model中实现生成逻辑
            $refund->goods_id = $orderDetail->goods_id;

            // --- 3. 类型强制修正策略 ---
            // 如果订单是【待发货】，强制只能选【仅退款】，防止前端逻辑被绕过
            if ($order->status == Order::STATUS_TO_BE_SHIPPED) {
                $applyType = self::TYPE_REFUND_ONLY;
            }
            $refund->type = $applyType;

            $refund->content = $params['content'] ?? ''; // 备注说明
            // 兼容 reason 字段
            $refund->reason = $params['reason'] ?? ($params['remark'] ?? '');
            $refund->pic_list = is_array($params['pic_list'] ?? null) ? json_encode($params['pic_list']) : ($params['pic_list'] ?? '[]');

            // --- 4. 金额计算 ---
            // 售后只能整件退(2026-09-28 定):退款金额固定为这件能退的全额,不接受顾客自填的部分金额
            $maxRefundPrice = $this->calculateMaxRefundPriceForDetail($order, $orderDetail);
            $refund->refund_price = max(0, $maxRefundPrice);

            $refund->status = OrderRefund::STATUS_PENDING; // 待审核
            $refund->created_at = time();

            if (!$refund->save()) {
                throw new Exception($this->getErrorMsg($refund));
            }

            // --- 5. 更新状态 ---
            // 更新子订单状态 -> 售后申请中
            $orderDetail->refund_status = OrderDetail::REFUND_STATUS_APPLIED;
            if (!$orderDetail->save()) {
                throw new Exception('更新订单详情售后状态失败');
            }

            // 更新主订单状态 -> 标记为有售后
            if ($order->refund_status == Order::REFUND_STATUS_NO_REFUND || $order->refund_status == Order::REFUND_STATUS_COMPLETED) {
                $order->refund_status = Order::REFUND_STATUS_APPLIED;
                if (!$order->save()) {
                    throw new Exception('更新主订单售后状态失败');
                }
            }

            $transaction->commit();

            // 触发通知事件
            Yii::$app->trigger(RefundEvent::EVENT_APPLIED, new RefundEvent(['refund' => $refund]));

            return $refund;
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error("售后申请异常: " . $e->getMessage());
            $this->addError('apply', $e->getMessage());
            return null;
        }
    }

    /**
     * 商家同意售后申请
     * @param int $refundId
     * @param array $params 可选参数，如退货地址 {return_name, return_mobile, return_address}
     * @return bool
     */
    public function agree(int $refundId, array $params = []): bool
    {
        /** @var OrderRefund $refund */
        $refund = OrderRefund::find()->with(['order', 'orderDetail'])->where(['id' => $refundId])->one();

        if (!$refund) {
            $this->addError('refund', '售后单不存在');
            return false;
        }
        if ($refund->status != OrderRefund::STATUS_PENDING) {
            $this->addError('status', '售后单状态已变更，无法操作');
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            self::claimStatus($refund, [OrderRefund::STATUS_PENDING], OrderRefund::STATUS_AGREED);

            // 如果是【退货退款】或【换货】，需要保存商家收货地址
            if (in_array($refund->type, [self::TYPE_RETURN_AND_REFUND, self::TYPE_EXCHANGE])) {
                if (!empty($params['return_name']))
                    $refund->return_name = $params['return_name'];
                if (!empty($params['return_mobile']))
                    $refund->return_mobile = $params['return_mobile'];
                if (!empty($params['return_address']))
                    $refund->return_address = $params['return_address'];
            }

            if (!$refund->save()) {
                throw new \Exception($this->getErrorMsg($refund));
            }

            // 注意：同意并不代表完成，如果是仅退款，通常需要商家再点一次“打款”才算完成
            // 或者是根据配置自动打款。这里保持逻辑分离，同意仅改变状态。

            $this->updateOrderStatusAfterAction($refund->order);
            $transaction->commit();

            Yii::$app->trigger(RefundEvent::EVENT_AGREED, new RefundEvent(['refund' => $refund]));

            // ⛔ 同意时不发分润回滚信号,等钱真退给顾客再发(RefundSuccessDispatcher::handleAfterSales):
            //    顾客在「已同意、还没寄货」时还能撤销售后,撤销后这件没退、订单照常结算;
            //    原来同意就回滚,分润 / 团队业绩 / 赠送积分回滚了又没有补回的路径,这件的钱就白丢了
            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            $this->addError('agree', $e->getMessage());
            return false;
        }
    }

    /**
     * 商家拒绝售后申请
     * @param int $refundId
     * @param string|null $reason 拒绝原因
     * @return bool
     */
    public function reject(int $refundId, ?string $reason = ''): bool
    {
        /** @var OrderRefund $refund */
        $refund = OrderRefund::find()->with(['order', 'orderDetail'])->where(['id' => $refundId])->one();

        if (!$refund || $refund->status != OrderRefund::STATUS_PENDING) {
            $this->addError('refund', '售后单不存在或状态不可操作');
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            self::claimStatus($refund, [OrderRefund::STATUS_PENDING], OrderRefund::STATUS_REJECTED);
            $refund->remark = $reason; // 记录拒绝原因
            if (!$refund->save()) {
                throw new \Exception($this->getErrorMsg($refund));
            }

            // 【关键】恢复子订单状态为“正常”，允许用户再次申请
            $orderDetail = $refund->orderDetail;
            if ($orderDetail) {
                $orderDetail->refund_status = OrderDetail::REFUND_STATUS_NORMAL;
                if (!$orderDetail->save()) {
                    throw new \Exception('恢复订单详情售后状态失败');
                }
            }

            $this->updateOrderStatusAfterAction($refund->order);
            $transaction->commit();
            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            $this->addError('reject', $e->getMessage());
            return false;
        }
    }

    /**
     * 商家确认换货完成:顾客寄回的货收到了、换的商品也寄出去了(后台 / 商家后台「完成换货」按钮)。
     *
     * 换货不退钱:售后单记成已完成,这件的售后标记恢复正常 —— 照常结算、分润照发;再同步主订单的售后状态,
     * 订单不再卡在「售后中」。不发退款完成 / 分润回滚事件,那些都是钱退了才发的。
     * 已同意、顾客已寄回两个状态都能完成(按钮就在这两个状态下出现)。
     *
     * 这个方法 2026-01 改退款那次被删掉了,两个后台按钮一直在调它,点了就报错,换货单永远完成不了。
     */
    public function completeExchange(int $refundId): bool
    {
        /** @var OrderRefund $refund */
        $refund = OrderRefund::find()->with(['order', 'orderDetail'])->where(['id' => $refundId])->one();
        if (!$refund || (int)$refund->type !== self::TYPE_EXCHANGE) {
            $this->addError('refund', '售后单不存在或不是换货');
            return false;
        }
        if (!in_array((int)$refund->status, [OrderRefund::STATUS_AGREED, OrderRefund::STATUS_USER_SHIPPED], true)) {
            $this->addError('status', '换货申请还没同意或已经处理完了');
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            self::claimStatus($refund, [OrderRefund::STATUS_AGREED, OrderRefund::STATUS_USER_SHIPPED], OrderRefund::STATUS_COMPLETED);

            $orderDetail = $refund->orderDetail;
            if ($orderDetail && (int)$orderDetail->refund_status !== OrderDetail::REFUND_STATUS_NORMAL) {
                $orderDetail->refund_status = OrderDetail::REFUND_STATUS_NORMAL;
                if (!$orderDetail->save()) {
                    throw new \Exception('恢复订单详情售后状态失败');
                }
            }

            $this->updateOrderStatusAfterAction($refund->order);
            $transaction->commit();
            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            $this->addError('exchange', $e->getMessage());
            return false;
        }
    }

    /**
     * 执行退款打款操作 (核心财务逻辑)
     * 包括：调起支付接口、这件作废(分润按这一刻回滚,见 voidDetail)、库存回滚、积分优惠券返还
     * @param int $refundId
     * @return bool
     */
    public function completeRefund(int $refundId): bool
    {
        /** @var OrderRefund $refund */
        $refund = OrderRefund::find()->where(['id' => $refundId])->forUpdate()->one();

        if (!$refund || $refund->is_refund == 1) {
            $this->addError('refund', '售后单不存在或已退款');
            return false;
        }
        // 允许打款的状态：已同意 或 用户已发货(针对退货退款)
        if (!in_array($refund->status, [OrderRefund::STATUS_AGREED, OrderRefund::STATUS_USER_SHIPPED])) {
            $this->addError('status', '售后单状态不正确，无法打款');
            return false;
        }

        $order = $refund->order;
        $orderDetail = $refund->orderDetail;

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 1. 调用支付接口进行真实退款
            $taskService = new RefundTaskService();
            // 这里我们把 refund_id 作为 source_id，类型为售后
            $taskService->createRefundTask(
                $order->id,
                $refund->refund_price,
                CommonPayRefund::SOURCE_AFTER_SALES,
                $refund->id
            );

            // 2. 更新售后单状态
            // 修改：不再直接标记为完成，而是进入“处理中”状态，等待 Cron 任务轮询确认
            $refund->status = OrderRefund::STATUS_PROCESSING;
            // $refund->is_refund = 0; // 保持为0，等到账后再更新
            // $refund->refund_time = ... // 移除，等到账后再更新

            if (!$refund->save()) {
                throw new \Exception($this->getErrorMsg($refund));
            }

            // 3. 这件作废:商家确认打款,这件就退定了 —— 之后顾客不能再撤销,退款也撤不回
            //    (退款失败处理台只有重新发起 / 登记已退款)。分润、业绩按这一刻算,不看钱到没到(2026-09-28 定)。
            //    售后标记先记「处理中」,钱退成功再记「已完成」(RefundSuccessDispatcher::handleAfterSales)。
            //    只在这件由有效变作废的这一刻广播,已经作废的不再发 —— 每件的按明细信号只发一次
            $voidNow = (int)$orderDetail->status !== OrderDetail::STATUS_INVALID;
            $orderDetail->refund_status = OrderDetail::REFUND_STATUS_PROCESSING;
            $orderDetail->status = OrderDetail::STATUS_INVALID;

            if (!$orderDetail->save()) {
                throw new \Exception($this->getErrorMsg($orderDetail));
            }

            // 4. 【核心】处理库存回滚
            // 业务决定：打款发起的瞬间，就应该回滚库存/优惠券/积分？
            // 还是等钱到账了再回滚？
            // 通常“商户点同意并打款”，货物权属已经转移，应该回滚。
            $this->handleStockRollback($refund);

            // 5. 返还优惠券
            // 券正被别的有效订单用着就不退(修复前自动选券的老订单记了券号却没核销,退它会把别人用掉的券退回)
            if ($orderDetail->user_coupon_id && !Order::find()->where(['user_coupon_id' => (int)$orderDetail->user_coupon_id, 'is_delete' => 0])
                    ->andWhere(['<>', 'id', (int)$order->id])->andWhere(['<>', 'status', Order::STATUS_CLOSED])->exists()) {
                UserCoupon::updateAll(['status' => 0, 'used_at' => 0], ['id' => $orderDetail->user_coupon_id, 'status' => 1]);
            }

            // 6. 返还积分
            if ($order->is_use_integral && $orderDetail->use_integral > 0) {
                $idempotencyKey = 'order_refund_integral_' . $refund->id;
                // 走核心积分网关:与下单 order_deduct、取消 order_deduct_refund 同一条抵扣链,类型统一
                (new \app\services\IntegralService())->grant(
                    $order->user_id,
                    $orderDetail->use_integral,
                    'order_deduct_refund',
                    [
                        'idempotencyKey' => $idempotencyKey,
                        'title'          => '售后返还',
                        'content'        => "售后退款返还,售后单:{$refund->order_no}",
                    ]
                );
            }

            // 7. 同步主订单状态
            $this->updateOrderStatusAfterAction($order);

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error("打款失败: " . $e->getMessage());
            $this->addError('refund', $e->getMessage());
            return false;
        }

        // 8. 这件作废了(第 3 步):广播给各插件、平台统一追回分润、重算收入 —— 都在事务提交之后
        if ($voidNow) {
            self::voidDetail($refund);
        }

        // 触发通知事件 (通知用户：正在打款中)
        // Yii::$app->trigger(RefundEvent::EVENT_PROCESSING, ...);

        return true;
    }

    /**
     * 售后退款的这件作废之后,统一做三件事(商家确认打款时调;在途的老售后单由退款成功收尾补调,见 handleAfterSales):
     *   ① 广播 order.commission.revert(这件)—— 告诉各插件「这件作废了」,插件回滚自己记在这件上的
     *      分润、业绩、赠送积分、码、奖池……插件收到时分润还是待结算 / 已结算,所以排在 ② 之前
     *   ② 平台统一追回这件的分润:没发的作废,已发的从原账户扣回(可扣成负数)
     *   ③ 重算订单的商家收入 / 平台收入(按没作废的明细)
     * 每一步单独兜底,一步出错不拦后面的,也不影响已经确认的打款
     */
    public static function voidDetail(OrderRefund $refund): void
    {
        $order = Order::findOne((int)$refund->order_id);
        try {
            Yii::$app->trigger(\app\events\CommissionRevertEvent::NAME, new \app\events\CommissionRevertEvent([
                'mall_id' => $order ? (int)$order->mall_id : 0,
                'order_id' => (int)$refund->order_id,
                'order_detail_id' => (int)$refund->order_detail_id, // 售后单对应单个明细 → 明细级回滚
                'source' => 'refund',
            ]));
        } catch (\Throwable $e) {
            Yii::error("[售后这件作废] 分润回滚信号处理失败 RefundID:{$refund->id} " . $e->getMessage());
        }
        try {
            RefundSuccessDispatcher::revertCommissions(['order_detail_id' => (int)$refund->order_detail_id, 'is_delete' => 0], '退款扣回');
        } catch (\Throwable $e) {
            Yii::error("[售后这件作废] 分润追回失败 RefundID:{$refund->id} " . $e->getMessage());
        }
        if ($order) {
            try {
                (new \app\services\order\actions\OrderSettlementService())->recalculateAfterRefund($order);
            } catch (\Throwable $e) {
                Yii::error("[售后这件作废] 重算收入失败 OrderID:{$order->id} " . $e->getMessage());
            }
        }
    }

    /**
     * 处理库存回滚逻辑 (智能判断)
     * @param OrderRefund $refund
     */
    private function handleStockRollback(OrderRefund $refund)
    {
        $order = $refund->order;
        $detail = $refund->orderDetail;
        $shouldRestock = false;

        // 场景A：退货退款 (Type 2)
        // 既然是退货，商家收到了货，理应回滚库存
        if ($refund->type == self::TYPE_RETURN_AND_REFUND) {
            $shouldRestock = true;
        } // 场景B：仅退款 (Type 1)
        elseif ($refund->type == self::TYPE_REFUND_ONLY) {
            // 子场景 B1：未发货时的仅退款 -> 货还在仓库 -> 必须回滚
            if ($order->delivery_status == Order::DELIVERY_STATUS_UNSHIPPED) {
                $shouldRestock = true;
            }
            // 子场景 B2：已发货时的仅退款 -> 货已发出(可能破损/丢件/仅退款不退货) -> 通常不回滚
            // 这里不做操作，$shouldRestock 默认为 false
        }

        if ($shouldRestock) {
            try {
                // Polymorphic stock rollback
                $itemData = [
                    'order_detail_id' => $detail->id,
                    'attr_id' => $detail->attr_id,
                    'is_pay' => $order->isPaid()
                ];
                $itemHandler = \app\services\order\factory\OrderHandlerFactory::create(
                    $order->plugin,
                    $detail->goods->type ?? $order->type
                );
                $itemHandler->returnStock($detail->goods, $detail->attrModel, $detail->num, $itemData);
            } catch (\Exception $e) {
                // 库存非核心资产(相比金额)，回滚失败记录日志即可，不要阻断退款流程
                Yii::error("库存回滚失败 DetailID:{$detail->id} - " . $e->getMessage());
            }
        }
    }

    /**
     * 用户填写退货物流 (针对退货退款)
     * @param int $refundId
     * @param int $userId
     * @param array $params {express, express_no}
     * @return OrderRefund|null
     */
    public function fillReturnExpress(int $refundId, int $userId, array $params): ?OrderRefund
    {
        /** @var OrderRefund $refund */
        $refund = OrderRefund::find()->where([
            'id' => $refundId,
            'user_id' => $userId,
            'is_delete' => 0
        ])->one();

        if (!$refund || $refund->status != OrderRefund::STATUS_AGREED) {
            $this->addError('status', '售后单状态不可填写物流');
            return null;
        }
        if (empty($params['express']) || empty($params['express_no'])) {
            $this->addError('express', '请填写快递公司和单号');
            return null;
        }

        $refund->user_express = $params['express'];
        $refund->user_express_no = $params['express_no'];
        $refund->user_express_at = time();
        $refund->status = OrderRefund::STATUS_USER_SHIPPED; // 变为“用户已发货”

        if ($refund->save()) {
            return $refund;
        }
        $this->addError('save', $this->getErrorMsg($refund));
        return null;
    }

    /**
     * 公开方法：获取最大退款金额 (供Form预览使用)
     * @param OrderDetail $orderDetail
     * @return float
     */
    public function getMaxRefundPrice(OrderDetail $orderDetail): float
    {
        if ($orderDetail->order) {
            return $this->calculateMaxRefundPriceForDetail($orderDetail->order, $orderDetail);
        }
        // 如果未预加载order，尝试懒加载
        $order = Order::findOne($orderDetail->order_id);
        if ($order) {
            return $this->calculateMaxRefundPriceForDetail($order, $orderDetail);
        }
        return 0.00;
    }

    /**
     * 内部计算逻辑：计算单个商品最大可退金额
     * @param Order $order
     * @param OrderDetail $orderDetail
     * @return float
     */
    /**
     * 内部计算逻辑：计算单个商品最大可退金额 (含运费智能判断)
     * @param Order $order
     * @param OrderDetail $orderDetail
     * @return float
     */
    private function calculateMaxRefundPriceForDetail(Order $order, OrderDetail $orderDetail): float
    {
        // 1. 基础金额：商品的实付金额
        $maxRefundPrice = 0.00;
        if (isset($orderDetail->pay_price) && $orderDetail->pay_price > 0) {
            $maxRefundPrice = (float) $orderDetail->pay_price;
        } else {
            // 降级策略：按比例分摊 (防止历史数据 pay_price 为空)
            $actualPaidGoodsTotal = bcsub($order->pay_price, $order->express_price, 2);
            if ($actualPaidGoodsTotal > 0 && $order->goods_total_price > 0) {
                $ratio = bcdiv($orderDetail->total_price, $order->goods_total_price, 8);
                $maxRefundPrice = (float) bcmul($actualPaidGoodsTotal, $ratio, 2);
            }
        }

        // 2. 【核心新增】运费退还逻辑
        // 条件A: 必须有运费
        // 条件B: 必须未发货 (已发货通常不退运费，除非是质量问题，那是额外赔付逻辑)
        if ($order->express_price > 0 && $order->delivery_status == Order::DELIVERY_STATUS_UNSHIPPED) {

            // 检查当前订单是否还有“其他有效商品”
            // 有效商品的定义：未删除 且 售后状态不是已完成
            $hasOtherActiveItems = OrderDetail::find()
                ->where(['order_id' => $order->id, 'is_delete' => 0])
                ->andWhere(['!=', 'id', $orderDetail->id]) // 排除当前这个正在退的
                ->andWhere([
                    'in',
                    'refund_status',
                    [
                        OrderDetail::REFUND_STATUS_NORMAL,  // 正常
                        OrderDetail::REFUND_STATUS_APPLIED, // 申请中 (还没退完也不算)
                        OrderDetail::REFUND_STATUS_PROCESSING // 处理中
                    ]
                ])
                ->exists();

            // 如果没有其他有效商品了，说明这是“最后一根稻草”
            // 此时退款，相当于整单全退，所以要把运费加上
            if (!$hasOtherActiveItems) {
                $maxRefundPrice = bcadd($maxRefundPrice, $order->express_price, 2);
            }
        }

        // 3. 扣除已退总额，防止超退
        // 查本订单已退+处理中的退款总额（排除已拒绝/已关闭的，排除当前商品的旧记录以支持重新申请）
        $refundedTotal = (float)(OrderRefund::find()
            ->where(['order_id' => $order->id, 'is_delete' => 0])
            ->andWhere(['not in', 'status', [OrderRefund::STATUS_REJECTED, OrderRefund::STATUS_CLOSED]])
            ->andWhere(['!=', 'order_detail_id', $orderDetail->id])
            ->sum('refund_price') ?: 0);

        $remainingRefundable = (float)bcsub($order->pay_price, $refundedTotal, 2);
        $maxRefundPrice = min($maxRefundPrice, $remainingRefundable);

        // 4. 【新增】用支付流水层面的已占用额度再做一次扣减
        // 覆盖场景：插件直接调 RefundTaskService 写 common_pay_refund 但未创建 OrderRefund（如 yuda_cancel）
        // 这类退款上面第 3 步的 OrderRefund 查询无法感知，必须用 common_pay_refund 真相做兜底
        $payOrder = \app\models\CommonPayOrder::find()
            ->where(['order_id' => $order->id, 'is_delete' => 0])
            ->one();
        if ($payOrder) {
            $payRemaining = (float)bcsub((string)$payOrder->pay_price, (string)$payOrder->getRefundedAmount(), 2);
            $maxRefundPrice = min($maxRefundPrice, $payRemaining);
        }

        return (float) max(0, $maxRefundPrice);
    }

    /**
     * 同步主订单状态 (每次操作后调用)
     * 逻辑：根据所有子订单的状态，决定主订单是“售后中”、“已关闭”还是“部分退款”
     */
    /**
     * 抢售后单状态:库里还是 $from 里的状态才改成 $to,改不到(别人已经先处理了)就报错,调用方的事务整个回滚。
     *
     * 同意 / 拒绝都是在锁外读的状态:两个人几乎同时点同意和拒绝(或顾客正好撤销),两边都能过前面的判断;
     * 而 save() 只按 id 更新、恒成功,后写的会把先写的覆盖掉 —— 同意还会已经发出分润回滚。
     * 所以状态用带旧状态的条件更新来改,只有一方能改到。改到后照常 save() 写其余字段(同值的状态再写一遍无妨)。
     */
    private static function claimStatus(OrderRefund $refund, array $from, int $to): void
    {
        $n = OrderRefund::updateAll(['status' => $to], ['id' => (int)$refund->id, 'status' => $from]);
        if ($n !== 1) {
            throw new \Exception('售后单状态已变更,请刷新后再操作');
        }
        $refund->status = $to;
    }

    public function updateOrderStatusAfterAction(Order $order): bool
    {
        // 开启内部短事务或使用锁，防止并发更新状态
        $internalTransaction = Yii::$app->db->beginTransaction();
        try {
            /** @var Order $orderToUpdate */
            $orderToUpdate = Order::find()->where(['id' => $order->id])->forUpdate()->one();
            if (!$orderToUpdate)
                throw new \Exception("主订单未找到");

            // 统计数据
            $totalDetailCount = (int) OrderDetail::find()->where(['order_id' => $orderToUpdate->id, 'is_delete' => 0])->count();

            // 已退款完成的详情数
            // ⛔ 必须和上面的分母同口径带 is_delete=0。不带的话,一条软删且
            //    refund_status=COMPLETE 的明细就能把分子顶到 >= 分母,
            //    isFullyRefunded 误判为 true → 订单被误关闭并标成全额退款,
            //    而真正在售的那条明细根本没退款。
            $refundedDetailCount = (int) OrderDetail::find()->where([
                'order_id' => $orderToUpdate->id,
                'is_delete' => 0,
                'refund_status' => OrderDetail::REFUND_STATUS_COMPLETE
            ])->count();

            // 正在售后中的数量 (申请中、同意、已发货、处理中)
            $processingCount = OrderRefund::find()->where(['order_id' => $orderToUpdate->id, 'is_delete' => 0])
                ->andWhere(['in', 'status', OrderRefund::IN_PROGRESS_STATUS])
                ->count();

            // 1. 核心状态流转：如果所有商品都退款了 -> 关闭订单
            $isFullyRefunded = ($totalDetailCount > 0 && $refundedDetailCount >= $totalDetailCount);
            if ($isFullyRefunded) {
                $orderToUpdate->pay_status = Order::PAY_STATUS_REFUNDED;
                $orderToUpdate->status = Order::STATUS_CLOSED; // 交易关闭
            } elseif ($refundedDetailCount > 0) {
                $orderToUpdate->pay_status = Order::PAY_STATUS_PARTIAL_REFUND; // 部分退款
                // 主状态保持不变 (如待发货/待收货/已完成)
            }

            // 2. 售后标识流转
            //
            // ⛔ 整单退完时,不管还有没有开着的售后单,订单级标记一律终结。
            //    否则会出现「status=已关闭 + refund_status=售后中」两个字段打架:
            //    插件强制整单退款(拼团失败 / 酒店拒单 / 门店取消,11 处 force=true 调用)
            //    走的是 cancel_order / 插件直写流水这两条路,它们**不碰 order_refund**,
            //    用户之前申请的售后单会留在 PENDING/AGREED/USER_SHIPPED,
            //    正好落进下面 processingCount 的集合里。
            //
            // ⚠ 那些售后单是**故意留着不关**的 —— 退货类售后里用户可能已经把货寄出,
            //    单子一关,商家收到包裹就查不到来历了(详见 refund-audit ⑨)。
            //    所以这里只收敛订单级标记,不动售后单本身:
            //    订单列表显示「已关闭/已退款」,售后列表那条仍在,货有据可查。
            if ($isFullyRefunded) {
                $orderToUpdate->refund_status = Order::REFUND_STATUS_COMPLETED;
            } elseif ($processingCount > 0) {
                $orderToUpdate->refund_status = Order::REFUND_STATUS_PROCESSING; // 售后中
            } else {
                if ($refundedDetailCount > 0) {
                    $orderToUpdate->refund_status = Order::REFUND_STATUS_COMPLETED; // 售后结束(有退款)
                } else {
                    $orderToUpdate->refund_status = Order::REFUND_STATUS_NO_REFUND; // 无售后
                }
            }

            if (!$orderToUpdate->save()) {
                throw new \Exception('状态更新失败');
            }

            $internalTransaction->commit();
            return true;
        } catch (\Throwable $e) {
            $internalTransaction->rollBack();
            Yii::error("更新主订单状态失败: " . $e->getMessage());
            return false;
        }
    }
}