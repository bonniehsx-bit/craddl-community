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

use app\events\BankSavedEvent;
use Yii;

/**
 * This is the model class for table "{{%bank}}".
 *
 * @property int $id 主键ID
 * @property int $mall_id 商城ID
 * @property int $user_id 用户ID
 * @property string $name 用户姓名
 * @property string $account 用户账户
 * @property string $bank_name 银行名称
 * @property string|null $bank_branch_name 支行名称
 * @property string $bank_code 银行编号
 * @property int $is_delete 是否删除
 * @property int|null $created_at 创建时间戳
 * @property int|null $updated_at 更新时间
 * @property int|null $deleted_at 删除时间
 * @property string $created_time 创建时间
 * @property string|null $updated_time 更新时间
 * @property string|null $deleted_time 删除时间
 * @property string $id_card 身份证号码
 * @property string $mobile 手机号
 * @property string $id_card_pic1 身份证正面
 * @property string $id_card_pic2 身份证背面
 * @property string $bank_card_pic1 银行卡正面
 * @property string $bank_card_pic2 银行卡背面
 * @property int $is_validity 是否长期有效
 * @property string $begin_date 开始日期
 * @property string $end_date 结束日期
 * @property int|null $province_code 省编码
 * @property int|null $city_code 市编码
 * @property int|null $area_code 区编码
 * @property string|null $province_name 省
 * @property string|null $city_name 市
 * @property string|null $area_name 区
 */
class Bank extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%bank}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'user_id', 'name', 'account', 'id_card', 'mobile'], 'required'],
            [['mall_id', 'user_id', 'is_delete', 'created_at', 'updated_at', 'deleted_at', 'is_validity'], 'integer'],
            [['created_time', 'updated_time', 'deleted_time', 'id_card_pic1', 'id_card_pic2', 'bank_card_pic1', 'bank_card_pic2', 'is_validity', 'begin_date', 'end_date'], 'safe'],
            [['name', 'account', 'bank_name', 'bank_branch_name', 'bank_code', 'province_name', 'city_name', 'area_name'], 'string', 'max' => 45],
            [['id_card'], 'string', 'max' => 64],
            [['mobile','province_code', 'city_code', 'area_code'], 'string', 'max' => 11],
            [['id_card_pic1', 'id_card_pic2', 'bank_card_pic1', 'bank_card_pic2'], 'string', 'max' => 512],
            [['begin_date', 'end_date'], 'string', 'max' => 30],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键ID',
            'mall_id' => '商城ID',
            'user_id' => '用户ID',
            'name' => '用户姓名',
            'account' => '用户账户',
            'bank_name' => '银行名称',
            'bank_branch_name' => '支行名称',
            'bank_code' => '银行编号',
            'is_delete' => '是否删除',
            'created_at' => '创建时间戳',
            'updated_at' => '更新时间',
            'deleted_at' => '删除时间',
            'created_time' => '创建时间',
            'updated_time' => '更新时间',
            'deleted_time' => '删除时间',
            'id_card' => '身份证号码',
            'mobile' => '手机号',
            'id_card_pic1' => '身份证正面',
            'id_card_pic2' => '身份证背面',
            'bank_card_pic1' => '银行卡正面',
            'bank_card_pic2' => '银行卡背面',
            'is_validity' => '是否长期有效',
            'begin_date' => '开始日期',
            'end_date' => '结束日期',
            'province_code' => '省编码',
            'city_code' => '市编码',
            'area_code' => '区编码',
            'province_name' => '省',
            'city_name' => '市',
            'area_name' => '区',
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function afterSave($insert, $changedAttributes)
    {
        $event = new BankSavedEvent();
        $event->bank = $this;
        Yii::$app->trigger(BankSavedEvent::class, $event);
        parent::afterSave($insert, $changedAttributes); // TODO: Change the autogenerated stub
    }

}
