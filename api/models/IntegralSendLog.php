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

namespace app\models;

/**
 * 积分赠送记录 / 追回锚点。表 {{%integral_send_log}}。
 *
 * @property int $id
 * @property int $mall_id
 * @property int $user_id          赠送对象(下单买家)
 * @property int $order_id
 * @property int $order_detail_id  追回锚点
 * @property int $goods_id
 * @property float $integral       赠送积分数(追回时按此扣回)
 * @property int $timing           赠送时机:0=结算后 1=支付即赠
 * @property int $status           1=已赠送 2=已追回
 * @property int $integral_log_id  对应发放流水
 * @property int $granted_at
 * @property int $reverted_at
 */
class IntegralSendLog extends BaseActiveRecord
{
    /** 赠送时机 */
    const TIMING_SETTLE = 0; // 订单结算后赠送
    const TIMING_PAY    = 1; // 支付成功即赠送

    /** 状态 */
    const STATUS_GRANTED  = 1; // 已赠送
    const STATUS_REVERTED = 2; // 已追回

    public static function tableName()
    {
        return '{{%integral_send_log}}';
    }

    public function rules()
    {
        return [
            [['user_id', 'order_id', 'order_detail_id'], 'required'],
            [['mall_id', 'user_id', 'order_id', 'order_detail_id', 'goods_id', 'timing', 'status', 'integral_log_id', 'granted_at', 'reverted_at'], 'integer'],
            [['integral'], 'number'],
        ];
    }
}
