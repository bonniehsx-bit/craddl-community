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

use app\models\Coupon;
use app\plugins\marketing\models\MarketingReward;
use app\services\CouponService;

/**
 * 奖励项读写:按活动读取(带展示信息)/ 全量替换保存
 */
class RewardService
{
    /**
     * 读取活动全部奖励项,按档位分组。
     * @return array<int, array[]> [tier_no => [ {id,tier_no,reward_type,coupon_id,amount,num,name,desc,valid}, ... ]]
     */
    public static function groupedByTier(int $campaignId): array
    {
        $rewards = MarketingReward::find()
            ->where(['campaign_id' => $campaignId, 'is_delete' => 0])
            ->orderBy(['tier_no' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $couponIds = [];
        foreach ($rewards as $r) {
            if ($r->reward_type === MarketingReward::TYPE_COUPON && $r->coupon_id > 0) {
                $couponIds[] = (int)$r->coupon_id;
            }
        }
        $coupons = $couponIds
            ? Coupon::find()->where(['id' => array_unique($couponIds), 'is_delete' => 0])->indexBy('id')->all()
            : [];

        $out = [];
        foreach ($rewards as $r) {
            /** @var MarketingReward $r */
            $row = [
                'id' => (int)$r->id,
                'tier_no' => (int)$r->tier_no,
                'reward_type' => $r->reward_type,
                'coupon_id' => (int)$r->coupon_id,
                'amount' => (float)$r->amount,
                'num' => (int)$r->num,
                'name' => '',
                'desc' => '',
                'valid' => true,
            ];
            if ($r->reward_type === MarketingReward::TYPE_COUPON) {
                $coupon = $coupons[$r->coupon_id] ?? null;
                $row['name'] = $coupon ? $coupon->name : '(券已删除)';
                $row['desc'] = $coupon ? CouponService::getCouponDescription($coupon->toArray()) : '';
                $row['valid'] = $coupon && $coupon->is_enabled;
            }
            $out[(int)$r->tier_no][] = $row;
        }
        return $out;
    }

    /**
     * 全量替换活动奖励项。$rewards 已由表单规整:[{tier_no,reward_type,coupon_id,amount,num}]
     */
    public static function replace(int $mallId, int $campaignId, array $rewards): void
    {
        MarketingReward::updateAll(['is_delete' => 1], ['campaign_id' => $campaignId, 'is_delete' => 0]);
        foreach ($rewards as $r) {
            $m = new MarketingReward();
            $m->mall_id = $mallId;
            $m->campaign_id = $campaignId;
            $m->tier_no = (int)$r['tier_no'];
            $m->reward_type = $r['reward_type'];
            $m->coupon_id = (int)$r['coupon_id'];
            $m->amount = (float)$r['amount'];
            $m->num = (int)$r['num'];
            if (!$m->save()) {
                throw new \Exception('奖励项保存失败: ' . current($m->getFirstErrors()));
            }
        }
    }
}
