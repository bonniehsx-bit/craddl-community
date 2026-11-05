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
use app\models\User;
use app\models\UserCoupon;
use app\plugins\marketing\models\MarketingCampaign;
use app\plugins\marketing\models\MarketingGrantLog;
use app\plugins\marketing\models\MarketingReward;

/**
 * 营销发放流水列表
 */
class GrantLogForm extends BaseModel
{
    public $page;
    public $limit;
    public $campaign_id;
    public $keyword;
    public $source_event;

    public function rules()
    {
        return [
            [['page', 'limit', 'campaign_id'], 'integer'],
            [['keyword', 'source_event'], 'string'],
        ];
    }

    private static $ucStatusText = [
        UserCoupon::STATUS_UNUSE => '未使用',
        UserCoupon::STATUS_USED => '已核销',
        UserCoupon::STATUS_EXPIRE => '已过期',
    ];

    public function search()
    {
        $query = MarketingGrantLog::find()->alias('g')
            ->where(['g.mall_id' => $this->mall_id, 'g.is_delete' => 0]);
        if ($this->campaign_id) {
            $query->andWhere(['g.campaign_id' => (int)$this->campaign_id]);
        }
        if ($this->source_event) {
            $query->andWhere(['g.source_event' => $this->source_event]);
        }
        if ($this->keyword) {
            $query->leftJoin(['u' => User::tableName()], 'u.id = g.user_id')
                ->andWhere(['or', ['g.user_id' => $this->keyword], ['like', 'u.nickname', $this->keyword], ['like', 'u.mobile', $this->keyword]]);
        }

        $pagination = null;
        $list = $query->orderBy('g.id DESC')->page($pagination, $this->limit, $this->page)->all();

        // 批量预取 用户 / 活动 / 用户券状态
        $userIds = $campaignIds = $ucIds = [];
        foreach ($list as $g) {
            $userIds[] = $g->user_id;
            $campaignIds[] = $g->campaign_id;
            $ucIds[] = $g->user_coupon_id;
        }
        $users = User::find()->where(['id' => $userIds])->indexBy('id')->all();
        $campaigns = MarketingCampaign::find()->where(['id' => $campaignIds])->indexBy('id')->all();
        $ucs = UserCoupon::find()->where(['id' => $ucIds])->indexBy('id')->all();

        $rows = [];
        /** @var MarketingGrantLog $g */
        foreach ($list as $g) {
            $uc = $ucs[$g->user_coupon_id] ?? null;
            $isCoupon = $g->reward_type === MarketingReward::TYPE_COUPON;
            $reverted = (int)$g->status === MarketingGrantLog::STATUS_REVERTED;
            if ($isCoupon) {
                $statusText = $reverted ? '已作废' : ($uc ? (self::$ucStatusText[$uc->status] ?? '') : '券已删除');
            } else {
                $statusText = $reverted ? '已回收' : '已到账';
            }
            $rows[] = [
                'reward_type' => $g->reward_type,
                'amount' => (float)$g->amount,
                'status' => (int)$g->status,
                'status_text' => $statusText,
                'tier_no' => (int)$g->tier_no,
                'reverted_time' => $g->reverted_at ? date('Y-m-d H:i:s', $g->reverted_at) : '',
                'id' => $g->id,
                'user_id' => $g->user_id,
                'nickname' => $users[$g->user_id]->nickname ?? '用户已注销',
                'mobile' => $users[$g->user_id]->mobile ?? '',
                'campaign_name' => $campaigns[$g->campaign_id]->name ?? '',
                'source_event' => $g->source_event,
                'source_text' => self::eventText($g->source_event),
                'face_value' => (float)$g->face_value,
                'uc_status' => $uc ? $uc->status : null,
                'uc_status_text' => $uc ? (self::$ucStatusText[$uc->status] ?? '') : '券已删除',
                'granted_time' => $g->granted_at ? date('Y-m-d H:i:s', $g->granted_at) : '',
            ];
        }
        return ['code' => ApiCode::CODE_SUCCESS, 'data' => ['list' => $rows, 'pagination' => $pagination]];
    }

    public static function eventText(string $event): string
    {
        $map = [
            'register' => '新人礼包', 'order_paid' => '订单返券', 'level_up' => '升级奖励',
            'birthday' => '生日券', 'inactive' => '沉睡召回', 'batch' => '定时群发', 'manual' => '手动发放',
            'order_cumulative' => '累计下单', 'claim' => '领券中心',
            'order_repeat' => '每满循环', 'new_customer_rebuy' => '新客复购', 'invite_first_order' => '邀请首单',
        ];
        return $map[$event] ?? $event;
    }
}
