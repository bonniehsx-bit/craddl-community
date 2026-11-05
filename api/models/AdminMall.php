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
 * This is the model class for table "jxmall_admin_mall".
 *
 * @property int $id
 * @property int $mall_id
 * @property int $admin_id
 * @property string $role founder      manager   operator
 * @property int|null $created_at
 * @property int|null $is_delete
 * @property int|null $deleted_at
 * @property int|null $updated_at
 */
class AdminMall extends BaseActiveRecord
{
    const FOUNDER = 'founder';
    const MANAGER = 'manager';
    const OPERATOR = 'operator';
    const MCH='mch';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'jxmall_admin_mall';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'admin_id', 'role'], 'required'],
            [['mall_id', 'admin_id', 'created_at', 'is_delete', 'deleted_at', 'updated_at'], 'integer'],
            [['role'], 'string', 'max' => 10],
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
            'admin_id' => 'Admin ID',
            'role' => 'Role',
            'created_at' => 'Created At',
            'is_delete' => 'Is Delete',
            'deleted_at' => 'Deleted At',
            'updated_at' => 'Updated At',
        ];
    }
}
