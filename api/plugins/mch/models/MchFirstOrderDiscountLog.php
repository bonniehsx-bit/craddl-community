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

namespace app\plugins\mch\models;

use app\models\BaseActiveRecord;
use app\models\Order;
use app\models\User;

/**
 * This is the model class for table "{{%plugin_mch_first_order_discount_log}}".
 *
 * @property int $id
 * @property int $mall_id
 * @property int $mch_id 商家ID
 * @property int $user_id 享受优惠的用户ID
 * @property int $order_id 关联的订单ID
 * @property float $discount_amount 本次优惠的金额
 *
 * --- 基类字段 ---
 * @property int $is_delete
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property string $created_time
 * @property string|null $updated_time
 * @property string|null $deleted_time
 *
 * --- 关联关系 ---
 * @property Order $order 关联的订单模型
 * @property User $user 关联的用户模型
 * @property Mch $mch 关联的商家模型
 */
class MchFirstOrderDiscountLog extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%plugin_mch_first_order_discount_log}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'mch_id', 'user_id', 'order_id', 'discount_amount'], 'required'],
            [['mall_id', 'mch_id', 'user_id', 'order_id', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['discount_amount'], 'number'],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
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
            'mch_id' => '商家ID',
            'user_id' => '用户ID',
            'order_id' => '订单ID',
            'discount_amount' => '优惠金额',
            'is_delete' => 'Is Delete',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
            'created_time' => '创建时间',
            'updated_time' => '更新时间',
            'deleted_time' => '删除时间',
        ];
    }

    /**
     * 定义与订单表 (Order) 的一对一关联关系
     * @return \yii\db\ActiveQuery
     */
    public function getOrder()
    {
        return $this->hasOne(Order::class, ['id' => 'order_id']);
    }

    /**
     * 定义与用户表 (User) 的一对一关联关系
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * 定义与商家表 (Mch) 的一对一关联关系
     * @return \yii\db\ActiveQuery
     */
    public function getMch()
    {
        return $this->hasOne(Mch::class, ['id' => 'mch_id']);
    }
}