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
use app\models\Order;
use app\models\UserCoupon;
use app\plugins\marketing\models\MarketingCampaign;
use app\plugins\marketing\models\MarketingGrantLog;
use app\plugins\marketing\models\MarketingReward;
use app\services\EffectiveSpend;
use yii\db\Query;

/**
 * 营销效果看板(二期)
 *
 * 漏斗:发放张数 → 到账人数 → 核销张数 → 带动GMV → ROI。
 * 数据全部从既有字段回捞,无需额外埋点:
 *   - 发放/面额     : plugin_marketing_grant_log
 *   - 核销          : user_coupon.status = 1(已核销)
 *   - 带动GMV/成本  : order.user_coupon_id 关联 + order.coupon_cut_price(实际抵扣=券成本口径)
 *                     GMV 按实收:付过款的单(已支付、部分退款),实付减掉已退回的钱;全退了的不算
 */
class StatForm extends BaseModel
{
    public $campaign_id; // 可选:仅统计某活动

    public function rules()
    {
        return [[['campaign_id'], 'integer']];
    }

    public function overview()
    {
        $campaignQuery = MarketingCampaign::find()
            ->where(['mall_id' => $this->mall_id, 'is_delete' => 0]);
        if ($this->campaign_id) {
            $campaignQuery->andWhere(['id' => (int)$this->campaign_id]);
        }
        /** @var MarketingCampaign[] $campaigns */
        $campaigns = $campaignQuery->orderBy('id DESC')->all();
        if (!$campaigns) {
            return ['code' => ApiCode::CODE_SUCCESS, 'data' => ['rows' => [], 'summary' => $this->emptySummary()]];
        }
        $campaignIds = array_map(function ($c) { return (int)$c->id; }, $campaigns);

        $grantTbl = MarketingGrantLog::tableName();
        $ucTbl = UserCoupon::tableName();
        $orderTbl = Order::tableName();
        $all = ['g.mall_id' => $this->mall_id, 'g.is_delete' => 0, 'g.campaign_id' => $campaignIds, 'g.status' => MarketingGrantLog::STATUS_GRANTED];
        // 券相关漏斗只统计券奖励(已回收作废的不计)
        $base = array_merge($all, ['g.reward_type' => MarketingReward::TYPE_COUPON]);

        // 0. 积分/余额发放合计(扣除回收)+ 获奖人数
        $assets = (new Query())->from(['g' => $grantTbl])
            ->select([
                'g.campaign_id',
                'integral_sum' => "SUM(CASE WHEN g.reward_type = 'integral' THEN g.amount ELSE 0 END)",
                'balance_sum' => "SUM(CASE WHEN g.reward_type = 'balance' THEN g.amount ELSE 0 END)",
                'user_count' => 'COUNT(DISTINCT g.user_id)',
            ])->where($all)->groupBy('g.campaign_id')->indexBy('campaign_id')->all();

        // 1. 发放:张数 / ∑面额
        $grants = (new Query())->from(['g' => $grantTbl])
            ->select([
                'g.campaign_id',
                'grant_count' => 'COUNT(*)',
                'user_count' => 'COUNT(DISTINCT g.user_id)',
                'face_sum' => 'SUM(g.face_value)',
            ])->where($base)->groupBy('g.campaign_id')->indexBy('campaign_id')->all();

        // 2. 核销张数(user_coupon.status=已核销)
        $redeemed = (new Query())->from(['g' => $grantTbl])
            ->innerJoin(['uc' => $ucTbl], 'uc.id = g.user_coupon_id')
            ->select(['g.campaign_id', 'redeem_count' => 'COUNT(*)'])
            ->where($base)->andWhere(['uc.status' => UserCoupon::STATUS_USED])
            ->groupBy('g.campaign_id')->indexBy('campaign_id')->all();

        // 3. 带动GMV / 券成本(实际抵扣)
        $gmv = (new Query())->from(['g' => $grantTbl])
            ->innerJoin(['o' => $orderTbl], 'o.user_coupon_id = g.user_coupon_id')
            ->select([
                'g.campaign_id',
                // 原来只认「已支付」:退过一件的单(支付状态变成部分退款)整张不算,带动的成交和订单数都少了
                'gmv' => 'SUM(GREATEST(o.pay_price - IFNULL(o.refund_price, 0), 0))',
                'cost' => 'SUM(o.coupon_cut_price)',
                'order_count' => 'COUNT(DISTINCT o.id)',
            ])->where($base)->andWhere(['o.pay_status' => EffectiveSpend::PAID_STATUSES, 'o.is_delete' => 0])
            ->groupBy('g.campaign_id')->indexBy('campaign_id')->all();

        $rows = [];
        $sum = $this->emptySummary();
        foreach ($campaigns as $c) {
            $id = (int)$c->id;
            $grantCount = (int)($grants[$id]['grant_count'] ?? 0);
            $userCount = (int)($assets[$id]['user_count'] ?? 0);
            $integralSum = round((float)($assets[$id]['integral_sum'] ?? 0), 2);
            $balanceSum = round((float)($assets[$id]['balance_sum'] ?? 0), 2);
            $redeemCount = (int)($redeemed[$id]['redeem_count'] ?? 0);
            $campGmv = (float)($gmv[$id]['gmv'] ?? 0);
            $campCost = (float)($gmv[$id]['cost'] ?? 0);
            $orderCount = (int)($gmv[$id]['order_count'] ?? 0);

            $rows[] = [
                'campaign_id' => $id,
                'name' => $c->name,
                'status_text' => CampaignListForm::$statusText[$c->status] ?? '',
                'grant_count' => $grantCount,
                'user_count' => $userCount,
                'integral_sum' => $integralSum,
                'balance_sum' => $balanceSum,
                'redeem_count' => $redeemCount,
                'redeem_rate' => $grantCount > 0 ? round($redeemCount / $grantCount * 100, 1) : 0,
                'order_count' => $orderCount,
                'gmv' => round($campGmv, 2),
                'cost' => round($campCost, 2),
                'roi' => $campCost > 0 ? round($campGmv / $campCost, 2) : 0,
            ];

            $sum['grant_count'] += $grantCount;
            $sum['redeem_count'] += $redeemCount;
            $sum['integral_sum'] += $integralSum;
            $sum['balance_sum'] += $balanceSum;
            $sum['gmv'] += $campGmv;
            $sum['cost'] += $campCost;
        }
        $sum['integral_sum'] = round($sum['integral_sum'], 2);
        $sum['balance_sum'] = round($sum['balance_sum'], 2);
        $sum['gmv'] = round($sum['gmv'], 2);
        $sum['cost'] = round($sum['cost'], 2);
        $sum['redeem_rate'] = $sum['grant_count'] > 0 ? round($sum['redeem_count'] / $sum['grant_count'] * 100, 1) : 0;
        $sum['roi'] = $sum['cost'] > 0 ? round($sum['gmv'] / $sum['cost'], 2) : 0;

        return ['code' => ApiCode::CODE_SUCCESS, 'data' => ['rows' => $rows, 'summary' => $sum]];
    }

    private function emptySummary(): array
    {
        return ['grant_count' => 0, 'redeem_count' => 0, 'redeem_rate' => 0, 'integral_sum' => 0, 'balance_sum' => 0, 'gmv' => 0, 'cost' => 0, 'roi' => 0];
    }
}
