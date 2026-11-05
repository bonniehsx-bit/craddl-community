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

namespace app\plugins\marketing\forms\mall;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Coupon;
use app\plugins\marketing\models\MarketingBatchTask;
use app\plugins\marketing\models\MarketingCampaign;
use app\plugins\marketing\models\MarketingGrantLog;
use app\plugins\marketing\models\MarketingReward;
use app\plugins\marketing\models\MarketingTrigger;
use app\plugins\marketing\services\AudienceService;
use app\plugins\marketing\services\CumulativeService;
use app\plugins\marketing\services\RewardService;
use app\services\order\OrderTypeRegistry;
use Yii;

/**
 * 营销活动保存 / 状态变更 / 删除
 *
 * 保存负责:活动主体 + 奖励项(券/积分/余额,阶梯活动按档位)+ 触发规则 + 群发任务 + 人群/预算。
 */
class CampaignSaveForm extends BaseModel
{
    public $id;
    public $name;
    public $type;
    public $status;
    public $start_at;
    public $end_at;
    public $budget_type;
    public $budget_total;
    public $budget_integral;
    public $budget_balance;
    public $per_user_limit;
    public $remark;
    public $audience;   // array
    public $rewards;    // array [{reward_type,coupon_id,amount,num}] 非阶梯活动的奖励(档位0);邀请首单=邀请人奖励
    public $invitee_rewards; // array 邀请首单:被邀请人奖励(可选)
    public $coupons;    // array [{coupon_id,num}] 旧版后台兼容,仅在未传 rewards 时使用
    public $trigger;    // array {event, ...condition};累计奖 tiers=[{threshold, rewards:[...]}]
    public $batch;      // array {plan_at}

    public function rules()
    {
        return [
            [['name', 'type'], 'required', 'on' => 'save'],
            [['id', 'type', 'status', 'start_at', 'end_at', 'budget_type', 'per_user_limit'], 'integer'],
            [['budget_total', 'budget_integral', 'budget_balance'], 'number'],
            [['name', 'remark'], 'string'],
            [['audience', 'rewards', 'invitee_rewards', 'coupons', 'trigger', 'batch'], 'safe'],
        ];
    }

    public function save()
    {
        $this->scenario = 'save';
        if (!$this->validate()) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => current($this->getFirstErrors())];
        }

        $type = in_array((int)$this->type, [
            MarketingCampaign::TYPE_TRIGGER, MarketingCampaign::TYPE_BATCH, MarketingCampaign::TYPE_CLAIM,
        ], true) ? (int)$this->type : MarketingCampaign::TYPE_TRIGGER;

        $event = $type === MarketingCampaign::TYPE_TRIGGER ? (string)($this->trigger['event'] ?? '') : '';
        if ($type === MarketingCampaign::TYPE_TRIGGER && !in_array($event, MarketingTrigger::events(), true)) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '请选择有效的触发事件'];
        }

        try {
            if ($event === MarketingTrigger::EVENT_ORDER_CUMULATIVE) {
                [$condition, $rewards] = $this->normalizeCumulative();
            } elseif ($event === MarketingTrigger::EVENT_INVITE) {
                $condition = $this->normalizeCondition($event);
                $rewards = $this->normalizeRewards($this->sourceRewards(), MarketingTrigger::INVITE_TIER_INVITER);
                if (!$rewards) {
                    throw new \Exception('请至少配置一项邀请人奖励');
                }
                $rewards = array_merge($rewards, $this->normalizeRewards((array)$this->invitee_rewards, MarketingTrigger::INVITE_TIER_INVITEE));
            } else {
                $condition = $event ? $this->normalizeCondition($event) : [];
                $rewards = $this->normalizeRewards($this->sourceRewards(), 0);
                if (!$rewards) {
                    throw new \Exception('请至少配置一项奖励');
                }
            }
        } catch (\Throwable $e) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => $e->getMessage()];
        }

        // 已有发放记录的活动,不允许改影响回收判断的配置,否则已发奖励可能找不到回收路径或被误收
        if ($this->id) {
            $lockMsg = $this->checkLockedConfig((int)$this->id, $type, $event, $condition);
            if ($lockMsg !== null) {
                return ['code' => ApiCode::CODE_FAIL, 'msg' => $lockMsg];
            }
        }

        // 领取型发积分/余额:每人不限领等于可反复调接口刷,必须设上限
        if ($type === MarketingCampaign::TYPE_CLAIM && (int)$this->per_user_limit <= 0) {
            foreach ($rewards as $r) {
                if ($r['reward_type'] !== MarketingReward::TYPE_COUPON) {
                    return ['code' => ApiCode::CODE_FAIL, 'msg' => '领取型活动发放积分或余额时,必须设置每人限领次数'];
                }
            }
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 1. 活动主体
            if ($this->id) {
                $campaign = MarketingCampaign::findOne(['id' => (int)$this->id, 'mall_id' => $this->mall_id, 'is_delete' => 0]);
                if (!$campaign) {
                    throw new \Exception('活动不存在');
                }
            } else {
                $campaign = new MarketingCampaign();
                $campaign->mall_id = $this->mall_id;
                $campaign->status = MarketingCampaign::STATUS_DRAFT;
            }
            $campaign->name = trim((string)$this->name);
            $campaign->type = $type;
            $campaign->start_at = max(0, (int)$this->start_at);
            $campaign->end_at = max(0, (int)$this->end_at);
            $campaign->budget_type = in_array((int)$this->budget_type, [0, 1, 2], true) ? (int)$this->budget_type : 0;
            $campaign->budget_total = max(0, (float)$this->budget_total);
            $campaign->budget_integral = max(0, round((float)$this->budget_integral, 2));
            $campaign->budget_balance = max(0, round((float)$this->budget_balance, 2));
            $campaign->per_user_limit = max(0, (int)$this->per_user_limit);
            $campaign->remark = (string)$this->remark;
            $campaign->audience_json = json_encode($this->normalizeAudience(), JSON_UNESCAPED_UNICODE);
            if (!$campaign->save()) {
                throw new \Exception('活动保存失败: ' . current($campaign->getFirstErrors()));
            }

            // 2. 奖励项(全量替换)
            RewardService::replace((int)$this->mall_id, (int)$campaign->id, $rewards);

            // 3. 触发规则 / 群发任务 / 领取型(无附属)。切换类型时清掉其它类型的附属记录
            MarketingTrigger::updateAll(['is_delete' => 1], ['campaign_id' => $campaign->id, 'is_delete' => 0]);
            MarketingBatchTask::updateAll(['is_delete' => 1], [
                'campaign_id' => $campaign->id, 'status' => MarketingBatchTask::STATUS_PENDING, 'is_delete' => 0,
            ]);
            if ($type === MarketingCampaign::TYPE_TRIGGER) {
                $trigger = new MarketingTrigger();
                $trigger->mall_id = $this->mall_id;
                $trigger->campaign_id = $campaign->id;
                $trigger->event = $event;
                $trigger->condition_json = json_encode($condition, JSON_UNESCAPED_UNICODE);
                if (!$trigger->save()) {
                    throw new \Exception('触发规则保存失败: ' . current($trigger->getFirstErrors()));
                }
            } elseif ($type === MarketingCampaign::TYPE_BATCH && !MarketingBatchTask::find()->where([
                'campaign_id' => $campaign->id,
                'status' => [MarketingBatchTask::STATUS_RUNNING, MarketingBatchTask::STATUS_DONE],
                'is_delete' => 0,
            ])->exists()) {
                // 群发型:只在还没开始发过时创建待发任务;已开始/已完成的群发再保存不会重发,要再发请新建活动
                $audience = $campaign->getAudience();
                $task = new MarketingBatchTask();
                $task->mall_id = $this->mall_id;
                $task->campaign_id = $campaign->id;
                $task->plan_at = max(0, (int)($this->batch['plan_at'] ?? 0));
                $task->audience_json = json_encode($audience, JSON_UNESCAPED_UNICODE);
                $task->status = MarketingBatchTask::STATUS_PENDING;
                $task->total_target = (int)AudienceService::build($this->mall_id, $audience)->count();
                if (!$task->save()) {
                    throw new \Exception('群发任务保存失败: ' . current($task->getFirstErrors()));
                }
            }

            $transaction->commit();
            return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '保存成功', 'data' => ['id' => $campaign->id]];
        } catch (\Throwable $e) {
            $transaction->rollBack();
            return ['code' => ApiCode::CODE_FAIL, 'msg' => $e->getMessage()];
        }
    }

    public function changeStatus()
    {
        $campaign = MarketingCampaign::findOne(['id' => (int)$this->id, 'mall_id' => $this->mall_id, 'is_delete' => 0]);
        if (!$campaign) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '活动不存在'];
        }
        $target = (int)$this->status;
        if (!in_array($target, [
            MarketingCampaign::STATUS_RUNNING, MarketingCampaign::STATUS_PAUSED, MarketingCampaign::STATUS_ENDED,
        ], true)) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '非法状态'];
        }
        if ($target === MarketingCampaign::STATUS_RUNNING) {
            $hasReward = MarketingReward::find()->where(['campaign_id' => $campaign->id, 'is_delete' => 0])->exists();
            if (!$hasReward) {
                return ['code' => ApiCode::CODE_FAIL, 'msg' => '活动未配置奖励,无法启用'];
            }
        }
        $campaign->status = $target;
        $campaign->save(false);
        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '操作成功'];
    }

    public function delete()
    {
        $campaign = MarketingCampaign::findOne(['id' => (int)$this->id, 'mall_id' => $this->mall_id, 'is_delete' => 0]);
        if (!$campaign) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '活动不存在'];
        }
        if ($campaign->status == MarketingCampaign::STATUS_RUNNING) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '请先暂停/结束活动再删除'];
        }
        $campaign->is_delete = 1;
        $campaign->save(false);
        MarketingReward::updateAll(['is_delete' => 1], ['campaign_id' => $campaign->id, 'is_delete' => 0]);
        MarketingTrigger::updateAll(['is_delete' => 1], ['campaign_id' => $campaign->id, 'is_delete' => 0]);
        MarketingBatchTask::updateAll(['is_delete' => 1], [
            'campaign_id' => $campaign->id, 'status' => MarketingBatchTask::STATUS_PENDING, 'is_delete' => 0,
        ]);
        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '已删除'];
    }

    // ---------- 已发放活动的配置锁 ----------

    /**
     * @return string|null 不允许保存的原因;null=可保存
     */
    private function checkLockedConfig(int $campaignId, int $type, string $event, array $newCondition): ?string
    {
        $campaign = MarketingCampaign::findOne(['id' => $campaignId, 'mall_id' => $this->mall_id, 'is_delete' => 0]);
        if (!$campaign || !MarketingGrantLog::find()->where(['campaign_id' => $campaignId])->exists()) {
            return null;
        }
        $msg = '活动已有发放记录,不能修改活动类型、触发事件、统计口径、周期、订单类型、档位门槛等会影响售后回收的配置;如需调整请结束本活动后新建';
        $oldTrigger = MarketingTrigger::findOne(['campaign_id' => $campaignId, 'is_delete' => 0]);
        $oldEvent = $oldTrigger ? $oldTrigger->event : '';
        if ((int)$campaign->type !== $type || $oldEvent !== $event) {
            return $msg;
        }
        if ($event === '') {
            return null;
        }
        $old = $this->lockedSignature($event, $oldTrigger->getCondition());
        $new = $this->lockedSignature($event, $newCondition);
        return $old === $new ? null : $msg;
    }

    /** 各触发事件中影响回收判断的字段(规整后比较) */
    private function lockedSignature(string $event, array $cond): array
    {
        $sorted = function ($v, $cast) {
            $a = array_map($cast, (array)$v);
            sort($a);
            return array_values(array_unique($a));
        };
        $types = $sorted($cond['order_types'] ?? [], 'strval');
        switch ($event) {
            case MarketingTrigger::EVENT_ORDER_CUMULATIVE:
                $c = CumulativeService::normalizeCondition($cond) ?: ['metric' => '', 'period' => '', 'goods_ids' => [], 'tiers' => []];
                return [$c['metric'], $c['period'], $sorted($c['goods_ids'], 'intval'), $types,
                    array_map(function ($t) { return [(int)$t['no'], (string)$t['threshold']]; }, $c['tiers'])];
            case MarketingTrigger::EVENT_ORDER_REPEAT:
                $c = CumulativeService::normalizeRepeat($cond) ?: ['metric' => '', 'period' => '', 'goods_ids' => [], 'step' => 0, 'max_times' => 0];
                return [$c['metric'], $c['period'], $sorted($c['goods_ids'], 'intval'), $types, (string)$c['step'], (int)$c['max_times']];
            case MarketingTrigger::EVENT_NEW_REBUY:
                return [(int)($cond['days'] ?? 7), $types];
            case MarketingTrigger::EVENT_INVITE:
                return [$types];
            case MarketingTrigger::EVENT_ORDER_PAID:
                return [(string)($cond['order_type'] ?? 'any'), (string)round((float)($cond['min_amount'] ?? 0), 2), $types];
            default:
                return [];
        }
    }

    // ---------- 规整 ----------

    /** 非阶梯活动的奖励来源:优先 rewards,旧版后台只传 coupons 时兼容转换 */
    private function sourceRewards(): array
    {
        if (is_array($this->rewards)) {
            return $this->rewards;
        }
        $out = [];
        foreach ((array)$this->coupons as $c) {
            $out[] = ['reward_type' => MarketingReward::TYPE_COUPON, 'coupon_id' => $c['coupon_id'] ?? 0, 'num' => $c['num'] ?? 1];
        }
        return $out;
    }

    /**
     * @return array [{tier_no,reward_type,coupon_id,amount,num}]
     * @throws \Exception 配置不合法
     */
    private function normalizeRewards(array $raw, int $tierNo): array
    {
        $out = [];
        foreach ($raw as $r) {
            $type = (string)($r['reward_type'] ?? '');
            if ($type === MarketingReward::TYPE_COUPON) {
                $couponId = (int)($r['coupon_id'] ?? 0);
                if ($couponId <= 0) {
                    continue; // 未选券的空行忽略
                }
                $exists = Coupon::find()->where(['id' => $couponId, 'mall_id' => $this->mall_id, 'is_delete' => 0])->exists();
                if (!$exists) {
                    throw new \Exception("优惠券#{$couponId} 不存在");
                }
                $out[] = ['tier_no' => $tierNo, 'reward_type' => $type, 'coupon_id' => $couponId, 'amount' => 0, 'num' => max(1, (int)($r['num'] ?? 1))];
            } elseif (in_array($type, [MarketingReward::TYPE_INTEGRAL, MarketingReward::TYPE_BALANCE], true)) {
                $amount = round((float)($r['amount'] ?? 0), 2);
                if ($amount <= 0) {
                    throw new \Exception('积分/余额奖励的数额必须大于 0');
                }
                $out[] = ['tier_no' => $tierNo, 'reward_type' => $type, 'coupon_id' => 0, 'amount' => $amount, 'num' => 1];
            }
        }
        return $out;
    }

    /**
     * 累计下单阶梯奖:tiers=[{threshold, rewards:[...]}],按门槛升序编档位号 1..N。
     * @return array [condition, rewards]
     * @throws \Exception
     */
    private function normalizeCumulative(): array
    {
        $t = is_array($this->trigger) ? $this->trigger : [];
        $base = $this->normalizeMetricBase($t);
        $metric = $base['metric'];

        $rawTiers = [];
        foreach ((array)($t['tiers'] ?? []) as $tier) {
            $threshold = MarketingTrigger::isIntMetric($metric)
                ? (int)($tier['threshold'] ?? 0)
                : round((float)($tier['threshold'] ?? 0), 2);
            if ($threshold <= 0) {
                throw new \Exception(MarketingTrigger::isIntMetric($metric) ? '每档的门槛必须是大于 0 的整数' : '每档的金额门槛必须大于 0');
            }
            $rawTiers[] = ['threshold' => $threshold, 'rewards' => (array)($tier['rewards'] ?? [])];
        }
        if (!$rawTiers) {
            throw new \Exception('请至少配置一个档位');
        }
        usort($rawTiers, function ($a, $b) { return $a['threshold'] <=> $b['threshold']; });

        $tiers = [];
        $rewards = [];
        $prev = null;
        foreach ($rawTiers as $i => $tier) {
            if ($prev !== null && $tier['threshold'] == $prev) {
                throw new \Exception('档位门槛不能重复');
            }
            $prev = $tier['threshold'];
            $no = $i + 1;
            $tierRewards = $this->normalizeRewards($tier['rewards'], $no);
            if (!$tierRewards) {
                throw new \Exception("第 {$no} 档(满 {$tier['threshold']})未配置奖励");
            }
            $tiers[] = ['no' => $no, 'threshold' => $tier['threshold']];
            $rewards = array_merge($rewards, $tierRewards);
        }
        return [array_merge($base, ['tiers' => $tiers]), $rewards];
    }

    /**
     * 累计/循环共用:统计指标、周期、指定商品(指定商品指标必须选商品)。
     * @throws \Exception
     */
    private function normalizeMetricBase(array $t): array
    {
        $metric = in_array($t['metric'] ?? '', MarketingTrigger::metrics(), true) ? $t['metric'] : MarketingTrigger::METRIC_COUNT;
        $period = in_array($t['period'] ?? '', MarketingTrigger::periods(), true) ? $t['period'] : MarketingTrigger::PERIOD_CAMPAIGN;
        $goodsIds = [];
        if (MarketingTrigger::isGoodsMetric($metric)) {
            $goodsIds = array_values(array_unique(array_filter(array_map('intval', (array)($t['goods_ids'] ?? [])))));
            if (!$goodsIds) {
                throw new \Exception('按指定商品统计时,请至少选择一个商品');
            }
        }
        return ['metric' => $metric, 'period' => $period, 'goods_ids' => $goodsIds, 'order_types' => $this->normalizeOrderTypes($t)];
    }

    /**
     * 计入的订单类型:只保留平台订单类型清单里存在的 key;空 = 不限。
     * @throws \Exception 传了但一个都不认识
     */
    private function normalizeOrderTypes(array $t): array
    {
        $keys = CumulativeService::normalizeOrderTypes($t);
        if (!$keys) {
            return [];
        }
        $valid = array_column(OrderTypeRegistry::all(), 'key');
        $out = array_values(array_intersect($keys, $valid));
        if (!$out) {
            throw new \Exception('选择的订单类型无效,请重新选择');
        }
        return $out;
    }

    private function normalizeAudience(): array
    {
        $a = is_array($this->audience) ? $this->audience : [];
        return [
            'level_ids' => array_values(array_filter(array_map('intval', (array)($a['level_ids'] ?? [])))),
            'consume_min' => max(0, (float)($a['consume_min'] ?? 0)),
            'consume_max' => max(0, (float)($a['consume_max'] ?? 0)),
            'only_first' => !empty($a['only_first']) ? 1 : 0,
            'reg_start' => max(0, (int)($a['reg_start'] ?? 0)),
            'reg_end' => max(0, (int)($a['reg_end'] ?? 0)),
            'last_order_days' => max(0, (int)($a['last_order_days'] ?? 0)),
        ];
    }

    private function normalizeCondition(string $event): array
    {
        $t = is_array($this->trigger) ? $this->trigger : [];
        switch ($event) {
            case MarketingTrigger::EVENT_REGISTER:
                return ['scan_days' => max(1, (int)($t['scan_days'] ?? 2))];
            case MarketingTrigger::EVENT_ORDER_PAID:
                $ot = in_array($t['order_type'] ?? '', [
                    MarketingTrigger::ORDER_ANY, MarketingTrigger::ORDER_FIRST, MarketingTrigger::ORDER_AMOUNT,
                ], true) ? $t['order_type'] : MarketingTrigger::ORDER_ANY;
                return ['order_type' => $ot, 'min_amount' => max(0, (float)($t['min_amount'] ?? 0)), 'order_types' => $this->normalizeOrderTypes($t)];
            case MarketingTrigger::EVENT_LEVEL_UP:
                return ['target_level_ids' => array_values(array_filter(array_map('intval', (array)($t['target_level_ids'] ?? []))))];
            case MarketingTrigger::EVENT_BIRTHDAY:
                $mode = ($t['mode'] ?? '') === MarketingTrigger::BIRTHDAY_MONTH
                    ? MarketingTrigger::BIRTHDAY_MONTH : MarketingTrigger::BIRTHDAY_DAY;
                return ['mode' => $mode];
            case MarketingTrigger::EVENT_INACTIVE:
                return ['days' => max(1, (int)($t['days'] ?? 30)), 'cooldown_days' => max(1, (int)($t['cooldown_days'] ?? 30))];
            case MarketingTrigger::EVENT_ORDER_REPEAT:
                $base = $this->normalizeMetricBase($t);
                $step = MarketingTrigger::isIntMetric($base['metric']) ? (int)($t['step'] ?? 0) : round((float)($t['step'] ?? 0), 2);
                if ($step <= 0) {
                    throw new \Exception('「每满」的数值必须大于 0');
                }
                return array_merge($base, ['step' => $step, 'max_times' => max(0, (int)($t['max_times'] ?? 0))]);
            case MarketingTrigger::EVENT_NEW_REBUY:
                return ['days' => max(1, (int)($t['days'] ?? 7)), 'order_types' => $this->normalizeOrderTypes($t)];
            case MarketingTrigger::EVENT_INVITE:
                return [
                    'max_per_inviter' => max(0, (int)($t['max_per_inviter'] ?? 0)),
                    'require_bind_before' => !isset($t['require_bind_before']) || !empty($t['require_bind_before']) ? 1 : 0,
                    'order_types' => $this->normalizeOrderTypes($t),
                ];
            default:
                return [];
        }
    }
}
