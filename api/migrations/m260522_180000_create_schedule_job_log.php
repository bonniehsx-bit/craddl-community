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

use app\core\Migration;

/**
 * 调度器任务执行审计流水表
 * Go scheduler 每派发执行一个任务（queue / cron）写一条，
 * 供后台「调度中心」看板统计与排障；由 Go 定期清理过期日志。
 */
class m260522_180000_create_schedule_job_log extends Migration
{
    public function up()
    {
        $this->createTable('{{%schedule_job_log}}', [
            'id'          => $this->primaryKey()->unsigned()->comment('ID'),
            'job_class'   => $this->string(191)->notNull()->defaultValue('')->comment('任务类名'),
            'source'      => $this->string(10)->notNull()->defaultValue('')->comment('来源 queue/cron'),
            'success'     => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('1成功 0失败'),
            'attempt'     => $this->integer()->notNull()->defaultValue(0)->comment('第几次尝试'),
            'duration_ms' => $this->integer()->notNull()->defaultValue(0)->comment('执行耗时(毫秒)'),
            'error'       => $this->string(500)->notNull()->defaultValue('')->comment('失败原因'),
            'executed_at' => $this->integer(11)->notNull()->defaultValue(0)->comment('执行时间戳'),
        ], null, '调度器任务执行流水');

        $this->createIndex('idx-schedule_job_log-executed_at', '{{%schedule_job_log}}', 'executed_at');
        $this->createIndex('idx-schedule_job_log-success', '{{%schedule_job_log}}', 'success');
    }

    public function down()
    {
        $this->dropTable('{{%schedule_job_log}}');
    }
}
