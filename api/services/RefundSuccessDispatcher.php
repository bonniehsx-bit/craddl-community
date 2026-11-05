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

use app\models\CommonPayOrder;
use app\models\CommonPayRefund;
use Yii;

/**
 * 退款成功统一分派器
 *
 * 任何触发 common_pay_refund.status → SUCCESS 的路径，都必须调用 dispatch() 来落地业务状态：
 *   · cron RefundTask（主动调网关后的同步回调 / 查单分支）
 *   · notify/RefundController（微信退款异步回调）
 *   · notify/FastController（快捷支付退款异步回调）
 *
 * 调用方在把 status 写成 SUCCESS 之后（或代替 notify 的 save 动作之前）执行 dispatch()。
 * 通过"调用方先判断 status != SUCCESS 才进入"达到去重；本 Service 本身不做锁。
 */
class RefundSuccessDispatcher
{
    /**
     * 按 source_type 分派，未识别 source 走通用关单兜底
     *
     * 幂等锁：business_synced_at 非 0 表示业务已闭环，直接跳过避免重复执行（防止佣金扣回等不幂等操作被重放）。
     * 分派成功后设置 business_synced_at = time()；若抛异常则不设置，由 cron 补做扫描可再次尝试。
     */
    public static function dispatch(CommonPayRefund $task)
    {
        // 幂等锁：业务已闭环则跳过
        if (!empty($task->business_synced_at)) {
            \Yii::warning("[RefundSuccessDispatcher] 业务已闭环，跳过 TaskID:{$task->id}");
            return;
        }

        if ($task->source_type == CommonPayRefund::SOURCE_AFTER_SALES) {
            self::handleAfterSales($task);
        } elseif ($task->source_type == CommonPayRefund::SOURCE_CANCEL_ORDER) {
            self::handleCancelOrder($task);
        } elseif ($task->source_type == CommonPayRefund::SOURCE_PAID_AFTER_CANCEL) {
            // 订单取消后才到的钱,原路退回就闭环了。订单早已关闭、从没按已付处理过(没发货、没记业绩、没分佣),
            // 不能走下面的兜底:它会把明细标成已退、订单改成全额退款,再发分润回滚信号 —— 团队业绩会扣掉一笔从没加过的钱
            \Yii::warning("[已取消后收到支付] 已原路退款 TaskID:{$task->id} PayOrderID:{$task->common_pay_order_id}");
        } else {
            // 兜底：插件直写流水的场景（如 yuda_cancel）
            self::handleGeneric($task);
        }

        // [资金口径] 把这一单累计退回的钱回写到 order.refund_price
        //
        // ⛔ 这个字段过去从来没被写过 —— 唯一的赋值是下单时初始化 '0.00'
        //    (InitBasicDataStage),退款流程只改 pay_status/status/refund_status。
        //    实测:测试服 49 个有成功退款流水的订单,refund_price 全是 0.00。
        //    后果是首页「今日销售额」按 `pay_price - IFNULL(refund_price,0)` 算,
        //    减数恒为 0,等于没减。全额退款靠 pay_status=3 排除掉了侥幸没错,
        //    但插件直写流水的路径(如 yuda_cancel)退了钱却不改 pay_status,
        //    实测 3 单钱已退还在按全额计销售额。
        //
        // ⚠ 必须放在分支之后、business_synced_at 之前,且**绝不能抛异常** ——
        //    一旦抛出,business_synced_at 不会被置位,cron 会重跑整个 dispatch,
        //    而佣金扣回是不幂等的,会被重复执行。所以内部自己吞异常。
        self::syncOrderRefundPrice($task);

        // 标记业务闭环完成（所有分支抛异常时这里不会执行，留给 cron 补做）
        $task->business_synced_at = time();
        $task->save(false, ['business_synced_at']);
    }


    /**
     * 把一批分佣统一交给核心 CommissionService::revert() 追回并作废。
     *
     * 这里原本是售后、取消各手写一遍「查 status=SETTLED → 自己 sub → 最后无差别 updateAll(INVALID)」,
     * 三处毛病叠在一起:
     *
     *  ① **只认 price / balance**。落到积分或扩展钱包的那批一分不追,却照样被标 INVALID。
     *     这个每次退款都会漏,与并发无关 —— 库里 integral 与 mch.funds 合计 371 笔已结算。
     *  ② 扣回失败时只往内存里的 `$log->remark` 拼一句(**从来没 save 过**,紧跟着的 updateAll
     *     也不带 remark 列),然后照样置 INVALID,那笔钱再没有重试的机会。
     *  ③ 查与置之间没有原子抢占,而 dispatcher / cron / 两个 notify 控制器**整条链零事务**。
     *     实测 8 个进程并发对同一张售后单做闭环,一笔 ¥30 被扣回 8 次。
     *
     * revert() 一次把三件事都解决:条件 UPDATE 原子抢占 (PENDING|SETTLED) → INVALID、
     * 按发放时的 payout_type 回到**原账户**(含积分/外部分账/任意扩展钱包)、
     * 强制扣款(钱已经花掉也追得回,可扣成负数)。plaza 早就迁过去了,这里跟上。
     *
     * ⚠️ 单条失败**不再**强行置 INVALID:保持原状,才能被 cron 的孤儿扫描或人工重试捡起来。
     */
    public static function revertCommissions(array $condition, string $tag): void
    {
        $ids = \app\models\CommissionLog::find()
            ->select('id')
            ->where($condition)
            ->andWhere(['not', ['status' => \app\models\CommissionLog::STATUS_INVALID]])
            ->column();

        if (!$ids) {
            return;
        }

        $service = new \app\services\CommissionService();
        foreach ($ids as $id) {
            try {
                $service->revert((int)$id);
            } catch (\Throwable $e) {
                Yii::error("[{$tag}] 分佣追回失败,该条保持原状待重试 CommissionLog:{$id} Error:{$e->getMessage()}");
            }
        }
    }

    /**
     * 按支付流水重算并回写 order.refund_price（这一单累计已退回多少钱）。
     *
     * 口径取 common_pay_refund 而不是 order_refund:
     *   · common_pay_refund 是**钱真的退回去了**的记录(status=SUCCESS),
     *     order_refund.refund_price 只是用户**申请**的金额,可能被驳回、被改额。
     *   · 三条退款路径(售后 / 取消订单 / 插件直写)都会落 common_pay_refund,
     *     只有它能覆盖全部来源;order_refund 只有售后单才有。
     *
     * 全量重算而不是累加:重算天然幂等,dispatch 被重放也不会把钱算两遍。
     */
    private static function syncOrderRefundPrice(CommonPayRefund $task): void
    {
        try {
            $payOrder = CommonPayOrder::findOne($task->common_pay_order_id);
            // order_id 为空的是插件/充值类支付单,不挂核心订单,跳过
            if (!$payOrder || empty($payOrder->order_id)) {
                return;
            }

            $order = \app\models\Order::findOne($payOrder->order_id);
            if (!$order) {
                return;
            }

            $sum = CommonPayRefund::find()->alias('r')
                ->innerJoin(['po' => CommonPayOrder::tableName()], 'po.id = r.common_pay_order_id')
                ->where([
                    'po.order_id'  => $order->id,
                    'po.is_delete' => 0,
                    'r.is_delete'  => 0,
                    'r.status'     => CommonPayRefund::STATUS_SUCCESS,
                ])
                ->sum('r.refund_price');

            $refunded = number_format((float) ($sum ?? 0), 2, '.', '');

            // 已经是这个值就不写了,省一次 UPDATE
            if (bccomp((string) ($order->refund_price ?? '0'), $refunded, 2) === 0) {
                return;
            }

            // 退款额超过支付额说明数据有问题,照实写入 + 告警,不在这里悄悄抹平;
            // 报表侧用 GREATEST(...,0) 兜底,不让它算出负营业额。
            if (bccomp($refunded, (string) $order->pay_price, 2) > 0) {
                Yii::error("[退款回写] 退款额超过支付额 OrderID:{$order->id} 支付:{$order->pay_price} 已退:{$refunded}");
            }

            $order->refund_price = $refunded;
            $order->save(false, ['refund_price']);
        } catch (\Throwable $e) {
            // 这里失败不能影响退款闭环 —— 金额回写只是统计口径,
            // 抛出去会导致 cron 重放 dispatch,把佣金扣回再执行一遍。
            Yii::error("[退款回写] 失败 TaskID:{$task->id}: " . $e->getMessage());
        }
    }

    /**
     * 通用退款成功回调：当支付流水已全额退回，把订单关闭 + 明细置退款完成
     *
     * 只做订单状态闭环，不碰具体业务（插件自己处理自己的业务数据，订单状态由平台收敛）。
     * 部分退款时不动订单状态（让业务层自行决定是否再退）。
     */
    public static function handleGeneric(CommonPayRefund $task)
    {
        $payOrder = CommonPayOrder::findOne($task->common_pay_order_id);
        if (!$payOrder || !$payOrder->order_id) {
            Yii::warning("[通用退款成功] 找不到对应订单 TaskID:{$task->id}");
            return;
        }

        $order = \app\models\Order::findOne($payOrder->order_id);
        if (!$order) {
            Yii::warning("[通用退款成功] 订单已删除 TaskID:{$task->id} OrderID:{$payOrder->order_id}");
            return;
        }

        // 只有支付流水全额退完才关单，部分退不动主订单状态
        $remaining = bcsub((string)$payOrder->pay_price, (string)$payOrder->getRefundedAmount(), 2);
        if (bccomp($remaining, '0.01', 2) >= 0) {
            Yii::warning("[通用退款成功] 部分退款不关单 TaskID:{$task->id} OrderID:{$order->id} 剩余可退:{$remaining}");
            return;
        }

        // 所有明细标"已退款完成"+"无效"
        \app\models\OrderDetail::updateAll(
            [
                'refund_status' => \app\models\OrderDetail::REFUND_STATUS_COMPLETE,
                'status' => \app\models\OrderDetail::STATUS_INVALID,
            ],
            ['order_id' => $order->id, 'is_delete' => 0]
        );


        // [财务回滚] 重算 mch_income / platform_income。
        // 与 handleAfterSales 同一条约定:必须在 updateOrderStatusAfterAction **之前**调,
        // 它依赖 OrderDetail.status 已被改成 INVALID(上面刚改完)。
        //
        // 实测三条路的差异就出在这一句上:only after_sales 调了它,所以 after_sales 的订单
        // mch_income 全是 0(正确);另两条路不调,订单全额退完了 mch_income 还挂着下单时
        // 的预估值 —— 商家货款报表(OrderListForm 的 SUM(o.mch_income))因此虚高。
        try {
            (new \app\services\order\actions\OrderSettlementService())->recalculateAfterRefund($order);
        } catch (\Throwable $e) {
            Yii::error("[退款财务回滚] OrderID:{$order->id} 失败: " . $e->getMessage());
        }

        // 复用标准关单器（它会按 OrderDetail.refund_status 决定主订单状态流转）
        (new RefundService())->updateOrderStatusAfterAction($order);

        // 分润大一统回滚:插件自写退款单(yuda_cancel / lucky_fail 等)走本兜底关单,必须同样发统一回滚信号——
        // 否则若该单曾结算返过佣金/分销/区域/经销(如 yuda 核销结算后被客服强制解散退款),退款后无法冲回(资损)。
        // 整单回滚 order_detail_id=0;下游监听器全幂等(佣金 revert_log 锚点 / 积分只追 GRANTED / 业绩有 wasSettled 门),
        // 对"从未结算返佣"的业态收到信号也是 no-op,安全。
        try {
            Yii::$app->trigger(\app\events\CommissionRevertEvent::NAME, new \app\events\CommissionRevertEvent([
                'mall_id' => $order->mall_id,
                'order_id' => $order->id,
                'order_detail_id' => 0,
                'source' => 'refund',
            ]));
        } catch (\Throwable $e) {
            Yii::error("[通用退款成功] 触发 CommissionRevertEvent 异常 TaskID:{$task->id} OrderID:{$order->id}: " . $e->getMessage());
        }

        Yii::warning("[通用退款成功] 订单已关闭 TaskID:{$task->id} OrderID:{$order->id} source_type:{$task->source_type}");
    }

    /**
     * 处理售后退款成功回调
     */
    public static function handleAfterSales(CommonPayRefund $task)
    {
        /** @var \app\models\OrderRefund $refund */
        $refund = \app\models\OrderRefund::findOne($task->source_id);
        if (!$refund) {
            Yii::warning("关联的售后单不存在 ID:{$task->source_id}");
            return;
        }

        // 更新售后单
        $refund->is_refund = 1;
        $refund->status = \app\models\OrderRefund::STATUS_COMPLETED;
        $refund->refund_time = date('Y-m-d H:i:s');
        $refund->save();

        // 更新子订单 -> 售后已完成(这件在商家确认打款时就已作废)
        $orderDetail = $refund->orderDetail;
        if ($orderDetail) {
            $voidedAtConfirm = (int)$orderDetail->status === \app\models\OrderDetail::STATUS_INVALID;
            $orderDetail->refund_status = \app\models\OrderDetail::REFUND_STATUS_COMPLETE;
            $orderDetail->status = \app\models\OrderDetail::STATUS_INVALID;
            $orderDetail->save();

            // 分润跟着「这件作废」走,不看钱到没到:商家确认打款时就广播 order.commission.revert、平台统一追回
            // (RefundService::completeRefund → voidDetail,2026-09-28 定)。这里不再发一次。
            if (!$voidedAtConfirm) {
                // 确认打款在这版上线之前、钱在上线之后才到的在途售后单:当时这件还没作废,在这里补做一次
                RefundService::voidDetail($refund);
            } else {
                // [资金安全] 确认打款之后才冒出来的这件的分润(比如付款后的排队任务晚到)也追回;已作废的跳过
                self::revertCommissions(['order_detail_id' => $orderDetail->id, 'is_delete' => 0], '退款扣回');
            }

            // [修复] 如果退款的是最后一个有效子项，重置运费结算标记（防止运费重复结算）
            $order = $refund->order;
            if ($order && $order->is_express_settled == 1) {
                $activeCount = \app\models\OrderDetail::find()
                    ->where(['order_id' => $order->id, 'is_delete' => 0])
                    ->andWhere(['!=', 'status', \app\models\OrderDetail::STATUS_INVALID])
                    ->count();
                if ($activeCount == 0) {
                    $order->is_express_settled = 0;
                    $order->save(false, ['is_express_settled']);
                    Yii::warning("[退款] 所有子项已退完，重置运费结算标记 OrderID:{$order->id}");
                }
            }

            // [首单立减回滚] 该 detail 用了首单立减，需要释放商家库存名额
            // listener 按 detail 总数扣库存 (FirstOrderDiscountListener:78-85)，所以每退一条释放 1 单
            if ($order
                && $order->mch_id > 0
                && bccomp((string) ($orderDetail->first_order_discount_price ?? '0'), '0.00', 2) > 0) {
                try {
                    \app\plugins\mch\models\Mch::updateAllCounters(
                        ['first_order_discount_total_stock' => 1],
                        ['id' => $order->mch_id]
                    );

                    // 如果整单全部退完，把首单立减日志一并删除（释放该用户的"首单"资格记录）
                    $remainingActiveCount = (int) \app\models\OrderDetail::find()
                        ->where(['order_id' => $order->id, 'is_delete' => 0])
                        ->andWhere(['!=', 'status', \app\models\OrderDetail::STATUS_INVALID])
                        ->count();
                    if ($remainingActiveCount === 0) {
                        \app\plugins\mch\models\MchFirstOrderDiscountLog::deleteAll([
                            'order_id' => $order->id,
                            'user_id' => $order->user_id,
                        ]);
                        Yii::warning("[首单立减回滚] OrderID:{$order->id} 整单退完，删除日志 + 释放库存");
                    } else {
                        Yii::warning("[首单立减回滚] OrderID:{$order->id} 部分退款，仅释放 1 单库存");
                    }
                } catch (\Exception $e) {
                    Yii::error("[首单立减回滚] OrderID:{$order->id} 失败: " . $e->getMessage());
                }
            }
        }

        // [财务回滚] 重算订单的 mch_income / platform_income，确保平台补贴成本和商家货款随退款同步反向
        // 必须在 updateOrderStatusAfterAction 前调用，因为它依赖 OrderDetail.status 已被改成 INVALID
        if ($refund->order) {
            try {
                (new \app\services\order\actions\OrderSettlementService())->recalculateAfterRefund($refund->order);
            } catch (\Exception $e) {
                Yii::error("[退款财务回滚] OrderID:{$refund->order_id} 失败: " . $e->getMessage());
            }
        }

        // 同步主订单
        if ($refund->order) {
            (new RefundService())->updateOrderStatusAfterAction($refund->order);
        }

        // 触发通过事件
        Yii::$app->trigger(\app\events\RefundEvent::EVENT_COMPLETED, new \app\events\RefundEvent(['refund' => $refund]));
    }

    /**
     * 处理取消订单退款成功回调
     */
    public static function handleCancelOrder(CommonPayRefund $task)
    {
        $order = \app\models\Order::findOne($task->source_id);
        if (!$order) return;

        $order->pay_status = \app\models\Order::PAY_STATUS_REFUNDED;
        $order->save();

        // 明细落地 + 订单级标记收敛。
        //
        // 这条路(OrderSettlementService::cancel → SOURCE_CANCEL_ORDER)原来**完全不碰
        // order_detail** —— cancel() 不碰,本函数也不碰。于是钱全退了,明细却还是
        // refund_status=0 / status=NORMAL,数据上完全看不出这单退过款。
        // 实测两台服务器:cancel_order 的订单明细 100% 停在非 INVALID
        // (测试服 20 单 / bonnie 9 单,无一例外)。
        //
        // 危害不止是难看:全仓有 8 处拿 `status != STATUS_INVALID` 判「这笔算不算数」——
        // TeamStatUpdater(团队业绩)、UserLevelUpgradeJob(用户升级)、
        // syk 的业绩/交易/区域、IntegralListener(积分)、pb 业绩监听。
        // 对它们而言这些已全额退款的订单从来没退过款。
        //
        // ⛔ 顺序不能反:updateOrderStatusAfterAction 是**数 refund_status=COMPLETE
        //    的明细条数**来判断整单退没退完的。先不标明细就调它,分子恒为 0 →
        //    isFullyRefunded=false → 落进 processingCount 分支,把 refund_status
        //    写成「售后中」,比不改还糟。必须先标明细、再调关单器,
        //    与 handleGeneric 同序。
        $payOrder = CommonPayOrder::findOne($task->common_pay_order_id);
        if ($payOrder) {
            // 只有支付流水全额退完才落地,部分退款不能把整单明细都标掉
            $remaining = bcsub((string) $payOrder->pay_price, (string) $payOrder->getRefundedAmount(), 2);
            if (bccomp($remaining, '0.01', 2) < 0) {
                \app\models\OrderDetail::updateAll(
                    [
                        'refund_status' => \app\models\OrderDetail::REFUND_STATUS_COMPLETE,
                        'status' => \app\models\OrderDetail::STATUS_INVALID,
                    ],
                    ['order_id' => $order->id, 'is_delete' => 0]
                );
            // [财务回滚] 重算 mch_income / platform_income。
            // 与 handleAfterSales 同一条约定:必须在 updateOrderStatusAfterAction **之前**调,
            // 它依赖 OrderDetail.status 已被改成 INVALID(上面刚改完)。
            //
            // 实测三条路的差异就出在这一句上:only after_sales 调了它,所以 after_sales 的订单
            // mch_income 全是 0(正确);另两条路不调,订单全额退完了 mch_income 还挂着下单时
            // 的预估值 —— 商家货款报表(OrderListForm 的 SUM(o.mch_income))因此虚高。
            try {
                (new \app\services\order\actions\OrderSettlementService())->recalculateAfterRefund($order);
            } catch (\Throwable $e) {
                Yii::error("[退款财务回滚] OrderID:{$order->id} 失败: " . $e->getMessage());
            }

                // 复用标准关单器:它按明细的 refund_status 收敛 order 的
                // status / pay_status / refund_status,三者口径由它一处决定
                (new RefundService())->updateOrderStatusAfterAction($order);
            } else {
                Yii::warning("[取消退款] 部分退款不标明细 TaskID:{$task->id} OrderID:{$order->id} 剩余可退:{$remaining}");
            }
        } else {
            Yii::warning("[取消退款] 找不到支付流水,跳过明细落地 TaskID:{$task->id} OrderID:{$order->id}");
        }

        // [资金安全] 同售后:整单的分佣一律交给核心 revert() 追回并作废 —— 见 revertCommissions()
        // OrderSettlementService 在取消时只标记了 INVALID，钱要在这里追。
        self::revertCommissions(['order_id' => $order->id, 'is_delete' => 0], '取消扣回');

        // 重置运费结算标记
        if ($order->is_express_settled == 1) {
            $order->is_express_settled = 0;
            $order->save(false, ['is_express_settled']);
        }

        Yii::warning("[取消退款] 订单取消扣回处理完毕 OrderID:{$order->id}");
    }
}
