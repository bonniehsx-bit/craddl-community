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
 * This is the model class for table "{{%admin_role}}".
 *
 * @property int $id
 * @property int|null $admin_id admin_id
 * @property integer|null $role_id 权限ID
 * @property int|null $updated_at 更新时间
 * @property int $deleted_at 删除时间
 * @property int|null $created_at 创建时间
 * @property int|null $is_delete 是否删除
 * @property string $created_time 创建时间
 */
class AdminRole extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%admin_role}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['admin_id', 'updated_at', 'deleted_at', 'created_at', 'is_delete','role_id'], 'integer'],
            [['created_time'], 'safe'],

        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'admin_id' => 'admin_id',
            'authority_id' => '权限ID',
            'updated_at' => '更新时间',
            'deleted_at' => '删除时间',
            'created_at' => '创建时间',
            'is_delete' => '是否删除',
            'created_time' => '创建时间',
        ];
    }
}
