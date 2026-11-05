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

use app\models\Order;
use app\models\OrderDetail;
use Yii;
use yii\db\Expression;
use yii\db\Query;

/**
 * 「有效消费」口径:一张订单、一条明细算不算消费 / 业绩 / 购买件数 —— 全系统只在这里定义。
 *
 * 规则(按市场通行做法):
 *   · 金额按商品实付(明细 pay_price),运费不算。运费是物流成本不是成交,分佣、成长值普遍不计;
 *     明细 pay_price 已摊掉优惠券、积分抵扣,明细合计 + 运费 = 订单实付。
 *   · 先看订单是不是有效单,按 Order 的两个状态逐个对(白名单,定义以外的值一律不算):
 *       订单状态 status:待发货 20(自提叫待核销、同城叫待配送)/ 待收货 30 / 已完成 40 算;
 *         待付款 10 没收到钱,不算;已关闭 0 不算 —— 不管钱是原路退的还是线下退的、备注写的什么
 *         (后台关单的老路径不改支付状态、不发回滚信号,实测某商城 373 张关掉的单一直被当成消费)。
 *         与后台订单列表「成交金额」是同一组状态。
 *       支付状态 pay_status:已支付 1 / 部分退款 2 算(部分退款的单,没退的明细照算);未支付 0 / 全额退款 3 不算 ——
 *         状态是已完成也一样(实测有关掉的单又被发货、自动确认收货走到已完成已结算,支付状态还是全额退款)。
 *   · 退掉的明细不算:售后同意那一刻记进回滚台账(user_team_stat_revert_log),退款到账后明细置无效(明细状态 2),两道都认;
 *     明细状态正常 0 / 有效 1 算。
 *   · 已结算口径:有效单里,已完成 40 且已结算(settlement_status=1);待结算 0、无需结算 2 不算。
 *
 * 两类用法,规则一模一样:
 *   · 实时查询(升级条件的件数 / 金额 / 直推类条件)用 orders() + validDetails(),算的是此刻的真实情况;
 *   · 团队统计(实时累加、巡检重算、批量重建、改上级整队转移)用 accounted():
 *     同一口径,再按 outbox 里还没落地的事件修正成「统计里应当记着的」(事件全部落地后正好等于真实值)——
 *     巡检重算不能把还没记账的单先算进去,否则事件落地时再加一次;也不能把还没扣的退款先扣掉,否则落地时再扣一次。
 *
 * 以前这套判断散在六七处各写一份,运费、部分退款、已关闭三处写法不一,巡检和实时累加一来一回就改一次数。
 */
class EffectiveSpend
{
    /** 算数的支付状态:已支付、部分退款 */
    const PAID_STATUSES = [Order::PAY_STATUS_PAID, Order::PAY_STATUS_PARTIAL_REFUND];

    /** 算数的订单状态:待发货(待核销 / 待配送)、待收货、已完成 */
    const VALID_STATUSES = [Order::STATUS_TO_BE_SHIPPED, Order::STATUS_SHIPPED, Order::STATUS_COMPLETED];

    /** 算不算这张单:已付口径 = 有效单(支付状态、订单状态都在白名单里);已结算口径 = 有效单里已完成且已结算 */
    public static function orders(bool $settled, string $o = 'o'): array
    {
        return ['and', [$o . '.pay_status' => self::PAID_STATUSES], $settled
            ? [$o . '.status' => Order::STATUS_COMPLETED, $o . '.settlement_status' => Order::SETTLEMENT_STATUS_SETTLED]
            : [$o . '.status' => self::VALID_STATUSES]];
    }

    /** 算不算这条明细:没删、没作废、没被回滚(本条或整单) */
    public static function validDetails(string $o = 'o', string $od = 'od'): array
    {
        return ['and', [$od . '.is_delete' => 0], ['!=', $od . '.status', OrderDetail::STATUS_INVALID], self::notReverted($o, $od)];
    }

    /** 没进回滚台账(本条明细或整单),SQL 片段 */
    public static function notReverted(string $o = 'o', string $od = 'od'): string
    {
        $t = Yii::$app->db->tablePrefix . 'user_team_stat_revert_log';
        return "NOT EXISTS (SELECT 1 FROM `{$t}` rl WHERE rl.order_id = {$o}.id AND (rl.order_detail_id = 0 OR rl.order_detail_id = {$od}.id))";
    }

    /**
     * 统计里应当记着的消费明细(订单、明细两层一起判)—— 团队统计的实时累加、巡检重算、批量重建、改上级整队转移共用。
     *
     * 目标:等 outbox 里排队的事件全部落地后,统计正好等于真实值(orders() + validDetails())。所以
     *   记着的 = 真实值 − 排队的付款 / 结算将来要加的 + 排队的回滚将来要扣的:
     *   · 付款 / 结算事件还在排队的单:整单不算(落地时会加);
     *   · 回滚还在排队的明细(整单回滚则这张单没回滚过的明细):照算,不管订单现在什么状态、明细作废没有(落地时会扣);
     *   · 其余明细:按有效口径(订单付过款没关闭 / 已完成已结算,明细没作废没回滚)。
     * 老路径关单(已关闭、没回滚)因此不算;它若还挂着排队的明细回滚,只算那几条,落地一扣正好归零。
     * 没开 outbox 时事件都是当场记账的,就是有效口径。
     *
     * @param int|null   $landing   正在落地的付款 / 结算事件所属订单:它自己不按「还在排队」排除(落地时 outbox 里它还是待处理)
     * @param array|null $revertNow [订单 id, 明细 id(0 = 整单)]:正在处理的这条回滚按「还在排队」算(没开 outbox 时它不在队里)
     */
    public static function accounted(int $mallId, bool $settled, string $o = 'o', string $od = 'od', ?int $landing = null, ?array $revertNow = null): array
    {
        $queued = TeamStatOutbox::enabled($mallId);
        $valid = ['and', self::orders($settled, $o), ['!=', $od . '.status', OrderDetail::STATUS_INVALID]];
        $revertPending = ['or'];
        if ($queued) {
            $revertPending[] = ['in', [$o . '.id', $od . '.id'], self::pendingRevertDetails($mallId)];
            $revertPending[] = ['in', $o . '.id', self::pendingRevertOrders($mallId)];
        }
        if ($revertNow) {
            $revertPending[] = $revertNow[1] > 0 ? [$o . '.id' => (int)$revertNow[0], $od . '.id' => (int)$revertNow[1]] : [$o . '.id' => (int)$revertNow[0]];
        }
        $cond = ['and', [$od . '.is_delete' => 0], self::notReverted($o, $od)];
        if ($queued) {
            $notQueued = ['not in', $o . '.id', self::pendingOrders($mallId, $settled ? 'order_settled' : 'order_paid')];
            $cond[] = $landing ? ['or', [$o . '.id' => $landing], $notQueued] : $notQueued;
        }
        if (count($revertPending) > 1) {
            // 回滚落地时按那一刻的订单状态决定扣不扣(onCommissionRevert 的 wasPaid / 结算已记),这里用同一个判断
            $eligible = $settled ? [$o . '.settlement_status' => Order::SETTLEMENT_STATUS_SETTLED] : ['!=', $o . '.pay_status', Order::PAY_STATUS_UNPAID];
            $cond[] = ['or', $valid, ['and', $eligible, $revertPending]];
        } else {
            $cond[] = $valid;
        }
        return $cond;
    }

    /** 这张单有没有某类事件还在 outbox 排队(没开 outbox 恒为否:事件都是当场记账的) */
    public static function hasPending(int $mallId, string $type, int $orderId): bool
    {
        return TeamStatOutbox::enabled($mallId)
            && self::pending($mallId, $type)->andWhere(new Expression(self::payloadInt('order_id') . ' = :oid', [':oid' => $orderId]))->exists();
    }

    /** 某类订单事件还在排队的订单 id(给 NOT IN 用,滤掉取不到 order_id 的,否则 NOT IN 遇 NULL 整个不成立) */
    private static function pendingOrders(int $mallId, string $type): Query
    {
        return self::pending($mallId, $type)
            ->select(new Expression(self::payloadInt('order_id')))
            ->andWhere(new Expression("JSON_EXTRACT(ob.payload, '$.order_id') IS NOT NULL"));
    }

    /** 整单回滚还在排队的订单 id */
    private static function pendingRevertOrders(int $mallId): Query
    {
        return self::pending($mallId, 'commission_revert')
            ->select(new Expression(self::payloadInt('order_id')))
            ->andWhere(new Expression(self::payloadInt('order_detail_id') . ' = 0'));
    }

    /** 明细回滚还在排队的 (订单 id, 明细 id) */
    private static function pendingRevertDetails(int $mallId): Query
    {
        return self::pending($mallId, 'commission_revert')
            ->select([new Expression(self::payloadInt('order_id')), new Expression(self::payloadInt('order_detail_id'))])
            ->andWhere(new Expression(self::payloadInt('order_detail_id') . ' > 0'));
    }

    private static function pending(int $mallId, string $type): Query
    {
        return (new Query())->from(TeamStatOutbox::TABLE . ' ob')
            ->where(['ob.mall_id' => $mallId, 'ob.status' => TeamStatOutbox::STATUS_PENDING, 'ob.type' => $type]);
    }

    private static function payloadInt(string $key): string
    {
        return "CAST(JSON_UNQUOTE(JSON_EXTRACT(ob.payload, '$.{$key}')) AS UNSIGNED)";
    }
}
