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
 * This is the model class for table "{{%level}}".
 *
 * @property int $id
 * @property int $mall_id
 * @property string $name
 * @property int $level 权重
 * @property int $is_delete
 * @property int|null $created_at
 * @property int|null $deleted_at
 * @property int|null $updated_at
 * @property string|null $pic_url
 * @property string|null $detail 说明
 * @property int $enabled
 * @property int $is_auto
 * @property int $is_discount
 * @property int $discount
 * @property int $buy_type 0 支付 1订单完成
 * @property integer $is_inviter 是否具有推广权限
 */
class Level extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%level}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'name', 'level'], 'required'],
            [['discount'], 'number', 'max' => 10],
            [['mall_id', 'is_inviter', 'buy_type', 'is_discount', 'level', 'is_delete', 'created_at', 'deleted_at', 'updated_at', 'enabled', 'is_auto', 'upgrade_logic'], 'integer'],
            [['name'], 'string', 'max' => 45],
            [['pic_url'], 'string', 'max' => 255],
            [['detail'], 'string', 'max' => 256],
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
            'level' => 'Level',
            'is_delete' => 'Is Delete',
            'created_at' => 'Created At',
            'deleted_at' => 'Deleted At',
            'updated_at' => 'Updated At',
            'pic_url' => 'Pic Url',
            'detail' => '说明',
            'enabled' => '是否启用',
            'is_auto' => '自动升级',
            'is_discount' => '是否折扣',
            'discount' => '折扣',
            'buy_type' => '0 支付 1订单完成',
            'is_inviter' => '是否具有推广权限'
        ];
    }
}
