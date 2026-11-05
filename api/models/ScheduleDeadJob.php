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
 * 调度器死信任务
 *
 * @property int $id
 * @property string $job_class 任务类名
 * @property string $payload 原始 payload JSON
 * @property int $attempt 已尝试次数
 * @property string $last_error 最后一次失败原因
 * @property int $status 0待处理 1已重投 2已忽略
 * @property int $failed_at 失败时间戳
 * @property int $is_delete
 * @property int $created_at
 * @property int $updated_at
 * @property int $deleted_at
 * @property string $created_time
 * @property string $updated_time
 * @property string $deleted_time
 */
class ScheduleDeadJob extends BaseActiveRecord
{
    const STATUS_PENDING = 0; // 待处理
    const STATUS_RETRIED = 1; // 已重投
    const STATUS_IGNORED = 2; // 已忽略

    public static function tableName()
    {
        return '{{%schedule_dead_job}}';
    }

    public function rules()
    {
        return [
            [['attempt', 'status', 'failed_at', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['payload'], 'string'],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
            [['job_class'], 'string', 'max' => 191],
            [['last_error'], 'string', 'max' => 500],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'         => 'ID',
            'job_class'  => '任务类名',
            'payload'    => 'payload',
            'attempt'    => '尝试次数',
            'last_error' => '失败原因',
            'status'     => '状态',
            'failed_at'  => '失败时间',
        ];
    }
}
