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
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * UserPath 模型 - 用户关系链垂直分表
 *
 * 这张表专门用于存储用户的无限层级路径，用于解决主表(User)大字段性能问题。
 * 主要用于高效统计团队人数、查找子孙节点。
 *
 * @property int $user_id      用户ID (主键, 关联User表)
 * @property int $mall_id      商城ID
 * @property string $path      关系链路径 (例如: -1-5-88-)
 * @property int $team_count   团队总人数 (冗余统计/缓存字段)
 * @property int $is_delete    是否删除
 * @property int $created_at   创建时间戳
 * @property int $updated_at   更新时间戳
 * @property int $deleted_at   删除时间戳
 * @property string $created_time 创建时间 (Format: Y-m-d H:i:s)
 * @property string $updated_time 更新时间
 * @property string $deleted_time 删除时间
 *
 * @property User $user        关联的主用户模型
 */
class UserPath extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%user_path}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            // 必填字段
            [['user_id', 'mall_id'], 'required'],

            // 整数验证
            [['user_id', 'mall_id', 'team_count', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],

            // Path 字段，因为是 TEXT 类型，只做字符串验证
            [['path'], 'string'],

            // 默认值
            [['team_count', 'is_delete'], 'default', 'value' => 0],

            // 安全字段 (时间字符串通常由数据库默认值处理，或手动设置)
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],

            // 唯一性验证 (确保一个用户只有一条 path 记录)
            [['user_id'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'user_id' => '用户ID',
            'mall_id' => '商城ID',
            'path' => '关系链路径',
            'team_count' => '团队总人数',
            'is_delete' => '是否删除',
            'created_at' => '创建时间',
            'updated_at' => '更新时间',
            'deleted_at' => '删除时间',
            'created_time' => '创建时间(格式化)',
            'updated_time' => '更新时间(格式化)',
            'deleted_time' => '删除时间(格式化)',
        ];
    }

    /**
     * 关联主表 User
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
}