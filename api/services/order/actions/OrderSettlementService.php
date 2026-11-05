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

namespace app\services\order\actions;

use app\events\OrderEvent;
use app\services\order\OrderBehaviorRegistry;
use app\handlers\order\BaseOrderHandler;

// 引入基类
use app\handlers\order\NormalOrderHandler;

// 引入普通处理器
use app\models\BaseModel;
use app\models\CommissionLog;
use app\models\CommonPayOrder;
use app\models\Goods;
use app\models\Order;
use app\models\OrderDetail;
use app\models\UserCoupon;
use app\services\order\factory\OrderHandlerFactory;

// [核心] 引入工厂
use Yii;

/**
 * 订单结算与生命周期管理服务
 * 职责：负责订单的“终结”阶段，包括取消订单、确认收货、用户删除以及最终的资金结算逻辑。
 */
class OrderSettlementService extends BaseModel
{

    /**
     * [私有] 事务内对订单行加排他锁并重载,实现 cancel / confirm / orderSettle 三个终态方法的**跨方法互斥串行**。
     *
     * 背景:这三个方法都是"读 $order->status → 判断 → 写新状态 + 触发事件"的结构。各步下游副作用(库存/退款单/
     * 优惠券/积分/分润回滚)虽各自幂等,但**幂等防不住跨方法竞态**——例如 force-cancel(SHIPPED→CLOSED)与
     * confirm(SHIPPED→COMPLETED)同时放行,会让同一单既退款又结算/打佣;或 cancel 不加锁直接覆盖一笔正在
     * orderSettle 的已结算单。故必须让三者抢同一把行锁、并在锁后各自复检前置状态,谁先拿锁谁赢,后者复检失败放弃。
     *
     * 用法:必须在已开启的事务内调用;调用方负责在返回 false 时 rollBack。返回后 $order 已是锁内权威数据。
     *
     * @return bool true=已持锁并重载;false=订单不存在(行已被物理删除)
     */
    private function lockOrderRow(Order $order): bool
    {
        // ⛔ 必须用「锁定读」拿锁内数据,不能 SELECT id FOR UPDATE 之后再 refresh():
        //    可重复读下外层事务里的普通 SELECT 读的是事务快照(如 AutoCancelOrderTask 先 findOne 过订单),
        //    看不见并发刚提交的支付(status 已是待发货)→ 把已付款的单当未付关掉、还不建退款。
        $fresh = Order::find()->where(['id' => $order->id])->forUpdate()->one();
        if (!$fresh) {
            return false;
        }
        foreach (array_keys($order->getRelatedRecords()) as $name) {
            unset($order->$name); // 与 refresh() 一致:丢掉已加载的关联,后面按锁内数据重新取
        }
        Order::populateRecord($order, $fresh->getAttributes());
        $order->afterRefresh();
        return true;
    }

    /**
     * 取消订单 (完整重构版)
     * @param Order $order
     * @param bool $force 是否强制取消(忽略部分权限检查)
     * @param bool $unpaidOnly 只取消待付款的单(顾客自己取消):在订单行锁里再确认一次
     * @return bool
     */
    public function cancel(Order $order, $force = false, bool $unpaidOnly = false)
    {
        // 1. 基础状态防御
        Yii::warning("订单取消 ID:{$order->id} NO:{$order->order_no}");
        if (!in_array($order->status, [Order::STATUS_UNPAID, Order::STATUS_TO_BE_SHIPPED])) {
            // God mode: if force is true, allow canceling SHIPPED (accepted) orders.
            if (!($force && $order->status == Order::STATUS_SHIPPED)) {
                $this->addError('status', '当前订单状态不可取消');
                return false;
            }
        }

        // 2. 业务权限校验
        try {
            $handler = OrderHandlerFactory::create($order->plugin, $order->type);
            $handler->checkCancelPermission($order, $force);
        } catch (\Exception $e) {
            Yii::warning($e->getMessage());
            $this->addError('cancel', $e->getMessage());
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 2b. 行锁 + 锁内复检:与 confirm / orderSettle 抢同一把行锁。拿锁后订单状态可能已被并发操作改写
            //     (如刚被 confirm 成 COMPLETED、或已被取消成 CLOSED),必须基于锁内权威状态重判可取消性,否则
            //     会出现"force-cancel 覆盖已确认/已结算单 → 同一单既退款又结算/打佣"的跨方法竞态(上面第1步是事务外快路径,不具原子性)。
            if (!$this->lockOrderRow($order)) {
                $transaction->rollBack();
                $this->addError('order', '订单不存在');
                return false;
            }
            $cancelable = in_array($order->status, [Order::STATUS_UNPAID, Order::STATUS_TO_BE_SHIPPED])
                || ($force && $order->status == Order::STATUS_SHIPPED);
            if (!$cancelable) {
                $transaction->rollBack();
                $this->addError('status', '当前订单状态不可取消');
                return false;
            }
            // 顾客自己取消只能取消待付款的单。表单那道检查读的不是锁内数据:支付恰好在这之间落地的话,
            // 这里会把已付款的单关掉并自动退款,绕过售后审核
            if ($unpaidOnly && (int)$order->status !== Order::STATUS_UNPAID) {
                $transaction->rollBack();
                $this->addError('status', '订单已付款，请申请售后进行退款');
                return false;
            }

            // 3. 更新主订单状态
            $order->status = Order::STATUS_CLOSED;
            $order->cancel_at = time();
            $order->settlement_status = Order::SETTLEMENT_STATUS_INVALID; // 标记无效结算
            $order->settled_at = time();

            if (!$order->save()) {
                Yii::error('主订单保存失败: ' . json_encode($order->getFirstErrors()));
                throw new \Exception('订单状态更新失败');
            }

            // 4. 执行库存回滚
            foreach ($order->orderDetails as $detail) {
                // 售后已经退掉的那件(明细作废)跳过:确认打款时售后已按「退货 / 仅退款、发没发货」决定过它回不回库存,
                // 这里再回一次就多了(returnStock 的防重锁只管一小时)
                if ((int)$detail->status === OrderDetail::STATUS_INVALID) {
                    continue;
                }
                $itemData = [
                    'order_detail_id' => $detail->id,
                    'attr_id' => $detail->attr_id,
                    'is_pay' => $order->isPaid()
                ];

                $itemHandler = OrderHandlerFactory::create(
                    $order->plugin,
                    $detail->goods->type ?? $order->type
                );
                // 即使是未付款取消，也需要回滚“下单占用”的库存
                $itemHandler->returnStock($detail->goods, $detail->attrModel, $detail->num, $itemData);
            }

            // 5. 资产返还 (优惠券 & 积分)
            $this->revertCoupons($order);
            $this->revertIntegrals($order);
            // 商户首单立减:下单时占的名额,没付款就取消了要还回去(已付款的走退款归还,这里不管)
            \app\plugins\mch\services\FirstOrderQuotaService::release($order);

            // 6. [核心改造] 处理支付单与退款逻辑

            // A. 无论是否支付，先将支付主单标记为“已取消”
            // 目的：防止用户在取消订单的毫秒级瞬间支付成功，导致异步回调继续执行“发货”逻辑
            CommonPayOrder::updateAll(
                ['is_cancel' => 1, 'cancel_at' => time()],
                ['order_no' => $order->order_no, 'is_pay' => 0] // 仅更新未支付的
            );

            // B. 如果订单已支付，执行退款流程
            if ($order->isPaid()) {

                // [新逻辑] 只有实付金额大于0才需要走退款流程 (纯积分兑换不涉及资金退款)
                if ($order->pay_price > 0) {
                    // 只退还没退的部分:售后先退过其中几件的单,关单退剩下的(原来整单全退,超出实付被额度守卫拦下,关单失败)。
                    // 先锁支付单再算,额度口径与守卫一致(已退 + 在退 + 待退);都退过了就不再建退款
                    $payOrder = CommonPayOrder::find()->where(['order_id' => $order->id, 'is_delete' => 0])->forUpdate()->one();
                    $refundable = $payOrder ? $payOrder->getRefundableAmount() : (string)$order->pay_price;
                    if (bccomp($refundable, '0', 2) > 0) {
                        $refundTaskService = new \app\services\RefundTaskService();
                        // 调用新写的服务，写入 common_pay_refund 流水表
                        $refundTaskService->createRefundTask(
                            $order->id,
                            $refundable,
                            \app\models\CommonPayRefund::SOURCE_CANCEL_ORDER, // 来源：取消订单
                            $order->id // 来源ID：订单ID
                        );
                    } else {
                        Yii::warning("订单取消: 实付已全部退过(或正在退),不再建退款 OrderID:{$order->id}");
                    }
                }
            }

            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();

            Yii::error("订单取消失败 ID:{$order->id}, Error:" . $e->getMessage());
            $this->addError('cancel', $e->getMessage());
            return false;
        }

        // 7. 取消相关事件【全部在 commit 之后触发】,各自独立 try/catch,监听器异常只记日志、不影响已提交的取消:
        //    ① EVENT_ORDER_CANCELED(原在事务内触发,写数据型监听器有事务边界风险);
        //    ② CommissionRevertEvent 大一统回滚(原在 commit 后但仍在 try 内,监听器抛错会去 rollBack 已提交事务并误返 false)。
        try {
            Yii::$app->trigger(Order::EVENT_ORDER_CANCELED, new OrderEvent(['order' => $order]));
        } catch (\Throwable $e) {
            Yii::error("[订单取消] 触发 EVENT_ORDER_CANCELED 异常 ID:{$order->id}: " . $e->getMessage());
        }
        // 分润统一回滚信号:整单取消 → 分润插件按此回滚,不必单独监听 canceled。
        try {
            Yii::$app->trigger(\app\events\CommissionRevertEvent::NAME, new \app\events\CommissionRevertEvent([
                'mall_id' => $order->mall_id,
                'order_id' => $order->id,
                'order_detail_id' => 0, // 整单
                'source' => 'cancel',
            ]));
        } catch (\Throwable $e) {
            Yii::error("[订单取消] 触发 CommissionRevertEvent 异常 ID:{$order->id}: " . $e->getMessage());
        }

        return true;
    }

    /**
     * [私有] 返还优惠券
     */
    private function revertCoupons(Order $order)
    {
        if ($order->user_coupon_id) {
            $uc = UserCoupon::findOne(['id' => $order->user_coupon_id, 'status' => UserCoupon::STATUS_USED]);
            // 这张券现在被别的有效订单用着就不退:修复前「自动选券」的老订单记了券号却没核销,
            // 取消它会把另一单真正用掉的券退回来
            if ($uc && Order::find()->where(['user_coupon_id' => (int)$uc->id, 'is_delete' => 0])
                    ->andWhere(['<>', 'id', (int)$order->id])->andWhere(['<>', 'status', Order::STATUS_CLOSED])->exists()) {
                Yii::warning("订单取消: 优惠券ID {$uc->id} 正被其他订单使用,不予返还。Order ID: {$order->id}");
                $uc = null;
            }
            if ($uc) {
                if ($uc->end_at > time()) {
                    $uc->status = UserCoupon::STATUS_UNUSE;
                    $uc->used_at = 0;
                    if (!$uc->save()) {
                        throw new \Exception('返还优惠券失败');
                    }
                } else {
                    Yii::warning("订单取消: 优惠券ID {$uc->id} 已过期，不予返还。Order ID: {$order->id}");
                }
            }
        }
    }

    /**
     * [私有] 返还积分
     */
    private function revertIntegrals(Order $order)
    {
        if ($order->is_use_integral && $order->use_integral > 0) {
            // 售后已经退掉的那几件(明细作废),确认打款时已按明细把它们抵扣的积分退了(RefundService::completeRefund),
            // 这里只退剩下的,否则那部分退两遍
            $returned = (int)OrderDetail::find()
                ->where(['order_id' => $order->id, 'is_delete' => 0, 'status' => OrderDetail::STATUS_INVALID])
                ->sum('use_integral');
            $amount = (int)$order->use_integral - $returned;
            if ($amount <= 0) {
                return;
            }
            $idempotencyKey = 'order_cancel_integral_' . $order->id;
            $content = "订单取消，返还抵扣积分，订单号：{$order->order_no}";
            $title = '订单取消返还';

            // 走核心积分网关:业务类型 order_deduct_refund(与下单 order_deduct 成对,便于报表对账)。
            // 注意:这是「回退一笔消耗」,方向是发放,不能用 revert()(那是按发放记录回收,方向相反)。
            (new \app\services\IntegralService())->grant(
                $order->user_id,
                $amount,
                'order_deduct_refund',
                [
                    'idempotencyKey' => $idempotencyKey,
                    'title'          => $title,
                    'content'        => $content,
                ]
            );
        }
    }

    /**
     * 用户确认收货
     * @param Order $order
     * @return bool
     */
    public function confirm(Order $order, $force = false)
    {
        // 线下核销 / 退房前禁止走基础管线确认收货。名单由各插件 Plugin::getOrderBehaviors() 的
        // block_confirm 声明（当前 yuda / hotel），核心不再硬编码任何插件名。
        //
        // 注：Registry 按「type 优先、plugin 兜底」双查，故不受历史上那个坑的影响
        //     收银台未声明 block_confirm，其 orderPaid() 主动调 confirm(force=true) 依旧放行。
        if (OrderBehaviorRegistry::blocksConfirm($order)) {
            $this->addError('plugin', '特殊业务订单不支持在基础订单管线中手动确认收货，请通过该业务专有流程操作！');
            return false;
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 0. 行锁 + 锁内重载:与 cancel / orderSettle 抢同一把行锁。非 force 时下面的 status 判断须基于锁内权威值,
            //    否则两个并发 confirm 同读 SHIPPED 会双双确认、双触发 EVENT_ORDER_CONFIRMED;或与 force-cancel 抢
            //    同一笔 SHIPPED 单造成终态打架。拿锁后状态已不是 SHIPPED 则非 force 直接放弃。
            if (!$this->lockOrderRow($order)) {
                $transaction->rollBack();
                $this->addError('order', '订单不存在');
                return false;
            }
            if ($order->status !== Order::STATUS_SHIPPED) {
                if (!$force) {
                    $this->addError('status', '订单不是待收货状态');
                    $transaction->rollBack();
                    return false;
                }
                $order->send_at = time();
                $order->delivery_status = Order::DELIVERY_STATUS_SHIPPED;
            }
            // 1. 更新订单的核心状态
            $order->status = Order::STATUS_COMPLETED;
            $order->confirm_at = time();
            $order->finish_at = time();
            // 2. 设置订单进入“待结算”状态
            $order->settlement_status = Order::SETTLEMENT_STATUS_PENDING;
            if (!$order->save()) {
                throw new \Exception($this->getErrorMsg($order));
            }
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            $this->addError('confirm', $e->getMessage());
            return false;
        }
        // 3. 确认收货事件【必须在 commit 之后触发】。CONFIRMED 上挂着会"真实落库"的监听器
        //    (integral 插件发门店底价补贴到 user.price、queue 插件入队),若在事务内触发、监听器独立事务已提交
        //    而本方法随后回滚,会出现"钱已打/已入队但订单未确认"的不一致。独立 try/catch,监听器异常不影响已确认。
        try {
            Yii::$app->trigger(Order::EVENT_ORDER_CONFIRMED, new OrderEvent(['order' => $order]));
        } catch (\Throwable $e) {
            Yii::error('[确认收货] 触发 EVENT_ORDER_CONFIRMED 异常: ' . $e->getMessage());
        }
        return true;
    }

    /**
     * 结算订单 (核心资金结算触发点)
     * @param Order $order 订单模型
     * @param bool $force 是否强制结算 (允许在 待发货/已发货 状态下强行结算)
     * @return bool
     */
    public function orderSettle(Order $order, $force = false): bool
    {
        $logPrefix = "[订单结算服务][Order:{$order->id}]";

        $targetSettlementStatus = null;
        $targetDetailStatus = null;
        $needForceComplete = false;

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 0. 行锁防并发(TOCTOU):与 cancel / confirm 抢同一把行锁,杜绝"两进程同读 PENDING 双双结算 →
            //    EVENT_ORDER_SETTLED 双触发 → 业绩翻倍/佣金双发",以及"cancel 覆盖正在结算的单"。
            if (!$this->lockOrderRow($order)) {
                $transaction->rollBack();
                $this->addError('order', '订单不存在');
                return false;
            }

            Yii::warning("{$logPrefix} 开始处理结算请求, force={$force}, status={$order->status}");

            // 1. 状态判定逻辑(锁内,杜绝 status / settlement_status 的 TOCTOU)
            if ($order->status == Order::STATUS_CLOSED) {
                if ($order->settlement_status == Order::SETTLEMENT_STATUS_INVALID) {
                    $transaction->rollBack();
                    $this->addError('settlement_status', '订单已是无需结算状态');
                    return false;
                }
                $targetSettlementStatus = Order::SETTLEMENT_STATUS_INVALID;
                $targetDetailStatus = OrderDetail::STATUS_INVALID;
            } elseif ($order->status == Order::STATUS_COMPLETED) {
                if ($order->settlement_status == Order::SETTLEMENT_STATUS_SETTLED) {
                    $transaction->rollBack();
                    $this->addError('settlement_status', '订单已是已结算状态');
                    return false;
                }
                $targetSettlementStatus = Order::SETTLEMENT_STATUS_SETTLED;
                $targetDetailStatus = OrderDetail::STATUS_VALID;
            } else {
                if ($force) {
                    $targetSettlementStatus = Order::SETTLEMENT_STATUS_SETTLED;
                    $targetDetailStatus = OrderDetail::STATUS_VALID;
                    $needForceComplete = true;
                    Yii::warning("{$logPrefix} 管理员触发强制结算，状态将流转为[已完成]");
                } else {
                    $transaction->rollBack();
                    $this->addError('status', "订单状态[{$order->status}]不满足自动结算条件，请尝试强制结算");
                    return false;
                }
            }

            // 2. 更新子订单详情状态
            // 只有未发生退款的正常子订单，才会被标记为“有效结算 (STATUS_VALID)”
            OrderDetail::updateAll(
                ['status' => $targetDetailStatus],
                [
                    'order_id' => $order->id,
                    'status' => OrderDetail::STATUS_NORMAL
                ]
            );

            // 3. 【核心新增：财务最终清算】
            // 仅当订单走向“已结算”状态时，才计算最终收益
            if ($targetSettlementStatus == Order::SETTLEMENT_STATUS_SETTLED) {
                $this->calculateFinalIncome($order);
            }

            // 4. 更新主订单状态
            if ($needForceComplete) {
                $order->status = Order::STATUS_COMPLETED;
                if (!$order->confirm_at) {
                    $order->confirm_at = time();
                }
            }
            $order->settlement_status = $targetSettlementStatus;
            $order->settled_at = time(); // 记录真正结算的时间

            if (!$order->save()) {
                // 记录真实校验错误:原来只抛通用文案,上层 OrderSettleTask 日志只剩"保存失败，发生未知错误"无从排查。
                $saveErrs = json_encode($order->getFirstErrors(), JSON_UNESCAPED_UNICODE);
                Yii::error("{$logPrefix} 主订单保存失败,校验错误: {$saveErrs}");
                throw new \Exception("主订单状态/财务数据保存失败: " . ($saveErrs === '[]' ? '(无字段校验错误,疑似 beforeSave 拦截 / DB 约束 / 字段类型问题)' : $saveErrs));
            }

            $transaction->commit();
            Yii::warning("{$logPrefix} 结算成功: SettleStatus={$targetSettlementStatus}");

        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error("{$logPrefix} 结算异常中断: " . $e->getMessage());
            $this->addError('exception', $e->getMessage());
            return false;
        }
        // 5. 触发外部钩子事件 (如分销返佣等)
        if ($targetSettlementStatus == Order::SETTLEMENT_STATUS_SETTLED) {
            try {
                Yii::warning("{$logPrefix} 准备触发 EVENT_ORDER_SETTLED, mch_id={$order->mch_id}, plugin={$order->plugin}");
                Yii::$app->trigger(Order::EVENT_ORDER_SETTLED, new OrderEvent(['order' => $order]));
                Yii::warning("{$logPrefix} EVENT_ORDER_SETTLED 触发完毕");
            } catch (\Throwable $e) {
                Yii::error("{$logPrefix} 触发 EVENT_ORDER_SETTLED 异常: " . $e->getMessage());
            }

            // settled 口径升级即时性：结算后检买家本人 + 上级链。与 order.paid 路径对称，
            // 让 buy_type=已结算 的等级条件在结算这一刻即时升，不再只依赖 5 分钟 dirty 兜底。
            try {
                Yii::$app->trigger(
                    \app\events\UserLevelCheckNeededEvent::NAME,
                    new \app\events\UserLevelCheckNeededEvent(['user_id' => (int)$order->user_id, 'trigger' => 'order_settled'])
                );
                \app\jobs\user\UserLevelUpgradeJob::dispatchUplineCheck((int)$order->user_id, 'order_settled_upline');
            } catch (\Throwable $e) {
                Yii::error("{$logPrefix} 触发结算后升级重检异常: " . $e->getMessage());
            }
        } elseif ($targetDetailStatus == OrderDetail::STATUS_INVALID) {
            // 关掉的单:只作废还没发的分润。已经发出去的(付款即发)扣不扣回、扣成负数与否由各业务插件自己决定
            // (它们收到取消 / 退款的回滚信号时处理,2026-09-28 定)—— 原来这里连已发的也直接标作废,
            // 钱没扣回、记录却成了「已失效」,插件之后再想扣回,revert() 看到已作废就跳过了
            CommissionLog::updateAll(['status' => CommissionLog::STATUS_INVALID],
                ['order_id' => $order->id, 'status' => CommissionLog::STATUS_PENDING]);
        }
        return true;
    }

    /**
     * [私有] 执行财务清算：结算时把订单的两个收入按最终口径写好
     *
     * 口径见 computeIncome()。调用前 orderSettle 已把「正常」明细标成「有效」,退掉的是「无效」。
     *
     * ⛔ 不要在这里标记明细的 is_mch_settled:它由 MchSettlementService::settleOneDetail() 在真正打款后设置。
     *    以前在这里提前标记,EVENT_ORDER_SETTLED 的监听器按 is_mch_settled=0 找不到记录,商户货款结算不出去。
     */
    private function calculateFinalIncome(Order $order)
    {
        [$mchIncome, $platformIncome] = $this->computeIncome($order);
        Yii::warning("[财务清算][Order:{$order->id}] mch_income:{$mchIncome} platform_income:{$platformIncome}");

        $order->mch_income = $mchIncome;
        $order->platform_income = $platformIncome; // 平台净利可能为负 (营销亏损)
        $order->is_express_settled = 1;
    }

    /**
     * 订单两个收入的唯一口径:按「没退掉的明细」汇总(结算、退款重算、存量回填共用)
     *
     *   商家单(mch_id > 0):商家 = Σ明细货款(mch_income_price,已扣平台服务费)
     *                        平台 = Σ平台服务费(concession_amount) − 平台补贴
     *                        运费:还有没退掉的明细就算,按 express_income_mch_id 归平台或商家
     *   自营单(mch_id = 0):商家 = 0 —— 只有 mch_id > 0 的订单才算商家收入
     *                        平台 = 见 selfOperatedIncome()
     *   平台补贴 = 支付随机减 + 平台券(商家券是商家自己让利,已经在明细货款里扣过)
     *
     * 「没退掉」= 状态不是「无效」。⛔ 不能只认「有效」:明细要到结算那一刻才从「正常」
     * 变「有效」,售后期内退掉一件时一条「有效」都没有 —— 原来的退款重算就是这么把
     * 整单两个收入都算成 0 的。
     *
     * 一件都没退掉时,补贴按整单金额扣,和原结算口径完全一致;退过的,只扣剩下那几件的份。
     *
     * @return string[] [商家收入, 平台收入]
     */
    public function computeIncome(Order $order): array
    {
        // ⛔ 必须带 is_delete=0:软删的明细算进来会多算货款(见 order-lifecycle-sim ⑥)
        $details = OrderDetail::find()->where(['order_id' => $order->id, 'is_delete' => 0])->all();
        $active = array_values(array_filter($details, function ($d) {
            return (int)$d->status !== OrderDetail::STATUS_INVALID;
        }));
        if (!$active) {
            return ['0.00', '0.00'];
        }

        $express = $this->money($order->express_price);
        if ((int)$order->mch_id === 0) {
            return ['0.00', $this->selfOperatedIncome($order, $details, $active, $express)];
        }

        $mchIncome = '0.00';
        $platformGross = '0.00';
        foreach ($active as $detail) {
            $mchIncome = bcadd($mchIncome, $this->money($detail->mch_income_price), 2);
            $platformGross = bcadd($platformGross, $this->money($detail->concession_amount), 2);
        }

        if (bccomp($express, '0', 2) > 0) {
            if ((int)$order->express_income_mch_id === 0) {
                $platformGross = bcadd($platformGross, $express, 2);
            } else {
                $mchIncome = bcadd($mchIncome, $express, 2);
            }
        }

        return [
            bccomp($mchIncome, '0', 2) < 0 ? '0.00' : $mchIncome, // 商家不能为负
            bcsub($platformGross, $this->platformSubsidy($order, $details, $active), 2),
        ];
    }

    /**
     * 自营单(mch_id = 0)的平台收入 = 实收 —— 一件没退就是实付;退过就是没退那几件的实付 + 运费。
     *
     * ⛔ 不能按明细货款汇总:自营单明细货款的含义换过几代 —— 最早记 0、积分商城单记 0、
     *    2026-04-14 前平台券先从货款里扣掉 —— 按货款算,老单结算时平台收入会塌成只剩运费
     *    (bonnie #83 实付 248 会结算成 10)。实付是唯一一直没变过的口径。
     *
     * ⛔ 插件后面要把多少钱结给门店 / 技师(卡券按结算底价、艾灸给技师),这里不管:
     *    这两列只给订单列表顶部的统计用,不承担分账口径。原来核心会问插件「你要结出去多少」
     *    再扣掉,于是同一批老单在装了插件和没装插件的环境算出两个数(bonnie 上卡券插件没装,
     *    7 张老单就整笔记成了平台的)。钱怎么分以结算、分润的流水为准,与这两列无关。
     */
    private function selfOperatedIncome(Order $order, array $details, array $active, string $express): string
    {
        if (count($active) === count($details)) {
            return bcadd($this->money($order->pay_price), '0', 2);
        }
        $cash = bccomp($express, '0', 2) > 0 ? $express : '0.00';
        foreach ($active as $detail) {
            $cash = bcadd($cash, $this->money($detail->pay_price), 2);
        }
        return $cash;
    }

    /** 要从平台收入里扣的平台补贴:支付随机减 + 平台券,退掉那几件分到的不扣(见 activeShare) */
    private function platformSubsidy(Order $order, array $details, array $active): string
    {
        return bcadd(
            $this->activeShare($this->money($order->platform_subsidy_random_minus), $details, $active, 'platform_subsidy_random_minus'),
            $this->activeShare($this->money($this->calcPlatformCouponSubsidy($order)), $details, $active, 'coupon_cut_price'),
            2
        );
    }

    /**
     * 整单的一项平台补贴里,要从平台收入里扣的那一份
     *
     * · 只算「没退掉的明细」:退掉那几件分到的补贴不再扣
     * · 明细上分摊过(明细合计 = 整单金额)按明细取,没分摊过的(老单 —— 支付随机减 2026-04-14 才开始
     *   分摊到明细)按明细金额占比折算
     * · ⛔ 下单时已经从明细货款里扣掉的,不能再扣一次:2026-04-14「优惠折扣字段语义重构」之前,
     *   平台券是先从明细货款里减掉的(货款 + 平台服务费 = 这件的实付);之后改成平台承担、货款不减,
     *   结算时才从平台收入里扣。老单按新口径扣,平台券就扣了两遍(自营 148 的单结算成 128)。
     *   这里按明细自己的数判断:货款 + 服务费 = 实付的,补贴早就不在里面了,跳过。
     * · 一件没退、全是新口径:就是整单金额,和原结算口径分毫不差
     */
    private function activeShare(string $orderAmount, array $all, array $active, string $field): string
    {
        if (bccomp($orderAmount, '0', 2) <= 0) {
            return '0.00';
        }
        $charged = array_values(array_filter($active, function ($d) {
            return !$this->subsidyAlreadyOut($d);
        }));
        if (count($charged) === count($all)) {
            return bcadd($orderAmount, '0', 2);
        }
        if (!$charged) {
            return '0.00';
        }
        $lineAll = '0.00';
        $baseAll = '0.00';
        foreach ($all as $detail) {
            $lineAll = bcadd($lineAll, $this->money($detail->$field), 2);
            $baseAll = bcadd($baseAll, $this->money($detail->total_price), 2);
        }
        $lineCharged = '0.00';
        $baseCharged = '0.00';
        foreach ($charged as $detail) {
            $lineCharged = bcadd($lineCharged, $this->money($detail->$field), 2);
            $baseCharged = bcadd($baseCharged, $this->money($detail->total_price), 2);
        }
        if (bccomp($lineAll, $orderAmount, 2) === 0) {
            return $lineCharged;
        }
        if (bccomp($baseAll, '0', 2) <= 0) {
            return '0.00';
        }
        return bcdiv(bcmul($orderAmount, $baseCharged, 6), $baseAll, 2);
    }

    /** 这件的补贴下单时已经从货款里扣掉了:货款 + 平台服务费 = 这件的实付(2026-04-14 前的口径) */
    private function subsidyAlreadyOut(OrderDetail $detail): bool
    {
        $gross = bcadd($this->money($detail->mch_income_price), $this->money($detail->concession_amount), 2);
        return bccomp($gross, $this->money($detail->pay_price), 2) === 0;
    }

    /** 金额字段转成 bc 函数能吃的字符串:null / 空串在 PHP 8 的 bc* 里会直接抛错 */
    private function money($value): string
    {
        return is_numeric($value) ? (string)$value : '0';
    }

    /**
     * 计算订单中由平台承担的优惠券金额
     * 仅当 UserCoupon.mch_id == 0 (平台券) 时返回 coupon_cut_price，否则返回 0
     */
    private function calcPlatformCouponSubsidy(Order $order): string
    {
        if ($order->user_coupon_id <= 0 || bccomp((string)$order->coupon_cut_price, '0.00', 2) <= 0) {
            return '0.00';
        }
        $userCoupon = UserCoupon::findOne($order->user_coupon_id);
        if (!$userCoupon || (int)$userCoupon->mch_id !== 0) {
            return '0.00';
        }
        return (string)$order->coupon_cut_price;
    }

    /**
     * 退款成功后重算订单财务字段
     *
     * 触发场景:退款落地(RefundSuccessDispatcher 三条路)把明细标成「无效」之后调用。
     * 口径与结算同一套,见 computeIncome()。结算前退、结算后退都适用。
     */
    public function recalculateAfterRefund(Order $order): void
    {
        $this->recalculateIncome($order, '退款回滚');
    }

    /**
     * 按 computeIncome() 重算订单两个收入并落库(退款回滚、存量回填共用)
     */
    public function recalculateIncome(Order $order, string $reason = '重算收入'): void
    {
        [$mchIncome, $platformIncome] = $this->computeIncome($order);
        Yii::warning("[{$reason}][Order:{$order->id}] mch_income:{$mchIncome} platform_income:{$platformIncome}");

        Order::updateAll([
            'mch_income' => $mchIncome,
            'platform_income' => $platformIncome,
        ], ['id' => $order->id]);
    }

    /**
     * 删除订单 (软删除)
     * @param Order $order
     * @return bool
     */
    public function delete(Order $order)
    {
        if (!in_array($order->status, [Order::STATUS_COMPLETED, Order::STATUS_CLOSED])) {
            $this->addError('status', '订单未结束，不可删除');
            return false;
        }
        $order->is_delete = 1;
        if (!$order->save()) {
            $this->addError('save', $this->getErrorMsg($order));
            return false;
        }
        return true;
    }
}