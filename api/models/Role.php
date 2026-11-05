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
 * This is the model class for table "{{%role}}".
 *
 * @property int $id
 * @property int $mall_id 商城ID
 * @property string|null $role_name role_name
 * @property string|null $role_key role_key
 * @property int|null $role_sort 排序
 * @property string|null $remark 备注
 * @property int|null $status 状态
 * @property int|null $menu_check_strictly menu_check_strictly
 * @property int|null $admin  是否是管理员
 * @property int|null $updated_at 更新时间
 * @property int $deleted_at 删除时间
 * @property int|null $created_at 创建时间
 * @property int|null $is_delete 是否删除
 * @property string $created_time 创建时间
 * @property integer $created_by 创建人
 */
class Role extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%role}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            // 这里加上了 'mall_id'
            [['role_sort','admin', 'status', 'menu_check_strictly', 'updated_at', 'deleted_at', 'created_at', 'is_delete','created_by', 'mall_id'], 'integer'],
            [['created_time'], 'safe'],
            [['role_name'], 'string', 'max' => 45],
            [['role_key', 'remark'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mall_id' => '商城ID', // 新增
            'role_name' => 'role_name',
            'role_key' => 'role_key',
            'role_sort' => '排序',
            'remark' => '备注',
            'status' => '状态',
            'menu_check_strictly' => 'menu_check_strictly',
            'admin' => '管理员',
            'updated_at' => '更新时间',
            'deleted_at' => '删除时间',
            'created_at' => '创建时间',
            'is_delete' => '是否删除',
            'created_time' => '创建时间',
            'created_by'=>'创建人'
        ];
    }
}