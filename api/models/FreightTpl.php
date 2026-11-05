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
 * This is the model class for table "{{%freight_tpl}}".
 *
 * @property int $id
 * @property int $mall_id
 * @property int $mch_id 商户ID, 0表示平台自营  <--- [新增]
 * @property int $enabled
 * @property string $name
 * @property int $is_default 默认模板
 * @property int $price_type 0 按件计费   1 按重量计费
 * @property int $updated_at
 * @property int $created_at
 * @property int $deleted_at
 * @property int $is_delete
 *
 * @property FreightPrice[] $freightPrices 关联的运费规则
 */
class FreightTpl extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%freight_tpl}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'name', 'updated_at', 'created_at', 'deleted_at'], 'required'],
            // [修改] 将 mch_id 加入到 integer 验证规则中
            [['mall_id', 'mch_id', 'enabled', 'is_default', 'price_type', 'updated_at', 'created_at', 'deleted_at', 'is_delete'], 'integer'],
            [['name'], 'string', 'max' => 45],
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
            'mch_id' => '所属商户', // <--- [新增]
            'enabled' => 'Enabled',
            'name' => '模板名称', // [优化] 修改为更友好的中文名
            'is_default' => '默认模板',
            'price_type' => '计费方式', // [优化] 修改为更友好的中文名
            'updated_at' => 'Updated At',
            'created_at' => 'Created At',
            'deleted_at' => 'Deleted At',
            'is_delete' => 'Is Delete',
        ];
    }

    /**
     * 定义运费模板与运费规则 (FreightPrice) 的一对多关系
     * @return \yii\db\ActiveQuery
     */
    public function getFreightPrices()
    {
        return $this->hasMany(FreightPrice::class, ['tpl_id' => 'id']);
    }
}