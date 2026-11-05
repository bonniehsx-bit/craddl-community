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
 * 调度器死信任务表
 * Go scheduler 的延迟任务重试耗尽后，由 Go 直接写入此表；
 * 后台「失败任务」可人工重投 / 忽略。
 */
class m260522_170000_create_schedule_dead_job extends Migration
{
    public function up()
    {
        $this->createTable('{{%schedule_dead_job}}', [
            'id'         => $this->primaryKey()->unsigned()->comment('ID'),
            'job_class'  => $this->string(191)->notNull()->defaultValue('')->comment('任务类名'),
            'payload'    => $this->text()->comment('原始 payload JSON（重投用）'),
            'attempt'    => $this->integer()->notNull()->defaultValue(0)->comment('已尝试次数'),
            'last_error' => $this->string(500)->notNull()->defaultValue('')->comment('最后一次失败原因'),
            'status'     => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('0待处理 1已重投 2已忽略'),
            'failed_at'  => $this->integer(11)->notNull()->defaultValue(0)->comment('失败时间戳'),
        ], null, '调度器死信任务');

        $this->createIndex('idx-schedule_dead_job-status', '{{%schedule_dead_job}}', 'status');
    }

    public function down()
    {
        $this->dropTable('{{%schedule_dead_job}}');
    }
}
