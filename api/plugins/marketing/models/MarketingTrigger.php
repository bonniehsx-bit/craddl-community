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

/**
 * 营销活动-触发规则(触发型活动:一条活动一条触发规则)
 *
 * @property int $id
 * @property int $mall_id
 * @property int $campaign_id
 * @property string $event
 * @property string $condition_json
 * @property int $is_delete
 */
class MarketingTrigger extends BaseActiveRecord
{
    const EVENT_REGISTER = 'register';    // 新用户注册(cron 扫近期注册)
    const EVENT_ORDER_PAID = 'order_paid';// 订单支付(首单/满额/复购)
    const EVENT_LEVEL_UP = 'level_up';    // 会员升级
    const EVENT_BIRTHDAY = 'birthday';    // 生日(cron)
    const EVENT_INACTIVE = 'inactive';    // 沉睡召回(cron)
    const EVENT_ORDER_CUMULATIVE = 'order_cumulative'; // 累计下单阶梯奖(订单结算时计数)
    const EVENT_ORDER_REPEAT = 'order_repeat';         // 每满 N 循环奖(订单结算时计数)
    const EVENT_NEW_REBUY = 'new_customer_rebuy';      // 新客 N 天内复购奖
    const EVENT_INVITE = 'invite_first_order';         // 邀请首单有礼

    // 累计/循环 统计指标
    const METRIC_COUNT = 'order_count';          // 有效订单数
    const METRIC_AMOUNT = 'order_amount';        // 有效实付金额
    const METRIC_GOODS_QTY = 'goods_qty';        // 指定商品件数
    const METRIC_GOODS_AMOUNT = 'goods_amount';  // 指定商品实付金额

    // 邀请首单:奖励项档位约定(复用 tier_no 区分奖励对象)
    const INVITE_TIER_INVITER = 1; // 邀请人
    const INVITE_TIER_INVITEE = 2; // 被邀请人

    // order_cumulative 统计周期
    const PERIOD_CAMPAIGN = 'campaign'; // 活动期内
    const PERIOD_MONTH = 'month';       // 自然月
    const PERIOD_WEEK = 'week';         // 自然周
    const PERIOD_LIFETIME = 'lifetime'; // 终身累计(含活动开始前的历史订单)

    // order_paid 子类型
    const ORDER_ANY = 'any';     // 任意支付
    const ORDER_FIRST = 'first'; // 仅首单
    const ORDER_AMOUNT = 'amount';// 满额(实付>=min_amount)

    // 生日口径
    const BIRTHDAY_DAY = 'day';     // 生日当天
    const BIRTHDAY_MONTH = 'month'; // 生日当月

    public static function events(): array
    {
        return [
            self::EVENT_REGISTER, self::EVENT_ORDER_PAID, self::EVENT_LEVEL_UP,
            self::EVENT_BIRTHDAY, self::EVENT_INACTIVE, self::EVENT_ORDER_CUMULATIVE,
            self::EVENT_ORDER_REPEAT, self::EVENT_NEW_REBUY, self::EVENT_INVITE,
        ];
    }

    public static function metrics(): array
    {
        return [self::METRIC_COUNT, self::METRIC_AMOUNT, self::METRIC_GOODS_QTY, self::METRIC_GOODS_AMOUNT];
    }

    /** 按件数/单数计(整数)的指标;其余按金额(两位小数) */
    public static function isIntMetric(string $metric): bool
    {
        return in_array($metric, [self::METRIC_COUNT, self::METRIC_GOODS_QTY], true);
    }

    public static function isGoodsMetric(string $metric): bool
    {
        return in_array($metric, [self::METRIC_GOODS_QTY, self::METRIC_GOODS_AMOUNT], true);
    }

    public static function periods(): array
    {
        return [self::PERIOD_CAMPAIGN, self::PERIOD_MONTH, self::PERIOD_WEEK, self::PERIOD_LIFETIME];
    }

    public static function tableName()
    {
        return '{{%plugin_marketing_trigger}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'campaign_id'], 'required'],
            [['mall_id', 'campaign_id', 'is_delete'], 'integer'],
            [['condition_json'], 'string'],
            [['event'], 'string', 'max' => 30],
        ];
    }

    public function getCondition(): array
    {
        $d = json_decode((string)$this->condition_json, true);
        return is_array($d) ? $d : [];
    }
}
