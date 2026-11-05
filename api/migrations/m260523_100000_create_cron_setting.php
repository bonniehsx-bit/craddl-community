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
 * Cron 任务覆盖配置表
 * 代码定义的 cron（Task::rule()）为默认值；本表是后台可视化编辑的覆盖层：
 * 无覆盖行 = 用代码默认；有行 = 按行的 enabled / expression 覆盖。
 * crontab/sync 合并后写入 Redis。
 */
class m260523_100000_create_cron_setting extends Migration
{
    public function up()
    {
        $this->createTable('{{%cron_setting}}', [
            'id'         => $this->primaryKey()->unsigned()->comment('ID'),
            'job_key'    => $this->string(191)->notNull()->defaultValue('')->comment('任务标识 class@method'),
            'enabled'    => $this->tinyInteger(1)->notNull()->defaultValue(1)->comment('1启用 0禁用'),
            'expression' => $this->string(100)->notNull()->defaultValue('')->comment('自定义 cron 表达式，空=用代码默认'),
        ], null, 'Cron 任务覆盖配置');

        $this->createIndex('uk-cron_setting-job_key', '{{%cron_setting}}', 'job_key', true);
    }

    public function down()
    {
        $this->dropTable('{{%cron_setting}}');
    }
}
