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
 * 充值档位的分期赠品配置(展开后的明细,一期一行)。
 *
 * 后台按「按月 × 3 期 × 每期 1 张」生成,落库就是 3 行 —— 规则只活在配置界面。
 * 这样发放逻辑只是"照表发",而且运营改规则不会影响已经充值的用户。
 *
 * @property int    $id
 * @property int    $mall_id
 * @property int    $plan_id
 * @property int    $period      第几期(1 起)
 * @property string $gift_type   coupon | asset
 * @property int    $coupon_id
 * @property int    $num
 * @property string $asset_type
 * @property string $amount
 * @property string $delay_mode  immediate | day | week | month
 * @property int    $delay_value
 * @property int    $sort
 */
class RechargePlanGift extends BaseActiveRecord
{
    const TYPE_COUPON = 'coupon';
    const TYPE_ASSET  = 'asset';

    const MODE_IMMEDIATE = 'immediate';
    const MODE_DAY       = 'day';
    const MODE_WEEK      = 'week';
    const MODE_MONTH     = 'month';

    public static function tableName()
    {
        return '{{%plugin_withdraw_recharge_plan_gift}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'plan_id'], 'required'],
            [['mall_id', 'plan_id', 'period', 'coupon_id', 'num', 'delay_value', 'sort'], 'integer'],
            [['period', 'num'], 'integer', 'min' => 1],
            [['delay_value'], 'integer', 'min' => 0],
            [['amount'], 'number', 'min' => 0],
            [['gift_type'], 'in', 'range' => [self::TYPE_COUPON, self::TYPE_ASSET]],
            [['delay_mode'], 'in', 'range' => [self::MODE_IMMEDIATE, self::MODE_DAY, self::MODE_WEEK, self::MODE_MONTH]],
            [['asset_type'], 'string', 'max' => 32],
            [['gift_type'], 'default', 'value' => self::TYPE_COUPON],
            [['delay_mode'], 'default', 'value' => self::MODE_IMMEDIATE],
            [['num'], 'default', 'value' => 1],
            [['period'], 'default', 'value' => 1],
            [['amount'], 'default', 'value' => 0],
        ];
    }

    /** 给 C 端/后台展示的到账时机文案 */
    public function timingText(): string
    {
        switch ($this->delay_mode) {
            case self::MODE_DAY:   return "{$this->delay_value} 天后到账";
            case self::MODE_WEEK:  return "{$this->delay_value} 周后到账";
            case self::MODE_MONTH: return "{$this->delay_value} 个月后到账";
            default:               return '充值立返';
        }
    }
}
