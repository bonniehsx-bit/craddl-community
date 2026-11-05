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
// [核心] 命名空间已按您的要求调整

use app\models\BaseActiveRecord;
use app\models\User;
use Yii;

/**
 * This is the model class for table "{{%plugin_mch_favorite}}".
 *
 * === 业务字段 ===
 * @property int $id
 * @property int $mall_id
 * @property int $user_id 收藏的用户ID
 * @property int $mch_id 被收藏的商户ID
 *
 * === BaseActiveRecord 自动添加的通用字段 ===
 * @property int $is_delete 是否删除
 * @property int|null $created_at 创建时间戳
 * @property int|null $updated_at 更新时间戳
 * @property int|null $deleted_at 删除时间戳
 * @property string $created_time 创建时间
 * @property string|null $updated_time 更新时间
 * @property string|null $deleted_time 删除时间
 *
 * === 关联关系 ===
 * @property User $user 关联的用户模型
 * @property Mch $mch 关联的商户模型
 */
class MchFavorite extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%plugin_mch_favorite}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            // 必填字段
            [['mall_id', 'user_id', 'mch_id'], 'required'],

            // 整数类型字段
            [
                [
                    'mall_id', 'user_id', 'mch_id',
                    'is_delete', 'created_at', 'updated_at', 'deleted_at'
                ],
                'integer'
            ],

            // [核心] 联合唯一性验证，防止重复收藏
            // 'targetAttribute' => null 表示使用当前属性 'mch_id' 和 'user_id' 进行组合验证
            [
                ['mch_id'],
                'unique',
                'targetAttribute' => ['mch_id', 'user_id'],
                'filter' => ['is_delete' => 0],
                'message' => '您已经收藏过该商家了。'
            ],
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
            'user_id' => '收藏用户ID',
            'mch_id' => '收藏商户ID',
            'created_time' => '收藏时间',
        ];
    }

    /**
     * 定义与用户 (User) 模型的一对一关联关系
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        // 注意：User模型在主应用目录下
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * 定义与商户 (Mch) 模型的一对一关联关系
     * @return \yii\db\ActiveQuery
     */
    public function getMch()
    {
        // Mch 模型也在插件目录中
        return $this->hasOne(Mch::class, ['id' => 'mch_id']);
    }
}