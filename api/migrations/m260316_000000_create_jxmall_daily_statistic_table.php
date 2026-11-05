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

class m260316_000000_create_jxmall_daily_statistic_table extends Migration
{
    public function up()
    {
        $this->createTable('{{%daily_statistic}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->defaultValue(0)->comment('商城ID'),
            'date' => $this->date()->notNull()->comment('统计日期'),
            'pv_count' => $this->integer()->notNull()->defaultValue(0)->comment('页面访问量PV'),
            'uv_count' => $this->integer()->notNull()->defaultValue(0)->comment('独立访客数UV'),
            'uv_log' => $this->text()->comment('游客UUID记录JSON'),
        ]);
        
        // 并发安全防护核心：唯一索引
        $this->createIndex(
            'idx_mall_date',
            '{{%daily_statistic}}',
            ['mall_id', 'date'],
            true
        );
    }

    public function down()
    {
        $this->dropTable('{{%daily_statistic}}');
    }
}
