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
use Yii;

/**
 * 这里是门店分类不是门店商品分类 商品分类使用商品分类表 Cat
 * This is the model class for table "{{%plugin_mch_cat}}".
 *
 * @property int $id ID
 * @property int $mall_id mall_id
 * @property string $cover_pic 图标
 * @property string $cat_name 分类名称
 * @property int $is_show 是否显示 1=显示 0=隐藏  <-- [ 新增 ]
 * @property int $is_delete 是否删除
 * @property int|null $created_at 创建时间戳
 * @property int|null $updated_at 更新时间
 * @property int|null $deleted_at 删除时间
 * @property string $created_time 创建时间
 * @property string|null $updated_time 更新时间
 * @property string|null $deleted_time 删除时间
 * @property integer $order_num
 */
class MchCat extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%plugin_mch_cat}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id'], 'required'],
            // --- [ 修改 ] ---
            // 将 is_show 添加到 integer 验证
            [['mall_id', 'is_delete', 'created_at', 'updated_at', 'deleted_at', 'order_num', 'is_show'], 'integer'],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
            [['cover_pic'], 'string', 'max' => 512],
            [['cat_name'], 'string', 'max' => 45],

            // --- [ 新增 ] ---
            // 为 is_show 设置模型层面的默认值
            ['is_show', 'default', 'value' => 1],
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
            'cover_pic' => '图标',
            'cat_name' => '分类名称',
            'is_show' => '是否显示', // <-- [ 新增 ]
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