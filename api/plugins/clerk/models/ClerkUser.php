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

namespace app\plugins\clerk\models;

use app\models\BaseActiveRecord;
use app\models\User;

/**
 * This is the model class for table "{{%plugin_clerk_user}}".
 *
 * @property int $id
 * @property int $mall_id
 * @property int $user_id 平台核销员 用户ID
 * @property string $name 真实姓名/备注
 * @property int $status 1启用 0禁用
 * @property int $is_delete 是否删除
 * @property int|null $created_at 创建时间戳
 * @property int|null $updated_at 更新时间
 * @property int|null $deleted_at 删除时间
 * @property string|null $created_time 创建时间
 * @property string|null $updated_time 更新时间
 * @property string|null $deleted_time 删除时间
 *
 * @property User $user
 */
class ClerkUser extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%plugin_clerk_user}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'user_id'], 'required'],
            [['mall_id', 'user_id', 'status', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['name'], 'string', 'max' => 255],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
            [['user_id'], 'unique', 'targetAttribute' => ['mall_id', 'user_id'], 'filter' => ['is_delete' => 0], 'message' => '该用户已被添加为平台核销员。'],
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
            'user_id' => '核销员用户',
            'name' => '人员备注',
            'status' => '状态',
            'is_delete' => '是否删除',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
            'created_time' => 'Created Time',
            'updated_time' => 'Updated Time',
            'deleted_time' => 'Deleted Time',
        ];
    }
    
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}
