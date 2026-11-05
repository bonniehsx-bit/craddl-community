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
 * 创建 Yii2 错误日志监控表
 * 由 Go scheduler 的 StartYiiLogConsumer 异步消费写入
 */
class m260428_100000_create_yii_error_log extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%yii_error_log}}', [
            'id'          => $this->primaryKey()->unsigned(),
            'level'       => $this->string(20)->notNull()->defaultValue('')->comment('日志级别: error/warning'),
            'category'    => $this->string(255)->notNull()->defaultValue('')->comment('Yii2 日志分类'),
            'message'     => $this->text()->null()->comment('错误消息'),
            'trace'       => $this->text()->null()->comment('调用堆栈（前5帧）'),
            'url'         => $this->string(500)->notNull()->defaultValue('')->comment('触发错误的请求URL'),
            'ip'          => $this->string(64)->notNull()->defaultValue('')->comment('客户端IP'),
            'fingerprint' => $this->string(32)->notNull()->defaultValue('')->comment('去重指纹 MD5(level+category+message[:200])'),
            'count'       => $this->integer()->unsigned()->notNull()->defaultValue(1)->comment('24小时内出现次数'),
            'last_seen_at'=> $this->integer()->unsigned()->notNull()->defaultValue(0)->comment('最后出现时间戳'),
        ]);

        $this->createIndex('idx_fingerprint', '{{%yii_error_log}}', 'fingerprint');
        $this->createIndex('idx_level', '{{%yii_error_log}}', 'level');
        $this->createIndex('idx_created_at', '{{%yii_error_log}}', 'created_at');
    }

    public function safeDown()
    {
        $this->dropTable('{{%yii_error_log}}');
    }
}
