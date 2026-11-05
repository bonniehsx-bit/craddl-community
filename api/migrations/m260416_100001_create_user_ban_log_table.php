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

/**
 * 封禁/解封操作审计日志表
 *
 * 后台每次对用户执行 ban / unban / anonymize 操作都会往这里写一条,
 * 后续可在后台按用户查历史 —— 解决"是谁什么时候为什么封的,何时解的"。
 *
 * action 枚举:
 *  - ban        后台主动拉黑
 *  - unban      后台手动解封
 *  - auto_unban 到期自动解封 (login 时 lazy 触发, operator_id = 0)
 *  - anonymize  注销冷却期满后 PII 匿名化 (cron 触发, operator_id = 0)
 */
class m260416_100001_create_user_ban_log_table extends \app\core\Migration
{
    public function safeUp()
    {
        $columns = [
            'id'           => $this->primaryKey(11)->comment('主键ID'),
            'user_id'      => $this->integer(11)->notNull()->defaultValue(0)->comment('被操作用户ID'),
            'action'       => $this->string(16)->notNull()->defaultValue('')->comment('操作: ban/unban/auto_unban/anonymize'),
            'reason'       => $this->string(255)->notNull()->defaultValue('')->comment('原因'),
            'operator_id'  => $this->integer(11)->notNull()->defaultValue(0)->comment('操作人 admin.id, 0=系统'),
            'operator_name' => $this->string(64)->notNull()->defaultValue('')->comment('操作人名称快照'),
            'banned_until' => $this->integer(11)->null()->defaultValue(null)->comment('本次封禁的解封时间戳, NULL=永久'),
            'snapshot'     => $this->text()->null()->comment('操作前的状态快照 (JSON)'),
        ];

        $this->createTable('{{%user_ban_log}}', $columns, null, '用户封禁/解封操作审计');
    }

    public function safeDown()
    {
        $this->dropTable('{{%user_ban_log}}');
    }
}
