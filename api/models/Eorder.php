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
 * This is the model class for table "{{%eorder}}".
 *
 * @property int $id
 * @property int $mall_id
 * @property string $name
 * @property string $express_key 快递公司key
 * @property string|null $account
 * @property string|null $password
 * @property string $month_code
 * @property string $shop_name 网点
 * @property string $tpl_style 模板样式
 * @property int $pay_type 付款方式
 * @property int $to_home 到家揽间
 * @property int|null $created_at
 * @property int|null $deleted_at
 * @property int|null $updated_at
 * @property int $is_delete
 * @property int $is_default
 * @property string|null $express
 * @property string $shop_code
 * @property string $sender_name 发件人名字
 * @property string $sender_mobile 发件手机号
 * @property string $sender_province 发件省份
 * @property string $sender_city 发件人城市
 * @property string $sender_county 发件人区域
 * @property string $sender_address 发件人地址
 */
class Eorder extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%eorder}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'name', 'express_key', 'month_code', 'shop_name', 'tpl_style', 'shop_code', 'sender_name', 'sender_mobile', 'sender_province', 'sender_city', 'sender_county', 'sender_address'], 'required'],
            [['mall_id', 'pay_type', 'to_home', 'created_at', 'deleted_at', 'updated_at', 'is_delete', 'is_default'], 'integer'],
            [['name', 'account', 'password', 'month_code', 'shop_name', 'tpl_style', 'shop_code', 'sender_name', 'sender_mobile', 'sender_province', 'sender_city', 'sender_county', 'sender_address'], 'string', 'max' => 45],
            [['express_key'], 'string', 'max' => 10],
            [['express'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mall_id' => 'Mall ID',
            'name' => 'Name',
            'express_key' => '快递公司key',
            'account' => 'Account',
            'password' => 'Password',
            'month_code' => 'Month Code',
            'shop_name' => '网点',
            'tpl_style' => '模板样式',
            'pay_type' => '付款方式',
            'to_home' => '到家揽间',
            'created_at' => 'Created At',
            'deleted_at' => 'Deleted At',
            'updated_at' => 'Updated At',
            'is_delete' => 'Is Delete',
            'is_default' => 'Is Default',
            'express' => 'Express',
            'shop_code' => 'Shop Code',
            'sender_name' => '发件人名字',
            'sender_mobile' => '发件手机号',
            'sender_province' => '发件省份',
            'sender_city' => '发件人城市',
            'sender_county' => '发件人区域',
            'sender_address' => '发件人地址',
        ];
    }
}
