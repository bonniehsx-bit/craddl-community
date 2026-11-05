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

namespace app\plugins\mch\services;

use app\models\Order;
use app\models\OrderDetail;
use app\models\User;
use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchFirstOrderDiscountLog;
use Yii;

/**
 * 商户首单立减:名额和「每人一次」的资格都在下单那一刻占住
 *
 * 原来是下单时只判断、付款后才扣名额(FirstOrderDiscountListener),中间什么都不占:
 *   · 名额剩 1,两个人都下单成功,都付款 —— 后付的那个扣名额失败,只记一条日志,优惠照给;
 *   · 同一个用户趁还没付款,连开好几单,每单都带首单立减,再逐个付款;
 *   · 扣失败的单退款时,名额照样 +1,凭空多出名额。
 *
 * 现在:
 *   下单(核心 ConsumeResourcesStage,在建单事务里)→ reserve():
 *     锁用户行 → 确认这个人没有在用的首单 → 名额按「桶」条件扣减 → 写日志。任何一步不过,整单回滚
 *   未付款取消(OrderSettlementService::cancel)→ release():删日志、名额还回去
 *   已付款的退款 → 原逻辑不变(RefundSuccessDispatcher 每退一条明细还 1 桶,全退完删日志)
 *   付款 → FirstOrderDiscountListener 看到日志已在就跳过,不再二次扣
 *
 * 日志行就是占位凭证:有它 = 这单占了名额;删它的那一下(影响行数 1)才还名额,重复取消不会多还。
 *
 * 名额单位是「桶」= 订单明细行数,和付款扣减、退款归还一直以来的口径一致。
 */
class FirstOrderQuotaService
{
    /**
     * 下单时占名额。订单已落库、明细已写好(在 ProcessOrderDetailsStage 之后调)。
     *
     * @throws \Exception 名额抢光 / 这个人已有在用的首单 —— 让整个建单事务回滚
     */
    public static function reserve(Order $order): void
    {
        if (!self::usesDiscount($order)) {
            return;
        }

        // 1. 锁用户行:同一个人的两次下单在这里排队。锁要等前一单的事务提交才放
        User::find()->where(['id' => (int)$order->user_id])->forUpdate()->one();

        // ⛔ 拿到锁之后查「有没有在用的首单」必须另开连接:
        //   建单事务开头就读过很多表,可重复读下这个事务里的普通查询看的是那一刻的快照,
        //   前一单哪怕已经提交,这里照样查不到它的日志 —— 两单都放行(并发测试实测过)。
        //   也不能改成对日志表 FOR UPDATE:这个人没有日志时锁的是间隙,不同用户同时下单会互相死锁。
        //   另开的连接不在这个事务里,读到的就是已提交的最新数据;锁保证前一单此刻一定已经提交了。
        //   当前连接也要查一遍:同一个事务里先写的日志(还没提交)只有它自己看得见。
        $mallId = (int)$order->mall_id;
        $userId = (int)$order->user_id;
        if (self::hasActiveUse($mallId, $userId) || self::hasActiveUseCommitted($mallId, $userId)) {
            throw new \Exception('首单优惠每人只能用一次,你已有一笔使用了首单优惠的订单');
        }

        // 2. 条件扣名额:判断和扣减是同一条 SQL,不够就扣不动
        $barrels = self::barrelsOf($order);
        if ($barrels > 0) {
            $n = Mch::updateAllCounters(
                ['first_order_discount_total_stock' => -$barrels],
                ['and', ['id' => (int)$order->mch_id], ['>=', 'first_order_discount_total_stock', $barrels]]
            );
            if ($n < 1) {
                throw new \Exception('首单优惠名额已被抢完,请重新下单');
            }
        }

        // 3. 占位凭证
        $log = new MchFirstOrderDiscountLog();
        $log->mall_id = (int)$order->mall_id;
        $log->mch_id = (int)$order->mch_id;
        $log->user_id = (int)$order->user_id;
        $log->order_id = (int)$order->id;
        $log->discount_amount = $order->first_order_discount_price;
        if (!$log->save()) {
            throw new \Exception('首单优惠记录失败: ' . json_encode($log->getFirstErrors(), JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 没付款就取消了:名额还回去、资格放出来。
     *
     * 只处理未付款的单 —— 已付款的取消会走退款,由 RefundSuccessDispatcher 按明细归还,这里再还就是两遍。
     * 删日志成功的那一次才还名额:重复取消、老订单(下单时还没这套、没有日志)都不会多还。
     */
    public static function release(Order $order): void
    {
        if ($order->isPaid() || (int)$order->mch_id <= 0) {
            return;
        }
        $deleted = MchFirstOrderDiscountLog::deleteAll(['order_id' => (int)$order->id, 'user_id' => (int)$order->user_id]);
        if ($deleted < 1) {
            return;
        }
        $barrels = self::barrelsOf($order);
        if ($barrels > 0) {
            Mch::updateAllCounters(['first_order_discount_total_stock' => $barrels], ['id' => (int)$order->mch_id]);
        }
        Yii::warning("[首单优惠] 订单 {$order->id} 未付款取消,归还名额 {$barrels} 桶");
    }

    /** 这单下单时有没有占过名额(付款监听器据此判断要不要走老逻辑) */
    public static function reservedAtCreation(Order $order): bool
    {
        return MchFirstOrderDiscountLog::find()->where(['order_id' => (int)$order->id])->exists();
    }

    /**
     * 这个人有没有在用的首单:下了单还没取消、或付过款没退完的,日志都还在
     */
    public static function hasActiveUse(int $mallId, int $userId): bool
    {
        return MchFirstOrderDiscountLog::find()
            ->where(['mall_id' => $mallId, 'user_id' => $userId, 'is_delete' => 0])
            ->exists();
    }

    /** 同 hasActiveUse,但用一条不在当前事务里的新连接查 —— 见 reserve() 里的说明 */
    private static function hasActiveUseCommitted(int $mallId, int $userId): bool
    {
        $fresh = clone Yii::$app->db;   // Connection::__clone 会丢掉原来的 PDO,第一次查询时新建连接
        try {
            return MchFirstOrderDiscountLog::find()
                ->where(['mall_id' => $mallId, 'user_id' => $userId, 'is_delete' => 0])
                ->exists($fresh);
        } finally {
            $fresh->close();
        }
    }

    private static function usesDiscount(Order $order): bool
    {
        return (int)$order->mch_id > 0
            && bccomp((string)($order->first_order_discount_price ?? '0'), '0', 2) > 0;
    }

    /** 名额按「桶」算 = 订单明细行数(付款扣减、退款归还一直是这个口径) */
    private static function barrelsOf(Order $order): int
    {
        return (int)OrderDetail::find()->where(['order_id' => (int)$order->id, 'is_delete' => 0])->count();
    }
}
