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
 * This is the model class for table "{{%search_log}}".
 *
 * @property int $id
 * @property int $mall_id
 * @property int $user_id 搜索用户ID, 0为游客
 * @property string $keyword 搜索关键词
 * @property int $created_at 创建时间戳
 */
class SearchLog extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%search_log}}';
    }

    /**
     * {@inheritdoc}
     *
     * 定义模型的验证规则
     * `required` 确保了核心数据的完整性
     * `integer` 和 `string` 进行了数据类型校验
     */
    public function rules()
    {
        return [
            [['mall_id', 'keyword', 'created_at'], 'required'],
            [['mall_id', 'user_id', 'created_at'], 'integer'],
            [['keyword'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * 定义字段的用户友好标签
     * 在后台管理等场景中会用到
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mall_id' => '商城ID',
            'user_id' => '用户ID',
            'keyword' => '关键词',
            'created_at' => '搜索时间',
        ];
    }
}