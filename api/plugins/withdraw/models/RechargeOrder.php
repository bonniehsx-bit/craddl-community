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
use app\plugins\withdraw\services\AssetService;

/**
 * 充值单 —— 一次充值的实例，1:1 对应一个核心订单。
 *
 * ⛔ status 是**发放的幂等锁**，不是普通展示字段。
 *   到账发放前必须走 `updateAll(..., ['id'=>x,'status'=>PENDING])` 抢占，
 *   靠 if 判断 status 再 save 是挡不住并发的（支付回调 + 补结算 cron 会同时来）。
 *
 * @property int    $id
 * @property int    $mall_id
 * @property int    $user_id
 * @property int    $plan_id
 * @property int    $order_id
 * @property string $order_no
 * @property string $asset_type
 * @property string $pay_amount
 * @property string $gain_amount
 * @property string $gift_amount
 * @property string $plan_title
 * @property int    $status
 * @property int    $credited_at
 */
class RechargeOrder extends BaseActiveRecord
{
    /** 待支付 */
    const STATUS_PENDING  = 0;
    /** 已到账 */
    const STATUS_CREDITED = 1;

    public static function tableName()
    {
        return '{{%plugin_withdraw_recharge_order}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'user_id'], 'required'],
            [['mall_id', 'user_id', 'plan_id', 'order_id', 'status', 'credited_at'], 'integer'],
            [['pay_amount', 'gain_amount', 'gift_amount'], 'number', 'min' => 0],
            [['asset_type', 'plan_title'], 'string', 'max' => 64],
            [['order_no'], 'string', 'max' => 64],
            [['status'], 'default', 'value' => self::STATUS_PENDING],
        ];
    }

    public function totalGain(): string
    {
        return bcadd((string)$this->gain_amount, (string)$this->gift_amount, 2);
    }

    public function assetLabel(): string
    {
        return AssetService::label((string)$this->asset_type);
    }

    public function statusText(): string
    {
        return (int)$this->status === self::STATUS_CREDITED ? '已到账' : '待支付';
    }
}
