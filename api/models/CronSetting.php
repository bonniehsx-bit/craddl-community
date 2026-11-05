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

/**
 * Cron 任务覆盖配置
 *
 * @property int $id
 * @property string $job_key 任务标识 class@method
 * @property int $enabled 1启用 0禁用
 * @property string $expression 自定义 cron 表达式（空=用代码默认）
 * @property int $is_delete
 * @property int $created_at
 * @property int $updated_at
 * @property int $deleted_at
 * @property string $created_time
 * @property string $updated_time
 * @property string $deleted_time
 */
class CronSetting extends BaseActiveRecord
{
    public static function tableName()
    {
        return '{{%cron_setting}}';
    }

    public function rules()
    {
        return [
            [['enabled', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
            [['job_key'], 'string', 'max' => 191],
            [['expression'], 'string', 'max' => 100],
        ];
    }
}
