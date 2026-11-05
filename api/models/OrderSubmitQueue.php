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
 * This is the model class for table "{{%order_submit_queue}}".
 *
 * @property int $id
 * @property int $mall_id 商城ID
 * @property int $user_id 用户ID
 * @property string $pre_order_no 唯一预订单号
 * @property string $form_data 序列化的订单提交表单数据
 * @property int $status 状态 0=待处理, 1=成功, 2=失败
 * @property string|null $order_no 成功后生成的正式订单号
 * @property string|null $pay_data_key 支付数据key
 * @property string|null $error_msg 失败时的错误信息
 * @property int $is_delete 是否删除
 * @property int $created_at 创建时间戳
 * @property int $updated_at 更新时间
 * @property int $deleted_at 删除时间
 * @property string $created_time 创建时间
 * @property string $updated_time 更新时间
 * @property string $deleted_time 删除时间
 */
class OrderSubmitQueue extends BaseActiveRecord
{
    const STATUS_PENDING = 0;
    const STATUS_SUCCESS = 1;
    const STATUS_FAILED  = 2;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%order_submit_queue}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'user_id', 'pre_order_no', 'form_data'], 'required'],
            [['mall_id', 'user_id', 'status', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['form_data', 'error_msg'], 'string'],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
            [['pre_order_no', 'order_no', 'pay_data_key'], 'string', 'max' => 255],
            [['pre_order_no'], 'unique'],
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
            'pre_order_no' => '预订单号',
            'form_data' => '表单数据',
            'status' => '状态',
            'order_no' => '正式订单号',
            'pay_data_key' => '支付数据Key',
            'error_msg' => '错误信息',
            'is_delete' => '是否删除',
            'created_at' => '创建时间戳',
            'updated_at' => '更新时间',
            'deleted_at' => '删除时间',
            'created_time' => '创建时间',
            'updated_time' => '更新时间',
            'deleted_time' => '删除时间',
        ];
    }
}

