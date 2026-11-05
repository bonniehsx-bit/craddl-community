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

namespace app\plugins\marketing\services;

use app\models\Order;
use app\models\User;
use app\plugins\marketing\models\MarketingCampaign;
use app\plugins\marketing\models\MarketingGrantLog;
use app\plugins\marketing\models\MarketingTrigger;

/**
 * 按「第几笔订单」判定的奖励:新客 N 天复购奖、邀请首单有礼
 *
 * 首单/第二单都按有效订单(已支付/部分退款、未关闭、有未失效且未售后的明细)按付款时间排序,
 * 且只看活动配置的「计入的订单类型」;发放在该笔订单**结算**时,回收在统一回滚信号时重算。
 */
class OrderRewardService
{
    // ================= 发放 =================

    public static function onOrderSettled(Order $order): void
    {
        $mallId = (int)$order->mall_id;
        $userId = (int)$order->user_id;
        if ($mallId <= 0 || $userId <= 0) {
            return;
        }
        // 先看有没有进行中的活动,没有就不查订单(每笔结算都会走到这里)
        $rebuyCampaigns = CumulativeService::campaigns($mallId, MarketingTrigger::EVENT_NEW_REBUY);
        $inviteCampaigns = CumulativeService::campaigns($mallId, MarketingTrigger::EVENT_INVITE);
        if (!$rebuyCampaigns && !$inviteCampaigns) {
            return;
        }
        foreach ($rebuyCampaigns as $item) {
            self::grantRebuy($order, $item['campaign'], self::normalizeRebuy($item['condition']));
        }
        foreach ($inviteCampaigns as $item) {
            self::grantInvite($order, $item['campaign'], self::normalizeInvite($item['condition']));
        }
    }

    /**
     * 新客复购:结算的这单正是第 2 笔有效订单,且距第 1 笔付款不超过 N 天。
     * 活动时段按第 1 笔付款时间判断(「活动期内成为新客」)。
     */
    private static function grantRebuy(Order $order, MarketingCampaign $c, array $cond): void
    {
        if (!CumulativeService::orderTypeMatches($cond['order_types'], $order)) {
            return;
        }
        $orders = self::firstValidOrders((int)$order->mall_id, (int)$order->user_id, 2, $cond['order_types']);
        if (count($orders) < 2 || $orders[1]['id'] !== (int)$order->id) {
            return;
        }
        $firstPayAt = $orders[0]['pay_at'];
        if ($orders[1]['pay_at'] - $firstPayAt > $cond['days'] * 86400 || !$c->isGrantable($firstPayAt)) {
            return;
        }
        if (!AudienceService::matchUser((int)$c->mall_id, (int)$order->user_id, $c->getAudience())) {
            return;
        }
        // 锁内复核:期间若首单被退款,本单就不再是第二单
        $guard = function () use ($order, $cond) {
            $now = self::firstValidOrders((int)$order->mall_id, (int)$order->user_id, 2, $cond['order_types']);
            return count($now) >= 2 && $now[1]['id'] === (int)$order->id
                && $now[1]['pay_at'] - $now[0]['pay_at'] <= $cond['days'] * 86400;
        };
        GrantService::grant((int)$c->id, (int)$order->user_id, MarketingTrigger::EVENT_NEW_REBUY, 'rebuy', 0, $firstPayAt, $guard);
    }

    /**
     * 邀请首单:结算的这单正是被邀请人第 1 笔有效订单 → 奖励直属上级(档位1),可选奖励被邀请人(档位2)。
     * 人群定向对邀请人生效;默认只认下单前已绑定的邀请关系(绑定时间未知的历史数据放行)。
     */
    private static function grantInvite(Order $order, MarketingCampaign $c, array $cond): void
    {
        if (!CumulativeService::orderTypeMatches($cond['order_types'], $order)) {
            return;
        }
        $mallId = (int)$order->mall_id;
        $first = self::firstValidOrders($mallId, (int)$order->user_id, 1, $cond['order_types']);
        if (!$first || $first[0]['id'] !== (int)$order->id) {
            return;
        }
        $payAt = $first[0]['pay_at'];
        if (!$c->isGrantable($payAt)) {
            return;
        }
        $invitee = User::find()->select(['id', 'parent_id', 'parent_at'])
            ->where(['id' => (int)$order->user_id, 'mall_id' => $mallId])->one();
        if (!$invitee || (int)$invitee->parent_id <= 0) {
            return;
        }
        if ($cond['require_bind_before'] && (int)$invitee->parent_at > 0 && (int)$invitee->parent_at > $payAt) {
            return; // 先下单后绑定,不算邀请带来的首单
        }
        $inviter = User::find()->select(['id'])
            ->where(['id' => (int)$invitee->parent_id, 'mall_id' => $mallId, 'is_delete' => 0])->one();
        if (!$inviter || !AudienceService::matchUser($mallId, (int)$inviter->id, $c->getAudience())) {
            return;
        }

        $max = $cond['max_per_inviter'];
        $campaignId = (int)$c->id;
        $inviterId = (int)$inviter->id;
        // 锁内复核:本单仍是被邀请人第一笔有效订单
        $stillFirst = function () use ($order, $mallId, $cond) {
            $now = self::firstValidOrders($mallId, (int)$order->user_id, 1, $cond['order_types']);
            return $now && $now[0]['id'] === (int)$order->id;
        };
        // 邀请人:另加每人上限(持活动行锁计数,防并发超发)
        $inviterGuard = function () use ($stillFirst, $campaignId, $inviterId, $max) {
            return $stillFirst() && ($max <= 0 || self::inviteCount($campaignId, $inviterId) < $max);
        };

        GrantService::grant($campaignId, $inviterId, MarketingTrigger::EVENT_INVITE,
            'invite:' . (int)$invitee->id, MarketingTrigger::INVITE_TIER_INVITER, $payAt, $inviterGuard);
        GrantService::grant($campaignId, (int)$invitee->id, MarketingTrigger::EVENT_INVITE,
            'invited', MarketingTrigger::INVITE_TIER_INVITEE, $payAt, $stillFirst);
    }

    // ================= 回收 =================

    public static function onRevert(Order $order): void
    {
        self::revertRebuy($order);
        self::revertInvite($order);
    }

    /** 新客复购:重算前两笔有效订单,不再满足 N 天内复购则回收 */
    private static function revertRebuy(Order $order): void
    {
        $mallId = (int)$order->mall_id;
        $userId = (int)$order->user_id;
        $event = MarketingTrigger::EVENT_NEW_REBUY;
        $filter = ['user_id' => $userId, 'dedup_key' => 'rebuy'];

        foreach (CumulativeService::candidateCampaignIds($mallId, $event, $filter) as $campaignId) {
            CumulativeService::withCampaignLock($campaignId, function (MarketingCampaign $c) use ($order, $mallId, $userId, $event, $filter) {
                $rows = CumulativeService::grantedRows($c, array_merge($filter, ['source_event' => $event]));
                if (!$rows) {
                    return;
                }
                $cond = self::normalizeRebuy(CumulativeService::rawConditionOf((int)$c->id, $event));
                $orders = self::firstValidOrders($mallId, $userId, 2, $cond['order_types']);
                if (count($orders) >= 2 && ($orders[1]['pay_at'] - $orders[0]['pay_at']) <= $cond['days'] * 86400) {
                    return;
                }
                foreach ($rows as $row) {
                    RewardRevertService::revert($row, "订单#{$order->id}售后,不再满足新客复购条件,回收营销奖励");
                }
            });
        }
    }

    /** 邀请首单:被邀请人在该活动计入的订单类型里已没有任何已结算的有效订单时,回收邀请人与被邀请人的奖励 */
    private static function revertInvite(Order $order): void
    {
        $mallId = (int)$order->mall_id;
        $inviteeId = (int)$order->user_id;
        $event = MarketingTrigger::EVENT_INVITE;

        $ids = array_merge(
            CumulativeService::candidateCampaignIds($mallId, $event, ['dedup_key' => 'invite:' . $inviteeId]),
            CumulativeService::candidateCampaignIds($mallId, $event, ['user_id' => $inviteeId, 'dedup_key' => 'invited'], false)
        );
        foreach (array_unique($ids) as $campaignId) {
            CumulativeService::withCampaignLock((int)$campaignId, function (MarketingCampaign $c) use ($order, $mallId, $inviteeId, $event) {
                $rows = array_merge(
                    CumulativeService::grantedRows($c, ['source_event' => $event, 'dedup_key' => 'invite:' . $inviteeId]),
                    CumulativeService::grantedRows($c, ['source_event' => $event, 'user_id' => $inviteeId, 'dedup_key' => 'invited'])
                );
                if (!$rows) {
                    return;
                }
                $cond = self::normalizeInvite(CumulativeService::rawConditionOf((int)$c->id, $event));
                $stillValid = CumulativeService::baseValidQuery($cond['order_types'])
                    ->andWhere(['o.mall_id' => $mallId, 'o.user_id' => $inviteeId])
                    ->exists();
                if ($stillValid) {
                    return;
                }
                foreach ($rows as $row) {
                    RewardRevertService::revert($row, "被邀请人#{$inviteeId}订单#{$order->id}售后后无有效订单,回收邀请奖励");
                }
            });
        }
    }

    // ================= C 端进度 =================

    public static function progress(int $mallId, int $userId): array
    {
        $now = time();
        $list = [];

        foreach (CumulativeService::campaigns($mallId, MarketingTrigger::EVENT_NEW_REBUY) as $item) {
            /** @var MarketingCampaign $c */
            $c = $item['campaign'];
            if (!$c->isGrantable($now) || !AudienceService::matchUser($mallId, $userId, $c->getAudience())) {
                continue;
            }
            $cond = self::normalizeRebuy($item['condition']);
            $orders = self::firstValidOrders($mallId, $userId, 2, $cond['order_types']);
            $state = null;
            $deadline = '';
            if (in_array('rebuy', CumulativeService::grantedKeys((int)$c->id, $userId), true)) {
                $state = 'granted';
            } elseif (count($orders) === 0) {
                $state = 'no_first';
            } elseif (count($orders) === 1) {
                $end = $orders[0]['pay_at'] + $cond['days'] * 86400;
                if ($now <= $end) {
                    $state = 'pending';
                    $deadline = date('Y-m-d H:i', $end);
                }
            } elseif ($orders[1]['pay_at'] - $orders[0]['pay_at'] <= $cond['days'] * 86400) {
                $state = 'reached'; // 第二单已付款,等结算后发放
            }
            if ($state === null) {
                continue; // 已错过条件,不展示
            }
            $list[] = [
                'kind' => 'rebuy',
                'campaign_id' => (int)$c->id,
                'name' => $c->name,
                'days' => $cond['days'],
                'state' => $state,
                'deadline' => $deadline,
                'rewards' => RewardService::groupedByTier((int)$c->id)[0] ?? [],
                'end_at' => $c->end_at ? date('Y-m-d', $c->end_at) : '',
            ];
        }

        foreach (CumulativeService::campaigns($mallId, MarketingTrigger::EVENT_INVITE) as $item) {
            /** @var MarketingCampaign $c */
            $c = $item['campaign'];
            if (!$c->isGrantable($now) || !AudienceService::matchUser($mallId, $userId, $c->getAudience())) {
                continue;
            }
            $cond = self::normalizeInvite($item['condition']);
            $rewards = RewardService::groupedByTier((int)$c->id);
            $list[] = [
                'kind' => 'invite',
                'campaign_id' => (int)$c->id,
                'name' => $c->name,
                'invited_count' => self::inviteCount((int)$c->id, $userId),
                'max_per_inviter' => $cond['max_per_inviter'],
                'rewards' => $rewards[MarketingTrigger::INVITE_TIER_INVITER] ?? [],
                'invitee_rewards' => $rewards[MarketingTrigger::INVITE_TIER_INVITEE] ?? [],
                'end_at' => $c->end_at ? date('Y-m-d', $c->end_at) : '',
            ];
        }
        return $list;
    }

    // ================= 工具 =================

    public static function normalizeRebuy(array $cond): array
    {
        return [
            'days' => max(1, (int)($cond['days'] ?? 7)),
            'order_types' => CumulativeService::normalizeOrderTypes($cond),
        ];
    }

    public static function normalizeInvite(array $cond): array
    {
        return [
            'max_per_inviter' => max(0, (int)($cond['max_per_inviter'] ?? 0)),
            'require_bind_before' => array_key_exists('require_bind_before', $cond) ? (empty($cond['require_bind_before']) ? 0 : 1) : 1,
            'order_types' => CumulativeService::normalizeOrderTypes($cond),
        ];
    }

    /**
     * 用户最早的 N 笔有效订单(不要求已结算),按付款时间升序。
     * @return array<int, array{id:int,pay_at:int}>
     */
    public static function firstValidOrders(int $mallId, int $userId, int $limit, array $orderTypes = []): array
    {
        $rows = CumulativeService::validPaidQuery($orderTypes)
            ->select(['o.id', 'o.pay_at'])
            ->andWhere(['o.mall_id' => $mallId, 'o.user_id' => $userId])
            ->groupBy(['o.id', 'o.pay_at'])
            ->orderBy(['o.pay_at' => SORT_ASC, 'o.id' => SORT_ASC])
            ->limit($limit)
            ->all();
        return array_map(function ($r) {
            return ['id' => (int)$r['id'], 'pay_at' => (int)$r['pay_at']];
        }, $rows);
    }

    /** 某邀请人在该活动已成功获得邀请奖励的人数(未回收) */
    public static function inviteCount(int $campaignId, int $inviterId): int
    {
        return (int)MarketingGrantLog::find()
            ->where([
                'campaign_id' => $campaignId, 'user_id' => $inviterId,
                'source_event' => MarketingTrigger::EVENT_INVITE, 'status' => MarketingGrantLog::STATUS_GRANTED, 'is_delete' => 0,
            ])
            ->andWhere(['like', 'dedup_key', 'invite:%', false])
            ->count('DISTINCT dedup_key');
    }
}
