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
 * This is the model class for table "{{%mch_goods_cat_rel}}".
 *
 * @property int $id
 * @property int $mall_id 商城ID
 * @property int $mch_id 商户ID
 * @property int $goods_id 商品ID
 * @property int $cat_id 分类ID
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property int $is_delete
 * @property string $created_time
 * @property string $updated_time
 * @property string $deleted_time
 */
class MchGoodsCatRel extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%mch_goods_cat_rel}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'mch_id', 'goods_id', 'cat_id', 'created_at', 'updated_at', 'deleted_at', 'is_delete'], 'integer'],
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
            'mch_id' => '商户ID',
            'goods_id' => '商品ID',
            'cat_id' => '分类ID',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
            'is_delete' => 'Is Delete',
            'created_time' => 'Created Time',
            'updated_time' => 'Updated Time',
            'deleted_time' => 'Deleted Time',
        ];
    }
}
