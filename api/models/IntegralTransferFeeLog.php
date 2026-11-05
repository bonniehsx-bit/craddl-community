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

use Yii;

/**
 * This is the model class for table "{{%integral_transfer_fee_log}}".
 *
 * === 业务字段 ===
 * @property int $id
 * @property int $mall_id
 * @property int $sender_id 转出方用户ID
 * @property int $recipient_id 接收方用户ID
 * @property float $transfer_amount 本次转赠的金额
 * @property float $service_charge_rate 当时的手续费率(%)
 * @property float $fee 实际收取的手续费
 * @property int $integral_log_id 关联的转出方积分扣除日志ID
 *
 * === 关联关系 ===
 * @property User $sender 关联的转出方用户信息
 * @property User $recipient 关联的接收方用户信息
 * @property IntegralLog $integralLog 关联的积分流水
 */
class IntegralTransferFeeLog extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%integral_transfer_fee_log}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            // 必填字段
            [['mall_id', 'sender_id', 'recipient_id', 'transfer_amount', 'service_charge_rate', 'fee'], 'required'],

            // 整数类型字段
            [['mall_id', 'sender_id', 'recipient_id', 'integral_log_id'], 'integer'],

            // 数值类型字段
            [['transfer_amount', 'service_charge_rate', 'fee'], 'number'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mall_id' => '商城ID',
            'sender_id' => '转出用户',
            'recipient_id' => '接收用户',
            'transfer_amount' => '转赠金额',
            'service_charge_rate' => '手续费率(%)',
            'fee' => '手续费',
            'integral_log_id' => '关联积分日志ID',
        ];
    }

    /**
     * 关联转出方用户
     * @return \yii\db\ActiveQuery
     */
    public function getSender()
    {
        return $this->hasOne(User::class, ['id' => 'sender_id']);
    }

    /**
     * 关联接收方用户
     * @return \yii\db\ActiveQuery
     */
    public function getRecipient()
    {
        return $this->hasOne(User::class, ['id' => 'recipient_id']);
    }

    /**
     * 关联积分流水
     * @return \yii\db\ActiveQuery
     */
    public function getIntegralLog()
    {
        return $this->hasOne(IntegralLog::class, ['id' => 'integral_log_id']);
    }
}