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
 * This is the model class for table "{{%queue}}".
 *
 * @property int $id
 * @property string $channel
 * @property resource $job
 * @property int $pushed_at
 * @property int $ttr
 * @property int $delay
 * @property int $priority
 * @property int|null $reserved_at
 * @property int|null $attempt
 * @property int|null $done_at
 * @property string|null $class class
 */
class Queue extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%queue}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['channel', 'job', 'pushed_at', 'ttr'], 'required'],
            [['job'], 'string'],
            [['pushed_at', 'ttr', 'delay', 'priority', 'reserved_at', 'attempt', 'done_at'], 'integer'],
            [['channel'], 'string', 'max' => 255],
            [['class'], 'string', 'max' => 128],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'channel' => 'Channel',
            'job' => 'job',
            'pushed_at' => 'Pushed At',
            'ttr' => 'Ttr',
            'delay' => 'Delay',
            'priority' => 'Priority',
            'reserved_at' => 'Reserved At',
            'attempt' => 'Attempt',
            'done_at' => 'Done At',
            'class' => 'class',
        ];
    }
}
