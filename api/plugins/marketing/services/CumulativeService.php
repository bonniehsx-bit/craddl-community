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

use app\events\CommissionRevertEvent;
use app\models\Order;
use app\models\OrderDetail;
use app\plugins\marketing\models\MarketingCampaign;
use app\plugins\marketing\models\MarketingGrantLog;
use app\plugins\marketing\models\MarketingTrigger;
use app\services\order\OrderTypeRegistry;
use yii\db\Query;

/**
 * 累计类奖励:阶梯奖(order_cumulative)+ 每满循环奖(order_repeat),以及订单类奖励的售后回收
 *
 * 统计指标:有效订单数 / 有效实付 / 指定商品件数 / 指定商品实付。
 *
 * 有效订单口径(与平台判断有效消费一致,再收紧两点):
 *   订单 已支付/部分退款、未关闭、**已结算**;明细 未失效、**售后状态=未售后**。
 *   - 只算已结算:结算在售后窗口之后,避免「下单拿奖再退款」
 *   - 售后状态≠0 即剔除:平台「同意售后」时就发回滚信号,但明细要等退款到账才标失效,
 *     只看失效状态会漏掉这一笔
 *
 * 发放时机:order.settled;回收时机:order.commission.revert(取消/售后/插件退款统一信号)。
 * 统计时间按订单支付时间(pay_at)归属周期。
 */
class CumulativeService
{
    /** 循环奖单次结算最多新发的次数,防「终身累计 + 小步长」一次发几百份;剩余的下次结算继续补发 */
    const REPEAT_MAX_NEW_PER_SETTLE = 50;

    // ================= 发放 =================

    public static function onOrderSettled(Order $order): void
    {
        $mallId = (int)$order->mall_id;
        $userId = (int)$order->user_id;
        if ($mallId <= 0 || $userId <= 0) {
            return;
        }
        // 先看有没有进行中的活动,没有就不查订单(每笔结算都会走到这里)
        $tierCampaigns = self::campaigns($mallId, MarketingTrigger::EVENT_ORDER_CUMULATIVE);
        $repeatCampaigns = self::campaigns($mallId, MarketingTrigger::EVENT_ORDER_REPEAT);
        if (!$tierCampaigns && !$repeatCampaigns) {
            return;
        }
        // 本单已无有效明细(结算前已退/关单)不触发
        if (self::orderValidQuery($order)->count() <= 0) {
            return;
        }
        $payAt = (int)($order->pay_at ?: $order->created_at);

        foreach ($tierCampaigns as $item) {
            $cond = self::normalizeCondition($item['condition']);
            if ($cond && self::orderTypeMatches($cond['order_types'], $order)) {
                self::grantTiers($item['campaign'], $cond, $userId, $payAt);
            }
        }
        foreach ($repeatCampaigns as $item) {
            $cond = self::normalizeRepeat($item['condition']);
            if ($cond && self::orderTypeMatches($cond['order_types'], $order)) {
                self::grantRepeat($item['campaign'], $cond, $userId, $payAt);
            }
        }
    }

    private static function grantTiers(MarketingCampaign $c, array $cond, int $userId, int $payAt): void
    {
        $refTime = self::refTime($cond['period'], $payAt);
        if (!$c->isGrantable($refTime) || !AudienceService::matchUser((int)$c->mall_id, $userId, $c->getAudience())) {
            return;
        }
        $range = self::periodRange($cond['period'], $payAt, $c);
        $value = self::metricValue((int)$c->mall_id, $userId, $cond['metric'], $range, $cond['goods_ids'], $cond['order_types']);
        foreach ($cond['tiers'] as $tier) {
            if ($value >= $tier['threshold']) {
                // 锁内复核:锁外算完到拿到锁之间若有订单退款,这里按最新数据判断,避免发出已不达标的档位
                $guard = function () use ($c, $userId, $cond, $range, $tier) {
                    return self::metricValue((int)$c->mall_id, $userId, $cond['metric'], $range, $cond['goods_ids'], $cond['order_types']) >= $tier['threshold'];
                };
                GrantService::grant(
                    (int)$c->id, $userId, MarketingTrigger::EVENT_ORDER_CUMULATIVE,
                    self::dedupKey($range['key'], (int)$tier['no']), (int)$tier['no'], $refTime, $guard
                );
            }
        }
    }

    private static function grantRepeat(MarketingCampaign $c, array $cond, int $userId, int $payAt): void
    {
        $refTime = self::refTime($cond['period'], $payAt);
        if (!$c->isGrantable($refTime) || !AudienceService::matchUser((int)$c->mall_id, $userId, $c->getAudience())) {
            return;
        }
        $range = self::periodRange($cond['period'], $payAt, $c);
        $value = self::metricValue((int)$c->mall_id, $userId, $cond['metric'], $range, $cond['goods_ids'], $cond['order_types']);
        $times = self::repeatTimes($value, $cond);
        if ($times <= 0) {
            return;
        }
        $existing = array_flip(self::grantedKeys((int)$c->id, $userId));
        $newCount = 0;
        for ($k = 1; $k <= $times && $newCount < self::REPEAT_MAX_NEW_PER_SETTLE; $k++) {
            $key = self::repeatKey($range['key'], $k);
            if (isset($existing[$key])) {
                continue;
            }
            $guard = function () use ($c, $userId, $cond, $range, $k) {
                $v = self::metricValue((int)$c->mall_id, $userId, $cond['metric'], $range, $cond['goods_ids'], $cond['order_types']);
                return self::repeatTimes($v, $cond) >= $k;
            };
            if (GrantService::grant((int)$c->id, $userId, MarketingTrigger::EVENT_ORDER_REPEAT, $key, 0, $refTime, $guard) <= 0) {
                break; // 发不出去(预算耗尽/券停用/锁内复核不过)时后面的次数同样发不出,别空转
            }
            $newCount++;
        }
    }

    // ================= 回收 =================

    /**
     * 订单取消/售后:重算受影响周期,掉档/次数减少的奖励回收;单笔订单奖不再达标的回收。
     */
    public static function onRevert(CommissionRevertEvent $event): void
    {
        $order = Order::findOne(['id' => (int)$event->order_id, 'mall_id' => (int)$event->mall_id]);
        if (!$order) {
            return;
        }
        self::revertTiers($order);
        self::revertRepeat($order);
        self::revertOrderPaid($order);
    }

    private static function revertTiers(Order $order): void
    {
        $mallId = (int)$order->mall_id;
        $userId = (int)$order->user_id;
        $payAt = (int)($order->pay_at ?: $order->created_at);
        $event = MarketingTrigger::EVENT_ORDER_CUMULATIVE;

        foreach (self::candidateCampaignIds($mallId, $event, ['user_id' => $userId]) as $campaignId) {
            self::withCampaignLock($campaignId, function (MarketingCampaign $c) use ($order, $mallId, $userId, $payAt, $event) {
                $rows = self::grantedRows($c, ['user_id' => $userId, 'source_event' => $event]);
                $cond = $rows ? self::normalizeCondition(self::rawConditionOf((int)$c->id, $event)) : null;
                if (!$cond) {
                    return;
                }
                $range = self::periodRange($cond['period'], $payAt, $c);
                $thresholds = [];
                foreach ($cond['tiers'] as $tier) {
                    $thresholds[(int)$tier['no']] = $tier['threshold'];
                }
                $value = null;
                foreach ($rows as $row) {
                    if ($row->dedup_key !== self::dedupKey($range['key'], (int)$row->tier_no)) {
                        continue; // 不是本单所在周期的奖励
                    }
                    if (!isset($thresholds[(int)$row->tier_no])) {
                        continue; // 档位已被改掉,无法判断,不动(保存时已禁止有发放记录后改档位)
                    }
                    if ($value === null) {
                        $value = self::metricValue($mallId, $userId, $cond['metric'], $range, $cond['goods_ids'], $cond['order_types']);
                    }
                    if ($value < $thresholds[(int)$row->tier_no]) {
                        RewardRevertService::revert($row, "订单#{$order->id}售后,累计未达档位,回收营销奖励");
                    }
                }
            });
        }
    }

    private static function revertRepeat(Order $order): void
    {
        $mallId = (int)$order->mall_id;
        $userId = (int)$order->user_id;
        $payAt = (int)($order->pay_at ?: $order->created_at);
        $event = MarketingTrigger::EVENT_ORDER_REPEAT;

        foreach (self::candidateCampaignIds($mallId, $event, ['user_id' => $userId]) as $campaignId) {
            self::withCampaignLock($campaignId, function (MarketingCampaign $c) use ($order, $mallId, $userId, $payAt, $event) {
                $rows = self::grantedRows($c, ['user_id' => $userId, 'source_event' => $event]);
                $cond = $rows ? self::normalizeRepeat(self::rawConditionOf((int)$c->id, $event)) : null;
                if (!$cond) {
                    return;
                }
                $range = self::periodRange($cond['period'], $payAt, $c);
                $prefix = 'rep:' . $range['key'] . ':k';
                $times = null;
                foreach ($rows as $row) {
                    if (strpos($row->dedup_key, $prefix) !== 0 || !ctype_digit(substr($row->dedup_key, strlen($prefix)))) {
                        continue;
                    }
                    if ($times === null) {
                        $times = self::repeatTimes(self::metricValue($mallId, $userId, $cond['metric'], $range, $cond['goods_ids'], $cond['order_types']), $cond);
                    }
                    if ((int)substr($row->dedup_key, strlen($prefix)) > $times) {
                        RewardRevertService::revert($row, "订单#{$order->id}售后,循环奖励次数减少,回收营销奖励");
                    }
                }
            });
        }
    }

    /**
     * 单笔订单奖:明细全部退完/关单即回收;满额型按「订单实付 − 已售后明细实付」和门槛比(与发放时用 order.pay_price 同口径,含运费)。
     */
    private static function revertOrderPaid(Order $order): void
    {
        $mallId = (int)$order->mall_id;
        $userId = (int)$order->user_id;
        $event = MarketingTrigger::EVENT_ORDER_PAID;
        $filter = ['user_id' => $userId, 'dedup_key' => 'order:' . $order->id];

        foreach (self::candidateCampaignIds($mallId, $event, $filter, false) as $campaignId) {
            self::withCampaignLock($campaignId, function (MarketingCampaign $c) use ($order, $filter, $event) {
                $rows = self::grantedRows($c, array_merge($filter, ['source_event' => $event]));
                if (!$rows) {
                    return;
                }
                $cond = self::rawConditionOf((int)$c->id, $event);
                $shouldRevert = (int)$order->status === Order::STATUS_CLOSED || !self::detailValidQuery($order)->exists();
                if (!$shouldRevert && ($cond['order_type'] ?? '') === MarketingTrigger::ORDER_AMOUNT) {
                    $refunded = (float)(new Query())
                        ->from(['od' => OrderDetail::tableName()])
                        ->where(['od.order_id' => (int)$order->id, 'od.is_delete' => 0])
                        ->andWhere(['or', ['<>', 'od.refund_status', OrderDetail::REFUND_STATUS_NO_REFUND], ['od.status' => OrderDetail::STATUS_INVALID]])
                        ->sum('od.pay_price');
                    $shouldRevert = ((float)$order->pay_price - $refunded) < (float)($cond['min_amount'] ?? 0);
                }
                if (!$shouldRevert) {
                    return;
                }
                foreach ($rows as $row) {
                    RewardRevertService::revert($row, "订单#{$order->id}退款后不再满足活动条件,回收营销奖励");
                }
            });
        }
    }

    // ================= C 端进度 =================

    /**
     * 用户在累计类活动中的进度(当前周期)。kind=tiers 阶梯 / repeat 循环。
     */
    public static function progress(int $mallId, int $userId): array
    {
        $now = time();
        $list = [];
        foreach (self::campaigns($mallId, MarketingTrigger::EVENT_ORDER_CUMULATIVE) as $item) {
            /** @var MarketingCampaign $c */
            $c = $item['campaign'];
            $cond = self::normalizeCondition($item['condition']);
            if (!$cond || !$c->isGrantable($now) || !AudienceService::matchUser($mallId, $userId, $c->getAudience())) {
                continue;
            }
            $range = self::periodRange($cond['period'], $now, $c);
            $value = self::metricValue($mallId, $userId, $cond['metric'], $range, $cond['goods_ids'], $cond['order_types']);
            $rewards = RewardService::groupedByTier((int)$c->id);
            $grantedKeys = self::grantedKeys((int)$c->id, $userId);

            $tiers = [];
            $next = null;
            foreach ($cond['tiers'] as $tier) {
                $no = (int)$tier['no'];
                $threshold = $tier['threshold'];
                $granted = in_array(self::dedupKey($range['key'], $no), $grantedKeys, true);
                $tiers[] = [
                    'no' => $no,
                    'threshold' => $threshold,
                    'state' => $granted ? 'granted' : ($value >= $threshold ? 'reached' : 'pending'),
                    'gap' => $value >= $threshold ? 0 : self::fmt($cond['metric'], $threshold - $value),
                    'rewards' => $rewards[$no] ?? [],
                ];
                if ($next === null && $value < $threshold) {
                    $next = ['no' => $no, 'gap' => self::fmt($cond['metric'], $threshold - $value)];
                }
            }
            $list[] = self::card('tiers', $c, $cond, $value, ['next' => $next, 'tiers' => $tiers]);
        }

        foreach (self::campaigns($mallId, MarketingTrigger::EVENT_ORDER_REPEAT) as $item) {
            /** @var MarketingCampaign $c */
            $c = $item['campaign'];
            $cond = self::normalizeRepeat($item['condition']);
            if (!$cond || !$c->isGrantable($now) || !AudienceService::matchUser($mallId, $userId, $c->getAudience())) {
                continue;
            }
            $range = self::periodRange($cond['period'], $now, $c);
            $value = self::metricValue($mallId, $userId, $cond['metric'], $range, $cond['goods_ids'], $cond['order_types']);
            $times = self::repeatTimes($value, $cond);
            $prefix = 'rep:' . $range['key'] . ':k';
            $granted = count(array_filter(self::grantedKeys((int)$c->id, $userId), function ($k) use ($prefix) {
                return strpos($k, $prefix) === 0;
            }));
            $capped = $cond['max_times'] > 0 && $times >= $cond['max_times'];
            $step = $cond['step'];
            $remainder = self::fmt($cond['metric'], $value - floor($value / $step + 1e-9) * $step);
            $list[] = self::card('repeat', $c, $cond, $value, [
                'step' => $step,
                'max_times' => $cond['max_times'],
                'times' => $times,
                'granted_times' => $granted,
                'next_gap' => $capped ? null : self::fmt($cond['metric'], $step - $remainder),
                'rewards' => RewardService::groupedByTier((int)$c->id)[0] ?? [],
            ]);
        }
        return $list;
    }

    private static function card(string $kind, MarketingCampaign $c, array $cond, $value, array $extra): array
    {
        return array_merge([
            'kind' => $kind,
            'campaign_id' => (int)$c->id,
            'name' => $c->name,
            'metric' => $cond['metric'],
            'period' => $cond['period'],
            'value' => self::fmt($cond['metric'], $value),
            'end_at' => $c->end_at ? date('Y-m-d', $c->end_at) : '',
        ], $extra);
    }

    // ================= 条件规整 =================

    /**
     * 阶梯奖条件;档位按门槛升序。无效返回 null。
     */
    public static function normalizeCondition(array $cond): ?array
    {
        $base = self::normalizeMetric($cond);
        if (!$base) {
            return null;
        }
        $tiers = [];
        foreach ((array)($cond['tiers'] ?? []) as $t) {
            $no = (int)($t['no'] ?? 0);
            $threshold = self::num($base['metric'], $t['threshold'] ?? 0);
            if ($no > 0 && $threshold > 0) {
                $tiers[] = ['no' => $no, 'threshold' => $threshold];
            }
        }
        if (!$tiers) {
            return null;
        }
        usort($tiers, function ($a, $b) { return $a['threshold'] <=> $b['threshold']; });
        return array_merge($base, ['tiers' => $tiers]);
    }

    /**
     * 循环奖条件:step 每满多少奖一次;max_times 每个周期最多奖几次(0=不限)。无效返回 null。
     */
    public static function normalizeRepeat(array $cond): ?array
    {
        $base = self::normalizeMetric($cond);
        if (!$base) {
            return null;
        }
        $step = self::num($base['metric'], $cond['step'] ?? 0);
        if ($step <= 0) {
            return null;
        }
        return array_merge($base, ['step' => $step, 'max_times' => max(0, (int)($cond['max_times'] ?? 0))]);
    }

    /** 公共部分:指标/周期/指定商品/订单类型。指定商品指标没配商品视为无效 */
    private static function normalizeMetric(array $cond): ?array
    {
        $metric = in_array($cond['metric'] ?? '', MarketingTrigger::metrics(), true) ? $cond['metric'] : MarketingTrigger::METRIC_COUNT;
        $period = in_array($cond['period'] ?? '', MarketingTrigger::periods(), true) ? $cond['period'] : MarketingTrigger::PERIOD_CAMPAIGN;
        $goodsIds = [];
        if (MarketingTrigger::isGoodsMetric($metric)) {
            $goodsIds = array_values(array_unique(array_filter(array_map('intval', (array)($cond['goods_ids'] ?? [])))));
            if (!$goodsIds) {
                return null;
            }
        }
        return ['metric' => $metric, 'period' => $period, 'goods_ids' => $goodsIds, 'order_types' => self::normalizeOrderTypes($cond)];
    }

    // ================= 订单类型口径 =================

    /** 条件里的「计入的订单类型」(平台订单类型 key);空数组 = 不限(兼容未配置的老活动) */
    public static function normalizeOrderTypes(array $cond): array
    {
        return array_values(array_unique(array_filter(array_map('strval', (array)($cond['order_types'] ?? [])))));
    }

    /** 平台订单类型清单(按商城请求级缓存;清单按当前商城已授权插件过滤) */
    private static $typeRegistry = [];

    private static function typeRegistry(): array
    {
        $mallId = (int)(\Yii::$app->mall->id ?? 0);
        if (!isset(self::$typeRegistry[$mallId])) {
            self::$typeRegistry[$mallId] = OrderTypeRegistry::all();
        }
        return self::$typeRegistry[$mallId];
    }

    /** 单个订单是否属于选中的订单类型(与平台 OrderTypeRegistry::matches 同口径) */
    public static function orderTypeMatches(array $keys, Order $order): bool
    {
        if (!$keys) {
            return true;
        }
        $plugin = (string)$order->plugin;
        $type = (string)$order->type;
        foreach (self::typeRegistry() as $t) {
            if (in_array($t['key'], $keys, true) && in_array($plugin, $t['plugins'], true)
                && (empty($t['types']) || in_array($type, $t['types'], true))) {
                return true;
            }
        }
        return false;
    }

    /**
     * 选中订单类型的 SQL 条件;null = 不限。
     * order.plugin 可空且普通订单历史上有空值,选了含 '' 的类型时显式放行 NULL(NULL IN (...) 在 SQL 里不成立)。
     */
    public static function orderTypeCondition(array $keys): ?array
    {
        if (!$keys) {
            return null;
        }
        $or = ['or'];
        foreach (self::typeRegistry() as $t) {
            if (!in_array($t['key'], $keys, true)) {
                continue;
            }
            $pluginCond = ['o.plugin' => $t['plugins']];
            if (in_array('', $t['plugins'], true)) {
                $pluginCond = ['or', $pluginCond, ['o.plugin' => null]];
            }
            $or[] = empty($t['types']) ? $pluginCond : ['and', $pluginCond, ['o.type' => $t['types']]];
        }
        return count($or) > 1 ? $or : ['o.id' => -1]; // 选中的类型当前都不可用 → 一单都不算
    }

    // ================= 口径与工具 =================

    /**
     * 进行中的某类触发活动(不按当前时间过滤活动时段,由调用方判断)。
     * @return array<int, array{campaign: MarketingCampaign, condition: array}> condition 为原始条件
     */
    public static function campaigns(int $mallId, string $event): array
    {
        $triggers = MarketingTrigger::find()
            ->where(['mall_id' => $mallId, 'event' => $event, 'is_delete' => 0])
            ->all();
        if (!$triggers) {
            return [];
        }
        $campaigns = MarketingCampaign::find()
            ->where([
                'id' => array_map(function ($t) { return (int)$t->campaign_id; }, $triggers),
                'mall_id' => $mallId,
                'type' => MarketingCampaign::TYPE_TRIGGER,
                'status' => MarketingCampaign::STATUS_RUNNING,
                'is_delete' => 0,
            ])->indexBy('id')->all();

        $out = [];
        foreach ($triggers as $t) {
            $c = $campaigns[$t->campaign_id] ?? null;
            if ($c) {
                $out[] = ['campaign' => $c, 'condition' => $t->getCondition()];
            }
        }
        return $out;
    }

    /** 回收时读条件:活动可能已结束/改过类型,不按状态过滤,优先未删除的最近一条 */
    public static function rawConditionOf(int $campaignId, string $event): array
    {
        $t = MarketingTrigger::find()
            ->where(['campaign_id' => $campaignId, 'event' => $event])
            ->orderBy(['is_delete' => SORT_ASC, 'id' => SORT_DESC])
            ->one();
        return $t ? $t->getCondition() : [];
    }

    // ================= 回收并发控制 =================

    /**
     * 回收要检查的活动:已有该用户「已发放」流水的活动 +(可选)该事件下进行中的活动。
     * 后者覆盖「发放事务尚未提交、流水还查不到」的并发窗口:回收会在活动行锁上等发放提交后再查。
     * @param array $filter 流水附加条件(user_id / dedup_key 等,字段名不带别名)
     */
    public static function candidateCampaignIds(int $mallId, string $sourceEvent, array $filter, bool $includeRunning = true): array
    {
        $ids = MarketingGrantLog::find()->select('campaign_id')->distinct()
            ->where(array_merge($filter, [
                'mall_id' => $mallId, 'source_event' => $sourceEvent,
                'status' => MarketingGrantLog::STATUS_GRANTED, 'is_delete' => 0,
            ]))->column();
        if ($includeRunning) {
            foreach (self::campaigns($mallId, $sourceEvent) as $item) {
                $ids[] = (int)$item['campaign']->id;
            }
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * 持活动行锁执行回收判断。发放(GrantService)也锁同一行,二者串行:
     * 回收拿到锁时,并发中的发放要么已提交(流水可见、按最新累计判断),要么尚未开始(发放时锁内复核会看到本次退款)。
     */
    public static function withCampaignLock(int $campaignId, callable $fn): void
    {
        $tx = \Yii::$app->db->beginTransaction();
        try {
            $c = MarketingCampaign::find()->where(['id' => $campaignId])->forUpdate()->one();
            if ($c) {
                $fn($c);
            }
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            \Yii::error("[营销奖励] 回收判断失败 campaign={$campaignId} - " . $e->getMessage());
        }
    }

    /** 锁内读取某活动下「已发放」的流水 */
    public static function grantedRows(MarketingCampaign $c, array $filter): array
    {
        return MarketingGrantLog::find()->where(array_merge($filter, [
            'campaign_id' => (int)$c->id,
            'status' => MarketingGrantLog::STATUS_GRANTED,
            'is_delete' => 0,
        ]))->all();
    }

    public static function grantedKeys(int $campaignId, int $userId): array
    {
        return MarketingGrantLog::find()
            ->select('dedup_key')->distinct()
            ->where(['campaign_id' => $campaignId, 'user_id' => $userId, 'status' => MarketingGrantLog::STATUS_GRANTED, 'is_delete' => 0])
            ->column();
    }

    /** 终身累计:历史订单也算,判断活动时段用当前时间;其余周期用本单付款时间 */
    private static function refTime(string $period, int $payAt): int
    {
        return $period === MarketingTrigger::PERIOD_LIFETIME ? time() : $payAt;
    }

    /** 当前周期可奖次数(已按每周期上限封顶) */
    public static function repeatTimes($value, array $cond): int
    {
        $times = (int)floor($value / $cond['step'] + 1e-9);
        return $cond['max_times'] > 0 ? min($times, $cond['max_times']) : $times;
    }

    /**
     * 统计区间与周期键。区间与活动时段取交集(终身累计不限时段)。
     * @return array{start:int,end:int,key:string} start/end 为 0 表示不限
     */
    public static function periodRange(string $period, int $ts, MarketingCampaign $c): array
    {
        $start = 0;
        $end = 0;
        switch ($period) {
            case MarketingTrigger::PERIOD_MONTH:
                $start = mktime(0, 0, 0, (int)date('n', $ts), 1, (int)date('Y', $ts));
                $end = mktime(0, 0, 0, (int)date('n', $ts) + 1, 1, (int)date('Y', $ts)) - 1;
                $key = 'm' . date('Ym', $ts);
                break;
            case MarketingTrigger::PERIOD_WEEK:
                $dayStart = mktime(0, 0, 0, (int)date('n', $ts), (int)date('j', $ts), (int)date('Y', $ts));
                $start = $dayStart - ((int)date('N', $ts) - 1) * 86400;
                $end = $start + 7 * 86400 - 1;
                $key = 'w' . date('oW', $ts);
                break;
            case MarketingTrigger::PERIOD_LIFETIME:
                return ['start' => 0, 'end' => 0, 'key' => 'l'];
            default: // 活动期内
                $key = 'c';
        }
        if ((int)$c->start_at > 0) {
            $start = max($start, (int)$c->start_at);
        }
        if ((int)$c->end_at > 0) {
            $end = $end > 0 ? min($end, (int)$c->end_at) : (int)$c->end_at;
        }
        return ['start' => $start, 'end' => $end, 'key' => $key];
    }

    /**
     * 累计值:有效订单数 / 有效实付 / 指定商品件数 / 指定商品实付。
     * @return int|float
     */
    public static function metricValue(int $mallId, int $userId, string $metric, array $range, array $goodsIds = [], array $orderTypes = [])
    {
        $q = self::baseValidQuery($orderTypes)
            ->andWhere(['o.mall_id' => $mallId, 'o.user_id' => $userId]);
        if ($range['start'] > 0) {
            $q->andWhere(['>=', 'o.pay_at', $range['start']]);
        }
        if ($range['end'] > 0) {
            $q->andWhere(['<=', 'o.pay_at', $range['end']]);
        }
        switch ($metric) {
            case MarketingTrigger::METRIC_AMOUNT:
                return round((float)$q->sum('od.pay_price'), 2);
            case MarketingTrigger::METRIC_GOODS_QTY:
                return (int)$q->andWhere(['od.goods_id' => $goodsIds ?: [-1]])->sum('od.num');
            case MarketingTrigger::METRIC_GOODS_AMOUNT:
                return round((float)$q->andWhere(['od.goods_id' => $goodsIds ?: [-1]])->sum('od.pay_price'), 2);
            default:
                return (int)$q->select('COUNT(DISTINCT o.id)')->scalar();
        }
    }

    public static function dedupKey(string $periodKey, int $tierNo): string
    {
        return 'cum:' . $periodKey . ':t' . $tierNo;
    }

    public static function repeatKey(string $periodKey, int $k): string
    {
        return 'rep:' . $periodKey . ':k' . $k;
    }

    /** 有效订单 + 有效明细(不要求已结算),可限定订单类型 */
    public static function validPaidQuery(array $orderTypes = []): Query
    {
        $q = (new Query())
            ->from(['o' => Order::tableName()])
            ->innerJoin(['od' => OrderDetail::tableName()], 'od.order_id = o.id')
            ->where([
                'o.is_delete' => 0,
                'o.pay_status' => [Order::PAY_STATUS_PAID, Order::PAY_STATUS_PARTIAL_REFUND],
                'od.is_delete' => 0,
                'od.refund_status' => OrderDetail::REFUND_STATUS_NO_REFUND,
            ])
            ->andWhere(['<>', 'o.status', Order::STATUS_CLOSED])
            ->andWhere(['<>', 'od.status', OrderDetail::STATUS_INVALID]);
        $typeCond = self::orderTypeCondition($orderTypes);
        if ($typeCond !== null) {
            $q->andWhere($typeCond);
        }
        return $q;
    }

    /** 有效订单 + 有效明细 + 已结算,可限定订单类型 */
    public static function baseValidQuery(array $orderTypes = []): Query
    {
        return self::validPaidQuery($orderTypes)->andWhere(['o.settlement_status' => Order::SETTLEMENT_STATUS_SETTLED]);
    }

    /** 本单是否仍有有效明细(结算触发时用) */
    private static function orderValidQuery(Order $order): Query
    {
        return self::baseValidQuery()->andWhere(['o.id' => (int)$order->id]);
    }

    /** 本单有效明细(不要求已结算,单笔订单奖回收用) */
    private static function detailValidQuery(Order $order): Query
    {
        return (new Query())
            ->from(['od' => OrderDetail::tableName()])
            ->where([
                'od.order_id' => (int)$order->id,
                'od.is_delete' => 0,
                'od.refund_status' => OrderDetail::REFUND_STATUS_NO_REFUND,
            ])
            ->andWhere(['<>', 'od.status', OrderDetail::STATUS_INVALID]);
    }

    private static function num(string $metric, $v)
    {
        return MarketingTrigger::isIntMetric($metric) ? (int)$v : round((float)$v, 2);
    }

    private static function fmt(string $metric, $v)
    {
        return MarketingTrigger::isIntMetric($metric) ? (int)round((float)$v) : round((float)$v, 2);
    }
}
