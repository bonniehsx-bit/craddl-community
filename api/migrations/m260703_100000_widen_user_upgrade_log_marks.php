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
 * Class m260703_100000_widen_user_upgrade_log_marks
 *
 * 加宽 user_upgrade_log.marks:varchar(45) → varchar(255)。
 * 原因:自动升级原因文案(如「两级直推 + 自购A与团队B合计满N件」等新条件)可达 60~100 字,
 * 超过原 45 字上限;写升级日志时校验失败会连累整个升级事务回滚(客户 #85644 即此问题)。
 */
class m260703_100000_widen_user_upgrade_log_marks extends Migration
{
    private $table = '{{%user_upgrade_log}}';

    public function safeUp()
    {
        $col = $this->db->getTableSchema($this->table, true)->getColumn('marks');
        if ($col === null) {
            echo "    marks 列不存在,跳过\n";
            return;
        }
        if ((int)$col->size >= 255) {
            echo "    marks 已 >= 255,跳过\n";
            return;
        }
        $this->alterColumn($this->table, 'marks', $this->string(255)->notNull()->comment('升级原因/备注'));
    }

    public function safeDown()
    {
        // 回滚到 45(若已有 >45 字的数据,MySQL 会截断或报错,开发阶段可接受)
        $col = $this->db->getTableSchema($this->table, true)->getColumn('marks');
        if ($col !== null && (int)$col->size > 45) {
            $this->alterColumn($this->table, 'marks', $this->string(45)->notNull()->comment('升级原因/备注'));
        }
    }
}
