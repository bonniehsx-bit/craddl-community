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
 * This is the model class for table "{{%cat}}".
 *
 * @property int $id
 * @property string $name
 * @property int $mall_id
 * @property string|null $cover_pic 缩略图
 * @property string|null $adv_pic 广告图
 * @property int $is_show
 * @property int $type 0 顶级 1 次级分类
 * @property string|null $link
 * @property int $sort 排序
 * @property int|null $updated_at
 * @property int|null $created_at
 * @property int|null $deleted_at
 * @property int $is_delete
 * @property int $mch_id
 * @property int $parent_id
 * @property int $platform_cat_id 平台商品分类ID

 */
class Cat extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%cat}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['mall_id', 'platform_cat_id', 'is_show', 'type', 'sort', 'updated_at', 'created_at', 'deleted_at', 'is_delete', 'mch_id', 'parent_id'], 'integer'],
            [['name'], 'string', 'max' => 45],
            [['cover_pic', 'adv_pic'], 'string', 'max' => 255],
            [['link'], 'string', 'max' => 128],


        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => '分类名称',
            'mall_id' => '商城ID',
            'cover_pic' => '缩略图',
            'adv_pic' => '广告图',
            'is_show' => '是否显示',
            'type' => '分类级别',
            'platform_cat_id' => '平台商品分类ID',
            'link' => '链接',
            'sort' => '排序',
            'updated_at' => 'Updated At',
            'created_at' => 'Created At',
            'deleted_at' => 'Deleted At',
            'is_delete' => 'Is Delete',
            'mch_id' => '商户ID',
            'parent_id' => '上级分类ID',

        ];
    }

    /**
     * 获取子分类
     * @return \yii\db\ActiveQuery
     */
    public function getChilds()
    {
        return $this->hasMany(Cat::class, ['parent_id' => 'id']);
    }

    /**
     * 获取子分类，并预加载每个子分类下的商品
     * @return \yii\db\ActiveQuery
     */
    public function getChildsWithGoods()
    {
        return $this->hasMany(Cat::class, ['parent_id' => 'id'])
            ->andWhere(['is_delete' => 0, 'is_show' => 1])
            ->orderBy('sort ASC') // 通常按 sort 升序排列
            ->with(['goods' => function ($query) {
                $query->andWhere(['is_delete' => 0, 'status' => 1])
                    ->orderBy('sort ASC')
                    ->limit(6);
            }]);
    }

    /**
     * 获取分类下的商品
     * @return \yii\db\ActiveQuery
     */
    public function getGoods()
    {
        return $this->hasMany(Goods::class, ['cat_id' => 'id']);
    }
}