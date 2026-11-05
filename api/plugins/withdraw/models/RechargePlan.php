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
 * 充值档位（挂在某个充值商品下）
 *
 * ⚠ 到账资产不在本表 —— 一律看所属商品的 RechargeGoods::asset_type。
 *   一个商品一个钱包，见 RechargeGoods 的注释。
 *
 * @property int    $id
 * @property int    $mall_id
 * @property int    $goods_id
 * @property string $title
 * @property string $pay_amount
 * @property string $gain_amount
 * @property string $gift_amount
 * @property string $user_types
 * @property int    $day_limit
 * @property int    $sort
 * @property int    $status
 * @property string $remark
 */
class RechargePlan extends BaseActiveRecord
{
    const STATUS_OFF = 0;
    const STATUS_ON  = 1;

    public static function tableName()
    {
        return '{{%plugin_withdraw_recharge_plan}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'goods_id', 'title', 'pay_amount', 'gain_amount'], 'required'],
            [['mall_id', 'goods_id', 'day_limit', 'total_limit', 'sort', 'status'], 'integer'],
            // 负数无意义,统一按 0(不限)处理,别让前端传 -1 变成"永远买不了"
            [['day_limit', 'total_limit'], 'integer', 'min' => 0],
            [['day_limit', 'total_limit'], 'default', 'value' => 0],
            [['pay_amount', 'gain_amount', 'gift_amount'], 'number', 'min' => 0],
            [['title'], 'string', 'max' => 64],
            [['user_types'], 'string', 'max' => 255],
            [['remark'], 'string', 'max' => 255],
            [['gift_amount'], 'default', 'value' => 0],
            [['user_types'], 'default', 'value' => Channel::USER_TYPE_USER],
            [['status'], 'default', 'value' => self::STATUS_ON],
        ];
    }

    /** 到账总量 = 基础 + 赠送 */
    public function totalGain(): string
    {
        return bcadd((string)$this->gain_amount, (string)$this->gift_amount, 2);
    }


    /** 该档位是否对这个用户类型开放 */
    public function allowsUserType(string $userType): bool
    {
        $types = array_filter(array_map('trim', explode(',', (string)$this->user_types)));
        return !$types || in_array($userType, $types, true);
    }
}
