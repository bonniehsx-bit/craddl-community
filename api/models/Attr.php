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

use app\helpers\RedisHelper;

/**
 * This is the model class for table "{{%attr}}".
 *
 * @property int $id
 * @property int|null $mall_id mall_id
 * @property string|null $attr_name 规格名称
 * @property int|null $stock 库存
 * @property float|null $price 售价
 * @property float|null $cost_price 成本价
 * @property float|null $purchase_price 采购价（商家拿货价）
 * @property string|null $cover_pic 图标
 * @property string|null $spec_data 规格组合详情 (JSON格式) [新增]
 * @property string|null $no SKU商品编码 [新增]
 * @property float|null $weight SKU重量(kg) [新增]
 * @property int|null $goods_id 商品ID
 * @property int|null $platform_attr_id 平台规格id
 * @property int $is_delete 是否删除
 * @property int|null $created_at 创建时间戳
 * @property int|null $updated_at 更新时间
 * @property int|null $deleted_at 删除时间
 * @property string $created_time 创建时间
 * @property string|null $updated_time 更新时间
 * @property string|null $deleted_time 删除时间
 */
class Attr extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%attr}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'platform_attr_id', 'stock', 'goods_id', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['price', 'weight', 'cost_price', 'purchase_price'], 'number'], // [修改] weight 也为 number 类型
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
            [['attr_name'], 'string', 'max' => 64],
            [['cover_pic'], 'string', 'max' => 512],
            [['no'], 'string', 'max' => 255], // [新增] no 的验证规则
            [['spec_data'], 'safe'], // [新增] spec_data 为安全属性，允许接收JSON字符串
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mall_id' => 'mall_id',
            'platform_attr_id' => '平台规格ID',
            'attr_name' => '规格名称',
            'stock' => '库存',
            'price' => '售价',
            'cost_price' => '成本价',
            'purchase_price' => '采购价',
            'cover_pic' => '图标',
            'spec_data' => '规格组合详情', // [新增]
            'no' => 'SKU商品编码', // [新增]
            'weight' => 'SKU重量(kg)', // [新增]
            'goods_id' => '商品ID',
            'is_delete' => '是否删除',
            'created_at' => '创建时间戳',
            'updated_at' => '更新时间',
            'deleted_at' => '删除时间',
            'created_time' => '创建时间',
            'updated_time' => '更新时间',
            'deleted_time' => '删除时间',
        ];
    }

    public static function getGoodsAttr($goods_id)
    {
        $list = Attr::find()->andWhere(['is_delete' => 0, 'goods_id' => $goods_id])->asArray()->all();
        return $list;
    }


    public static function getStock($id)
    {
        $num = self::find()->andWhere(['id' => $id])->sum('stock');
        return $num ?? 0;
    }

}