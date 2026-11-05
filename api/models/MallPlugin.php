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
 * This is the model class for table "{{%mall_plugin}}".
 *
 * @property int $id
 * @property int|null $mall_id mall_id
 * @property string|null $plugin 插件名
 * @property int $is_delete 是否删除
 * @property int|null $created_at 创建时间戳
 * @property int|null $updated_at 更新时间
 * @property int|null $deleted_at 删除时间
 * @property string $created_time 创建时间
 * @property string|null $updated_time 更新时间
 * @property string|null $deleted_time 删除时间
 */
class MallPlugin extends BaseActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%mall_plugin}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
            [['plugin'], 'string', 'max' => 45],
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
            'plugin' => '插件名',
            'is_delete' => '是否删除',
            'created_at' => '创建时间戳',
            'updated_at' => '更新时间',
            'deleted_at' => '删除时间',
            'created_time' => '创建时间',
            'updated_time' => '更新时间',
            'deleted_time' => '删除时间',
        ];
    }

    /**
     * 当前商城已开通的插件名列表（含基座 'mall'；已删插件/关系自动过滤）。
     * 统一 SaaS 插件范围查询，避免散落多处重复。
     * @param int $mallId
     * @return string[]
     */
    public static function enabledPlugins($mallId): array
    {
        $purchased = self::find()->alias('m')
            ->innerJoin(['p' => Plugin::tableName()], 'p.name=m.plugin')
            ->andWhere(['m.mall_id' => $mallId, 'm.is_delete' => 0, 'p.is_delete' => 0])
            ->select('m.plugin')
            ->column();
        return array_merge(['mall'], $purchased);
    }
}
