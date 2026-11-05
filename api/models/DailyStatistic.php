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
 * This is the model class for table "{{%daily_statistic}}".
 *
 * @property int $id
 * @property int $mall_id 商城ID
 * @property string $date 统计日期
 * @property int $pv_count 页面访问量PV
 * @property int $uv_count 独立访客数UV
 * @property string|null $uv_log 游客UUID记录JSON
 * @property string $created_at
 * @property string $updated_at
 */
class DailyStatistic extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%daily_statistic}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'pv_count', 'uv_count'], 'integer'],
            [['date'], 'required'],
            [['date', 'created_at', 'updated_at'], 'safe'],
            [['uv_log'], 'string'],
            [['mall_id', 'date'], 'unique', 'targetAttribute' => ['mall_id', 'date']],
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
            'date' => '统计日期',
            'pv_count' => '页面访问量PV',
            'uv_count' => '独立访客数UV',
            'uv_log' => '游客UUID记录JSON',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }
}
