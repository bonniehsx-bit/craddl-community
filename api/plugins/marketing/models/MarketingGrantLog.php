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

namespace app\plugins\marketing\models;

use app\models\BaseActiveRecord;
use app\models\User;
use app\models\UserCoupon;

/**
 * 营销活动-发放流水(去重锚点 + 回收锚点 + 统计)
 *
 * 一行 = 发出的一份奖励:一张券 / 一笔积分 / 一笔余额。
 * 唯一索引 (campaign_id,user_id,dedup_key,reward_id,seq) 兜底防重复发放;
 * 回收时 status 翻为 REVERTED 并给 dedup_key 追加后缀,释放占位,之后重新达标可再次发放。
 *
 * @property int $id
 * @property int $mall_id
 * @property int $campaign_id
 * @property int $coupon_id
 * @property int $user_id
 * @property int $user_coupon_id
 * @property string $source_event
 * @property string $dedup_key
 * @property float $face_value
 * @property int $granted_at
 * @property string $reward_type
 * @property int $reward_id
 * @property int $seq
 * @property int $tier_no
 * @property float $amount
 * @property int $ref_id
 * @property int $status
 * @property int $reverted_at
 * @property int $is_delete
 *
 * @property User $user
 * @property UserCoupon $userCoupon
 */
class MarketingGrantLog extends BaseActiveRecord
{
    const STATUS_GRANTED = 1;
    const STATUS_REVERTED = 2;

    public static function tableName()
    {
        return '{{%plugin_marketing_grant_log}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'campaign_id', 'user_id'], 'required'],
            [['mall_id', 'campaign_id', 'coupon_id', 'user_id', 'user_coupon_id', 'granted_at', 'reward_id', 'seq', 'tier_no', 'ref_id', 'status', 'reverted_at', 'is_delete'], 'integer'],
            [['face_value', 'amount'], 'number'],
            [['source_event', 'dedup_key'], 'string', 'max' => 80],
            [['reward_type'], 'string', 'max' => 20],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getUserCoupon()
    {
        return $this->hasOne(UserCoupon::class, ['id' => 'user_coupon_id']);
    }
}
