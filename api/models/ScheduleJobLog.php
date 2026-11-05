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
 * 调度器任务执行审计流水
 *
 * @property int $id
 * @property string $job_class 任务类名
 * @property string $source 来源 queue/cron
 * @property int $success 1成功 0失败
 * @property int $attempt 第几次尝试
 * @property int $duration_ms 执行耗时(毫秒)
 * @property string $error 失败原因
 * @property int $executed_at 执行时间戳
 * @property int $is_delete
 * @property int $created_at
 * @property int $updated_at
 * @property int $deleted_at
 * @property string $created_time
 * @property string $updated_time
 * @property string $deleted_time
 */
class ScheduleJobLog extends BaseActiveRecord
{
    public static function tableName()
    {
        return '{{%schedule_job_log}}';
    }

    public function rules()
    {
        return [
            [['success', 'attempt', 'duration_ms', 'executed_at', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
            [['job_class'], 'string', 'max' => 191],
            [['source'], 'string', 'max' => 10],
            [['error'], 'string', 'max' => 500],
        ];
    }
}
