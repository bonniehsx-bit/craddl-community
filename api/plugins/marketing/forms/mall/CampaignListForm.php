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
use app\models\Goods;
use app\plugins\marketing\models\MarketingCampaign;
use app\plugins\marketing\models\MarketingReward;
use app\plugins\marketing\models\MarketingTrigger;
use app\plugins\marketing\services\CumulativeService;
use app\plugins\marketing\services\RewardService;
use app\services\CouponService;

/**
 * 营销活动列表 / 详情 / 选券
 */
class CampaignListForm extends BaseModel
{
    public $page;
    public $limit;
    public $id;
    public $type;
    public $status;
    public $keyword;

    public function rules()
    {
        return [
            [['page', 'limit', 'id', 'type', 'status'], 'integer'],
            [['keyword'], 'string'],
        ];
    }

    public static $typeText = [
        MarketingCampaign::TYPE_TRIGGER => '触发型',
        MarketingCampaign::TYPE_BATCH => '群发型',
        MarketingCampaign::TYPE_CLAIM => '领取型',
    ];
    public static $statusText = [
        MarketingCampaign::STATUS_DRAFT => '草稿',
        MarketingCampaign::STATUS_RUNNING => '进行中',
        MarketingCampaign::STATUS_PAUSED => '已暂停',
        MarketingCampaign::STATUS_ENDED => '已结束',
    ];

    public function search()
    {
        $query = MarketingCampaign::find()
            ->where(['mall_id' => $this->mall_id, 'is_delete' => 0]);
        if ($this->type) {
            $query->andWhere(['type' => (int)$this->type]);
        }
        if ($this->status !== null && $this->status !== '') {
            $query->andWhere(['status' => (int)$this->status]);
        }
        if ($this->keyword) {
            $query->andWhere(['like', 'name', $this->keyword]);
        }

        $pagination = null;
        $list = $query->orderBy('id DESC')->page($pagination, $this->limit, $this->page)->all();

        // 领取/核销统计(轻量:按活动聚合发放流水)
        $rows = [];
        /** @var MarketingCampaign $c */
        foreach ($list as $c) {
            $rows[] = [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type,
                'type_text' => self::$typeText[$c->type] ?? '',
                'status' => $c->status,
                'status_text' => self::$statusText[$c->status] ?? '',
                'budget_type' => $c->budget_type,
                'budget_total' => (float)$c->budget_total,
                'grant_count' => (int)$c->grant_count,
                'grant_amount' => (float)$c->grant_amount,
                'budget_integral' => (float)$c->budget_integral,
                'budget_balance' => (float)$c->budget_balance,
                'grant_integral' => (float)$c->grant_integral,
                'grant_balance' => (float)$c->grant_balance,
                'start_at' => $c->start_at ? date('Y-m-d H:i', $c->start_at) : '不限',
                'end_at' => $c->end_at ? date('Y-m-d H:i', $c->end_at) : '不限',
                'created_time' => $c->created_time,
            ];
        }
        return ['code' => ApiCode::CODE_SUCCESS, 'data' => ['list' => $rows, 'pagination' => $pagination]];
    }

    public function detail()
    {
        $c = MarketingCampaign::findOne(['id' => (int)$this->id, 'mall_id' => $this->mall_id, 'is_delete' => 0]);
        if (!$c) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '活动不存在'];
        }

        $grouped = RewardService::groupedByTier((int)$c->id);
        $trigger = MarketingTrigger::findOne(['campaign_id' => $c->id, 'is_delete' => 0]);
        $isInvite = $trigger && $trigger->event === MarketingTrigger::EVENT_INVITE;
        $rewards = $isInvite ? ($grouped[MarketingTrigger::INVITE_TIER_INVITER] ?? []) : ($grouped[0] ?? []);
        $inviteeRewards = $isInvite ? ($grouped[MarketingTrigger::INVITE_TIER_INVITEE] ?? []) : [];
        $coupons = []; // 旧版后台兼容
        foreach ($rewards as $r) {
            if ($r['reward_type'] === MarketingReward::TYPE_COUPON) {
                $coupons[] = ['coupon_id' => $r['coupon_id'], 'num' => $r['num'], 'name' => $r['name'], 'desc' => $r['desc']];
            }
        }

        // 触发规则;累计奖把每档奖励挂进 tiers;指定商品回显商品信息
        $triggerData = null;
        if ($trigger) {
            $triggerData = array_merge(['event' => $trigger->event], $trigger->getCondition());
            $goodsIds = array_map('intval', (array)($triggerData['goods_ids'] ?? []));
            $triggerData['goods_list'] = $goodsIds
                ? Goods::find()->select(['id', 'name', 'cover_pic'])->where(['id' => $goodsIds, 'mall_id' => $this->mall_id])->asArray()->all()
                : [];
            if ($trigger->event === MarketingTrigger::EVENT_ORDER_CUMULATIVE) {
                $cond = CumulativeService::normalizeCondition($trigger->getCondition());
                $triggerData['tiers'] = [];
                foreach ($cond['tiers'] ?? [] as $tier) {
                    $triggerData['tiers'][] = [
                        'no' => $tier['no'],
                        'threshold' => $tier['threshold'],
                        'rewards' => $grouped[$tier['no']] ?? [],
                    ];
                }
            }
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'data' => [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type,
                'status' => $c->status,
                'start_at' => $c->start_at,
                'end_at' => $c->end_at,
                'budget_type' => $c->budget_type,
                'budget_total' => (float)$c->budget_total,
                'budget_integral' => (float)$c->budget_integral,
                'budget_balance' => (float)$c->budget_balance,
                'per_user_limit' => (int)$c->per_user_limit,
                'remark' => $c->remark,
                'audience' => $c->getAudience(),
                'rewards' => $rewards,
                'invitee_rewards' => $inviteeRewards,
                'coupons' => $coupons,
                'trigger' => $triggerData,
            ],
        ];
    }

    /** 可选平台券(mch_id=0 平台券,已启用) */
    public function couponOptions()
    {
        $query = Coupon::find()
            ->where(['mall_id' => $this->mall_id, 'mch_id' => 0, 'is_delete' => 0, 'is_enabled' => 1]);
        if ($this->keyword) {
            $query->andWhere(['like', 'name', $this->keyword]);
        }
        $list = $query->orderBy('id DESC')->limit(200)->all();
        $rows = [];
        /** @var Coupon $c */
        foreach ($list as $c) {
            $rows[] = [
                'coupon_id' => $c->id,
                'name' => $c->name,
                'desc' => CouponService::getCouponDescription($c->toArray()),
            ];
        }
        return ['code' => ApiCode::CODE_SUCCESS, 'data' => ['list' => $rows]];
    }
}
