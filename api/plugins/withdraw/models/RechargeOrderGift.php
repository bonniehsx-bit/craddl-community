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

namespace app\plugins\withdraw\models;

use app\models\BaseActiveRecord;

/**
 * 充值单的分期发放计划(下单快照 + 发放状态机)。
 *
 * 所有业务字段都是**下单那一刻的快照**,连 plan_grant_at(计划发放时间)都算死了。
 * 不快照的话,运营改了分期方案,已充值用户的赠品会跟着变 —— 那是事故。
 *
 * status 这一位就是发放的幂等锁:发放前必须 UPDATE ... WHERE status=0 抢占。
 *
 * @property int    $id
 * @property int    $mall_id
 * @property int    $recharge_order_id
 * @property int    $user_id
 * @property int    $period
 * @property string $gift_type
 * @property int    $coupon_id
 * @property string $coupon_name
 * @property int    $num
 * @property string $asset_type
 * @property string $amount
 * @property int    $plan_grant_at
 * @property int    $status
 * @property int    $granted_at
 * @property int    $attempts
 * @property string $fail_reason
 * @property string $user_coupon_ids
 * @property int    $revoked_at
 */
class RechargeOrderGift extends BaseActiveRecord
{
    const STATUS_PENDING = 0;
    const STATUS_GRANTED = 1;
    const STATUS_FAILED  = 2;
    const STATUS_VOID    = 3;

    public static function tableName()
    {
        return '{{%plugin_withdraw_recharge_order_gift}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'recharge_order_id', 'user_id'], 'required'],
            [['mall_id', 'recharge_order_id', 'user_id', 'period', 'coupon_id', 'num',
              'plan_grant_at', 'status', 'granted_at', 'attempts', 'revoked_at'], 'integer'],
            [['amount'], 'number', 'min' => 0],
            [['gift_type'], 'string', 'max' => 16],
            [['asset_type'], 'string', 'max' => 32],
            [['coupon_name'], 'string', 'max' => 128],
            [['fail_reason', 'user_coupon_ids'], 'string', 'max' => 255],
            [['status'], 'default', 'value' => self::STATUS_PENDING],
            [['num'], 'default', 'value' => 1],
            [['amount'], 'default', 'value' => 0],
        ];
    }

    public function statusText(): string
    {
        switch ((int) $this->status) {
            case self::STATUS_GRANTED: return '已到账';
            case self::STATUS_FAILED:  return '发放失败';
            case self::STATUS_VOID:    return '已作废';
            default:                   return '待到账';
        }
    }

    /** 给 C 端展示:送了什么 */
    public function contentText(): string
    {
        if ($this->gift_type === RechargePlanGift::TYPE_ASSET) {
            return (string) $this->amount;
        }
        return ($this->coupon_name ?: '优惠券') . ' ×' . $this->num;
    }
}
