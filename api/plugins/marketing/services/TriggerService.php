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
use app\plugins\marketing\models\MarketingCampaign;
use app\plugins\marketing\models\MarketingTrigger;
use app\services\EffectiveSpend;

/**
 * 触发编排:把平台事件/定时扫描 → 匹配触发型活动 → 人群校验 → 调 GrantService 发券。
 *
 * 事件型(实时):订单支付、会员升级。
 * 定时型(每日 cron,逐商场):新人注册、生日、沉睡召回。
 */
class TriggerService
{
    /**
     * 取某商场某事件下"进行中"的触发型活动(附带触发条件)。
     * @return array<int, array{campaign: MarketingCampaign, condition: array}>
     */
    private static function activeTriggers(int $mallId, string $event): array
    {
        $triggers = MarketingTrigger::find()
            ->where(['mall_id' => $mallId, 'event' => $event, 'is_delete' => 0])
            ->all();
        if (!$triggers) {
            return [];
        }
        $campaignIds = array_map(function ($t) { return (int)$t->campaign_id; }, $triggers);
        $campaigns = MarketingCampaign::find()
            ->where(['id' => $campaignIds, 'type' => MarketingCampaign::TYPE_TRIGGER, 'is_delete' => 0])
            ->indexBy('id')->all();

        $out = [];
        foreach ($triggers as $t) {
            $c = $campaigns[$t->campaign_id] ?? null;
            if ($c && $c->isGrantable()) {
                $out[] = ['campaign' => $c, 'condition' => $t->getCondition()];
            }
        }
        return $out;
    }

    // ================= 事件型 =================

    /**
     * 订单支付 → 首单/满额/复购返券。
     */
    public static function onOrderPaid(Order $order): void
    {
        $mallId = (int)$order->mall_id;
        $userId = (int)$order->user_id;
        if ($mallId <= 0 || $userId <= 0) {
            return;
        }

        foreach (self::activeTriggers($mallId, MarketingTrigger::EVENT_ORDER_PAID) as $item) {
            $c = $item['campaign'];
            $cond = $item['condition'];
            if (!self::matchOrderCondition($order, $cond)) {
                continue;
            }
            // 人群按「这单之前」判(仅首单人群不把刚付款的这单算进去)
            if (!AudienceService::matchUser($mallId, $userId, $c->getAudience(), (int)$order->id)) {
                continue;
            }
            GrantService::grant($c->id, $userId, MarketingTrigger::EVENT_ORDER_PAID, 'order:' . $order->id);
        }
    }

    private static function matchOrderCondition(Order $order, array $cond): bool
    {
        // 计入的订单类型(未配置 = 不限,兼容老活动)
        if (!CumulativeService::orderTypeMatches(CumulativeService::normalizeOrderTypes($cond), $order)) {
            return false;
        }
        $subType = $cond['order_type'] ?? MarketingTrigger::ORDER_ANY;

        if ($subType === MarketingTrigger::ORDER_FIRST) {
            // 首单:除本单外无其它已支付订单(部分退款的单也是付过款的,支付状态是「部分退款」;
            // 原来只认「已支付」,上一单退了一件,下一单又能再领一次首单礼)
            $hasOther = Order::find()
                ->where(['mall_id' => $order->mall_id, 'user_id' => $order->user_id, 'pay_status' => EffectiveSpend::PAID_STATUSES, 'is_delete' => 0])
                ->andWhere(['<>', 'id', $order->id])
                ->exists();
            return !$hasOther;
        }
        if ($subType === MarketingTrigger::ORDER_AMOUNT) {
            $min = (float)($cond['min_amount'] ?? 0);
            return (float)$order->pay_price >= $min;
        }
        return true; // any
    }

    /**
     * 会员升级 → 升级奖励券。
     * @param int $newLevelId 升级后的等级ID
     */
    public static function onLevelUp(int $mallId, int $userId, int $newLevelId): void
    {
        if ($mallId <= 0 || $userId <= 0) {
            return;
        }
        foreach (self::activeTriggers($mallId, MarketingTrigger::EVENT_LEVEL_UP) as $item) {
            $c = $item['campaign'];
            $cond = $item['condition'];
            // 目标等级过滤(空=任意升级都发)
            $targets = array_map('intval', (array)($cond['target_level_ids'] ?? []));
            if ($targets && !in_array($newLevelId, $targets, true)) {
                continue;
            }
            if (!AudienceService::matchUser($mallId, $userId, $c->getAudience())) {
                continue;
            }
            GrantService::grant($c->id, $userId, MarketingTrigger::EVENT_LEVEL_UP, 'lvup:' . $newLevelId);
        }
    }

    /**
     * 用户注册 → 新人礼包【即时到账】(监听 UserEvent::REGISTER,单用户)。
     * 与 cron runNewUser 共用幂等键 'reg',即时发过后 cron 不会重发(互为兜底)。
     */
    public static function onRegister(int $mallId, int $userId): void
    {
        if ($mallId <= 0 || $userId <= 0) {
            return;
        }
        foreach (self::activeTriggers($mallId, MarketingTrigger::EVENT_REGISTER) as $item) {
            $c = $item['campaign'];
            if (!AudienceService::matchUser($mallId, $userId, $c->getAudience())) {
                continue;
            }
            GrantService::grant($c->id, $userId, MarketingTrigger::EVENT_REGISTER, 'reg');
        }
    }

    // ================= 定时型(逐商场,由 cron 传入 mallId) =================

    /**
     * 新人注册礼包:扫近期注册用户,幂等一次性发放(cron 兜底,防即时事件漏发)。
     */
    public static function runNewUser(int $mallId): int
    {
        $total = 0;
        foreach (self::activeTriggers($mallId, MarketingTrigger::EVENT_REGISTER) as $item) {
            $c = $item['campaign'];
            $cond = $item['condition'];
            // 扫描窗口(默认近2天,容错 cron 漏跑;幂等键固定 reg,重复扫不会重发)
            $days = max(1, (int)($cond['scan_days'] ?? 2));
            $rule = $c->getAudience();
            $rule['reg_start'] = max((int)($rule['reg_start'] ?? 0), time() - $days * 86400);
            foreach (AudienceService::userIds($mallId, $rule) as $uid) {
                $total += GrantService::grant($c->id, $uid, MarketingTrigger::EVENT_REGISTER, 'reg');
            }
        }
        return $total;
    }

    /**
     * 生日券:扫当天/当月生日用户(User.month/day),按年幂等。
     */
    public static function runBirthday(int $mallId): int
    {
        $total = 0;
        $year = (int)date('Y');
        $month = (int)date('n');
        $day = (int)date('j');

        foreach (self::activeTriggers($mallId, MarketingTrigger::EVENT_BIRTHDAY) as $item) {
            $c = $item['campaign'];
            $cond = $item['condition'];
            $mode = ($cond['mode'] ?? MarketingTrigger::BIRTHDAY_DAY) === MarketingTrigger::BIRTHDAY_MONTH
                ? MarketingTrigger::BIRTHDAY_MONTH : MarketingTrigger::BIRTHDAY_DAY;

            $query = AudienceService::build($mallId, $c->getAudience())
                ->andWhere(['month' => $month]);
            if ($mode === MarketingTrigger::BIRTHDAY_DAY) {
                $query->andWhere(['day' => $day]);
            }
            $uids = array_map('intval', $query->select('id')->column());

            // 当月模式按"年+月"幂等;当天模式按"年"幂等
            $dedup = $mode === MarketingTrigger::BIRTHDAY_MONTH ? ('bday:' . $year . ':' . $month) : ('bday:' . $year);
            foreach ($uids as $uid) {
                $total += GrantService::grant($c->id, $uid, MarketingTrigger::EVENT_BIRTHDAY, $dedup);
            }
        }
        return $total;
    }

    /**
     * 沉睡召回:扫末次下单距今>=N天的用户,按冷却周期幂等。
     */
    public static function runRecall(int $mallId): int
    {
        $total = 0;
        foreach (self::activeTriggers($mallId, MarketingTrigger::EVENT_INACTIVE) as $item) {
            $c = $item['campaign'];
            $cond = $item['condition'];
            $days = max(1, (int)($cond['days'] ?? 30));
            $cooldown = max(1, (int)($cond['cooldown_days'] ?? 30));

            $rule = $c->getAudience();
            $rule['last_order_days'] = max((int)($rule['last_order_days'] ?? 0), $days);

            // 冷却周期分桶:同一用户在一个冷却窗口内只召回一次
            $bucket = (int)floor(time() / ($cooldown * 86400));
            $dedup = 'recall:' . $bucket;

            foreach (AudienceService::userIds($mallId, $rule) as $uid) {
                $total += GrantService::grant($c->id, $uid, MarketingTrigger::EVENT_INACTIVE, $dedup);
            }
        }
        return $total;
    }
}
