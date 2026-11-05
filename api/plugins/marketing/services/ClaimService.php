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

use app\plugins\marketing\models\MarketingCampaign;
use app\plugins\marketing\models\MarketingGrantLog;
use app\plugins\marketing\models\MarketingReward;

/**
 * 领取型活动(C端领券中心)
 *
 * 用户主动领取:活动带人群定向 + 每人限领 + 预算。领取仍复用核心 CouponService 发券,
 * 本服务只做"能不能领 + 领了记账"的编排(复用 GrantService)。
 */
class ClaimService
{
    /**
     * 领券中心列表:当前商场进行中的领取型活动 + 每张券的可领状态。
     */
    public static function listForUser(int $mallId, int $userId): array
    {
        $campaigns = MarketingCampaign::find()
            ->where(['mall_id' => $mallId, 'type' => MarketingCampaign::TYPE_CLAIM, 'is_delete' => 0])
            ->orderBy('id DESC')->all();

        $out = [];
        foreach ($campaigns as $c) {
            /** @var MarketingCampaign $c */
            if (!$c->isGrantable()) {
                continue;
            }
            $state = self::claimState($c, $userId);

            $rewards = [];
            $coupons = []; // 兼容已发布的旧版 C 端,只认券
            foreach (RewardService::groupedByTier((int)$c->id)[0] ?? [] as $r) {
                if (!$r['valid']) {
                    continue;
                }
                $rewards[] = $r;
                if ($r['reward_type'] === MarketingReward::TYPE_COUPON) {
                    $coupons[] = ['name' => $r['name'], 'desc' => $r['desc'], 'num' => $r['num']];
                }
            }
            if (!$rewards) {
                continue;
            }

            $out[] = [
                'campaign_id' => $c->id,
                'name' => $c->name,
                'rewards' => $rewards,
                'coupons' => $coupons,
                'can_claim' => $state['can'],
                'claim_msg' => $state['msg'],
                'end_at' => $c->end_at ? date('Y-m-d', $c->end_at) : '',
            ];
        }
        return ['code' => 0, 'msg' => '', 'data' => ['list' => $out]];
    }

    /**
     * 领取。返回 ['code'=>0/1,'msg'=>...]
     */
    public static function claim(int $mallId, int $userId, int $campaignId): array
    {
        $c = MarketingCampaign::findOne([
            'id' => $campaignId, 'mall_id' => $mallId,
            'type' => MarketingCampaign::TYPE_CLAIM, 'is_delete' => 0,
        ]);
        if (!$c || !$c->isGrantable()) {
            return ['code' => 1, 'msg' => '活动不存在或已结束'];
        }
        $state = self::claimState($c, $userId);
        if (!$state['can']) {
            return ['code' => 1, 'msg' => $state['msg']];
        }

        // 幂等键:按"第几次领取"分槽,配合每人限领
        $claimedTimes = self::claimedTimes($campaignId, $userId);
        $granted = GrantService::grant($campaignId, $userId, 'claim', 'claim:' . ($claimedTimes + 1));
        if ($granted <= 0) {
            return ['code' => 1, 'msg' => '领取失败,可能已被领完'];
        }
        return ['code' => 0, 'msg' => '领取成功', 'data' => ['granted' => $granted]];
    }

    /**
     * 用户对某活动的可领状态(人群 + 每人限领 + 预算)。
     */
    private static function claimState(MarketingCampaign $c, int $userId): array
    {
        if (!AudienceService::matchUser((int)$c->mall_id, $userId, $c->getAudience())) {
            return ['can' => false, 'msg' => '不符合领取条件'];
        }
        if ((int)$c->per_user_limit > 0 && self::claimedTimes((int)$c->id, $userId) >= (int)$c->per_user_limit) {
            return ['can' => false, 'msg' => '已领取'];
        }
        if ($c->isBudgetExhausted()) {
            return ['can' => false, 'msg' => '已领完'];
        }
        return ['can' => true, 'msg' => '立即领取'];
    }

    /**
     * 用户已领取该活动的次数(按不同 dedup_key 计一次领取)。
     */
    private static function claimedTimes(int $campaignId, int $userId): int
    {
        return (int)MarketingGrantLog::find()
            ->where(['campaign_id' => $campaignId, 'user_id' => $userId, 'is_delete' => 0])
            ->count('DISTINCT dedup_key');
    }
}
