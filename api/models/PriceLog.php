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
 * This is the model class for table "{{%price_log}}".
 *
 * @property int $id
 * @property int $mall_id 商城ID
 * @property int $user_id 用户ID
 * @property float $price 金额
 * @property float|null $current 当前剩余
 * @property string|null $order_no 订单号
 * @property int $order_detail_id 订单详情ID
 * @property int $buy_user_id 购买者的ID
 * @property int $type 类型: 0减少 1增加
 * @property string|null $title 标题
 * @property string|null $remarks 备注
 * @property string|null $month_at 月份
 * @property string|null $date_at 日期
 * @property int $created_at 创建时间
 * @property int $updated_at 更新时间
 * @property int $deleted_at 删除时间
 * @property int $is_delete 是否删除
 */
class PriceLog extends BaseActiveRecord
{
    const TYPE_SUB = 0; // 减少
    const TYPE_ADD = 1; // 增加

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%price_log}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'user_id'], 'required'],
            [['mall_id', 'user_id', 'order_detail_id', 'buy_user_id', 'type', 'created_at', 'updated_at', 'deleted_at', 'is_delete'], 'integer'],
            [['price', 'current'], 'number'],
            [['date_at'], 'safe'], // date 类型通常用 safe 或 date 验证器
            [['order_no', 'title'], 'string', 'max' => 45],
            [['remarks'], 'string', 'max' => 128],
            [['month_at'], 'string', 'max' => 10], // 数据库定义为 string(10)
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
            'user_id' => '用户ID',
            'price' => '金额',
            'current' => '当前剩余',
            'remarks' => '备注',
            'order_detail_id' => '订单详情ID',
            'buy_user_id' => '购买者的ID',
            'order_no' => '订单号',
            'type' => '类型', // 0减少 1增加
            'title' => '标题',
            'month_at' => '月份',
            'date_at' => '日期',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
            'deleted_at' => '删除时间',
            'is_delete' => '是否删除',
        ];
    }

    /**
     * 保存前的钩子
     * 自动补充月份和日期字段
     * @param bool $insert
     * @return bool
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            // 如果没有手动设置 month_at，则自动生成
            if (empty($this->month_at)) {
                $this->month_at = date('Y-m');
            }
            // 如果没有手动设置 date_at，则自动生成
            if (empty($this->date_at)) {
                $this->date_at = date('Y-m-d');
            }
            return true;
        }
        return false;
    }
}